<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля MCP-сервера (erp804_mcp).
 *
 * ID: 804 (диапазон 800–899, Intelligence)
 * Префикс таблиц: erp_804_*
 *
 * Модуль не владеет бизнес-данными: он даёт агентам (LLM, коннекторам,
 * внешним воркерам) доступ к сервисам других модулей через протокол MCP.
 * Инструменты объявляют сами модули файлом `mcp.php` — MCP-сервер лишь
 * проверяет ключ, права, аргументы и записывает вызов.
 *
 * Порядок маршрутов: сначала точный `/mcp` (транспорт JSON-RPC), затем API
 * управления, и только потом разделы интерфейса.
 */

// Транспорт MCP: POST — JSON-RPC, GET — сведения о сервере
Route::set('erp804_mcp_endpoint', 'mcp')
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'Mcp',
        'action'     => 'index',
    ));

// Оператор demo: отдельный ключ управляет записью, но данные всегда в demo.
Route::set('erp804_mcp_demo_agent', 'mcp/demo/agent')
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'DemoAgent',
        'action'     => 'index',
    ));

// QA demo: аутентификация в main, выполнение строго в demo и всегда read-only.
Route::set('erp804_mcp_demo_discovery', 'mcp/demo/discovery')
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'DemoDiscovery',
        'action'     => 'index',
    ));

// Компактный транспорт: tools/list содержит только поиск и безопасный прокси.
Route::set('erp804_mcp_discovery', 'mcp/discovery')
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'Discovery',
        'action'     => 'index',
    ));

// Управление MCP для людей и внешних консолей
Route::set('erp804_mcp_api', 'mcp/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'Api',
        'action'     => 'info',
    ));

// Витрина для человека: /mcp занят протоколом (GET отдаёт сведения о сервере),
// поэтому обзор живёт на /mcp/console, а разделы — рядом с ним.
Route::set('erp804_mcp_console', 'mcp/console')
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// Интерфейс: обзор, инструменты, вызовы, ключи
Route::set('erp804_mcp_section', 'mcp/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp804',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// -----------------------------------------------------------------------------
// Локальные реакции модуля: каждое подтверждение агентской операции — в лог
// приложения (основная запись — в журнале действий erp119).
// -----------------------------------------------------------------------------

Event::listen('erp804.mcp.approved', function ($payload) {
    Kohana::$log->add(Log::WARNING, 'erp804: агентская операция подтверждена человеком — вызов #:id, инструмент :tool', array(
        ':id'   => (string) Arr::get((array) $payload, 'call_id', '—'),
        ':tool' => (string) Arr::get((array) $payload, 'tool', '—'),
    ));
});
