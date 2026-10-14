<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Конфигурация модуля ERP217 "Центр уведомлений"
 */
return array(
    // Список доступных каналов (для валидации и интерфейсов)
    'channels' => array(
        'push'     => 'Внутреннее уведомление',
        'email'    => 'E-mail',
        //'telegram' => 'Telegram',
        //'max'      => 'Система Max',
        
    ),
    
    // Настройки внутренних Push-уведомлений (браузерные/внутрисистемные)
    'push' => array(
        'save_to_db'      => TRUE,  // Сохранять ли историю в БД для виджета "Колокольчик"
        'realtime'        => FALSE, // Использовать ли WebSocket (если настроен Socket.io/Centrifugo)
    ),
    
    
    // Настройки драйвера Email (SMTP)
    // 'email' => array(), // email из настроек сайта

    // Настройки драйвера Telegram Bot API
    'telegram' => array(
        'bot_token'       => '0000000000:AAH_ExampleToken_XXXXXXXXXXXX',
        'api_url'         => 'https://api.telegram.org/bot',
        'parse_mode'      => 'HTML', // HTML или Markdown
        'proxy'           => array(
            'enabled'  => FALSE,
            'host'     => '',
            'port'     => '',
            'user'     => '',
            'pass'     => '',
        ),
    ),

    // Настройки драйвера интеграции с системой Max
    'max' => array(
        'api_key'         => 'max_integration_key_v1',
        'endpoint'        => 'https://api.max-system.ru/v1/notify',
    ),

    

    // Параметры обработки очереди (Notification)
    'notification' => array(
        'max_attempts'    => 5,     // Максимальное количество попыток отправки при ошибках
        'batch_limit'     => 50,    // Сколько сообщений обрабатывать за один запуск CRON
        'retry_interval'  => 300,   // Интервал между попытками в секундах (5 минут)
        'log_success'     => TRUE,  // Записывать ли успешные отправки в erp_217_logs
        'log_errors'      => TRUE,  // Записывать ли ошибки в erp_217_logs
    ),

    // Настройки безопасности
    'acl' => array(
        'admin_roles' => array('admin', 'director'),
    ),
);