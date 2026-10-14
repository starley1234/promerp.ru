<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля продаж (erp410_shipment).
 *
 * ID: 410 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_410_*
 * Слой: Operations — продажа закрывает конец производственного контура:
 *       заказ клиента → подтверждение → резерв на складе → сборка отгрузки →
 *       списание со склада (erp401) → счёт → оплата. Покупатели берутся из
 *       справочника контрагентов erp301, товар — из реестра erp227, склад и
 *       остатки — erp401.
 *
 * Порядок маршрутов: частные (api, widget, действия и карточки) объявлены
 * раньше общих — иначе составной адрес разберёт общее правило и вызовет не то
 * действие.
 */

// JSON API
Route::set('erp410_shipment_api', 'sales/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp410',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты (для дашбордов и карточек клиента)
Route::set('erp410_shipment_widget', 'sales/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp410',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// --- Заказы клиента --------------------------------------------------------
Route::set('erp410_shipment_order_act', 'sales/orders/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'act'));

Route::set('erp410_shipment_order_line', 'sales/orders/line/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'line'));

Route::set('erp410_shipment_order_save', 'sales/orders/save')
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'save'));

Route::set('erp410_shipment_order_view', 'sales/orders/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'view'));

Route::set('erp410_shipment_order_edit', 'sales/orders/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'edit'));

Route::set('erp410_shipment_orders', 'sales/orders(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Order', 'action' => 'index'));

// --- Отгрузки --------------------------------------------------------------
Route::set('erp410_shipment_shipment_act', 'sales/shipments/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Shipment', 'action' => 'act'));

Route::set('erp410_shipment_shipment_build', 'sales/shipments/build(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Shipment', 'action' => 'build'));

Route::set('erp410_shipment_shipment_view', 'sales/shipments/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Shipment', 'action' => 'view'));

Route::set('erp410_shipment_shipments', 'sales/shipments(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Shipment', 'action' => 'index'));

// --- Счета -----------------------------------------------------------------
Route::set('erp410_shipment_invoice_act', 'sales/invoices/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Invoice', 'action' => 'act'));

Route::set('erp410_shipment_invoice_view', 'sales/invoices/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Invoice', 'action' => 'view'));

Route::set('erp410_shipment_invoices', 'sales/invoices(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Invoice', 'action' => 'index'));

// --- Клиенты и прайс -------------------------------------------------------
Route::set('erp410_shipment_customer_save', 'sales/customers/save')
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Customer', 'action' => 'save'));

Route::set('erp410_shipment_customer_view', 'sales/customers/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Customer', 'action' => 'view'));

Route::set('erp410_shipment_customers', 'sales/customers(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Customer', 'action' => 'index'));

Route::set('erp410_shipment_price_save', 'sales/prices/save')
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Price', 'action' => 'save'));

Route::set('erp410_shipment_prices', 'sales/prices(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Price', 'action' => 'index'));

// --- Доска продаж ----------------------------------------------------------
Route::set('erp410_shipment_index', 'sales(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp410', 'controller' => 'Index', 'action' => 'index'));

/**
 * События модуля (шина erp208):
 *
 *   erp410.order.created            заказ клиента создан
 *   erp410.order.updated            заказ изменён
 *   erp410.order.confirmed          заказ подтверждён (проверен кредитный лимит)
 *   erp410.order.reserved           товар зарезервирован на складе
 *   erp410.order.released           резерв снят, заказ снова подтверждён
 *   erp410.order.line_cancelled     позиция отменена
 *   erp410.order.partially_shipped  заказ отгружен частично
 *   erp410.order.shipped            заказ отгружен полностью
 *   erp410.order.closed             заказ закрыт
 *   erp410.order.cancelled          заказ отменён
 *   erp410.shipment.created         отгрузка создана (собрана из заказа)
 *   erp410.shipment.ready           отгрузка собрана и готова к отправке
 *   erp410.shipment.posted          отгрузка проведена: расход со склада
 *   erp410.shipment.cancelled       отгрузка отменена
 *   erp410.invoice.created          счёт создан
 *   erp410.invoice.issued           счёт выставлен клиенту
 *   erp410.invoice.partially_paid   получена частичная оплата
 *   erp410.invoice.paid             счёт оплачен
 *   erp410.invoice.cancelled        счёт отменён
 *   erp410.price.recorded           цена попала в историю
 *   erp410.customer.updated         условия клиента изменены
 *
 * Потребители: erp801_analytics (выручка и отгрузки), erp404_documents (печать
 * накладной и счёта), erp217_notification_center (оповещение менеджера о
 * подтверждении и просрочке оплаты), erp119_audit (журнал движений). Модуль
 * никого не вызывает напрямую — только публикует события и мягко проверяет
 * соседей через `class_exists()`.
 */
