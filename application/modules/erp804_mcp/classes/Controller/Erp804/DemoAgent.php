<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Компактный endpoint для симуляции работы предприятия в demo-схеме.
 * В отличие от DemoDiscovery не форсирует read-only: права, approval и
 * read_only берутся из отдельного ключа оператора.
 */
class Controller_Erp804_DemoAgent extends Controller_Erp804_DemoDiscovery
{
    protected function _endpoint_path()
    {
        return '/mcp/demo/agent';
    }

    protected function _effective_key(array $key)
    {
        $key['configured_read_only'] = ! empty($key['read_only']);
        $key['endpoint_read_only'] = FALSE;
        $key['endpoint_path'] = '/mcp/demo/agent';
        $key['id'] = NULL;
        return $key;
    }
}
