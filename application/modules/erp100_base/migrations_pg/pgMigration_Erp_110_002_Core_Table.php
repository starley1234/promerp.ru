<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Расширение структуры ядра ERP (Core) для PostgreSQL.
 * Добавляет таблицы профилей, настроек, сессий и OAuth.
 */
class pgMigration_Erp_110_002_Core_Table extends pgMigration {
    
    // protected $_db_group = 'default';
    
    public function up()
    {
        // 1. Сессии (PostgreSQL)
        $this->run_query("CREATE TABLE IF NOT EXISTS sessions (
            session_id varchar(128) NOT NULL,
            last_active integer NOT NULL,
            contents text NOT NULL,
            CONSTRAINT sessions_pkey PRIMARY KEY (session_id)
        );");
        
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_sessions_last_active ON sessions (last_active);");

        // 2. Расширение таблицы пользователей
        $this->run_query("ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS last_ip varchar(45) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS email_confirmed smallint NOT NULL DEFAULT 0,
            ADD COLUMN IF NOT EXISTS confirmation_token varchar(64) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS failed_login_attempts integer NOT NULL DEFAULT 0,
            ADD COLUMN IF NOT EXISTS last_failed_login integer DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reset_token varchar(64) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reset_expires integer DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN IF NOT EXISTS updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");

        // 3. Настройки пользователей (JSONB для PostgreSQL)
        $this->run_query("CREATE TABLE IF NOT EXISTS users_settings (
            user_id integer NOT NULL,
            settings_json jsonb DEFAULT NULL,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT users_settings_pkey PRIMARY KEY (user_id),
            CONSTRAINT users_settings_user_id_fkey FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );");

        // 4. Профили пользователей
        $this->run_query("CREATE TABLE IF NOT EXISTS user_profiles (
            user_id integer NOT NULL,
            first_name varchar(255) DEFAULT NULL,
            last_name varchar(255) DEFAULT NULL,
            middle_name varchar(255) DEFAULT NULL,
            full_name varchar(512) DEFAULT NULL,
            date_of_birth date DEFAULT NULL,
            gender varchar(20) DEFAULT NULL,
            phone_number varchar(20) DEFAULT NULL,
            telegram varchar(100) DEFAULT NULL,
            max_messenger varchar(100) DEFAULT NULL,
            address varchar(255) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            timezone varchar(50) DEFAULT 'UTC',
            position varchar(255) DEFAULT NULL,
            department_id integer DEFAULT NULL,
            avatar_url varchar(504) DEFAULT NULL,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT user_profiles_pkey PRIMARY KEY (user_id),
            CONSTRAINT user_profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );");
        
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_user_profiles_department_id ON user_profiles (department_id);");

        // 5. OAuth данные
        $this->run_query("CREATE TABLE IF NOT EXISTS users_oauth (
            id serial,
            user_id integer NOT NULL,
            provider varchar(50) NOT NULL,
            provider_id varchar(255) NOT NULL,
            access_token text NOT NULL,
            refresh_token text,
            expires_in integer DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            first_name varchar(255) DEFAULT NULL,
            last_name varchar(255) DEFAULT NULL,
            email varchar(254) DEFAULT NULL,
            sex varchar(10) DEFAULT NULL,
            birthday date DEFAULT NULL,
            avatar_id varchar(255) DEFAULT NULL,
            CONSTRAINT users_oauth_pkey PRIMARY KEY (id),
            CONSTRAINT users_oauth_provider_id_key UNIQUE (provider, provider_id),
            CONSTRAINT users_oauth_user_id_fkey FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );");

        // 6. Сообщения обратной связи
        $this->run_query("CREATE TABLE IF NOT EXISTS contact_messages (
            id serial,
            user_id integer DEFAULT NULL,
            email varchar(254) NOT NULL,
            message text NOT NULL,
            referer varchar(2048) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT contact_messages_pkey PRIMARY KEY (id),
            CONSTRAINT contact_messages_user_id_fkey FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        );");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS contact_messages CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS users_oauth CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS user_profiles CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS users_settings CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS sessions CASCADE;");

        $this->run_query("ALTER TABLE users 
            DROP COLUMN IF EXISTS last_ip,
            DROP COLUMN IF EXISTS email_confirmed,
            DROP COLUMN IF EXISTS confirmation_token,
            DROP COLUMN IF EXISTS failed_login_attempts,
            DROP COLUMN IF EXISTS last_failed_login,
            DROP COLUMN IF EXISTS reset_token,
            DROP COLUMN IF EXISTS reset_expires,
            DROP COLUMN IF EXISTS created_at,
            DROP COLUMN IF EXISTS updated_at;");
    }
}