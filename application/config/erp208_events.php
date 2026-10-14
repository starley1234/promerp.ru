<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Конфигурация шины событий (erp208_events).
 *
 * Файла не было вообще, поэтому EventBus получал пустой конфиг и работал
 * «в памяти»: событие уходило подписчикам текущего запроса и нигде не
 * сохранялось. Из-за этого:
 *   - нельзя было разобрать, что происходило в системе час назад;
 *   - журнал действий (erp119_audit) не мог дочитать события из erp_208_events;
 *   - экран «События» падал на отсутствующем ключе predefined_event_names.
 *
 * Теперь каждое опубликованное событие сохраняется в erp_208_events — таблица
 * работает как outbox: журнал действий и разбор инцидентов читают её отдельно
 * от запроса пользователя (задача erp119:sync по указателю).
 */
return array(

    'event_bus' => array(

        // Сохранять каждое опубликованное событие в erp_208_events
        'log_published_events' => TRUE,

        // Список для выбора на экране событий (значения не ограничивают
        // публикацию — опубликовать можно любое имя)
        'predefined_event_names' => array(
            // Пользователи и доступ
            'auth.user_logged_in',
            'auth.user_created',
            'auth.user_deleted',

            // Документы и производство
            'erp228.card.created',
            'erp228.card.updated',
            'erp228.card.deleted',
            'erp228.card.status',
            'erp228.card.document',

            // Контрагенты
            'erp301.counterparty.created',
            'erp301.counterparty.updated',
            'erp301.counterparty.deleted',
            'erp301.contract.created',
            'erp301.contract.updated',
            'erp301.contract.terminated',

            // Склад
            'erp401.movement.created',
            'erp401.stock.low',

            // Реестр объектов и мастер-данные
            'erp227.object.created',
            'erp227.object.updated',
            'erp227.object.linked',
            'erp230.object.approved',

            // Файлы и уведомления
            'erp212_storage.file_uploaded',
            'erp212_storage.file_downloaded',
            'erp217.email.sent',
            'erp217.email.failed',

            // Платформа
            'erp218.module.status_changed',
            'erp218.module.updated',
        ),
    ),

    'logging' => array(
        'enabled' => TRUE,
        'file'    => 'events.log',
        'level'   => Log::INFO,
    ),
);
