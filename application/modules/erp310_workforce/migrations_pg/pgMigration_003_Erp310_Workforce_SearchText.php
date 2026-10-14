<?php defined('SYSPATH') or die('No direct script access.');

/** Поисковая строка нормализуется PHP: PostgreSQL locale C не lower-case кириллицу. */
class pgMigration_003_Erp310_Workforce_SearchText extends pgMigration
{
    public function up()
    {
        $this->run_query("ALTER TABLE erp_310_employees ADD COLUMN IF NOT EXISTS search_text TEXT NOT NULL DEFAULT '';");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_310_employees_search_idx ON erp_310_employees(search_text text_pattern_ops);");
    }

    public function down()
    {
        $this->run_query('DROP INDEX IF EXISTS erp_310_employees_search_idx;');
        $this->run_query('ALTER TABLE erp_310_employees DROP COLUMN IF EXISTS search_text;');
    }
}
