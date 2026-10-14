<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Качество (erp405_quality): таблицы.
 *
 * Четыре таблицы, которые повторяют путь несоответствия — от обнаружения до решения:
 *
 *   erp_405_defect_codes    справочник кодов дефектов (что именно не так);
 *   erp_405_inspections     контроль: партия, изделие, количество, где и кем
 *                           проверяется, чем закончилось;
 *   erp_405_defects         найденные несоответствия: код, количество, причина,
 *                           виновник (поставщик/производство/склад);
 *   erp_405_decisions       решения по партии (журнал: переделать, вернуть,
 *                           утиль, использовать как есть) и движение склада,
 *                           которым решение исполнено.
 *
 * Ссылки на чужие модули (номенклатура erp227, склад erp401, приёмка erp403,
 * производственный заказ erp402, операция цеха erp408, контрагент erp301) —
 * мягкие, без внешних ключей: модули включаются и выключаются независимо, а
 * документы качества остаются читаемыми.
 *
 * Флаги — SMALLINT 0/1, а не BOOLEAN: PostgreSQL отдаёт boolean строками
 * `t`/`f`, и `(bool) 'f'` — это TRUE (см. AGENTS.md).
 *
 * @package    Erp405
 * @category   Migrations
 */
class pgMigration_001_Erp405_Quality_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_defect_codes();
        $this->_create_inspections();
        $this->_create_defects();
        $this->_create_decisions();

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_405_inspection_number_seq START 1");
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_405_decisions CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_405_defects CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_405_inspections CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_405_defect_codes CASCADE');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_405_inspection_number_seq');
    }

    /**
     * Справочник кодов дефектов.
     *
     * @return  void
     */
    protected function _create_defect_codes()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_405_defect_codes (
                id              SERIAL PRIMARY KEY,
                code            VARCHAR(32)  NOT NULL,
                name            VARCHAR(255) NOT NULL,
                category        VARCHAR(32)  NOT NULL DEFAULT 'other',
                severity        SMALLINT     NOT NULL DEFAULT 2,
                description     TEXT         NULL,
                is_active       SMALLINT     NOT NULL DEFAULT 1,
                search_text     VARCHAR(512) NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_405_defect_codes_code_key UNIQUE (code),
                CONSTRAINT erp_405_defect_codes_category_check
                    CHECK (category IN ('size', 'surface', 'material', 'assembly', 'document', 'packaging', 'other')),
                CONSTRAINT erp_405_defect_codes_severity_check CHECK (severity BETWEEN 1 AND 3),
                CONSTRAINT erp_405_defect_codes_active_check CHECK (is_active IN (0, 1))
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_405_defect_codes IS 'Коды дефектов: что именно не так (erp405_quality)'");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_defect_codes_category ON erp_405_defect_codes (category, is_active)");
    }

    /**
     * Контроль (приёмочный, операционный, итоговый).
     *
     * @return  void
     */
    protected function _create_inspections()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_405_inspections (
                id                      SERIAL PRIMARY KEY,
                uuid                    UUID         NOT NULL DEFAULT gen_random_uuid(),
                number                  VARCHAR(32)  NOT NULL,
                kind                    VARCHAR(16)  NOT NULL DEFAULT 'incoming',
                source                  VARCHAR(24)  NOT NULL DEFAULT 'manual',
                source_ref              VARCHAR(64)  NULL,
                item_id                 INTEGER      NULL,
                item_name               VARCHAR(255) NULL,
                qty                     NUMERIC(14,3) NOT NULL DEFAULT 0,
                warehouse_id            INTEGER      NULL,
                quarantine_warehouse_id INTEGER      NULL,
                quarantine_qty          NUMERIC(14,3) NOT NULL DEFAULT 0,
                status                  VARCHAR(16)  NOT NULL DEFAULT 'draft',
                result                  VARCHAR(16)  NULL,
                decision                VARCHAR(16)  NULL,
                decision_comment        TEXT         NULL,
                defect_qty              NUMERIC(14,3) NOT NULL DEFAULT 0,
                supplier_id             INTEGER      NULL,
                order_id                INTEGER      NULL,
                operation_id            INTEGER      NULL,
                inspector_id            INTEGER      NULL,
                decided_by              INTEGER      NULL,
                decided_at              TIMESTAMP WITH TIME ZONE NULL,
                inspected_at            TIMESTAMP WITH TIME ZONE NULL,
                note                    TEXT         NULL,
                created_by              INTEGER      NULL,
                created_at              TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at              TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_405_inspections_number_key UNIQUE (number),
                CONSTRAINT erp_405_inspections_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_405_inspections_kind_check
                    CHECK (kind IN ('incoming', 'operational', 'final')),
                CONSTRAINT erp_405_inspections_source_check
                    CHECK (source IN ('manual', 'erp403_receipt', 'erp402_order', 'erp408_operation', 'erp407_proposal')),
                CONSTRAINT erp_405_inspections_status_check
                    CHECK (status IN ('draft', 'registered', 'decided', 'closed', 'cancelled')),
                CONSTRAINT erp_405_inspections_result_check
                    CHECK (result IS NULL OR result IN ('passed', 'failed')),
                CONSTRAINT erp_405_inspections_decision_check
                    CHECK (decision IS NULL OR decision IN ('use_as_is', 'rework', 'return', 'scrap', 'sort')),
                CONSTRAINT erp_405_inspections_qty_check CHECK (qty >= 0),
                CONSTRAINT erp_405_inspections_defect_qty_check CHECK (defect_qty >= 0),
                CONSTRAINT erp_405_inspections_quarantine_qty_check CHECK (quarantine_qty >= 0)
            )
        ");

        $this->run_query("COMMENT ON COLUMN erp_405_inspections.status IS 'draft — черновик контроля, registered — контроль идёт, decided — решение принято, closed — закрыт, cancelled — отменён'");
        $this->run_query("COMMENT ON COLUMN erp_405_inspections.decision IS 'use_as_is — использовать как есть, rework — в переделку, return — вернуть поставщику, scrap — в утиль, sort — сортировка'");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_inspections_status ON erp_405_inspections (status, kind)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_inspections_source ON erp_405_inspections (source, source_ref)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_inspections_item ON erp_405_inspections (item_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_inspections_supplier ON erp_405_inspections (supplier_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_inspections_created ON erp_405_inspections (created_at DESC)");
    }

    /**
     * Найденные несоответствия.
     *
     * @return  void
     */
    protected function _create_defects()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_405_defects (
                id              SERIAL PRIMARY KEY,
                inspection_id   INTEGER      NOT NULL,
                defect_code_id  INTEGER      NULL,
                code            VARCHAR(32)  NULL,
                qty             NUMERIC(14,3) NOT NULL DEFAULT 0,
                severity        SMALLINT     NOT NULL DEFAULT 2,
                cause           VARCHAR(16)  NOT NULL DEFAULT 'unknown',
                supplier_id     INTEGER      NULL,
                order_id        INTEGER      NULL,
                operation_id    INTEGER      NULL,
                item_id         INTEGER      NULL,
                photo_file_id   INTEGER      NULL,
                note            TEXT         NULL,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_405_defects_inspection_fkey
                    FOREIGN KEY (inspection_id) REFERENCES erp_405_inspections (id) ON DELETE CASCADE,
                CONSTRAINT erp_405_defects_cause_check
                    CHECK (cause IN ('supplier', 'production', 'warehouse', 'equipment', 'design', 'unknown')),
                CONSTRAINT erp_405_defects_severity_check CHECK (severity BETWEEN 1 AND 3),
                CONSTRAINT erp_405_defects_qty_check CHECK (qty > 0)
            )
        ");

        $this->run_query("COMMENT ON COLUMN erp_405_defects.cause IS 'Причина: supplier — поставщик, production — производство, warehouse — склад, equipment — оборудование, design — конструкция, unknown — не установлена'");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_defects_inspection ON erp_405_defects (inspection_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_defects_code ON erp_405_defects (defect_code_id, created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_defects_cause ON erp_405_defects (cause, created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_defects_supplier ON erp_405_defects (supplier_id, created_at DESC)");
    }

    /**
     * Решения по партии: журнал, который объясняет каждое движение склада.
     *
     * @return  void
     */
    protected function _create_decisions()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_405_decisions (
                id                  SERIAL PRIMARY KEY,
                inspection_id       INTEGER      NOT NULL,
                decision            VARCHAR(16)  NOT NULL,
                qty                 NUMERIC(14,3) NOT NULL DEFAULT 0,
                from_warehouse_id   INTEGER      NULL,
                to_warehouse_id     INTEGER      NULL,
                movement_ref        VARCHAR(64)  NULL,
                comment             TEXT         NULL,
                decided_by          INTEGER      NULL,
                created_at          TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_405_decisions_inspection_fkey
                    FOREIGN KEY (inspection_id) REFERENCES erp_405_inspections (id) ON DELETE CASCADE,
                CONSTRAINT erp_405_decisions_decision_check
                    CHECK (decision IN ('use_as_is', 'rework', 'return', 'scrap', 'sort', 'quarantine', 'release')),
                CONSTRAINT erp_405_decisions_qty_check CHECK (qty >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_405_decisions_inspection ON erp_405_decisions (inspection_id, created_at DESC)");
    }
}
