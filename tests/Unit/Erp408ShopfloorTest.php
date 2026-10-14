<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты цеха (модуль erp408_shopfloor).
 *
 * Проверяют главное обещание модуля: заказ получает маршрут операций, работу
 * можно начать и закрыть с планшета, факт (годные, брак, время, простой)
 * остаётся в журнале отметок, а событие уходит в производство.
 *
 * Тесты работают на своих данных (`ТЕСТ-408-*`, заказы `ПО-ТЕСТ-408-*`) и
 * удаляют их в tearDown, поэтому демонстрационные данные не страдают. Если
 * таблиц модуля нет — тесты пропускаются.
 */
class Erp408ShopfloorTest extends TestCase
{
    /** @var \Service_Erp408_Operations */
    protected $operations;

    /** @var \Service_Erp408_WorkCenters */
    protected $centers;

    /** @var \Service_Erp408_Shifts */
    protected $shifts;

    /** @var \Service_Erp402_Orders|null */
    protected $orders;

    /** @var array Созданные техкарты (для уборки) */
    protected $cards = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль цеха недоступен — выполните миграции erp408');
            return;
        }

        $this->operations = \Service_Erp408_Operations::instance();
        $this->centers    = \Service_Erp408_WorkCenters::instance();
        $this->shifts     = \Service_Erp408_Shifts::instance();

        if (class_exists('Service_Erp402_Orders'))
        {
            $this->orders = \Service_Erp402_Orders::instance();
        }
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $order_ids = array();

            foreach (\DB::select('id')->from('erp_402_orders')
                ->where('number', 'LIKE', 'ПО-ТЕСТ-408-%')->execute()->as_array() as $row)
            {
                $order_ids[] = (int) $row['id'];
            }

            if ($order_ids)
            {
                // Отметки уходят вместе с операциями (ON DELETE CASCADE)
                \DB::delete('erp_408_operations')->where('order_id', 'IN', $order_ids)->execute();
                \DB::delete('erp_402_order_items')->where('order_id', 'IN', $order_ids)->execute();
                \DB::delete('erp_402_orders')->where('id', 'IN', $order_ids)->execute();
            }

            $center_ids = array();
            $shift_ids  = array();

            foreach (\DB::select('id')->from('erp_408_work_centers')
                ->where('code', 'LIKE', 'ТЕСТ-408-%')->execute()->as_array() as $row)
            {
                $center_ids[] = (int) $row['id'];
            }

            foreach (\DB::select('id')->from('erp_408_shifts')
                ->where('code', 'LIKE', 'ТЕСТ-408-%')->execute()->as_array() as $row)
            {
                $shift_ids[] = (int) $row['id'];
            }

            if ($center_ids)
            {
                \DB::delete('erp_408_calendar')->where('work_center_id', 'IN', $center_ids)->execute();
                \DB::delete('erp_408_operations')->where('work_center_id', 'IN', $center_ids)->execute();
                \DB::delete('erp_408_work_centers')->where('id', 'IN', $center_ids)->execute();
            }

            if ($shift_ids)
            {
                \DB::delete('erp_408_calendar')->where('shift_id', 'IN', $shift_ids)->execute();
                \DB::delete('erp_408_shifts')->where('id', 'IN', $shift_ids)->execute();
            }

            foreach ($this->cards as $card_id)
            {
                \DB::delete('erp_228_tech_operations')
                    ->where('document_id', 'IN', \DB::select('id')->from('erp_228_tech_documents')->where('tech_card_id', '=', $card_id))
                    ->execute();
                \DB::delete('erp_228_tech_documents')->where('tech_card_id', '=', $card_id)->execute();
                \DB::delete('erp_228_tech_cards')->where('id', '=', $card_id)->execute();
            }
        }
        catch (\Exception $e)
        {
            // уборка не должна ломать тест
        }
    }

    // ------------------------------------------------------------------ тесты --

    public function test_work_center_is_created_and_code_is_unique()
    {
        $center = $this->make_center();

        $this->assertSame(TRUE, $center['is_active']);
        $this->assertSame('Механический участок', $center['workshop']);

        $reason = NULL;

        try
        {
            $this->make_center(array('code' => $center['code']));
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Повтор кода рабочего центра должен быть отклонён');
    }

    public function test_center_with_operations_cannot_be_deleted()
    {
        $center    = $this->make_center();
        $operation = $this->make_operation(array('work_center_id' => $center['id']));

        $reason = NULL;

        try
        {
            $this->centers->delete($center['id']);
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Центр с операциями удалять нельзя');
        $this->assertNotNull($this->operations->get($operation['id']));

        $this->assertEquals(TRUE, $this->centers->delete($this->make_center()['id']), 'Центр без операций удаляется');
    }

    public function test_disabled_center_cannot_be_assigned()
    {
        $center    = $this->make_center();
        $operation = $this->make_operation();

        $this->centers->set_active($center['id'], FALSE);

        $reason = NULL;

        try
        {
            $this->operations->assign($operation['id'], $center['id']);
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('no_work_center', $reason);
    }

    public function test_calendar_defines_working_hours()
    {
        $center = $this->make_center();
        $shift  = $this->make_shift();

        $this->shifts->set_day('2026-11-05', $center['id'], $shift['id'], TRUE, 6, 'Сокращённый день');
        $this->shifts->set_day('2026-11-07', $center['id'], $shift['id'], FALSE, 0, 'Ремонт');

        $this->assertSame(6.0, $this->shifts->hours_for($center['id'], '2026-11-05'));
        $this->assertSame(0.0, $this->shifts->hours_for($center['id'], '2026-11-07'));
        $this->assertSame(FALSE, $this->shifts->is_working('2026-11-07', $center['id']));
        $this->assertSame('2026-11-09', $this->shifts->next_working_day('2026-11-07', $center['id']));
    }

    public function test_calendar_generate_fills_month_for_one_center()
    {
        $center = $this->make_center();
        $shift  = $this->make_shift();

        $days = $this->shifts->generate('2026-12-01', '2026-12-07', array($center['id']), array($shift['id']));

        $this->assertSame(7, $days);

        $rows = $this->shifts->calendar('2026-12-01', '2026-12-07', $center['id']);

        $this->assertSame(7, count($rows));

        $working = 0;

        foreach ($rows as $row)
        {
            if ($row['is_working'])
            {
                $working++;
            }
        }

        $this->assertSame(5, $working, 'Будни рабочие, суббота и воскресенье — нет');
    }

    public function test_plan_order_builds_route_from_tech_card()
    {
        $order = $this->make_order_with_card(4);
        $count = $this->operations->plan_order($order);

        $this->assertGreaterThan(0, $count);

        $route = $this->operations->for_order($order->id);

        $this->assertSame($count, count($route));

        foreach ($route as $operation)
        {
            $this->assertSame('planned', $operation['status']);
            $this->assertSame('tech_card', $operation['source']);
            $this->assertSame(4.0, (float) $operation['qty_planned']);
            $this->assertNotSame('', trim($operation['name']));
        }

        $this->assertSame($count, $this->operations->plan_order($order), 'Повторное планирование не должно дублировать операции');
    }

    public function test_plan_order_requires_route()
    {
        $order = $this->make_order();

        $reason = NULL;

        try
        {
            $this->operations->plan_order($order);
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('no_route', $reason, 'Без техкарты и состава маршрут не строится');
    }

    public function test_orders_without_route_queue_is_filled_and_emptied()
    {
        $order = $this->make_order_with_card(2);

        // Очередь говорит о заказах, которые уже в производстве: черновик в неё
        // не попадает, поэтому заказ сначала утверждается планом (erp402)
        $this->orders->add_item($order->id, array('name' => 'Пруток 20', 'qty_per_unit' => 1, 'uom_code' => 'кг'));
        $this->orders->plan($order->id);

        $queue = array();
        foreach ($this->operations->orders_without_route(50) as $row)
        {
            $queue[] = (int) $row['id'];
        }

        $this->assertContains((int) $order->id, $queue, 'Заказ без маршрута должен попасть в очередь');

        $this->operations->plan_order($this->orders->get($order->id));

        $queue = array();
        foreach ($this->operations->orders_without_route(50) as $row)
        {
            $queue[] = (int) $row['id'];
        }

        $this->assertNotContains((int) $order->id, $queue, 'После раскладки заказ уходит из очереди');
    }

    public function test_assign_moves_operation_to_working_day()
    {
        $center   = $this->make_center();
        $shift    = $this->make_shift();
        $order    = $this->make_order_with_card(1);
        $this->operations->plan_order($order);
        $route    = $this->operations->for_order($order->id);
        $operation = $route[0];

        // Суббота объявлена нерабочей: назначение должно уехать на понедельник
        $this->shifts->set_day('2026-11-07', $center['id'], $shift['id'], FALSE, 0, 'Выходной');

        $assigned = $this->operations->assign($operation['id'], $center['id'], $shift['id'], '2026-11-07');

        $this->assertSame($center['id'], $assigned['work_center_id']);
        $this->assertSame('2026-11-09', $assigned['planned_day']);
        $this->assertSame($shift['id'], $assigned['shift_id']);
    }

    public function test_operation_lifecycle_records_fact()
    {
        $operation = $this->make_operation(array('qty_planned' => 10));

        $started = $this->operations->act($operation['id'], 'start');
        $this->assertSame('in_progress', $started['status']);

        $output = $this->operations->act($operation['id'], 'output', array('qty_ok' => 4, 'qty_scrap' => 1, 'comment' => 'первая партия'));
        $this->assertSame(4.0, (float) $output['qty_ok']);
        $this->assertSame(1.0, (float) $output['qty_scrap']);

        $paused = $this->operations->act($operation['id'], 'pause', array('comment' => 'перерыв'));
        $this->assertSame('paused', $paused['status']);

        $resumed = $this->operations->act($operation['id'], 'resume');
        $this->assertSame('in_progress', $resumed['status']);

        $finished = $this->operations->act($operation['id'], 'finish', array('qty_ok' => 6));
        $this->assertSame('done', $finished['status']);
        $this->assertSame(10.0, (float) $finished['qty_ok']);
        $this->assertSame(100, (int) $finished['progress']);
        $this->assertNotNull($finished['actual_end']);

        $kinds = array();

        foreach ($this->operations->worklogs($operation['id'], 20) as $log)
        {
            $kinds[] = $log['kind'];
        }

        $this->assertSame(array('finish', 'output', 'resume', 'pause', 'output', 'start'), $kinds, 'Журнал отметок хранит все нажатия по порядку');
    }

    public function test_output_validates_quantity()
    {
        $operation = $this->make_operation(array('qty_planned' => 10));
        $this->operations->act($operation['id'], 'start');

        $reason = NULL;

        try
        {
            $this->operations->act($operation['id'], 'output', array('qty_ok' => 0));
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('qty_invalid', $reason, 'Нулевая выработка — ошибка ввода');

        $reason = NULL;

        try
        {
            $this->operations->act($operation['id'], 'output', array('qty_ok' => 500));
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('qty_invalid', $reason, 'Опечатка «500 вместо 5» должна ловиться');
    }

    public function test_invalid_transitions_are_rejected()
    {
        $operation = $this->make_operation(array('qty_planned' => 5));

        $reason = NULL;

        try
        {
            $this->operations->act($operation['id'], 'output', array('qty_ok' => 1));
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Выработка по операции «к выполнению» невозможна');

        $this->operations->act($operation['id'], 'finish');

        $reason = NULL;

        try
        {
            $this->operations->act($operation['id'], 'start');
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Завершённую операцию начать нельзя');
    }

    public function test_downtime_is_tracked_with_reason()
    {
        $operation = $this->make_operation(array('qty_planned' => 3));
        $this->operations->act($operation['id'], 'start');

        $paused = $this->operations->act($operation['id'], 'downtime_start', array('reason' => 'equipment', 'comment' => 'сломался шпиндель'));

        $this->assertSame('paused', $paused['status']);
        $this->assertNotNull($paused['downtime_started_at']);
        $this->assertSame('Поломка оборудования', $paused['downtime_reason_label']);

        $reason = NULL;

        try
        {
            $this->operations->act($operation['id'], 'downtime_start', array('reason' => 'other'));
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Второй простой поверх первого не открывается');

        $stopped = $this->operations->act($operation['id'], 'downtime_stop');

        $this->assertNull($stopped['downtime_started_at']);
        $this->assertSame(FALSE, (bool) $stopped['is_downtime']);
    }

    public function test_delete_only_untouched_planned_operation()
    {
        $operation = $this->make_operation();
        $this->operations->act($operation['id'], 'delete');

        $this->assertNull($this->operations->get($operation['id']), 'Операция без отметок удаляется');

        $busy = $this->make_operation();
        $this->operations->act($busy['id'], 'start');

        $reason = NULL;

        try
        {
            $this->operations->act($busy['id'], 'delete');
        }
        catch (\Exception_Erp408_Shopfloor $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'Операция с отметками только отменяется');
    }

    public function test_finish_publishes_event_and_writes_journal()
    {
        $operation = $this->make_operation(array('qty_planned' => 2));
        $this->operations->act($operation['id'], 'start');
        $finished = $this->operations->act($operation['id'], 'finish', array('qty_ok' => 2));

        $events = \DB::select('id', 'event_name')
            ->from('erp_208_events')
            ->where('event_name', '=', 'erp408.operation.completed')
            ->where('payload', 'LIKE', '%'.$operation['order_number'].'%')
            ->order_by('id', 'DESC')
            ->execute()
            ->as_array();

        $this->assertSame(1, count($events), 'Завершение операции публикует ровно одно событие erp408.operation.completed');

        $journal = \DB::select('id', 'action')
            ->from('erp_119_audit_log')
            ->where('entity_type', '=', 'operation')
            ->where('entity_id', '=', $finished['uuid'])
            ->execute()
            ->as_array();

        $this->assertGreaterThan(0, count($journal), 'Действия цеха попадают в журнал аудита');
    }

    public function test_get_ignores_text_that_is_not_identifier()
    {
        $this->assertNull($this->operations->get('ПО-ТЕСТ-408-НЕ-UUID'), 'Произвольный текст не должен доходить до сравнения с uuid');
        $this->assertNull($this->operations->get(''));
    }

    public function test_board_and_terminal_show_planned_work()
    {
        $center   = $this->make_center();
        $operation = $this->make_operation(array('work_center_id' => $center['id'], 'qty_planned' => 7));

        $board = $this->operations->board($operation['planned_day'], $center['id']);

        // В базе есть и демонстрационные данные, поэтому проверяем не «сколько
        // всего», а что наш центр и наша операция в загрузке есть
        $centers = array();
        foreach ($board['centers'] as $block)
        {
            $centers[] = (int) $block['center']['id'];
        }

        $this->assertContains((int) $center['id'], $centers, 'центр виден в загрузке дня');
        $this->assertGreaterThanOrEqual(1, (int) $board['totals']['operations']);

        $mine = NULL;

        foreach ($this->operations->terminal_tasks($center['id'], $operation['planned_day']) as $task)
        {
            if ((int) $task['id'] === (int) $operation['id'])
            {
                $mine = $task;
            }
        }

        $this->assertNotNull($mine, 'задание видно в терминале центра');
        $this->assertSame(7.0, (float) $mine['qty_planned']);
        $this->assertArrayHasKey('start', $mine['actions']);
    }

    /**
     * Карточка операции — страница, которая открывается на реальных данных.
     *
     * Регрессия S2: шаблон карточки вызывал `$this->can(...)`, а View::capture()
     * работает в статическом контексте — страница отдавала 500. Дымовой прогон
     * этого не видел, пока в базе не было ни одной операции: адрес карточки
     * строится только по существующей записи.
     */
    public function test_operation_card_page_renders_on_real_data()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $center    = $this->make_center();
        $operation = $this->make_operation(array('work_center_id' => $center['id'], 'qty_planned' => 3));

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        $result = $smoke->run(array(
            array('url' => '/shopfloor/operations/view/'.(int) $operation['id'], 'name' => 'Карточка операции'),
        ));

        $row = reset($result['rows']);

        $this->assertSame(NULL, $row['error'],
            'карточка операции: '.$row['status'].' '.$row['error']);
        $this->assertSame(200, (int) $row['status'],
            'карточка операции отвечает '.$row['status'].', а не 200 ('.$row['error'].')');
    }

    public function test_stats_count_operations()
    {
        $operation = $this->make_operation(array('qty_planned' => 5));
        $this->operations->act($operation['id'], 'start');
        $this->operations->act($operation['id'], 'finish', array('qty_ok' => 5));

        $stats = $this->operations->stats(30);

        $this->assertGreaterThan(0, (int) $stats['total']);
        $this->assertGreaterThan(0, (int) $stats['done']);
        $this->assertGreaterThan(0, (float) $stats['qty_ok']);
    }

    public function test_allowed_actions_follow_status()
    {
        $planned = $this->operations->prepare(array('status' => 'planned') + $this->blank_operation());

        $this->assertArrayHasKey('start', $this->operations->allowed_actions($planned));
        $this->assertArrayNotHasKey('output', $this->operations->allowed_actions($planned));

        $done = $this->operations->prepare(array('status' => 'done') + $this->blank_operation());

        $this->assertSame(array(), $this->operations->allowed_actions($done));
        $this->assertSame(array('assign', 'start', 'finish', 'cancel', 'delete'), $this->operations->transitions()['planned']);
    }

    public function test_mcp_catalogue_exposes_shopfloor_tools()
    {
        if ( ! class_exists('Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $names = array();

        foreach (\Service_Erp804_Registry::instance()->tools() as $name => $tool)
        {
            $names[] = $name;
        }

        foreach (array('shopfloor.board', 'shopfloor.operation.start', 'shopfloor.route.plan') as $expected)
        {
            $this->assertContains($expected, $names, 'Инструмент '.$expected.' должен быть в каталоге агента');
        }
    }

    // ---------------------------------------------------------------- помощники --

    /**
     * @return  bool
     */
    /**
     * Производственная статистика периода: сырьё для OEE и Парето простоев.
     *
     * Цех отдаёт агрегаты (план, факт, время, брак, простои по причинам), а
     * формулы коэффициентов живут в аналитике — здесь проверяется именно
     * сырьё: без него OEE не из чего считать.
     */
    public function test_production_stats_gathers_oee_source_data()
    {
        $day = date('Y-m-d');

        $operation = $this->make_operation(array(
            'qty_planned'  => 10,
            'planned_hours' => 8,
            'planned_day'  => $day,
        ));

        $this->operations->act($operation['id'], 'start');
        $this->operations->act($operation['id'], 'downtime_start', array('reason' => 'equipment', 'comment' => 'тест простоев'));

        sleep(1);

        $this->operations->act($operation['id'], 'downtime_stop');
        $this->operations->act($operation['id'], 'output', array('qty_ok' => 6, 'qty_scrap' => 1));

        $stats = $this->operations->production_stats($day, $day);

        $this->assertSame($day, $stats['from']);
        $this->assertGreaterThanOrEqual(1, $stats['totals']['operations'], 'операция попала в период');
        $this->assertGreaterThanOrEqual(10.0, $stats['totals']['qty_planned'], 'план собран');
        $this->assertGreaterThanOrEqual(6.0, $stats['totals']['qty_ok'], 'годная выработка собрана');
        $this->assertGreaterThanOrEqual(1.0, $stats['totals']['qty_scrap'], 'брак собран');
        $this->assertGreaterThanOrEqual(8.0, $stats['totals']['planned_hours'], 'плановые часы собраны — из них считается доступность');
        $this->assertGreaterThanOrEqual(1, $stats['totals']['downtime_sec'], 'простой учтён');

        $reasons = array();

        foreach ($stats['downtime_reasons'] as $reason)
        {
            $reasons[$reason['reason']] = $reason;
        }

        $this->assertArrayHasKey('equipment', $reasons, 'простой попал в разбор по причинам');
        $this->assertSame('Поломка оборудования', $reasons['equipment']['reason_label'], 'причина подписана по-человечески');
        $this->assertGreaterThanOrEqual(1, $reasons['equipment']['seconds'], 'длительность простоя собрана из журнала работ');
        $this->assertGreaterThan(0, $reasons['equipment']['share'], 'доля причины посчитана');

        $centers = array();

        foreach ($stats['by_center'] as $center)
        {
            $centers[(int) $center['work_center_id']] = $center;
        }

        $this->assertNotEmpty($centers, 'разрез по рабочим центрам не пуст');
        $this->assertNotSame('', (string) reset($centers)['work_center_label'], 'центр подписан для отчёта');
    }

    protected function available()
    {
        try
        {
            return (bool) \DB::query(\Database::SELECT, "SELECT to_regclass('erp_408_operations') IS NOT NULL AS ok")
                ->execute()
                ->get('ok', FALSE);
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * @param   array   $data
     * @return  array
     */
    protected function make_center(array $data = array())
    {
        return $this->centers->create($data + array(
            'code'              => 'ТЕСТ-408-'.strtoupper(bin2hex(random_bytes(2))),
            'name'              => 'Тестовый центр',
            'kind'              => 'center',
            'workshop'          => 'Механический участок',
            'capacity_per_hour' => 2,
            'cost_per_hour'     => 1500,
        ));
    }

    /**
     * @param   array   $data
     * @return  array
     */
    protected function make_shift(array $data = array())
    {
        return $this->shifts->create($data + array(
            'code'           => 'ТЕСТ-408-S'.strtoupper(bin2hex(random_bytes(1))),
            'name'           => 'Тестовая смена',
            'start_time'     => '08:00',
            'end_time'       => '16:00',
            'duration_hours' => 8,
        ));
    }

    /**
     * @param   array   $data
     * @return  \Model_Erp402_Order
     */
    protected function make_order(array $data = array())
    {
        $data['number'] = isset($data['number']) ? $data['number'] : 'ПО-ТЕСТ-408-'.strtoupper(bin2hex(random_bytes(3)));

        return $this->orders->create($data + array('title' => 'Тестовое изделие цеха', 'qty' => 3));
    }

    /**
     * Заказ с техкартой (в техкарте — одна операция).
     *
     * @param   float   $qty
     * @return  \Model_Erp402_Order
     */
    protected function make_order_with_card($qty = 1)
    {
        return $this->make_order(array('qty' => $qty, 'tech_card_id' => $this->make_tech_card()));
    }

    /**
     * Ручная операция на своём заказе.
     *
     * @param   array   $data
     * @return  array
     */
    protected function make_operation(array $data = array())
    {
        $order = $this->make_order(array('qty' => isset($data['qty_planned']) ? $data['qty_planned'] : 5));

        return $this->operations->add_operation($data + array(
            'order_id'    => $order->id,
            'name'        => 'Токарная (тест)',
            'qty_planned' => 5,
            'planned_day' => date('Y-m-d'),
        ));
    }

    /**
     * Техкарта с одной операцией для маршрута цеха.
     *
     * @return  int
     */
    protected function make_tech_card()
    {
        $cards = \Service_Erp228_TechCards::instance();

        $card = $cards->create(array(
            'title'           => 'ТЕСТ-408 изделие',
            'source_code'     => "cube([30, 20, 10]);\n// тестовая карта для erp408",
            'production_type' => 'additive',
            'target_type'     => 'parametric',
            'input_params'    => array('материал' => 'PA12'),
        ));

        $this->cards[] = (int) $card->id;

        $cards->save_document($card->id, array(
            'content_markdown' => '# Маршрут',
            'structured_data'  => array(
                'спецификация' => array(
                    array('наименование' => 'Пруток 20', 'количество' => 1, 'единица' => 'кг'),
                ),
            ),
        ), array(
            array('op_name' => 'Токарная', 'duration_sec' => 600, 'cost_amount' => 120),
            array('op_name' => 'Контроль', 'duration_sec' => 120, 'cost_amount' => 40),
        ));

        return (int) $card->id;
    }

    /**
     * Пустая строка операции для проверок правил без обращения к базе.
     *
     * @return  array
     */
    protected function blank_operation()
    {
        return array(
            'id'             => 0,
            'uuid'           => '00000000-0000-0000-0000-000000000000',
            'op_no'          => 10,
            'name'           => 'Проверка',
            'order_id'       => 0,
            'order_number'   => 'ПО-ПРОВЕРКА',
            'order_title'    => 'Проверка',
            'work_center_id' => NULL,
            'shift_id'       => NULL,
            'planned_day'    => date('Y-m-d'),
            'qty_planned'    => 1,
            'qty_ok'         => 0,
            'qty_scrap'      => 0,
            'planned_hours'  => 0,
            'norm_cost'      => 0,
            'worked_sec'     => 0,
            'downtime_sec'   => 0,
            'downtime_started_at' => NULL,
            'actual_start'   => NULL,
            'actual_end'     => NULL,
            'source'         => 'manual',
            'note'           => NULL,
        );
    }
}
