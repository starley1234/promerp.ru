<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация базового модуля (erp100_base).
 *
 * ID: 100 (диапазон 100–199, Ядро)
 * Таблицы: users, roles, user_roles, user_profiles, user_settings (ядро).
 * Слой: ядро — вход, роли и права. Никаких бизнес-сущностей: модуль отвечает
 *       на вопросы «кто это» и «что ему можно».
 *
 * Страницы входа (`/auth/*`) обслуживает модуль auth фреймворка; здесь только
 * адрес JSON API, чтобы у ядра тоже был машинный вход: проверка ключа агента,
 * список пользователей и ролей.
 */

Route::set('erp100_access', 'access/users(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory'=>'Erp100','controller'=>'Access','action'=>'index'));

Route::set('erp100_api', 'erp100/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp100',
        'controller' => 'Api',
        'action'     => 'me',
    ));

// Переключатель режима интерфейса (простой/профессиональный): обычная ссылка,
// см. Controller_Erp100_Ui и Ui_Mode.
Route::set('erp100_ui_mode', 'ui/mode/<mode>', array('mode' => 'simple|pro'))
    ->defaults(array('directory' => 'Erp100', 'controller' => 'Ui', 'action' => 'mode'));
