<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля ссылок (erp118_links).
 *
 * ID: 118 (диапазон 100–199, Ядро)
 * Таблица: erp_118_links — личные закладки и пункты меню пользователя.
 * Слой: ядро — навигация. Данные строго личные: ссылки видны только владельцу.
 *
 * Страницы (`/erp118_links`, `/erp118_links/manage`) разбирает общий маршрут
 * по имени контроллера; здесь объявлен только адрес JSON API, чтобы мобильное
 * приложение и агент могли читать закладки и меню, не разбирая HTML.
 */

Route::set('erp118_links_api', 'erp118_links/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp118',
        'controller' => 'Api',
        'action'     => 'links',
    ));
