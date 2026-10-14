<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp802_Integration_CreateTables extends pgMigration
{
    protected $_db_group='default';
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_802_sources (
                id BIGSERIAL PRIMARY KEY, uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
                code VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL,
                kind VARCHAR(16) NOT NULL CHECK(kind IN ('file','one_c','http')),
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                config JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            INSERT INTO erp_802_sources(code,name,kind) VALUES
                ('file','Файловый обмен CSV/JSON','file'),
                ('1c','1С: HTTP/файловый обмен','one_c')
            ON CONFLICT(code) DO NOTHING;

            CREATE TABLE IF NOT EXISTS erp_802_packages (
                id BIGSERIAL PRIMARY KEY, uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
                source_id BIGINT NOT NULL REFERENCES erp_802_sources(id),
                direction VARCHAR(8) NOT NULL CHECK(direction IN ('in','out')),
                entity_kind VARCHAR(32) NOT NULL,
                format VARCHAR(8) NOT NULL CHECK(format IN ('json','csv')),
                checksum VARCHAR(64) NOT NULL, external_batch_id VARCHAR(128),
                correlation_id UUID NOT NULL DEFAULT gen_random_uuid(),
                status VARCHAR(16) NOT NULL DEFAULT 'received'
                    CHECK(status IN ('received','validating','processing','completed','partial','failed')),
                dry_run BOOLEAN NOT NULL DEFAULT FALSE,
                payload_size BIGINT NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0, applied_rows INTEGER NOT NULL DEFAULT 0,
                skipped_rows INTEGER NOT NULL DEFAULT 0, error_rows INTEGER NOT NULL DEFAULT 0,
                error_summary TEXT, created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                started_at TIMESTAMP, finished_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_802_package_checksum_uq UNIQUE(source_id,direction,entity_kind,checksum,dry_run)
            );
            CREATE INDEX IF NOT EXISTS erp_802_package_status_idx ON erp_802_packages(status,created_at DESC);

            CREATE TABLE IF NOT EXISTS erp_802_package_rows (
                id BIGSERIAL PRIMARY KEY, package_id BIGINT NOT NULL REFERENCES erp_802_packages(id) ON DELETE CASCADE,
                row_no INTEGER NOT NULL, external_id VARCHAR(190) NOT NULL,
                operation VARCHAR(16) NOT NULL DEFAULT 'upsert',
                checksum VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending'
                    CHECK(status IN ('pending','planned','applied','skipped','error')),
                reason_code VARCHAR(64), message TEXT,
                raw_data JSONB NOT NULL, result_data JSONB,
                internal_id BIGINT, internal_uuid UUID,
                processed_at TIMESTAMP,
                CONSTRAINT erp_802_package_row_uq UNIQUE(package_id,row_no)
            );
            CREATE INDEX IF NOT EXISTS erp_802_row_status_idx ON erp_802_package_rows(package_id,status,row_no);

            CREATE TABLE IF NOT EXISTS erp_802_mappings (
                id BIGSERIAL PRIMARY KEY, source_id BIGINT NOT NULL REFERENCES erp_802_sources(id) ON DELETE CASCADE,
                entity_kind VARCHAR(32) NOT NULL, external_id VARCHAR(190) NOT NULL,
                internal_id BIGINT, internal_uuid UUID NOT NULL,
                external_version VARCHAR(128), content_hash VARCHAR(64) NOT NULL,
                last_package_id BIGINT NULL REFERENCES erp_802_packages(id) ON DELETE SET NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_802_mapping_external_uq UNIQUE(source_id,entity_kind,external_id),
                CONSTRAINT erp_802_mapping_internal_uq UNIQUE(source_id,entity_kind,internal_uuid)
            );
        ");
    }
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_802_mappings,erp_802_package_rows,erp_802_packages,erp_802_sources CASCADE;');
    }
}
