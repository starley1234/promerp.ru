<?php defined('SYSPATH') or die('No direct script access.');

/** Durable transport queue for integrations and background agents. */
class pgMigration_003_Erp208_Outbox extends pgMigration
{
    protected $_db_group = 'default';
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_208_outbox (
                id BIGSERIAL PRIMARY KEY,
                uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
                event_name VARCHAR(255) NOT NULL,
                aggregate_type VARCHAR(64), aggregate_id VARCHAR(128),
                payload JSONB NOT NULL DEFAULT '{}'::jsonb,
                headers JSONB NOT NULL DEFAULT '{}'::jsonb,
                idempotency_key VARCHAR(190) NOT NULL UNIQUE,
                correlation_id UUID NOT NULL DEFAULT gen_random_uuid(),
                status VARCHAR(16) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','processing','delivered','dead')),
                attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0),
                max_attempts INTEGER NOT NULL DEFAULT 8 CHECK (max_attempts BETWEEN 1 AND 100),
                available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                lease_until TIMESTAMP, leased_by VARCHAR(128),
                last_error TEXT, delivered_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS erp_208_outbox_ready_idx
                ON erp_208_outbox(available_at,id) WHERE status='pending';
            CREATE INDEX IF NOT EXISTS erp_208_outbox_lease_idx
                ON erp_208_outbox(lease_until) WHERE status='processing';
            CREATE INDEX IF NOT EXISTS erp_208_outbox_correlation_idx
                ON erp_208_outbox(correlation_id);

            CREATE TABLE IF NOT EXISTS erp_208_consumer_receipts (
                id BIGSERIAL PRIMARY KEY,
                consumer VARCHAR(128) NOT NULL,
                idempotency_key VARCHAR(190) NOT NULL,
                outbox_id BIGINT NULL REFERENCES erp_208_outbox(id) ON DELETE SET NULL,
                result_hash VARCHAR(64), processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_208_consumer_receipt_uq UNIQUE(consumer,idempotency_key)
            );
        ");
    }
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_208_consumer_receipts, erp_208_outbox CASCADE;');
    }
}
