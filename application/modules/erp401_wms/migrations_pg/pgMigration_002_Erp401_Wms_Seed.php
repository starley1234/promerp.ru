<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационные данные складского учёта (erp401_wms).
 *
 * Миграция идемпотентна: повторный запуск не создаёт дублей.
 * Номенклатура берётся из реестра erp227 (компоненты и узлы электроники),
 * сам модуль номенклатуру не дублирует.
 */
class pgMigration_002_Erp401_Wms_Seed extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        // 1. Склады
        $this->run_query("
            INSERT INTO erp_401_warehouses (code, name, type, address, description) VALUES
            ('WH-MAIN',    'Основной склад готовой продукции', 'main',       'Корпус А, склад 1', 'Отгрузка собранных модулей и узлов'),
            ('WH-SMT',     'Склад компонентов SMT',            'production', 'Корпус А, цех 2',   'Компоненты для поверхностного монтажа'),
            ('WH-TRANSIT', 'Склад в пути',                     'transit',    NULL,                'Товары в транспортировке'),
            ('WH-QUAR',    'Карантин',                         'quarantine', 'Корпус Б, пом. 14', 'Входной контроль и спорные партии'),
            ('WH-SCRAP',   'Склад списания',                   'scrap',      'Корпус Б, пом. 15', 'Брак и утилизация')
            ON CONFLICT (code) DO NOTHING;
        ");

        // 2. Начальные остатки: (склад, объект реестра, количество, единица, минимум)
        //    item_id ссылается на erp_227_objects.id:
        //    1 = RES-0402-10K, 2 = CAP-0603-100NF, 3 = MCU-STM32F103C8T6, 5 = ASSY-CTRL-001
        $this->run_query("
            INSERT INTO erp_401_balances (warehouse_id, item_id, uom_code, qty, reserved, min_qty)
            SELECT w.id, v.item_id, v.uom_code, v.qty, v.reserved, v.min_qty
            FROM erp_401_warehouses w
            JOIN (VALUES
                ('WH-SMT',  1, 'pcs', 48000.000000, 0, 10000),
                ('WH-SMT',  2, 'pcs', 31500.000000, 0,  8000),
                ('WH-SMT',  3, 'pcs',  1240.000000, 0,   200),
                ('WH-MAIN', 5, 'pcs',    12.000000, 0,     5)
            ) AS v(code, item_id, uom_code, qty, reserved, min_qty) ON v.code = w.code
            ON CONFLICT (warehouse_id, item_id) DO NOTHING;
        ");

        // 3. Проводки прихода, чтобы журнал движений был непустым с самого начала
        $this->run_query("
            INSERT INTO erp_401_movements
                (type, item_id, uom_code, warehouse_id, qty, balance_before, balance_after,
                 status, doc_type, note, performed_at, created_by)
            SELECT
                'receipt', b.item_id, b.uom_code, b.warehouse_id, b.qty, 0, b.qty,
                'posted', 'seed', 'Начальный остаток (демоданные миграции 002)',
                CURRENT_TIMESTAMP, NULL
            FROM erp_401_balances b
            WHERE NOT EXISTS (
                SELECT 1 FROM erp_401_movements m
                WHERE m.doc_type = 'seed' AND m.item_id = b.item_id AND m.warehouse_id = b.warehouse_id
            );
        ");

        // 4. Подписка центра уведомлений на падение остатка ниже минимума.
        //    Шина erp208 читает подписчиков из этой таблицы при старте,
        //    callback формата Class::method вызывается как ($payload, $event, $initiator).
        $this->run_query("
            INSERT INTO erp_208_event_subscribers
                (event_name, module_id, callback_method, is_notification, is_active, description)
            VALUES
                ('erp401.stock_low', 'erp401', 'Service_Erp401_Notifications::on_stock_low', TRUE, TRUE,
                 'Уведомление ответственных за склад о падении остатка ниже минимума')
            ON CONFLICT (event_name, module_id, callback_method) DO NOTHING;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_208_event_subscribers WHERE event_name = 'erp401.stock_low' AND module_id = 'erp401';");
        $this->run_query("DELETE FROM erp_401_movements WHERE doc_type = 'seed';");
        $this->run_query("DELETE FROM erp_401_balances;");
        $this->run_query("DELETE FROM erp_401_warehouses WHERE code IN ('WH-MAIN','WH-SMT','WH-TRANSIT','WH-QUAR','WH-SCRAP');");
    }
}
