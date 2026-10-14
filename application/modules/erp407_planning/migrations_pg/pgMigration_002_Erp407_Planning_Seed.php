<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационная производственная программа (erp407_planning).
 *
 * Пустой модуль планирования показывает пустые экраны: непонятно ни что такое
 * программа, ни как выглядит расчёт. Поэтому заводим одну утверждённую
 * программу «План выпуска модулей управления» со строками по реальной
 * номенклатуре реестра (модуль в сборе, плата, резисторы) и одну пустую
 * программу-черновик как заготовку для правки.
 *
 * Расчёт по демо-программе не выполняется: его запускает человек кнопкой, а
 * результат зависит от текущих остатков и заказов — так снимок в базе всегда
 * честный. Строки программы без позиции номенклатуры расчёт тоже переживёт:
 * он предупредит, что остатки не учтены.
 *
 * Миграция идемпотентна: номера `ПП-ГГГГ-900001` и `ПП-ГГГГ-900002`
 * закреплены за демонстрацией, повторный запуск ничего не дублирует.
 */
class pgMigration_002_Erp407_Planning_Seed extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Утверждённая программа: 3 позиции на ближайший месяц
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_407_programs (number, title, period_from, period_to, status, source, horizon_days, note, search_text)
            VALUES (
                'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900001',
                'План выпуска модулей управления',
                date_trunc('month', CURRENT_DATE)::date,
                (date_trunc('month', CURRENT_DATE) + interval '1 month - 1 day')::date,
                'approved',
                'manual',
                30,
                'Демонстрационная программа: утверждена, строки заданы. Запустите «Рассчитать потребность», чтобы увидеть дефициты и даты заказа.',
                'план выпуска модулей управления демонстрационная'
            )
            ON CONFLICT (number) DO NOTHING;
        ");

        // ---------------------------------------------------------------------
        // 2. Строки демонстрационной программы
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_407_program_lines (program_id, line_no, item_id, name, code, qty, uom_code, due_date, status, note)
            SELECT p.id, v.line_no, v.item_id, v.name, v.code, v.qty, v.uom_code,
                   (CURRENT_DATE + v.days_left)::date,
                   'planned',
                   v.note
            FROM erp_407_programs p
            CROSS JOIN (VALUES
                (10, 5, 'Модуль управления в сборе', 'ASSY-CTRL-001', 50::numeric,  'шт', 21, 'Готовое изделие — собирается из платы и компонентов'),
                (20, 4, 'Плата управления v1.0',     'PCB-CTRL-001', 30::numeric,  'шт', 14, 'Полуфабрикат: покрывается остатком и незавершёнкой'),
                (30, 1, 'Резистор 10кОм 1% 0402',    'RES-0402-10K', 1000::numeric, 'шт', 14, 'Комплектующие для платы')
            ) AS v (line_no, item_id, name, code, qty, uom_code, days_left, note)
            WHERE p.number = 'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900001'
              AND NOT EXISTS (
                  SELECT 1 FROM erp_407_program_lines l
                  WHERE l.program_id = p.id AND l.line_no = v.line_no
              );
        ");

        // Позиции, которых нет в реестре, оставляем без item_id: расчёт
        // предупредит об этом, а не посчитает остатки «по нулю».
        $this->run_query("
            UPDATE erp_407_program_lines l
            SET item_id = NULL
            FROM erp_407_programs p
            WHERE l.program_id = p.id
              AND p.number = 'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900001'
              AND l.item_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM erp_227_objects o WHERE o.id = l.item_id AND o.is_latest
              );
        ");

        // ---------------------------------------------------------------------
        // 3. Черновик-заготовка: пустая программа для правки своими руками
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_407_programs (number, title, period_from, period_to, status, source, horizon_days, note, search_text)
            VALUES (
                'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900002',
                'План на следующий месяц (черновик)',
                (date_trunc('month', CURRENT_DATE) + interval '1 month')::date,
                (date_trunc('month', CURRENT_DATE) + interval '2 months - 1 day')::date,
                'draft',
                'manual',
                30,
                'Заготовка: добавьте строки, утвердите и рассчитайте потребность. Черновик можно править и удалять.',
                'план на следующий месяц черновик заготовка'
            )
            ON CONFLICT (number) DO NOTHING;
        ");
    }

    public function down()
    {
        // Убираем ровно демонстрацию: строки уходят каскадом
        $this->run_query("
            DELETE FROM erp_407_programs
            WHERE number IN (
                'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900001',
                'ПП-' || to_char(CURRENT_DATE, 'YYYY') || '-900002'
            )
        ");
    }
}
