<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Демонстрационный стенд (erp811_demo): журнал сборок.
 *
 * Модуль держит только одну таблицу — историю сборок стенда. Она отвечает на
 * вопросы, которые задаёт и администратор, и посетитель: когда стенд собирали,
 * по какому сценарию, сколько объектов получилось и кто это запускал.
 *
 * Само демонстрационное производство живёт в отдельной базе (см. config/demo.php):
 * у демонстрации не должно быть ни одной строки в боевых таблицах.
 */
class pgMigration_001_Erp811_Demo_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->run_query('
            CREATE TABLE IF NOT EXISTS erp_811_demo (
                id        SERIAL PRIMARY KEY,
                built_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                built_by  INTEGER,
                scenario  VARCHAR(64) NOT NULL,
                counts    JSONB NOT NULL DEFAULT \'{}\',
                note      TEXT
            )
        ');

        $this->run_query('
            CREATE INDEX IF NOT EXISTS erp_811_demo_built_at_idx ON erp_811_demo (built_at DESC)
        ');
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->drop_table('erp_811_demo');
    }
}
