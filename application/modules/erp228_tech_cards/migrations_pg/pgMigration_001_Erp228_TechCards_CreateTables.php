<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: технологические карты, документы и операции (erp228_tech_cards).
 *
 * Порт исходной MySQL-схемы на PostgreSQL: ENUM заменён на VARCHAR + CHECK,
 * JSON — на JSONB, AUTO_INCREMENT — на SERIAL, uuid генерируется функцией
 * gen_random_uuid() (pgcrypto) либо сервисом в PHP.
 *
 * Миграция идемпотентна: CREATE TABLE IF NOT EXISTS + CREATE INDEX IF NOT EXISTS.
 */
class pgMigration_001_Erp228_TechCards_CreateTables extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Технологические карты (исходник и состояние генерации)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_228_tech_cards (
                id              SERIAL PRIMARY KEY,
                uuid            UUID         NOT NULL DEFAULT gen_random_uuid(),
                target_type     VARCHAR(50)  NOT NULL DEFAULT 'parametric'
                                CHECK (target_type IN ('parametric', 'stl', 'manual')),
                target_id       INTEGER      NULL,
                title           VARCHAR(255) NOT NULL,
                source_code     TEXT         NOT NULL,
                input_params    JSONB        NOT NULL DEFAULT '{}'::jsonb,
                production_type VARCHAR(50)  NOT NULL DEFAULT 'additive',
                status          VARCHAR(20)  NOT NULL DEFAULT 'draft'
                                CHECK (status IN ('draft', 'pending', 'processing', 'ready', 'error')),
                error_msg       TEXT         NULL,
                search_text     VARCHAR(512) NOT NULL DEFAULT '',
                is_deleted      SMALLINT     NOT NULL DEFAULT 0,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_228_tech_cards_uuid_uniq ON erp_228_tech_cards (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_228_tech_cards_target_idx ON erp_228_tech_cards (target_type, target_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_228_tech_cards_status_idx ON erp_228_tech_cards (status, is_deleted);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_228_tech_cards_search_idx ON erp_228_tech_cards (search_text varchar_pattern_ops);");

        // ---------------------------------------------------------------------
        // 2. Документы — результаты генерации (одна актуальная версия на карту)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_228_tech_documents (
                id               SERIAL PRIMARY KEY,
                tech_card_id     INTEGER      NOT NULL REFERENCES erp_228_tech_cards (id) ON DELETE CASCADE,
                is_actual        SMALLINT     NOT NULL DEFAULT 1,
                content_markdown TEXT         NULL,
                structured_data  JSONB        NOT NULL DEFAULT '[]'::jsonb,
                llm_model        VARCHAR(100) NULL,
                tokens_total     INTEGER      NOT NULL DEFAULT 0,
                gen_time_ms      INTEGER      NOT NULL DEFAULT 0,
                created_at       TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_228_tech_documents_card_idx ON erp_228_tech_documents (tech_card_id, is_actual);");

        // Актуальная версия у карты ровно одна — гарантирует сама БД.
        $this->run_query("
            CREATE UNIQUE INDEX IF NOT EXISTS erp_228_tech_documents_actual_uniq
                ON erp_228_tech_documents (tech_card_id)
                WHERE is_actual = 1;
        ");

        // ---------------------------------------------------------------------
        // 3. Операции — шаги техпроцесса, разобранные из ответа LLM
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_228_tech_operations (
                id             SERIAL PRIMARY KEY,
                document_id    INTEGER       NOT NULL REFERENCES erp_228_tech_documents (id) ON DELETE CASCADE,
                sort_order     INTEGER       NOT NULL DEFAULT 10,
                op_name        VARCHAR(255)  NOT NULL,
                op_description TEXT          NULL,
                duration_sec   INTEGER       NOT NULL DEFAULT 0,
                cost_amount    NUMERIC(15,2) NOT NULL DEFAULT 0,
                params         JSONB         NOT NULL DEFAULT '{}'::jsonb
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_228_tech_operations_doc_idx ON erp_228_tech_operations (document_id, sort_order);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_228_tech_operations CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_228_tech_documents CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_228_tech_cards CASCADE;");
    }
}
