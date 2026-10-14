<?php defined('SYSPATH') or die('No direct script access.');

/** Минимальная оргструктура, чтобы модуль был пригоден сразу после установки. */
class pgMigration_002_Erp310_Workforce_Seed extends pgMigration
{
    public function up()
    {
        $this->run_query("
            INSERT INTO erp_310_departments (code, name, kind, sort_order)
            VALUES ('COMPANY', 'Предприятие', 'company', 10)
            ON CONFLICT ((upper(code))) DO NOTHING;
            INSERT INTO erp_310_departments (parent_id, code, name, kind, sort_order)
            SELECT id, 'PRODUCTION', 'Производство', 'workshop', 20 FROM erp_310_departments WHERE code='COMPANY'
            ON CONFLICT ((upper(code))) DO NOTHING;
            INSERT INTO erp_310_departments (parent_id, code, name, kind, sort_order)
            SELECT id, 'WAREHOUSE', 'Склад', 'department', 30 FROM erp_310_departments WHERE code='COMPANY'
            ON CONFLICT ((upper(code))) DO NOTHING;

            INSERT INTO erp_310_positions (department_id, code, name, category)
            SELECT id, 'PROD-MANAGER', 'Начальник производства', 'manager' FROM erp_310_departments WHERE code='PRODUCTION'
            ON CONFLICT ((upper(code))) DO NOTHING;
            INSERT INTO erp_310_positions (department_id, code, name, category)
            SELECT id, 'OPERATOR', 'Оператор оборудования', 'worker' FROM erp_310_departments WHERE code='PRODUCTION'
            ON CONFLICT ((upper(code))) DO NOTHING;
            INSERT INTO erp_310_positions (department_id, code, name, category)
            SELECT id, 'STOREKEEPER', 'Кладовщик', 'worker' FROM erp_310_departments WHERE code='WAREHOUSE'
            ON CONFLICT ((upper(code))) DO NOTHING;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_310_positions WHERE code IN ('PROD-MANAGER','OPERATOR','STOREKEEPER');");
        $this->run_query("DELETE FROM erp_310_departments WHERE code IN ('PRODUCTION','WAREHOUSE');");
        $this->run_query("DELETE FROM erp_310_departments WHERE code='COMPANY';");
    }
}
