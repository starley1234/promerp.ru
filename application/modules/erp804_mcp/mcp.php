<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Служебные инструменты MCP-сервера (erp804_mcp).
 *
 * Их задача — чтобы агент мог осмотреться: какой у него ключ и права, какие
 * инструменты доступны, что стало с его прошлым вызовом (например, после
 * подтверждения человеком) и что вообще происходило в системе.
 *
 * @package    Erp804
 * @category   Mcp
 */

$s = function (array $properties, array $required = array()) {
    return array(
        'type'       => 'object',
        'properties' => $properties,
        'required'   => $required,
    );
};

$field = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};

return array(
    'module' => 'erp804',
    'tools'  => array(

        'system.tool.search' => array(
            'title'       => 'Найти нужный инструмент по задаче',
            'description' => 'ВСЕГДА используйте этот инструмент первым. Опишите обычными словами, что нужно сделать. Он вернёт только 1–5 доступных инструментов, объяснит аргументы и даст готовый вызов system.tool.call на этом же endpoint. Не придумывайте имя инструмента самостоятельно.',
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $key = Service_Erp804_Registry::instance()->context('key');
                if ( ! $key)
                {
                    throw new Exception_Erp804_Mcp('Поиск инструментов требует ключа агента', NULL, 'unauthorized');
                }
                return Service_Erp804_Discovery::instance()->search(
                    $args['query'],
                    $key,
                    isset($args['limit']) ? (int) $args['limit'] : 3,
                    isset($args['mode']) ? $args['mode'] : 'read'
                );
            },
            'schema'      => $s(array(
                'query' => $field('string', 'Задача обычными словами. Хорошо: «показать остатки ABS на складе». Плохо: одно общее слово «данные».'),
                'mode'  => $field('string', 'Искать чтение, изменение или оба вида. Без явного выбора безопасно ищется только чтение', array('enum' => array('any', 'read', 'write'), 'default' => 'read')),
                'limit' => $field('integer', 'Число кандидатов; обычно достаточно 3', array('minimum' => 1, 'maximum' => 5, 'default' => 3)),
            ), array('query')),
        ),

        'system.tools.summary' => array(
            'title'       => 'Краткая карта доступных инструментов',
            'description' => 'Компактная замена большому system.tools.list. Возвращает только общее количество и число доступных инструментов по модулям, не выгружая сотни названий в контекст. После сводки ищите конкретное действие через system.tool.search.',
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $registry = Service_Erp804_Registry::instance();
                $key = $registry->context('key');
                if ( ! $key)
                {
                    throw new Exception_Erp804_Mcp('Сводка инструментов требует ключа агента', NULL, 'unauthorized');
                }
                $counts = array();
                foreach ($registry->for_key($key) as $tool)
                {
                    $module = isset($tool['module']) ? $tool['module'] : 'other';
                    $counts[$module] = isset($counts[$module]) ? $counts[$module] + 1 : 1;
                }
                ksort($counts);
                return array(
                    'summary' => 'Доступно инструментов: '.array_sum($counts).'. Для выбора опишите конкретную задачу в system.tool.search.',
                    'total' => array_sum($counts),
                    'modules' => $counts,
                    'read_only' => ! empty($key['read_only']),
                    'next_step' => 'Не запрашивайте полный каталог. Вызовите system.tool.search с объектом и действием, например: «показать потребность программы выпуска».',
                );
            },
            'schema'      => $s(array()),
        ),

        'system.health' => array(
            'title'       => 'Готовность системы',
            'description' => 'Короткая preflight-проверка БД, MCP-реестра и демо-контура. Возвращает машинный status ready/degraded до начала сквозного теста.',
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $location = DB::query(Database::SELECT, 'SELECT current_database() AS database, current_schema() AS schema, version() AS version')
                    ->execute()->current();
                $problems = Service_Erp804_Registry::instance()->problems();
                $demo = class_exists('Service_Erp811_Demo') ? Service_Erp811_Demo::instance()->status() : NULL;
                $ready = empty($problems) AND ($demo === NULL OR ! empty($demo['available']));

                return array(
                    'summary' => $ready ? 'Система готова к агентскому тестированию' : 'Система требует внимания перед тестированием',
                    'status' => $ready ? 'ready' : 'degraded',
                    'database' => array('reachable' => TRUE, 'name' => $location['database'], 'schema' => $location['schema'], 'version' => $location['version']),
                    'mcp' => array('tools' => count(Service_Erp804_Registry::instance()->tools()), 'problems' => $problems),
                    'demo' => $demo === NULL ? array('enabled' => FALSE) : array(
                        'enabled' => $demo['enabled'], 'available' => $demo['available'],
                        'build_state' => $demo['build_state'], 'tables' => $demo['tables'], 'built_at' => $demo['built_at'],
                    ),
                );
            },
            'schema'      => $s(array()),
        ),

        'system.whoami' => array(
            'title'       => 'Кто я и что мне можно',
            'description' => 'Возвращает имя ключа агента, его права и признак «только чтение». Полезно первым вызовом, чтобы не пытаться делать запрещённое.',
            // Доступно любому действующему ключу: агент спрашивает о себе,
            // а не о чужих данных.
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $key = Service_Erp804_Registry::instance()->context('key');

                if ( ! $key)
                {
                    return array('summary' => 'Вызов без ключа агента', 'key' => NULL, 'scopes' => array());
                }

                $read_only = ! empty($key['read_only']);
                $runtime = DB::query(Database::SELECT, 'SELECT current_database() AS database, current_schema() AS schema')
                    ->execute()->current();
                return array(
                    'summary'   => $read_only
                        ? 'Ключ «'.$key['name'].'» работает только на чтение; безопасен для read-only QA.'
                        : 'ВНИМАНИЕ: ключ «'.$key['name'].'» разрешает запись. Если задача требует read-only QA — ОСТАНОВИТЕСЬ и запросите read-only ключ.',
                    'key'       => $key['name'],
                    'client'    => isset($key['client']) ? $key['client'] : NULL,
                    'scopes'    => $key['scopes'],
                    'read_only' => $read_only,
                    'configured_read_only' => array_key_exists('configured_read_only', $key) ? (bool) $key['configured_read_only'] : $read_only,
                    'endpoint_read_only' => ! empty($key['endpoint_read_only']),
                    'endpoint' => isset($key['endpoint_path']) ? $key['endpoint_path'] : '/mcp',
                    'runtime' => array('database' => $runtime['database'], 'schema' => $runtime['schema']),
                    'qa_read_only_safe' => $read_only,
                    'blocking_warning' => $read_only ? NULL : 'Ключ не является read-only. Не продолжайте проверку, если изменение данных запрещено.',
                    'calls'     => (int) $key['calls_count'],
                );
            },
            'schema'      => $s(array()),
        ),

        'system.tools.list' => array(
            'title'       => 'Список инструментов',
            'description' => 'Каталог инструментов с правами и признаком изменения данных. Можно ограничить одним модулем (erp402, erp119, erp804 …).',
            // Доступно любому действующему ключу: агент спрашивает о себе,
            // а не о чужих данных.
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $registry = Service_Erp804_Registry::instance();
                $tools    = $registry->catalogue();

                if ( ! empty($args['module']))
                {
                    $tools = array_values(array_filter($tools, function ($tool) use ($args) {
                        return $tool['module'] === $args['module'];
                    }));
                }

                $rows = array();

                foreach ($tools as $tool)
                {
                    $rows[] = array(
                        'name'     => $tool['name'],
                        'title'    => $tool['title'],
                        'scope'    => $tool['scope'],
                        'mutating' => $tool['mutating'],
                        'approval' => $tool['approval'],
                    );
                }

                return array('summary' => 'Инструментов: '.count($rows), 'tools' => $rows, 'modules' => $registry->sources());
            },
            'schema'      => $s(array(
                'module' => $field('string', 'Модуль-владелец инструментов (erp402, erp119, erp804)'),
            )),
        ),

        'system.call.status' => array(
            'title'       => 'Статус вызова',
            'description' => 'Что стало с вызовом: выполняется, выполнен, ошибка, ждёт подтверждения человека или отклонён. Нужен после операций, требующих согласования.',
            // Доступно любому действующему ключу: агент спрашивает о себе,
            // а не о чужих данных.
            'self'        => TRUE,
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $call = Service_Erp804_Calls::instance()->find($args['call_id']);

                if ( ! $call)
                {
                    throw new Exception_Erp804_Mcp('Вызов :id не найден', array(':id' => $args['call_id']), 'invalid_arguments');
                }

                return array(
                    'summary' => 'Вызов #'.$call['id'].' ('.$call['tool_name'].'): '.$call['status'],
                    'call_id' => (int) $call['id'],
                    'uuid'    => $call['uuid'],
                    'tool'    => $call['tool_name'],
                    'status'  => $call['status'],
                    'error'   => $call['error'],
                    'result'  => $call['result'],
                );
            },
            'schema'      => $s(array(
                'call_id' => $field('integer', 'Номер вызова из ответа tools/call'),
            ), array('call_id')),
        ),

        'system.last_calls' => array(
            'title'       => 'Последние вызовы',
            'description' => 'Журнал вызовов MCP: кто (ключ), что вызывал, чем закончилось. Помогает разобраться, если агент «что-то сделал».',
            'scope'       => 'erp804_mcp.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $result = Service_Erp804_Calls::instance()->search(array(
                    'tool_name' => isset($args['tool_name']) ? $args['tool_name'] : NULL,
                    'status'    => isset($args['status']) ? $args['status'] : NULL,
                ), isset($args['limit']) ? (int) $args['limit'] : 10, 0);

                $rows = array();

                foreach ($result['rows'] as $call)
                {
                    $rows[] = array(
                        'id'        => (int) $call['id'],
                        'tool'      => $call['tool_name'],
                        'key'       => $call['key_name'],
                        'status'    => $call['status'],
                        'mutating'  => (bool) $call['mutating'],
                        'error'     => $call['error'],
                        'created_at'=> $call['created_at'],
                    );
                }

                return array('summary' => 'Вызовов: '.count($rows), 'calls' => $rows, 'total' => (int) $result['total']);
            },
            'schema'      => $s(array(
                'tool_name' => $field('string', 'Фильтр по имени инструмента'),
                'status'    => $field('string', 'Фильтр по статусу', array('enum' => array('ok', 'error', 'awaiting_approval', 'approved', 'rejected', 'pending'))),
                'limit'     => $field('integer', 'Сколько записей вернуть', array('minimum' => 1, 'maximum' => 50, 'default' => 10)),
            )),
        ),
    ),
);
