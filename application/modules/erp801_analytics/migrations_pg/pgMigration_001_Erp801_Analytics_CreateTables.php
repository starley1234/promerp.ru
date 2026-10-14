<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Аналитика и витрины (erp801_analytics): таблицы.
 *
 * Модуль ничего не считает «у себя» по чужим таблицам: агрегаты берутся у
 * владельцев данных (склад erp401, цех erp408, качество erp405, закупки
 * erp403) через их сервисы, а здесь живёт то, что принадлежит аналитике:
 *
 *   erp_801_snapshots  снимок витрины на дату: код витрины, фильтры и готовый
 *                      результат (JSONB). Снимок — это «отчёт на память»:
 *                      цифры не пересчитываются задним числом, когда соседний
 *                      модуль переписал историю, и два снимка можно сравнить;
 *   erp_801_settings   пороги витрин (целевое покрытие запасов, границы ABC и
 *                      XYZ, цель по OEE, допустимая доля брака и просрочки) —
 *                      одни на систему, чтобы дашборд у директора и отчёт у
 *                      начальника цеха считали «много/мало» одинаково.
 *
 * Отдельной таблицы под кэш агрегатов нет намеренно: живые данные на этом
 * объёме считаются быстрее, чем истекает любой кэш, а расхождение кэша с
 * источником — это неверное решение, принятое по устаревшей цифре.
 *
 * @package    Erp801
 * @category   Migrations
 */
class pgMigration_001_Erp801_Analytics_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_snapshots();
        $this->_create_settings();
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_801_settings CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_801_snapshots CASCADE');
    }

    /**
     * Снимки витрин.
     *
     * `code` — код витрины (`summary`, `stock`, `abc`, `production`, `quality`,
     * `purchases`), `filters` — с какими параметрами считали, `payload` —
     * готовый результат. Пара «фильтры + результат» делает снимок
     * воспроизводимым: видно, за какой период и по какому складу цифра.
     *
     * `day` — день, за который снят отчёт: по нему ищут «что было на первое
     * число», и он не совпадает с `created_at`, если снимок сделали задним
     * числом (например, закрывая месяц).
     *
     * @return  void
     */
    protected function _create_snapshots()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_801_snapshots (
                id          SERIAL PRIMARY KEY,
                uuid        UUID         NOT NULL DEFAULT gen_random_uuid(),
                code        VARCHAR(32)  NOT NULL,
                day         DATE         NOT NULL,
                title       VARCHAR(255) NOT NULL DEFAULT '',
                filters     JSONB        NULL DEFAULT '{}'::jsonb,
                payload     JSONB        NOT NULL DEFAULT '{}'::jsonb,
                note        TEXT         NULL,
                created_by  INTEGER      NULL,
                created_at  TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_801_snapshots_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_801_snapshots_code_check
                    CHECK (code IN ('summary', 'stock', 'abc', 'production', 'quality', 'purchases'))
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_801_snapshots IS 'Снимки витрин аналитики: фильтры и результат на дату (erp801_analytics)'");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_801_snapshots_code_day ON erp_801_snapshots (code, day DESC, id DESC)");
    }

    /**
     * Настройки (пороги) витрин.
     *
     * Хранится строками: значение — число или короткая строка, а разбор и
     * проверку диапазона делает сервис настроек. Так новая настройка не требует
     * миграции — а пороги в этой предметной области меняются чаще, чем схема.
     *
     * @return  void
     */
    protected function _create_settings()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_801_settings (
                id          SERIAL PRIMARY KEY,
                code        VARCHAR(64)  NOT NULL,
                value       VARCHAR(255) NOT NULL,
                updated_by  INTEGER      NULL,
                updated_at  TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_801_settings_code_key UNIQUE (code)
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_801_settings IS 'Пороги витрин аналитики: покрытие запасов, границы ABC/XYZ, цели KPI (erp801_analytics)'");
    }
}
