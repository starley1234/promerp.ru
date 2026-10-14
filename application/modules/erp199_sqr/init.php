<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Маршруты модуля QR-маркировки (erp199_sqr).
 *
 * Порядок важен: Kohana проверяет маршруты сверху вниз, поэтому сначала идут
 * точные адреса разделов и API, и только в конце — короткий `/qr/<code>` для
 * ссылки из самого QR-кода. Иначе `/qr/tokens` попал бы в «показать наклейку
 * tokens» и админка стала бы недоступна.
 */

// --- Мобильная справка для того, у кого нет приложения ----------------------
Route::set('erp199_help', 'mobile.help')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Qr',
        'action'     => 'help',
    ));

// --- JSON API тонкого клиента ----------------------------------------------
Route::set('erp199_api', 'qr/api/<action>(/<id>)')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Api',
        'action'     => 'index',
    ));

// --- Терминал в браузере (WebView приложения открывает тот же адрес) --------
Route::set('erp199_terminal', 'qr/scan(/<code>)')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Qr',
        'action'     => 'index',
    ));

// --- Админка ---------------------------------------------------------------
Route::set('erp199_index', 'qr(/<action>(/<id>))', array('action' => '(tokens|token|objects|object|inventories|inventory|inventory_open|inventory_close|scenarios|scenario|types|type|activity|label|status|moderate|issue|bind)'))
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// --- Короткая ссылка из QR-кода: /qr/QR-XXXX-XXXX --------------------------
// Последний маршрут: всё, что не разобрали выше, считаем кодом наклейки.
Route::set('erp199_code', 'qr/<code>')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Qr',
        'action'     => 'code',
    ));

// Старые адреса исходного модуля оставлены как синонимы, чтобы уже
// напечатанные ссылки и закладки продолжали работать.
Route::set('erp199_legacy_api', 'erp199/api(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Api',
        'action'     => 'index',
    ));

Route::set('erp199_legacy', 'erp199(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp199',
        'controller' => 'Index',
        'action'     => 'index',
    ));