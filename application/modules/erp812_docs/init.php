<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация маршрутов модуля интерактивной документации erp812_docs.
 */

// Главный экран документации
Route::set('erp812_docs_home', 'docs')
    ->defaults(array(
        'controller' => 'Erp812_Index',
        'action'     => 'index',
    ));

// Сквозные бизнес-процессы
Route::set('erp812_docs_processes', 'docs/processes(/<id>)', array('id' => '[a-zA-Z0-9_\-]+'))
    ->defaults(array(
        'controller' => 'Erp812_Processes',
        'action'     => 'index',
    ));

// Каталог модулей
Route::set('erp812_docs_modules', 'docs/modules')
    ->defaults(array(
        'controller' => 'Erp812_Modules',
        'action'     => 'index',
    ));

// Детальный просмотр модуля
Route::set('erp812_docs_module_view', 'docs/modules/<id>', array('id' => '[a-zA-Z0-9_]+'))
    ->defaults(array(
        'controller' => 'Erp812_Modules',
        'action'     => 'view',
    ));

// Системная архитектура
Route::set('erp812_docs_architecture', 'docs/architecture')
    ->defaults(array(
        'controller' => 'Erp812_Architecture',
        'action'     => 'index',
    ));

// Каталог MCP инструментов
Route::set('erp812_docs_mcp', 'docs/mcp')
    ->defaults(array(
        'controller' => 'Erp812_Mcp',
        'action'     => 'index',
    ));

// JSON API документации
Route::set('erp812_docs_api', 'erp812_api/<action>')
    ->defaults(array(
        'controller' => 'Erp812_Api',
        'action'     => 'search',
    ));
