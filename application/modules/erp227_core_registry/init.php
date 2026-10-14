<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Init file for Core Registry Module (ID: 227)
 * Универсальный реестр объектов ERP
 */

// 1. Регистрация роутов модуля
// Основной интерфейс управления реестром
Route::set('erp227_core_registry_admin', 'registry(/<controller>(/<action>(/<id>)))', array(
    'id' => '\d+',
))
->defaults(array(
    'directory'  => 'Erp227',
    'controller' => 'Index',
    'action'     => 'index',
));

// API роуты для поиска и динамических данных (Select2, TreeView, AI Search)
Route::set('erp227_core_registry_api', 'api/registry/<action>(/<id>)', array(
    'id' => '\d+',
))
->defaults(array(
    'directory'  => 'Erp227',
    'controller' => 'Api',
));

// Роуты для управления типами и единицами измерения
Route::set('erp227_core_registry_settings', 'registry/settings/<controller>(/<action>(/<id>))', array(
    'controller' => '(types|units|links)',
    'id'         => '\d+',
))
->defaults(array(
    'directory'  => 'Erp227',
));

// 2. Регистрация событий (Event Observer)
// Перехват системных событий для синхронизации с реестром

/**
 * Событие: После создания объекта в реестре
 * Используется для генерации эмбеддингов или уведомлений
 */
Event::listen('erp227.object.created', function($object_model) {
    // Логика после создания: например, постановка задачи на генерацию AI-вектора
    // Model_Erp227_Event::log($object_model->id, 'created');
});

/**
 * Событие: Изменение связей (BOM / Назначения)
 * Позволяет другим модулям реагировать на изменение структуры изделия
 */
Event::listen('erp227.link.added', function($link_model) {
    // Например: пересчет себестоимости родительского объекта
});

/**
 * Событие: Удаление пользователя системы
 * Синхронизация: если пользователь удален, деактивируем связанный объект "Сотрудник"
 */
Event::listen('system.user.delete', function($user_id) {
    // Поиск объекта типа 'employee' с внешним ID пользователя и смена статуса
    // Service_Erp227_Registry::instance()->deactivate_user_object($user_id);
});

/**
 * Событие: Системный аудит
 * Запись всех критических изменений реестра в секционированную таблицу событий
 */
Event::listen('erp227.audit.log', function($data) {
    $event = ORM::factory('Erp227_Event');
    $event->values($data);
    $event->save();
});