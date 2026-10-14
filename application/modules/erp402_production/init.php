<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля производства (erp402_production).
 *
 * ID: 402 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_402_*
 * Слой: Operations — планирует и запускает выпуск, но не владеет ни
 *       номенклатурой (реестр erp227), ни складом (erp401), ни техкартами
 *       (erp228): всё это берётся через сервисы соседних модулей.
 *
 * Порядок маршрутов: частные (api, widget, служебные действия над заказом)
 * объявлены раньше общих, иначе составной адрес разберёт общее правило
 * и вызовет не то действие.
 */

// JSON API
Route::set('erp402_production_api', 'production/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Api',
        'action'     => 'search',
    ));

// Виджеты
Route::set('erp402_production_widget', 'production/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// Состав: заполнение из техкарты и строки состава
Route::set('erp402_production_order_fill', 'production/orders/fill(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'fill',
    ));

Route::set('erp402_production_order_item_save', 'production/orders/item/save')
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'item_save',
    ));

Route::set('erp402_production_order_item_delete', 'production/orders/item/delete/<order_id>/<id>', array('order_id' => '\d+', 'id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'item_delete',
    ));

// Заказ: сохранение, карточка, форма
Route::set('erp402_production_order_save', 'production/orders/save')
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'save',
    ));

Route::set('erp402_production_order_view', 'production/orders/view/<id>', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'view',
    ));

Route::set('erp402_production_order_edit', 'production/orders/edit(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'edit',
    ));

// Список и действия над заказом (plan, release, start, complete, cancel, delete)
Route::set('erp402_production_orders', 'production/orders(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Order',
        'action'     => 'index',
    ));

// Сводка
Route::set('erp402_production', 'production(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp402',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// -----------------------------------------------------------------------------
// Локальные реакции модуля. Шина erp208 публикует события всем подписчикам;
// здесь — то, что нужно самому производству: отметка в журнале приложения.
// -----------------------------------------------------------------------------

/**
 * Выпуск продукции: пишем в файловый лог как зеркало (основная запись —
 * в журнале действий erp119 и в шине событий).
 */
Event::listen('erp402.order.completed', function ($payload) {
    Kohana::$log->add(Log::INFO, 'erp402: выпуск по заказу :number — :qty (статус :status)', array(
        ':number' => (string) Arr::get((array) $payload, 'number', '—'),
        ':qty'    => (string) Arr::get((array) $payload, 'qty', '0'),
        ':status' => (string) Arr::get((array) $payload, 'status', '—'),
    ));
});

// -----------------------------------------------------------------------------
// Обратная связь из цеха (erp408_shopfloor). Цех сообщает, что операция
// выполнена; производство отражает это в журнале заказа. Статус заказа при
// этом не меняется: выпуск продукции оформляет склад по документу
// (Orders::complete()), поэтому у данных остаётся один владелец.
// -----------------------------------------------------------------------------
Event::listen('erp408.operation.completed', function ($payload) {
    $payload = (array) $payload;

    $number = (string) Arr::get($payload, 'order_number', '—');
    $op_no  = (string) Arr::get($payload, 'op_no', '—');
    $name   = (string) Arr::get($payload, 'name', '');
    $done   = (int) Arr::get($payload, 'operations_done', 0);
    $total  = (int) Arr::get($payload, 'operations_total', 0);

    Kohana::$log->add(Log::INFO, 'erp402: цех закрыл операцию :op_no (:name) заказа :number — операций :done из :total', array(
        ':op_no'  => $op_no,
        ':name'   => $name,
        ':number' => $number,
        ':done'   => $done,
        ':total'  => $total,
    ));

    $uuid = (string) Arr::get($payload, 'order_uuid', '');

    if ($uuid === '' OR ! class_exists('Service_Erp119_Journal'))
    {
        return;
    }

    try
    {
        Service_Erp119_Journal::instance()->change('order', $uuid, 'update', array(), array(
            'operations_done'  => $done,
            'operations_total' => $total,
            'operations_open'  => (int) Arr::get($payload, 'operations_open', 0),
            'qty_ok_total'     => (float) Arr::get($payload, 'qty_ok_total', 0),
            'qty_scrap_total'  => (float) Arr::get($payload, 'qty_scrap_total', 0),
        ), array(
            'module'       => 'erp402',
            'entity_label' => 'Заказ '.$number,
            'message'      => 'Цех: операция '.$op_no.' «'.$name.'» завершена (годных '
                .(float) Arr::get($payload, 'qty_ok', 0).', брак '.(float) Arr::get($payload, 'qty_scrap', 0)
                .'), операций закрыто '.$done.' из '.$total,
            'severity'     => 'info',
            'force'        => TRUE,
        ));
    }
    catch (Exception $e)
    {
        Kohana::$log->add(Log::WARNING, 'erp402: факт из цеха не записан в журнал — :error', array(':error' => $e->getMessage()));
    }
});
