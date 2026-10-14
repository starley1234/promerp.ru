<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля «Технологические карты» (erp228_tech_cards).
 *
 * Маршруты — под префиксом /tech_cards. Порядок регистрации важен: частные
 * правила объявлены раньше общего 'tech_cards(/<action>(/<id>))', иначе
 * составные адреса попали бы в общий разбор и вызывали бы не то действие
 * (параметр <action> в середине пути превращается в метод action_<значение>).
 *
 * Публикуемые события (подписчики — в других модулях или в init.php своего):
 *   erp228.card.created   — карта создана
 *   erp228.card.updated   — карта изменена
 *   erp228.card.deleted   — карта отправлена в архив
 *   erp228.card.status    — сменился статус генерации
 *   erp228.card.document  — сохранена новая версия техпроцесса
 */

// API: /tech_cards/api/<action>(/<id>)
Route::set('erp228_tech_cards_api', 'tech_cards/api/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты: /tech_cards/widget/<action>(/<id>)
Route::set('erp228_tech_cards_widget', 'tech_cards/widget/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Widget',
        'action'     => 'status_badge',
    ));

// Редактор: создание, правка, сохранение, генерация
Route::set('erp228_tech_cards_editor_create', 'tech_cards/editor')
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Editor',
        'action'     => 'create',
    ));

Route::set('erp228_tech_cards_editor_edit', 'tech_cards/editor/<id>', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Editor',
        'action'     => 'edit',
    ));

Route::set('erp228_tech_cards_save', 'tech_cards/save(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Editor',
        'action'     => 'save',
    ));

Route::set('erp228_tech_cards_generate', 'tech_cards/generate')
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Editor',
        'action'     => 'generate',
    ));

// Общий маршрут UI: сводка, список, карточка, удаление
Route::set('erp228_tech_cards_main', 'tech_cards(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp228',
        'controller' => 'Index',
        'action'     => 'index',
    ));
