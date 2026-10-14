<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp231_ProductStructure_CreateTables extends pgMigration
{
    protected $_db_group = 'default';

    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_231_structures (
                id BIGSERIAL PRIMARY KEY,
                uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
                product_item_id BIGINT NOT NULL REFERENCES erp_227_objects(id),
                kind VARCHAR(16) NOT NULL CHECK (kind IN ('ebom','mbom','service')),
                revision VARCHAR(32) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','review','approved','obsolete')),
                site_code VARCHAR(64) NOT NULL DEFAULT '',
                source_system VARCHAR(64), source_revision VARCHAR(64),
                tech_card_id BIGINT NULL REFERENCES erp_228_tech_cards(id) ON DELETE SET NULL,
                effective_from DATE, effective_to DATE,
                approved_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                approved_at TIMESTAMP(0), created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_231_structure_revision_uq UNIQUE(product_item_id, kind, revision, site_code),
                CONSTRAINT erp_231_structure_dates CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS erp_231_one_approved_idx
                ON erp_231_structures(product_item_id, kind, site_code)
                WHERE status='approved';
            CREATE INDEX IF NOT EXISTS erp_231_structure_product_idx ON erp_231_structures(product_item_id, kind, status);

            CREATE TABLE IF NOT EXISTS erp_231_lines (
                id BIGSERIAL PRIMARY KEY,
                structure_id BIGINT NOT NULL REFERENCES erp_231_structures(id) ON DELETE CASCADE,
                line_no INTEGER NOT NULL CHECK (line_no > 0),
                parent_line_id BIGINT NULL REFERENCES erp_231_lines(id) ON DELETE RESTRICT,
                component_item_id BIGINT NOT NULL REFERENCES erp_227_objects(id),
                qty NUMERIC(20,6) NOT NULL CHECK (qty > 0),
                uom VARCHAR(10) NOT NULL REFERENCES erp_227_units(unit),
                scrap_pct NUMERIC(7,4) NOT NULL DEFAULT 0 CHECK (scrap_pct >= 0 AND scrap_pct < 100),
                supply_type VARCHAR(16) NOT NULL DEFAULT 'buy' CHECK (supply_type IN ('make','buy','phantom','consumable')),
                operation_no INTEGER NULL, effectivity JSONB NOT NULL DEFAULT '{}'::jsonb,
                alternatives JSONB NOT NULL DEFAULT '[]'::jsonb, note TEXT,
                created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_231_line_no_uq UNIQUE(structure_id, line_no)
            );
            CREATE INDEX IF NOT EXISTS erp_231_line_component_idx ON erp_231_lines(component_item_id);

            CREATE TABLE IF NOT EXISTS erp_231_mappings (
                id BIGSERIAL PRIMARY KEY,
                mbom_structure_id BIGINT NOT NULL REFERENCES erp_231_structures(id) ON DELETE CASCADE,
                ebom_line_id BIGINT NOT NULL REFERENCES erp_231_lines(id) ON DELETE CASCADE,
                mbom_line_id BIGINT NULL REFERENCES erp_231_lines(id) ON DELETE CASCADE,
                mapping_kind VARCHAR(16) NOT NULL CHECK (mapping_kind IN ('same','split','merge','excluded','added')),
                reason TEXT, created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_231_mapping_uq UNIQUE(ebom_line_id, mbom_line_id, mapping_kind)
            );

            CREATE TABLE IF NOT EXISTS erp_231_changes (
                id BIGSERIAL PRIMARY KEY, uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
                product_item_id BIGINT NOT NULL REFERENCES erp_227_objects(id),
                from_structure_id BIGINT NULL REFERENCES erp_231_structures(id),
                to_structure_id BIGINT NOT NULL REFERENCES erp_231_structures(id),
                kind VARCHAR(8) NOT NULL CHECK (kind IN ('ECR','ECO')),
                status VARCHAR(16) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','review','approved','rejected','implemented')),
                reason TEXT NOT NULL, impact JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                approved_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at TIMESTAMP(0)
            );

            CREATE OR REPLACE FUNCTION erp_231_guard_approved() RETURNS trigger AS $$
            DECLARE sid BIGINT; locked BOOLEAN;
            BEGIN
                sid := CASE WHEN TG_OP='DELETE' THEN OLD.structure_id ELSE NEW.structure_id END;
                SELECT status='approved' INTO locked FROM erp_231_structures WHERE id=sid;
                IF locked THEN RAISE EXCEPTION 'Approved product structure % is immutable', sid; END IF;
                RETURN CASE WHEN TG_OP='DELETE' THEN OLD ELSE NEW END;
            END; $$ LANGUAGE plpgsql;
            DROP TRIGGER IF EXISTS erp_231_lines_immutable ON erp_231_lines;
            CREATE TRIGGER erp_231_lines_immutable BEFORE INSERT OR UPDATE OR DELETE ON erp_231_lines
                FOR EACH ROW EXECUTE FUNCTION erp_231_guard_approved();
        ");
    }

    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_231_changes, erp_231_mappings, erp_231_lines, erp_231_structures CASCADE; DROP FUNCTION IF EXISTS erp_231_guard_approved();');
    }
}
