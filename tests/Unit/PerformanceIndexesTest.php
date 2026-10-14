<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Миграции производительности: индексы объявлены только на существующих
 * таблицах и колонках, а статика шаблона подписана версией.
 *
 * Тест статический (читает исходники): базы данных ему не нужно, поэтому он
 * ловит опечатку в имени таблицы до выкладки, а не на живой миграции.
 */
class PerformanceIndexesTest extends TestCase
{
    /**
     * @return array  файл => [таблица => [колонки…]]
     */
    protected function definitions()
    {
        $result = array();
        $files  = array_merge(
            glob(APPPATH.'modules/*/migrations_pg/*PerformanceIndexes.php'),
            glob(APPPATH.'modules/*/migrations_pg/*Performance_Indexes.php')
        );

        foreach ($files as $file)
        {
            $source = file_get_contents($file);

            // Берём только присваивания $_indexes / $_trigram
            preg_match_all('/\$_(?:indexes|trigram)\s*=\s*array\((.*?)\n    \);/s', $source, $blocks);

            foreach ($blocks[1] as $block)
            {
                preg_match_all("/'(\w+)'\s*=>\s*array\(([^)]*)\)/", $block, $pairs, PREG_SET_ORDER);

                foreach ($pairs as $pair)
                {
                    preg_match_all("/'([^']+)'/", $pair[2], $columns);

                    foreach ($columns[1] as $definition)
                    {
                        foreach (explode(',', $definition) as $column)
                        {
                            $result[$file][$pair[1]][] = trim($column);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Исходники всех миграций одной строкой.
     *
     * @return string
     */
    protected function migrations_source()
    {
        $source = '';

        foreach (glob(APPPATH.'modules/*/migrations_pg/*.php') as $file)
        {
            if ( ! preg_match('/Performance_?Indexes\.php$/', $file))
            {
                $source .= "\n".file_get_contents($file);
            }
        }

        return $source;
    }

    public function test_performance_migrations_exist_for_search_heavy_modules()
    {
        $files = array_keys($this->definitions());

        $this->assertGreaterThanOrEqual(10, count($files), 'Миграции индексов не найдены');
    }

    public function test_indexed_tables_and_columns_exist_in_schema_migrations()
    {
        $source     = $this->migrations_source();
        $violations = array();

        foreach ($this->definitions() as $file => $tables)
        {
            foreach ($tables as $table => $columns)
            {
                if ( ! preg_match('/CREATE TABLE IF NOT EXISTS\s+'.preg_quote($table, '/').'\b/', $source, $match, PREG_OFFSET_CAPTURE))
                {
                    $violations[] = basename($file).': таблица '.$table.' нигде не создаётся';
                    continue;
                }

                // Тело CREATE TABLE — до следующего CREATE TABLE
                $tail  = substr($source, $match[0][1]);
                $next  = strpos($tail, 'CREATE TABLE IF NOT EXISTS', 10);
                $block = $next === FALSE ? $tail : substr($tail, 0, $next);

                foreach (array_unique($columns) as $column)
                {
                    $created = preg_match('/\b'.preg_quote($column, '/').'\b/', $block);
                    $added   = preg_match('/ALTER TABLE\s+'.preg_quote($table, '/').'\s+ADD COLUMN IF NOT EXISTS\s+'.preg_quote($column, '/').'\b/', $source);

                    if ( ! $created AND ! $added)
                    {
                        $violations[] = basename($file).': у таблицы '.$table.' нет колонки '.$column;
                    }
                }
            }
        }

        $this->assertSame(array(), $violations);
    }

    public function test_migration_helpers_validate_identifiers()
    {
        $source = file_get_contents(APPPATH.'modules/erp111_migrations_postgresql/classes/Model/Erp111/Migration.php');

        $this->assertStringContainsString('function add_indexes', $source);
        $this->assertStringContainsString('function add_trigram_indexes', $source);
        $this->assertStringContainsString('function assert_identifier', $source);
        $this->assertStringContainsString("'/^[a-z_][a-z0-9_]*$/'", $source, 'Имена в SQL индекса должны проверяться');
    }

    public function test_template_scripts_carry_version_stamp()
    {
        $template = file_get_contents(APPPATH.'views/template_tailwindcss.php');

        foreach (array('theme_init', 'template_js', 'app_js', 'smart_reference_js', 'jquery', 'fontawesome') as $key)
        {
            $this->assertStringContainsString("Assets::url('".$key."')", $template, 'Статика «'.$key.'» должна идти с меткой версии');
            $this->assertTrue(\Assets::exists($key), 'Файл статики «'.$key.'» не найден');
            $this->assertStringContainsString('?v=', \Assets::url($key));
        }

        $this->assertStringNotContainsString('src="/assets/js/app.js"', $template);
    }
}
