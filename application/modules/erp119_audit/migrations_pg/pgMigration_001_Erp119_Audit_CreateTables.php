<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: журнал действий и изменений (erp119_audit).
 *
 * Схема рассчитана на промышленную эксплуатацию, а не на демонстрацию:
 *
 *  1. `erp_119_audit_log` секционирована по месяцам (RANGE по occurred_at).
 *     Секционирование здесь не «оптимизация», а способ удалять старое:
 *     регламент хранения — это DROP целой секции, а не DELETE миллионов строк.
 *     DEFAULT-секция страхует от сбоя регламента: запись не потеряется, даже
 *     если секцию месяца не успели создать (её разбирает `attach_default()`).
 *  2. Таблица только на запись: триггер запрещает UPDATE и DELETE. Правка
 *     журнала возможна лишь обслуживанием от владельца таблиц (тесты и
 *     регламент делают это через `disable_immutability()`).
 *  3. `erp_119_audit_cursors` — указатель чтения шины событий (outbox):
 *     журнал дочитывает `erp_208_events` и не задваивает записи.
 *  4. `erp_119_audit_settings` — справочник аудита сущностей: человеческое
 *     название, аудит чтения (track_reads), свой срок хранения, включение.
 *
 * Миграция идемпотентна (IF NOT EXISTS / ON CONFLICT DO NOTHING).
 */
class pgMigration_001_Erp119_Audit_CreateTables extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Журнал: родительская секционированная таблица
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_119_audit_log (
                id           BIGSERIAL    NOT NULL,
                occurred_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
                module       VARCHAR(32)  NOT NULL,
                entity_type  VARCHAR(64)  NOT NULL,
                entity_id    VARCHAR(64)  NOT NULL DEFAULT '',
                entity_label VARCHAR(255) NULL,
                action       VARCHAR(32)  NOT NULL,
                severity     VARCHAR(16)  NOT NULL DEFAULT 'info',
                actor_id     INTEGER      NULL,
                actor_name   VARCHAR(128) NULL,
                actor_ip     INET         NULL,
                source       VARCHAR(16)  NOT NULL DEFAULT 'web',
                request_id   VARCHAR(64)  NULL,
                route        VARCHAR(255) NULL,
                message      TEXT         NULL,
                changes      JSONB        NOT NULL DEFAULT '[]'::jsonb,
                context      JSONB        NOT NULL DEFAULT '{}'::jsonb,
                search_text  TEXT         NOT NULL DEFAULT '',
                PRIMARY KEY (id, occurred_at),
                CONSTRAINT erp_119_audit_log_severity_check
                    CHECK (severity IN ('info', 'notice', 'warning', 'critical')),
                CONSTRAINT erp_119_audit_log_source_check
                    CHECK (source IN ('web', 'api', 'cli', 'cron', 'event', 'system'))
            ) PARTITION BY RANGE (occurred_at);
        ");

        // DEFAULT-секция: принимает всё, для чего нет секции месяца
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_119_audit_log_default
                PARTITION OF erp_119_audit_log DEFAULT;
        ");

        // Секции месяцев: шесть назад (разбор «вчерашних» записей) и три вперёд
        $storage = $this->_storage();

        for ($shift = -1 * (int) $storage['months_back']; $shift <= (int) $storage['months_ahead']; $shift++)
        {
            $this->run_query($this->_partition_sql(date('Y-m', strtotime(($shift >= 0 ? '+' : '').$shift.' month', strtotime(date('Y-m-01'))))));
        }

        // ---------------------------------------------------------------------
        // 2. Индексы: «по объекту», «по человеку», «по времени», «по запросу»
        //    и GIN по изменениям («в каких записях менялось это поле»)
        // ---------------------------------------------------------------------
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_entity_idx ON erp_119_audit_log (entity_type, entity_id, occurred_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_actor_idx ON erp_119_audit_log (actor_id, occurred_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_module_idx ON erp_119_audit_log (module, action, occurred_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_recent_idx ON erp_119_audit_log (occurred_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_request_idx ON erp_119_audit_log (request_id) WHERE request_id IS NOT NULL;");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_119_audit_log_changes_gin ON erp_119_audit_log USING gin (changes jsonb_path_ops);");

        // ---------------------------------------------------------------------
        // 3. Только запись: UPDATE и DELETE запрещены
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE OR REPLACE FUNCTION erp_119_audit_log_immutable() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'erp_119_audit_log: журнал только на запись (append-only), % запрещено', TG_OP;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        $this->run_query("
            CREATE OR REPLACE TRIGGER erp_119_audit_log_immutable_trg
                BEFORE DELETE OR UPDATE ON erp_119_audit_log
                FOR EACH ROW EXECUTE FUNCTION erp_119_audit_log_immutable();
        ");

        // ---------------------------------------------------------------------
        // 4. Указатель чтения шины событий (outbox)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_119_audit_cursors (
                name          VARCHAR(64)  NOT NULL,
                last_event_id BIGINT       NOT NULL DEFAULT 0,
                updated_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
                PRIMARY KEY (name)
            );
        ");

        $this->run_query("
            INSERT INTO erp_119_audit_cursors (name, last_event_id) VALUES ('events', 0)
            ON CONFLICT (name) DO NOTHING;
        ");

        // ---------------------------------------------------------------------
        // 5. Справочник аудита сущностей
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_119_audit_settings (
                id               SERIAL       NOT NULL,
                module           VARCHAR(32)  NOT NULL,
                entity_type      VARCHAR(64)  NOT NULL,
                label            VARCHAR(128) NOT NULL,
                track_reads      SMALLINT     NOT NULL DEFAULT 0,
                retention_months INTEGER      NULL,
                enabled          SMALLINT     NOT NULL DEFAULT 1,
                updated_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
                PRIMARY KEY (id)
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_119_audit_settings_uniq ON erp_119_audit_settings (module, entity_type);");

        // Что аудируется «из коробки». Просмотры (track_reads) включены только
        // там, где они действительно значимы: пользователи и роли.
        $seed = array(
            array('erp228', 'tech_card', 'Технологическая карта', 0),
            array('erp228', 'tech_document', 'Техпроцесс', 0),
            array('erp301', 'counterparty', 'Контрагент', 0),
            array('erp301', 'contract', 'Договор', 0),
            array('erp227', 'object', 'Объект реестра', 0),
            array('erp230', 'dictionary', 'Справочник MDM', 0),
            array('erp401', 'movement', 'Движение по складу', 0),
            array('erp217', 'notification', 'Уведомление', 0),
            array('erp100', 'user', 'Пользователь', 1),
            array('erp100', 'role', 'Роль', 0),
        );

        foreach ($seed as $row)
        {
            $this->run_query("
                INSERT INTO erp_119_audit_settings (module, entity_type, label, track_reads, enabled)
                VALUES (".$this->_quote($row[0]).", ".$this->_quote($row[1]).", ".$this->_quote($row[2]).", ".(int) $row[3].", 1)
                ON CONFLICT (module, entity_type) DO NOTHING;
            ");
        }
    }

    public function down()
    {
        $this->run_query("DROP TRIGGER IF EXISTS erp_119_audit_log_immutable_trg ON erp_119_audit_log;");

        // Секции уходят вместе с родительской таблицей
        $this->run_query("DROP TABLE IF EXISTS erp_119_audit_log CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_119_audit_cursors;");
        $this->run_query("DROP TABLE IF EXISTS erp_119_audit_settings;");
        $this->run_query("DROP FUNCTION IF EXISTS erp_119_audit_log_immutable();");
    }

    /**
     * Настройки хранения из конфига модуля.
     *
     * @return  array
     */
    protected function _storage()
    {
        $config = Kohana::$config->load('erp119_audit');
        $config = ($config instanceof Kohana_Config_Group) ? $config->as_array() : (array) $config;

        $storage = isset($config['storage']) ? $config['storage'] : array();

        return array(
            'months_back'  => isset($storage['months_back']) ? $storage['months_back'] : 6,
            'months_ahead' => isset($storage['months_ahead']) ? $storage['months_ahead'] : 3,
        );
    }

    /**
     * SQL создания секции месяца.
     *
     * @param   string  $month  YYYY-MM
     * @return  string
     */
    protected function _partition_sql($month)
    {
        $name  = 'erp_119_audit_log_'.str_replace('-', '_', $month);
        $start = $month.'-01';
        $end   = date('Y-m-01', strtotime($start.' +1 month'));

        return "CREATE TABLE IF NOT EXISTS {$name} PARTITION OF erp_119_audit_log"
              ." FOR VALUES FROM (".$this->_quote($start).") TO (".$this->_quote($end).");";
    }

    /**
     * Строковый литерал (миграции — не тот случай, где нужны параметры:
     * DDL не принимает привязку значений).
     *
     * @param   string  $value
     * @return  string
     */
    protected function _quote($value)
    {
        return "'".str_replace("'", "''", (string) $value)."'";
    }
}
