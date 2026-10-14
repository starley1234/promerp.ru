<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: справочник контрагентов, контакты, реквизиты и договоры (erp301_counterparty).
 *
 * Модуль владеет только «коммерческой» стороной: кто нам что поставляет и кто у нас
 * покупает. Номенклатура и цены живут в других модулях, файлы — в erp212_file_storage.
 *
 * Миграция идемпотентна: CREATE TABLE IF NOT EXISTS + ON CONFLICT DO NOTHING.
 */
class pgMigration_001_Erp301_Counterparty_CreateTables extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Контрагенты
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_301_counterparties (
                id          SERIAL PRIMARY KEY,
                code        VARCHAR(32)  NOT NULL,
                name        VARCHAR(255) NOT NULL,
                full_name   VARCHAR(512) NULL,
                kind        VARCHAR(20)  NOT NULL DEFAULT 'legal'
                            CHECK (kind IN ('legal', 'individual', 'state', 'foreign')),
                roles       JSONB        NOT NULL DEFAULT '[]'::jsonb,
                inn         VARCHAR(12)  NULL,
                kpp         VARCHAR(9)   NULL,
                ogrn        VARCHAR(15)  NULL,
                country     VARCHAR(64)  NULL DEFAULT 'RU',
                city        VARCHAR(128) NULL,
                address     TEXT         NULL,
                phone       VARCHAR(64)  NULL,
                email       VARCHAR(128) NULL,
                site        VARCHAR(255) NULL,
                status      VARCHAR(20)  NOT NULL DEFAULT 'active'
                            CHECK (status IN ('active', 'blocked', 'archived')),
                rating      SMALLINT     NOT NULL DEFAULT 0 CHECK (rating BETWEEN 0 AND 5),
                note        TEXT         NULL,
                metadata    JSONB        NOT NULL DEFAULT '{}'::jsonb,
                is_deleted  SMALLINT     NOT NULL DEFAULT 0,
                created_by  INTEGER      NULL,
                created_at  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_301_counterparties_code_uniq ON erp_301_counterparties (code);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_counterparties_name_idx ON erp_301_counterparties (lower(name));");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_counterparties_inn_idx ON erp_301_counterparties (inn);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_counterparties_status_idx ON erp_301_counterparties (status, is_deleted);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_counterparties_roles_gin ON erp_301_counterparties USING GIN (roles);");

        // ---------------------------------------------------------------------
        // 2. Контактные лица
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_301_contacts (
                id              SERIAL PRIMARY KEY,
                counterparty_id INTEGER      NOT NULL REFERENCES erp_301_counterparties (id) ON DELETE CASCADE,
                name            VARCHAR(255) NOT NULL,
                position        VARCHAR(128) NULL,
                phone           VARCHAR(64)  NULL,
                email           VARCHAR(128) NULL,
                is_primary      SMALLINT     NOT NULL DEFAULT 0,
                note            TEXT         NULL,
                is_deleted      SMALLINT     NOT NULL DEFAULT 0,
                created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_contacts_counterparty_idx ON erp_301_contacts (counterparty_id, is_deleted);");

        // ---------------------------------------------------------------------
        // 3. Реквизиты и банковские счета
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_301_requisites (
                id              SERIAL PRIMARY KEY,
                counterparty_id INTEGER      NOT NULL REFERENCES erp_301_counterparties (id) ON DELETE CASCADE,
                kind            VARCHAR(20)  NOT NULL DEFAULT 'bank'
                                CHECK (kind IN ('bank', 'legal', 'postal')),
                title           VARCHAR(255) NULL,
                bank_name       VARCHAR(255) NULL,
                bik             VARCHAR(20)  NULL,
                account         VARCHAR(34)  NULL,
                corr_account    VARCHAR(34)  NULL,
                currency        VARCHAR(3)   NOT NULL DEFAULT 'RUB',
                address         TEXT         NULL,
                is_primary      SMALLINT     NOT NULL DEFAULT 0,
                is_deleted      SMALLINT     NOT NULL DEFAULT 0,
                created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_requisites_counterparty_idx ON erp_301_requisites (counterparty_id, is_deleted);");

        // ---------------------------------------------------------------------
        // 4. Договоры (со ссылкой на скан в erp212_file_storage)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_301_contracts (
                id              SERIAL PRIMARY KEY,
                counterparty_id INTEGER      NOT NULL REFERENCES erp_301_counterparties (id) ON DELETE CASCADE,
                number          VARCHAR(64)  NOT NULL,
                title           VARCHAR(255) NULL,
                kind            VARCHAR(20)  NOT NULL DEFAULT 'supply'
                                CHECK (kind IN ('supply', 'service', 'work', 'lease', 'other')),
                signed_at       DATE         NULL,
                valid_from      DATE         NULL,
                valid_to        DATE         NULL,
                amount          NUMERIC(18,2) NULL,
                currency        VARCHAR(3)   NOT NULL DEFAULT 'RUB',
                payment_terms   VARCHAR(255) NULL,
                status          VARCHAR(20)  NOT NULL DEFAULT 'draft'
                                CHECK (status IN ('draft', 'active', 'suspended', 'expired', 'terminated')),
                file_id         INTEGER      NULL REFERENCES erp_212_files (id) ON DELETE SET NULL,
                note            TEXT         NULL,
                is_deleted      SMALLINT     NOT NULL DEFAULT 0,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_301_contracts_number_uniq ON erp_301_contracts (counterparty_id, number);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_contracts_valid_idx ON erp_301_contracts (valid_to, status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_301_contracts_counterparty_idx ON erp_301_contracts (counterparty_id, is_deleted);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_301_contracts CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_301_requisites CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_301_contacts CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_301_counterparties CASCADE;");
    }
}
