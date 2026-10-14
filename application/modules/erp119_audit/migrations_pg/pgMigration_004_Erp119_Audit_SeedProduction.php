<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 004: справочник аудита — сущности производства (erp402, erp408).
 *
 * Производственный заказ (erp402) пишет в журнал сущность `order`, цех
 * (erp408) — три вида сущностей: операция, рабочий центр и смена. Без записи
 * в `erp_119_audit_settings` они видны в интерфейсе аудита техническими
 * именами (`order`, `operation`, `work_center`, `shift`) и не попадают в
 * фильтр сущностей — поэтому справочник дополняется вместе с модулями.
 *
 * Таблицу ведёт сам модуль аудита: чужие модули в неё не пишут, а добавляют
 * записи миграцией — иначе сброс справочника в одном месте ломает связи в
 * другом.
 *
 * Миграция идемпотентна: повторный запуск ничего не меняет.
 */
class pgMigration_004_Erp119_Audit_SeedProduction extends pgMigration
{
    public function up()
    {
        $entities = array(
            array('erp402', 'order',       'Производственный заказ'),
            array('erp408', 'operation',   'Операция цеха'),
            array('erp408', 'work_center', 'Рабочий центр'),
            array('erp408', 'shift',       'Смена цеха'),
        );

        foreach ($entities as $entity)
        {
            $this->run_query("
                INSERT INTO erp_119_audit_settings (module, entity_type, label, track_reads, enabled)
                VALUES (
                    '".$entity[0]."',
                    '".$entity[1]."',
                    '".str_replace("'", "''", $entity[2])."',
                    0,
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
            WHERE (module = 'erp408' AND entity_type IN ('operation', 'work_center', 'shift'))
               OR (module = 'erp402' AND entity_type = 'order');
        ");
    }
}
