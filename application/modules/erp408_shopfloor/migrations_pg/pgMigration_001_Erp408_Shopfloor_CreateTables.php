<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 001: цех — рабочие центры, смены, календарь, операции (erp408_shopfloor).
 *
 * Склад закрывается терминалами, а цех — нет: без учёта смен и выработки нет
 * ни факта по операциям, ни реальной себестоимости. Схема повторяет то, как
 * работает цех, и разделена на пять таблиц:
 *
 *  1. `erp_408_work_centers` — где работают: участки и станки с
 *     производительностью и стоимостью нормо-часа;
 *  2. `erp_408_shifts` — когда работают: шаблоны смен (первая, вторая, ночная);
 *  3. `erp_408_calendar` — конкретные рабочие дни: сколько часов работает
 *     центр в смену (пустой центр или пустая смена означают «в целом по цеху»);
 *  4. `erp_408_operations` — что делать: маршрут производственного заказа,
 *     разложенный по операциям, центрам и сменам, с планом и фактом;
 * Простой считается открытым, пока не закрыт: `downtime_started_at` хранит
 * начало, а `downtime_sec` накапливает итог (в цехе станок может стоять
 * сутками, и «забыть нажать стоп» — обычное дело).
 *
 *  5. `erp_408_worklogs` — отметки исполнителя: старт, пауза, выработка,
 *     окончание, простой. Журнал пополняется только добавлением: по нему
 *     считается `worked_sec`/`downtime_sec` операции и видно, кто и когда
 *     нажимал кнопку (в терминальном режиме это основное доказательство).
 *
 * Границы модуля соблюдены: `order_id`/`item_id` — мягкие ссылки на `erp402`
 * и `erp227` без внешних ключей. Цех пишет только в свои таблицы, а факт
 * возвращает в производство событием (`erp408.operation.completed`) — прямая
 * запись в `erp_402_*` из цеха запрещена (иначе два владельца у одних данных).
 *
 * Миграция идемпотентна (IF NOT EXISTS / ON CONFLICT DO NOTHING).
 */
class pgMigration_001_Erp408_Shopfloor_CreateTables extends pgMigration
{
    public function up()
    {
        // ---------------------------------------------------------------------
        // 1. Рабочие центры и участки
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_408_work_centers (
                id                SERIAL PRIMARY KEY,
                uuid              UUID         NOT NULL DEFAULT gen_random_uuid(),
                code              VARCHAR(32)  NOT NULL,
                name              VARCHAR(128) NOT NULL,
                kind              VARCHAR(16)  NOT NULL DEFAULT 'center'
                                  CHECK (kind IN ('center', 'area')),
                workshop          VARCHAR(128) NULL,
                capacity_per_hour NUMERIC(12,2) NULL,
                cost_per_hour     NUMERIC(14,2) NULL,
                is_active         BOOLEAN      NOT NULL DEFAULT TRUE,
                note              TEXT         NULL,
                search_text       VARCHAR(512) NULL,
                created_by        INTEGER      NULL,
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at        TIMESTAMPTZ  NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_work_centers_code_uniq ON erp_408_work_centers (code);");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_work_centers_uuid_uniq ON erp_408_work_centers (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_work_centers_active_idx ON erp_408_work_centers (is_active, kind);");

        // ---------------------------------------------------------------------
        // 2. Смены (шаблоны)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_408_shifts (
                id             SERIAL PRIMARY KEY,
                uuid           UUID         NOT NULL DEFAULT gen_random_uuid(),
                code           VARCHAR(32)  NOT NULL,
                name           VARCHAR(128) NOT NULL,
                start_time     TIME         NOT NULL,
                end_time       TIME         NOT NULL,
                duration_hours NUMERIC(6,2) NOT NULL DEFAULT 8,
                is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
                sort_order     INTEGER      NOT NULL DEFAULT 0,
                note           TEXT         NULL,
                created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at     TIMESTAMPTZ  NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_shifts_code_uniq ON erp_408_shifts (code);");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_shifts_uuid_uniq ON erp_408_shifts (uuid);");

        // ---------------------------------------------------------------------
        // 3. Календарь: рабочие дни по центрам и сменам
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_408_calendar (
                id             SERIAL PRIMARY KEY,
                day            DATE         NOT NULL,
                work_center_id INTEGER      NULL REFERENCES erp_408_work_centers(id) ON DELETE CASCADE,
                shift_id       INTEGER      NULL REFERENCES erp_408_shifts(id) ON DELETE CASCADE,
                is_working     BOOLEAN      NOT NULL DEFAULT TRUE,
                hours          NUMERIC(6,2) NOT NULL DEFAULT 8,
                note           TEXT         NULL,
                created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at     TIMESTAMPTZ  NULL
            );
        ");

        // NULL в обычном UNIQUE-индексе PostgreSQL считает различными значениями,
        // поэтому «день без центра и смены» защищаем выражением с COALESCE.
        $this->run_query("
            CREATE UNIQUE INDEX IF NOT EXISTS erp_408_calendar_slot_uniq
                ON erp_408_calendar (day, COALESCE(work_center_id, 0), COALESCE(shift_id, 0));
        ");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_calendar_day_idx ON erp_408_calendar (day, is_working);");

        // ---------------------------------------------------------------------
        // 4. Операции производственных заказов (маршрут в цехе)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_408_operations (
                id               SERIAL PRIMARY KEY,
                uuid             UUID         NOT NULL DEFAULT gen_random_uuid(),
                order_id         INTEGER      NOT NULL,
                order_uuid       UUID         NULL,
                order_number     VARCHAR(32)  NULL,
                order_title      VARCHAR(255) NULL,
                item_id          INTEGER      NULL,
                qty_planned      NUMERIC(14,3) NOT NULL DEFAULT 0,
                op_no            INTEGER      NOT NULL DEFAULT 10,
                name             VARCHAR(255) NOT NULL,
                description      TEXT         NULL,
                work_center_id   INTEGER      NULL REFERENCES erp_408_work_centers(id) ON DELETE SET NULL,
                shift_id         INTEGER      NULL REFERENCES erp_408_shifts(id) ON DELETE SET NULL,
                planned_day      DATE         NULL,
                planned_hours    NUMERIC(8,2) NOT NULL DEFAULT 0,
                norm_cost        NUMERIC(14,2) NOT NULL DEFAULT 0,
                status           VARCHAR(16)  NOT NULL DEFAULT 'planned'
                                 CHECK (status IN ('planned', 'in_progress', 'paused', 'done', 'cancelled')),
                qty_ok           NUMERIC(14,3) NOT NULL DEFAULT 0,
                qty_scrap        NUMERIC(14,3) NOT NULL DEFAULT 0,
                worked_sec       INTEGER      NOT NULL DEFAULT 0,
                downtime_sec     INTEGER      NOT NULL DEFAULT 0,
                downtime_started_at TIMESTAMPTZ NULL,
                actual_start     TIMESTAMPTZ  NULL,
                actual_end       TIMESTAMPTZ  NULL,
                assigned_user_id INTEGER      NULL,
                source           VARCHAR(16)  NOT NULL DEFAULT 'tech_card'
                                 CHECK (source IN ('tech_card', 'manual', 'order')),
                note             TEXT         NULL,
                created_by       INTEGER      NULL,
                created_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at       TIMESTAMPTZ  NULL
            );
        ");

        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_operations_order_no_uniq ON erp_408_operations (order_id, op_no);");
        $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_408_operations_uuid_uniq ON erp_408_operations (uuid);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_operations_status_idx ON erp_408_operations (status, planned_day);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_operations_center_idx ON erp_408_operations (work_center_id, status);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_operations_order_idx ON erp_408_operations (order_id, op_no);");

        // ---------------------------------------------------------------------
        // 5. Отметки исполнителя (append-only журнал терминала)
        // ---------------------------------------------------------------------
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_408_worklogs (
                id              BIGSERIAL PRIMARY KEY,
                uuid            UUID         NOT NULL DEFAULT gen_random_uuid(),
                operation_id    INTEGER      NOT NULL REFERENCES erp_408_operations(id) ON DELETE CASCADE,
                user_id         INTEGER      NULL,
                kind            VARCHAR(16)  NOT NULL
                                CHECK (kind IN ('start', 'pause', 'resume', 'output',
                                                'finish', 'downtime_start', 'downtime_end')),
                at              TIMESTAMPTZ  NOT NULL DEFAULT now(),
                day             DATE         NOT NULL DEFAULT CURRENT_DATE,
                qty_ok          NUMERIC(14,3) NULL,
                qty_scrap       NUMERIC(14,3) NULL,
                seconds         INTEGER      NULL,
                downtime_reason VARCHAR(64)  NULL,
                comment         TEXT         NULL,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
            );
        ");

        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_worklogs_operation_idx ON erp_408_worklogs (operation_id, at DESC);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_worklogs_day_idx ON erp_408_worklogs (day, kind);");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_408_worklogs_user_idx ON erp_408_worklogs (user_id, day);");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_408_worklogs CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_408_operations CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_408_calendar CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_408_shifts CASCADE;");
        $this->run_query("DROP TABLE IF EXISTS erp_408_work_centers CASCADE;");
    }
}
