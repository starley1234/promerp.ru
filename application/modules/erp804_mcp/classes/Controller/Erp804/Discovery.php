<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Компактный MCP endpoint: рекламирует только поиск и безопасный прокси-вызов
 * вместо полного каталога. Авторизация и JSON-RPC транспорт совпадают с
 * `/mcp`, поэтому существующие ключи и клиенты работают без новых настроек.
 */
class Controller_Erp804_Discovery extends Controller_Erp804_Mcp
{
    protected function _server_mode()
    {
        return 'discovery';
    }

    protected function _endpoint_path()
    {
        return '/mcp/discovery';
    }
}
