<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Разбор ответа LLM и офлайн-шаблоны техпроцесса (erp228_tech_cards).
 *
 * Сервис без состояния и без обращений к БД — его можно проверять тестами
 * на строках. Задача: превратить свободный ответ модели (JSON, JSON в ```фенсе,
 * markdown) в единый набор данных документа:
 *
 *     array(
 *         'content_markdown' => '...',
 *         'structured_data'  => array(...),   // дерево для виджетов
 *         'operations'       => array(        // шаги техпроцесса
 *             array('op_name' => ..., 'op_description' => ..., 'duration_sec' => ..., 'cost_amount' => ..., 'params' => array()),
 *         ),
 *     )
 *
 * Если ответ модели разобрать не удалось, работает `from_markdown()`: каждая
 * значимая строка списка становится операцией. Полностью офлайн-режим
 * (`offline()`) строит техпроцесс по шаблону типа производства — это позволяет
 * пользоваться модулем без настроенного ключа LLM.
 *
 * @package    Erp228
 * @category   Services
 */
class Service_Erp228_Parser
{
    /** @var Service_Erp228_Parser */
    protected static $_instance;

    /**
     * @return Service_Erp228_Parser
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // -------------------------------------------------------------------------
    //  Разбор ответа LLM
    // -------------------------------------------------------------------------

    /**
     * Разобрать ответ модели в структуру документа.
     *
     * @param  string|array $answer
     * @return array
     */
    public function parse($answer)
    {
        $data = is_array($answer) ? $answer : NULL;

        if ($data === NULL)
        {
            $data = $this->_json((string) $answer);
        }

        if ($data === NULL)
        {
            // Модель ответила текстом — считаем его описанием, шаги берём из строк
            return array(
                'content_markdown' => trim((string) $answer),
                'structured_data'  => array(),
                'operations'       => $this->from_markdown((string) $answer),
            );
        }

        $markdown = $this->_first_of($data, array('content_markdown', 'markdown', 'content', 'description', 'text'));
        $steps    = $this->_first_of($data, array('operations', 'steps', 'process', 'tech_process'));

        if ( ! is_array($steps))
        {
            $steps = array();
        }

        $operations = array();

        foreach (array_values($steps) as $index => $step)
        {
            if (is_string($step))
            {
                $step = array('op_name' => $step);
            }

            if ( ! is_array($step))
            {
                continue;
            }

            $operations[] = $this->_normalize_operation($step, $index);
        }

        if ( ! $operations AND $markdown)
        {
            $operations = $this->from_markdown($markdown);
        }

        $structured = $this->_first_of($data, array('structured_data', 'structure', 'tree'));

        if ( ! is_array($structured))
        {
            // Если модели дали структуру — сохраняем её, иначе собираем сами
            $structured = array();

            foreach ($operations as $operation)
            {
                $structured[] = array(
                    'step'     => $operation['sort_order'] / 10,
                    'name'     => $operation['op_name'],
                    'duration' => $operation['duration_sec'],
                    'cost'     => $operation['cost_amount'],
                );
            }
        }

        return array(
            'content_markdown' => trim((string) $markdown),
            'structured_data'  => $structured,
            'operations'       => $operations,
        );
    }

    /**
     * Шаги из markdown-текста: нумерованные и маркированные строки.
     *
     * @param  string $text
     * @return array
     */
    public function from_markdown($text)
    {
        $operations = array();
        $index      = 0;

        foreach (preg_split('/\R/u', (string) $text) as $line)
        {
            $line = trim($line);

            if ($line === '')
            {
                continue;
            }

            // «1. Печать», «1) Печать», «- Печать», «* Печать»
            if ( ! preg_match('/^(?:\d+[.)]|[-*+])\s+(.+)$/u', $line, $match))
            {
                continue;
            }

            $title = trim($match[1]);

            if ($title === '')
            {
                continue;
            }

            $duration = 0;

            // «... (15 мин)» — длительность из скобок
            if (preg_match('/\(([^)]*)\)/u', $title, $time))
            {
                $duration = $this->_duration($time[1]);
            }

            $operations[] = array(
                'sort_order'     => ($index + 1) * 10,
                'op_name'        => mb_substr($title, 0, 255, 'UTF-8'),
                'op_description' => NULL,
                'duration_sec'   => $duration,
                'cost_amount'    => 0,
                'params'         => array(),
            );

            $index++;
        }

        return $operations;
    }

    // -------------------------------------------------------------------------
    //  Офлайн-режим: шаблон по типу производства
    // -------------------------------------------------------------------------

    /**
     * Техпроцесс по шаблону — используется, когда LLM недоступна.
     *
     * @param  Model_Erp228_TechCard|array $card
     * @return array  та же структура, что и parse()
     */
    public function offline($card)
    {
        $title      = $this->_field($card, 'title');
        $type       = $this->_field($card, 'production_type');
        $params     = $this->_field($card, 'input_params');

        if (is_string($params))
        {
            $decoded = json_decode($params, TRUE);
            $params  = is_array($decoded) ? $decoded : array();
        }

        if ( ! is_array($params))
        {
            $params = array();
        }

        $operations = $this->template_operations($type, $params);

        $lines   = array();
        $lines[] = '## Техпроцесс: '.($title !== '' ? $title : 'без названия');
        $lines[] = '';
        $lines[] = 'Шаблон сформирован офлайн (ключ LLM не настроен либо модель недоступна).';
        $lines[] = 'Проверьте состав операций и нормы времени перед запуском в производство.';
        $lines[] = '';

        $structured = array();

        foreach ($operations as $index => $operation)
        {
            $lines[] = ($index + 1).'. '.$operation['op_name'].' — '.Model_Erp228_TechDocument::human_duration($operation['duration_sec']);

            if ($operation['op_description'])
            {
                $lines[] = '   '.$operation['op_description'];
            }

            $structured[] = array(
                'step'     => ($index + 1),
                'name'     => $operation['op_name'],
                'duration' => $operation['duration_sec'],
                'cost'     => $operation['cost_amount'],
            );
        }

        return array(
            'content_markdown' => implode("\n", $lines),
            'structured_data'  => $structured,
            'operations'       => $operations,
        );
    }

    /**
     * Типовые операции для типа производства.
     *
     * @param  string $production_type
     * @param  array  $params
     * @return array
     */
    public function template_operations($production_type, array $params = array())
    {
        $quantity = isset($params['партия']) ? max((int) $params['партия'], 1) : 1;
        $material = isset($params['материал']) ? (string) $params['материал'] : NULL;

        switch ($production_type)
        {
            case 'mechanical':
                $operations = array(
                    array('Подготовка управляющей программы', 'Проверка чертежа, выбор инструмента, пробный прогон', 3600, 2500),
                    array('Черновая обработка', 'Съём основного припуска', 5400, 4200),
                    array('Чистовая обработка', 'Выход в размер посадочных поверхностей', 4200, 3600),
                    array('Слесарная доводка и контроль', 'Снятие заусенцев, замеры по чертежу', 1800, 900),
                );
                $tool = 'фреза ЧПУ';
                break;

            case 'manual':
                $operations = array(
                    array('Комплектация', 'Подбор деталей и крепежа по спецификации', 1800, 600),
                    array('Сборка', 'Механическая сборка узла', 5400, 2400),
                    array('Монтаж и пайка', 'Электрический монтаж, пайка соединений', 3600, 2100),
                    array('Проверка и маркировка', 'Функциональная проверка, маркировка изделия', 1800, 800),
                );
                $tool = 'ручной инструмент';
                break;

            case 'additive':
            default:
                $operations = array(
                    array('Подготовка модели', 'Проверка толщины стенок и ориентации в слайсере', 900, 350),
                    array('Печать', 'Аддитивная печать партии', 3600, 1500),
                    array('Постобработка', 'Удаление поддержек, зачистка посадочных поверхностей', 2400, 900),
                    array('Контроль размеров', 'Замеры по чертежу, отбраковка', 1200, 450),
                );
                $tool = '3D-принтер';
                break;
        }

        $result = array();
        $sort   = 10;

        foreach ($operations as $operation)
        {
            $operation_params = array('инструмент' => $tool);

            if ($material)
            {
                $operation_params['материал'] = $material;
            }

            if ($quantity > 1)
            {
                $operation_params['партия'] = $quantity;
            }

            $result[] = array(
                'sort_order'     => $sort,
                'op_name'        => $operation[0],
                'op_description' => $operation[1],
                'duration_sec'   => (int) round($operation[2] * $quantity / 2),
                'cost_amount'    => round($operation[3] * $quantity / 2, 2),
                'params'         => $operation_params,
            );

            $sort += 10;
        }

        return $result;
    }

    /**
     * Промпт для модели: код изделия, тип производства и ограничения.
     *
     * @param  Model_Erp228_TechCard|array $card
     * @param  array  $config
     * @return string
     */
    public function build_prompt($card, array $config = array())
    {
        $type  = $this->_field($card, 'production_type');
        $type  = isset(Model_Erp228_TechCard::$production_types[$type])
            ? Model_Erp228_TechCard::$production_types[$type]
            : $type;

        $params = $this->_field($card, 'input_params');

        if ( ! is_string($params))
        {
            $params = json_encode((array) $params, JSON_UNESCAPED_UNICODE);
        }

        $system = isset($config['system_prompt'])
            ? $config['system_prompt']
            : 'Вы — ведущий инженер-технолог производства.';

        $question = implode("\n", array(
            'Составьте технологическую карту изготовления изделия.',
            'Название: '.$this->_field($card, 'title'),
            'Тип производства: '.$type,
            'Параметры производства: '.$params,
            '',
            'Исходный код изделия:',
            (string) $this->_field($card, 'source_code'),
            '',
            'Ответ верните строго в JSON: {"markdown": "описание техпроцесса",',
            '"operations": [{"name": "...", "description": "...", "duration_sec": 600,',
            '"cost": 1000, "params": {"инструмент": "..."}}]}',
        ));

        return $system."\n\n".$question;
    }

    // -------------------------------------------------------------------------
    //  Внутреннее
    // -------------------------------------------------------------------------

    /**
     * Привести шаг к единому виду.
     *
     * @param  array $step
     * @param  int   $index
     * @return array
     */
    protected function _normalize_operation(array $step, $index)
    {
        $name = $this->_first_of($step, array('op_name', 'name', 'title', 'step', 'operation'));

        $duration = $this->_first_of($step, array('duration_sec', 'duration', 'time_sec', 'time', 'norm_time'));
        $cost     = $this->_first_of($step, array('cost_amount', 'cost', 'price', 'amount'));
        $params   = $this->_first_of($step, array('params', 'parameters', 'options'));

        if ( ! is_array($params))
        {
            $params = array();
        }

        return array(
            'sort_order'     => isset($step['sort_order']) ? (int) $step['sort_order'] : ($index + 1) * 10,
            'op_name'        => mb_substr(trim((string) $name), 0, 255, 'UTF-8'),
            'op_description' => $this->_first_of($step, array('op_description', 'description', 'desc', 'details')),
            'duration_sec'   => $this->_duration($duration),
            'cost_amount'    => $this->_money($cost),
            'params'         => $params,
        );
    }

    /**
     * Деньги: «1 200,50», «1200.5», «1.200,50 руб.» → float.
     *
     * Русская запись использует запятую как десятичный разделитель, а точку —
     * как разделитель тысяч; разбираем оба варианта.
     *
     * @param  mixed $value
     * @return float
     */
    protected function _money($value)
    {
        if (is_numeric($value))
        {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.,\-]/', '', (string) $value);

        if ($clean === '' OR $clean === '-')
        {
            return 0.0;
        }

        if (strpos($clean, ',') !== FALSE)
        {
            // «1.200,50» → «1200.50»
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return round((float) $clean, 2);
    }

    /**
     * Длительность в секундах: 600, «10 мин», «1.5 ч», «45 с», «00:15:00».
     *
     * @param  mixed $value
     * @return int
     */
    protected function _duration($value)
    {
        if (is_numeric($value))
        {
            return (int) round((float) $value);
        }

        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        if ($value === '')
        {
            return 0;
        }

        // ЧЧ:ММ:СС и ММ:СС
        if (preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $value, $match))
        {
            if (isset($match[3]))
            {
                return ((int) $match[1]) * 3600 + ((int) $match[2]) * 60 + (int) $match[3];
            }

            return ((int) $match[1]) * 60 + (int) $match[2];
        }

        if (preg_match('/([\d.,]+)\s*(час|ч|hour|h)\b/u', $value, $match))
        {
            return (int) round((float) str_replace(',', '.', $match[1]) * 3600);
        }

        if (preg_match('/([\d.,]+)\s*(мин|min|m)\b/u', $value, $match))
        {
            return (int) round((float) str_replace(',', '.', $match[1]) * 60);
        }

        if (preg_match('/([\d.,]+)\s*(сек|с|sec|s)\b/u', $value, $match))
        {
            return (int) round((float) str_replace(',', '.', $match[1]));
        }

        if (preg_match('/([\d.,]+)/', $value, $match))
        {
            return (int) round((float) str_replace(',', '.', $match[1]) * 60);
        }

        return 0;
    }

    /**
     * Первое непустое значение из списка ключей.
     *
     * @param  array $data
     * @param  array $keys
     * @return mixed
     */
    protected function _first_of(array $data, array $keys)
    {
        foreach ($keys as $key)
        {
            if (isset($data[$key]) AND $data[$key] !== '' AND $data[$key] !== NULL)
            {
                return $data[$key];
            }
        }

        return NULL;
    }

    /**
     * JSON из ответа модели: чистый, в ```фенсе или с текстом вокруг.
     *
     * @param  string $text
     * @return array|null
     */
    protected function _json($text)
    {
        $text = trim($text);

        if ($text === '')
        {
            return NULL;
        }

        // ```json ... ``` и ``` ... ```
        if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $match))
        {
            $text = trim($match[1]);
        }

        $decoded = json_decode($text, TRUE);

        if (is_array($decoded))
        {
            return $decoded;
        }

        // JSON внутри текста: берём первый сбалансированный блок { ... }
        $start = strpos($text, '{');

        if ($start === FALSE)
        {
            return NULL;
        }

        $depth = 0;
        $end   = NULL;

        for ($i = $start; $i < strlen($text); $i++)
        {
            if ($text[$i] === '{')
            {
                $depth++;
            }
            elseif ($text[$i] === '}')
            {
                $depth--;

                if ($depth === 0)
                {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === NULL)
        {
            return NULL;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), TRUE);

        return is_array($decoded) ? $decoded : NULL;
    }

    /**
     * Поле карты — из модели или из массива.
     *
     * @param  Model_Erp228_TechCard|array $card
     * @param  string $field
     * @return mixed
     */
    protected function _field($card, $field)
    {
        if (is_array($card))
        {
            return isset($card[$field]) ? $card[$field] : '';
        }

        if (is_object($card) AND isset($card->$field))
        {
            return $card->$field;
        }

        return '';
    }
}
