<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_001_Erp227_Core_Registry_CreateTables extends pgMigration
{
    protected $_db_group = 'default';

    public function up()
    {
        $this->run_query("CREATE EXTENSION IF NOT EXISTS ltree;");
        $this->run_query("CREATE EXTENSION IF NOT EXISTS vector;");
        $this->run_query("CREATE EXTENSION IF NOT EXISTS pgcrypto;");

        // 1. Единицы измерения
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_units (
                unit       VARCHAR(10) PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                is_decimal BOOLEAN DEFAULT true
            );
            COMMENT ON TABLE erp_227_units IS 'Справочник единиц измерения';
        ");

        $this->run_query("
            INSERT INTO erp_227_units (unit, name, is_decimal) VALUES
            ('pcs', 'Штука', false),
            ('kg',  'Килограмм', true),
            ('m',   'Метр', true),
            ('h',   'Человеко-час', true),
            ('l',   'Литр', true)
            ON CONFLICT DO NOTHING;
        ");

        // 2. Типы объектов
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_object_types (
                id                BIGSERIAL PRIMARY KEY,
                code              VARCHAR(50) NOT NULL UNIQUE,
                name              VARCHAR(255) NOT NULL,
                schema            JSONB DEFAULT '{}'::jsonb,
                ui_config         JSONB DEFAULT '{}'::jsonb,
                icon              VARCHAR(50),
                created_at        TIMESTAMPTZ DEFAULT now()
            );
            COMMENT ON TABLE erp_227_object_types IS 'Шаблоны объектов (дискриминаторы)';
        ");

        // 2.1 Таблица последовательностей (нумераторы)
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_sequences (
                type_id     BIGINT PRIMARY KEY REFERENCES erp_227_object_types(id) ON DELETE CASCADE,
                prefix      VARCHAR(10) NOT NULL,
                current_val BIGINT DEFAULT 0,
                padding     INTEGER DEFAULT 6
            );
            COMMENT ON TABLE erp_227_sequences IS 'Настройки префиксов и счетчиков для типов объектов';
        ");

        // 3. Главный реестр объектов
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_objects (
                id                  BIGSERIAL PRIMARY KEY,
                external_uuid       UUID DEFAULT gen_random_uuid() UNIQUE,
                type_id             BIGINT NOT NULL REFERENCES erp_227_object_types(id),
                parent_id           BIGINT REFERENCES erp_227_objects(id) ON DELETE SET NULL,

                kind                VARCHAR(20) DEFAULT 'definition',
                code                VARCHAR(100),
                name                VARCHAR(500) NOT NULL,

                revision            INTEGER DEFAULT 1,
                is_latest           BOOLEAN DEFAULT true,
                path                LTREE,

                attributes          JSONB DEFAULT '{}'::jsonb,
                current_state       JSONB DEFAULT '{}'::jsonb,
                
                tags                TEXT[] DEFAULT '{}',
                metadata            JSONB DEFAULT '{}'::jsonb,

                status              VARCHAR(30) DEFAULT 'active',
                created_at          TIMESTAMPTZ DEFAULT now(),
                updated_at          TIMESTAMPTZ DEFAULT now(),
                created_by          BIGINT,
                updated_by          BIGINT,
                is_deleted          BOOLEAN DEFAULT false,
                merged_into_id      BIGINT,

                search_vector TSVECTOR GENERATED ALWAYS AS (
                    setweight(to_tsvector('russian', COALESCE(code, '')), 'A') ||
                    setweight(to_tsvector('russian', COALESCE(name, '')), 'B') ||
                    setweight(to_tsvector('russian', attributes::text), 'C')
                ) STORED,

                CHECK (id <> parent_id)
            );
            COMMENT ON TABLE erp_227_objects IS 'Главный реестр всех объектов системы';
        ");

        $this->run_query("CREATE INDEX idx_obj_type          ON erp_227_objects (type_id);");
        $this->run_query("CREATE INDEX idx_obj_parent        ON erp_227_objects (parent_id);");
        $this->run_query("CREATE UNIQUE INDEX uq_obj_code_active ON erp_227_objects (code) WHERE (is_deleted IS FALSE AND is_latest IS TRUE AND code IS NOT NULL);");
        $this->run_query("CREATE INDEX idx_obj_path_gist     ON erp_227_objects USING GIST (path);");
        $this->run_query("CREATE INDEX idx_obj_attributes_gin ON erp_227_objects USING GIN (attributes);");
        $this->run_query("CREATE INDEX idx_obj_tags_gin       ON erp_227_objects USING GIN (tags);");
        $this->run_query("CREATE INDEX idx_obj_metadata_gin   ON erp_227_objects USING GIN (metadata);");
        $this->run_query("CREATE INDEX idx_obj_search_gin    ON erp_227_objects USING GIN (search_vector);");

        // 4. Связи
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_object_links (
                id                BIGSERIAL PRIMARY KEY,
                source_id         BIGINT NOT NULL REFERENCES erp_227_objects(id),
                target_id         BIGINT NOT NULL REFERENCES erp_227_objects(id),
                link_type         VARCHAR(30) NOT NULL,

                quantity          NUMERIC(18,4) DEFAULT 1,
                unit              VARCHAR(10) REFERENCES erp_227_units(unit),

                sort_order        INTEGER DEFAULT 0,
                data              JSONB DEFAULT '{}'::jsonb,

                created_at        TIMESTAMPTZ DEFAULT now(),
                created_by        BIGINT,
                is_deleted        BOOLEAN DEFAULT false,

                CONSTRAINT uq_link UNIQUE (source_id, target_id, link_type)
            );
        ");
        $this->run_query("CREATE INDEX idx_obj_links_source_id ON erp_227_object_links (source_id);");
        $this->run_query("CREATE INDEX idx_obj_links_target_id ON erp_227_object_links (target_id);");

        // 5. События
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_events (
                id              BIGSERIAL,
                object_id       BIGINT NOT NULL REFERENCES erp_227_objects(id),
                event_type      VARCHAR(50) NOT NULL,
                actor_id        BIGINT,
                payload         JSONB,
                metric_value    NUMERIC(18,4),
                created_at      TIMESTAMPTZ DEFAULT now()
            ) PARTITION BY RANGE (created_at);
        ");
        $this->run_query("CREATE INDEX idx_events_object_id ON erp_227_events (object_id);");
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_227_events_default PARTITION OF erp_227_events DEFAULT;");
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_227_events_y2025 PARTITION OF erp_227_events FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');");
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_227_events_y2026 PARTITION OF erp_227_events FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');");

        // 6. Векторные эмбеддинги
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_227_embeddings (
                id           BIGSERIAL PRIMARY KEY,
                object_id    BIGINT NOT NULL REFERENCES erp_227_objects(id) ON DELETE CASCADE,
                model_name   VARCHAR(100),
                embedding    VECTOR(1024),
                created_at   TIMESTAMPTZ DEFAULT now()
            );
        ");
        $this->run_query("CREATE INDEX idx_embeddings_object_id ON erp_227_embeddings (object_id);");
        $this->run_query("CREATE INDEX idx_embeddings_hnsw ON erp_227_embeddings USING hnsw (embedding vector_cosine_ops);");

        // === Триггеры ===

        $this->run_query("
            CREATE OR REPLACE FUNCTION fn_erp_227_set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = now();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        $this->run_query("
            CREATE TRIGGER trg_erp_227_objects_updated
            BEFORE UPDATE ON erp_227_objects
            FOR EACH ROW EXECUTE FUNCTION fn_erp_227_set_updated_at();
        ");

        $this->run_query("
            CREATE OR REPLACE FUNCTION fn_erp_227_update_path() RETURNS trigger AS $$
            BEGIN
                IF NEW.id IS NULL THEN
                    NEW.id := nextval(pg_get_serial_sequence('erp_227_objects', 'id'));
                END IF;

                IF NEW.parent_id IS NULL THEN
                    NEW.path := NEW.id::text::ltree;
                ELSE
                    NEW.path := (SELECT path FROM erp_227_objects WHERE id = NEW.parent_id) || NEW.id::text::ltree;
                END IF;

                IF (TG_OP = 'UPDATE' AND OLD.path IS DISTINCT FROM NEW.path) THEN
                    UPDATE erp_227_objects 
                    SET path = NEW.path || subpath(path, nlevel(OLD.path))
                    WHERE path <@ OLD.path AND id != OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        $this->run_query("
            CREATE TRIGGER trg_erp_227_path
            BEFORE INSERT OR UPDATE OF parent_id ON erp_227_objects
            FOR EACH ROW EXECUTE FUNCTION fn_erp_227_update_path();
        ");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_227_embeddings CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_events CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_object_links CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_objects CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_sequences CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_object_types CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_227_units CASCADE;");
        $this->run_query("DROP FUNCTION IF EXISTS fn_erp_227_set_updated_at CASCADE;");
        $this->run_query("DROP FUNCTION IF EXISTS fn_erp_227_update_path CASCADE;");
    }
}