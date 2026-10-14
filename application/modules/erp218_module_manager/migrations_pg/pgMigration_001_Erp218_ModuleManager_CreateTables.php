<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp218_ModuleManager_CreateTables extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        // Таблица реестра модулей (основная сущность)
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_218_modules (
                id SERIAL PRIMARY KEY,
                system_name VARCHAR(100) NOT NULL,
                system_id VARCHAR(50) DEFAULT NULL,
                display_name VARCHAR(255) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT NULL,
                version VARCHAR(50) DEFAULT '1.0.0',
                status VARCHAR(50) DEFAULT 'alpha',
                is_published SMALLINT DEFAULT 0,
                developer_notes TEXT,
                path VARCHAR(255) DEFAULT NULL,
                main_page_uri VARCHAR(255) DEFAULT NULL,
                functionality JSONB DEFAULT NULL,
                entities JSONB DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_218_modules.system_name IS 'Имя папки модуля в application/modules/';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.system_id IS 'Префикс модуля, например, erp_218';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.display_name IS 'Понятное название модуля';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.description IS 'Краткое описание функционала';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.icon IS 'Иконка модуля (например, fa-cogs)';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.version IS 'Версия в формате SemVer';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.status IS 'alpha, beta, stable, archived';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.is_published IS 'Флаг доступности модуля в системе';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.developer_notes IS 'Технические заметки и особенности внедрения';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.path IS 'Абсолютный или относительный путь к директории';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.main_page_uri IS 'URI главной страницы модуля';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.functionality IS 'JSON-объект с перечнем функционала';");
        $this->run_query("COMMENT ON COLUMN erp_218_modules.entities IS 'JSON-объект с перечнем сущностей модуля';");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS idx_erp218_modules_system_name ON erp_218_modules (system_name);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_modules_status ON erp_218_modules (status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_modules_is_published ON erp_218_modules (is_published);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_modules_created_by ON erp_218_modules (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_modules_is_deleted ON erp_218_modules (is_deleted);");

        // Таблица зависимостей между модулями
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_218_dependencies (
                id SERIAL PRIMARY KEY,
                module_id INTEGER NOT NULL,
                required_module_id INTEGER NOT NULL,
                min_version VARCHAR(50) DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_218_dependencies.module_id IS 'ID основного модуля';");
        $this->run_query("COMMENT ON COLUMN erp_218_dependencies.required_module_id IS 'ID модуля, от которого зависит основной';");
        $this->run_query("COMMENT ON COLUMN erp_218_dependencies.min_version IS 'Минимально требуемая версия зависимого модуля';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_deps_module_id ON erp_218_dependencies (module_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_deps_required_module_id ON erp_218_dependencies (required_module_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_deps_created_by ON erp_218_dependencies (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_deps_is_deleted ON erp_218_dependencies (is_deleted);");

        // Таблица истории изменений версий (Changelog)
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_218_version_history (
                id SERIAL PRIMARY KEY,
                module_id INTEGER NOT NULL,
                version VARCHAR(50) NOT NULL,
                change_log TEXT,
                release_date DATE DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_218_version_history.change_log IS 'Описание изменений в этой версии';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_vh_module_id ON erp_218_version_history (module_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_vh_version ON erp_218_version_history (version);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_vh_created_by ON erp_218_version_history (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_vh_is_deleted ON erp_218_version_history (is_deleted);");

        // Таблица метаданных LLM (результаты анализа кода и README)
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_218_llm_analysis (
                id SERIAL PRIMARY KEY,
                module_id INTEGER NOT NULL,
                raw_content TEXT,
                ai_summary TEXT,
                analysis_status VARCHAR(50) DEFAULT 'pending',
                last_scan_at TIMESTAMP DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT NULL,
                is_deleted SMALLINT DEFAULT 0
            );
        ");

        $this->run_query("COMMENT ON COLUMN erp_218_llm_analysis.raw_content IS 'Исходный текст из README или файлов';");
        $this->run_query("COMMENT ON COLUMN erp_218_llm_analysis.ai_summary IS 'Сгенерированное нейросетью резюме';");
        $this->run_query("COMMENT ON COLUMN erp_218_llm_analysis.analysis_status IS 'pending, completed, failed';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_llm_module_id ON erp_218_llm_analysis (module_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_llm_status ON erp_218_llm_analysis (analysis_status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_llm_created_by ON erp_218_llm_analysis (created_by);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp218_llm_is_deleted ON erp_218_llm_analysis (is_deleted);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_218_llm_analysis CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_218_version_history CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_218_dependencies CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_218_modules CASCADE;");
    }
}