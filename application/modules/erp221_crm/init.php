<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Маршруты модуля CRM (erp221_crm).
 *
 * Порядок важен: Kohana проверяет маршруты сверху вниз, поэтому сначала идут
 * точные адреса — API и короткие ссылки на сделку, — и только потом общий
 * маршрут админки. Иначе `/crm/api/board` попал бы в раздел админки «api».
 *
 * Клиенты и контактные лица живут у владельца (`/counterparties`, erp301):
 * CRM не заводит второй справочник, а дополняет его профилем продаж. Отсюда и
 * два маршрута-синонима для перехода в карточку клиента — см. README модуля.
 */

// --- JSON API: рабочее место менеджера и мобильный клиент --------------------
Route::set('erp221_api', 'crm/api/<action>(/<id>)')
    ->defaults(array(
        'directory'  => 'Erp221',
        'controller' => 'Api',
        'action'     => 'index',
    ));

// --- Карточка сделки: /crm/deal/123 ----------------------------------------
Route::set('erp221_deal', 'crm/deal(/<id>)')
    ->defaults(array(
        'directory'  => 'Erp221',
        'controller' => 'Index',
        'action'     => 'deal',
    ));

// --- Админка: только перечисленные разделы, остальное — доска --------------
Route::set('erp221_index', 'crm(/<action>(/<id>))', array(
        'action' => '(deals|deal|deal_save|deal_stage|deal_delete|deal_contact|'
            .'clients|client|client_save|contact_save|contact_delete|'
            .'stages|stage_save|stage_delete|loss_reason_save|loss_reason_delete|'
            .'analytics|loss_analysis|kb|kb_save|kb_delete|'
            .'journal|activity_save|activity_delete|activity_file|'
            .'reminders|reminders_run|ai_run|export|import|suggest)',
    ))
    ->defaults(array(
        'directory'  => 'Erp221',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// --- Старые адреса исходного модуля ----------------------------------------
// Исходная версия держала восемь контроллеров, поэтому её адреса выглядят как
// `/erp221/deal/view/12`. Они не выбрасываются: контроллер Legacy переводит
// их на актуальную страницу ответом 301, чтобы закладки менеджеров и ссылки в
// письмах продолжали работать. Регистрируются последними: точные маршруты
// выше должны побеждать.
Route::set('erp221_legacy_api', 'erp221/api(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp221',
        'controller' => 'Api',
        'action'     => 'index',
    ));

// Внимание: сегмент старого действия назван `step`, а не `action`:
// имя `action` служебное — по нему Kohana выбирает метод контроллера.
Route::set('erp221_legacy', 'erp221(/<section>(/<step>(/<id>)))')
    ->defaults(array(
        'directory'  => 'Erp221',
        'controller' => 'Legacy',
        'action'     => 'index',
    ));
