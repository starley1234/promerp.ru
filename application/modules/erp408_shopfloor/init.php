<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля цеха (erp408_shopfloor).
 *
 * ID: 408 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_408_*
 * Слой: Operations — цех выполняет операции производственного заказа и
 *       фиксирует факт (время, выработка, брак, простои). Номенклатурой
 *       владеет реестр erp227, заказами — erp402, складом — erp401,
 *       техкартами — erp228; всё это берётся через сервисы соседних модулей.
 *
 * Порядок маршрутов: частные (api, widget, действия над операцией, поиск в
 * терминале) объявлены раньше общих — иначе составной адрес разберёт общее
 * правило и вызовет не то действие.
 */

// JSON API
Route::set('erp408_shopfloor_api', 'shopfloor/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp408',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты
Route::set('erp408_shopfloor_widget', 'shopfloor/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp408',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// --- Терминал ---------------------------------------------------------------
Route::set('erp408_shopfloor_terminal_find', 'shopfloor/terminal/find')
    ->defaults(array(
        'directory'  => 'Erp408',
        'controller' => 'Terminal',
        'action'     => 'find',
    ));

Route::set('erp408_shopfloor_terminal', 'shopfloor/terminal(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp408',
        'controller' => 'Terminal',
        'action'     => 'index',
    ));

// --- Рабочие центры и смены -------------------------------------------------
Route::set('erp408_shopfloor_centers_save', 'shopfloor/centers/save')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Centers', 'action' => 'save'));

Route::set('erp408_shopfloor_centers_toggle', 'shopfloor/centers/toggle/<id>', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Centers', 'action' => 'toggle'));

Route::set('erp408_shopfloor_centers_delete', 'shopfloor/centers/delete/<id>', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Centers', 'action' => 'delete'));

Route::set('erp408_shopfloor_centers_edit', 'shopfloor/centers/edit(/<id>)', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Centers', 'action' => 'edit'));

Route::set('erp408_shopfloor_centers', 'shopfloor/centers(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Centers', 'action' => 'index'));

Route::set('erp408_shopfloor_shifts_save', 'shopfloor/shifts/save')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'save'));

Route::set('erp408_shopfloor_shifts_toggle', 'shopfloor/shifts/toggle/<id>', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'toggle'));

Route::set('erp408_shopfloor_shifts_delete', 'shopfloor/shifts/delete/<id>', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'delete'));

Route::set('erp408_shopfloor_shifts_day', 'shopfloor/shifts/day')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'day'));

Route::set('erp408_shopfloor_shifts_generate', 'shopfloor/shifts/generate')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'generate'));

Route::set('erp408_shopfloor_shifts', 'shopfloor/shifts(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Shifts', 'action' => 'index'));

// --- Операции ---------------------------------------------------------------
Route::set('erp408_shopfloor_operations_act', 'shopfloor/operations/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'act'));

Route::set('erp408_shopfloor_operations_plan', 'shopfloor/operations/plan')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'plan'));

Route::set('erp408_shopfloor_operations_replan', 'shopfloor/operations/replan')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'replan'));

Route::set('erp408_shopfloor_operations_save', 'shopfloor/operations/save')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'save'));

Route::set('erp408_shopfloor_operations_view', 'shopfloor/operations/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'view'));

Route::set('erp408_shopfloor_operations_edit', 'shopfloor/operations/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'edit'));

Route::set('erp408_shopfloor_operations', 'shopfloor/operations(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Operations', 'action' => 'index'));

// --- Загрузка цеха и раскладка маршрута из очереди --------------------------
Route::set('erp408_shopfloor_plan', 'shopfloor/plan')
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Index', 'action' => 'plan'));

Route::set('erp408_shopfloor_board', 'shopfloor(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp408', 'controller' => 'Index', 'action' => 'index'));

/**
 * События модуля (шина erp208):
 *
 *   erp408.route.planned        маршрут заказа разложен по центрам и дням
 *   erp408.operation.started    операция начата у станка
 *   erp408.operation.output     отметка выработки (годные, брак)
 *   erp408.operation.completed  операция завершена — основной сигнал для erp402
 *   erp408.operation.cancelled  операция отменена
 *   erp408.downtime.started     простой открыт (поломка, нет материала…)
 *   erp408.downtime.finished    простой закрыт
 *
 * Потребители: erp402_production (журнал заказа), erp409_cost (себестоимость),
 * erp217_notification (оповещения). Модуль никого не вызывает напрямую.
 */
