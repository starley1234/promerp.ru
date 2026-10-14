<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовая миграция ядра ERP (Core) для PostgreSQL
 */
class pgMigration_Erp_110_001_Core_Table extends pgMigration {
    
    // protected $_db_group = 'default';
    
    public function up()
    {
        // 1. Роли
        $this->run_query("CREATE TABLE IF NOT EXISTS roles
        (
          id serial,
          \"name\" varchar(32) NOT NULL,
          description text NOT NULL,
          CONSTRAINT roles_id_pkey PRIMARY KEY (id),
          CONSTRAINT roles_name_key UNIQUE (name)
        );");

        // 2. Пользователи
        $this->run_query("CREATE TABLE IF NOT EXISTS users
        (
          id serial,
          email varchar(254) NOT NULL,
          username varchar(32) NOT NULL,
          \"password\" varchar(64) NOT NULL,
          logins integer NOT NULL DEFAULT 0,
          last_login integer,
          CONSTRAINT users_id_pkey PRIMARY KEY (id),
          CONSTRAINT users_username_key UNIQUE (username),
          CONSTRAINT users_email_key UNIQUE (email),
          CONSTRAINT users_logins_check CHECK (logins >= 0)
        );");

        // 3. Связь роли-пользователи
        $this->run_query("CREATE TABLE IF NOT EXISTS roles_users
        (
          user_id integer,
          role_id integer
        );");

        // 4. Токены
        $this->run_query("CREATE TABLE IF NOT EXISTS user_tokens
        (
          id serial,
          user_id integer NOT NULL,
          user_agent varchar(40) NOT NULL,
          token character varying(64) NOT NULL,
          created integer NOT NULL,
          expires integer NOT NULL,
          CONSTRAINT user_tokens_id_pkey PRIMARY KEY (id),
          CONSTRAINT user_tokens_token_key UNIQUE (token)
        );");

        // 5. Индексы
        $this->run_query("CREATE INDEX IF NOT EXISTS user_id_idx ON roles_users (user_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS role_id_idx ON roles_users (role_id);");

        // 6. Внешние ключи
        $this->run_query("ALTER TABLE roles_users DROP CONSTRAINT IF EXISTS user_id_fkey;");
        $this->run_query("ALTER TABLE roles_users ADD CONSTRAINT user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;");
        
        $this->run_query("ALTER TABLE roles_users DROP CONSTRAINT IF EXISTS role_id_fkey;");
        $this->run_query("ALTER TABLE roles_users ADD CONSTRAINT role_id_fkey FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE;");

        $this->run_query("ALTER TABLE user_tokens DROP CONSTRAINT IF EXISTS user_id_fkey;");
        $this->run_query("ALTER TABLE user_tokens ADD CONSTRAINT user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;");

        // 7. Начальные данные
        $this->run_query("INSERT INTO roles (name, description) VALUES 
            ('login', 'Login privileges, granted after account confirmation'),
            ('admin', 'Administrative user, has access to everything.')
            ON CONFLICT (name) DO NOTHING;");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS user_tokens CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS roles_users CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS users CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS roles CASCADE;");
    }
}