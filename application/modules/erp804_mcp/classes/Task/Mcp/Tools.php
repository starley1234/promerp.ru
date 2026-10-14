<?php defined('SYSPATH') or die('No direct script access.');

/**
 * `bin/console mcp:tools` — каталог инструментов MCP.
 *
 * Быстрый способ увидеть, что доступно агентам, не поднимая HTTP-клиента:
 * какие модули отдают инструменты, какие права нужны, что изменяет данные.
 * `--check` дополнительно проверяет объявления (есть ли обработчик, описание,
 * схема, право) — эту же проверку использует статический аудит.
 *
 * @package    Erp804
 * @category   Tasks
 */
class Task_Mcp_Tools extends Minion_Task
{
    protected $_options = array(
        'json'   => FALSE,
        'check'  => FALSE,
        'module' => NULL,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        // Minion отдаёт флаг без значения как NULL — читаем через _flag()/_value()
        $as_json = $this->_flag($options, 'json');
        $check   = $this->_flag($options, 'check');
        $module  = $this->_value($options, 'module');

        $registry = Service_Erp804_Registry::instance();
        $tools    = $registry->catalogue();

        if ($module)
        {
            $tools  = array_values(array_filter($tools, function ($tool) use ($module) {
                return $tool['module'] === $module;
            }));
        }

        if ($as_json)
        {
            echo json_encode(array(
                'tools'    => $tools,
                'modules'  => $registry->sources(),
                'problems' => $registry->problems(),
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;

            return;
        }

        echo 'Инструментов MCP: '.count($tools), PHP_EOL, PHP_EOL;

        foreach ($registry->sources() as $module => $count)
        {
            echo '  '.str_pad($module, 8).' — '.$count.' шт.', PHP_EOL;
        }

        echo PHP_EOL;

        foreach ($tools as $tool)
        {
            $marks = array();

            if ($tool['mutating'])
            {
                $marks[] = 'изменяет';
            }

            if ($tool['approval'])
            {
                $marks[] = 'нужно подтверждение';
            }

            echo $tool['name'], PHP_EOL;
            echo '    ', $tool['title'], PHP_EOL;
            $scope = ! empty($tool['self']) ? 'любой действующий ключ' : (isset($tool['scope']) ? $tool['scope'] : '—');

            echo '    права: ', $scope, ($marks ? ' ('.implode(', ', $marks).')' : ''), PHP_EOL;
            echo '    ', $this->_trim($tool['description']), PHP_EOL;

            $properties = isset($tool['schema']['properties']) ? $tool['schema']['properties'] : array();

            if ($properties)
            {
                $parts = array();

                foreach ($properties as $name => $rule)
                {
                    $parts[] = $name.':'.(isset($rule['type']) ? $rule['type'] : 'string');
                }

                echo '    аргументы: ', implode(', ', $parts), PHP_EOL;
            }

            echo PHP_EOL;
        }

        if ($check)
        {
            $problems = $registry->problems();

            if ( ! $problems)
            {
                echo 'Проверка объявлений: замечаний нет', PHP_EOL;

                return;
            }

            echo 'Проверка объявлений: замечаний '.count($problems), PHP_EOL;

            foreach ($problems as $problem)
            {
                echo '  - ', ($problem['tool'] ? $problem['tool'].': ' : ''), $problem['message'], ' (', $problem['module'], ')', PHP_EOL;
            }
        }
    }

    /**
     * @param   string  $text
     * @param   int     $width
     * @return  string
     */
    protected function _trim($text, $width = 150)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return (mb_strlen($text) > $width) ? mb_substr($text, 0, $width - 1).'…' : $text;
    }
}
