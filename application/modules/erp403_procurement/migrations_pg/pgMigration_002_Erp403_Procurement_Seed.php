<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационные данные закупок (erp403_procurement).
 *
 * Пустой модуль показывает пустые экраны, и по ним нельзя понять, чем закупки
 * отличаются от склада. Поэтому заводим ровно один сквозной пример:
 *
 *   - закупочные условия двух поставщиков из справочника контрагентов
 *     (erp301) — срок поставки, валюта, минимальная партия, один поставщик
 *     помечен предпочтительным;
 *   - историю цен по двум ходовым компонентам, чтобы выбор поставщика
 *     опирался на данные, а не на пустую таблицу;
 *   - заявку «ЗК-ГГГГ-900001» в состоянии «На согласовании» с двумя позициями
 *     и заготовку-черновик «ЗК-ГГГГ-900002».
 *
 * Заказ поставщику и приёмка намеренно не создаются: их делает человек
 * кнопкой, и тогда приход на склад — честный факт, а не декорация.
 *
 * Миграция идемпотентна: номера закреплены за демонстрацией, профили
 * поставщиков вставляются по уникальному `supplier_id`, история цен
 * добавляется только если её ещё нет.
 */
class pgMigration_002_Erp403_Procurement_Seed extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Закупочные условия поставщиков (erp301 → erp_403_suppliers)
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_403_suppliers (supplier_id, lead_time_days, currency, min_order, payment_terms, rating, is_preferred, is_active, note)
            SELECT c.id,
                   CASE WHEN c.id = (SELECT MIN(id) FROM erp_301_counterparties WHERE jsonb_exists(roles, 'supplier')) THEN 7 ELSE 14 END,
                   'RUB',
                   5000,
                   'Оплата по факту поставки',
                   4,
                   CASE WHEN c.id = (SELECT MIN(id) FROM erp_301_counterparties WHERE jsonb_exists(roles, 'supplier')) THEN 1 ELSE 0 END,
                   1,
                   'Демонстрационные закупочные условия: срок, минимальная партия и оценка поставщика.'
            FROM erp_301_counterparties c
            WHERE jsonb_exists(c.roles, 'supplier')
              AND c.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM erp_403_suppliers s WHERE s.supplier_id = c.id);
        ");

        // ---------------------------------------------------------------------
        // 2. История цен: два компонента у двух поставщиков
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_403_prices (supplier_id, item_id, name, price, qty, currency, uom_code, source, dated_at, note)
            SELECT s.supplier_id,
                   o.id,
                   o.name,
                   v.price,
                   v.qty,
                   'RUB',
                   'шт',
                   'manual',
                   (CURRENT_DATE - 30)::date,
                   'Демонстрационная история цен (внесена миграцией erp403).'
            FROM (VALUES
                ('Резистор 10кОм 1% 0402',   12.50::numeric, 1000::numeric),
                ('Конденсатор 100нФ 50В X7R 0603', 8.90::numeric, 500::numeric)
            ) AS v (item_name, price, qty)
            JOIN erp_227_objects o ON o.name = v.item_name AND o.is_latest
            CROSS JOIN (SELECT DISTINCT supplier_id FROM erp_403_suppliers ORDER BY supplier_id LIMIT 2) AS s
            WHERE NOT EXISTS (
                SELECT 1 FROM erp_403_prices p
                WHERE p.supplier_id = s.supplier_id
                  AND p.item_id = o.id
                  AND p.source = 'manual'
            );
        ");

        // ---------------------------------------------------------------------
        // 3. Заявка на согласовании с двумя позициями
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_403_requests (number, status, priority, source, warehouse_id, need_date, note, search_text)
            VALUES (
                'ЗК-' || to_char(CURRENT_DATE, 'YYYY') || '-900001',
                'submitted',
                2,
                'manual',
                (SELECT id FROM erp_401_warehouses WHERE name = 'Склад компонентов SMT' ORDER BY id LIMIT 1),
                (CURRENT_DATE + 21)::date,
                'Демонстрационная заявка: согласуйте её и оформите заказ поставщику — появятся заказ, приёмка и приход на склад.',
                'заявка демонстрационная компоненты согласование'
            )
            ON CONFLICT (number) DO NOTHING;
        ");

        $this->run_query("
            INSERT INTO erp_403_request_lines (request_id, line_no, item_id, name, code, qty, uom_code, need_date, supplier_id, price, status, note)
            SELECT r.id, v.line_no, o.id, o.name, o.code, v.qty, 'шт', (CURRENT_DATE + v.days_left)::date, s.supplier_id, v.price, 'open', v.note
            FROM erp_403_requests r
            CROSS JOIN (VALUES
                (10, 'Резистор 10кОм 1% 0402',        1000::numeric, 14, 12.50::numeric, 'Ходовая позиция монтажа'),
                (20, 'Конденсатор 100нФ 50В X7R 0603', 500::numeric, 21,  8.90::numeric, 'Пополнение склада компонентов')
            ) AS v (line_no, item_name, qty, days_left, price, note)
            LEFT JOIN erp_227_objects o ON o.name = v.item_name AND o.is_latest
            LEFT JOIN erp_403_suppliers s ON s.is_preferred = 1
            WHERE r.number = 'ЗК-' || to_char(CURRENT_DATE, 'YYYY') || '-900001'
              AND NOT EXISTS (
                  SELECT 1 FROM erp_403_request_lines l
                  WHERE l.request_id = r.id AND l.line_no = v.line_no
              );
        ");

        // ---------------------------------------------------------------------
        // 4. Заготовка-черновик: пустая заявка для правки своими руками
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_403_requests (number, status, priority, source, warehouse_id, need_date, note, search_text)
            VALUES (
                'ЗК-' || to_char(CURRENT_DATE, 'YYYY') || '-900002',
                'draft',
                3,
                'manual',
                (SELECT id FROM erp_401_warehouses WHERE name = 'Склад компонентов SMT' ORDER BY id LIMIT 1),
                (CURRENT_DATE + 30)::date,
                'Заготовка: добавьте позиции, отправьте на согласование и оформите заказ.',
                'заявка черновик заготовка'
            )
            ON CONFLICT (number) DO NOTHING;
        ");
    }

    public function down()
    {
        // Убираем ровно демонстрацию: строки уходят каскадом
        $this->run_query("
            DELETE FROM erp_403_prices
            WHERE note = 'Демонстрационная история цен (внесена миграцией erp403).'
        ");

        $this->run_query("
            DELETE FROM erp_403_requests
            WHERE number IN (
                'ЗК-' || to_char(CURRENT_DATE, 'YYYY') || '-900001',
                'ЗК-' || to_char(CURRENT_DATE, 'YYYY') || '-900002'
            )
        ");
    }
}
