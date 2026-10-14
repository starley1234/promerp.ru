<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты качества (модуль erp405_quality).
 *
 * Проверяют обещание модуля: партия берётся под контроль, дефекты записываются
 * с причиной, брак изолируется на карантин, решение по браку **исполняется
 * складом** (утиль — перемещением на склад утиля, а не пометкой в таблице), а
 * статистика считает то же, что видно в документах.
 *
 * Тесты работают на своих данных (`ТЕСТ-405*`) и убирают их в tearDown, включая
 * склады карантина и утиля, остатки и движения. Если таблиц модуля нет — тесты
 * пропускаются.
 */
class Erp405QualityTest extends TestCase
{
    /** @var \Service_Erp405_Inspections */
    protected $inspections;

    /** @var \Service_Erp405_DefectCodes */
    protected $codes;

    /** @var \Service_Erp405_Stats */
    protected $stats;

    /** @var array Идентификаторы складов теста */
    protected $warehouses = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль качества недоступен — выполните миграции erp405');
            return;
        }

        $this->inspections = \Service_Erp405_Inspections::instance();
        $this->codes       = \Service_Erp405_DefectCodes::instance();
        $this->stats       = \Service_Erp405_Stats::instance();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $ids = $this->test_inspection_ids();

            if ($ids)
            {
                // Дефекты и решения уходят каскадом (FOREIGN KEY ON DELETE CASCADE)
                \DB::delete('erp_405_inspections')->where('id', 'IN', $ids)->execute();
            }

            \DB::delete('erp_405_defect_codes')->where('code', 'LIKE', 'ТЕСТ-405%')->execute();

            $warehouses = $this->warehouse_ids();

            if ($warehouses)
            {
                \DB::delete('erp_401_movements')->where('warehouse_id', 'IN', $warehouses)->execute();
                \DB::delete('erp_401_movements')->where('warehouse_to_id', 'IN', $warehouses)->execute();
                \DB::delete('erp_401_balances')->where('warehouse_id', 'IN', $warehouses)->execute();
                \DB::delete('erp_401_warehouses')->where('id', 'IN', $warehouses)->execute();
            }

            $this->warehouses = array();
        }
        catch (\Exception $e)
        {
            // уборка не должна прятать настоящую ошибку теста
        }
    }

    // =========================================================================
    //  Справочник кодов дефектов
    // =========================================================================

    public function test_dictionary_is_seeded_and_searchable()
    {
        $codes = $this->codes->search(array(), 100, 0);

        $this->assertGreaterThanOrEqual(10, count($codes), 'стартовый справочник кодов дефектов должен прийти миграцией');

        $found = $this->codes->search(array('term' => 'царап'), 10, 0);

        $this->assertGreaterThan(0, count($found), 'поиск по названию дефекта должен работать');

        // Категории и важность — из справочника, а не свободным текстом
        foreach ($codes as $code)
        {
            $this->assertArrayHasKey((string) $code->category, \Model_Erp405_DefectCode::$categories);
            $this->assertArrayHasKey((int) $code->severity, \Model_Erp405_DefectCode::$severities);
        }
    }

    public function test_duplicate_code_is_rejected()
    {
        $this->codes->create(array('code' => 'ТЕСТ-405-DUP', 'name' => 'Дубль теста', 'category' => 'other'));

        $reason = $this->reason(function () {
            $this->codes->create(array('code' => 'тест-405-dup', 'name' => 'Ещё раз то же', 'category' => 'other'));
        });

        $this->assertSame('duplicate_code', $reason);
    }

    // =========================================================================
    //  Контроль и дефекты
    // =========================================================================

    public function test_number_and_lifecycle_of_inspection()
    {
        $inspection = $this->make_inspection(array('qty' => 50));

        $this->assertMatchesRegularExpression('/^ПК-\d{4}-\d{6}$/', (string) $inspection->number);
        $this->assertSame('draft', (string) $inspection->status);
        $this->assertNull($inspection->result, 'без проверки итога нет');

        $inspection = $this->inspections->start($inspection->id);

        $this->assertSame('registered', (string) $inspection->status);

        $inspection = $this->inspections->mark_passed($inspection->id);

        $this->assertSame('passed', (string) $inspection->result);
        $this->assertSame('decided', (string) $inspection->status);

        $inspection = $this->inspections->close($inspection->id);

        $this->assertSame('closed', (string) $inspection->status);

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->update($inspection->id, array('qty' => 100));
        });

        $this->assertSame('invalid_transition', $reason, 'закрытый контроль не правится');
    }

    public function test_defect_turns_result_into_failed_and_counts_qty()
    {
        $inspection = $this->make_inspection(array('qty' => 100));

        $defect = $this->inspections->add_defect($inspection->id, array(
            'defect_code_id' => $this->code_id('SURF-SCRATCH'),
            'qty'            => 15,
            'cause'          => 'supplier',
        ));

        $inspection = $this->inspections->get($inspection->id);

        $this->assertSame('failed', (string) $inspection->result, 'факт дефекта сам ставит итог «брак»');
        $this->assertSame(15.0, (float) $inspection->defect_qty);
        $this->assertSame(85.0, $inspection->good_qty());
        $this->assertSame(15.0, $inspection->defect_share());
        $this->assertSame('SURF-SCRATCH', (string) $defect->code);

        // Второй дефект складывается с первым
        $this->inspections->add_defect($inspection->id, array('qty' => 5, 'cause' => 'production', 'severity' => 3));

        $inspection = $this->inspections->get($inspection->id);

        $this->assertSame(20.0, (float) $inspection->defect_qty);

        // Снять ошибочную запись тоже можно — и итог пересчитывается
        $this->inspections->remove_defect($defect->id);

        $inspection = $this->inspections->get($inspection->id);

        $this->assertSame(5.0, (float) $inspection->defect_qty);
    }

    public function test_defects_cannot_exceed_batch()
    {
        $inspection = $this->make_inspection(array('qty' => 10));

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->add_defect($inspection->id, array('qty' => 11));
        });

        $this->assertSame('qty_exceeded', $reason);
    }

    public function test_passed_is_blocked_while_defects_exist()
    {
        $inspection = $this->make_inspection(array('qty' => 10));

        $this->inspections->add_defect($inspection->id, array('qty' => 1, 'cause' => 'production'));

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->mark_passed($inspection->id);
        });

        $this->assertSame('invalid_transition', $reason);
    }

    // =========================================================================
    //  Карантин и решения: склад — единственный исполнитель
    // =========================================================================

    public function test_quarantine_isolates_batch_and_release_returns_it()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для проверки карантина нужны склад erp401 и номенклатура erp227');
        }

        $main  = $this->warehouse('main');
        $hold  = $this->warehouse('quarantine');
        $item  = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($main, $item, 30);

        $inspection = $this->make_inspection(array(
            'qty'          => 30,
            'item_id'      => $item,
            'warehouse_id' => $main,
        ));

        $this->inspections->add_defect($inspection->id, array('qty' => 6, 'cause' => 'supplier'));

        $inspection = $this->inspections->quarantine($inspection->id, $hold);

        $this->assertSame(6.0, (float) $inspection->quarantine_qty);
        $this->assertSame($hold, (int) $inspection->quarantine_warehouse_id);
        $this->assertTrue($inspection->is_quarantined());
        $this->assertSame(6.0, \Service_Erp401_Stock::instance()->qty($hold, $item), 'партия физически на карантине');
        $this->assertSame(24.0, \Service_Erp401_Stock::instance()->qty($main, $item));

        // Второй раз столько же изолировать нельзя — брака всего 6
        $reason = $this->reason(function () use ($inspection, $hold) {
            $this->inspections->quarantine($inspection->id, $hold, 6);
        });

        $this->assertSame('qty_exceeded', $reason);

        // Пока партия на карантине, контроль не закрывается
        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->close($inspection->id);
        });

        $this->assertSame('invalid_transition', $reason);

        $inspection = $this->inspections->release($inspection->id);

        $this->assertSame(0.0, (float) $inspection->quarantine_qty);
        $this->assertFalse($inspection->is_quarantined());
        $this->assertSame(0.0, \Service_Erp401_Stock::instance()->qty($hold, $item));
        $this->assertSame(30.0, \Service_Erp401_Stock::instance()->qty($main, $item), 'выпуск вернул партию на склад');
    }

    public function test_scrap_decision_moves_defect_to_scrap_warehouse()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для проверки утиля нужны склад erp401 и номенклатура erp227');
        }

        $main  = $this->warehouse('main');
        $scrap = $this->warehouse('scrap');
        $item  = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($main, $item, 20);

        $inspection = $this->make_inspection(array(
            'qty'          => 20,
            'item_id'      => $item,
            'warehouse_id' => $main,
        ));

        $this->inspections->add_defect($inspection->id, array('qty' => 4, 'cause' => 'production'));

        $inspection = $this->inspections->decide($inspection->id, 'scrap', 'ТЕСТ-405: раковины в отливке', NULL, array(
            'warehouse_id' => $scrap,
        ));

        $this->assertSame('scrap', (string) $inspection->decision);
        $this->assertSame('decided', (string) $inspection->status);
        $this->assertSame(4.0, \Service_Erp401_Stock::instance()->qty($scrap, $item), 'брак физически на складе утиля');
        $this->assertSame(16.0, \Service_Erp401_Stock::instance()->qty($main, $item));

        $movement = \DB::select('id', 'doc_type')
            ->from('erp_401_movements')
            ->where('warehouse_to_id', '=', $scrap)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        $this->assertNotSame(FALSE, $movement, 'решение оставлено в движениях склада');
        $this->assertSame('erp405_scrap', (string) $movement['doc_type']);

        $decision = \DB::select('decision', 'qty')
            ->from('erp_405_decisions')
            ->where('inspection_id', '=', (int) $inspection->id)
            ->where('decision', '=', 'scrap')
            ->execute()
            ->current();

        $this->assertNotSame(FALSE, $decision, 'журнал решений объясняет движение склада');
        $this->assertSame(4.0, (float) $decision['qty']);
    }

    public function test_decision_requires_failed_result()
    {
        $inspection = $this->make_inspection(array('qty' => 5));

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->decide($inspection->id, 'scrap');
        });

        $this->assertSame('invalid_transition', $reason, 'году́ю партию в утиль не отправляют');
    }

    public function test_unknown_decision_is_rejected()
    {
        $inspection = $this->make_inspection(array('qty' => 5));

        $this->inspections->add_defect($inspection->id, array('qty' => 1));

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->decide($inspection->id, 'sell_to_neighbour');
        });

        $this->assertSame('invalid_argument', $reason);
    }

    public function test_cancel_is_blocked_for_quarantined_batch()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для проверки карантина нужны склад erp401 и номенклатура erp227');
        }

        $main = $this->warehouse('main');
        $hold = $this->warehouse('quarantine');
        $item = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($main, $item, 8);

        $inspection = $this->make_inspection(array('qty' => 8, 'item_id' => $item, 'warehouse_id' => $main));

        $this->inspections->add_defect($inspection->id, array('qty' => 2));
        $this->inspections->quarantine($inspection->id, $hold);

        $reason = $this->reason(function () use ($inspection) {
            $this->inspections->cancel($inspection->id, 'ТЕСТ-405: передумали');
        });

        $this->assertSame('invalid_transition', $reason, 'партия на карантине — сначала выпуск');
    }

    // =========================================================================
    //  Входной контроль приёмки (erp403) и статистика
    // =========================================================================

    public function test_incoming_inspection_from_receipt_line_is_idempotent()
    {
        if ( ! class_exists('Service_Erp403_Receipts') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для входного контроля нужны модуль закупок и номенклатура erp227');
        }

        $receipts = \Service_Erp403_Receipts::instance();

        $receipt = $receipts->create(array(
            'number'       => 'ПР-ТЕСТ-405-1',
            'supplier_id'  => $this->supplier_id(),
            'warehouse_id' => $this->warehouse('main'),
            'lines'        => array(array(
                'item_id'  => $this->item_id(),
                'name'     => 'ТЕСТ-405 приход',
                'qty'      => 7,
                'price'    => 10,
                'uom_code' => 'шт',
            )),
            'note'         => 'ТЕСТ-405: входной контроль',
        ));

        $line = $receipts->lines($receipt->id);
        $line = reset($line);

        $inspection = $this->inspections->from_receipt_line((int) $line->id);

        $this->assertSame('incoming', (string) $inspection->kind);
        $this->assertSame('erp403_receipt', (string) $inspection->source);
        $this->assertSame((int) $line->id, (int) $inspection->source_ref);
        $this->assertSame((int) $receipt->warehouse_id, (int) $inspection->warehouse_id);

        // Повторный вызов не заводит второй контроль для той же позиции
        $again = $this->inspections->from_receipt_line((int) $line->id);

        $this->assertSame((int) $inspection->id, (int) $again->id);

        // Итог контроля сообщается приёмке: годная позиция больше не блокирует приход
        $this->inspections->mark_passed($inspection->id);

        $line = $receipts->line((int) $line->id);

        $this->assertSame('passed', (string) $line->quality_status);

        // Уборка: приёмка теста (позиции и сам документ)
        \DB::delete('erp_403_receipt_lines')->where('receipt_id', '=', (int) $receipt->id)->execute();
        \DB::delete('erp_403_receipts')->where('id', '=', (int) $receipt->id)->execute();
    }

    public function test_stats_count_what_documents_show()
    {
        $inspection = $this->make_inspection(array('qty' => 40));

        $this->inspections->add_defect($inspection->id, array('qty' => 8, 'cause' => 'warehouse'));
        $this->inspections->add_defect($inspection->id, array('qty' => 2, 'cause' => 'warehouse'));

        $summary = $this->stats->summary();

        $this->assertGreaterThanOrEqual(1, (int) $summary['failed']);
        $this->assertGreaterThanOrEqual(10.0, (float) $summary['defect_qty']);

        $by_cause = $this->stats->by_cause();

        $causes = array();

        foreach ($by_cause as $row)
        {
            $causes[$row['cause']] = (float) $row['qty'];
        }

        $this->assertArrayHasKey('warehouse', $causes);
        $this->assertGreaterThanOrEqual(10.0, $causes['warehouse']);

        // Партия с браком и без решения попадает в «ждут решения»
        $pending = $this->inspections->pending(50);
        $found   = FALSE;

        foreach ($pending as $row)
        {
            if ((int) $row->id === (int) $inspection->id)
            {
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'контроль с браком и без решения должен быть в очереди решений');
    }

    // =========================================================================
    //  Страницы, API и агентский доступ
    // =========================================================================

    public function test_pages_render_on_real_data()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $inspection = $this->make_inspection(array('qty' => 12));

        $this->inspections->add_defect($inspection->id, array('qty' => 3, 'cause' => 'production'));

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        $result = $smoke->run(array(
            array('url' => '/quality',                                          'name' => 'Доска качества'),
            array('url' => '/quality/inspections',                             'name' => 'Контроли'),
            array('url' => '/quality/inspections?pending=1',                   'name' => 'Очередь решений'),
            array('url' => '/quality/inspections/view/'.(int) $inspection->id, 'name' => 'Карточка контроля'),
            array('url' => '/quality/inspections/edit',                        'name' => 'Форма контроля'),
            array('url' => '/quality/quarantine',                              'name' => 'Карантин'),
            array('url' => '/quality/defects',                                 'name' => 'Коды дефектов'),
            array('url' => '/quality/defects/edit',                            'name' => 'Форма кода дефекта'),
            array('url' => '/quality/api/stats',                               'name' => 'API: сводка'),
            array('url' => '/quality/api/inspections',                         'name' => 'API: контроли'),
            array('url' => '/quality/api/codes',                               'name' => 'API: коды дефектов'),
            array('url' => '/quality/api/by_cause',                            'name' => 'API: статистика'),
            array('url' => '/quality/widget/summary',                          'name' => 'Виджет: сводка'),
            array('url' => '/quality/widget/quarantine',                       'name' => 'Виджет: карантин'),
        ));

        foreach ($result['rows'] as $row)
        {
            $this->assertSame(NULL, $row['error'], $row['name'].': '.$row['status'].' '.$row['error']);
            $this->assertSame(200, (int) $row['status'], $row['name'].' отвечает '.$row['status']);
        }
    }

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

        $smoke  = new \Service_Dev_Smoke;
        $result = $smoke->run(array(
            array('url' => '/quality/api/stats', 'name' => 'API без входа'),
        ));

        $row = reset($result['rows']);

        $this->assertSame(403, (int) $row['status'], 'JSON API качества не отдаёт данные без прав');
    }

    /**
     * Контракт методов: изменяющее действие по GET получает 405, чтение — нет.
     *
     * Проверяем решение ядра (`Controller_Base::api_method_error()`), потому что
     * внутрипроцессный прогон подставляет запрос без метода: настоящее
     * HTTP-поведение здесь не воспроизвести, а правило — можно. Живая проверка
     * делается запросом к dev-серверу (`curl -i /quality/api/inspection_create`).
     */
    public function test_mutating_api_action_rejects_get()
    {
        $error = \Controller_Base::api_method_error('Controller_Erp405_Api', 'inspection_create', \Request::GET, TRUE);

        $this->assertNotNull($error, 'изменяющее действие по GET должно получать отказ');
        $this->assertStringContainsString('POST', $error);

        $this->assertNull(
            \Controller_Base::api_method_error('Controller_Erp405_Api', 'stats', \Request::GET, TRUE),
            'чтение по GET разрешено: оно есть в application/config/api.php'
        );
    }

    public function test_mcp_exposes_quality_tools()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $names = array();

        foreach (\Service_Erp804_Registry::instance()->tools() as $name => $tool)
        {
            $names[] = (string) $name;
        }

        foreach (array('quality.stats', 'quality.inspections', 'quality.inspection', 'quality.pending',
                       'quality.quarantine', 'quality.defect_codes', 'quality.defects_report',
                       'quality.inspection.create', 'quality.defect.add', 'quality.inspection.quarantine',
                       'quality.inspection.release', 'quality.decision.scrap', 'quality.decision.return') as $tool)
        {
            $this->assertContains($tool, $names, 'инструмент '.$tool.' объявлен в mcp.php');
        }
    }

    /**
     * Инструменты MCP качества не просто объявлены — они доводят вызов до
     * сервиса. Проверяются все 19: обработчик получает аргументы, зовёт
     * `Service_Erp405_*` и отдаёт результат. Ошибка предметной логики
     * (`Exception_Erp405_Quality`) — законный ответ, ошибка PHP (нет метода,
     * нет аргумента, не тот тип) — нет.
     */
    public function test_mcp_handlers_reach_services()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $registry  = \Service_Erp804_Registry::instance();
        $inspection = $this->make_inspection(array('qty' => 6));

        $cases = array(
            'quality.stats'                   => array(),
            'quality.inspections'             => array('limit' => 5),
            'quality.inspection'              => array('id' => (int) $inspection->id),
            'quality.pending'                 => array(),
            'quality.quarantine'              => array(),
            'quality.defect_codes'            => array(),
            'quality.defects_report'          => array(),
            'quality.inspection.create'       => array('kind' => 'incoming', 'qty' => 3, 'note' => 'ТЕСТ-405: вызов MCP'),
            'quality.inspection.from_receipt' => array('line_id' => 999999999),
            'quality.defect.add'              => array('inspection_id' => (int) $inspection->id, 'qty' => 2, 'cause' => 'production'),
            'quality.inspection.start'        => array('id' => (int) $inspection->id),
            'quality.inspection.pass'         => array('id' => (int) $inspection->id),
            'quality.inspection.quarantine'   => array('id' => (int) $inspection->id),
            'quality.inspection.release'      => array('id' => (int) $inspection->id),
            'quality.inspection.close'        => array('id' => (int) $inspection->id),
            'quality.decision.scrap'          => array('id' => (int) $inspection->id),
            'quality.decision.return'         => array('id' => (int) $inspection->id),
            'quality.decision.rework'         => array('id' => (int) $inspection->id),
            'quality.defect_code.save'        => array('code' => 'ТЕСТ-405-MCP', 'name' => 'Проверка MCP', 'category' => 'other'),
        );

        $declared = $registry->tools();

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
                // сервиса, а тот объяснил, почему нельзя. Реестр заворачивает
                // такое в Exception_Erp804_Mcp, сохраняя причину в previous.
                // Ошибки PHP (нет метода, нет аргумента) он не ловит: они
                // прилетят сюда как Error и уронят тест — это и нужно.
                $this->assertInstanceOf('Exception', $e->getPrevious(), $name.': отказ должен прийти из сервиса, а не из реестра');
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

        try
        {
            \DB::select('id')->from('erp_405_inspections')->limit(1)->execute();

            $available = TRUE;
        }
        catch (\Exception $e)
        {
            $available = FALSE;
        }

        return $available;
    }

    /**
     * Контроль теста: номер с пометкой ТЕСТ-405, чтобы уборка его узнала.
     *
     * @param   array   $data
     * @return  \Model_Erp405_Inspection
     */
    protected function make_inspection(array $data = array())
    {
        // Номер остаётся рабочим (ПК-ГГГГ-НННННН) — его формат проверяет тест
        // жизненного цикла; тестовые записи узнаём по пометке в примечании.
        return $this->inspections->register(array_merge(array(
            'kind' => 'incoming',
            'qty'  => 10,
            'note' => 'ТЕСТ-405: автоматический тест',
        ), $data));
    }

    /**
     * @param   string $code
     * @return  int
     */
    protected function code_id($code)
    {
        foreach ($this->codes->search(array('active_only' => FALSE), 200, 0) as $item)
        {
            if ((string) $item->code === $code)
            {
                return (int) $item->id;
            }
        }

        $created = $this->codes->create(array('code' => $code, 'name' => 'ТЕСТ-405 '.$code, 'category' => 'other'));

        return (int) $created->id;
    }

    /**
     * Склад теста нужного типа (создаётся один раз на прогон).
     *
     * @param   string $type main|quarantine|scrap
     * @return  int
     */
    protected function warehouse($type = 'main')
    {
        if (isset($this->warehouses[$type]))
        {
            return $this->warehouses[$type];
        }

        if ( ! class_exists('Service_Erp401_Stock'))
        {
            $this->markTestSkipped('Модуль склада erp401 недоступен');
        }

        $code = 'ТЕСТ-405-'.mb_strtoupper(mb_substr($type, 0, 1));

        $existing = \DB::select('id')->from('erp_401_warehouses')
            ->where('code', '=', $code)
            ->limit(1)
            ->execute()
            ->current();

        if ($existing)
        {
            $this->warehouses[$type] = (int) $existing['id'];

            return $this->warehouses[$type];
        }

        $warehouse = \Service_Erp401_Stock::instance()->create_warehouse(array(
            'code' => $code,
            'name' => 'ТЕСТ-405 '.$type,
            'type' => $type,
        ));

        $this->warehouses[$type] = (int) $warehouse->id;

        return $this->warehouses[$type];
    }

    /**
     * Идентификаторы складов теста (в том числе оставшиеся от упавшего прогона).
     *
     * @return  array
     */
    protected function warehouse_ids()
    {
        $ids = array_values($this->warehouses);

        foreach (\DB::select('id')->from('erp_401_warehouses')
            ->where('code', 'LIKE', 'ТЕСТ-405%')
            ->execute()
            ->as_array() as $row)
        {
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Идентификаторы контролей, созданных тестом (по пометке в примечании и по
     * документу приёмки теста).
     *
     * @return  array
     */
    protected function test_inspection_ids()
    {
        $ids = array();

        $rows = \DB::select('id')->from('erp_405_inspections')
            ->where('note', 'LIKE', '%ТЕСТ-405%')
            ->execute()
            ->as_array();

        foreach ($rows as $row)
        {
            $ids[] = (int) $row['id'];
        }

        $rows = \DB::select('i.id')
            ->from(array('erp_405_inspections', 'i'))
            ->join(array('erp_403_receipt_lines', 'l'), 'INNER')
            ->on('l.id', '=', \DB::expr('i.source_ref::int'))
            ->join(array('erp_403_receipts', 'r'), 'INNER')
            ->on('r.id', '=', 'l.receipt_id')
            ->where('r.number', 'LIKE', 'ПР-ТЕСТ-405-%')
            ->execute()
            ->as_array();

        foreach ($rows as $row)
        {
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param   string $table
     * @param   string $pattern
     * @return  array
     */
    protected function ids($table, $pattern)
    {
        $ids = array();

        foreach (\DB::select('id')->from($table)->where('number', 'LIKE', $pattern)->execute()->as_array() as $row)
        {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @return  int|null
     */
    protected function item_id()
    {
        static $item_id = NULL;

        if ($item_id !== NULL)
        {
            return $item_id;
        }

        $row = \DB::select('id')->from('erp_227_objects')
            ->where('is_latest', '=', 't')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->execute()
            ->current();

        $item_id = $row ? (int) $row['id'] : 0;

        return $item_id ?: NULL;
    }

    /**
     * @return  int|null
     */
    protected function supplier_id()
    {
        if ( ! class_exists('Service_Erp301_Counterparties'))
        {
            return NULL;
        }

        // Поставщик нужен только для входного контроля: берём первого, кого
        // отдаёт владелец справочника.
        $row = \DB::select('id')->from('erp_301_counterparties')->order_by('id', 'ASC')->limit(1)->execute()->current();

        return $row ? (int) $row['id'] : NULL;
    }

    /**
     * Машинный код ошибки сервиса (проверяем поведение, а не текст).
     *
     * @param   callable $callback
     * @return  string|null
     */
    protected function reason(callable $callback)
    {
        try
        {
            $callback();
        }
        catch (\Exception_Erp405_Quality $e)
        {
            return $e->reason();
        }

        return NULL;
    }
}
