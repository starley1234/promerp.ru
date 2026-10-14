<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp212_Storage_CreateTables extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        // Создание таблицы 'erp_212_storage_backends'
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_212_storage_backends (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                driver VARCHAR(20) NOT NULL CHECK (driver IN ('local', 's3', 'ftp')) DEFAULT 'local',
                config_data TEXT NULL,
                is_active SMALLINT NOT NULL DEFAULT 1
            );
        ");

        // Создание таблицы 'erp_212_files'
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_212_files (
                id SERIAL PRIMARY KEY,
                backend_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                module_prefix VARCHAR(50) NULL DEFAULT 'default',
                filename VARCHAR(255) NOT NULL,
                storage_path TEXT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size BIGINT NOT NULL,
                extension VARCHAR(10) NOT NULL,
                hash VARCHAR(64) NULL,
                is_public SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) NOT NULL,
                updated_at TIMESTAMP(0) NULL,
                deleted_at TIMESTAMP(0) NULL,
                CONSTRAINT fk_212_file_backend FOREIGN KEY (backend_id) REFERENCES erp_212_storage_backends (id) ON DELETE RESTRICT
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_212_files_user_id ON erp_212_files (user_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_212_files_mime ON erp_212_files (mime_type);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_212_files_module ON erp_212_files (module_prefix);");

        // Создание таблицы 'erp_212_file_links'
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_212_file_links (
                id SERIAL PRIMARY KEY,
                file_id INTEGER NOT NULL,
                token VARCHAR(128) NOT NULL,
                expires_at TIMESTAMP(0) NOT NULL,
                created_at TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP,
                click_count INTEGER DEFAULT 0,
                CONSTRAINT fk_212_links_file FOREIGN KEY (file_id) REFERENCES erp_212_files (id) ON DELETE CASCADE
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS idx_212_links_token ON erp_212_file_links (token);");

        // Создание таблицы 'erp_212_file_permissions'
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_212_file_permissions (
                id SERIAL PRIMARY KEY,
                file_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                can_read SMALLINT NOT NULL DEFAULT 1,
                can_write SMALLINT NOT NULL DEFAULT 0,
                can_delete SMALLINT NOT NULL DEFAULT 0,
                CONSTRAINT fk_212_perm_file FOREIGN KEY (file_id) REFERENCES erp_212_files (id) ON DELETE CASCADE
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS idx_212_file_user ON erp_212_file_permissions (file_id, user_id);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_212_file_permissions CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_212_file_links CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_212_files CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_212_storage_backends CASCADE;");
    }

}