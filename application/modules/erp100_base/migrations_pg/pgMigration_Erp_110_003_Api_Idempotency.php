<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Идемпотентность изменяющих запросов JSON API (ядро, erp100_base).
 *
 * Зачем в ядре. Точка входа для машин у каждого модуля — свой `Api.php`, и
 * повтор запроса там означал повтор операции: обрыв связи, повтор агента или
 * двойной клик по кнопке создавали второй документ, второй заказ, вторую
 * приёмку. Слой MCP от повторов защищён (уникальный `idempotency_key` в
 * `erp_804_mcp_calls`), а обычный JSON API — нет. Ключ и ответ хранятся здесь,
 * чтобы защита работала одинаково для всех модулей: модулю достаточно быть
 * API-контроллером, менять свой код не нужно.
 *
 * Таблица — не журнал, а защита от повтора, поэтому записи короткоживущие:
 * `expires_at` по умолчанию 24 часа, `purge()` чистит просроченные.
 *
 * @package    Erp100
 * @category   Migrations
 */
class pgMigration_Erp_110_003_Api_Idempotency extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_100_api_idempotency (
            id bigserial PRIMARY KEY,
            scope varchar(128) NOT NULL,
            idem_key varchar(128) NOT NULL,
            user_id integer NULL,
            method varchar(8) NOT NULL DEFAULT 'POST',
            uri text NULL,
            request_hash varchar(40) NOT NULL,
            request_json text NULL,
            state varchar(16) NOT NULL DEFAULT 'in_progress',
            response_code integer NULL,
            response_body text NULL,
            response_type varchar(64) NULL,
            entity_id integer NULL,
            created_at timestamp with time zone NOT NULL DEFAULT now(),
            finished_at timestamp with time zone NULL,
            expires_at timestamp with time zone NOT NULL DEFAULT (now() + interval '24 hours')
        );");

        $this->run_query("COMMENT ON TABLE erp_100_api_idempotency IS 'Ключи идемпотентности изменяющих запросов JSON API';");
        $this->run_query("COMMENT ON COLUMN erp_100_api_idempotency.scope IS 'Границы ключа: erpNNN:контроллер:действие';");
        $this->run_query("COMMENT ON COLUMN erp_100_api_idempotency.state IS 'in_progress — операция выполняется, done — ответ сохранён';");
        $this->run_query("COMMENT ON COLUMN erp_100_api_idempotency.request_hash IS 'sha1 тела запроса: тот же ключ с другим телом — конфликт';");

        // Один ключ — один результат. Уникальность базовая (без частичного
        // индекса): повторов ключа быть не должно вообще.
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_100_api_idempotency_key_uniq ON erp_100_api_idempotency (scope, idem_key);");

        // Для уборки просроченных и быстрого поиска по пользователю
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_100_api_idempotency_expires_idx ON erp_100_api_idempotency (expires_at);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_100_api_idempotency_user_idx ON erp_100_api_idempotency (user_id, created_at DESC);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_100_api_idempotency;");
    }
}
