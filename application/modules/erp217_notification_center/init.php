<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Регистрация роутов модуля ERP217 (Центр уведомлений)
 */

// Роут для API (тестовая отправка, статусы)
Route::set('erp217_api', 'erp217_api(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp217',
        'controller' => 'Api',
        'action'     => 'stats', // action_index у контроллера нет
    ));

// Роут для управления шаблонами (Admin)
Route::set('erp217_templates', 'erp217_templates(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp217',
        'controller' => 'Templates',
        'action'     => 'index',
    ));

// Роут для мониторинга очереди (Admin)
Route::set('erp217_notification', 'erp217_notification(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp217',
        'controller' => 'Notification',
        'action'     => 'index',
    ));

// Основной роут (Личный кабинет, подписки, виджеты)
Route::set('erp217_notification_center', 'erp217(/<controller>(/<action>(/<id>)))')
    ->defaults(array(
        'directory'  => 'Erp217',
        'controller' => 'Index',
        'action'     => 'index',
    ));

/*

*/


/**
 * Привязка Event Observer (Перехват системных событий)
 * Используется внутренняя система событий проекта (Event::listen или аналогичная)
 */

// 1. Перехват создания сущностей в системе для автоматических уведомлений
Event::listen('erp.*.entity.created', function($event_data) {
    // Вызов шины уведомлений: Service_Erp217_Bus::instance()->send(...)
    // Логика маппинга событий на шаблоны реализована внутри Bus
    Service_Erp217_Bus::instance()->handle_system_event('entity_created', $event_data);
});

// 2. Очистка подписок при удалении пользователя
Event::listen('auth.user_deleted', function($user) {
    if (isset($user->id)) {
        // Помечаем подписки как удаленные (is_deleted = 1)
        DB::update('erp_217_subscriptions')
            ->set(array('is_deleted' => 1, 'updated_at' => DB::expr('NOW()')))
            ->where('user_id', '=', $user->id)
            ->execute();
        
        Log::instance()->add(Log::INFO, 'ERP217: Subscriptions disabled for deleted user :id', array(':id' => $user->id));
    }
});

// 3. Регистрация хука для Cron (обработка очереди)
// Обычно вызывается через системный планировщик, но можно инициировать через событие
Event::listen('system.cron.minutely', function() {
    Service_Erp217_Bus::instance()->process_notification(50);
});

/**
 * Пример вызова шины из других модулей (для README):
 * Service_Erp217_Bus::instance()->send('new_order', $user_id, ['order_id' => 123]);
 */