<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Выгрузка витрин в CSV (erp801_analytics).
 *
 * Формат — CSV, а не XLSX, по двум причинам. Первая: библиотеки работы с
 * Excel в проекте нет (composer недоступен, см. docs/DEPLOY_AND_ROADMAP.md),
 * а писать свой XLSX — это чужой формат своими руками на ровном месте. Вторая:
 * CSV с BOM и точкой с запятой открывается Excel и LibreOffice двойным щелчком
 * и остаётся читаемым текстом в почте и в git.
 *
 * Разделитель — точка с запятой и BOM в начале: без них русский Excel считает
 * файл одноколоночным и ломает кириллицу. Десятичный разделитель оставлен
 * точкой: так файл читается и русским, и любым другим Excel, а «запятая вместо
 * точки» ломает машиночитаемость ради одной локали.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Csv
{
    /** @var Service_Erp801_Csv|null */
    protected static $_instance;

    /**
     * @return  Service_Erp801_Csv
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Что можно выгрузить.
     *
     * @return  array   код => подпись
     */
    public function blocks()
    {
        return Model_Erp801_Snapshot::$codes;
    }

    /**
     * Коды витрин, которые можно выгрузить.
     *
     * Нужны там, где код витрины вводит человек или агент: подсказка в ошибке и
     * список допустимых значений в схеме инструмента MCP. Без них агент узнаёт
     * о существовании витрины только из текста описания и ошибается на
     * «deficit» — это не витрина, а вкладка витрины запасов.
     *
     * @return  array   список кодов
     */
    public static function codes()
    {
        return array_keys(Model_Erp801_Snapshot::$codes);
    }

    /**
     * Выгрузка витрины: имя файла и содержимое.
     *
     * @param   string  $code
     * @param   array   $filters
     * @return  array   {filename, content, rows, title}
     */
    public function render($code, array $filters = array())
    {
        $blocks = $this->blocks();

        if ( ! isset($blocks[$code]))
        {
            throw new Exception_Erp801_Analytics(
                'Неизвестная витрина «:code». Доступны: :codes',
                array(':code' => (string) $code, ':codes' => implode(', ', self::codes())),
                'invalid_argument'
            );
        }

        $payload = Service_Erp801_Snapshots::instance()->build($code, $filters);
        $table   = $this->table($code, $payload);

        $stamp = date('Ymd');

        if (isset($payload['from'], $payload['to']))
        {
            $stamp = str_replace('-', '', (string) $payload['from']).'_'.str_replace('-', '', (string) $payload['to']);
        }

        return array(
            'title'    => $blocks[$code],
            'filename' => 'analytics_'.$code.'_'.$stamp.'.csv',
            'rows'     => count($table['rows']),
            'content'  => $this->csv($table['header'], $table['rows'], $table['title']),
        );
    }

    /**
     * Таблица витрины: шапка и строки.
     *
     * @param   string  $code
     * @param   array   $payload
     * @return  array   {title, header, rows}
     */
    public function table($code, array $payload)
    {
        switch ($code)
        {
            case 'summary':
                $rows = array();

                foreach (isset($payload['kpi']) ? $payload['kpi'] : array() as $kpi)
                {
                    $rows[] = array(
                        $kpi['title'],
                        $kpi['value'],
                        $kpi['unit'],
                        $kpi['target'],
                        $this->state_label($kpi['state']),
                        $kpi['hint'],
                    );
                }

                foreach (isset($payload['attention']) ? $payload['attention'] : array() as $row)
                {
                    $rows[] = array(
                        'Внимание: '.$row['title'],
                        $row['value'],
                        '',
                        '',
                        $this->state_label($row['state']),
                        $row['hint'],
                    );
                }

                return array(
                    'title'  => 'Сводка аналитики',
                    'header' => array('Показатель', 'Значение', 'Единица', 'Цель', 'Состояние', 'Пояснение'),
                    'rows'   => $rows,
                );

            case 'stock':
                $rows = array();

                foreach (isset($payload['rows']) ? $payload['rows'] : array() as $row)
                {
                    $rows[] = array(
                        $row['code'],
                        $row['name'],
                        $row['qty_in'],
                        $row['qty_out'],
                        $row['stock'],
                        $row['reserved'],
                        $row['min_qty'],
                        $row['cover_days'],
                        $row['turns_period'],
                        $this->class_label($row['class']),
                    );
                }

                return array(
                    'title'  => 'Оборачиваемость и покрытие запасов',
                    'header' => array('Код', 'Позиция', 'Приход', 'Расход', 'Остаток', 'Резерв', 'Минимум', 'Покрытие, дней', 'Оборотов за период', 'Класс'),
                    'rows'   => $rows,
                );

            case 'abc':
                $rows = array();

                foreach (isset($payload['rows']) ? $payload['rows'] : array() as $row)
                {
                    $rows[] = array(
                        $row['code'],
                        $row['name'],
                        $row['qty_out'],
                        $row['share'],
                        $row['share_total'],
                        $row['abc'],
                        $row['cv'],
                        $row['xyz'] === NULL ? 'мало данных' : $row['xyz'],
                    );
                }

                return array(
                    'title'  => 'ABC/XYZ анализ запасов',
                    'header' => array('Код', 'Позиция', 'Расход', 'Доля, %', 'Накопленная доля, %', 'ABC', 'Вариация, %', 'XYZ'),
                    'rows'   => $rows,
                );

            case 'production':
                $rows = array();

                foreach (isset($payload['pareto']) ? $payload['pareto'] : array() as $row)
                {
                    $rows[] = array(
                        'Простой: '.$row['reason_label'],
                        $row['events'],
                        $row['hours'],
                        $row['share'],
                        $row['share_total'],
                        '',
                    );
                }

                foreach (isset($payload['by_center']) ? $payload['by_center'] : array() as $row)
                {
                    $rows[] = array(
                        $row['work_center_label'],
                        $row['operations'],
                        $row['worked_sec'],
                        $row['qty_ok'],
                        $row['qty_scrap'],
                        $row['oee'] === NULL ? 'н/д' : $row['oee'].'%',
                    );
                }

                return array(
                    'title'  => 'OEE, простои и выработка',
                    'header' => array('Строка', 'События/операции', 'Часы/секунды', 'Годное/доля', 'Брак/доля', 'OEE'),
                    'rows'   => $rows,
                );

            case 'quality':
                $rows = array();

                foreach (isset($payload['cause_pareto']) ? $payload['cause_pareto'] : array() as $row)
                {
                    $rows[] = array('Причина: '.$row['label'], $row['qty'], $row['share'], $row['share_total']);
                }

                foreach (isset($payload['code_pareto']) ? $payload['code_pareto'] : array() as $row)
                {
                    $rows[] = array('Код: '.$row['label'], $row['qty'], $row['share'], $row['share_total']);
                }

                foreach (isset($payload['by_supplier']) ? $payload['by_supplier'] : array() as $row)
                {
                    $rows[] = array(
                        'Поставщик: '.(isset($row['name']) ? $row['name'] : '#'.(int) $row['supplier_id']),
                        isset($row['qty']) ? $row['qty'] : 0,
                        '',
                        '',
                    );
                }

                return array(
                    'title'  => 'Качество: Парето брака',
                    'header' => array('Строка', 'Количество', 'Доля, %', 'Накопленная доля, %'),
                    'rows'   => $rows,
                );

            case 'purchases':
                $rows = array();

                foreach (isset($payload['overdue_rows']) ? $payload['overdue_rows'] : array() as $row)
                {
                    $rows[] = array(
                        'Просрочен: '.$row['number'],
                        $row['supplier_name'],
                        $row['delivery_date'],
                        $row['days_late'],
                        $row['amount'],
                        $row['status_label'],
                    );
                }

                foreach (isset($payload['due_rows']) ? $payload['due_rows'] : array() as $row)
                {
                    $rows[] = array(
                        'Срок близко: '.$row['number'],
                        $row['supplier_name'],
                        $row['delivery_date'],
                        $row['days_late'],
                        $row['amount'],
                        $row['status_label'],
                    );
                }

                return array(
                    'title'  => 'Закупки: сроки и просрочка',
                    'header' => array('Заказ', 'Поставщик', 'Дата поставки', 'Просрочка, дней', 'Сумма', 'Состояние'),
                    'rows'   => $rows,
                );
        }

        return array('title' => 'Выгрузка', 'header' => array(), 'rows' => array());
    }

    /**
     * CSV с BOM, точкой с запятой и переводом строки Windows — так файл
     * открывается в Excel по двойному щелчку и не рассыпается на колонки-кашу.
     *
     * @param   array   $header
     * @param   array   $rows
     * @param   string  $title
     * @return  string
     */
    public function csv(array $header, array $rows, $title = NULL)
    {
        $lines = array();

        if ($title !== NULL AND $title !== '')
        {
            $lines[] = $this->_line(array($title));
            $lines[] = '';
        }

        if ($header)
        {
            $lines[] = $this->_line($header);
        }

        foreach ($rows as $row)
        {
            $lines[] = $this->_line($row);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /**
     * Строка CSV: числа — как есть, текст — в кавычках, если в нём есть
     * разделитель, кавычка или перевод строки.
     *
     * @param   array   $values
     * @return  string
     */
    protected function _line(array $values)
    {
        $cells = array();

        foreach ($values as $value)
        {
            if ($value === NULL)
            {
                $cells[] = '';
                continue;
            }

            if (is_bool($value))
            {
                $cells[] = $value ? '1' : '0';
                continue;
            }

            $string = (string) $value;

            if (is_numeric($string))
            {
                $cells[] = $string;
                continue;
            }

            $cells[] = '"'.str_replace('"', '""', $string).'"';
        }

        return implode(';', $cells);
    }

    /**
     * Подпись состояния для человека.
     *
     * @param   string  $state
     * @return  string
     */
    public function state_label($state)
    {
        $labels = array(
            'ok'      => 'в норме',
            'warn'    => 'внимание',
            'bad'     => 'плохо',
            'unknown' => 'нет данных',
        );

        return isset($labels[$state]) ? $labels[$state] : (string) $state;
    }

    /**
     * Классы движения запаса: код => подпись (для таблиц и фильтров).
     *
     * @return  array
     */
    public function class_labels()
    {
        return array(
            'fast'   => 'быстрый',
            'medium' => 'средний',
            'slow'   => 'медленный',
            'dead'   => 'мёртвый',
            'out'    => 'нет запаса',
            'idle'   => 'без расхода',
        );
    }

    /**
     * Подпись класса движения запаса.
     *
     * @param   string  $class
     * @return  string
     */
    public function class_label($class)
    {
        $labels = $this->class_labels();

        return isset($labels[$class]) ? $labels[$class] : (string) $class;
    }
}
