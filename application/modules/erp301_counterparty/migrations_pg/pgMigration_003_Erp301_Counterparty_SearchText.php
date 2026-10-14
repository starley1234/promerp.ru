<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 003: нормализованное поле поиска (search_text).
 *
 * Кластер PostgreSQL собран с локалью C, поэтому `lower()` и `ILIKE` не меняют
 * регистр кириллицы: поиск «платан» не находил «Платан». Поле search_text
 * заполняется в PHP через mb_strtolower и ищется обычным LIKE.
 *
 * Заполнение существующих строк выполняется здесь же, идемпотентно.
 */
class pgMigration_003_Erp301_Counterparty_SearchText extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        $this->run_query("ALTER TABLE erp_301_counterparties ADD COLUMN IF NOT EXISTS search_text VARCHAR(1024) NULL;");
        $this->run_query("ALTER TABLE erp_301_contracts ADD COLUMN IF NOT EXISTS search_text VARCHAR(1024) NULL;");

        // Бэкфилл: mb_strtolower недоступен в SQL при locale=C
        foreach (DB::select('id', 'name', 'full_name', 'code', 'inn')
            ->from('erp_301_counterparties')->execute()->as_array() as $row)
        {
            DB::update('erp_301_counterparties')
                ->set(array('search_text' => $this->_lower(implode(' ', array_filter(array(
                    $row['name'], $row['full_name'], $row['code'], $row['inn'],
                ))))))
                ->where('id', '=', (int) $row['id'])
                ->execute();
        }

        foreach (DB::select('id', 'number', 'title')
            ->from('erp_301_contracts')->execute()->as_array() as $row)
        {
            DB::update('erp_301_contracts')
                ->set(array('search_text' => $this->_lower(implode(' ', array_filter(array($row['number'], $row['title']))))))
                ->where('id', '=', (int) $row['id'])
                ->execute();
        }

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_counterparties_search_idx ON erp_301_counterparties (search_text);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_contracts_search_idx ON erp_301_contracts (search_text);");
    }

    public function down()
    {
        $this->run_query("DROP INDEX IF EXISTS erp_301_counterparties_search_idx;");
        $this->run_query("DROP INDEX IF EXISTS erp_301_contracts_search_idx;");
        $this->run_query("ALTER TABLE erp_301_counterparties DROP COLUMN IF EXISTS search_text;");
        $this->run_query("ALTER TABLE erp_301_contracts DROP COLUMN IF EXISTS search_text;");
    }

    /**
     * Приведение строки к нижнему регистру (UTF-8).
     *
     * @param   string  $value
     * @return  string
     */
    protected function _lower($value)
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }
}
