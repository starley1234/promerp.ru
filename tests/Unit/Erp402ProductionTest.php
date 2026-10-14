<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты производства (модуль erp402_production).
 *
 * Проверяют жизненный цикл заказа на реальных сервисах: состав из техкарты
 * (erp228), резерв и списание материалов (erp401), выпуск продукции,
 * публикацию событий и запись в журнал действий (erp119).
 *
 * Тесты работают в своих складах (`WH-TEST-402*`) и удаляют заказы с номерами
 * `ПО-ТЕСТ-*` в tearDown, поэтому демо-данные не страдают. Если таблиц модуля
 * или склада нет — тесты пропускаются.
 */
class Erp402ProductionTest extends TestCase
{
    /** @var \Service_Erp402_Orders */
    protected $orders;

    /** @var \Service_Erp402_Materials|null */
    protected $materials;

    /** @var \Model_Erp401_Warehouse|null Склад материалов теста */
    protected $warehouse;

    /** @var \Model_Erp401_Warehouse|null Склад готовой продукции теста */
    protected $output;

    /** @var int Позиция номенклатуры из реестра erp227 */
    protected $item_id = 1;

    /** @var bool Есть ли складской модуль с остатками */
    protected $stock_ready = FALSE;

    /** @var array Созданные техкарты (для уборки) */
    protected $cards = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль производства недоступен — выполните миграции erp402');
            return;
        }

        $this->orders = \Service_Erp402_Orders::instance();

        $this->stock_ready = class_exists('Service_Erp401_Stock');

        if ($this->stock_ready)
        {
            $this->warehouse = $this->make_warehouse('WH-TEST-402', 'Тестовый склад материалов');
            $this->output    = $this->make_warehouse('WH-TEST-402-OUT', 'Тестовый склад готовой продукции');
            $this->materials = \Service_Erp402_Materials::instance();
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
                ->where('number', 'LIKE', 'ПО-ТЕСТ-%')->execute()->as_array() as $row)
            {
                $order_ids[] = (int) $row['id'];
            }

            if ($order_ids)
            {
                // Операции цеха (erp408) живут по мягкой ссылке на заказ, поэтому
                // уборка теста обязана снять их сама — иначе в терминале остаются
                // «сироты»: операции по заказу, которого нет (баг B10).
                if (\DB::query(\Database::SELECT, "SELECT to_regclass('erp_408_operations') IS NOT NULL AS ok")
                    ->execute()->get('ok', FALSE))
                {
                    \DB::delete('erp_408_operations')->where('order_id', 'IN', $order_ids)->execute();
                }

                \DB::delete('erp_402_order_items')->where('order_id', 'IN', $order_ids)->execute();
                \DB::delete('erp_402_orders')->where('id', 'IN', $order_ids)->execute();
            }

            if ($this->stock_ready)
            {
                $warehouse_ids = array();

                foreach (array($this->warehouse, $this->output) as $warehouse)
                {
                    if ($warehouse)
                    {
                        $warehouse_ids[] = (int) $warehouse->id;
                    }
                }

                if ($warehouse_ids)
                {
                    \DB::delete('erp_401_reservations')->where('warehouse_id', 'IN', $warehouse_ids)->execute();
                    \DB::delete('erp_401_movements')->where('warehouse_id', 'IN', $warehouse_ids)->execute();
                    \DB::delete('erp_401_balances')->where('warehouse_id', 'IN', $warehouse_ids)->execute();
                    \DB::delete('erp_401_warehouses')->where('id', 'IN', $warehouse_ids)->execute();
                }
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

    public function test_created_order_gets_number_and_draft_status()
    {
        $order = $this->orders->create(array('title' => 'Изделие с автономером', 'qty' => 5));

        $this->assertSame('draft', $order->status, 'новый заказ — черновик');
        $this->assertMatchesRegularExpression('/^ПО-\d{4}-\d{6}$/', (string) $order->number, 'номер вида ПО-ГГГГ-НННННН');
        $this->assertSame(5.0, (float) $order->qty);
        $this->assertNotEmpty((string) $order->uuid, 'uuid выдан базой');

        $this->orders->delete($order->id);
    }

    public function test_order_number_is_unique_even_for_numberless_creation()
    {
        $first  = $this->make_order();
        $second = $this->make_order();

        $this->assertNotSame($first->number, $second->number, 'последовательность выдаёт разные номера');
    }

    public function test_empty_title_is_rejected()
    {
        $this->expectException(\Exception_Erp402_Production::class);
        $this->expectExceptionMessage('Укажите изделие');

        $this->orders->create(array('title' => '', 'qty' => 1));
    }

    public function test_zero_qty_is_rejected()
    {
        $this->expectException(\ORM_Validation_Exception::class);

        $this->orders->create(array('title' => 'Ноль штук', 'qty' => 0));
    }

    public function test_order_item_computes_total_demand_from_norm_and_plan()
    {
        $order = $this->make_order(array('qty' => 4));

        $item = $this->orders->add_item($order->id, array(
            'name'         => 'Крепёж М6',
            'qty_per_unit' => 2.5,
            'uom_code'     => 'pcs',
        ));

        $this->assertSame(10.0, (float) $item->qty_total, 'норма 2,5 × план 4 = 10');
        $this->assertSame(10, (int) $item->line_no, 'первая строка получает номер 10');
    }

    public function test_plan_requires_composition()
    {
        $order = $this->make_order();

        $this->expectException(\Exception_Erp402_Production::class);
        $this->expectExceptionMessage('Пустой состав');

        $this->orders->plan($order->id);
    }

    public function test_plan_recalculates_demand_after_qty_change()
    {
        $order = $this->make_order(array('qty' => 2));

        $this->orders->add_item($order->id, array('name' => 'Полоса 20×4', 'qty_per_unit' => 3));

        $this->orders->update($order->id, array('qty' => 5));
        $planned = $this->orders->plan($order->id);

        $items = $this->orders->items($planned->id);

        $this->assertSame('planned', $planned->status);
        $this->assertSame(15.0, (float) $items[0]->qty_total, 'потребность пересчитана: 3 × 5');
    }

    public function test_release_reserves_materials_on_warehouse()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $this->give_stock(100);

        $order = $this->make_order(array('qty' => 3, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 2,
        ));

        $result = $this->orders->release($order->id);

        $this->assertSame('released', $result['order']->status, 'материалы зарезервированы — заказ выпущен в производство');
        $this->assertSame(1, $result['reserved']);
        $this->assertSame(array(), $result['short']);

        $items = $this->orders->items($order->id);

        $this->assertSame('reserved', $items[0]->status);
        $this->assertNotEmpty($items[0]->reservation_id, 'строка ссылается на резерв склада');

        $reservations = \Service_Erp401_Stock::instance()->reservations(array('ref_type' => 'erp402_order', 'ref_id' => $order->id), 10, 0);

        $this->assertSame(1, $reservations['total'], 'резерв создан под заказ');
        $this->assertSame(6.0, (float) $reservations['rows'][0]['qty'], 'зарезервировано 2 × 3');
    }

    public function test_release_without_stock_keeps_order_planned_and_marks_line_short()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $order = $this->make_order(array('qty' => 5, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Дефицитный материал',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $result = $this->orders->release($order->id);

        $this->assertSame('planned', $result['order']->status, 'без материалов заказ остаётся планом');
        $this->assertNotEmpty($result['short'], 'нехватка попала в отчёт');

        $items = $this->orders->items($order->id);

        $this->assertSame('short', $items[0]->status, 'строка помечена как нехватка');
    }

    public function test_release_reports_unmapped_lines()
    {
        $order = $this->make_order(array('qty' => 1));

        $this->orders->add_item($order->id, array('name' => 'Материал без номенклатуры', 'qty_per_unit' => 1));

        $result = $this->orders->release($order->id);

        $this->assertSame(array('Материал без номенклатуры'), $result['unmapped'], 'несопоставленные строки перечислены');
    }

    public function test_complete_issues_materials_and_receives_output()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $this->give_stock(50);

        $order = $this->make_order(array(
            'qty'                 => 2,
            'item_id'             => $this->item_id,
            'warehouse_id'        => $this->warehouse->id,
            'output_warehouse_id' => $this->output->id,
            'uom_code'            => 'pcs',
        ));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 5,
        ));

        $this->orders->release($order->id);
        $this->orders->start($order->id);

        $stock = \Service_Erp401_Stock::instance();
        $before = $stock->qty($this->warehouse->id, $this->item_id);

        $result = $this->orders->complete($order->id);

        $this->assertSame('done', $result['order']->status, 'заказ закрыт');
        $this->assertSame(1, $result['issued'], 'материалы списаны');
        $this->assertNotNull($result['received'], 'готовая продукция принята на склад');
        $this->assertSame(
            round($before - 10, 3),
            round($stock->qty($this->warehouse->id, $this->item_id), 3),
            'остаток материалов уменьшился на потребность (5 × 2)'
        );
        $this->assertSame(2.0, (float) $stock->qty($this->output->id, $this->item_id), 'выпуск принят на склад продукции');

        $items = $this->orders->items($order->id);

        $this->assertSame('issued', $items[0]->status);
        $this->assertNull($items[0]->reservation_id, 'резерв закрыт списанием');
    }

    public function test_partial_completion_keeps_order_in_progress()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $this->give_stock(100);

        $order = $this->make_order(array('qty' => 10, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $this->orders->release($order->id);
        $this->orders->start($order->id);

        $result = $this->orders->complete($order->id, 4);

        $this->assertSame('in_progress', $result['order']->status, 'частичный выпуск не закрывает заказ');
        $this->assertSame(4.0, (float) $result['order']->qty_done);
        $this->assertSame(40.0, $result['order']->progress(), 'прогресс 4 из 10');
    }

    public function test_completion_without_stock_raises_and_keeps_order_open()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $order = $this->make_order(array('qty' => 1, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Нет на складе',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $this->orders->plan($order->id);
        $this->orders->start($order->id);

        try
        {
            $this->orders->complete($order->id);
            $this->fail('Ожидалась ошибка списания');
        }
        catch (\Exception_Erp402_Production $e)
        {
            $this->assertSame('material_shortage', $e->reason());
        }

        $this->assertSame('in_progress', $this->orders->get($order->id)->status, 'заказ остался в работе');
    }

    public function test_cancel_releases_reservations()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $this->give_stock(20);

        $order = $this->make_order(array('qty' => 2, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $this->orders->release($order->id);

        $stock = \Service_Erp401_Stock::instance();
        $reserved_before = (float) $stock->balance($this->warehouse->id, $this->item_id)->reserved;

        $cancelled = $this->orders->cancel($order->id, 'тест отмены');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(
            2.0,
            round($reserved_before - (float) $stock->balance($this->warehouse->id, $this->item_id)->reserved, 3),
            'резерв снят при отмене'
        );
    }

    public function test_invalid_transition_is_rejected()
    {
        $order = $this->make_order();

        $reason = NULL;

        try
        {
            $this->orders->start($order->id);
        }
        catch (\Exception_Erp402_Production $e)
        {
            $reason = $e->reason();
        }

        $this->assertSame('invalid_transition', $reason, 'по черновику работу не начинают');
    }

    public function test_start_is_allowed_from_plan_and_release()
    {
        // Материалы могут прийти позже: работу разрешено начать из плана,
        // расход спишется при выпуске
        $order = $this->make_order();

        $this->orders->add_item($order->id, array('name' => 'Материал', 'qty_per_unit' => 1));

        $planned = $this->orders->plan($order->id);

        $this->assertArrayHasKey('start', $this->orders->transitions($planned), 'из плана работу начать можно');
        $this->assertArrayHasKey('release', $this->orders->transitions($planned));

        $started = $this->orders->start($order->id);

        $this->assertSame('in_progress', $started->status);
        $this->assertNotEmpty((string) $started->started_at, 'отметка начала работы');
        $this->assertArrayHasKey('complete', $this->orders->transitions($started), 'в работе доступен выпуск');
    }

    public function test_completed_order_cannot_be_edited_or_deleted()
    {
        $order = $this->make_order(array('qty' => 1));

        $this->orders->add_item($order->id, array('name' => 'Материал', 'qty_per_unit' => 1));
        $this->orders->plan($order->id);
        $this->orders->start($order->id);
        $this->orders->complete($order->id, 1);

        try
        {
            $this->orders->update($order->id, array('title' => 'Новое имя'));
            $this->fail('Завершённый заказ не должен изменяться');
        }
        catch (\Exception_Erp402_Production $e)
        {
            $this->assertSame('locked', $e->reason());
        }

        try
        {
            $this->orders->delete($order->id);
            $this->fail('Завершённый заказ нельзя удалить');
        }
        catch (\Exception_Erp402_Production $e)
        {
            $this->assertSame('locked', $e->reason());
        }
    }

    public function test_draft_order_can_be_deleted()
    {
        $order = $this->make_order();

        $this->assertTrue($this->orders->delete($order->id));
        $this->assertNull($this->orders->get($order->id), 'заказ удалён');
    }

    /**
     * Отмена заказа закрывает работу цеха: иначе мастер видит в сменном
     * задании операции по заказу, которого больше нет.
     */
    public function test_cancel_closes_shopfloor_operations()
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            $this->markTestSkipped('Модуль цеха не подключён');
        }

        $shopfloor = \Service_Erp408_Operations::instance();
        $order     = $this->make_order(array('qty' => 2));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $this->orders->plan($order->id);

        // Маршрут передаётся цеху при запуске (и при резервировании материалов):
        // до этого заказ — ещё только намерение
        $this->orders->start($order->id);

        $before = $shopfloor->for_order($order->id);

        $this->assertNotEmpty($before, 'запуск заказа создаёт операции цеха');

        $this->orders->cancel($order->id, 'тест каскада');

        foreach ($shopfloor->for_order($order->id) as $operation)
        {
            $this->assertSame('cancelled', $operation['status'],
                'операция «'.$operation['name'].'» закрыта вместе с заказом');
        }
    }

    /**
     * Удаление заказа убирает и операции цеха: «сироты» в терминале —
     * известный источник мусора в сменном задании.
     */
    public function test_delete_removes_shopfloor_operations()
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            $this->markTestSkipped('Модуль цеха не подключён');
        }

        $shopfloor = \Service_Erp408_Operations::instance();
        $order     = $this->make_order(array('qty' => 1));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 1,
        ));

        $this->orders->plan($order->id);
        $this->orders->start($order->id);

        $this->assertNotEmpty($shopfloor->for_order($order->id), 'маршрут передан цеху');

        $this->orders->cancel($order->id, 'тест удаления');
        $this->orders->delete($order->id);

        $this->assertSame(array(), $shopfloor->for_order($order->id),
            'вместе с заказом уходят его операции — сирот не остаётся');
    }

    public function test_search_filters_by_status_and_term()
    {
        $order = $this->make_order(array('title' => 'Уникальное изделие ПОИСК-402'));
        $this->orders->add_item($order->id, array('name' => 'Материал', 'qty_per_unit' => 1));
        $this->orders->plan($order->id);

        $by_term = $this->orders->search(array('term' => 'поиск-402'), 10, 0);
        $this->assertGreaterThanOrEqual(1, $by_term['total'], 'поиск по наименованию находит заказ');

        $by_status = $this->orders->search(array('status' => 'planned', 'term' => 'поиск-402'), 10, 0);
        $this->assertSame(1, $by_status['total']);

        $other = $this->orders->search(array('status' => 'done', 'term' => 'поиск-402'), 10, 0);
        $this->assertSame(0, $other['total'], 'фильтр по статусу отсекает заказ');
    }

    public function test_transitions_follow_production_cycle()
    {
        $order = $this->make_order();

        $draft = $this->orders->transitions($order);
        $this->assertArrayHasKey('plan', $draft);
        $this->assertArrayNotHasKey('complete', $draft, 'выпуск недоступен из черновика');

        $this->orders->add_item($order->id, array('name' => 'Материал', 'qty_per_unit' => 1));
        $planned = $this->orders->plan($order->id);

        $this->assertArrayHasKey('release', $this->orders->transitions($planned));

        $done = $this->orders->transitions($planned);
        $this->assertArrayNotHasKey('plan', $done, 'план уже утверждён');
    }

    public function test_materials_requirements_show_shortage()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $this->give_stock(3);

        $order = $this->make_order(array('qty' => 5, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Заготовка',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 2,
        ));

        $requirements = $this->materials->requirements($order);
        $summary      = $this->materials->summary($order);

        $this->assertSame(10.0, (float) $requirements[0]['needed'], 'нужно 10 (2 × 5)');
        $this->assertSame(3.0, (float) $requirements[0]['have'], 'на складе 3');
        $this->assertSame(7.0, (float) $requirements[0]['shortage'], 'не хватает 7');
        $this->assertSame(1, $summary['short']);
    }

    public function test_stats_count_orders_by_status()
    {
        $order = $this->make_order(array('qty' => 2));
        $this->orders->add_item($order->id, array('name' => 'Материал', 'qty_per_unit' => 1));
        $this->orders->plan($order->id);

        $stats = $this->orders->stats();

        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['open'], 'заказ в плане считается открытым');
        $this->assertArrayHasKey('planned', $stats['by_status']);
    }

    public function test_order_changes_are_published_to_event_bus()
    {
        $order = $this->make_order(array('title' => 'Событие ПО-ТЕСТ'));

        if ( ! class_exists('Service_Erp208_EventBus'))
        {
            $this->markTestSkipped('Шина событий недоступна');
        }

        $found = \DB::select(array(\DB::expr('COUNT(*)'), 'total'))
            ->from('erp_208_events')
            ->where('event_name', '=', 'erp402.order.created')
            ->where('payload', 'LIKE', '%'.$order->number.'%')
            ->execute()
            ->get('total', 0);

        $this->assertSame(1, (int) $found, 'событие создания заказа сохранено шиной ровно один раз');
    }

    public function test_order_changes_are_written_to_action_journal()
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            $this->markTestSkipped('Журнал действий недоступен');
        }

        $order = $this->make_order(array('title' => 'Журнал ПО-ТЕСТ'));

        $found = \DB::select(array(\DB::expr('COUNT(*)'), 'total'))
            ->from('erp_119_audit_log')
            ->where('entity_type', '=', 'order')
            ->where('entity_id', '=', (string) $order->uuid)
            ->execute()
            ->get('total', 0);

        $this->assertGreaterThanOrEqual(1, (int) $found, 'создание заказа попало в журнал действий');
    }

    public function test_bom_extracts_lines_from_nested_structure()
    {
        $bom = \Service_Erp402_Bom::instance();

        list($items, $warnings) = $bom->extract(array(
            'изделие' => 'Кронштейн',
            'спецификация' => array(
                array('наименование' => 'Полоса 20×4', 'количество' => 2, 'ед_изм' => 'кг'),
                array('title' => 'Крепёж М6', 'qty' => 4),
                array('комментарий' => 'без количества — не строка состава'),
            ),
        ));

        $this->assertCount(2, $items, 'разобраны только строки с наименованием и количеством');
        $this->assertSame('Полоса 20×4', $items[0]['name']);
        $this->assertSame(2.0, $items[0]['qty_per_unit']);
        $this->assertSame('кг', $items[0]['uom_code']);
        $this->assertSame('Крепёж М6', $items[1]['name']);
        $this->assertSame(10, $items[0]['line_no'], 'строки получают шаг 10');
        $this->assertSame(array(), $warnings);
    }

    public function test_bom_merges_duplicate_materials()
    {
        $bom = \Service_Erp402_Bom::instance();

        list($items) = $bom->extract(array(
            array('name' => 'Саморез 4×30', 'qty' => 4),
            array('name' => 'саморез 4×30', 'qty' => 6),
        ));

        $this->assertCount(1, $items, 'один материал в разных узлах — одна строка');
        $this->assertSame(10.0, $items[0]['qty_per_unit'], 'нормы сложены');
        $this->assertSame(2, $items[0]['sources']);
    }

    public function test_tech_card_composition_fills_order()
    {
        if ( ! class_exists('Service_Erp228_TechCards'))
        {
            $this->markTestSkipped('Модуль техкарт недоступен');
        }

        $card_id = $this->make_tech_card();

        $order = $this->make_order(array('qty' => 3, 'tech_card_id' => $card_id));

        $result = $this->orders->fill_from_tech_card($order->id);

        $this->assertSame(2, $result['added'], 'из техкарты добавлены строки состава');
        $this->assertSame(1, count($result['operations']), 'маршрут операций прочитан');

        $items = $this->orders->items($order->id);

        $this->assertSame(3.0, (float) $items[0]->qty_total, 'норма 1 × план 3');
        $this->assertSame(12.0, (float) $items[1]->qty_total, 'норма 4 × план 3');
    }

    public function test_shortages_report_lists_missing_materials()
    {
        if ( ! $this->stock_ready)
        {
            $this->markTestSkipped('Складской модуль недоступен');
        }

        $order = $this->make_order(array('qty' => 2, 'warehouse_id' => $this->warehouse->id));

        $this->orders->add_item($order->id, array(
            'name'         => 'Дефицит ПО-ТЕСТ',
            'item_id'      => $this->item_id,
            'qty_per_unit' => 10,
        ));

        $this->orders->plan($order->id);

        $shortages = $this->materials->shortages(50);

        $ours = array();

        foreach ($shortages as $row)
        {
            if ($row['order']->id === $order->id)
            {
                $ours[] = $row;
            }
        }

        $this->assertCount(1, $ours, 'заказ попал в отчёт о нехватке');
        $this->assertSame(20.0, (float) $ours[0]['needed']);
        $this->assertGreaterThan(0, $ours[0]['shortage']);
    }

    // -------------------------------------------------------------- помощники --

    /**
     * Модуль и таблицы доступны.
     *
     * @return  bool
     */
    protected function available()
    {
        try
        {
            return (bool) \DB::query(\Database::SELECT, "SELECT to_regclass('erp_402_orders') IS NOT NULL AS ok")
                ->execute()
                ->get('ok', FALSE);
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Создать заказ с тестовым номером.
     *
     * @param   array   $data
     * @return  \Model_Erp402_Order
     */
    protected function make_order(array $data = array())
    {
        $data['number'] = isset($data['number']) ? $data['number'] : 'ПО-ТЕСТ-'.strtoupper(bin2hex(random_bytes(4)));

        return $this->orders->create($data + array('title' => 'Тестовое изделие', 'qty' => 1));
    }

    /**
     * Тестовый склад.
     *
     * @param   string  $code
     * @param   string  $name
     * @return  \Model_Erp401_Warehouse
     */
    protected function make_warehouse($code, $name)
    {
        $stock = \Service_Erp401_Stock::instance();

        $existing = \ORM::factory('Erp401_Warehouse')->where('code', '=', $code)->find();

        if ($existing->loaded())
        {
            return $existing;
        }

        return $stock->create_warehouse(array('code' => $code, 'name' => $name, 'type' => 'production'));
    }

    /**
     * Завести материалы на тестовом складе.
     *
     * @param   float   $qty
     * @return  void
     */
    protected function give_stock($qty)
    {
        $stock = \Service_Erp401_Stock::instance();

        // Приводим остаток к нужному значению: тесты не зависят друг от друга
        $stock->adjust($this->warehouse->id, $this->item_id, (float) $qty, array(
            'uom_code' => 'pcs',
            'note'     => 'Тестовый остаток erp402',
        ));
    }

    /**
     * Техкарта с составом и операцией.
     *
     * @return  int
     */
    protected function make_tech_card()
    {
        $cards = \Service_Erp228_TechCards::instance();

        $card = $cards->create(array(
            'title'           => 'ТЕСТ-402 изделие',
            'source_code'     => "cube([40, 30, 20]);\n// тестовая карта для erp402",
            'production_type' => 'additive',
            'target_type'     => 'parametric',
            'input_params'    => array('материал' => 'PA12'),
        ));

        $this->cards[] = (int) $card->id;

        $cards->save_document($card->id, array(
            'content_markdown' => '# Состав',
            'structured_data'  => array(
                'спецификация' => array(
                    array('наименование' => 'Полоса 20×4', 'количество' => 1, 'единица' => 'кг'),
                    array('наименование' => 'Крепёж М6', 'количество' => 4, 'единица' => 'pcs'),
                ),
            ),
        ), array(
            array('op_name' => 'Резка', 'duration_sec' => 120, 'cost_amount' => 15),
        ));

        return (int) $card->id;
    }
}
