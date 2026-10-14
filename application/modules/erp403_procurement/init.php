<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля закупок (erp403_procurement).
 *
 * ID: 403 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_403_*
 * Слой: Operations — закупки закрывают разрыв между потребностью и складом:
 *       заявка (руками, по минимуму склада или из предложения планирования
 *       erp407) → согласование → заказ поставщику → приёмка → приход на склад
 *       erp401. Поставщики берутся из справочника контрагентов erp301, нормы и
 *       цены — из истории цен самого модуля.
 *
 * Порядок маршрутов: частные (api, widget, действия и карточки) объявлены
 * раньше общих — иначе составной адрес разберёт общее правило и вызовет не то
 * действие.
 */

// JSON API
Route::set('erp403_procurement_api', 'procurement/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp403',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты (для дашбордов и карточек)
Route::set('erp403_procurement_widget', 'procurement/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp403',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// --- Заявки ----------------------------------------------------------------
Route::set('erp403_procurement_request_act', 'procurement/requests/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'act'));

Route::set('erp403_procurement_request_line', 'procurement/requests/line/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'line'));

Route::set('erp403_procurement_request_save', 'procurement/requests/save')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'save'));

Route::set('erp403_procurement_request_view', 'procurement/requests/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'view'));

Route::set('erp403_procurement_request_edit', 'procurement/requests/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'edit'));

Route::set('erp403_procurement_requests', 'procurement/requests(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Request', 'action' => 'index'));

// --- Заказы поставщику -----------------------------------------------------
Route::set('erp403_procurement_order_act', 'procurement/orders/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'act'));

Route::set('erp403_procurement_order_line', 'procurement/orders/line/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'line'));

Route::set('erp403_procurement_order_save', 'procurement/orders/save')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'save'));

Route::set('erp403_procurement_order_view', 'procurement/orders/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'view'));

Route::set('erp403_procurement_order_edit', 'procurement/orders/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'edit'));

Route::set('erp403_procurement_orders', 'procurement/orders(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Order', 'action' => 'index'));

// --- Приёмки ---------------------------------------------------------------
Route::set('erp403_procurement_receipt_act', 'procurement/receipts/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Receipt', 'action' => 'act'));

Route::set('erp403_procurement_receipt_line', 'procurement/receipts/line/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Receipt', 'action' => 'line'));

Route::set('erp403_procurement_receipt_view', 'procurement/receipts/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Receipt', 'action' => 'view'));

Route::set('erp403_procurement_receipts', 'procurement/receipts(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Receipt', 'action' => 'index'));

// --- Поставщики ------------------------------------------------------------
Route::set('erp403_procurement_supplier_save', 'procurement/suppliers/save')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Supplier', 'action' => 'save'));

Route::set('erp403_procurement_supplier_reference', 'procurement/suppliers/reference')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Supplier', 'action' => 'reference'));

Route::set('erp403_procurement_supplier_compare', 'procurement/suppliers/compare')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Supplier', 'action' => 'compare'));

Route::set('erp403_procurement_supplier_view', 'procurement/suppliers/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Supplier', 'action' => 'view'));

Route::set('erp403_procurement_suppliers', 'procurement/suppliers(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Supplier', 'action' => 'index'));

// --- Дефицит склада и авто-заявки ------------------------------------------
Route::set('erp403_procurement_auto_requests', 'procurement/auto-requests')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Index', 'action' => 'auto_requests'));

Route::set('erp403_procurement_low_stock', 'procurement/low-stock(/<action>)')
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Index', 'action' => 'low_stock'));

Route::set('erp403_procurement_index', 'procurement(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp403', 'controller' => 'Index', 'action' => 'index'));

/**
 * События модуля (шина erp208):
 *
 *   erp403.request.created        заявка создана
 *   erp403.request.updated        заявка изменена
 *   erp403.request.submitted      заявка ждёт согласования
 *   erp403.request.approved       заявка согласована — можно заказывать
 *   erp403.request.rejected       заявка отклонена
 *   erp403.request.cancelled      заявка отменена
 *   erp403.request.ordered        заявка попала в заказ поставщику
 *   erp403.request.auto_created   заявки заведены по минимуму склада
 *   erp403.order.created          заказ создан
 *   erp403.order.updated          заказ изменён
 *   erp403.order.sent             заказ отправлен поставщику (цены ушли в историю)
 *   erp403.order.confirmed        заказ подтверждён поставщиком
 *   erp403.order.partially_received  принят частично
 *   erp403.order.received         принят полностью
 *   erp403.order.cancelled        заказ отменён
 *   erp403.orders.collected       заказы собраны из заявок одной кнопкой
 *   erp403.receipt.created        приёмка создана
 *   erp403.receipt.quality        результат входного контроля записан
 *   erp403.receipt.posted         приёмка проведена: приход на складе
 *   erp403.receipt.cancelled      приёмка отменена
 *   erp403.price.recorded         цена попала в историю
 *   erp403.supplier.updated       условия поставщика изменены
 *
 * Потребители: erp405_quality (входной контроль по `erp403.receipt.created`),
 * erp404_documents (печать заказа и накладной), erp217_notification
 * (оповещение закупщика о подтверждении и просрочке поставки), erp119_audit
 * (журнал движений). Модуль никого не вызывает напрямую — только публикует
 * события и мягко проверяет соседей через `class_exists()`.
 */
