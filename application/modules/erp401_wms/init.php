<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля складского учёта (erp401_wms).
 *
 * ID: 401 (диапазон 400-599, Operations)
 * Префикс таблиц: erp_401_*
 * Слой: Operations — выполняет движения, но не владеет номенклатурой
 *       (номенклатура живёт в реестре erp227).
 */

// -----------------------------------------------------------------------------
// 1. Роуты
//    Порядок важен: частные роуты (api, widget, разделы) идут ДО общего
//    'warehouse(/<action>)', иначе /warehouse/balances попадёт в Index.
// -----------------------------------------------------------------------------
Route::set('erp401_wms_api', 'warehouse/api/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Api',
    ));

Route::set('erp401_wms_widget', 'warehouse/widget/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Widget',
    ));

Route::set('erp401_wms_balances', 'warehouse/balances(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Balance',
        'action'     => 'index',
    ));

Route::set('erp401_wms_movements', 'warehouse/movements(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Movement',
        'action'     => 'index',
    ));

Route::set('erp401_wms_warehouses', 'warehouse/warehouses(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Warehouse',
        'action'     => 'index',
    ));

Route::set('erp401_wms_reservations', 'warehouse/reservations(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Reservation',
        'action'     => 'index',
    ));

Route::set('erp401_wms', 'warehouse(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp401',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// -----------------------------------------------------------------------------
// 2. Подписки на события модуля (лёгкая шина Event)
//    Публичная шина erp208 подхватывает слушателей из таблицы
//    erp_208_event_subscribers (см. миграцию 002), здесь — локальные реакции,
//    которые нужны самому модулю.
// -----------------------------------------------------------------------------

/**
 * Падение остатка ниже минимума: дублируем в локальную шину,
 * чтобы другие модули могли реагировать без обращения к erp208.
 */
Event::listen('erp401.stock_low', function ($payload) {
    // Оставляем точку расширения: сюда можно добавить авто-заказ,
    // остановку запуска производства и т.п.
    Kohana::$log->add(Log::INFO, 'erp401: остаток :item_id на складе :warehouse_id ниже минимума (:qty <= :min)',
        array(
            ':item_id'      => (int) Arr::get((array) $payload, 'item_id', 0),
            ':warehouse_id' => (int) Arr::get((array) $payload, 'warehouse_id', 0),
            ':qty'          => (float) Arr::get((array) $payload, 'qty', 0),
            ':min'          => (float) Arr::get((array) $payload, 'min_qty', 0),
        ));
});

/**
 * Приход на склад: логируем операцию для аудита.
 * Точка расширения для будущих реакций (пересчёт себестоимости, плановые поставки).
 */
Event::listen('erp401.stock_received', function ($payload) {
    Kohana::$log->add(Log::INFO, 'erp401: приход :qty :uom позиции :item_id на склад :warehouse_id',
        array(
            ':item_id'      => (int) Arr::get((array) $payload, 'item_id', 0),
            ':warehouse_id' => (int) Arr::get((array) $payload, 'warehouse_id', 0),
            ':qty'          => (float) Arr::get((array) $payload, 'qty', 0),
            ':uom'          => (string) Arr::get((array) $payload, 'uom_code', 'pcs'),
        ));
});
