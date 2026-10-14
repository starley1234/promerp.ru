<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: производственные заказы (erp402_production).
 *
 * Схема решает три задачи производства и потому разделена на две таблицы:
 *
 *  1. `erp_402_orders` — заголовок заказа: что производим (изделие реестра
 *     `erp227` или техкарта `erp228`), сколько, когда нужно, откуда берём
 *     материалы и куда принимаем готовую продукцию. Статус заказа — это
 *     состояние производственного процесса, а не «галочка»: `draft` →
 *     `planned` → `released` → `in_progress` → `done` (либо `cancelled`).
 *  2. `erp_402_order_items` — состав (потребность в материалах) с нормами
 *     расхода: `qty_per_unit` — на единицу изделия, `qty_total` — на весь
 *     заказ, `qty_issued` — сколько уже списано. Строка ссылается на резерв
 *     склада (`erp401`) — так производство и склад не расходятся в цифрах.
 *
 * Ссылки на другие модули (`item_id` — реестр `erp227`, `warehouse_id` —
 * `erp401`, `tech_card_id` — `erp228`, `customer_id` — `erp301`) намеренно
 * оставлены «мягкими»: без внешних ключей. Схему модуля нельзя ломать
 * переездом соседа, и наоборот — чужие таблицы в чужой миграции не создаются.
 *
 * Номер заказа берётся из последовательности: две вкладки, нажавшие
 * «Создать» одновременно, не получат один и тот же номер.
 *
 * Миграция идемпотентна (IF NOT EXISTS / ON CONFLICT DO NOTHING).
 */
class pgMigration_001_Erp402_Production_CreateTables extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Заказы
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_402_orders (
                id                    SERIAL PRIMARY KEY,
                uuid                  UUID         NOT NULL DEFAULT gen_random_uuid(),
                number                VARCHAR(32)  NOT NULL,
                title                 VARCHAR(255) NOT NULL,
                tech_card_id          INTEGER      NULL,
                item_id               INTEGER      NULL,
                uom_code              VARCHAR(16)  NOT NULL DEFAULT 'pcs',
                qty                   NUMERIC(15,3) NOT NULL DEFAULT 0 CHECK (qty >= 0),
                qty_done              NUMERIC(15,3) NOT NULL DEFAULT 0 CHECK (qty_done >= 0),
                status                VARCHAR(20)  NOT NULL DEFAULT 'draft'
                                      CHECK (status IN ('draft', 'planned', 'released', 'in_progress', 'done', 'cancelled')),
                priority              SMALLINT     NOT NULL DEFAULT 5 CHECK (priority BETWEEN 1 AND 9),
                warehouse_id          INTEGER      NULL,
                output_warehouse_id   INTEGER      NULL,
                customer_id           INTEGER      NULL,
                contract_id           INTEGER      NULL,
                target_date           DATE         NULL,
                started_at            TIMESTAMPTZ  NULL,
                finished_at           TIMESTAMPTZ  NULL,
                planned_materials_cost NUMERIC(15,2) NOT NULL DEFAULT 0,
                planned_labor_cost    NUMERIC(15,2) NOT NULL DEFAULT 0,
                note                  TEXT         NULL,
                search_text           VARCHAR(512) NOT NULL DEFAULT '',
                created_by            INTEGER      NULL,
                created_at            TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            TIMESTAMP(0) NULL
            );
        ");

        // Последовательность номеров: «ПО-2026-000123»
        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_402_order_number_seq;");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_402_orders_number_uniq ON erp_402_orders (number);");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_402_orders_uuid_uniq ON erp_402_orders (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_orders_status_idx ON erp_402_orders (status, target_date);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_orders_card_idx ON erp_402_orders (tech_card_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_orders_item_idx ON erp_402_orders (item_id);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_orders_search_idx ON erp_402_orders (search_text varchar_pattern_ops);");

        // ---------------------------------------------------------------------
        // 2. Состав заказа (потребность в материалах)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_402_order_items (
                id             SERIAL PRIMARY KEY,
                order_id       INTEGER      NOT NULL REFERENCES erp_402_orders (id) ON DELETE CASCADE,
                line_no        INTEGER      NOT NULL DEFAULT 10,
                item_id        INTEGER      NULL,
                name           VARCHAR(255) NOT NULL,
                uom_code       VARCHAR(16)  NOT NULL DEFAULT 'pcs',
                qty_per_unit   NUMERIC(15,6) NOT NULL DEFAULT 0 CHECK (qty_per_unit >= 0),
                qty_total      NUMERIC(15,3) NOT NULL DEFAULT 0 CHECK (qty_total >= 0),
                qty_issued     NUMERIC(15,3) NOT NULL DEFAULT 0 CHECK (qty_issued >= 0),
                warehouse_id   INTEGER      NULL,
                reservation_id INTEGER      NULL,
                status         VARCHAR(20)  NOT NULL DEFAULT 'planned'
                               CHECK (status IN ('planned', 'reserved', 'issued', 'short')),
                note           TEXT         NULL,
                created_at     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP(0) NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_402_order_items_line_uniq ON erp_402_order_items (order_id, line_no);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_order_items_order_idx ON erp_402_order_items (order_id, status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_402_order_items_item_idx ON erp_402_order_items (item_id);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_402_order_items CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_402_orders CASCADE;");
        $this->run_query("DROP SEQUENCE IF EXISTS erp_402_order_number_seq;");
    }
}
