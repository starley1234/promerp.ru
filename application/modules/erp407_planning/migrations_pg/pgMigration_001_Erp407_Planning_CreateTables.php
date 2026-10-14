<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Планирование потребностей (erp407_planning): таблицы.
 *
 * Четыре таблицы, которые отражают четыре шага работы плановика:
 *
 *   erp_407_programs      производственная программа: что и когда выпустить;
 *   erp_407_program_lines строки программы: изделие, количество, срок;
 *   erp_407_requirements  снимок расчёта потребности (прогон MRP): сколько
 *                         нужно, сколько есть на складе и в незавершёнке,
 *                         сколько не хватает и когда это надо заказать;
 *   erp_407_proposals     предложения (закупка или производство) и их судьба:
 *                         создано, утверждено, передано в исполнение.
 *
 * Ссылки на чужие модули (номенклатура erp227, склад erp401, производство
 * erp402, техкарты erp228) — мягкие, без внешних ключей: модули включаются и
 * выключаются независимо, а расчёт остаётся воспроизводимым.
 *
 * @package    Erp407
 * @category   Migrations
 */
class pgMigration_001_Erp407_Planning_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_programs();
        $this->_create_program_lines();
        $this->_create_requirements();
        $this->_create_proposals();
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_407_proposals CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_407_requirements CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_407_program_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_407_programs CASCADE');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_407_program_number_seq');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_407_proposal_number_seq');
    }

    /**
     * Производственная программа: горизонт планирования и состояние.
     *
     * @return  void
     */
    protected function _create_programs()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_407_programs (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                title           VARCHAR(255) NOT NULL,
                period_from     DATE        NOT NULL,
                period_to       DATE        NOT NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'draft',
                source          VARCHAR(16) NOT NULL DEFAULT 'manual',
                horizon_days    INTEGER     NOT NULL DEFAULT 30,
                note            TEXT        NULL,
                run_at          TIMESTAMP WITH TIME ZONE NULL,
                run_uuid        UUID        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_407_programs_number_key UNIQUE (number),
                CONSTRAINT erp_407_programs_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_407_programs_status_check CHECK (status IN ('draft', 'approved', 'closed')),
                CONSTRAINT erp_407_programs_source_check CHECK (source IN ('manual', 'orders')),
                CONSTRAINT erp_407_programs_period_check CHECK (period_to >= period_from)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_programs_status ON erp_407_programs (status, period_from)");

        // Номера программ и предложений — из последовательностей (как номера
        // заказов производства): сквозная нумерация на год, без гонок.
        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_407_program_number_seq START 1");
        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_407_proposal_number_seq START 1");
    }

    /**
     * Строки программы: изделие и срок выпуска.
     *
     * @return  void
     */
    protected function _create_program_lines()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_407_program_lines (
                id              SERIAL PRIMARY KEY,
                program_id      INTEGER      NOT NULL REFERENCES erp_407_programs (id) ON DELETE CASCADE,
                line_no         INTEGER      NOT NULL DEFAULT 10,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                due_date        DATE         NULL,
                tech_card_id    INTEGER      NULL,
                order_id        INTEGER      NULL,
                status          VARCHAR(16)  NOT NULL DEFAULT 'planned',
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_407_program_lines_qty_check CHECK (qty >= 0),
                CONSTRAINT erp_407_program_lines_status_check CHECK (status IN ('planned', 'produced', 'cancelled'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_program_lines_program ON erp_407_program_lines (program_id, line_no)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_program_lines_item ON erp_407_program_lines (item_id)");
    }

    /**
     * Снимок расчёта потребности (прогон MRP).
     *
     * Строка — «что делать с позицией»: сколько нужно, чем закрывается спрос
     * (склад, резерв, незавершёнка), сколько остаётся закрыть и когда это надо
     * заказать, чтобы успеть к сроку.
     *
     * @return  void
     */
    protected function _create_requirements()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_407_requirements (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                run_uuid        UUID        NOT NULL,
                program_id      INTEGER     NULL REFERENCES erp_407_programs (id) ON DELETE CASCADE,
                line_id         INTEGER     NULL,
                item_id         INTEGER     NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                supply_type     VARCHAR(16)  NOT NULL DEFAULT 'purchase',
                demand_qty      NUMERIC(18,6) NOT NULL DEFAULT 0,
                stock_qty       NUMERIC(18,6) NOT NULL DEFAULT 0,
                reserved_qty    NUMERIC(18,6) NOT NULL DEFAULT 0,
                wip_qty         NUMERIC(18,6) NOT NULL DEFAULT 0,
                on_order_qty    NUMERIC(18,6) NOT NULL DEFAULT 0,
                covered_qty     NUMERIC(18,6) NOT NULL DEFAULT 0,
                deficit_qty     NUMERIC(18,6) NOT NULL DEFAULT 0,
                order_qty       NUMERIC(18,6) NOT NULL DEFAULT 0,
                round_qty       NUMERIC(18,6) NOT NULL DEFAULT 0,
                lead_time_days  INTEGER     NOT NULL DEFAULT 0,
                due_date        DATE        NULL,
                order_date      DATE        NULL,
                late            BOOLEAN     NOT NULL DEFAULT FALSE,
                level           INTEGER     NOT NULL DEFAULT 0,
                parent_name     VARCHAR(255) NULL,
                source          VARCHAR(16) NOT NULL DEFAULT 'program',
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_407_requirements_supply_check CHECK (supply_type IN ('purchase', 'produce')),
                CONSTRAINT erp_407_requirements_source_check CHECK (source IN ('program', 'orders'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_requirements_run ON erp_407_requirements (run_uuid)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_requirements_program ON erp_407_requirements (program_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_requirements_deficit ON erp_407_requirements (deficit_qty) WHERE deficit_qty > 0");
    }

    /**
     * Предложения: что заказать у поставщика и что запустить в производство.
     *
     * @return  void
     */
    protected function _create_proposals()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_407_proposals (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                requirement_id  INTEGER     NULL REFERENCES erp_407_requirements (id) ON DELETE SET NULL,
                program_id      INTEGER     NULL REFERENCES erp_407_programs (id) ON DELETE SET NULL,
                kind            VARCHAR(16) NOT NULL DEFAULT 'purchase',
                item_id         INTEGER     NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                due_date        DATE        NULL,
                order_date      DATE        NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'new',
                priority        INTEGER     NOT NULL DEFAULT 3,
                target_ref      VARCHAR(64) NULL,
                approved_by     INTEGER     NULL,
                approved_at     TIMESTAMP WITH TIME ZONE NULL,
                done_at         TIMESTAMP WITH TIME ZONE NULL,
                note            TEXT        NULL,
                created_by      INTEGER     NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_407_proposals_number_key UNIQUE (number),
                CONSTRAINT erp_407_proposals_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_407_proposals_kind_check CHECK (kind IN ('purchase', 'produce')),
                CONSTRAINT erp_407_proposals_status_check CHECK (status IN ('new', 'approved', 'exported', 'cancelled', 'done')),
                CONSTRAINT erp_407_proposals_qty_check CHECK (qty >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_proposals_status ON erp_407_proposals (status, kind)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_407_proposals_item ON erp_407_proposals (item_id)");
    }
}
