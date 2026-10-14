<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: MCP-сервер и агентский доступ (erp804_mcp).
 *
 * Две таблицы решают две разные задачи:
 *
 *  1. `erp_804_mcp_keys` — доступ. Ключ — это не «пароль пользователя», а
 *     отдельная учётная запись агента: имя клиента (LLM-агент, коннектор,
 *     воркер), набор прав (`scopes`), запрет на изменения (`read_only`),
 *     срок жизни и счётчик вызовов. В базе лежит только SHA-256 от токена —
 *     утечка дампа не даёт доступа.
 *  2. `erp_804_mcp_calls` — журнал вызовов инструментов: кто (key_id),
 *     что (tool_name), с какими аргументами, что получилось, сколько заняло
 *     времени и требовалось ли подтверждение человека. Именно здесь видно
 *     «что сделал агент», независимо от того, попал вызов в журнал `erp119`
 *     (туда пишутся только изменяющие операции).
 *
 * Подтверждение человеком (`approval_required`) хранится статусом записи:
 * вызов остаётся `awaiting_approval`, пока его не одобрят или не отклонят.
 *
 * `idempotency_key` защищает от повторного выполнения: LLM-клиенты любят
 * повторить запрос после таймаута, и второй раз заказ создаваться не должен.
 *
 * Миграция идемпотентна (IF NOT EXISTS).
 */
class pgMigration_001_Erp804_Mcp_CreateTables extends pgMigration
{
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_804_mcp_keys (
                id            SERIAL PRIMARY KEY,
                uuid          UUID         NOT NULL DEFAULT gen_random_uuid(),
                name          VARCHAR(128) NOT NULL,
                token_hash    VARCHAR(64)  NOT NULL,
                token_prefix  VARCHAR(16)  NOT NULL,
                client        VARCHAR(64)  NULL,
                actor_id      INTEGER      NULL,
                scopes        TEXT[]       NOT NULL DEFAULT ARRAY[]::TEXT[],
                read_only     BOOLEAN      NOT NULL DEFAULT FALSE,
                enabled       BOOLEAN      NOT NULL DEFAULT TRUE,
                calls_count   INTEGER      NOT NULL DEFAULT 0,
                last_used_at  TIMESTAMPTZ  NULL,
                expires_at    TIMESTAMPTZ  NULL,
                note          TEXT         NULL,
                created_by    INTEGER      NULL,
                created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at    TIMESTAMPTZ  NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_804_mcp_keys_hash_uniq ON erp_804_mcp_keys (token_hash);");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_804_mcp_keys_uuid_uniq ON erp_804_mcp_keys (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_804_mcp_keys_enabled_idx ON erp_804_mcp_keys (enabled, expires_at);");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_804_mcp_calls (
                id                BIGSERIAL PRIMARY KEY,
                uuid              UUID         NOT NULL DEFAULT gen_random_uuid(),
                key_id            INTEGER      NULL REFERENCES erp_804_mcp_keys(id) ON DELETE SET NULL,
                key_name          VARCHAR(128) NULL,
                tool_name         VARCHAR(128) NOT NULL,
                arguments         JSONB        NOT NULL DEFAULT '{}'::jsonb,
                status            VARCHAR(24)  NOT NULL DEFAULT 'pending'
                                  CHECK (status IN ('pending', 'ok', 'error', 'awaiting_approval', 'approved', 'rejected')),
                mutating          BOOLEAN      NOT NULL DEFAULT FALSE,
                result            JSONB        NULL,
                error             TEXT         NULL,
                reason            VARCHAR(64)  NULL,
                duration_ms       INTEGER      NULL,
                approved_by       INTEGER      NULL,
                approved_at       TIMESTAMPTZ  NULL,
                rejection_reason  TEXT         NULL,
                idempotency_key   VARCHAR(128) NULL,
                ip                VARCHAR(64)  NULL,
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT now(),
                finished_at       TIMESTAMPTZ  NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_804_mcp_calls_idem_uniq ON erp_804_mcp_calls (idempotency_key) WHERE idempotency_key IS NOT NULL;");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_804_mcp_calls_uuid_uniq ON erp_804_mcp_calls (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_804_mcp_calls_created_idx ON erp_804_mcp_calls (created_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_804_mcp_calls_tool_idx ON erp_804_mcp_calls (tool_name, created_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_804_mcp_calls_status_idx ON erp_804_mcp_calls (status, created_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_804_mcp_calls_key_idx ON erp_804_mcp_calls (key_id, created_at DESC);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_804_mcp_calls CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_804_mcp_keys CASCADE;");
    }
}
