<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля контрагентов (erp301_counterparty).
 *
 * ID: 301 (диапазон 300-399, Master Data)
 * Префикс таблиц: erp_301_*
 * Слой: Master Data — владеет «коммерческой стороной» (кто поставляет и кто покупает),
 *       но не владеет номенклатурой (реестр erp227) и не хранит файлы (erp212).
 *
 * Публикуемые события:
 *   erp301.counterparty.created / updated / deleted
 *   erp301.contract.created / updated / terminated
 */

// -----------------------------------------------------------------------------
// 1. Роуты
//    Порядок важен: частные роуты (api, разделы) идут ДО общего
//    'counterparties(/<action>(/<id>))', иначе /counterparties/list попадёт в Index.
// -----------------------------------------------------------------------------
Route::set('erp301_counterparty_api', 'counterparties/api/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Api',
    ));

Route::set('erp301_counterparty_contracts', 'counterparties/contracts(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Contract',
        'action'     => 'index',
    ));

Route::set('erp301_counterparty_list', 'counterparties/list', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'index',
    ));

Route::set('erp301_counterparty_card', 'counterparties/view(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'view',
    ));

Route::set('erp301_counterparty_edit', 'counterparties/edit(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'edit',
    ));

Route::set('erp301_counterparty_save', 'counterparties/save(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'save',
    ));

Route::set('erp301_counterparty_delete', 'counterparties/delete', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'delete',
    ));

// ВНИМАНИЕ: параметр роута с именем <action> в середине пути становится именем
// метода контроллера (action_<значение>), поэтому 'contact_<action>' вызывал бы
// action_save вместо action_contact_save. Для составных действий маршруты
// объявляются литеральными со явным default'ом action.
Route::set('erp301_counterparty_contact_save', 'counterparties/contact_save', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'contact_save',
    ));

Route::set('erp301_counterparty_contact_delete', 'counterparties/contact_delete', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'contact_delete',
    ));

Route::set('erp301_counterparty_requisite_save', 'counterparties/requisite_save', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Counterparty',
        'action'     => 'requisite_save',
    ));

Route::set('erp301_counterparty_contract_save', 'counterparties/contract_save', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Contract',
        'action'     => 'save',
    ));

Route::set('erp301_counterparty_contract_terminate', 'counterparties/contract_terminate', array())
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Contract',
        'action'     => 'terminate',
    ));

Route::set('erp301_counterparty', 'counterparties(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp301',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// -----------------------------------------------------------------------------
// 2. Локальные реакции на события модуля.
//    Полноценная шина — erp208_events (таблица erp_208_event_subscribers),
//    здесь только то, что нужно самому модулю: журналирование идемпотентности.
// -----------------------------------------------------------------------------
Event::listen('erp301.counterparty.deleted', function ($payload) {
    Kohana::$log->add(Log::INFO, 'erp301: контрагент :code помещён в архив (событие erp301.counterparty.deleted)', array(
        ':code' => (string) Arr::get((array) $payload, 'code', '?'),
    ));
});
