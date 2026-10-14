<?php defined('SYSPATH') or die('No direct script access.');

/** Штатное расписание: план ставок отделён от фактических назначений. */
class pgMigration_004_Erp310_Workforce_StaffUnits extends pgMigration
{
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_310_staff_units (
                id             SERIAL PRIMARY KEY,
                uuid           UUID NOT NULL DEFAULT gen_random_uuid(),
                department_id  INTEGER NOT NULL REFERENCES erp_310_departments(id) ON DELETE RESTRICT,
                position_id    INTEGER NOT NULL REFERENCES erp_310_positions(id) ON DELETE RESTRICT,
                planned_fte    NUMERIC(7,2) NOT NULL CHECK (planned_fte > 0 AND planned_fte <= 999),
                valid_from     DATE NOT NULL DEFAULT CURRENT_DATE,
                valid_to       DATE NULL,
                status         VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
                note           TEXT NULL,
                created_by     INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP(0) NULL,
                CONSTRAINT erp_310_staff_dates CHECK (valid_to IS NULL OR valid_to >= valid_from)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_staff_units_uuid_uniq ON erp_310_staff_units(uuid);
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_staff_units_active_uniq
                ON erp_310_staff_units(department_id, position_id) WHERE status='active' AND valid_to IS NULL;
            CREATE INDEX IF NOT EXISTS erp_310_staff_units_department_idx
                ON erp_310_staff_units(department_id, status);
        ");
    }

    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_310_staff_units CASCADE;');
    }
}
