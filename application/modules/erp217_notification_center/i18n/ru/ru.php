<?php

return [
    // Название модуля
    'erp217_notification_center_module_name' => 'Центр уведомлений',

    // Каналы связи (Channels)
    'channel_email'    => 'Электронная почта (Email)',
    'channel_telegram' => 'Telegram',
    'channel_max'      => 'Max (Мессенджер)',
    'channel_push'     => 'Push-уведомления',
    'channel_internal' => 'Внутренние уведомления',

    // Статусы сообщений/очереди (Statuses)
    'status_pending'   => 'В ожидании',
    'status_notificationd'    => 'В очереди',
    'status_sending'   => 'Отправляется',
    'status_sent'      => 'Отправлено',
    'status_delivered' => 'Доставлено',
    'status_failed'    => 'Ошибка отправки',
    'status_read'      => 'Прочитано',
    'status_canceled'  => 'Отменено',

    // Настройки подписок (Subscriptions)
    'subscription_settings'  => 'Настройки подписок',
    'event_name'             => 'Событие системы',
    'subscribe_all'          => 'Подписаться на все',
    'unsubscribe_all'        => 'Отписаться от всего',
    'notification_frequency' => 'Частота уведомлений',

    // Системные сообщения и заголовки
    'error_channel_not_found'   => 'Выбранный канал связи не найден или не настроен.',
    'error_notification_failed'        => 'Не удалось добавить сообщение в очередь.',
    'success_subscription_save' => 'Настройки уведомлений успешно сохранены.',
    'no_notifications'          => 'У вас пока нет новых уведомлений.',
    'mark_as_read'              => 'Пометить как прочитанное',
    'clear_history'             => 'Очистить историю',

    // Очередь (Notification)
    'notification_priority'    => 'Приоритет',
    'notification_attempts'    => 'Попыток отправки',
    'notification_last_error'  => 'Последняя ошибка',
    'notification_scheduled_at' => 'Запланировано на',
];