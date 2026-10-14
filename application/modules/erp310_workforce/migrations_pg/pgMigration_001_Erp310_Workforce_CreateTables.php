<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Организационная структура, штатные позиции и сотрудники.
 */
class pgMigration_001_Erp310_Workforce_CreateTables extends pgMigration
{
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_310_departments (
                id          SERIAL PRIMARY KEY,
                uuid        UUID NOT NULL DEFAULT gen_random_uuid(),
                parent_id   INTEGER NULL REFERENCES erp_310_departments(id) ON DELETE RESTRICT,
                code        VARCHAR(32) NOT NULL,
                name        VARCHAR(255) NOT NULL,
                kind        VARCHAR(24) NOT NULL DEFAULT 'department'
                            CHECK (kind IN ('company','branch','department','workshop','section','team')),
                status      VARCHAR(16) NOT NULL DEFAULT 'active'
                            CHECK (status IN ('active','archived')),
                sort_order  INTEGER NOT NULL DEFAULT 100,
                created_by  INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP(0) NULL,
                CONSTRAINT erp_310_departments_parent_self CHECK (parent_id IS NULL OR parent_id <> id)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_departments_uuid_uniq ON erp_310_departments(uuid);
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_departments_code_uniq ON erp_310_departments(upper(code));
            CREATE INDEX IF NOT EXISTS erp_310_departments_parent_idx ON erp_310_departments(parent_id, sort_order, name);

            CREATE TABLE IF NOT EXISTS erp_310_positions (
                id             SERIAL PRIMARY KEY,
                uuid           UUID NOT NULL DEFAULT gen_random_uuid(),
                department_id  INTEGER NULL REFERENCES erp_310_departments(id) ON DELETE RESTRICT,
                code           VARCHAR(32) NOT NULL,
                name           VARCHAR(255) NOT NULL,
                category       VARCHAR(24) NOT NULL DEFAULT 'specialist'
                               CHECK (category IN ('director','manager','specialist','worker','support')),
                status         VARCHAR(16) NOT NULL DEFAULT 'active'
                               CHECK (status IN ('active','archived')),
                created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP(0) NULL
            );
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_positions_uuid_uniq ON erp_310_positions(uuid);
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_positions_code_uniq ON erp_310_positions(upper(code));
            CREATE INDEX IF NOT EXISTS erp_310_positions_department_idx ON erp_310_positions(department_id, status);

            CREATE TABLE IF NOT EXISTS erp_310_employees (
                id             SERIAL PRIMARY KEY,
                uuid           UUID NOT NULL DEFAULT gen_random_uuid(),
                personnel_no   VARCHAR(32) NOT NULL,
                user_id        INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                last_name      VARCHAR(128) NOT NULL,
                first_name     VARCHAR(128) NOT NULL,
                middle_name    VARCHAR(128) NULL,
                email          VARCHAR(255) NULL,
                phone          VARCHAR(64) NULL,
                hired_at       DATE NULL,
                dismissed_at   DATE NULL,
                status         VARCHAR(16) NOT NULL DEFAULT 'active'
                               CHECK (status IN ('active','leave','dismissed')),
                note           TEXT NULL,
                search_text    TEXT NOT NULL DEFAULT '',
                created_by     INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP(0) NULL,
                CONSTRAINT erp_310_employee_dates CHECK (dismissed_at IS NULL OR hired_at IS NULL OR dismissed_at >= hired_at)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_employees_uuid_uniq ON erp_310_employees(uuid);
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_employees_number_uniq ON erp_310_employees(upper(personnel_no));
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_employees_user_uniq ON erp_310_employees(user_id) WHERE user_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS erp_310_employees_name_idx ON erp_310_employees(lower(last_name), lower(first_name));
            CREATE INDEX IF NOT EXISTS erp_310_employees_status_idx ON erp_310_employees(status);

            CREATE TABLE IF NOT EXISTS erp_310_assignments (
                id             SERIAL PRIMARY KEY,
                employee_id    INTEGER NOT NULL REFERENCES erp_310_employees(id) ON DELETE CASCADE,
                department_id  INTEGER NOT NULL REFERENCES erp_310_departments(id) ON DELETE RESTRICT,
                position_id    INTEGER NULL REFERENCES erp_310_positions(id) ON DELETE RESTRICT,
                manager_id     INTEGER NULL REFERENCES erp_310_employees(id) ON DELETE SET NULL,
                valid_from     DATE NOT NULL DEFAULT CURRENT_DATE,
                valid_to       DATE NULL,
                is_primary     SMALLINT NOT NULL DEFAULT 1 CHECK (is_primary IN (0,1)),
                rate           NUMERIC(5,2) NOT NULL DEFAULT 1 CHECK (rate > 0 AND rate <= 2),
                created_by     INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_310_assignment_dates CHECK (valid_to IS NULL OR valid_to >= valid_from),
                CONSTRAINT erp_310_assignment_manager CHECK (manager_id IS NULL OR manager_id <> employee_id)
            );
            CREATE INDEX IF NOT EXISTS erp_310_assignments_employee_idx ON erp_310_assignments(employee_id, valid_from DESC);
            CREATE INDEX IF NOT EXISTS erp_310_assignments_department_idx ON erp_310_assignments(department_id, valid_to);
            CREATE UNIQUE INDEX IF NOT EXISTS erp_310_assignments_current_primary_uniq
                ON erp_310_assignments(employee_id) WHERE is_primary = 1 AND valid_to IS NULL;
        ");
    }

    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_310_assignments CASCADE;');
        $this->run_query('DROP TABLE IF EXISTS erp_310_employees CASCADE;');
        $this->run_query('DROP TABLE IF EXISTS erp_310_positions CASCADE;');
        $this->run_query('DROP TABLE IF EXISTS erp_310_departments CASCADE;');
    }
}
