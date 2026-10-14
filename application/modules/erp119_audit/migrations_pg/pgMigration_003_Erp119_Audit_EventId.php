<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 003: след события шины в записи журнала.
 *
 * Перенос событий (`erp119:sync`) должен быть идемпотентным: повторное чтение
 * шины — после правки правил разбора, после переноса основной машины или после
 * сбоя — не должно задваивать записи. Для этого в строке журнала хранится номер
 * события из `erp_208_events`, а частичный уникальный индекс не даёт записать
 * одно событие дважды.
 *
 * Индекс частичный (WHERE event_id IS NOT NULL): записи, сделанные кодом
 * напрямую, события шины не имеют и под ограничение не попадают.
 *
 * Миграция идемпотентна.
 */
class pgMigration_003_Erp119_Audit_EventId extends pgMigration
{
    public function up()
    {
        $this->run_query("
            ALTER TABLE erp_119_audit_log
                ADD COLUMN IF NOT EXISTS event_id BIGINT NULL;
        ");

        $this->run_query("
            COMMENT ON COLUMN erp_119_audit_log.event_id IS
                'Номер события шины erp208, из которого выведена запись (NULL — запись сделана кодом)';
        ");

        // Уникальный индекс на секционированной таблице обязан включать ключ
        // секционирования (occurred_at), поэтому глобально уникальным след
        // события сделать нельзя. Ограничение навешиваем на каждую секцию:
        // одного события в одной секции быть не может, а разные секции
        // одному событию и не достанутся — время события определяет секцию.
        $this->run_query("
            CREATE INDEX IF NOT EXISTS erp_119_audit_log_event_idx
                ON erp_119_audit_log (event_id)
                WHERE event_id IS NOT NULL;
        ");

        foreach ($this->partitions() as $partition)
        {
            $this->run_query("
                CREATE UNIQUE INDEX IF NOT EXISTS ".$partition."_event_uniq
                    ON ".$partition." (event_id)
                    WHERE event_id IS NOT NULL;
            ");
        }
    }

    /**
     * Секции журнала, существующие в базе.
     *
     * @return  array
     */
    protected function partitions()
    {
        $names = array();

        foreach (DB::query(Database::SELECT, "
            SELECT c.relname AS name
            FROM pg_class c
            JOIN pg_inherits i ON i.inhrelid = c.oid
            JOIN pg_class p ON p.oid = i.inhparent
            WHERE p.relname = 'erp_119_audit_log'
            ORDER BY c.relname
        ")->execute()->as_array() as $row)
        {
            $names[] = $row['name'];
        }

        return $names;
    }

    public function down()
    {
        $this->run_query("DROP INDEX IF EXISTS erp_119_audit_log_event_idx;");

        foreach ($this->partitions() as $partition)
        {
            $this->run_query("DROP INDEX IF EXISTS ".$partition."_event_uniq;");
        }

        $this->run_query("ALTER TABLE erp_119_audit_log DROP COLUMN IF EXISTS event_id;");
    }
}
