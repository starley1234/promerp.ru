<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты планирования потребностей (модуль erp407_planning).
 *
 * Проверяют главное обещание модуля: программа превращается в расчёт
 * потребности, расчёт — в дефициты с датами заказа, дефициты — в предложения,
 * а предложение передаётся в исполнение (заказ производства или заявка
 * закупки). Плюс то, что обычно ломается: страницы с реальными данными и
 * отсутствие задвоения при повторных запусках.
 *
 * Тесты работают на своих данных (программы `ПП-ТЕСТ-407-*`, заказы
 * `ПО-ТЕСТ-407-*`) и убирают их в tearDown, поэтому демонстрационные данные не
 * страдают. Если таблиц модуля нет — тесты пропускаются.
 */
class Erp407PlanningTest extends TestCase
{
    /** @var \Service_Erp407_Programs */
    protected $programs;

    /** @var \Service_Erp407_Mrp */
    protected $mrp;

    /** @var \Service_Erp407_Proposals */
    protected $proposals;

    /** @var int|null Позиция номенклатуры из реестра erp227 (для проверки остатков) */
    protected $item_id;

    /** @var array Созданные заказы производства (для уборки) */
    protected $order_ids = array();

    /** @var array Созданные программы: номер может быть и сгенерированным, поэтому помним id */
    protected $program_ids = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль планирования недоступен — выполните миграции erp407');
            return;
        }

        $this->programs  = \Service_Erp407_Programs::instance();
        $this->mrp       = \Service_Erp407_Mrp::instance();
        $this->proposals = \Service_Erp407_Proposals::instance();

        $this->item_id = $this->find_item();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $program_ids = $this->program_ids;

            foreach (\DB::select('id')->from('erp_407_programs')
                ->where('number', 'LIKE', 'ПП-ТЕСТ-407-%')->execute()->as_array() as $row)
            {
                $program_ids[] = (int) $row['id'];
            }

            $program_ids = array_unique(array_filter($program_ids));

            // Предложения теста: и по программам, и заведённые вручную (у них
            // нет программы, зато есть узнаваемое имя)
            $proposal_ids = array();

            $proposals = \DB::select('id', 'target_ref')->from('erp_407_proposals')
                ->where('name', 'LIKE', 'ТЕСТ-407%');

            if ($program_ids)
            {
                $proposals->or_where('program_id', 'IN', $program_ids);
            }

            foreach ($proposals->execute()->as_array() as $row)
            {
                $proposal_ids[] = (int) $row['id'];

                if (strpos((string) $row['target_ref'], 'erp402:') === 0)
                {
                    $this->order_ids[] = (int) substr($row['target_ref'], strlen('erp402:'));
                }
            }

            if ($proposal_ids)
            {
                \DB::delete('erp_407_proposals')->where('id', 'IN', $proposal_ids)->execute();
            }

            if ($program_ids)
            {
                \DB::delete('erp_407_requirements')->where('program_id', 'IN', $program_ids)->execute();
                \DB::delete('erp_407_program_lines')->where('program_id', 'IN', $program_ids)->execute();
                \DB::delete('erp_407_programs')->where('id', 'IN', $program_ids)->execute();
            }

            // Заказы, созданные тестом напрямую
            foreach (\DB::select('id')->from('erp_402_orders')
                ->where('number', 'LIKE', 'ПО-ТЕСТ-407-%')->execute()->as_array() as $row)
            {
                $this->order_ids[] = (int) $row['id'];
            }

            $this->order_ids = array_unique(array_filter($this->order_ids));

            foreach (array('erp_402_order_items', 'erp_408_operations') as $table)
            {
                try
                {
                    \DB::delete($table)->where('order_id', 'IN', $this->order_ids)->execute();
                }
                catch (\Exception $e)
                {
                    // Таблицы соседнего модуля может не быть — заказ всё равно убираем
                }
            }

            if ($this->order_ids)
            {
                \DB::delete('erp_402_orders')->where('id', 'IN', $this->order_ids)->execute();
            }
        }
        catch (\Exception $e)
        {
            // Уборка не должна закрывать тест: её результат виден по счётчикам строк
        }
    }

    // -------------------------------------------------------------- жизненный цикл --

    public function test_program_number_is_generated_in_project_format()
    {
        $program = $this->make_program(array(), FALSE);

        $this->assertMatchesRegularExpression('/^ПП-\d{4}-\d{6}$/', (string) $program->number,
            'номер программы — ПП-ГГГГ-НННННН');
        $this->assertSame('draft', $program->status);
    }

    public function test_program_lifecycle_lines_approve_close()
    {
        $program = $this->make_program();

        $first  = $this->programs->add_line($program->id, array('name' => 'ТЕСТ-407 изделие', 'qty' => 5));
        $second = $this->programs->add_line($program->id, array('name' => 'ТЕСТ-407 изделие 2', 'qty' => 3));

        $this->assertSame(10, (int) $first->line_no, 'нумерация строк идёт с шагом 10');
        $this->assertSame(20, (int) $second->line_no);

        // Утверждать пустую программу нельзя — это не расчёт, а обязательство
        $empty = $this->make_program();

        try
        {
            $this->programs->approve($empty->id);
            $this->fail('пустую программу утвердить нельзя');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('no_demand', $e->reason(), 'причина отказа — в программе нет строк');
        }

        $approved = $this->programs->approve($program->id);

        $this->assertSame('approved', $approved->status);

        $closed = $this->programs->close($program->id);

        $this->assertSame('closed', $closed->status);
        $this->assertFalse($closed->is_editable(), 'закрытая программа не правится');

        // Закрытая программа не считает потребность
        try
        {
            $this->mrp->calculate($program->id);
            $this->fail('по закрытой программе расчёт невозможен');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('invalid_transition', $e->reason());
        }
    }

    public function test_calculate_requires_demand()
    {
        $program = $this->make_program();

        try
        {
            $this->mrp->calculate($program->id);
            $this->fail('без строк считать нечего');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('no_demand', $e->reason());
        }
    }

    // ----------------------------------------------------------------- расчёт --

    public function test_mrp_calculates_deficit_and_order_date()
    {
        $program = $this->make_program();
        $due     = date('Y-m-d', strtotime('+20 days'));

        $this->programs->add_line($program->id, array(
            'name'     => 'ТЕСТ-407 материал без номенклатуры',
            'qty'      => 10,
            'due_date' => $due,
        ));

        $result = $this->mrp->calculate($program->id);

        $this->assertSame(1, count($result['rows']), 'одна позиция спроса — одна строка расчёта');

        $row = reset($result['rows']);

        $this->assertSame(10.0, (float) $row['demand_qty']);
        $this->assertSame(10.0, (float) $row['deficit_qty']);
        $this->assertSame(10.0, (float) $row['order_qty']);
        $this->assertSame('purchase', $row['supply_type'], 'без техкарты изделие закупается');
        $this->assertSame(7, (int) $row['lead_time_days'], 'срок поставки по умолчанию — неделя');
        $this->assertSame(date('Y-m-d', strtotime($due.' -7 days')), $row['order_date'],
            'дата заказа = срок минус срок поставки');
        $this->assertFalse((bool) $row['late']);
        $this->assertNotEmpty($result['run_uuid']);

        // Снимок сохранён и привязан к программе — именно его показывают экраны
        $program = $this->programs->get($program->id);

        $this->assertSame($result['run_uuid'], (string) $program->run_uuid);
        $this->assertNotEmpty((string) $program->run_at);

        $snapshot = $this->mrp->requirements($program);

        $this->assertCount(1, $snapshot);
        $this->assertSame('ТЕСТ-407 материал без номенклатуры', (string) $snapshot[0]->name);
        $this->assertSame(0.0, (float) $snapshot[0]->coverage(), 'ни склада, ни незавершёнки — покрытия нет');

        $summary = $this->mrp->summarize($result['rows']);

        $this->assertSame(1, (int) $summary['deficit']);
        $this->assertSame(10.0, (float) $summary['shortage']);
        $this->assertSame(1, (int) $summary['purchase']);

        // PostgreSQL отдаёт boolean строкой, поэтому «просрочено» не должно
        // загораться само: у позиции срок заказа ещё впереди
        $this->assertFalse($snapshot[0]->is_late(), 'срок заказа впереди — не просрочено');

        $from_db = $this->mrp->summarize(array_map(function ($row) {
            return $row->as_array();
        }, $snapshot));

        $this->assertSame(0, (int) $from_db['late'], 'снимок из БД не считает просроченным то, что не просрочено');

        $this->assertNotEmpty($result['warnings'], 'несоответствие номенклатуре должно быть озвучено предупреждением');
    }

    public function test_mrp_marks_late_positions()
    {
        $program = $this->make_program();

        // Срок наступил завтра, а заказывать надо было неделю назад
        $this->programs->add_line($program->id, array(
            'name'     => 'ТЕСТ-407 срочное',
            'qty'      => 2,
            'due_date' => date('Y-m-d', strtotime('+1 day')),
        ));

        $result = $this->mrp->calculate($program->id);
        $row    = reset($result['rows']);

        $this->assertTrue((bool) $row['late'], 'позиция с прошедшей датой заказа помечается late');
        $this->assertSame(1, (int) $result['summary']['late']);

        // и то же самое после чтения снимка из БД: late приходит строкой 't'
        $program = $this->programs->get($program->id);
        $snapshot = $this->mrp->requirements($program);

        $this->assertTrue($snapshot[0]->is_late(), 'просроченная позиция остаётся просроченной в снимке');

        $from_db = $this->mrp->summarize(array_map(function ($row) {
            return $row->as_array();
        }, $snapshot));

        $this->assertSame(1, (int) $from_db['late'], 'сводка по снимку тоже видит просрочку');
    }

    public function test_mrp_subtracts_stock_when_item_is_mapped()
    {
        if ( ! $this->item_id OR ! class_exists('Service_Erp401_Stock'))
        {
            $this->markTestSkipped('Нет номенклатуры erp227 или склада erp401 для проверки покрытия');
        }

        $stock = \Service_Erp401_Stock::instance();
        $code  = 'WH-TEST-407';

        $warehouse = \ORM::factory('Erp401_Warehouse')->where('code', '=', $code)->find();

        if ( ! $warehouse->loaded())
        {
            $warehouse = $stock->create_warehouse(array('code' => $code, 'name' => 'Склад теста erp407', 'type' => 'production'));
        }

        try
        {
            $stock->adjust($warehouse->id, $this->item_id, 4.0, array('uom_code' => 'pcs', 'note' => 'Тестовый остаток erp407'));
        }
        catch (\Exception $e)
        {
            // Склад не разрешает «привести к тому же значению» — значит остаток уже нужный
        }

        $program = $this->make_program();

        $this->programs->add_line($program->id, array(
            'item_id'  => $this->item_id,
            'name'     => 'ТЕСТ-407 позиция из реестра',
            'qty'      => 10,
            'uom_code' => 'pcs',
            'due_date' => date('Y-m-d', strtotime('+14 days')),
        ));

        $result = $this->mrp->calculate($program->id, array('warehouse_id' => $warehouse->id));
        $row    = reset($result['rows']);

        $this->assertSame(10.0, (float) $row['demand_qty']);
        $this->assertSame(4.0, (float) $row['stock_qty'], 'остаток склада виден в расчёте');
        $this->assertSame(4.0, (float) $row['covered_qty'], 'склад покрывает часть спроса');
        $this->assertSame(6.0, (float) $row['deficit_qty'], 'нехватка — это спрос минус покрытие');
    }

    // ------------------------------------------------------------- предложения --

    public function test_proposals_are_created_from_deficits_without_duplicates()
    {
        $program = $this->make_program();

        // Срок выпуска завтра, а срок поставки — неделя: заказ уже просрочен
        $this->programs->add_line($program->id, array(
            'name'     => 'ТЕСТ-407 дефицит',
            'qty'      => 3,
            'due_date' => date('Y-m-d', strtotime('+1 day')),
        ));

        $this->programs->approve($program->id);
        $this->mrp->calculate($program->id);

        $first = $this->proposals->create_from_requirements($program->id);

        $this->assertSame(1, (int) $first['created']);
        $this->assertSame(0, (int) $first['skipped']);

        $second = $this->proposals->create_from_requirements($program->id);

        $this->assertSame(0, (int) $second['created'], 'повторный вызов не задваивает предложения');
        $this->assertSame(1, (int) $second['skipped'], 'по позиции уже есть открытое предложение');

        $rows = $this->proposals->search(array('program_id' => $program->id));

        $this->assertCount(1, $rows);
        $this->assertMatchesRegularExpression('/^ПЗ-\d{4}-\d{6}$/', (string) $rows[0]->number);
        $this->assertSame('purchase', $rows[0]->kind);
        $this->assertSame(3.0, (float) $rows[0]->qty);
        $this->assertSame(1, (int) $rows[0]->priority, 'предложение по просроченному дефициту — срочное');
        $this->assertSame('new', $rows[0]->status);
        $this->assertNotEmpty((string) $rows[0]->order_date, 'предложение помнит дату заказа из расчёта');
    }

    public function test_mcp_deficits_returns_rows_without_type_error()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $program = $this->make_program();
        $this->programs->add_line($program->id, array(
            'name' => 'ТЕСТ-407 MCP дефицит', 'qty' => 4,
            'due_date' => date('Y-m-d', strtotime('+5 days')),
        ));
        $this->programs->approve($program->id);
        $this->mrp->calculate($program->id);

        $result = \Service_Erp804_Registry::instance()->call('planning.deficits', array(
            'program_id' => (int) $program->id,
            'limit' => 50,
        ));

        $this->assertNotEmpty($result['rows']);
        $this->assertGreaterThan(0, (int) $result['totals']['deficit']);
        $this->assertArrayHasKey('covered_qty', $result['rows'][0]);
    }

    public function test_proposal_flow_approve_export_complete()
    {
        $program = $this->make_program();
        $this->programs->add_line($program->id, array('name' => 'ТЕСТ-407 поток', 'qty' => 1));
        $this->mrp->calculate($program->id);

        $this->proposals->create_from_requirements($program->id);

        $found    = $this->proposals->search(array('program_id' => $program->id));
        $proposal = reset($found);

        // Передача без утверждения — запрещена: сначала решение, потом заказ
        try
        {
            $this->proposals->export($proposal->id);
            $this->fail('неутверждённое предложение передавать нельзя');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('invalid_transition', $e->reason());
        }

        $this->assertSame('approved', $this->proposals->approve($proposal->id)->status);

        // Повторное утверждение — без ошибки и без новых записей
        $this->assertSame('approved', $this->proposals->approve($proposal->id)->status);

        $result = $this->proposals->export($proposal->id);

        $this->assertSame('exported', $result['proposal']->status);

        // С подключённым модулем закупок (erp403) предложение превращается в
        // настоящую заявку: очередь waiting:erp403 больше не нужна.
        if (class_exists('Service_Erp403_Requests'))
        {
            $this->assertMatchesRegularExpression('/^erp403:\d+$/', (string) $result['target'],
                'закупка передаётся в модуль закупок ссылкой erp403:<id>');
            $this->assertNull($result['warning'], 'заявка создана — предупреждать не о чем');

            $request_id = (int) substr($result['target'], strlen('erp403:'));
            $request    = \Service_Erp403_Requests::instance()->get($request_id);

            $this->assertNotNull($request, 'заявка закупки действительно создана');
            $this->assertSame((string) $proposal->number, (string) $request->source_ref,
                'в заявке видно, чьим предложением она порождена');
            $this->assertSame('erp407', (string) $request->source);

            // Уборка: заявку и её строки за собой уносим.
            \DB::delete('erp_403_request_lines')->where('request_id', '=', $request_id)->execute();
            \DB::delete('erp_403_requests')->where('id', '=', $request_id)->execute();
        }
        else
        {
            $this->assertSame('waiting:erp403', $result['target'],
                'без модуля закупок предложение ждёт в очереди, а не теряется');
            $this->assertNotNull($result['warning'], 'агент и человек должны знать, что заявка ещё не создана');
        }

        // После передачи правки закрыты
        try
        {
            $this->proposals->update($proposal->id, array('qty' => 99));
            $this->fail('переданное предложение не правится');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('proposal_locked', $e->reason());
        }

        $this->assertSame('done', $this->proposals->complete($proposal->id)->status);
    }

    public function test_program_is_filled_from_open_production_orders()
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            $this->markTestSkipped('Модуль производства erp402 недоступен');
        }

        $page   = \Service_Erp402_Orders::instance()->search(array(
            'status' => array('planned', 'released', 'in_progress'),
        ), 5);

        if (empty($page['rows']))
        {
            $this->markTestSkipped('Нет открытых заказов производства для наполнения программы');
        }

        $program = $this->make_program();
        $result  = $this->programs->fill_from_orders($program->id);

        $this->assertGreaterThan(0, (int) $result['added'], 'строки программы берутся из открытых заказов');
        $this->assertGreaterThan(0, count($this->programs->lines($program->id)));

        $program = $this->programs->get($program->id);

        $this->assertSame('orders', (string) $program->source);

        $line = $this->programs->lines($program->id)[0];

        $first = reset($page['rows']);
        $first = is_array($first) ? (object) $first : $first;

        $this->assertSame((string) $first->number, (string) $line->code,
            'строка ссылается на заказ производства');
        // Повторный сбор не задваивает уже включённые заказы
        $again = $this->programs->fill_from_orders($program->id);

        $this->assertSame(0, (int) $again['added'], 'повторный сбор не задваивает строки');
    }

    public function test_production_proposal_creates_erp402_order()
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            $this->markTestSkipped('Модуль производства erp402 недоступен');
        }

        $proposal = $this->proposals->create(array(
            'kind'       => 'produce',
            'name'       => 'ТЕСТ-407 своё производство',
            'qty'        => 7,
            'uom_code'   => 'шт',
            'due_date'   => date('Y-m-d', strtotime('+10 days')),
            'order_date' => date('Y-m-d', strtotime('+3 days')),
        ));

        $this->proposals->approve($proposal->id);

        $result = $this->proposals->export($proposal->id);

        $this->assertMatchesRegularExpression('/^erp402:\d+$/', (string) $result['target'],
            'выпуск передаётся в производство ссылкой на заказ');

        $order_id = (int) substr($result['target'], strlen('erp402:'));
        $this->order_ids[] = $order_id;

        $order = \Service_Erp402_Orders::instance()->get($order_id);

        $this->assertNotNull($order, 'заказ производства создан');
        $this->assertSame('ТЕСТ-407 своё производство', (string) $order->title);
        $this->assertSame(7.0, (float) $order->qty);
        $this->assertSame(date('Y-m-d', strtotime('+10 days')), (string) $order->target_date);
        $this->assertStringContainsString($proposal->number, (string) $order->note,
            'в заказе видно, из какого предложения он появился');
    }

    public function test_cancelled_proposal_is_not_repeatable()
    {
        $proposal = $this->proposals->create(array(
            'kind' => 'purchase',
            'name' => 'ТЕСТ-407 отмена',
            'qty'  => 1,
        ));

        $this->assertSame('cancelled', $this->proposals->cancel($proposal->id, 'нет бюджета')->status);

        try
        {
            $this->proposals->cancel($proposal->id);
            $this->fail('отклонённое предложение отклонять больше нечего');
        }
        catch (\Exception_Erp407_Planning $e)
        {
            $this->assertSame('invalid_transition', $e->reason());
        }

        $this->assertStringContainsString('нет бюджета', (string) $this->proposals->get($proposal->id)->note,
            'причина отказа остаётся в предложении и уходит в журнал');
    }

    public function test_dashboard_and_pages_render_on_real_data()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $program = $this->make_program();

        $this->programs->add_line($program->id, array(
            'name'     => 'ТЕСТ-407 страница',
            'qty'      => 4,
            'due_date' => date('Y-m-d', strtotime('+5 days')),
        ));

        $this->mrp->calculate($program->id);
        $this->proposals->create_from_requirements($program->id);

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        $result = $smoke->run(array(
            array('url' => '/planning', 'name' => 'Доска планирования'),
            array('url' => '/planning/deficits', 'name' => 'Дефициты'),
            array('url' => '/planning/deficits?program_id='.(int) $program->id, 'name' => 'Дефициты программы'),
            array('url' => '/planning/programs', 'name' => 'Список программ'),
            array('url' => '/planning/programs/view/'.(int) $program->id, 'name' => 'Карточка программы'),
            array('url' => '/planning/programs/edit/'.(int) $program->id, 'name' => 'Правка программы'),
            array('url' => '/planning/proposals', 'name' => 'Список предложений'),
            array('url' => '/planning/proposals?program_id='.(int) $program->id, 'name' => 'Предложения программы'),
            array('url' => '/planning/api/stats', 'name' => 'API: сводка'),
            array('url' => '/planning/api/program?id='.(int) $program->id, 'name' => 'API: программа'),
            array('url' => '/planning/api/requirements?program_id='.(int) $program->id, 'name' => 'API: потребность'),
            array('url' => '/planning/widget/summary', 'name' => 'Виджет: сводка'),
        ));

        foreach ($result['rows'] as $row)
        {
            $this->assertSame(NULL, $row['error'], $row['name'].': '.$row['status'].' '.$row['error']);
            $this->assertSame(200, (int) $row['status'], $row['name'].' отвечает '.$row['status']);
            $this->assertGreaterThan(0, (int) $row['bytes'], $row['name'].' отдаёт пустой ответ');
        }
    }

    public function test_mcp_catalogue_exposes_planning_tools()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $names = array();

        foreach (\Service_Erp804_Registry::instance()->tools() as $name => $tool)
        {
            if (strpos($name, 'planning.') === 0)
            {
                $names[] = $name;
            }
        }

        $this->assertContains('planning.mrp.calculate', $names);
        $this->assertContains('planning.deficits', $names);
        $this->assertContains('planning.proposals.create', $names);
        $this->assertContains('planning.proposal.export', $names);

        $tools = \Service_Erp804_Registry::instance()->tools();

        $this->assertTrue((bool) $tools['planning.proposal.export']['approval'],
            'передача предложения в исполнение требует подтверждения человека');
        $this->assertSame('erp407.approve', $tools['planning.proposal.export']['scope']);
    }

    // ------------------------------------------------------------------ данные --

    /**
     * Модуль доступен, если миграции применены.
     *
     * @return  bool
     */
    protected function available()
    {
        try
        {
            return (bool) \DB::query(\Database::SELECT, "SELECT to_regclass('public.erp_407_programs') IS NOT NULL AS ok")
                ->execute()->get('ok');
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Тестовая программа с узнаваемым номером.
     *
     * @param   array   $data
     * @param   bool    $with_number
     * @return  \Model_Erp407_Program
     */
    protected function make_program(array $data = array(), $with_number = TRUE)
    {
        if ($with_number AND empty($data['number']))
        {
            $data['number'] = 'ПП-ТЕСТ-407-'.strtoupper(bin2hex(random_bytes(4)));
        }

        $program = $this->programs->create($data + array(
            'title'       => 'ТЕСТ-407 программа',
            'period_from' => date('Y-m-d'),
            'period_to'   => date('Y-m-d', strtotime('+30 days')),
        ));

        $this->program_ids[] = (int) $program->id;

        return $program;
    }

    /**
     * Позиция номенклатуры из реестра erp227 (или NULL).
     *
     * @return  int|null
     */
    protected function find_item()
    {
        try
        {
            $row = \DB::select('id')->from('erp_227_objects')
                ->where('is_latest', '=', TRUE)
                ->order_by('id', 'ASC')
                ->limit(1)
                ->execute()
                ->current();

            return $row ? (int) $row['id'] : NULL;
        }
        catch (\Exception $e)
        {
            return NULL;
        }
    }
}
