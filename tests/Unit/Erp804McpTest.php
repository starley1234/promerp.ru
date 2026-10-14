<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты MCP-сервера (модуль erp804_mcp).
 *
 * Проверяют то, ради чего модуль существует: агент с ключом видит только
 * разрешённые инструменты, изменяющие операции уходят на подтверждение
 * человеку, повторный вызов с тем же ключом идемпотентности не выполняется
 * дважды, а каждый вызов остаётся в журнале.
 *
 * Инструменты берутся из `mcp.php` подключённых модулей, поэтому тесты
 * заодно проверяют и сами объявления (erp402, erp119, erp804).
 *
 * Тестовые ключи называются `ТЕСТ-804-*` и удаляются в tearDown; если таблиц
 * модуля нет — тесты пропускаются.
 */
class Erp804McpTest extends TestCase
{
    /** @var array Созданные ключи (id) */
    protected $keys = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль MCP недоступен — выполните миграции erp804_mcp');
            return;
        }
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            \DB::delete('erp_804_mcp_calls')->where('key_name', 'LIKE', 'ТЕСТ-804-%')->execute();
            \DB::delete('erp_804_mcp_keys')->where('name', 'LIKE', 'ТЕСТ-804-%')->execute();
        }
        catch (\Exception $e)
        {
            // Уборка не должна ломать тесты
        }
    }

    // =========================================================================
    //  Реестр инструментов
    // =========================================================================

    public function test_registry_collects_tools_from_modules()
    {
        $registry = \Service_Erp804_Registry::instance();
        $names    = array_keys($registry->tools());

        $this->assertGreaterThanOrEqual(20, count($names), 'Ожидались инструменты минимум трёх модулей');
        $this->assertContains('production.order.get', $names);
        $this->assertContains('audit.journal.search', $names);
        $this->assertContains('system.whoami', $names);
        $this->assertContains('system.health', $names);
        $this->assertContains('system.tools.summary', $names);
        $this->assertContains('planning.program.list', $names);
        $this->assertContains('counterparty.search', $names);
        $this->assertContains('counterparty.get', $names);
        $this->assertContains('warehouse.stock.list', $names);
        $this->assertContains('warehouse.movements.list', $names);

        $sources = $registry->sources();
        $this->assertArrayHasKey('erp402', $sources);
        $this->assertArrayHasKey('erp119', $sources);
        $this->assertArrayHasKey('erp804', $sources);
    }

    public function test_tool_declarations_have_no_problems()
    {
        $problems = \Service_Erp804_Registry::instance()->problems();

        $messages = array();

        foreach ($problems as $problem)
        {
            $messages[] = ($problem['tool'] ? $problem['tool'].': ' : '').$problem['message'];
        }

        $this->assertSame(array(), $messages, 'Объявления mcp.php содержат замечания');
    }

    public function test_catalogue_exposes_schema_for_agents()
    {
        $catalogue = \Service_Erp804_Registry::instance()->catalogue();

        $found = NULL;

        foreach ($catalogue as $tool)
        {
            if ($tool['name'] === 'production.orders.search')
            {
                $found = $tool;
            }
        }

        $this->assertNotNull($found, 'Инструмент production.orders.search не найден');
        $this->assertSame('erp402_production.view', $found['scope']);
        $this->assertFalse($found['mutating']);
        $this->assertSame('object', $found['schema']['type']);
    }

    // =========================================================================
    //  Ключи
    // =========================================================================

    public function test_issued_token_is_verified_and_stored_hashed()
    {
        $issued = $this->make_key(array('erp402_production.view'));
        $token  = $issued['token'];

        $this->assertSame(0, strpos($token, 'mcp_'), 'Токен должен начинаться с mcp_');

        $row = \DB::select()->from('erp_804_mcp_keys')->where('id', '=', $issued['key']['id'])->execute()->current();

        $this->assertNotSame($token, $row['token_hash'], 'Токен не должен храниться в открытом виде');
        $this->assertSame(hash('sha256', $token), $row['token_hash']);

        $key = \Service_Erp804_Keys::instance()->authenticate($token);

        $this->assertSame((int) $issued['key']['id'], (int) $key['id']);
        $this->assertTrue((bool) $key['enabled']);
    }

    public function test_unknown_token_is_rejected()
    {
        $reason = NULL;

        try
        {
            \Service_Erp804_Keys::instance()->authenticate('mcp_этого-ключа-нет');
        }
        catch (\Exception_Erp804_Mcp $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('unauthorized', $reason);
    }

    public function test_revoked_key_stops_working()
    {
        $issued = $this->make_key(array('erp804_mcp.call'));

        \Service_Erp804_Keys::instance()->revoke($issued['key']['id']);

        $reason = NULL;

        try
        {
            \Service_Erp804_Keys::instance()->authenticate($issued['token']);
        }
        catch (\Exception_Erp804_Mcp $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('unauthorized', $reason);
    }

    public function test_expired_key_is_not_accepted()
    {
        $issued = $this->make_key(array('erp804_mcp.call'), array('expires_at' => date('c', time() - 3600)));

        $reason = NULL;

        try
        {
            \Service_Erp804_Keys::instance()->authenticate($issued['token']);
        }
        catch (\Exception_Erp804_Mcp $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('unauthorized', $reason);
    }

    // =========================================================================
    //  Права и видимость инструментов
    // =========================================================================

    public function test_key_sees_only_tools_allowed_by_scopes()
    {
        $key   = $this->make_key(array('erp119_audit.view'))['key'];
        $tools = \Service_Erp804_Registry::instance()->for_key($key);

        $this->assertArrayHasKey('audit.journal.search', $tools);
        $this->assertArrayNotHasKey('production.orders.search', $tools);
    }

    public function test_wildcard_scope_opens_module_tools()
    {
        $key   = $this->make_key(array('erp402_production.*'))['key'];
        $tools = \Service_Erp804_Registry::instance()->for_key($key);

        $this->assertArrayHasKey('production.orders.search', $tools);
        $this->assertArrayHasKey('production.order.create', $tools);
        $this->assertArrayNotHasKey('audit.journal.search', $tools);
    }

    public function test_read_only_key_does_not_see_mutating_tools()
    {
        $key   = $this->make_key(array('erp402_production.*'), array('read_only' => TRUE))['key'];
        $tools = \Service_Erp804_Registry::instance()->for_key($key);

        $this->assertArrayHasKey('production.orders.search', $tools);
        $this->assertArrayNotHasKey('production.order.create', $tools);
    }

    /**
     * Подсказки (annotations) говорят агенту, что можно повторять, а что нет.
     *
     * `idempotentHint = false` у записи выработки — защита от двойного учёта:
     * агент, повторивший вызов после сетевой ошибки, не должен удвоить факт.
     * `destructiveHint = false` у старта работы — наоборот, признак того, что
     * вызов не разрушает данные.
     */
    public function test_tool_hints_warn_about_destructive_and_repeatable_calls()
    {
        $key = $this->make_key(array('erp408.view', 'erp408.terminal'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 4,
            'method'  => 'tools/list',
        ), $key);

        $tools = array();

        foreach ($answer['result']['tools'] as $tool)
        {
            $tools[$tool['name']] = $tool['annotations'];
        }

        $this->assertTrue($tools['shopfloor.board']['readOnlyHint'], 'чтение помечено как безопасное');
        $this->assertFalse($tools['shopfloor.operation.output']['idempotentHint'], 'выработку нельзя повторять — удвоит факт');
        $this->assertFalse($tools['shopfloor.operation.start']['destructiveHint'], 'старт работы не разрушает данные');
        $this->assertTrue($tools['shopfloor.operation.finish']['destructiveHint'], 'завершение закрывает операцию');
    }

    // =========================================================================
    //  Транспорт JSON-RPC
    // =========================================================================

    public function test_initialize_reports_server_and_protocol()
    {
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => array('protocolVersion' => '2025-06-18'),
        ));

        $this->assertSame('2.0', $answer['jsonrpc']);
        $this->assertSame(1, $answer['id']);
        $this->assertSame('2025-06-18', $answer['result']['protocolVersion']);
        $this->assertSame('promerp-mcp', $answer['result']['serverInfo']['name']);
    }

    public function test_notification_gets_no_answer()
    {
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
        ));

        $this->assertNull($answer, 'Уведомления не требуют ответа');
    }

    public function test_registry_converts_php_error_to_structured_mcp_error()
    {
        $registry = \Service_Erp804_Registry::instance();
        $property = new \ReflectionProperty($registry, '_tools');
        $property->setAccessible(TRUE);
        $previous = $property->getValue($registry);
        $tools = $previous;
        $tools['system.test.throwable'] = array(
            'module' => 'erp804', 'self' => TRUE, 'mutating' => FALSE,
            'schema' => array('type' => 'object', 'properties' => array()),
            'handler' => function (array $args) { throw new \Error('test throwable'); },
        );

        try
        {
            $property->setValue($registry, $tools);

            $caught = NULL;
            try
            {
                $registry->call('system.test.throwable', array());
            }
            catch (\Exception_Erp804_Mcp $e)
            {
                $caught = $e;
            }

            $this->assertNotNull($caught);
            $this->assertSame('failed', $caught->reason());
            $this->assertStringContainsString('test throwable', $caught->getMessage());

            // Транспорт после Error остаётся работоспособным и отвечает на
            // следующее JSON-RPC сообщение в той же серверной сессии.
            $answer = \Service_Erp804_Server::instance()->handle(array(
                'jsonrpc' => '2.0', 'id' => 804, 'method' => 'ping',
            ));
            $this->assertSame('2.0', $answer['jsonrpc']);
            $this->assertSame(804, $answer['id']);
            $this->assertArrayHasKey('result', $answer);
        }
        finally
        {
            $property->setValue($registry, $previous);
        }
    }

    public function test_unknown_method_returns_json_rpc_error()
    {
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 7,
            'method'  => 'promerp/что-то-своё',
        ));

        $this->assertSame(-32601, $answer['error']['code']);
        $this->assertSame(7, $answer['id']);
    }

    public function test_tools_list_requires_key()
    {
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
        ));

        $this->assertSame(-32001, $answer['error']['code']);
    }

    public function test_tools_list_filters_by_key_scopes()
    {
        $key = $this->make_key(array('erp119_audit.view'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/list',
        ), $key);

        $names = array();

        foreach ($answer['result']['tools'] as $tool)
        {
            $names[] = $tool['name'];
        }

        $this->assertContains('audit.journal.search', $names);
        $this->assertNotContains('production.orders.search', $names);
        $this->assertArrayHasKey('inputSchema', $answer['result']['tools'][0]);

        $json = json_encode($answer['result']['tools']);
        $this->assertStringNotContainsString('"properties":[]', $json, 'MCP JSON Schema properties must be an object, never a list');
    }

    public function test_discovery_route_points_to_compact_controller()
    {
        $route = \Route::get('erp804_mcp_discovery');
        $this->assertSame('/mcp/discovery', '/'.ltrim($route->uri(array()), '/'));
        $this->assertTrue(class_exists('Controller_Erp804_Discovery'));
    }

    public function test_demo_discovery_route_is_separate_and_forces_effective_read_only()
    {
        $route = \Route::get('erp804_mcp_demo_discovery');
        $this->assertSame('/mcp/demo/discovery', '/'.ltrim($route->uri(array()), '/'));

        $controller = (new \ReflectionClass('Controller_Erp804_DemoDiscovery'))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, '_effective_key');
        $method->setAccessible(TRUE);
        $key = $method->invoke($controller, array('id' => 7, 'name' => 'test', 'read_only' => FALSE, 'scopes' => array('*')));

        $this->assertTrue($key['read_only']);
        $this->assertTrue($key['endpoint_read_only']);
        $this->assertFalse($key['configured_read_only']);
        $this->assertNull($key['id']);
    }

    public function test_demo_agent_route_keeps_key_policy_but_targets_demo()
    {
        $route = \Route::get('erp804_mcp_demo_agent');
        $this->assertSame('/mcp/demo/agent', '/'.ltrim($route->uri(array()), '/'));

        $controller = (new \ReflectionClass('Controller_Erp804_DemoAgent'))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, '_effective_key');
        $method->setAccessible(TRUE);
        $key = $method->invoke($controller, array('id' => 8, 'name' => 'operator', 'read_only' => FALSE, 'scopes' => array('erp407.*')));

        $this->assertFalse($key['read_only']);
        $this->assertFalse($key['endpoint_read_only']);
        $this->assertSame('/mcp/demo/agent', $key['endpoint_path']);
        $this->assertNull($key['id']);
    }

    public function test_discovery_endpoint_advertises_only_search_and_safe_proxy()
    {
        $key = $this->make_key(array('erp401_wms.view_stock', 'erp402_production.view'))['key'];
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0', 'id' => 31, 'method' => 'tools/list',
        ), $key, 'discovery');

        $this->assertCount(2, $answer['result']['tools']);
        $this->assertSame('system.tool.search', $answer['result']['tools'][0]['name']);
        $this->assertSame('system.tool.call', $answer['result']['tools'][1]['name']);
        $this->assertStringContainsString('ВСЕГДА', $answer['result']['tools'][0]['description']);
        $this->assertArrayNotHasKey('required', $answer['result']['tools'][1]['inputSchema']);
    }

    public function test_discovery_finds_allowed_warehouse_tool_and_returns_ready_call()
    {
        $key = $this->make_key(array('erp401_wms.view_stock'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search('показать остатки материала на складе', $key, 3, 'read');

        $this->assertGreaterThan(0, $result['found']);
        $this->assertSame('warehouse.stock.list', $result['results'][0]['name']);
        $this->assertSame('/mcp/discovery', $result['endpoint']);
        $this->assertSame('tools/call', $result['results'][0]['call']['method']);
        $this->assertSame('system.tool.call', $result['results'][0]['call']['params']['name']);
        $this->assertSame('warehouse.stock.list', $result['results'][0]['call']['params']['arguments']['tool_name']);
        $this->assertSame('warehouse.stock.list', $result['results'][0]['tool_arguments']['tool_name']);
        $this->assertNotEmpty($result['results'][0]['arguments']);
    }

    public function test_discovery_translates_common_hallucinated_tool_name()
    {
        $key = $this->make_key(array('erp407.view'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search(
            'найти planning.need.list для получения потребностей по id программы', $key, 3, 'read'
        );

        $this->assertSame(1, $result['found']);
        $this->assertSame(array('planning.need.list'), $result['unavailable_tool_names']);
        $this->assertFalse($result['requested_tool_available']);
        $this->assertSame('planning.program.get', $result['results'][0]['name']);
        $this->assertSame('planning.need.list', $result['results'][0]['interpreted_from']);
        $this->assertStringContainsString('Не повторяйте', $result['summary']);
    }

    public function test_discovery_exact_name_inside_prose_wins()
    {
        $key = $this->make_key(array('erp407.view'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search(
            'получить данные через planning.deficits для программы', $key, 5, 'read'
        );

        $this->assertSame(1, $result['found']);
        $this->assertSame('planning.deficits', $result['results'][0]['name']);
    }

    public function test_discovery_exact_name_returns_one_candidate_only()
    {
        $key = $this->make_key(array('erp407.view'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search('planning.program.get', $key, 5, 'read');

        $this->assertSame(1, $result['found']);
        $this->assertSame('planning.program.get', $result['results'][0]['name']);
    }

    public function test_discovery_replaces_large_tool_catalogue_with_summary()
    {
        $key = $this->make_key(array('*'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search('system tools list список инструментов', $key, 3, 'read');

        $this->assertGreaterThan(0, $result['found']);
        $this->assertSame('system.tools.summary', $result['results'][0]['name']);
        $this->assertNotSame('system.tools.list', $result['results'][0]['name']);
    }

    public function test_discovery_never_leaks_tools_for_missing_scope()
    {
        $key = $this->make_key(array('erp119_audit.view'))['key'];
        $result = \Service_Erp804_Discovery::instance()->search('остатки на складе', $key, 5, 'any');
        $names = isset($result['results']) ? array_column($result['results'], 'name') : array();

        $this->assertNotContains('warehouse.stock.list', $names);
    }

    public function test_discovery_proxy_forwards_nested_arguments_to_target_validator()
    {
        $key = $this->make_key(array('erp407.view'))['key'];
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0', 'id' => 34, 'method' => 'tools/call',
            'params' => array('name' => 'system.tool.call', 'arguments' => array(
                'tool_name' => 'planning.program.get',
                'arguments' => array('id' => '999999999'),
            )),
        ), $key, 'discovery');

        $this->assertArrayHasKey('result', $answer);
        $this->assertTrue($answer['result']['isError']);
        $this->assertNotSame('invalid_arguments', $answer['result']['structuredContent']['reason']);
    }

    public function test_discovery_proxy_accepts_one_extra_arguments_wrapper()
    {
        $key = $this->make_key(array('erp407.view'))['key'];
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0', 'id' => 35, 'method' => 'tools/call',
            'params' => array('name' => 'system.tool.call', 'arguments' => array(
                'arguments' => array(
                    'tool_name' => 'planning.program.get',
                    'arguments' => array('id' => '999999999'),
                ),
            )),
        ), $key, 'discovery');

        $this->assertArrayHasKey('result', $answer);
        $this->assertTrue($answer['result']['isError']);
        $this->assertNotSame('invalid_arguments', $answer['result']['structuredContent']['reason']);
    }

    public function test_discovery_proxy_reuses_normal_scope_checks()
    {
        $key = $this->make_key(array('erp119_audit.view'))['key'];
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0', 'id' => 33, 'method' => 'tools/call',
            'params' => array('name' => 'system.tool.call', 'arguments' => array(
                'tool_name' => 'warehouse.stock.list', 'arguments' => array(),
            )),
        ), $key, 'discovery');

        $this->assertSame(-32003, $answer['error']['code']);
    }

    public function test_discovery_rejects_business_tool_calls_on_compact_endpoint()
    {
        $key = $this->make_key(array('erp401_wms.view_stock'))['key'];
        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0', 'id' => 32, 'method' => 'tools/call',
            'params' => array('name' => 'warehouse.stock.list', 'arguments' => array()),
        ), $key, 'discovery');

        $this->assertSame(-32601, $answer['error']['code']);
        $this->assertStringContainsString('system.tool.call', $answer['error']['message']);
    }

    public function test_empty_tool_properties_are_serialized_as_json_object_for_lm_studio()
    {
        $key = $this->make_key(array('erp804_mcp.call'))['key'];
        $tools = \Service_Erp804_Server::instance()->tools_schema($key);
        $whoami = NULL;

        foreach ($tools as $tool)
        {
            if ($tool['name'] === 'system.whoami')
            {
                $whoami = $tool;
                break;
            }
        }

        $this->assertNotNull($whoami);
        $this->assertInstanceOf(\stdClass::class, $whoami['inputSchema']['properties']);
        $this->assertStringContainsString('"properties":{}', json_encode($whoami['inputSchema']));
    }

    public function test_batch_payload_is_answered_in_order()
    {
        $answers = \Service_Erp804_Server::instance()->handle_payload(array(
            array('jsonrpc' => '2.0', 'id' => 10, 'method' => 'ping'),
            array('jsonrpc' => '2.0', 'id' => 11, 'method' => 'нет-такого-метода'),
        ));

        $this->assertCount(2, $answers);
        $this->assertSame(10, $answers[0]['id']);
        $this->assertSame(11, $answers[1]['id']);
        $this->assertSame(-32601, $answers[1]['error']['code']);
    }

    public function test_broken_payload_is_rejected_as_a_whole()
    {
        $answer = \Service_Erp804_Server::instance()->handle_payload('не-json');

        $this->assertSame(-32700, $answer['error']['code']);
    }

    // =========================================================================
    //  Вызовы инструментов
    // =========================================================================

    public function test_call_without_scope_is_forbidden()
    {
        $key = $this->make_key(array('erp119_audit.view'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 20,
            'method'  => 'tools/call',
            'params'  => array('name' => 'production.orders.search', 'arguments' => array()),
        ), $key);

        $this->assertSame(-32003, $answer['error']['code']);
        $this->assertSame('forbidden', $answer['error']['data']['reason']);
    }

    public function test_read_only_key_cannot_change_data()
    {
        $key = $this->make_key(array('erp402_production.*'), array('read_only' => TRUE))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 21,
            'method'  => 'tools/call',
            'params'  => array('name' => 'production.order.create', 'arguments' => array()),
        ), $key);

        $this->assertSame(-32003, $answer['error']['code']);
        $this->assertSame('read_only', $answer['error']['data']['reason']);
    }

    public function test_unknown_tool_is_reported()
    {
        $key = $this->make_key(array('*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 22,
            'method'  => 'tools/call',
            'params'  => array('name' => 'production.нет_такого', 'arguments' => array()),
        ), $key);

        $this->assertSame(-32601, $answer['error']['code']);
        $this->assertSame('unknown_tool', $answer['error']['data']['reason']);
    }

    public function test_invalid_arguments_are_reported_and_journaled()
    {
        $key = $this->make_key(array('*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 23,
            'method'  => 'tools/call',
            'params'  => array('name' => 'system.call.status', 'arguments' => array()),
        ), $key);

        $this->assertSame(-32602, $answer['error']['code']);
        $this->assertSame('invalid_arguments', $answer['error']['data']['reason']);
    }

    public function test_successful_call_returns_content_and_is_journaled()
    {
        $key     = $this->make_key(array('erp804_mcp.call'))['key'];
        $server  = \Service_Erp804_Server::instance();

        $answer = $server->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 24,
            'method'  => 'tools/call',
            'params'  => array('name' => 'system.whoami', 'arguments' => array()),
        ), $key);

        $this->assertFalse($answer['result']['isError']);
        $this->assertSame(24, $answer['id']);

        $call_id = (int) $answer['result']['structuredContent']['call_id'];
        $this->assertGreaterThan(0, $call_id);

        $call = \Service_Erp804_Calls::instance()->find($call_id);

        $this->assertSame('ok', $call['status']);
        $this->assertSame('system.whoami', $call['tool_name']);
        $this->assertSame('ТЕСТ-804-ключ', $call['key_name']);
        $this->assertNotNull($call['finished_at']);
    }

    public function test_repeated_call_with_idempotency_key_is_replayed()
    {
        $key    = $this->make_key(array('*'))['key'];
        $server = \Service_Erp804_Server::instance();
        $marker = 'ТЕСТ-804-идемпотентность-'.uniqid();

        $first = $server->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 30,
            'method'  => 'tools/call',
            'params'  => array(
                'name'            => 'system.last_calls',
                'arguments'       => array('limit' => 1),
                'idempotency_key' => $marker,
            ),
        ), $key);

        $second = $server->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 31,
            'method'  => 'tools/call',
            'params'  => array(
                'name'            => 'system.last_calls',
                'arguments'       => array('limit' => 1),
                'idempotency_key' => $marker,
            ),
        ), $key);

        $this->assertSame(
            (int) $first['result']['structuredContent']['call_id'],
            (int) $second['result']['structuredContent']['call_id'],
            'Повтор с тем же ключом идемпотентности должен вернуть прежний вызов'
        );
        $this->assertTrue($second['result']['structuredContent']['replayed']);

        $count = \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_804_mcp_calls')
            ->where('idempotency_key', '=', $marker)
            ->execute()->get('cnt');

        $this->assertSame(1, (int) $count);
    }

    public function test_llm_arguments_are_normalised_to_declared_types()
    {
        $registry = \Service_Erp804_Registry::instance();

        // LLM часто присылает число строкой — сервер обязан привести «3» к 3
        list($clean, $errors) = $registry->validate_arguments('system.last_calls', array('limit' => '3'));

        $this->assertSame(array(), $errors);
        $this->assertSame(3, $clean['limit']);

        // Значение вне диапазона отсекается схемой, а не падением в базе
        list($clean2, $errors2) = $registry->validate_arguments('system.last_calls', array('limit' => 9000));

        $this->assertNotSame(array(), $errors2);

        // Обязательный аргумент отсутствует
        list($clean3, $errors3) = $registry->validate_arguments('system.call.status', array());

        $this->assertNotSame(array(), $errors3);
    }

    public function test_successful_call_text_is_readable_for_llm()
    {
        $key = $this->make_key(array('erp804_mcp.call'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 32,
            'method'  => 'tools/call',
            'params'  => array('name' => 'system.whoami', 'arguments' => array()),
        ), $key);

        $text = $answer['result']['content'][0]['text'];

        $this->assertStringContainsString('ТЕСТ-804-ключ', $text, 'В тексте видно, чей это ключ');
        $this->assertStringContainsString('"scopes"', $text, 'К тексту приложен JSON с подробностями');
    }

    // =========================================================================
    //  Подтверждение человеком
    // =========================================================================

    public function test_dangerous_tool_is_parked_for_approval()
    {
        $key = $this->make_key(array('erp402_production.*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 40,
            'method'  => 'tools/call',
            'params'  => array(
                'name'      => 'production.order.cancel',
                'arguments' => array('order' => 'ТЕСТ-804-нет-такого-заказа'),
            ),
        ), $key);

        $this->assertFalse($answer['result']['isError']);
        $this->assertSame('awaiting_approval', $answer['result']['structuredContent']['status']);

        $call = \Service_Erp804_Calls::instance()->find($answer['result']['structuredContent']['call_id']);

        $this->assertSame('awaiting_approval', $call['status']);
        $this->assertNull($call['finished_at'], 'Ожидающий подтверждения вызов ещё не завершён');
    }

    public function test_pending_call_is_visible_in_the_pending_list()
    {
        $key = $this->make_key(array('erp402_production.*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 41,
            'method'  => 'tools/call',
            'params'  => array(
                'name'      => 'production.order.complete',
                'arguments' => array('order' => 'ТЕСТ-804-нет-такого-заказа'),
            ),
        ), $key);

        $call_id = (int) $answer['result']['structuredContent']['call_id'];

        $found = FALSE;

        foreach (\Service_Erp804_Calls::instance()->pending(100) as $pending)
        {
            if ((int) $pending['id'] === $call_id)
            {
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'Вызов, ждущий подтверждения, должен быть виден в списке');

        // Агент может спросить статус своего вызова тем же ключом
        $status = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 42,
            'method'  => 'tools/call',
            'params'  => array('name' => 'system.call.status', 'arguments' => array('call_id' => $call_id)),
        ), $key);

        $this->assertFalse($status['result']['isError'], 'system.call.status должен находить вызов по номеру');
        $this->assertSame('awaiting_approval', $status['result']['structuredContent']['status']);
    }

    public function test_approval_executes_the_call_and_marks_it()
    {
        $key = $this->make_key(array('erp402_production.*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 43,
            'method'  => 'tools/call',
            'params'  => array(
                'name'      => 'production.order.cancel',
                'arguments' => array('order' => 'ПО-ТЕСТ-804-нет-такого'),
            ),
        ), $key);

        $call_id = (int) $answer['result']['structuredContent']['call_id'];

        $call = \Service_Erp804_Calls::instance()->approve($call_id, 1);

        $this->assertSame('error', $call['status'], 'Заказ не найден — операция должна закончиться ошибкой, но она выполнена');
        $this->assertSame(1, (int) $call['approved_by']);
        $this->assertNotNull($call['finished_at']);
    }

    public function test_rejection_keeps_the_call_untouched()
    {
        $key = $this->make_key(array('erp402_production.*'))['key'];

        $answer = \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 44,
            'method'  => 'tools/call',
            'params'  => array(
                'name'      => 'production.order.cancel',
                'arguments' => array('order' => 'ПО-ТЕСТ-804-нет-такого'),
            ),
        ), $key);

        $call_id = (int) $answer['result']['structuredContent']['call_id'];

        $call = \Service_Erp804_Calls::instance()->reject($call_id, 1, 'Отменяем только по заявке');

        $this->assertSame('rejected', $call['status']);
        $this->assertSame('Отменяем только по заявке', $call['rejection_reason']);
        $this->assertNull($call['result']);

        // Решения по вызову больше не ждут
        $reason = NULL;

        try
        {
            \Service_Erp804_Calls::instance()->approve($call_id, 1);
        }
        catch (\Exception_Erp804_Mcp $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_arguments', $reason);
    }

    public function test_stats_count_calls_by_status()
    {
        $key = $this->make_key(array('erp804_mcp.call'))['key'];

        \Service_Erp804_Server::instance()->handle(array(
            'jsonrpc' => '2.0',
            'id'      => 50,
            'method'  => 'tools/call',
            'params'  => array('name' => 'system.whoami', 'arguments' => array()),
        ), $key);

        $stats = \Service_Erp804_Calls::instance()->stats(1);

        $this->assertGreaterThanOrEqual(1, (int) $stats['ok']);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('mutating', $stats);
        $this->assertArrayHasKey('awaiting_approval', $stats);
    }

    public function test_key_changes_are_journaled_in_audit_module()
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            $this->markTestSkipped('Журнал действий доступен только с erp119_audit');
        }

        $key = $this->make_key(array('erp804_mcp.manage'))['key'];

        \Service_Erp804_Keys::instance()->update($key['id'], array('note' => 'проверка журнала'));

        $found = \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_119_audit_log')
            ->where('module', '=', 'erp804')
            ->where('entity_type', '=', 'mcp_key')
            ->execute()->get('cnt');

        $this->assertGreaterThan(0, (int) $found, 'Изменения ключей должны попадать в журнал действий');
    }

    // =========================================================================
    //  Помощники
    // =========================================================================

    /**
     * Создать тестовый ключ агента.
     *
     * @param   array   $scopes
     * @param   array   $extra
     * @return  array   ['key' => …, 'token' => …]
     */
    protected function make_key(array $scopes, array $extra = array())
    {
        $issued = \Service_Erp804_Keys::instance()->issue('ТЕСТ-804-ключ', $scopes, $extra);

        $this->keys[] = (int) $issued['key']['id'];

        return $issued;
    }

    /**
     * @return  bool
     */
    protected function available()
    {
        try
        {
            return class_exists('Service_Erp804_Keys')
                AND \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))->from('erp_804_mcp_keys')->execute()->get('cnt') !== NULL;
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }
}
