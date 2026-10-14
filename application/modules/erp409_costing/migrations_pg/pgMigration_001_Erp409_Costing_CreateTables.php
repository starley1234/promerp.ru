<?php defined('SYSPATH') or die('No direct script access.');
class pgMigration_001_Erp409_Costing_CreateTables extends pgMigration
{
    public function up()
    {
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_409_calculations(
            id BIGSERIAL PRIMARY KEY, uuid UUID NOT NULL DEFAULT gen_random_uuid(), order_id INTEGER NOT NULL,
            order_number VARCHAR(32) NOT NULL, order_title VARCHAR(255) NOT NULL, version INTEGER NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft', currency VARCHAR(3) NOT NULL DEFAULT 'RUB',
            planned_material NUMERIC(18,2) NOT NULL DEFAULT 0, planned_labor NUMERIC(18,2) NOT NULL DEFAULT 0,
            planned_machine NUMERIC(18,2) NOT NULL DEFAULT 0, planned_overhead NUMERIC(18,2) NOT NULL DEFAULT 0,
            actual_material NUMERIC(18,2) NOT NULL DEFAULT 0, actual_labor NUMERIC(18,2) NOT NULL DEFAULT 0,
            actual_machine NUMERIC(18,2) NOT NULL DEFAULT 0, actual_overhead NUMERIC(18,2) NOT NULL DEFAULT 0,
            scrap_loss NUMERIC(18,2) NOT NULL DEFAULT 0, planned_total NUMERIC(18,2) NOT NULL DEFAULT 0,
            actual_total NUMERIC(18,2) NOT NULL DEFAULT 0, output_qty NUMERIC(18,3) NOT NULL DEFAULT 0,
            planned_unit_cost NUMERIC(18,6), actual_unit_cost NUMERIC(18,6), warnings JSONB NOT NULL DEFAULT '[]'::jsonb,
            calculated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, calculated_by INTEGER,
            approved_at TIMESTAMPTZ, approved_by INTEGER,
            CONSTRAINT erp_409_calc_status_ck CHECK(status IN('draft','approved')),
            CONSTRAINT erp_409_calc_order_version_uq UNIQUE(order_id,version), CONSTRAINT erp_409_calc_uuid_uq UNIQUE(uuid))");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_409_calc_order_idx ON erp_409_calculations(order_id,version DESC)");
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_409_lines(
            id BIGSERIAL PRIMARY KEY, calculation_id BIGINT NOT NULL REFERENCES erp_409_calculations(id) ON DELETE CASCADE,
            side VARCHAR(8) NOT NULL, category VARCHAR(16) NOT NULL, label VARCHAR(255) NOT NULL,
            item_id INTEGER, operation_id INTEGER, source_type VARCHAR(32) NOT NULL, source_id BIGINT,
            qty NUMERIC(18,6) NOT NULL DEFAULT 0, uom_code VARCHAR(16), rate NUMERIC(18,6) NOT NULL DEFAULT 0,
            amount NUMERIC(18,2) NOT NULL DEFAULT 0, rate_source VARCHAR(64) NOT NULL,
            source_ref VARCHAR(255), included_in_total BOOLEAN NOT NULL DEFAULT TRUE, explanation JSONB NOT NULL DEFAULT '{}'::jsonb,
            CONSTRAINT erp_409_line_side_ck CHECK(side IN('plan','actual')),
            CONSTRAINT erp_409_line_category_ck CHECK(category IN('material','labor','machine','overhead','scrap')))");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_409_lines_calc_idx ON erp_409_lines(calculation_id,side,category)");
    }
    public function down(){ $this->run_query('DROP TABLE IF EXISTS erp_409_lines CASCADE'); $this->run_query('DROP TABLE IF EXISTS erp_409_calculations CASCADE'); }
}
