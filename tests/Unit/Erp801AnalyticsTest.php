<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты аналитики (модуль erp801_analytics).
 *
 * Проверяют обещание модуля:
 *
 *  - витрины считаются по реальным данным и по правилам владельцев, а не по
 *    копии правил в аналитике;
 *  - формулы (покрытие, ABC/XYZ, OEE, Парето) считают то, что заявлено, и
 *    честно молчат там, где данных не хватает (`NULL` вместо выдуманного нуля);
 *  - выключенный источник — это `source_missing`, а не нули;
 *  - пороги проверяются по диапазону и по взаимной границе;
 *  - снимок фиксирует цифру и сравнивается с предыдущим;
 *  - CSV открывается Excel (BOM, разделитель);
 *  - в коде аналитики нет обращений к чужим таблицам — только к своим и к
 *    сервисам владельцев;
 *  - инструменты MCP объявлены и доводят вызов до сервиса.
 *
 * Тесты работают на своём складе (`WH-801-TEST`), своих позициях и своих
 * снимках (`ТЕСТ-801*`) и убирают всё в tearDown. Если таблиц модуля нет —
 * тесты пропускаются.
 */
class Erp801AnalyticsTest extends TestCase
{
    /** @var \Service_Erp401_Stock */
    protected $stock;

    /** @var \Service_Erp801_Settings */
    protected $settings;

    /** @var \Service_Erp801_Snapshots */
    protected $snapshots;

    /** @var \Model_Erp401_Warehouse|null Тестовый склад */
    protected $warehouse;

    /** @var array Настройки, которые тест менял (вернуть в tearDown) */
    protected $touched_settings = array();

    /** @var array Позиции теста */
    protected $items = array(1, 2, 3);

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль аналитики недоступен — выполните миграции erp801');
            return;
        }

        $this->stock     = \Service_Erp401_Stock::instance();
        $this->settings  = \Service_Erp801_Settings::instance();
        $this->snapshots = \Service_Erp801_Snapshots::instance();

        $this->warehouse = $this->make_warehouse();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            foreach ($this->touched_settings as $code)
            {
                $this->settings->reset($code);
            }

            $this->touched_settings = array();

            \DB::delete('erp_801_snapshots')->where('title', 'LIKE', 'ТЕСТ-801%')->execute();

            if ($this->warehouse AND $this->warehouse->loaded())
            {
                $id = (int) $this->warehouse->id;

                \DB::delete('erp_401_movements')->where('warehouse_id', '=', $id)->execute();
                \DB::delete('erp_401_movements')->where('warehouse_to_id', '=', $id)->execute();
                \DB::delete('erp_401_balances')->where('warehouse_id', '=', $id)->execute();
                \DB::delete('erp_401_warehouses')->where('id', '=', $id)->execute();
            }

            $this->warehouse = NULL;
        }
        catch (\Exception $e)
        {
            // уборка не должна прятать настоящую причину падения теста
        }
    }

    // =========================================================================
    //  Витрина запасов
    // =========================================================================

    /**
     * Оборачиваемость и покрытие считаются по движениям и остаткам владельца.
     */
    public function test_turnover_uses_warehouse_facts()
    {
        $id = (int) $this->warehouse->id;

        $this->stock->receive($id, $this->items[0], 10);
        $this->stock->issue($id, $this->items[0], 6);

        $turnover = \Service_Erp801_Stock::instance()->turnover(array(
            'warehouse_id' => $id,
            'item_id'      => $this->items[0],
        ));

        $this->assertSame(1, $turnover['totals']['items'], 'в витрину попала только наша позиция');
        $this->assertSame(10.0, round($turnover['totals']['qty_in'], 6));
        $this->assertSame(6.0, round($turnover['totals']['qty_out'], 6));
        $this->assertSame(4.0, round($turnover['totals']['stock'], 6), 'остаток склада — из erp401, а не из суммы движений');

        $row = $turnover['rows'][0];

        // 4 единицы при расходе 6 за период: покрытие = 4 / (6 / дней периода).
        $this->assertSame(round(4 / (6 / $turnover['days']), 1), $row['cover_days'], 'покрытие считается от темпа расхода');
        $this->assertSame(round(6 / 4, 4), $row['turns_period'], 'обороты за период = расход / остаток');
        $this->assertSame('medium', $row['class'], 'класс движения выведен из покрытия');
        $this->assertNotEmpty($row['name'], 'имя позиции взято у владельца справочника');
    }

    /**
     * Дефицит: покрытие ниже цели и прикидка «сколько докупить».
     */
    public function test_deficit_shows_low_cover_and_shortage()
    {
        $id = (int) $this->warehouse->id;

        $this->stock->receive($id, $this->items[0], 10);
        $this->stock->issue($id, $this->items[0], 9);

        $deficit = \Service_Erp801_Stock::instance()->deficit(array(
            'warehouse_id' => $id,
            'item_id'      => $this->items[0],
        ));

        $this->assertSame(1, $deficit['items'], 'позиция попала в дефицит');

        $row = $deficit['rows'][0];

        $this->assertContains('low_cover', $row['reasons'], 'причина — покрытие ниже цели');
        $this->assertLessThan((float) $deficit['target_days'], (float) $row['cover_days']);

        // Докупить до цели: разница покрытия × дневной расход.
        $expected = round(((float) $deficit['target_days'] - (float) $row['cover_days']) * $row['daily_out'], 6);

        $this->assertSame($expected, round($row['shortage_to_target'], 6), 'прикидка дозакупки считается от цели');

        // Цель берётся из настроек, а не из константы в коде.
        $this->touched_settings[] = 'cover_target_days';
        $this->settings->save(array('cover_target_days' => 5));

        $lenient = \Service_Erp801_Stock::instance()->deficit(array(
            'warehouse_id' => $id,
            'item_id'      => $this->items[0],
        ));

        $this->assertSame(0, $lenient['items'], 'при цели 5 дней та же позиция дефицитом не считается');
    }

    /**
     * ABC — по накопительной доле расхода, XYZ — по вариации месячных расходов.
     *
     * Месячные движения вставляются прямо в таблицу движений: сервис склада
     * всегда пишет «сейчас», а аналитике нужна история, чтобы проверить XYZ.
     */
    public function test_abc_xyz_uses_share_and_variation()
    {
        $id = (int) $this->warehouse->id;

        $months = array(
            date('Y-m', strtotime('first day of last month')),
            date('Y-m'),
        );

        // Прошлый месяц — любое число; текущий — только первое: движение 5-го
        // числа при запуске теста 1–4 числа оказалось бы «в будущем» и выпало
        // из периода, который сервис ограничивает сегодняшним днём.
        $this_day = '-01';

        // Ровное потребление: 20 и 20 за два месяца → класс X (вариация 0).
        $this->movement($id, $this->items[0], 20, $months[0].'-05 10:00:00');
        $this->movement($id, $this->items[0], 20, $months[1].$this_day.' 10:00:00');

        // Непредсказуемое: 100 и 1 → вариация ~ 138 % → класс Z.
        $this->movement($id, $this->items[1], 100, $months[0].'-06 10:00:00');
        $this->movement($id, $this->items[1], 1, $months[1].$this_day.' 10:00:00');

        // Хвост расхода: одно движение за прошлый месяц — класса XYZ ещё нет.
        $this->movement($id, $this->items[2], 1, $months[0].'-07 10:00:00');

        $analysis = \Service_Erp801_Stock::instance()->abc_xyz(array(
            'warehouse_id' => $id,
            'from'         => $months[0].'-01',
            'to'           => date('Y-m-d'),
        ));

        $this->assertSame(3, $analysis['totals']['items'], 'в анализ попали все три позиции');
        $this->assertSame(2, count($analysis['months']), 'период покрывает два месяца');

        $rows = array();

        foreach ($analysis['rows'] as $row)
        {
            $rows[(int) $row['item_id']] = $row;
        }

        $this->assertArrayHasKey($this->items[0], $rows);
        $this->assertArrayHasKey($this->items[1], $rows);
        $this->assertArrayHasKey($this->items[2], $rows);

        // Сортировка по расходу: 101 → 40 → 1.
        $this->assertSame($this->items[1], (int) $analysis['rows'][0]['item_id'], 'первым идёт самый расходуемый');
        $this->assertSame($this->items[2], (int) $analysis['rows'][2]['item_id'], 'последним — хвост расхода');

        // A — это то, из чего набираются первые 80 % расхода; хвост уходит в C.
        $this->assertSame('A', $rows[$this->items[1]]['abc'], 'самый расходуемый — класс A');
        $this->assertSame('A', $rows[$this->items[0]]['abc'], 'второй нужен, чтобы набрать 80 % — тоже A');
        $this->assertSame('C', $rows[$this->items[2]]['abc'], 'хвост расхода — класс C');
        $this->assertGreaterThan(95.0, (float) $rows[$this->items[2]]['share_total'], 'накопленная доля растёт по порядку');

        $this->assertSame(0.0, (float) $rows[$this->items[0]]['cv'], 'ровное потребление — вариация 0');
        $this->assertSame('X', $rows[$this->items[0]]['xyz'], 'ровное потребление — класс X');
        $this->assertGreaterThan(100.0, (float) $rows[$this->items[1]]['cv'], 'скачки расхода дают большую вариацию');
        $this->assertSame('Z', $rows[$this->items[1]]['xyz'], 'непредсказуемое потребление — класс Z');
        // Пропущенный месяц — это ноль расхода, а не «нет данных»: вариация честно высокая.
        $this->assertGreaterThan(100.0, (float) $rows[$this->items[2]]['cv'], 'месяц без расхода — тоже точка ряда');
        $this->assertSame('Z', $rows[$this->items[2]]['xyz'], 'редкий расход — непредсказуемый класс Z');

        $matrix_total = array_sum(array_map('intval', $analysis['matrix']));

        $this->assertSame(3, $matrix_total, 'матрица посчитала все три позиции');
        $this->assertSame(1, (int) $analysis['matrix']['AX'], 'ровное потребление попало в клетку AX');
        $this->assertSame(1, (int) $analysis['matrix']['AZ'], 'скачки расхода — в клетку AZ');
        $this->assertSame(1, (int) $analysis['matrix']['CZ'], 'хвост расхода — в клетку CZ');

        // Период короче двух месяцев: класс XYZ не выставляется — по одной точке
        // разброс не определён, и аналитика этого не придумывает.
        $short = \Service_Erp801_Stock::instance()->abc_xyz(array(
            'warehouse_id' => $id,
            'from'         => date('Y-m-01'),
            'to'           => date('Y-m-d'),
        ));

        $this->assertNotEmpty($short['rows'], 'позиции текущего месяца в анализе есть');

        foreach ($short['rows'] as $row)
        {
            $this->assertNull($row['xyz'], 'по одному месяцу класс XYZ не выставляется');
            $this->assertNull($row['cv'], 'вариация по одной точке не определена');
        }
    }

    /**
     * Сводка собирает блоки, каждый KPI идёт с целью и состоянием.
     */
    public function test_summary_reports_blocks_with_targets()
    {
        $summary = \Service_Erp801_Summary::instance()->build(array());

        $this->assertNotEmpty($summary['kpi'], 'показатели сводки посчитаны');
        $this->assertNotEmpty($summary['sources'], 'состояние источников показано');
        $this->assertNotEmpty($summary['generated_at']);

        foreach ($summary['blocks'] as $block)
        {
            $this->assertArrayHasKey('available', $block, 'блок честно сообщает, посчитан ли он');
        }

        foreach ($summary['kpi'] as $kpi)
        {
            $this->assertNotEmpty($kpi['title']);
            $this->assertArrayHasKey('target', $kpi, 'у показателя есть цель');
            $this->assertContains($kpi['state'], array('ok', 'warn', 'bad', 'unknown'), 'состояние — из объявленных');
            $this->assertNotEmpty($kpi['url'], 'из KPI можно перейти к витрине');
        }

        // Витрина без источника обязана сказать об этом, а не показать нули.
        $probe  = new \Service_Erp801_Quality(new Erp801SourcesWithoutQuality(), \Service_Erp801_Settings::instance());
        $reason = NULL;

        try
        {
            $probe->panel(array());
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('source_missing', $reason, 'выключенный источник — это source_missing, а не нули');

        // То же для цеха: без него OEE не выдумывается.
        $production = new \Service_Erp801_Production(new Erp801SourcesWithoutShopfloor(), \Service_Erp801_Settings::instance());

        $reason = NULL;

        try
        {
            $production->panel(array());
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('source_missing', $reason, 'без цеха витрина производства честно недоступна');
    }

    /**
     * OEE: формулы и честные «не определено» вместо деления на ноль.
     */
    public function test_oee_formula_and_undefined_cases()
    {
        $probe = new Erp801ProductionProbe();

        // Работа 3600 с, простой 900 с → доступность 80 %.
        // Норма: 8 ч плана на 8 единиц → 3600 с/ед. Выпуск 9 единиц (8 годных, 1 брак)
        // при 3600 с работы → производительность 3600 × 9 / 3600 = 900 %.
        $result = $probe->probe(array(
            'qty_planned'   => 8,
            'qty_ok'        => 8,
            'qty_scrap'     => 1,
            'planned_hours' => 8,
            'worked_sec'    => 3600,
            'downtime_sec'  => 900,
        ));

        $this->assertSame(80.0, $result['availability'], 'доступность = работа / (работа + простои)');
        $this->assertSame(3600.0, $result['ideal_cycle_sec'], 'норма такта = плановое время / план выпуска');
        $this->assertSame(900.0, $result['performance'], 'производительность против нормы');
        $this->assertSame(88.9, $result['quality'], 'качество = годное / выпуск');
        $this->assertSame(640.1, $result['oee'], 'OEE = доступность × производительность × качество');
        $this->assertNotEmpty($result['notes'], 'перевыполнение нормы помечено, а не спрятано');

        // Пустые данные: ни одного деления на ноль, всё «не определено».
        $empty = $probe->probe(array(
            'qty_planned'   => 0,
            'qty_ok'        => 0,
            'qty_scrap'     => 0,
            'planned_hours' => 0,
            'worked_sec'    => 0,
            'downtime_sec'  => 0,
        ));

        $this->assertNull($empty['availability']);
        $this->assertNull($empty['performance']);
        $this->assertNull($empty['quality']);
        $this->assertNull($empty['oee'], 'OEE не считается, когда нет данных');
        $this->assertGreaterThanOrEqual(3, count($empty['notes']), 'каждое «не определено» объяснено');
    }

    /**
     * Витрины производства, качества и закупок отдают данные своих владельцев.
     */
    public function test_production_quality_and_purchases_panels()
    {
        $production = \Service_Erp801_Production::instance()->panel(array('from' => date('Y-m-d', strtotime('-30 days'))));

        $this->assertArrayHasKey('totals', $production);
        $this->assertArrayHasKey('plan_fact', $production);
        $this->assertArrayHasKey('pareto', $production);
        $this->assertArrayHasKey('by_center', $production);

        $quality = \Service_Erp801_Quality::instance()->panel(array('from' => date('Y-m-d', strtotime('-90 days'))));

        $this->assertArrayHasKey('summary', $quality);
        $this->assertArrayHasKey('cause_pareto', $quality);
        $this->assertArrayHasKey('code_pareto', $quality);
        $this->assertContains($quality['state'], array('ok', 'warn', 'bad', 'unknown'), 'состояние качества — одно из объявленных');

        $purchases = \Service_Erp801_Purchases::instance()->panel(array());

        $this->assertArrayHasKey('stats', $purchases);
        $this->assertArrayHasKey('overdue_share', $purchases);
        $this->assertArrayHasKey('overdue_rows', $purchases);
        $this->assertGreaterThanOrEqual(0, $purchases['overdue']);

        foreach ($purchases['overdue_rows'] as $row)
        {
            $this->assertNotEmpty($row['number'], 'строка заказа пришла от владельца');
            $this->assertNotEmpty($row['supplier_name'], 'имя поставщика получено через сервис закупок');
        }
    }

    // =========================================================================
    //  Пороги, снимки, выгрузка
    // =========================================================================

    /**
     * Пороги: диапазон, взаимная граница и возврат к умолчанию.
     */
    public function test_settings_validate_ranges_and_relations()
    {
        $rows = $this->settings->rows();

        $this->assertNotEmpty($rows, 'описание порогов есть');
        $this->assertSame(30, $this->settings->get('cover_target_days'), 'по умолчанию порог покрытия — 30 дней');

        // Вне диапазона: порог не должен «приняться по-тихому».
        $this->touched_settings[] = 'cover_target_days';

        $reason = NULL;

        try
        {
            $this->settings->save(array('cover_target_days' => 0));
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('validation', $reason, 'значение вне диапазона отклонено с причиной validation');
        $this->assertSame(30, $this->settings->get('cover_target_days'), 'отклонённое значение не сохранилось');

        // Границы ABC не должны пересечься: A < B.
        $this->touched_settings[] = 'abc_a_share';
        $this->touched_settings[] = 'abc_b_share';

        $reason = NULL;

        try
        {
            $this->settings->save(array('abc_a_share' => 90, 'abc_b_share' => 85));
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('validation', $reason, 'A не может быть больше B');

        // Корректное сохранение и возврат к умолчанию пустой строкой.
        $this->settings->save(array('cover_target_days' => 45));
        $this->assertSame(45, $this->settings->get('cover_target_days'));

        $this->settings->save(array('cover_target_days' => ''));
        $this->assertSame(30, $this->settings->get('cover_target_days'), 'пустое значение вернуло умолчание');
    }

    /**
     * Снимок фиксирует результат, читается по uuid, сравнивается и удаляется.
     */
    public function test_snapshots_are_saved_compared_and_deleted()
    {
        $id      = (int) $this->warehouse->id;
        $filters = array('warehouse_id' => $id, 'item_id' => $this->items[0]);

        $this->stock->receive($id, $this->items[0], 10);
        $this->stock->issue($id, $this->items[0], 2);

        $first = $this->snapshots->save('stock', $filters, NULL, 'ТЕСТ-801 первый', 'проверка сравнения');

        $this->assertTrue(\Erp::is_uuid((string) $first->uuid), 'снимок получил uuid');
        $this->assertNotEmpty($first->payload(), 'результат витрины сохранён целиком');

        $this->stock->issue($id, $this->items[0], 3);

        $second = $this->snapshots->save('stock', $filters, NULL, 'ТЕСТ-801 второй');

        $found = $this->snapshots->find((string) $second->uuid);

        $this->assertNotNull($found, 'снимок находится по uuid');
        $this->assertSame((int) $second->id, (int) $found->id);

        $diff = $this->snapshots->compare((int) $first->id, (int) $second->id);

        $paths = array();

        foreach ($diff['rows'] as $row)
        {
            $paths[] = $row['path'];
        }

        $this->assertContains('totals.qty_out', $paths, 'сравнение увидело изменившийся расход');
        $this->assertNotContains('rows', $paths, 'построчные списки в сравнение не попадают');

        // Первый снимок не «догоняет» источник: в нём осталось 2, хотя на складе уже 5.
        $reloaded = $this->snapshots->find((int) $first->id);
        $payload  = $reloaded->payload();

        $this->assertSame(2.0, round((float) $payload['totals']['qty_out'], 6), 'снимок не пересчитывается задним числом');
        $this->assertSame(5.0, round((float) $this->snapshots->find((int) $second->id)->payload()['totals']['qty_out'], 6), 'второй снимок снят на новых данных');

        // Снимки разных витрин не сравниваются — это ошибка клиента, а не «ноль отличий».
        $reason = NULL;

        try
        {
            $this->snapshots->compare((int) $first->id, (int) \Service_Erp801_Snapshots::instance()->save('production', array(), NULL, 'ТЕСТ-801 производство')->id);
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_argument', $reason, 'сравнение разных витрин отклонено');

        // Мусор из адреса не доходит до СУБД: это 404, а не ошибка приведения типа.
        foreach (array('abc', '', 0, '123-456') as $garbage)
        {
            $this->assertNull($this->snapshots->find($garbage), 'по «'.var_export($garbage, TRUE).'» снимок не ищется и не падает');
        }

        $this->snapshots->delete((int) $first->id);
        $this->assertNull($this->snapshots->find((int) $first->id), 'снимок удалён');
    }

    /**
     * Выгрузка: BOM, точка с запятой, имя файла с периодом.
     */
    public function test_csv_export_is_excel_friendly()
    {
        $file = \Service_Erp801_Csv::instance()->render('stock', array(
            'warehouse_id' => (int) $this->warehouse->id,
            'item_id'      => $this->items[0],
        ));

        $this->assertStringEndsWith('.csv', $file['filename']);
        $this->assertStringContainsString("\xEF\xBB\xBF", $file['content'], 'BOM нужен, чтобы Excel не сломал кириллицу');
        $this->assertStringContainsString(';', $file['content'], 'разделитель — точка с запятой');
        $this->assertStringContainsString('Оборачиваемость', $file['content'], 'в файле есть заголовок витрины');

        $reason = NULL;

        try
        {
            \Service_Erp801_Csv::instance()->render('nope');
        }
        catch (\Exception_Erp801_Analytics $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_argument', $reason, 'неизвестная витрина — честная ошибка');
    }

    // =========================================================================
    //  Слой, права и агентский доступ
    // =========================================================================

    /**
     * Аналитика не ходит в чужие таблицы: только свои `erp_801_*` и сервисы
     * владельцев. Проверяется по строковым литералам, а не по комментариям.
     */
    public function test_module_reads_only_own_tables()
    {
        $init = \Kohana::find_file('modules', 'erp801_analytics/init');

        $this->assertNotSame(FALSE, $init, 'модуль аналитики найден в модулях');

        $foreign = array();

        foreach ($this->php_files(dirname($init)) as $file)
        {
            foreach (token_get_all((string) file_get_contents($file)) as $token)
            {
                if ( ! is_array($token) OR $token[0] !== T_CONSTANT_ENCAPSED_STRING)
                {
                    continue;
                }

                if (preg_match('/^([\'"])erp_(\d{3})_/', $token[1], $matches) AND $matches[2] !== '801')
                {
                    $foreign[] = basename($file).': '.$token[1];
                }
            }
        }

        $this->assertSame(array(), $foreign, 'в аналитике нет обращений к чужим таблицам — только к сервисам владельцев');
    }

    /**
     * Каждая витрина открывается на живых данных: страница отдаёт 200 и не
     * падает из-за поля, которого нет в ответе сервиса.
     */
    public function test_pages_render_on_real_data()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $id = (int) $this->warehouse->id;

        $this->stock->receive($id, $this->items[0], 8);
        $this->stock->issue($id, $this->items[0], 5);

        $snapshot = $this->snapshots->save('stock', array('warehouse_id' => $id), NULL, 'ТЕСТ-801 страница');

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        $result = $smoke->run(array(
            array('url' => '/analytics',                                'name' => 'Сводка'),
            array('url' => '/analytics/stock',                          'name' => 'Оборачиваемость'),
            array('url' => '/analytics/stock?tab=deficit&limit=50',     'name' => 'Дефицит'),
            array('url' => '/analytics/stock?class=dead',               'name' => 'Фильтр класса'),
            array('url' => '/analytics/abc?class=A',                    'name' => 'ABC/XYZ'),
            array('url' => '/analytics/production?center_id=1',         'name' => 'Производство'),
            array('url' => '/analytics/quality',                        'name' => 'Качество'),
            array('url' => '/analytics/purchases?due_days=14',          'name' => 'Закупки'),
            array('url' => '/analytics/snapshots',                      'name' => 'Снимки'),
            array('url' => '/analytics/snapshots/view/'.(int) $snapshot->id, 'name' => 'Карточка снимка'),
            array('url' => '/analytics/settings',                       'name' => 'Пороги'),
            array('url' => '/analytics/api/summary',                    'name' => 'API: сводка'),
            array('url' => '/analytics/api/sources',                    'name' => 'API: источники'),
            array('url' => '/analytics/api/stock',                      'name' => 'API: запасы'),
            array('url' => '/analytics/api/deficit',                    'name' => 'API: дефицит'),
            array('url' => '/analytics/api/abc',                        'name' => 'API: ABC'),
            array('url' => '/analytics/api/production',                 'name' => 'API: производство'),
            array('url' => '/analytics/api/quality',                    'name' => 'API: качество'),
            array('url' => '/analytics/api/purchases',                  'name' => 'API: закупки'),
            array('url' => '/analytics/api/snapshots',                  'name' => 'API: снимки'),
            array('url' => '/analytics/api/snapshot/'.(int) $snapshot->id, 'name' => 'API: снимок'),
            array('url' => '/analytics/api/settings',                   'name' => 'API: пороги'),
        ));

        foreach ($result['rows'] as $row)
        {
            $this->assertSame(NULL, $row['error'], $row['name'].': '.$row['status'].' '.$row['error']);
            $this->assertSame(200, (int) $row['status'], $row['name'].' отвечает '.$row['status']);
        }
    }

    /**
     * Анониму витрины не отдаются: аналитика показывает выручку, остатки и брак.
     */
    public function test_api_blocked_for_anonymous()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        try
        {
            \Auth::instance()->logout();
        }
        catch (\Exception $e)
        {
            // Сессии может не быть — для проверки это не важно.
        }

        try
        {
            $smoke = new \Service_Dev_Smoke;

            $result = $smoke->run(array(
                array('url' => '/analytics/api/summary', 'name' => 'API: сводка'),
            ));

            $row = $result['rows'][0];

            $this->assertContains((int) $row['status'], array(401, 403), 'анониму API не отвечает данными');
        }
        finally
        {
            $smoke->login_as();
        }
    }

    /**
     * Права и адреса: действия чтения объявлены в конфиге API, изменяющие — нет.
     */
    public function test_actions_and_routes_are_declared()
    {
        $config  = \Kohana::$config->load('api');
        $config  = ($config instanceof \Kohana_Config_Group) ? $config->as_array() : (array) $config;
        $actions = (array) $config['read_only_actions'];

        foreach (array('summary', 'sources', 'stock', 'deficit', 'abc', 'production', 'quality', 'purchases', 'snapshots', 'snapshot', 'settings', 'export') as $action)
        {
            $this->assertContains($action, $actions, 'действие чтения '.$action.' объявлено в config/api.php');
        }

        foreach (array('save_snapshot', 'delete_snapshot', 'save_settings') as $action)
        {
            $this->assertNotContains($action, $actions, 'изменяющее действие '.$action.' не считается чтением');
        }

        foreach (array('erp801_analytics_index', 'erp801_analytics_api', 'erp801_analytics_export', 'erp801_analytics_snapshots', 'erp801_analytics_settings') as $name)
        {
            $this->assertNotNull(\Route::get($name), 'маршрут '.$name.' объявлен');
        }
    }

    public function test_quality_defect_share_is_numeric_or_unknown_never_boolean()
    {
        $share = \Service_Erp801_Quality::instance()->summary()['defect_share'];

        $this->assertTrue($share === NULL OR is_float($share), 'Доля брака должна быть float или NULL');
        $this->assertFalse(is_bool($share), 'Ошибка приоритета AND превращала defect_share в true');
    }

    /**
     * Инструменты MCP: объявлены, права разведены, вызовы доходят до сервисов.
     */
    public function test_mcp_tools_reach_services()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $registry = \Service_Erp804_Registry::instance();
        $declared = $registry->tools();

        $names = array();

        foreach ($declared as $name => $tool)
        {
            $names[] = (string) $name;
        }

        $expected = array(
            'analytics.summary', 'analytics.sources', 'analytics.stock', 'analytics.deficit',
            'analytics.abc', 'analytics.production', 'analytics.quality', 'analytics.purchases',
            'analytics.snapshots', 'analytics.snapshot', 'analytics.settings', 'analytics.export',
            'analytics.save_snapshot', 'analytics.save_settings', 'analytics.delete_snapshot',
        );

        foreach ($expected as $tool)
        {
            $this->assertContains($tool, $names, 'инструмент '.$tool.' объявлен в mcp.php');
        }

        $id      = (int) $this->warehouse->id;
        $filters = array('warehouse_id' => $id, 'item_id' => $this->items[0]);

        $this->stock->receive($id, $this->items[0], 5);

        $snapshot = $this->snapshots->save('stock', $filters, NULL, 'ТЕСТ-801 для MCP');

        $cases = array(
            'analytics.sources'       => array(),
            'analytics.settings'      => array(),
            'analytics.summary'       => $filters,
            'analytics.stock'         => $filters,
            'analytics.deficit'       => $filters,
            'analytics.abc'           => $filters,
            'analytics.production'    => array('from' => date('Y-m-d', strtotime('-30 days'))),
            'analytics.quality'       => array('from' => date('Y-m-d', strtotime('-90 days'))),
            'analytics.purchases'     => array(),
            'analytics.snapshots'     => array('code' => 'stock'),
            'analytics.snapshot'      => array('id' => (int) $snapshot->id, 'compare' => TRUE),
            'analytics.export'        => array('code' => 'stock') + $filters,
            'analytics.save_snapshot' => array('code' => 'stock', 'title' => 'ТЕСТ-801 из MCP') + $filters,
            'analytics.save_settings' => array('values' => array('cover_target_days' => 45)),
            'analytics.delete_snapshot' => array('id' => (int) $snapshot->id),
        );

        $this->touched_settings[] = 'cover_target_days';

        foreach ($cases as $name => $args)
        {
            $this->assertArrayHasKey($name, $declared, 'инструмент '.$name.' объявлен');

            try
            {
                $result = $registry->call($name, $args);
            }
            catch (\Exception_Erp804_Mcp $e)
            {
                // Предметный отказ сервиса — законный ответ: обработчик дошёл до
                // сервиса, а тот объяснил, почему нельзя. Ошибки PHP сюда не
                // попадают: реестр их не ловит, и тест падает — это и нужно.
                $this->assertInstanceOf('Exception', $e->getPrevious(), $name.': отказ должен прийти из сервиса');
                $this->assertNotSame('', (string) $e->getMessage(), $name.': у отказа должно быть объяснение');
                continue;
            }

            $this->assertIsArray($result, $name.' должен вернуть структуру ответа');
            $this->assertArrayHasKey('summary', $result, $name.': в ответе должна быть строка-итог');
        }
    }

    // =========================================================================
    //  Помощники
    // =========================================================================

    protected function available()
    {
        static $available = NULL;

        if ($available !== NULL)
        {
            return $available;
        }

        if ( ! class_exists('Service_Erp801_Summary'))
        {
            return $available = FALSE;
        }

        try
        {
            $tables = \DB::select('table_name')
                ->from('information_schema.tables')
                ->where('table_schema', '=', 'public')
                ->where('table_name', 'IN', array('erp_801_snapshots', 'erp_801_settings'))
                ->execute()
                ->as_array('table_name', 'table_name');

            return $available = (count($tables) === 2);
        }
        catch (\Exception $e)
        {
            return $available = FALSE;
        }
    }

    /**
     * @return  \Model_Erp401_Warehouse
     */
    protected function make_warehouse()
    {
        \DB::delete('erp_401_warehouses')->where('code', '=', 'WH-801-TEST')->execute();

        $warehouse = \ORM::factory('Erp401_Warehouse');
        $warehouse->code      = 'WH-801-TEST';
        $warehouse->name      = 'Склад аналитики (тест)';
        $warehouse->type      = 'main';
        $warehouse->is_active = 1;
        $warehouse->save();

        return $warehouse;
    }

    /**
     * Движение с исторической датой: сервис склада пишет «сейчас», а XYZ нужна
     * история. Вставка идёт в таблицу владельца только из теста.
     *
     * @param   int     $warehouse_id
     * @param   int     $item_id
     * @param   float   $qty
     * @param   string  $performed_at
     * @return  void
     */
    protected function movement($warehouse_id, $item_id, $qty, $performed_at)
    {
        \DB::insert('erp_401_movements', array(
                'type', 'item_id', 'warehouse_id', 'qty', 'balance_before', 'balance_after',
                'status', 'doc_type', 'performed_at', 'created_at',
            ))
            ->values(array(
                'issue', (int) $item_id, (int) $warehouse_id, (float) $qty, (float) $qty, 0.0,
                'posted', 'test', $performed_at, date('Y-m-d H:i:s'),
            ))
            ->execute();
    }

    /**
     * Все PHP-файлы модуля.
     *
     * @param   string  $root
     * @return  array
     */
    protected function php_files($root)
    {
        $files    = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file)
        {
            if ($file->isFile() AND substr($file->getFilename(), -4) === '.php')
            {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}

/**
 * Источники без качества: проверяет, что витрина честно сообщает о выключенном
 * модуле, а не показывает нули.
 */
class Erp801SourcesWithoutQuality extends \Service_Erp801_Sources
{
    /**
     * @return  null
     */
    public function quality()
    {
        return NULL;
    }
}

/**
 * Источники без цеха: OEE не из чего считать — витрина должна сказать об этом.
 */
class Erp801SourcesWithoutShopfloor extends \Service_Erp801_Sources
{
    /**
     * @return  null
     */
    public function shopfloor()
    {
        return NULL;
    }
}

/**
 * Доступ к формулам OEE для проверки без подделки данных цеха.
 */
class Erp801ProductionProbe extends \Service_Erp801_Production
{
    /**
     * @param   array   $totals
     * @return  array
     */
    public function probe(array $totals)
    {
        return $this->_oee($totals);
    }
}
