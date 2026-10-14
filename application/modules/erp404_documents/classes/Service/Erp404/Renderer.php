<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Подстановки в шаблонах печатных форм (erp404_documents).
 *
 * Свой мини-движок вместо шаблонизатора — намеренно. Печатная форма это HTML с
 * точками данных, а не программа: чем меньше в шаблоне можно, тем труднее
 * испортить документ и тем проще объяснить пользователю, что он видит.
 * Поддерживается ровно три вещи:
 *
 *   {{ref.number}}          подстановка значения по пути (точка — разделитель);
 *   {{document.number}}     то же, другой корень (поля самого документа);
 *   {{#rows ref.lines}}…{{/rows}}  блок, повторяемый по строкам массива:
 *                                  внутри доступны поля строки и `{{index}}`.
 *
 * Протухшая подстановка — не «пустое место», а отказ: `{{ref.такого_поля}}`
 * бросает `unknown_placeholder`. Иначе шаблон с опечаткой печатает документ без
 * суммы или без поставщика, и это замечают уже на бумаге.
 *
 * Значения экранируются (`htmlspecialchars`): данные приходят из чужих модулей и
 * из форм, а печатная форма — такая же точка вывода, как страница.
 *
 * Вложенные блоки (`{{#rows}}` внутри `{{#rows}}`) не поддерживаются: печатная
 * форма, которой нужен вложенный список, — повод сделать отдельный шаблон.
 *
 * @package    Erp404
 * @category   Services
 */
class Service_Erp404_Renderer
{
    /** @var Service_Erp404_Renderer */
    protected static $_instance;

    /**
     * @return  Service_Erp404_Renderer
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
     * Шаблон блока-повтора.
     *
     * @var string
     */
    const BLOCK_PATTERN = '/\{\{\s*#rows\s+([\p{L}_][\p{L}\p{N}_.]*)\s*\}\}(.*?)\{\{\s*\/rows\s*\}\}/isu';

    /**
     * Отрисовать тело шаблона.
     *
     * @param   string  $body
     * @param   array   $data   корни подстановок: ref, document, meta, …
     * @return  string
     * @throws  Exception_Erp404_Documents
     */
    public function render($body, array $data)
    {
        $body = (string) $body;

        // Сначала блоки: внутри них свои подстановки (поля строки).
        $body = preg_replace_callback(self::BLOCK_PATTERN, function ($match) use ($data) {
            return $this->_render_rows(trim($match[1]), $match[2], $data);
        }, $body);

        return $this->_render_values($body, $data);
    }

    /**
     * Проверить шаблон, не собирая документ: все подстановки должны быть
     * известны, блоки — ссылаться на массивы, скобки — закрываться.
     *
     * @param   string  $body
     * @param   array   $data
     * @return  array   замечания человеческим языком (пусто — шаблон в порядке)
     */
    public function check($body, array $data = array())
    {
        $problems = array();
        $body     = (string) $body;

        if (trim($body) === '')
        {
            $problems[] = 'Тело шаблона пустое — печатать нечего.';
            return $problems;
        }

        $opened = preg_match_all(self::BLOCK_PATTERN, $body, $matches);
        $starts = preg_match_all('/\{\{\s*#rows\b/iu', $body);
        $ends   = preg_match_all('/\{\{\s*\/rows\s*\}\}/iu', $body);

        if ($starts !== $ends)
        {
            $problems[] = 'Блоки {{#rows …}} и {{/rows}} не сходятся: открывающих '.$starts.', закрывающих '.$ends.'.';
        }

        if ($matches AND $data)
        {
            foreach ($matches[1] as $path)
            {
                if ($this->dig($data, $path, $missing) === NULL OR ! is_array($this->dig($data, $path)))
                {
                    $problems[] = 'Блок {{#rows '.$path.'}} не находит список в данных.';
                }
            }
        }

        if ( ! $data)
        {
            // Без данных проверяем только форму подстановок.
            if (preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/u', $body, $all))
            {
                foreach ($all[1] as $name)
                {
                    if (strpos($name, '#rows') === 0 OR strpos($name, '/rows') === 0)
                    {
                        continue;
                    }

                    if (strpos($name, '.') === FALSE)
                    {
                        $problems[] = 'Подстановка {{'.$name.'}} без пути: нужно {{корень.поле}}.';
                    }
                }
            }

            return $problems;
        }

        return $problems;
    }

    /**
     * Раскрыть блок `{{#rows path}}…{{/rows}}`.
     *
     * @param   string  $path
     * @param   string  $inner
     * @param   array   $data
     * @return  string
     * @throws  Exception_Erp404_Documents
     */
    protected function _render_rows($path, $inner, array $data)
    {
        $rows = $this->dig($data, $path);

        if ($rows === NULL)
        {
            throw new Exception_Erp404_Documents(
                'Шаблон обращается к списку :path, которого нет в данных',
                array(':path' => $path),
                'unknown_placeholder'
            );
        }

        if ( ! is_array($rows))
        {
            throw new Exception_Erp404_Documents(
                'Подстановка :path должна быть списком строк',
                array(':path' => $path),
                'unknown_placeholder'
            );
        }

        $html   = '';
        $index  = 0;

        foreach ($rows as $row)
        {
            $index++;

            $scope = is_array($row) ? $row : array('value' => $row);
            $scope['index'] = $index;

            // Внутри блока подстановки ищутся и в самой строке, и в корнях
            // данных: `{{item_name}}` — поле строки, `{{meta.company}}` — общее.
            $html .= $this->_render_values($inner, array_merge($data, array('row' => $scope)), $scope);
        }

        return $html;
    }

    /**
     * Подставить значения в текст.
     *
     * @param   string  $body
     * @param   array   $data
     * @param   array   $scope  поля строки блока (если внутри блока)
     * @return  string
     * @throws  Exception_Erp404_Documents
     */
    protected function _render_values($body, array $data, array $scope = array())
    {
        // Имена подстановок — любые буквы, включая кириллицу: шаблон пишет
        // человек, и «{{счёт.номер}}» — такая же опечатка, как «{{ref.nuber}}»,
        // и должна ловиться, а не печататься как есть.
        return preg_replace_callback('/\{\{\s*([\p{L}_][\p{L}\p{N}_]*(?:\.[\p{L}\p{N}_]+)*)\s*\}\}/iu', function ($match) use ($data, $scope) {
            $path = $match[1];

            // Поле строки блока — самый близкий контекст.
            if ($scope AND strpos($path, '.') === FALSE AND array_key_exists($path, $scope))
            {
                return $this->_escape($scope[$path]);
            }

            $value = $this->dig($data, $path, $missing);

            if ($missing)
            {
                throw new Exception_Erp404_Documents(
                    'Подстановка :path не найдена в данных: проверьте шаблон',
                    array(':path' => $path),
                    'unknown_placeholder'
                );
            }

            if (is_array($value))
            {
                throw new Exception_Erp404_Documents(
                    'Подстановка :path — список, нужен блок {{#rows :path}}',
                    array(':path' => $path),
                    'unknown_placeholder'
                );
            }

            return $this->_escape($value);
        }, $body);
    }

    /**
     * Значение по пути `ref.lines.0.qty`.
     *
     * @param   array   $data
     * @param   string  $path
     * @param   bool    $missing    TRUE, если ключа в данных нет
     * @return  mixed
     */
    public function dig(array $data, $path, & $missing = NULL)
    {
        $missing = FALSE;
        $value   = $data;

        foreach (explode('.', (string) $path) as $key)
        {
            if (is_array($value) AND array_key_exists($key, $value))
            {
                $value = $value[$key];
                continue;
            }

            $missing = TRUE;

            return NULL;
        }

        return $value;
    }

    /**
     * Значение для печати: NULL — пустая строка, остальное — текст.
     *
     * @param   mixed   $value
     * @return  string
     */
    protected function _escape($value)
    {
        if ($value === NULL)
        {
            return '';
        }

        if (is_bool($value))
        {
            return $value ? 'да' : 'нет';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
