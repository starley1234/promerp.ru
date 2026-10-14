<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Отгрузка и продажи (erp410_shipment): таблицы.
 *
 * Модуль закрывает конец производственного контура — путь от заказа клиента до
 * счёта:
 *
 *   erp_410_orders           заказ клиента: что и когда покупатель просит;
 *   erp_410_order_lines      позиции заказа: количество, цена, отгрузка, резерв;
 *   erp_410_shipments        отгрузка: чем и откуда уехало;
 *   erp_410_shipment_lines   позиции отгрузки (сборка под заказ);
 *   erp_410_invoices         счёт: основание для денег;
 *   erp_410_invoice_lines    позиции счёта;
 *   erp_410_prices           история цен продажи: база и цены клиентов;
 *   erp_410_customers        условия клиента: отсрочка, лимит, скидка, адрес.
 *
 * Клиенты — контрагенты с ролью «покупатель» (erp301), склад — erp401,
 * номенклатура — реестр erp227. Ссылки на чужие модули мягкие, без внешних
 * ключей: модули включаются и выключаются независимо, а документы продажи
 * остаются читаемыми.
 *
 * Флаги — SMALLINT 0/1, а не BOOLEAN: PostgreSQL отдаёт boolean строками
 * `t`/`f`, и `(bool) 'f'` — это TRUE.
 *
 * @package    Erp410
 * @category   Migrations
 */
class pgMigration_001_Erp410_Shipment_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_orders();
        $this->_create_shipments();
        $this->_create_invoices();
        $this->_create_prices();
        $this->_create_customers();
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_410_invoice_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_invoices CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_shipment_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_shipments CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_order_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_orders CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_prices CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_410_customers CASCADE');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_410_order_number_seq');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_410_shipment_number_seq');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_410_invoice_number_seq');
    }

    /**
     * Заказы клиента и их позиции: что покупатель просит.
     *
     * @return  void
     */
    protected function _create_orders()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_orders (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                customer_id     INTEGER     NULL,
                contact_id      INTEGER     NULL,
                contract_id     INTEGER     NULL,
                warehouse_id    INTEGER     NULL,
                status          VARCHAR(24) NOT NULL DEFAULT 'draft',
                source          VARCHAR(16) NOT NULL DEFAULT 'manual',
                source_ref      VARCHAR(64) NULL,
                currency        VARCHAR(8)  NOT NULL DEFAULT 'RUB',
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                order_date      DATE        NULL,
                ship_date       DATE        NULL,
                delivery_address TEXT       NULL,
                payment_terms   VARCHAR(64) NULL,
                note            TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                confirmed_by    INTEGER     NULL,
                confirmed_at    TIMESTAMP WITH TIME ZONE NULL,
                closed_at       TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_orders_number_key UNIQUE (number),
                CONSTRAINT erp_410_orders_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_410_orders_status_check CHECK (status IN ('draft', 'confirmed', 'reserved', 'picking', 'shipped', 'closed', 'cancelled')),
                CONSTRAINT erp_410_orders_source_check CHECK (source IN ('manual', 'erp221', 'import')),
                CONSTRAINT erp_410_orders_amount_check CHECK (amount >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_orders_customer ON erp_410_orders (customer_id, status)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_orders_status ON erp_410_orders (status, ship_date)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_orders_source ON erp_410_orders (source, source_ref)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_order_lines (
                id              SERIAL PRIMARY KEY,
                order_id        INTEGER      NOT NULL REFERENCES erp_410_orders (id) ON DELETE CASCADE,
                line_no         INTEGER      NOT NULL DEFAULT 10,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                qty_reserved    NUMERIC(18,6) NOT NULL DEFAULT 0,
                qty_shipped     NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                discount        NUMERIC(5,2) NOT NULL DEFAULT 0,
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                status          VARCHAR(16)  NOT NULL DEFAULT 'open',
                reservation_id  INTEGER      NULL,
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_order_lines_qty_check CHECK (qty >= 0),
                CONSTRAINT erp_410_order_lines_shipped_check CHECK (qty_shipped >= 0),
                CONSTRAINT erp_410_order_lines_discount_check CHECK (discount >= 0 AND discount <= 100),
                CONSTRAINT erp_410_order_lines_status_check CHECK (status IN ('open', 'reserved', 'shipped', 'cancelled'))
            )
        ");

        // Резерв строки живёт в erp401 (`erp_401_reservations`) — модуль продаж
        // только помнит его идентификатор, чтобы списать со склада именно то,
        // что под эту позицию заморожено.

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_order_lines_order ON erp_410_order_lines (order_id, line_no)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_order_lines_item ON erp_410_order_lines (item_id, status)");

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_410_order_number_seq START 1");
    }

    /**
     * Отгрузки и их позиции: чем и откуда уехало.
     *
     * @return  void
     */
    protected function _create_shipments()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_shipments (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                order_id        INTEGER     NULL REFERENCES erp_410_orders (id) ON DELETE SET NULL,
                customer_id     INTEGER     NULL,
                warehouse_id    INTEGER     NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'draft',
                shipment_date   DATE        NULL,
                carrier         VARCHAR(128) NULL,
                transport_number VARCHAR(64) NULL,
                delivery_address TEXT       NULL,
                comment         TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                posted_by       INTEGER     NULL,
                posted_at       TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_shipments_number_key UNIQUE (number),
                CONSTRAINT erp_410_shipments_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_410_shipments_status_check CHECK (status IN ('draft', 'ready', 'shipped', 'cancelled'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_shipments_order ON erp_410_shipments (order_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_shipments_status ON erp_410_shipments (status, shipment_date)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_shipment_lines (
                id              SERIAL PRIMARY KEY,
                shipment_id     INTEGER      NOT NULL REFERENCES erp_410_shipments (id) ON DELETE CASCADE,
                order_line_id   INTEGER      NULL,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_shipment_lines_qty_check CHECK (qty >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_shipment_lines_shipment ON erp_410_shipment_lines (shipment_id)");

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_410_shipment_number_seq START 1");
    }

    /**
     * Счета и их позиции: основание для денег.
     *
     * @return  void
     */
    protected function _create_invoices()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_invoices (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                order_id        INTEGER     NULL REFERENCES erp_410_orders (id) ON DELETE SET NULL,
                shipment_id     INTEGER     NULL REFERENCES erp_410_shipments (id) ON DELETE SET NULL,
                customer_id     INTEGER     NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'draft',
                issue_date      DATE        NULL,
                due_date        DATE        NULL,
                currency        VARCHAR(8)  NOT NULL DEFAULT 'RUB',
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                paid_amount     NUMERIC(18,2) NOT NULL DEFAULT 0,
                note            TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                issued_by       INTEGER     NULL,
                issued_at       TIMESTAMP WITH TIME ZONE NULL,
                paid_at         TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_invoices_number_key UNIQUE (number),
                CONSTRAINT erp_410_invoices_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_410_invoices_status_check CHECK (status IN ('draft', 'issued', 'paid', 'cancelled')),
                CONSTRAINT erp_410_invoices_amount_check CHECK (amount >= 0),
                CONSTRAINT erp_410_invoices_paid_check CHECK (paid_amount >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_invoices_customer ON erp_410_invoices (customer_id, status)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_invoices_due ON erp_410_invoices (status, due_date)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_invoices_order ON erp_410_invoices (order_id)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_invoice_lines (
                id              SERIAL PRIMARY KEY,
                invoice_id      INTEGER      NOT NULL REFERENCES erp_410_invoices (id) ON DELETE CASCADE,
                order_line_id   INTEGER      NULL,
                shipment_line_id INTEGER     NULL,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_invoice_lines_qty_check CHECK (qty >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_invoice_lines_invoice ON erp_410_invoice_lines (invoice_id)");

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_410_invoice_number_seq START 1");
    }

    /**
     * История цен продажи: база (customer_id пуст) и цены конкретных клиентов.
     *
     * @return  void
     */
    protected function _create_prices()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_prices (
                id              SERIAL PRIMARY KEY,
                item_id         INTEGER      NOT NULL,
                customer_id     INTEGER      NULL,
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                currency        VARCHAR(8)   NOT NULL DEFAULT 'RUB',
                discount        NUMERIC(5,2) NOT NULL DEFAULT 0,
                source          VARCHAR(16)  NOT NULL DEFAULT 'manual',
                source_ref      VARCHAR(64)  NULL,
                valid_from      DATE         NULL,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_prices_price_check CHECK (price >= 0),
                CONSTRAINT erp_410_prices_discount_check CHECK (discount >= 0 AND discount <= 100),
                CONSTRAINT erp_410_prices_source_check CHECK (source IN ('manual', 'order', 'shipment', 'invoice', 'import'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_prices_item ON erp_410_prices (item_id, customer_id, created_at DESC)");
    }

    /**
     * Условия клиента: отсрочка, лимит, скидка, адрес доставки.
     *
     * @return  void
     */
    protected function _create_customers()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_410_customers (
                id              SERIAL PRIMARY KEY,
                customer_id     INTEGER      NOT NULL,
                payment_terms   VARCHAR(64)  NULL,
                credit_days     INTEGER      NOT NULL DEFAULT 0,
                credit_limit    NUMERIC(18,2) NOT NULL DEFAULT 0,
                discount        NUMERIC(5,2) NOT NULL DEFAULT 0,
                manager_id      INTEGER      NULL,
                delivery_address TEXT        NULL,
                terms           TEXT         NULL,
                is_active       SMALLINT     NOT NULL DEFAULT 1,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_410_customers_customer_key UNIQUE (customer_id),
                CONSTRAINT erp_410_customers_credit_check CHECK (credit_days >= 0 AND credit_limit >= 0),
                CONSTRAINT erp_410_customers_discount_check CHECK (discount >= 0 AND discount <= 100)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_410_customers_active ON erp_410_customers (is_active)");
    }
}
