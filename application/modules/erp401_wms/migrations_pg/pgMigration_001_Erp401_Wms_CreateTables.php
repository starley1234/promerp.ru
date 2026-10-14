<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: создание схемы складского учёта (WMS), модуль erp401.
 *
 * Соглашения проекта:
 *  - префикс таблиц модуля: erp_401_*
 *  - номенклатура не дублируется: ссылаемся на объекты реестра erp227
 *    (erp_227_objects.id) через поле item_id, физического FK не создаём,
 *    чтобы не связывать схемы модулей (см. docs/ARCHITECTURE.md).
 */
class pgMigration_001_Erp401_Wms_CreateTables extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Склады (точки хранения)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_401_warehouses (
                id           SERIAL PRIMARY KEY,
                code         VARCHAR(32)  NOT NULL,
                name         VARCHAR(255) NOT NULL,
                type         VARCHAR(24)  NOT NULL DEFAULT 'main',
                object_id    INTEGER      DEFAULT NULL,
                is_active    SMALLINT     NOT NULL DEFAULT 1,
                address      VARCHAR(255) DEFAULT NULL,
                description  TEXT         DEFAULT NULL,
                created_by   INTEGER      DEFAULT NULL,
                created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_erp_401_warehouses_code UNIQUE (code),
                CONSTRAINT ck_erp_401_warehouses_type
                    CHECK (type IN ('main', 'transit', 'production', 'scrap', 'quarantine'))
            );
        ");

        $this->run_query("COMMENT ON TABLE  erp_401_warehouses IS 'Склады и точки хранения (модуль erp401_wms)';");
        $this->run_query("COMMENT ON COLUMN erp_401_warehouses.code IS 'Уникальный код склада (WH-MAIN, WH-TRANSIT, ...)';");
        $this->run_query("COMMENT ON COLUMN erp_401_warehouses.type IS 'Тип склада: main|transit|production|scrap|quarantine';");
        $this->run_query("COMMENT ON COLUMN erp_401_warehouses.object_id IS 'Ссылка на объект реестра erp227 (если склад как сущность)';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_warehouses_type    ON erp_401_warehouses (type);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_warehouses_active  ON erp_401_warehouses (is_active);");

        // ---------------------------------------------------------------------
        // 2. Остатки: одна строка на пару (склад, номенклатура)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_401_balances (
                id           SERIAL PRIMARY KEY,
                warehouse_id INTEGER      NOT NULL,
                item_id      INTEGER      NOT NULL,
                uom_code     VARCHAR(16)  NOT NULL DEFAULT 'pcs',
                qty          NUMERIC(18,6) NOT NULL DEFAULT 0,
                reserved     NUMERIC(18,6) NOT NULL DEFAULT 0,
                min_qty      NUMERIC(18,6) NOT NULL DEFAULT 0,
                updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_erp_401_balances_wh_item UNIQUE (warehouse_id, item_id),
                CONSTRAINT ck_erp_401_balances_qty      CHECK (qty >= 0),
                CONSTRAINT ck_erp_401_balances_reserved CHECK (reserved >= 0),
                CONSTRAINT fk_erp_401_balances_warehouse
                    FOREIGN KEY (warehouse_id) REFERENCES erp_401_warehouses (id) ON DELETE CASCADE
            );
        ");

        $this->run_query("COMMENT ON TABLE  erp_401_balances IS 'Остатки номенклатуры по складам (модуль erp401_wms)';");
        $this->run_query("COMMENT ON COLUMN erp_401_balances.item_id IS 'ID объекта реестра erp227 (номенклатура)';");
        $this->run_query("COMMENT ON COLUMN erp_401_balances.uom_code IS 'Код единицы измерения из реестра erp227 (erp_227_units.unit: pcs|kg|m|...)';");
        $this->run_query("COMMENT ON COLUMN erp_401_balances.qty IS 'Физический остаток на складе';");
        $this->run_query("COMMENT ON COLUMN erp_401_balances.reserved IS 'Зарезервировано под заказы/производство';");
        $this->run_query("COMMENT ON COLUMN erp_401_balances.min_qty IS 'Неснижаемый минимум: при qty <= min_qty выдаётся событие erp401.stock_low';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_balances_item      ON erp_401_balances (item_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_balances_warehouse ON erp_401_balances (warehouse_id);");

        // ---------------------------------------------------------------------
        // 3. Журнал движений (приход / расход / перемещение / корректировка)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_401_movements (
                id              SERIAL PRIMARY KEY,
                type            VARCHAR(16)  NOT NULL,
                item_id         INTEGER      NOT NULL,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'pcs',
                warehouse_id    INTEGER      NOT NULL,
                warehouse_to_id INTEGER      DEFAULT NULL,
                qty             NUMERIC(18,6) NOT NULL,
                balance_before  NUMERIC(18,6) NOT NULL DEFAULT 0,
                balance_after   NUMERIC(18,6) NOT NULL DEFAULT 0,
                status          VARCHAR(16)  NOT NULL DEFAULT 'posted',
                doc_type        VARCHAR(32)  DEFAULT NULL,
                doc_id          INTEGER      DEFAULT NULL,
                note            TEXT         DEFAULT NULL,
                performed_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by      INTEGER      DEFAULT NULL,
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT ck_erp_401_movements_type
                    CHECK (type IN ('receipt', 'issue', 'transfer', 'adjust')),
                CONSTRAINT ck_erp_401_movements_status
                    CHECK (status IN ('draft', 'posted', 'void')),
                CONSTRAINT ck_erp_401_movements_qty CHECK (qty > 0),
                CONSTRAINT fk_erp_401_movements_warehouse
                    FOREIGN KEY (warehouse_id) REFERENCES erp_401_warehouses (id) ON DELETE CASCADE,
                CONSTRAINT fk_erp_401_movements_warehouse_to
                    FOREIGN KEY (warehouse_to_id) REFERENCES erp_401_warehouses (id) ON DELETE SET NULL
            );
        ");

        $this->run_query("COMMENT ON TABLE  erp_401_movements IS 'Журнал складских движений (модуль erp401_wms)';");
        $this->run_query("COMMENT ON COLUMN erp_401_movements.type IS 'Тип движения: receipt|issue|transfer|adjust';");
        $this->run_query("COMMENT ON COLUMN erp_401_movements.balance_before IS 'Остаток на складе-источнике до движения';");
        $this->run_query("COMMENT ON COLUMN erp_401_movements.balance_after IS 'Остаток на складе-источнике после движения';");
        $this->run_query("COMMENT ON COLUMN erp_401_movements.status IS 'draft|posted|void (сторно делается отдельной записью)';");
        $this->run_query("COMMENT ON COLUMN erp_401_movements.doc_type IS 'Тип документа-основания (order, production, manual, ...)';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_movements_item   ON erp_401_movements (item_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_movements_wh     ON erp_401_movements (warehouse_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_movements_date   ON erp_401_movements (performed_at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_movements_doc    ON erp_401_movements (doc_type, doc_id);");

        // ---------------------------------------------------------------------
        // 4. Резервы под заказы / производственные задания
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_401_reservations (
                id           SERIAL PRIMARY KEY,
                warehouse_id INTEGER      NOT NULL,
                item_id      INTEGER      NOT NULL,
                qty          NUMERIC(18,6) NOT NULL,
                status       VARCHAR(16)  NOT NULL DEFAULT 'active',
                ref_type     VARCHAR(32)  DEFAULT NULL,
                ref_id       INTEGER      DEFAULT NULL,
                expires_at   TIMESTAMP    DEFAULT NULL,
                created_by   INTEGER      DEFAULT NULL,
                created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                released_at  TIMESTAMP    DEFAULT NULL,
                CONSTRAINT ck_erp_401_reservations_status
                    CHECK (status IN ('active', 'released', 'consumed')),
                CONSTRAINT ck_erp_401_reservations_qty CHECK (qty > 0),
                CONSTRAINT fk_erp_401_reservations_warehouse
                    FOREIGN KEY (warehouse_id) REFERENCES erp_401_warehouses (id) ON DELETE CASCADE
            );
        ");

        $this->run_query("COMMENT ON TABLE  erp_401_reservations IS 'Резервы номенклатуры под заказы и производство (erp401_wms)';");
        $this->run_query("COMMENT ON COLUMN erp_401_reservations.ref_type IS 'Тип документа-потребителя резерва (order, production, ...)';");
        $this->run_query("COMMENT ON COLUMN erp_401_reservations.status IS 'active|released|consumed';");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_reservations_item ON erp_401_reservations (item_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_reservations_ref  ON erp_401_reservations (ref_type, ref_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_erp_401_reservations_st   ON erp_401_reservations (status);");

        // ---------------------------------------------------------------------
        // 5. Роли модуля
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO roles (name, description) VALUES
            ('401_stock_manager', 'Управление складом: приход, расход, перемещения, корректировки'),
            ('401_stock_viewer',  'Просмотр остатков и журнала движений склада')
            ON CONFLICT (name) DO NOTHING;
        ");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_401_reservations;");
        $this->run_query("DROP TABLE IF EXISTS erp_401_movements;");
        $this->run_query("DROP TABLE IF EXISTS erp_401_balances;");
        $this->run_query("DROP TABLE IF EXISTS erp_401_warehouses;");
        $this->run_query("DELETE FROM roles WHERE name IN ('401_stock_manager', '401_stock_viewer');");
    }
}
