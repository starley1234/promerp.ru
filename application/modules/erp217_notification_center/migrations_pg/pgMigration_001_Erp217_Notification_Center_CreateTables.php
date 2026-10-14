<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp217_Notification_Center_CreateTables extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        // Функция для эмуляции ON UPDATE CURRENT_TIMESTAMP
        $this->run_query("
            CREATE OR REPLACE FUNCTION update_modified_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = now();
                RETURN NEW;
            END;
            $$ language 'plpgsql';
        ");

        // Таблица типов событий
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_event_types (
                id SERIAL PRIMARY KEY,
                alias VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                allowed_roles VARCHAR(255) DEFAULT 'login',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0,
                CONSTRAINT idx_alias UNIQUE (alias)
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_event_types_is_deleted ON erp_217_event_types (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_event_types_modtime ON erp_217_event_types;");
        $this->run_query("CREATE TRIGGER update_erp_217_event_types_modtime BEFORE UPDATE ON erp_217_event_types FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");

        // Таблица шаблонов уведомлений
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_templates (
                id SERIAL PRIMARY KEY,
                alias VARCHAR(100) NOT NULL,
                channel VARCHAR(50) NOT NULL,
                subject VARCHAR(255) DEFAULT NULL,
                body TEXT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0,
                CONSTRAINT idx_alias_channel UNIQUE (alias, channel)
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_templates_channel ON erp_217_templates (channel);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_templates_created_by ON erp_217_templates (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_templates_is_deleted ON erp_217_templates (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_templates_modtime ON erp_217_templates;");
        $this->run_query("CREATE TRIGGER update_erp_217_templates_modtime BEFORE UPDATE ON erp_217_templates FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");

        // Таблица настроек подписок пользователей на типы событий
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_subscriptions (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                channel VARCHAR(50) NOT NULL,
                is_enabled SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_user_event ON erp_217_subscriptions (user_id, event_type);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_subscriptions_created_by ON erp_217_subscriptions (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_subscriptions_is_deleted ON erp_217_subscriptions (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_subscriptions_modtime ON erp_217_subscriptions;");
        $this->run_query("CREATE TRIGGER update_erp_217_subscriptions_modtime BEFORE UPDATE ON erp_217_subscriptions FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");

        // Таблица подписок на конкретные сущности
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_entity_subscriptions (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0,
                CONSTRAINT idx_user_entity UNIQUE (user_id, entity_type, entity_id)
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_entity_lookup ON erp_217_entity_subscriptions (entity_type, entity_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_entity_subs_is_deleted ON erp_217_entity_subscriptions (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_entity_subscriptions_modtime ON erp_217_entity_subscriptions;");
        $this->run_query("CREATE TRIGGER update_erp_217_entity_subscriptions_modtime BEFORE UPDATE ON erp_217_entity_subscriptions FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");

        // Таблица очереди сообщений
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_notification (
                id SERIAL PRIMARY KEY,
                recipient_id INTEGER NOT NULL,
                template_id INTEGER DEFAULT NULL,
                channel VARCHAR(50) NOT NULL,
                destination VARCHAR(255) NOT NULL,
                subject VARCHAR(255) DEFAULT NULL,
                payload TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                attempts SMALLINT DEFAULT 0,
                last_error TEXT,
                scheduled_at TIMESTAMP DEFAULT NULL,
                sent_at TIMESTAMP DEFAULT NULL,
                is_read SMALLINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_notification_status ON erp_217_notification (status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_notification_scheduled_at ON erp_217_notification (scheduled_at);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_notification_recipient_id ON erp_217_notification (recipient_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_notification_created_by ON erp_217_notification (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_notification_is_deleted ON erp_217_notification (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_notification_modtime ON erp_217_notification;");
        $this->run_query("CREATE TRIGGER update_erp_217_notification_modtime BEFORE UPDATE ON erp_217_notification FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");

        // Таблица логов отправки
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_217_logs (
                id SERIAL PRIMARY KEY,
                queue_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                channel VARCHAR(50) NOT NULL,
                response_code VARCHAR(50) DEFAULT NULL,
                response_body TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_logs_queue_id ON erp_217_logs (queue_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_logs_user_id ON erp_217_logs (user_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_logs_created_by ON erp_217_logs (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_logs_is_deleted ON erp_217_logs (is_deleted);");
        $this->run_query("DROP TRIGGER IF EXISTS update_erp_217_logs_modtime ON erp_217_logs;");
        $this->run_query("CREATE TRIGGER update_erp_217_logs_modtime BEFORE UPDATE ON erp_217_logs FOR EACH ROW EXECUTE PROCEDURE update_modified_column();");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_217_logs CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_notification CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_entity_subscriptions CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_subscriptions CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_templates CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_217_event_types CASCADE;");
        $this->run_query("DROP FUNCTION IF EXISTS update_modified_column();");
    }
}