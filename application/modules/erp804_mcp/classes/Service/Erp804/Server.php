<?php defined('SYSPATH') or die('No direct script access.');

/**
 * MCP-сервер (erp804_mcp).
 *
 * Реализует Model Context Protocol поверх JSON-RPC 2.0 без внешних
 * зависимостей. Поддерживаемые методы:
 *
 *  - `initialize`             — рукопожатие: версия протокола, имя сервера, возможности;
 *  - `notifications/initialized` — уведомление клиента (ответа нет);
 *  - `ping`                   — проверка живости;
 *  - `tools/list`             — список инструментов, доступных ключу агента;
 *  - `tools/call`             — вызов инструмента;
 *  - `resources/list`, `resources/read`, `prompts/list` — пустые наборы
 *    (объявлены, чтобы клиенты не спотыкались).
 *
 * Сервер не знает ничего о конкретных модулях: инструменты берутся из реестра
 * (см. `Service_Erp804_Registry`), поэтому управление производством, складом
 * или журналом добавляется вместе с модулем, а не правкой MCP-сервера.
 *
 * Что сервер делает сам:
 *
 *  1. проверяет ключ и права (scope) инструмента;
 *  2. запрещает изменяющие инструменты ключам «только чтение»;
 *  3. проверяет аргументы по схеме (JSON Schema);
 *  4. не выполняет опасные операции сразу — отправляет на подтверждение
 *     человеку (`approval` у инструмента);
 *  5. пишет каждый вызов в `erp_804_mcp_calls`, а изменяющие — ещё и в
 *     журнал `erp119`;
 *  6. поддерживает `idempotency_key` — повтор запроса агентом не приведёт
 *     к повторному созданию заказа.
 *
 * @package    Erp804
 * @category   Services
 */
class Service_Erp804_Server
{
    /** @var string Версия протокола MCP, которую объявляет сервер */
    const PROTOCOL_VERSION = '2025-06-18';

    const SERVER_NAME    = 'promerp-mcp';
    const SERVER_VERSION = '0.1.0';

    /** @var Service_Erp804_Server|null */
    protected static $_instance;

    /**
     * @return  Service_Erp804_Server
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
     * Проверить токен агента.
     *
     * @param   string  $token
     * @return  array
     */
    public function authenticate($token)
    {
        return Service_Erp804_Keys::instance()->authenticate($token);
    }

    /**
     * Информация о сервере (для `GET /mcp` и интерфейса).
     *
     * @return  array
     */
    public function info()
    {
        $registry = Service_Erp804_Registry::instance();

        return array(
            'name'        => self::SERVER_NAME,
            'version'     => self::SERVER_VERSION,
            'protocol'    => self::PROTOCOL_VERSION,
            'tools'       => count($registry->tools()),
            'modules'     => $registry->sources(),
            'problems'    => $registry->problems(),
        );
    }

    // =========================================================================
    //  JSON-RPC
    // =========================================================================

    /**
     * Обработать одно сообщение JSON-RPC.
     *
     * @param   array       $message
     * @param   array|null  $key        ключ агента (NULL — только публичные методы)
     * @return  array|null  ответ либо NULL для уведомлений
     */
    public function handle(array $message, ?array $key = NULL, $mode = 'full')
    {
        $id     = array_key_exists('id', $message) ? $message['id'] : NULL;
        $method = isset($message['method']) ? (string) $message['method'] : '';
        $params = (isset($message['params']) AND is_array($message['params'])) ? $message['params'] : array();
        $notify = ! array_key_exists('id', $message);

        if ($method === '')
        {
            return self::error($id, -32600, 'Не указан метод (method)');
        }

        try
        {
            switch ($method)
            {
                case 'initialize':
                    return $notify ? NULL : $this->reply($id, array(
                        'protocolVersion' => self::PROTOCOL_VERSION,
                        'capabilities'    => array(
                            'tools' => array('listChanged' => FALSE),
                        ),
                        'serverInfo'      => array(
                            'name'    => $mode === 'discovery' ? self::SERVER_NAME.'-discovery' : self::SERVER_NAME,
                            'version' => self::SERVER_VERSION,
                        ),
                        'instructions'    => $mode === 'discovery'
                            ? 'Это компактная точка входа с двумя инструментами. Сначала вызовите system.tool.search с задачей обычными словами, затем передайте готовый call из результата в system.tool.call на этом же endpoint. Никогда не придумывайте имя инструмента.'
                            : 'Управление производством PromERP. Инструменты вида production.* — заказы, состав, материалы, выпуск; system.* — сведения о сервере; audit.* — история изменений. Вызовы, изменяющие данные, требуют подтверждения человека.',
                    ));

                case 'notifications/initialized':
                case 'notifications/cancelled':
                    return NULL;

                case 'ping':
                    return $notify ? NULL : $this->reply($id, array());

                case 'tools/list':
                    if ( ! $key)
                    {
                        throw new Exception_Erp804_Mcp('Список инструментов требует ключа агента', NULL, 'unauthorized');
                    }

                    return $notify ? NULL : $this->reply($id, array('tools' => $this->tools_schema($key, $mode)));

                case 'tools/call':
                    return $notify ? NULL : $this->tools_call($id, $params, $key, $mode);

                case 'resources/list':
                    return $notify ? NULL : $this->reply($id, array('resources' => array()));

                case 'prompts/list':
                    return $notify ? NULL : $this->reply($id, array('prompts' => array()));

                default:
                    return self::error($id, -32601, 'Метод «'.$method.'» не поддерживается', array('method' => $method));
            }
        }
        catch (Exception_Erp804_Mcp $e)
        {
            return self::error($id, $e->rpc_code(), $e->getMessage(), array('reason' => $e->reason()));
        }
        catch (Throwable $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp804: сбой MCP-сервера — :error', array(':error' => $e->getMessage()));

            return self::error($id, -32603, 'Внутренняя ошибка сервера', array('reason' => 'internal'));
        }
    }

    /**
     * Обработать тело запроса: одиночное сообщение или пакет.
     *
     * @param   mixed       $payload
     * @param   array|null  $key
     * @return  array|null  ответ (массив для пакета) либо NULL, если отвечать нечем
     */
    public function handle_payload($payload, ?array $key = NULL, $mode = 'full')
    {
        if ( ! is_array($payload) OR ! $payload)
        {
            return self::error(NULL, -32700, 'Тело запроса должно быть JSON-объектом или массивом объектов');
        }

        // Пакет: список сообщений
        if (array_keys($payload) === range(0, count($payload) - 1))
        {
            $responses = array();

            foreach ($payload as $message)
            {
                if ( ! is_array($message))
                {
                    $responses[] = self::error(NULL, -32600, 'Элемент пакета должен быть объектом');
                    continue;
                }

                $response = $this->handle($message, $key, $mode);

                if ($response !== NULL)
                {
                    $responses[] = $response;
                }
            }

            return $responses ? $responses : NULL;
        }

        return $this->handle($payload, $key, $mode);
    }

    // =========================================================================
    //  Инструменты
    // =========================================================================

    /**
     * Описание инструментов для `tools/list` — только доступные ключу.
     *
     * @param   array|null  $key
     * @return  array
     */
    public function tools_schema(?array $key = NULL, $mode = 'full')
    {
        $registry = Service_Erp804_Registry::instance();
        $tools    = $key ? $registry->for_key($key) : array();
        if ($mode === 'discovery')
        {
            $tools = isset($tools['system.tool.search'])
                ? array('system.tool.search' => $tools['system.tool.search'])
                : array();
        }
        $list     = array();

        foreach ($tools as $name => $tool)
        {
            $list[] = array(
                'name'        => $name,
                'title'       => isset($tool['title']) ? $tool['title'] : $name,
                'description' => isset($tool['description']) ? $tool['description'] : '',
                // JSON Schema requires `properties` to be an object. PHP's
                // empty array otherwise becomes `[]`, which strict clients
                // such as LM Studio reject while loading the whole tool set.
                'inputSchema' => $this->normalize_input_schema(
                    isset($tool['schema']) ? $tool['schema'] : array('type' => 'object', 'properties' => array())
                ),
                'annotations' => array(
                    'readOnlyHint'    => empty($tool['mutating']),
                    // destructiveHint — «может безвозвратно изменить данные».
                    // По умолчанию повторяет mutating, но модуль может уточнить
                    // ключом 'destructive' (старт работы — не разрушение).
                    'destructiveHint' => array_key_exists('destructive', $tool)
                        ? ! empty($tool['destructive'])
                        : ! empty($tool['mutating']),
                    // idempotentHint — «повторный вызов с теми же аргументами не
                    // добавит эффекта». По умолчанию TRUE (запрос можно
                    // повторить), однако запись выработки или создание заказа
                    // повторять нельзя: модуль ставит 'idempotent' => FALSE,
                    // и агент не удвоит данные при повторе.
                    'idempotentHint'  => array_key_exists('idempotent', $tool)
                        ? ! empty($tool['idempotent'])
                        : TRUE,
                    'module'          => $tool['module'],
                    'scope'           => isset($tool['scope']) ? $tool['scope'] : '',
                    'any_key'         => ! empty($tool['self']),
                    'approval'        => ! empty($tool['approval']),
                ),
            );
        }

        if ($mode === 'discovery')
        {
            $list[] = array(
                'name' => 'system.tool.call',
                'title' => 'Вызвать найденный инструмент',
                'description' => 'Используйте ТОЛЬКО после system.tool.search. Передайте results[0].tool_arguments прямо как аргументы этого инструмента: tool_name и arguments должны быть рядом, без дополнительной внешней обёртки. Прокси повторно проверит права, read-only режим и аргументы.',
                'inputSchema' => $this->normalize_input_schema(array(
                    'type' => 'object',
                    'properties' => array(
                        // Не отмечаем required на уровне клиентской схемы: слабые
                        // клиенты иногда добавляют лишнюю обёртку arguments.
                        // Сервер развернёт её и всё равно строго проверит target.
                        'tool_name' => array('type' => 'string', 'description' => 'Точное results[0].tool_arguments.tool_name из ответа поиска; расположите на верхнем уровне'),
                        'arguments' => array('type' => 'object', 'description' => 'Аргументы целевого инструмента; не оборачивайте tool_name внутрь этого поля'),
                        'arguments_json' => array('type' => 'string', 'description' => 'Запасной формат для клиентов без object-параметров: JSON-объект строкой'),
                    ),
                    'additionalProperties' => TRUE,
                )),
                'annotations' => array(
                    'readOnlyHint' => FALSE, 'destructiveHint' => TRUE,
                    'idempotentHint' => FALSE, 'module' => 'erp804',
                    'scope' => '', 'any_key' => TRUE, 'approval' => FALSE,
                ),
            );
        }

        return $list;
    }

    /**
     * Подготовить JSON Schema к сериализации без потери типа пустого объекта.
     *
     * В PHP и список, и объект представлены массивом. `json_encode(array())`
     * даёт `[]`, хотя MCP требует `{}` для `properties`. Нормализуем также
     * вложенные object-схемы и `items`, чтобы ошибка не вернулась при появлении
     * составных аргументов.
     *
     * @param   array  $schema
     * @return  array
     */
    protected function normalize_input_schema(array $schema)
    {
        foreach ($schema as $key => $value)
        {
            if ($key === 'properties' AND is_array($value))
            {
                if ($value === array())
                {
                    $schema[$key] = new stdClass;
                    continue;
                }

                foreach ($value as $property => $definition)
                {
                    if (is_array($definition))
                    {
                        $value[$property] = $this->normalize_input_schema($definition);
                    }
                }

                $schema[$key] = $value;
                continue;
            }

            if (($key === 'items' OR $key === 'additionalProperties') AND is_array($value))
            {
                $schema[$key] = $this->normalize_input_schema($value);
            }
        }

        if (isset($schema['type']) AND $schema['type'] === 'object' AND ! array_key_exists('properties', $schema))
        {
            $schema['properties'] = new stdClass;
        }

        return $schema;
    }

    /**
     * `tools/call`.
     *
     * @param   mixed       $id
     * @param   array       $params
     * @param   array|null  $key
     * @return  array
     */
    protected function tools_call($id, array $params, ?array $key = NULL, $mode = 'full')
    {
        if ( ! $key)
        {
            throw new Exception_Erp804_Mcp('Вызов инструмента требует ключа агента', NULL, 'unauthorized');
        }

        $name = isset($params['name']) ? (string) $params['name'] : '';
        $args = (isset($params['arguments']) AND is_array($params['arguments'])) ? $params['arguments'] : array();

        if ($name === '')
        {
            throw new Exception_Erp804_Mcp('Не указано имя инструмента', NULL, 'invalid_arguments');
        }
        if ($mode === 'discovery' AND $name === 'system.tool.call')
        {
            // ToolForge/Pydantic-обвязки слабых моделей иногда передают
            // {arguments:{tool_name,arguments}} вместо {tool_name,arguments}.
            // Клиентская required-проверка больше не отсекает такой вызов, а
            // здесь безопасно снимается ровно одна лишняя оболочка.
            if (empty($args['tool_name'])
                AND isset($args['arguments']) AND is_array($args['arguments'])
                AND isset($args['arguments']['tool_name']))
            {
                $args = $args['arguments'];
            }

            $target = isset($args['tool_name']) ? trim((string) $args['tool_name']) : '';
            if (isset($args['arguments']) AND is_array($args['arguments']))
            {
                $target_args = $args['arguments'];
            }
            elseif ( ! empty($args['arguments_json']) AND is_string($args['arguments_json']))
            {
                $decoded = json_decode($args['arguments_json'], TRUE);
                $target_args = is_array($decoded) ? $decoded : array();
            }
            else
            {
                // Терпимый формат для слабых клиентов: tool_name + аргументы
                // цели прямо в корне, без обязательной обёртки arguments.
                $target_args = $args;
                unset($target_args['tool_name'], $target_args['arguments_json']);
            }
            // Даже если слабая модель проигнорировала рекомендацию поиска и
            // попросила старый каталог, не возвращаем 162 описания в контекст.
            if ($target === 'system.tools.list')
            {
                $target = 'system.tools.summary';
                $target_args = array();
            }
            if ($target === '')
            {
                throw new Exception_Erp804_Mcp('Поле tool_name пусто. Скопируйте results[N].name из system.tool.search', NULL, 'invalid_arguments');
            }
            if (in_array($target, array('system.tool.search', 'system.tool.call'), TRUE))
            {
                throw new Exception_Erp804_Mcp(
                    'В tool_name передано имя обёртки '.$target.'. Здесь требуется имя ЦЕЛЕВОГО инструмента из results[N].name, например planning.program.get',
                    NULL,
                    'invalid_arguments'
                );
            }
            // Повторно проходим полный штатный pipeline: scope, read-only,
            // validation, approval, idempotency и журнал целевого вызова.
            return $this->tools_call($id, array(
                'name' => $target,
                'arguments' => $target_args,
                'idempotency_key' => isset($params['idempotency_key']) ? $params['idempotency_key'] : NULL,
            ), $key, 'full');
        }
        if ($mode === 'discovery' AND $name !== 'system.tool.search')
        {
            throw new Exception_Erp804_Mcp(
                'Сначала вызовите system.tool.search, затем system.tool.call.',
                NULL,
                'unknown_tool'
            );
        }

        $registry = Service_Erp804_Registry::instance();
        $tool     = $registry->get($name);

        if ( ! $tool)
        {
            throw new Exception_Erp804_Mcp('Инструмент «:name» не объявлен', array(':name' => $name), 'unknown_tool');
        }

        $keys = Service_Erp804_Keys::instance();

        if (empty($tool['self']) AND ! $keys->allows($key, isset($tool['scope']) ? $tool['scope'] : ''))
        {
            throw new Exception_Erp804_Mcp(
                'Ключу «:key» не разрешено право :scope',
                array(':key' => isset($key['name']) ? $key['name'] : '?', ':scope' => $tool['scope']),
                'forbidden'
            );
        }

        if ( ! empty($key['read_only']) AND ! empty($tool['mutating']))
        {
            throw new Exception_Erp804_Mcp('Ключ работает только на чтение — изменяющие инструменты недоступны', NULL, 'read_only');
        }

        $idempotency = NULL;

        foreach (array('idempotency_key', '_idempotency_key') as $field)
        {
            if ( ! empty($params[$field]))
            {
                $idempotency = (string) $params[$field];
            }
        }

        $calls = Service_Erp804_Calls::instance();

        $call = $calls->start($name, $key, $args, array(
            'mutating'        => ! empty($tool['mutating']),
            'idempotency_key' => $idempotency,
            'ip'              => Request::$client_ip,
        ));

        // Повторный вызов с тем же idempotency_key: отдаём прежний результат
        if ($call['status'] === 'ok' AND $call['result'] !== NULL)
        {
            return $this->tool_result($id, $call['result'], array('call_id' => (int) $call['id'], 'replayed' => TRUE));
        }

        if ( ! empty($tool['approval']) AND $call['status'] !== 'approved')
        {
            $call = $calls->await_approval($call['id'], 'Инструмент требует подтверждения человека');

            return $this->reply($id, array(
                'content' => array(array(
                    'type' => 'text',
                    'text' => 'Операция «'.$name.'» ожидает подтверждения человека. Вызов #'.$call['id'].' ('.$call['uuid'].').'
                        .' Проверить статус можно инструментом system.call.status.',
                )),
                'structuredContent' => array(
                    'status'  => 'awaiting_approval',
                    'call_id' => (int) $call['id'],
                    'uuid'    => $call['uuid'],
                ),
                'isError' => FALSE,
            ));
        }

        list($clean, $errors) = $registry->validate_arguments($name, $args);

        if ($errors)
        {
            $calls->finish($call['id'], NULL, implode('; ', $errors), 'invalid_arguments', 0);

            $required = isset($tool['schema']['required']) ? (array) $tool['schema']['required'] : array();
            throw new Exception_Erp804_Mcp(
                'Инструмент :tool: аргументы не приняты: :errors. Обязательные поля: :required. Получены поля: :received.',
                array(
                    ':tool' => $name,
                    ':errors' => implode('; ', $errors),
                    ':required' => $required ? implode(', ', $required) : 'нет',
                    ':received' => $args ? implode(', ', array_keys($args)) : 'нет',
                ),
                'invalid_arguments'
            );
        }

        $started = microtime(TRUE);

        $registry->set_context(array('key' => $key, 'call' => $call, 'server' => $this));

        try
        {
            $data = $registry->call($name, $clean);
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $failed = $calls->finish($call['id'], NULL, $e->getMessage(), $e->reason(), (int) round((microtime(TRUE) - $started) * 1000));

            if ( ! empty($tool['mutating']))
            {
                $calls->journal($failed, 'execute');
            }

            return $this->reply($id, array(
                'content' => array(array('type' => 'text', 'text' => $e->getMessage())),
                'structuredContent' => array('status' => 'error', 'reason' => $e->reason(), 'call_id' => (int) $call['id']),
                'isError' => TRUE,
            ));
        }

        $done = $calls->finish($call['id'], $data, NULL, NULL, (int) round((microtime(TRUE) - $started) * 1000));

        if ( ! empty($tool['mutating']))
        {
            $calls->journal($done, 'execute');
        }

        return $this->tool_result($id, $data, array('call_id' => (int) $done['id']));
    }

    /**
     * Выполнить инструмент без проверок (используется после одобрения человеком).
     *
     * @param   string  $name
     * @param   array   $args
     * @param   array   $call
     * @return  array
     */
    public function execute($name, array $args, array $call = array())
    {
        $registry = Service_Erp804_Registry::instance();

        list($clean, $errors) = $registry->validate_arguments($name, $args);

        if ($errors)
        {
            throw new Exception_Erp804_Mcp(
                'Аргументы не приняты: :errors',
                array(':errors' => implode('; ', $errors)),
                'invalid_arguments'
            );
        }

        // Контекст: одобренный вызов выполняется без ключа, но с записью журнала
        $registry->set_context(array('key' => NULL, 'call' => $call, 'approved' => TRUE, 'server' => $this));

        return $registry->call($name, $clean);
    }

    // =========================================================================
    //  Оформление ответов
    // =========================================================================

    /**
     * Ответ на успешный вызов инструмента.
     *
     * @param   mixed   $id
     * @param   array   $data
     * @param   array   $meta
     * @return  array
     */
    protected function tool_result($id, array $data, array $meta = array())
    {
        return $this->reply($id, array(
            'content'           => array(array('type' => 'text', 'text' => $this->as_text($data))),
            'structuredContent' => $data + $meta,
            'isError'           => FALSE,
        ));
    }

    /**
     * Человекочитаемый текст результата: подпись (если есть) плюс JSON.
     *
     * @param   array   $data
     * @return  string
     */
    protected function as_text(array $data)
    {
        // `summary` — договорённость протокола: короткая строка-итог для
        // человека. Инструмент может назвать так и структуру (числовая сводка
        // отчёта) — тогда приведение к строке даёт «Array to string
        // conversion», и весь ответ сервера падает в «внутреннюю ошибку».
        // Не-скалярную подпись пропускаем: JSON рядом и так несёт данные.
        $summary = (isset($data['summary']) AND is_scalar($data['summary'])) ? (string) $data['summary'] : '';
        $json    = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (strlen($json) > 8000)
        {
            $json = substr($json, 0, 8000)."\n… (ответ обрезан, уточните запрос)";
        }

        return trim($summary."\n".$json);
    }

    /**
     * @param   mixed   $id
     * @param   array   $result
     * @return  array
     */
    protected function reply($id, array $result)
    {
        return array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        );
    }

    /**
     * @param   mixed   $id
     * @param   int     $code
     * @param   string  $message
     * @param   array   $data
     * @return  array
     */
    public static function error($id, $code, $message, ?array $data = NULL)
    {
        $error = array('code' => (int) $code, 'message' => $message);

        if ($data)
        {
            $error['data'] = $data;
        }

        return array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $error,
        );
    }
}
