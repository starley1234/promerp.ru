<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: справочник аудита — сущности работающих модулей.
 *
 * Справочник `erp_119_audit_settings` решает три задачи: человеческое название
 * сущности в интерфейсе, аудит просмотров (track_reads) и свой срок хранения.
 * Здесь добавляются сущности, которые появились в шине событий после первого
 * наполнения: складские движения и резервы, сделки CRM, базы сравнения MDM.
 *
 * Миграция идемпотентна: повторный запуск ничего не меняет.
 */
class pgMigration_002_Erp119_Audit_SeedEntities extends pgMigration
{
    public function up()
    {
        $entities = array(
            array('erp401', 'stock_reservation', 'Резерв на складе', 0),
            array('erp221', 'deal', 'Сделка CRM', 0),
            array('erp230', 'baseline', 'База сравнения', 0),
        );

        // «Движение по складу» заведено миграцией 001; здесь только уточняем
        // подпись, если она осталась технической.
        $this->run_query("
            UPDATE erp_119_audit_settings
            SET label = 'Движение по складу'
            WHERE module = 'erp401' AND entity_type = 'movement' AND label = 'movement';
        ");

        foreach ($entities as $entity)
        {
            $this->run_query("
                INSERT INTO erp_119_audit_settings (module, entity_type, label, track_reads, enabled)
                VALUES (
                    '".$entity[0]."',
                    '".$entity[1]."',
                    '".str_replace("'", "''", $entity[2])."',
                    ".(int) $entity[3].",
                    1
                )
                ON CONFLICT (module, entity_type) DO NOTHING;
            ");
        }
    }

    public function down()
    {
        $this->run_query("
            DELETE FROM erp_119_audit_settings
            WHERE module = 'erp401' AND entity_type = 'stock_reservation'
               OR module = 'erp221' AND entity_type = 'deal'
               OR module = 'erp230' AND entity_type = 'baseline';
        ");
    }
}
