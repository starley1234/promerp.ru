<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Read-only discovery endpoint, выполняющий доменные инструменты строго внутри
 * demo-хранилища. Ключ аутентифицируется в основной БД до переключения.
 */
class Controller_Erp804_DemoDiscovery extends Controller_Erp804_Discovery
{
    protected function _endpoint_path()
    {
        return '/mcp/demo/discovery';
    }

    protected function _effective_key(array $key)
    {
        $key['configured_read_only'] = ! empty($key['read_only']);
        $key['read_only'] = TRUE;
        $key['endpoint_read_only'] = TRUE;
        $key['endpoint_path'] = '/mcp/demo/discovery';
        // В demo-схеме нет строки ключа из основной БД; журнал сохраняет имя,
        // но не создаёт неверную FK-ссылку.
        $key['id'] = NULL;
        return $key;
    }

    protected function _prepare_tool_runtime()
    {
        if ( ! class_exists('Service_Erp811_Demo'))
        {
            throw new Exception_Erp804_Mcp('Модуль demo не подключён', NULL, 'demo_unavailable');
        }

        $demo = Service_Erp811_Demo::instance();
        if ( ! $demo->available(TRUE) OR ! $demo->use_demo_database())
        {
            throw new Exception_Erp804_Mcp('Demo-схема не собрана или недоступна', NULL, 'demo_unavailable');
        }
    }

    protected function _restore_tool_runtime()
    {
        Service_Erp811_Demo::instance()->use_main_database();
    }
}
