<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационный заказ (erp402_production).
 *
 * Нужен, чтобы раздел «Производство» не открывался пустым списком в новой
 * базе и чтобы было видно полный путь: состав → потребность → резерв → выпуск.
 *
 * Заказ намеренно «неполный»: склад и позиция номенклатуры не заданы, потому
 * что демо-миграция модуля не имеет права опираться на данные соседей
 * (`erp401`, `erp227`) — их может не быть в момент применения. Такая строка
 * показывает ровно то, что должен сделать планировщик: сопоставить материал
 * с номенклатурой и выбрать склад.
 *
 * Миграция идемпотентна: ON CONFLICT по номеру заказа и по номеру строки.
 */
class pgMigration_002_Erp402_Production_Seed extends pgMigration
{
    public function up()
    {
        $this->run_query("
            INSERT INTO erp_402_orders (number, title, uom_code, qty, status, priority, target_date, note, search_text)
            VALUES (
                'ПО-ДЕМО-001',
                'Демо-заказ: пробная партия',
                'pcs',
                10,
                'planned',
                5,
                CURRENT_DATE + INTERVAL '14 days',
                'Демонстрационный заказ: заполните состав и склад материалов.',
                'по-демо-001 демо-заказ: пробная партия'
            )
            ON CONFLICT (number) DO NOTHING;
        ");

        $this->run_query("
            INSERT INTO erp_402_order_items (order_id, line_no, name, uom_code, qty_per_unit, qty_total, status, note)
            SELECT o.id, 10, 'Материал по спецификации (демо)', 'pcs', 1, 10, 'planned',
                   'Номенклатура не сопоставлена — выберите позицию реестра.'
            FROM erp_402_orders o
            WHERE o.number = 'ПО-ДЕМО-001'
            ON CONFLICT (order_id, line_no) DO NOTHING;
        ");

        $this->run_query("
            INSERT INTO erp_402_order_items (order_id, line_no, name, uom_code, qty_per_unit, qty_total, status, note)
            SELECT o.id, 20, 'Крепёж (демо)', 'pcs', 4, 40, 'planned', NULL
            FROM erp_402_orders o
            WHERE o.number = 'ПО-ДЕМО-001'
            ON CONFLICT (order_id, line_no) DO NOTHING;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_402_orders WHERE number = 'ПО-ДЕМО-001';");
    }
}
