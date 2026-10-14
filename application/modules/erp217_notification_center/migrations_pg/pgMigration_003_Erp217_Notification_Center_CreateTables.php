<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_003_Erp217_Notification_Center_CreateTables extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        // Функция для автоматического обновления поля updated_at
        $this->run_query("
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql';
        ");

        // Таблица для хранения количества непрочитанных уведомлений
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_unread_counters (
                user_id INTEGER NOT NULL,
                count INTEGER DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id)
            );
        ");

        $this->run_query("
            DROP TRIGGER IF EXISTS update_erp_217_unread_counters_modtime ON erp_217_unread_counters;
            CREATE TRIGGER update_erp_217_unread_counters_modtime
            BEFORE UPDATE ON erp_217_unread_counters
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ");

        // Таблица для статистики
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_statistics (
                id SERIAL PRIMARY KEY,
                channel VARCHAR(50) NOT NULL DEFAULT 'all',
                total_in_notification INTEGER DEFAULT 0,
                pending_count INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                sent_last_24h INTEGER DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT idx_channel UNIQUE (channel)
            );
        ");

        $this->run_query("
            DROP TRIGGER IF EXISTS update_erp_217_statistics_modtime ON erp_217_statistics;
            CREATE TRIGGER update_erp_217_statistics_modtime
            BEFORE UPDATE ON erp_217_statistics
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_217_statistics CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_unread_counters CASCADE;");
    }
}