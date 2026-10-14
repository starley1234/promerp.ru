<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp208_Events_CreateTables extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        // Таблица для хранения событий
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_208_events (
                id SERIAL PRIMARY KEY,
                event_name VARCHAR(255) NOT NULL,
                initiator_id INTEGER DEFAULT NULL,
                payload TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_208_events.event_name IS 'Название события (например, erp502.task_done)';");
        $this->run_query("COMMENT ON COLUMN erp_208_events.initiator_id IS 'ID пользователя, инициировавшего событие';");
        $this->run_query("COMMENT ON COLUMN erp_208_events.payload IS 'JSON-строка с данными события';");

        // Таблица для хранения подписчиков на события
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_208_event_subscribers (
                id SERIAL PRIMARY KEY,
                event_name VARCHAR(255) NOT NULL,
                module_id VARCHAR(50) DEFAULT NULL,
                callback_method VARCHAR(255),
                is_notification BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT idx_event_module_callback UNIQUE (event_name, module_id, callback_method)
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_208_event_subscribers.event_name IS 'Название события, на которое подписан';");
        $this->run_query("COMMENT ON COLUMN erp_208_event_subscribers.module_id IS 'Идентификатор модуля-подписчика (например, 502, notifications)';");
        $this->run_query("COMMENT ON COLUMN erp_208_event_subscribers.callback_method IS 'Метод или функция, которая должна быть вызвана при событии';");
        $this->run_query("COMMENT ON COLUMN erp_208_event_subscribers.is_notification IS 'Флаг автоматической отправки в Notification Center';");
        $this->run_query("COMMENT ON COLUMN erp_208_event_subscribers.is_active IS 'Активна ли подписка';");

        // Добавление специфических ролей для модуля 208
        $this->run_query("
            INSERT INTO roles (name, description) 
            SELECT '208_event_publisher', 'Пользователь, который может инициировать события модуля 208'
            WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = '208_event_publisher');
        ");

        $this->run_query("
            INSERT INTO roles (name, description) 
            SELECT '208_event_listener', 'Пользователь, который может просматривать или обрабатывать события модуля 208'
            WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = '208_event_listener');
        ");
    }

    public function down()
    {
        // Удаление ролей, добавленных в up()
        $this->run_query("DELETE FROM roles WHERE name IN ('208_event_publisher', '208_event_listener');");

        // Удаление таблицы 'erp_208_event_subscribers'
        $this->run_query("DROP TABLE IF EXISTS erp_208_event_subscribers CASCADE;");

        // Удаление таблицы 'erp_208_events'
        $this->run_query("DROP TABLE IF EXISTS erp_208_events CASCADE;");
    }
}