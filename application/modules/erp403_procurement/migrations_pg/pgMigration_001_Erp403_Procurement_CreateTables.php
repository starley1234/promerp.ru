<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Закупки (erp403_procurement): таблицы.
 *
 * Восемь таблиц, которые повторяют путь закупки от потребности до склада:
 *
 *   erp_403_requests        заявка на закупку: что нужно купить и к какому сроку;
 *   erp_403_request_lines   позиции заявки;
 *   erp_403_orders          заказ поставщику: что и у кого куплено;
 *   erp_403_order_lines     позиции заказа: количество, цена, срок поставки;
 *   erp_403_receipts        приёмка: документ поставщика и склад-получатель;
 *   erp_403_receipt_lines   позиции приёмки (с зацепкой входного контроля);
 *   erp_403_prices          история цен поставщиков (из заказов и приёмок);
 *   erp_403_suppliers       закупочные условия поставщика: срок, валюта,
 *                           минимальная партия, отсрочка, оценка.
 *
 * Ссылки на чужие модули (контрагенты erp301, склад erp401, реестр
 * номенклатуры erp227, планирование erp407) — мягкие, без внешних ключей:
 * модули включаются и выключаются независимо, а документы закупки остаются
 * читаемыми даже если соседний модуль снят.
 *
 * Флаги (`is_preferred`, `is_active`) — SMALLINT 0/1, а не BOOLEAN:
 * PostgreSQL отдаёт boolean строками `t`/`f`, и `(bool) 'f'` — это TRUE.
 * Числа избавляют модуль от этих граблей (см. AGENTS.md, пункт 38).
 *
 * @package    Erp403
 * @category   Migrations
 */
class pgMigration_001_Erp403_Procurement_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_requests();
        $this->_create_orders();
        $this->_create_receipts();
        $this->_create_prices();
        $this->_create_suppliers();
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_403_receipt_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_receipts CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_order_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_orders CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_request_lines CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_requests CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_prices CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_403_suppliers CASCADE');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_403_request_number_seq');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_403_order_number_seq');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_403_receipt_number_seq');
    }

    /**
     * Заявки и их позиции: что нужно купить.
     *
     * @return  void
     */
    protected function _create_requests()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_requests (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'draft',
                priority        SMALLINT    NOT NULL DEFAULT 3,
                source          VARCHAR(16) NOT NULL DEFAULT 'manual',
                source_ref      VARCHAR(64) NULL,
                warehouse_id    INTEGER     NULL,
                need_date       DATE        NULL,
                order_id        INTEGER     NULL,
                note            TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                approved_by     INTEGER     NULL,
                approved_at     TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_requests_number_key UNIQUE (number),
                CONSTRAINT erp_403_requests_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_403_requests_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'ordered', 'cancelled')),
                CONSTRAINT erp_403_requests_source_check CHECK (source IN ('manual', 'auto', 'erp407', 'import')),
                CONSTRAINT erp_403_requests_priority_check CHECK (priority BETWEEN 1 AND 4)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_requests_status ON erp_403_requests (status, need_date)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_requests_source ON erp_403_requests (source, source_ref)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_request_lines (
                id              SERIAL PRIMARY KEY,
                request_id      INTEGER      NOT NULL REFERENCES erp_403_requests (id) ON DELETE CASCADE,
                line_no         INTEGER      NOT NULL DEFAULT 10,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                need_date       DATE         NULL,
                supplier_id     INTEGER      NULL,
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                status          VARCHAR(16)  NOT NULL DEFAULT 'open',
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_request_lines_qty_check CHECK (qty >= 0),
                CONSTRAINT erp_403_request_lines_status_check CHECK (status IN ('open', 'ordered', 'received', 'cancelled'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_request_lines_request ON erp_403_request_lines (request_id, line_no)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_request_lines_item ON erp_403_request_lines (item_id)");

        // Номера документов — из последовательностей: сквозная нумерация на год,
        // без гонок при одновременной работе нескольких закупщиков.
        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_403_request_number_seq START 1");
    }

    /**
     * Заказы поставщику и их позиции: что и у кого куплено.
     *
     * @return  void
     */
    protected function _create_orders()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_orders (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                supplier_id     INTEGER     NULL,
                contract_id     INTEGER     NULL,
                request_id      INTEGER     NULL,
                warehouse_id    INTEGER     NULL,
                status          VARCHAR(24) NOT NULL DEFAULT 'draft',
                currency        VARCHAR(8)  NOT NULL DEFAULT 'RUB',
                amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
                delivery_date   DATE        NULL,
                payment_terms   VARCHAR(64) NULL,
                note            TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                sent_at         TIMESTAMP WITH TIME ZONE NULL,
                closed_at       TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_orders_number_key UNIQUE (number),
                CONSTRAINT erp_403_orders_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_403_orders_status_check CHECK (status IN ('draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled')),
                CONSTRAINT erp_403_orders_amount_check CHECK (amount >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_orders_supplier ON erp_403_orders (supplier_id, status)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_orders_status ON erp_403_orders (status, delivery_date)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_order_lines (
                id              SERIAL PRIMARY KEY,
                order_id        INTEGER      NOT NULL REFERENCES erp_403_orders (id) ON DELETE CASCADE,
                line_no         INTEGER      NOT NULL DEFAULT 10,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                code            VARCHAR(64)  NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                qty_received    NUMERIC(18,6) NOT NULL DEFAULT 0,
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                delivery_date   DATE         NULL,
                request_line_id INTEGER      NULL,
                status          VARCHAR(16)  NOT NULL DEFAULT 'open',
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_order_lines_qty_check CHECK (qty >= 0),
                CONSTRAINT erp_403_order_lines_received_check CHECK (qty_received >= 0),
                CONSTRAINT erp_403_order_lines_status_check CHECK (status IN ('open', 'partial', 'received', 'cancelled'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_order_lines_order ON erp_403_order_lines (order_id, line_no)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_order_lines_item ON erp_403_order_lines (item_id)");

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_403_order_number_seq START 1");
    }

    /**
     * Приёмки и их позиции: где закупка становится складом.
     *
     * @return  void
     */
    protected function _create_receipts()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_receipts (
                id              SERIAL PRIMARY KEY,
                uuid            UUID        NOT NULL DEFAULT gen_random_uuid(),
                number          VARCHAR(32) NOT NULL,
                order_id        INTEGER     NULL,
                supplier_id     INTEGER     NULL,
                warehouse_id    INTEGER     NOT NULL,
                status          VARCHAR(16) NOT NULL DEFAULT 'draft',
                doc_number      VARCHAR(64) NULL,
                doc_date        DATE        NULL,
                received_at     DATE        NULL,
                note            TEXT        NULL,
                search_text     VARCHAR(500) NOT NULL DEFAULT '',
                created_by      INTEGER     NULL,
                posted_by       INTEGER     NULL,
                posted_at       TIMESTAMP WITH TIME ZONE NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_receipts_number_key UNIQUE (number),
                CONSTRAINT erp_403_receipts_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_403_receipts_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_receipts_order ON erp_403_receipts (order_id, status)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_receipts_supplier ON erp_403_receipts (supplier_id, received_at)");

        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_receipt_lines (
                id              SERIAL PRIMARY KEY,
                receipt_id      INTEGER      NOT NULL REFERENCES erp_403_receipts (id) ON DELETE CASCADE,
                order_line_id   INTEGER      NULL,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 0,
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                quality_status  VARCHAR(16)  NOT NULL DEFAULT 'pending',
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_receipt_lines_qty_check CHECK (qty > 0),
                CONSTRAINT erp_403_receipt_lines_quality_check CHECK (quality_status IN ('pending', 'passed', 'failed'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_receipt_lines_receipt ON erp_403_receipt_lines (receipt_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_receipt_lines_item ON erp_403_receipt_lines (item_id)");

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_403_receipt_number_seq START 1");
    }

    /**
     * История цен поставщиков.
     *
     * @return  void
     */
    protected function _create_prices()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_prices (
                id              SERIAL PRIMARY KEY,
                supplier_id     INTEGER      NOT NULL,
                item_id         INTEGER      NULL,
                name            VARCHAR(255) NOT NULL,
                price           NUMERIC(18,6) NOT NULL DEFAULT 0,
                qty             NUMERIC(18,6) NOT NULL DEFAULT 1,
                currency        VARCHAR(8)   NOT NULL DEFAULT 'RUB',
                uom_code        VARCHAR(16)  NOT NULL DEFAULT 'шт',
                source          VARCHAR(16)  NOT NULL DEFAULT 'manual',
                order_id        INTEGER      NULL,
                receipt_id      INTEGER      NULL,
                dated_at        DATE         NOT NULL DEFAULT CURRENT_DATE,
                note            TEXT         NULL,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_prices_price_check CHECK (price >= 0),
                CONSTRAINT erp_403_prices_source_check CHECK (source IN ('order', 'receipt', 'manual'))
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_prices_item ON erp_403_prices (item_id, dated_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_prices_supplier ON erp_403_prices (supplier_id, dated_at DESC)");
    }

    /**
     * Закупочные условия поставщика.
     *
     * @return  void
     */
    protected function _create_suppliers()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_403_suppliers (
                id              SERIAL PRIMARY KEY,
                supplier_id     INTEGER      NOT NULL,
                lead_time_days  INTEGER      NOT NULL DEFAULT 7,
                currency        VARCHAR(8)   NOT NULL DEFAULT 'RUB',
                min_order       NUMERIC(18,2) NOT NULL DEFAULT 0,
                payment_terms   VARCHAR(64)  NULL,
                rating          SMALLINT     NOT NULL DEFAULT 3,
                is_preferred    SMALLINT     NOT NULL DEFAULT 0,
                is_active       SMALLINT     NOT NULL DEFAULT 1,
                note            TEXT         NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_403_suppliers_supplier_key UNIQUE (supplier_id),
                CONSTRAINT erp_403_suppliers_rating_check CHECK (rating BETWEEN 1 AND 5),
                CONSTRAINT erp_403_suppliers_preferred_check CHECK (is_preferred IN (0, 1)),
                CONSTRAINT erp_403_suppliers_active_check CHECK (is_active IN (0, 1)),
                CONSTRAINT erp_403_suppliers_lead_time_check CHECK (lead_time_days >= 0)
            )
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS idx_403_suppliers_preferred ON erp_403_suppliers (is_preferred, is_active)");
    }
}
