<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационные данные продаж (erp410_shipment).
 *
 * Пустой модуль показывает пустые экраны, и по ним нельзя понять, чем продажа
 * отличается от склада. Поэтому заводим один сквозной пример:
 *
 *   - условия клиента (erp301, роль «покупатель»): отсрочка 14 дней, лимит,
 *     скидка 3 %;
 *   - прайс: базовая цена готового изделия «Модуль управления в сборе» и платы,
 *     отдельная цена клиента со скидкой;
 *   - заказ «ЗКЛ-ГГГГ-900001» в состоянии «Подтверждён»: 5 модулей со сроком
 *     отгрузки через неделю — его можно зарезервировать, собрать и отгрузить
 *     (запас на складе готовой продукции для этого уже есть);
 *   - черновик «ЗКЛ-ГГГГ-900002» на плату управления — он показывает, как
 *     выглядит заказ до подтверждения.
 *
 * Склад и остатки не создаются: склад ведёт erp401, и продажа только просит у
 * него расход. Отгрузка и счёт намеренно не создаются — их делает человек
 * кнопкой, и тогда движение товара и денег — честный факт, а не декорация.
 *
 * Миграция идемпотентна: заказы вставляются по уникальному номеру, строки —
 * только если их ещё нет, прайс и условия клиента — при отсутствии записи.
 */
class pgMigration_002_Erp410_Shipment_Seed extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Условия клиентов (erp301, роль «покупатель» → erp_410_customers)
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_410_customers (customer_id, payment_terms, credit_days, credit_limit, discount, delivery_address, terms, is_active)
            SELECT c.id,
                   'Оплата с отсрочкой ' || v.credit_days || ' дней',
                   v.credit_days,
                   v.credit_limit,
                   v.discount,
                   'по адресу покупателя',
                   'Демонстрационные условия продажи: отсрочка, лимит и скидка клиента.',
                   1
            FROM erp_301_counterparties c
            CROSS JOIN (VALUES (14, 500000::numeric, 3::numeric)) AS v (credit_days, credit_limit, discount)
            WHERE jsonb_exists(c.roles, 'customer')
              AND c.is_deleted = 0
              AND NOT EXISTS (SELECT 1 FROM erp_410_customers s WHERE s.customer_id = c.id);
        ");

        // ---------------------------------------------------------------------
        // 2. Прайс: базовая цена и цена клиента со скидкой
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_410_prices (item_id, customer_id, price, currency, discount, source, valid_from, created_at)
            SELECT o.id, NULL, v.price, 'RUB', 0, 'manual', CURRENT_DATE, NOW()
            FROM (VALUES
                ('Модуль управления в сборе', 45000.000000::numeric),
                ('Плата управления v1.0',     12000.000000::numeric)
            ) AS v (item_name, price)
            JOIN erp_227_objects o ON o.name = v.item_name AND o.is_latest
            WHERE NOT EXISTS (
                SELECT 1 FROM erp_410_prices p
                WHERE p.item_id = o.id AND p.customer_id IS NULL AND p.source = 'manual'
            );
        ");

        $this->run_query("
            INSERT INTO erp_410_prices (item_id, customer_id, price, currency, discount, source, valid_from, created_at)
            SELECT o.id, s.customer_id, 43650.000000, 'RUB', 3, 'manual', CURRENT_DATE, NOW()
            FROM erp_227_objects o
            CROSS JOIN (SELECT customer_id FROM erp_410_customers ORDER BY customer_id LIMIT 1) AS s
            WHERE o.name = 'Модуль управления в сборе' AND o.is_latest
              AND NOT EXISTS (
                SELECT 1 FROM erp_410_prices p
                WHERE p.item_id = o.id AND p.customer_id = s.customer_id
              );
        ");

        // ---------------------------------------------------------------------
        // 3. Подтверждённый заказ на 5 модулей: путь до отгрузки открыт
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_410_orders (number, customer_id, warehouse_id, status, source, currency, order_date, ship_date, payment_terms, note, search_text, confirmed_at)
            VALUES (
                'ЗКЛ-' || to_char(CURRENT_DATE, 'YYYY') || '-900001',
                (SELECT c.id FROM erp_301_counterparties c WHERE jsonb_exists(c.roles, 'customer') AND c.is_deleted = 0 ORDER BY c.id LIMIT 1),
                (SELECT id FROM erp_401_warehouses WHERE name = 'Основной склад готовой продукции' ORDER BY id LIMIT 1),
                'confirmed',
                'manual',
                'RUB',
                CURRENT_DATE,
                (CURRENT_DATE + 7)::date,
                'Оплата с отсрочкой 14 дней',
                'Демонстрационный заказ: зарезервируйте позицию, соберите отгрузку и проведите её — остаток готовой продукции уменьшится, появится счёт.',
                'заказ демонстрационный модуль управления отгрузка продажа',
                NOW()
            )
            ON CONFLICT (number) DO NOTHING;
        ");

        $this->run_query("
            INSERT INTO erp_410_order_lines (order_id, line_no, item_id, name, code, qty, uom_code, price, discount, amount, status)
            SELECT o.id, 10, i.id, i.name, i.code, 5, 'шт', 43650.000000, 3, ROUND(5 * 43650.000000 * (1 - 3 / 100.0), 2), 'open'
            FROM erp_410_orders o
            JOIN erp_227_objects i ON i.name = 'Модуль управления в сборе' AND i.is_latest
            WHERE o.number = 'ЗКЛ-' || to_char(CURRENT_DATE, 'YYYY') || '-900001'
              AND NOT EXISTS (SELECT 1 FROM erp_410_order_lines l WHERE l.order_id = o.id);
        ");

        // ---------------------------------------------------------------------
        // 4. Черновик заказа на плату управления
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_410_orders (number, customer_id, warehouse_id, status, source, currency, order_date, ship_date, note, search_text)
            VALUES (
                'ЗКЛ-' || to_char(CURRENT_DATE, 'YYYY') || '-900002',
                (SELECT c.id FROM erp_301_counterparties c WHERE jsonb_exists(c.roles, 'customer') AND c.is_deleted = 0 ORDER BY c.id LIMIT 1),
                (SELECT id FROM erp_401_warehouses WHERE name = 'Основной склад готовой продукции' ORDER BY id LIMIT 1),
                'draft',
                'manual',
                'RUB',
                CURRENT_DATE,
                (CURRENT_DATE + 14)::date,
                'Демонстрационный черновик: подтвердите заказ, чтобы зарезервировать позиции и собрать отгрузку.',
                'заказ демонстрационный плата управления черновик'
            )
            ON CONFLICT (number) DO NOTHING;
        ");

        $this->run_query("
            INSERT INTO erp_410_order_lines (order_id, line_no, item_id, name, code, qty, uom_code, price, discount, amount, status)
            SELECT o.id, 10, i.id, i.name, i.code, 20, 'шт', 12000.000000, 0, ROUND(20 * 12000.000000, 2), 'open'
            FROM erp_410_orders o
            JOIN erp_227_objects i ON i.name = 'Плата управления v1.0' AND i.is_latest
            WHERE o.number = 'ЗКЛ-' || to_char(CURRENT_DATE, 'YYYY') || '-900002'
              AND NOT EXISTS (SELECT 1 FROM erp_410_order_lines l WHERE l.order_id = o.id);
        ");

        // Показатель «сумма» у заказов считается сервисом при первом открытии,
        // но на пустом стенде карточка должна быть верной сразу.
        $this->run_query("
            UPDATE erp_410_orders o
            SET amount = COALESCE((SELECT SUM(l.amount) FROM erp_410_order_lines l WHERE l.order_id = o.id), 0)
            WHERE o.number LIKE 'ЗКЛ-%';
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_410_order_lines WHERE order_id IN (SELECT id FROM erp_410_orders WHERE number LIKE 'ЗКЛ-%-90000%')");
        $this->run_query("DELETE FROM erp_410_orders WHERE number LIKE 'ЗКЛ-%-90000%'");
        $this->run_query("DELETE FROM erp_410_prices WHERE source = 'manual'");
        $this->run_query("DELETE FROM erp_410_customers");
    }
}
