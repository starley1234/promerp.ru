<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: стартовые данные цеха (erp408_shopfloor).
 *
 * Пустой справочник рабочих центров делает модуль бесполезным: мастеру некуда
 * назначить операцию, а терминалу — нечего показать. Поэтому заводим три
 * типовых рабочих места и три смены, а календарь заполняем на текущий месяц по
 * правилу «будни рабочие, суббота и воскресенье — нет».
 *
 * Данные демонстрационные и заведомо перезаписываемые: коды `СТ-01`, `СБ-01`,
 * `ОТК-01`, `S1`–`S3` при необходимости правятся в интерфейсе. Календарь
 * можно перегенерировать из задачи `shopfloor:calendar`.
 *
 * Миграция идемпотентна: повторный запуск ничего не дублирует.
 */
class pgMigration_002_Erp408_Shopfloor_Seed extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Рабочие центры
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_408_work_centers (code, name, kind, workshop, capacity_per_hour, cost_per_hour, note, search_text)
            VALUES
                ('СТ-01',  'Токарный станок №1',      'center', 'Механический участок', 4,  1800, 'Демонстрационный рабочий центр', 'ст-01 токарный станок №1 механический участок'),
                ('СБ-01',  'Участок сборки',          'area',   'Сборочный цех',        1,  900,  'Демонстрационный участок',      'сб-01 участок сборки сборочный цех'),
                ('ОТК-01', 'Контроль качества (ОТК)', 'center', 'ОТК',                  8,  1200, 'Демонстрационный пост контроля', 'отк-01 контроль качества отк')
            ON CONFLICT (code) DO NOTHING;
        ");

        // ---------------------------------------------------------------------
        // 2. Смены
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_408_shifts (code, name, start_time, end_time, duration_hours, sort_order)
            VALUES
                ('S1', 'Первая смена',   '08:00', '16:00', 8, 1),
                ('S2', 'Вторая смена',   '16:00', '00:00', 8, 2),
                ('S3', 'Ночная смена',   '00:00', '08:00', 8, 3)
            ON CONFLICT (code) DO NOTHING;
        ");

        // ---------------------------------------------------------------------
        // 3. Календарь на текущий месяц: будни — рабочие, выходные — нет
        // ---------------------------------------------------------------------
        $this->run_query("
            INSERT INTO erp_408_calendar (day, work_center_id, shift_id, is_working, hours, note)
            SELECT
                d::date,
                wc.id,
                s.id,
                (EXTRACT(ISODOW FROM d) < 6),
                CASE WHEN EXTRACT(ISODOW FROM d) < 6 THEN s.duration_hours ELSE 0 END,
                CASE WHEN EXTRACT(ISODOW FROM d) < 6 THEN NULL ELSE 'Выходной' END
            FROM generate_series(
                     date_trunc('month', CURRENT_DATE),
                     date_trunc('month', CURRENT_DATE) + interval '1 month - 1 day',
                     interval '1 day'
                 ) AS d
            CROSS JOIN erp_408_work_centers wc
            CROSS JOIN erp_408_shifts s
            WHERE s.code = 'S1'
            ON CONFLICT DO NOTHING;
        ");
    }

    public function down()
    {
        // Демо-данные удаляются только у самих себя: операции и отметки не трогаем
        $this->run_query("DELETE FROM erp_408_calendar WHERE shift_id IN (SELECT id FROM erp_408_shifts WHERE code = 'S1');");
        $this->run_query("DELETE FROM erp_408_shifts WHERE code IN ('S1', 'S2', 'S3');");
        $this->run_query("DELETE FROM erp_408_work_centers WHERE code IN ('СТ-01', 'СБ-01', 'ОТК-01');");
    }
}
