<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реестр инструментов MCP (erp804_mcp).
 *
 * Инструменты объявляют сами модули — файлом `mcp.php` в корне модуля:
 *
 *     return array(
 *         'module' => 'erp402',
 *         'tools'  => array(
 *             'production.orders.search' => array(
 *                 'title'       => '…',
 *                 'description' => '…',
 *                 'scope'       => 'erp402_production.view',
 *                 'mutating'    => FALSE,
 *                 'handler'     => function (array $args) { … return array(...); },
 *                 'schema'      => array('type' => 'object', 'properties' => array(…)),
 *             ),
 *         ),
 *     );
 *
 * Никакого «центрального списка» нет: подключили модуль — его инструменты
 * появились у агентов, выключили — исчезли. Реестр проверяет объявления
 * (имя, схема, право, обработчик) и сообщает о проблемах в `dev:audit`.
 *
 * Инструмент с признаком `'self' => TRUE` доступен любому действующему ключу
 * без отдельного права: это «сведения о себе» — кто я, что мне можно, что
 * стало с моим вызовом. Иначе агенту, которого ограничили правами склада,
 * было бы нечем спросить, чем закончилась его же операция.
 *
 * Схема аргументов — JSON Schema (упрощённая): `type`, `properties`,
 * `required`, `enum`, `default`, `minimum`/`maximum`. Сервер приводит
 * аргументы к объявленным типам: LLM часто присылает «10» вместо 10.
 *
 * @package    Erp804
 * @category   Services
 */
class Service_Erp804_Registry
{
    /** @var Service_Erp804_Registry|null */
    protected static $_instance;

    /** @var array|null Инструменты: имя => объявление */
    protected $_tools;

    /** @var array Модуль-владелец: имя => модуль */
    protected $_sources = array();

    /** @var array Замечания к объявлениям */
    protected $_problems = array();

    /** @var array Контекст текущего вызова: key, call */
    protected $_context = array();

    /**
     * @return  Service_Erp804_Registry
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Обнаружение
    // =========================================================================

    /**
     * Запомнить контекст вызова — из него обработчики узнают, какой ключ
     * агента пришёл и в какую запись журнала писать результат.
     *
     * @param   array   $context
     * @return  void
     */
    public function set_context(array $context)
    {
        $this->_context = $context;
    }

    /**
     * Значение контекста вызова.
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    public function context($key = NULL, $default = NULL)
    {
        if ($key === NULL)
        {
            return $this->_context;
        }

        return isset($this->_context[$key]) ? $this->_context[$key] : $default;
    }

    /**
     * Все объявленные инструменты.
     *
     * @return  array   имя => объявление
     */
    public function tools()
    {
        if ($this->_tools !== NULL)
        {
            return $this->_tools;
        }

        $this->_tools = array();

        foreach (Kohana::modules() as $module => $path)
        {
            $file = rtrim($path, '/').'/mcp.php';

            if ( ! is_file($file))
            {
                continue;
            }

            $definition = include $file;

            if ( ! is_array($definition) OR empty($definition['tools']))
            {
                $this->_problems[] = array(
                    'tool'   => NULL,
                    'module' => $module,
                    'message'=> 'mcp.php не вернул массив с ключом tools',
                );
                continue;
            }

            $owner = isset($definition['module']) ? (string) $definition['module'] : (string) $module;

            foreach ($definition['tools'] as $name => $tool)
            {
                $name = (string) $name;

                if (isset($this->_tools[$name]))
                {
                    $this->_problems[] = array(
                        'tool'   => $name,
                        'module' => $owner,
                        'message'=> 'имя уже занято модулем '.$this->_sources[$name],
                    );
                    continue;
                }

                foreach ($this->check($name, $tool) as $message)
                {
                    $this->_problems[] = array('tool' => $name, 'module' => $owner, 'message' => $message);
                }

                $tool['module'] = $owner;
                $this->_tools[$name] = $tool;
                $this->_sources[$name] = $owner;
            }
        }

        ksort($this->_tools);

        return $this->_tools;
    }

    /**
     * Замечания к объявлениям (используются `mcp:tools --check` и аудитом).
     *
     * @return  array
     */
    public function problems()
    {
        $this->tools();

        return $this->_problems;
    }

    /**
     * Модули, отдавшие инструменты: модуль => сколько инструментов.
     *
     * @return  array
     */
    public function sources()
    {
        $this->tools();

        $counts = array();

        foreach ($this->_sources as $module)
        {
            $counts[$module] = isset($counts[$module]) ? $counts[$module] + 1 : 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Найти инструмент.
     *
     * @param   string  $name
     * @return  array|null
     */
    public function get($name)
    {
        $tools = $this->tools();

        return isset($tools[$name]) ? $tools[$name] : NULL;
    }

    /**
     * Инструменты, доступные ключу (по его правам).
     *
     * @param   array   $key
     * @return  array   имя => объявление
     */
    public function for_key(array $key)
    {
        $keys  = Service_Erp804_Keys::instance();
        $allow = array();

        foreach ($this->tools() as $name => $tool)
        {
            // `self` — инструменты о себе: доступны любому действующему ключу
            if (empty($tool['self']) AND ! $keys->allows($key, isset($tool['scope']) ? $tool['scope'] : ''))
            {
                continue;
            }

            if ( ! empty($key['read_only']) AND ! empty($tool['mutating']))
            {
                continue;
            }

            $allow[$name] = $tool;
        }

        return $allow;
    }

    /**
     * Описание инструментов без обработчиков — для интерфейса, документации
     * и команды `mcp:tools`.
     *
     * @return  array
     */
    public function catalogue()
    {
        $list = array();

        foreach ($this->tools() as $name => $tool)
        {
            $list[] = array(
                'name'        => $name,
                'title'       => isset($tool['title']) ? $tool['title'] : $name,
                'description' => isset($tool['description']) ? $tool['description'] : '',
                'module'      => $tool['module'],
                'scope'       => isset($tool['scope']) ? $tool['scope'] : '',
                'mutating'    => ! empty($tool['mutating']),
                'approval'    => ! empty($tool['approval']),
                'self'        => ! empty($tool['self']),
                'schema'      => isset($tool['schema']) ? $tool['schema'] : array('type' => 'object', 'properties' => array()),
            );
        }

        return $list;
    }

    // =========================================================================
    //  Проверка и вызов
    // =========================================================================

    /**
     * Проверить объявление инструмента.
     *
     * @param   string  $name
     * @param   mixed   $tool
     * @return  array   замечания
     */
    protected function check($name, $tool)
    {
        $problems = array();

        if ( ! preg_match('/^[a-z][a-z0-9_]*\.[a-z0-9_.]+$/', $name))
        {
            $problems[] = 'имя должно быть вида «группа.действие» (латиница, нижний регистр)';
        }

        if ( ! is_array($tool))
        {
            return $problems ? array_merge($problems, array('объявление не массив')) : array('объявление не массив');
        }

        if (empty($tool['handler']) OR ! is_callable($tool['handler']))
        {
            $problems[] = 'не задан обработчик (handler)';
        }

        if (empty($tool['scope']) AND empty($tool['self']))
        {
            $problems[] = 'не указано право (scope) — агент получит инструмент без ограничений';
        }

        if (empty($tool['description']))
        {
            $problems[] = 'нет описания: LLM выбирает инструмент по описанию';
        }

        if (empty($tool['schema']) OR ! is_array($tool['schema']))
        {
            $problems[] = 'не задана схема аргументов (schema)';
        }
        elseif ( ! isset($tool['schema']['type']) OR $tool['schema']['type'] !== 'object')
        {
            $problems[] = 'схема аргументов должна быть объектом (type = object)';
        }

        return $problems;
    }

    /**
     * Привести аргументы к схеме инструмента.
     *
     * @param   string  $name
     * @param   array   $args
     * @return  array   array(normalized, errors)
     */
    public function validate_arguments($name, array $args)
    {
        $tool   = $this->get($name);
        $schema = ($tool AND isset($tool['schema'])) ? $tool['schema'] : array('type' => 'object', 'properties' => array());
        $props  = isset($schema['properties']) ? $schema['properties'] : array();
        $errors = array();
        $clean  = array();

        foreach ((array) $props as $key => $rule)
        {
            if ( ! array_key_exists($key, $args) OR $args[$key] === '' OR $args[$key] === NULL)
            {
                if (array_key_exists('default', $rule))
                {
                    $clean[$key] = $rule['default'];
                }
                continue;
            }

            $value = $args[$key];
            $type  = isset($rule['type']) ? $rule['type'] : 'string';

            switch ($type)
            {
                case 'integer':
                    if ( ! is_numeric($value))
                    {
                        $errors[] = '«'.$key.'» должен быть целым числом';
                        break;
                    }
                    $value = (int) $value;
                    break;

                case 'number':
                    if ( ! is_numeric($value))
                    {
                        $errors[] = '«'.$key.'» должен быть числом';
                        break;
                    }
                    $value = (float) $value;
                    break;

                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === NULL)
                    {
                        $errors[] = '«'.$key.'» должен быть true/false';
                        break;
                    }
                    break;

                case 'array':
                    if (is_string($value))
                    {
                        $decoded = json_decode($value, TRUE);
                        $value = is_array($decoded) ? $decoded : array_map('trim', explode(',', $value));
                    }
                    if ( ! is_array($value))
                    {
                        $errors[] = '«'.$key.'» должен быть списком';
                        break;
                    }
                    if (isset($rule['items']['type']))
                    {
                        foreach ($value as $index => $item)
                        {
                            if ($rule['items']['type'] === 'integer' AND ! is_numeric($item))
                            {
                                $errors[] = '«'.$key.'['.$index.']» должен быть целым числом';
                            }
                            elseif ($rule['items']['type'] === 'integer')
                            {
                                $value[$index] = (int) $item;
                            }
                        }
                    }
                    break;

                default:
                    $value = (string) $value;
            }

            if (isset($rule['enum']) AND is_array($rule['enum']) AND ! in_array($value, $rule['enum'], TRUE))
            {
                $errors[] = '«'.$key.'»: допустимые значения — '.implode(', ', $rule['enum']);
            }

            if (isset($rule['minimum']) AND is_numeric($value) AND $value < $rule['minimum'])
            {
                $errors[] = '«'.$key.'» не может быть меньше '.$rule['minimum'];
            }

            if (isset($rule['maximum']) AND is_numeric($value) AND $value > $rule['maximum'])
            {
                $errors[] = '«'.$key.'» не может быть больше '.$rule['maximum'];
            }

            $clean[$key] = $value;
        }

        foreach (isset($schema['required']) ? (array) $schema['required'] : array() as $key)
        {
            if ( ! array_key_exists($key, $clean) OR $clean[$key] === NULL OR $clean[$key] === '')
            {
                $errors[] = 'не указан обязательный аргумент «'.$key.'»';
            }
        }

        // Аргументы, которых нет в схеме, отбрасываем: LLM любит добавлять «своё»
        return array($clean, array_values(array_unique($errors)));
    }

    /**
     * Выполнить инструмент.
     *
     * @param   string  $name
     * @param   array   $args    уже проверенные аргументы
     * @return  array
     * @throws  Exception_Erp804_Mcp
     */
    public function call($name, array $args)
    {
        $tool = $this->get($name);

        if ( ! $tool)
        {
            throw new Exception_Erp804_Mcp('Инструмент «:name» не объявлен', array(':name' => $name), 'unknown_tool');
        }

        try
        {
            $result = call_user_func($tool['handler'], $args);
        }
        catch (Exception_Erp804_Mcp $e)
        {
            throw $e;
        }
        catch (Throwable $e)
        {
            throw new Exception_Erp804_Mcp(
                'Инструмент «:name» вернул ошибку: :error',
                array(':name' => $name, ':error' => $e->getMessage()),
                'failed',
                0,
                // Kohana_Exception ещё типизирует previous как Exception,
                // тогда как PHP Error реализует только Throwable.
                $e instanceof Exception ? $e : NULL
            );
        }

        return is_array($result) ? $result : array('result' => $result);
    }
}
