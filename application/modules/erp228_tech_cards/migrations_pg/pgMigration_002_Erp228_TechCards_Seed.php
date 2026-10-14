<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационные технологические карты.
 *
 * Две карты — аддитивная (готовый документ с операциями) и механообработка
 * (черновик без документа). Набор нужен, чтобы UI, виджеты и API модуля
 * открывались на чистой установке, а не показывали пустые экраны.
 *
 * Миграция идемпотентна: ON CONFLICT по uuid карты и по актуальной версии документа.
 */
class pgMigration_002_Erp228_TechCards_Seed extends pgMigration
{
    public function up()
    {
        // 1. Карты (uuid фиксированы — по ним же проверяется идемпотентность)
        $this->run_query("
            INSERT INTO erp_228_tech_cards
                (uuid, target_type, target_id, title, source_code, input_params, production_type,
                 status, search_text, created_by)
            VALUES
                ('11111111-2280-4000-8000-000000002281', 'parametric', 101,
                 'Кронштейн датчика вибрации',
                 '// Кронштейн датчика вибрации, аддитив\nplate = 60; height = 35; wall = 3;\ndifference() { cube([plate, 40, height]); translate([wall, wall, wall]) cube([plate-2*wall, 40-2*wall, height]); }\n',
                 '{\"материал\": \"PA12\", \"заполнение_процентов\": 30, \"партия\": 12}'::jsonb, 'additive',
                 'ready', 'кронштейн датчика вибрации 2281', 1),
                ('11111111-2280-4000-8000-000000002282', 'stl', NULL,
                 'Корпус блока управления (фрезеровка)',
                 '// Заготовка корпуса, механообработка\nsize = [180, 120, 45];\n// черновая фреза D12, чистовая D6\n',
                 '{\"материал\": \"Д16Т\", \"обороты\": 4000, \"партия\": 4}'::jsonb, 'mechanical',
                 'draft', 'корпус блока управления фрезеровка 2282', 1)
            ON CONFLICT (uuid) DO NOTHING;
        ");

        // 2. Готовый документ первой карты (актуальная версия)
        $this->run_query("
            INSERT INTO erp_228_tech_documents
                (tech_card_id, is_actual, content_markdown, structured_data, llm_model, tokens_total, gen_time_ms)
            SELECT c.id, 1,
                E'## Техпроцесс: кронштейн датчика вибрации\n\n1. Подготовка модели и проверка толщины стенки (не менее 3 мм).\n2. Печать PA12, заполнение 30%, слой 0,1 мм.\n3. Удаление поддержек, зачистка посадочных поверхностей.\n4. Контроль межцентрового расстояния и установка резьбовых вставок М3.\n',
                '[{\"step\": 1, \"name\": \"Подготовка модели\"}, {\"step\": 2, \"name\": \"Печать\"}, {\"step\": 3, \"name\": \"Постобработка\"}]'::jsonb,
                'demo-model', 1420, 8600
            FROM erp_228_tech_cards c
            WHERE c.uuid = '11111111-2280-4000-8000-000000002281'
            ON CONFLICT (tech_card_id) WHERE is_actual = 1 DO NOTHING;
        ");

        // 3. Операции документа
        $this->run_query("
            INSERT INTO erp_228_tech_operations
                (document_id, sort_order, op_name, op_description, duration_sec, cost_amount, params)
            SELECT d.id, v.sort_order, v.op_name, v.op_description, v.duration_sec, v.cost_amount, v.params
            FROM erp_228_tech_documents d
            JOIN erp_228_tech_cards c ON c.id = d.tech_card_id
            CROSS JOIN (VALUES
                (10, 'Подготовка модели', 'Проверка толщины стенки и ориентации в слайсере', 900, 350.00,
                 '{\"слой_мм\": 0.1, \"заполнение_процентов\": 30}'::jsonb),
                (20, 'Печать PA12', 'Аддитивная печать партии 12 шт.', 14400, 5400.00,
                 '{\"материал\": \"PA12\", \"количество\": 12}'::jsonb),
                (30, 'Постобработка', 'Удаление поддержек и зачистка посадочных поверхностей', 3600, 1200.00,
                 '{\"операция\": \"ручная\"}'::jsonb),
                (40, 'Контроль', 'Замер межцентрового расстояния, установка вставок М3', 1800, 900.00,
                 '{\"инструмент\": \"штангенциркуль\"}'::jsonb)
            ) AS v(sort_order, op_name, op_description, duration_sec, cost_amount, params)
            WHERE c.uuid = '11111111-2280-4000-8000-000000002281'
              AND NOT EXISTS (
                  SELECT 1 FROM erp_228_tech_operations o WHERE o.document_id = d.id
              );
        ");
    }

    public function down()
    {
        $this->run_query("
            DELETE FROM erp_228_tech_cards
            WHERE uuid IN ('11111111-2280-4000-8000-000000002281', '11111111-2280-4000-8000-000000002282');
        ");
    }
}
