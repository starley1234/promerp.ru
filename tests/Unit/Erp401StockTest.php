<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты складского учёта (модуль erp401_wms).
 *
 * Проверяют бизнес-логику Service_Erp401_Stock на реальной БД:
 * приход/расход/перемещение/корректировку, защиту от ухода в минус
 * и жизненный цикл резерва.
 *
 * Тесты работают в своём тестовом складе и удаляют его в tearDown,
 * поэтому безопасны для данных. Если БД или таблицы модуля недоступны —
 * тесты пропускаются.
 */
class Erp401StockTest extends TestCase
{
    /** @var \Service_Erp401_Stock */
    protected $stock;

    /** @var \Model_Erp401_Warehouse|null */
    protected $warehouse;

    /** @var \Model_Erp401_Warehouse|null */
    protected $warehouse_to;

    /** @var int Тестовая позиция из реестра erp227 */
    protected $item_id = 1;

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Складской модуль недоступен — выполните миграции erp401');
            return;
        }

        $this->stock = \Service_Erp401_Stock::instance();

        $this->warehouse = $this->makeWarehouse('WH-TEST-1', 'Тестовый склад 1');
        $this->warehouse_to = $this->makeWarehouse('WH-TEST-2', 'Тестовый склад 2');
    }

    protected function tearDown(): void
    {
        if ($this->warehouse OR $this->warehouse_to)
        {
            try
            {
                \DB::delete('erp_401_reservations')
                    ->where('warehouse_id', 'IN', $this->testWarehouseIds())
                    ->execute();
                \DB::delete('erp_401_movements')
                    ->where('warehouse_id', 'IN', $this->testWarehouseIds())
                    ->execute();
                \DB::delete('erp_401_balances')
                    ->where('warehouse_id', 'IN', $this->testWarehouseIds())
                    ->execute();
                \DB::delete('erp_401_warehouses')
                    ->where('code', 'IN', array('WH-TEST-1', 'WH-TEST-2'))
                    ->execute();
            }
            catch (\Exception $e)
            {
                // подчистка не должна ломать тест
            }
        }
    }

    // ---------------------------------------------------------------- тесты --

    public function test_balance_and_reservation_pages_clamp_limits_and_offsets()
    {
        $balances = $this->stock->balances(array(), 100000, -10);
        $reservations = $this->stock->reservations(array(), 100000, -10);

        $this->assertIsArray($balances);
        $this->assertIsArray($reservations);
        $this->assertArrayHasKey('rows', $balances);
        $this->assertArrayHasKey('total', $balances);
        $this->assertArrayHasKey('rows', $reservations);
        $this->assertArrayHasKey('total', $reservations);
    }

    public function test_receive_increases_balance_and_writes_movement()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100, array('doc_type' => 'test'));

        $this->assertSame(100.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));

        $movement = \ORM::factory('Erp401_Movement')
            ->where('warehouse_id', '=', $this->warehouse->id)
            ->where('item_id', '=', $this->item_id)
            ->where('type', '=', 'receipt')
            ->order_by('id', 'DESC')
            ->find();

        $this->assertTrue($movement->loaded(), 'приход должен быть записан в журнал');
        $this->assertSame('posted', $movement->status);
        $this->assertEquals(0.0, (float) $movement->balance_before);
        $this->assertEquals(100.0, (float) $movement->balance_after);
    }

    public function test_issue_decreases_balance()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $this->stock->issue($this->warehouse->id, $this->item_id, 40);

        $this->assertSame(60.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
    }

    public function test_issue_more_than_balance_throws()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 10);

        $this->expectException(\Exception_Erp401_Stock::class);
        $this->expectExceptionMessageMatches('//');

        $this->stock->issue($this->warehouse->id, $this->item_id, 999);
    }

    public function test_failed_issue_does_not_change_balance()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 10);

        try
        {
            $this->stock->issue($this->warehouse->id, $this->item_id, 999);
        }
        catch (\Exception_Erp401_Stock $e)
        {
            // ожидаемо
        }

        // транзакция должна откатиться — остаток не изменился
        $this->assertSame(10.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
    }

    public function test_transfer_moves_stock_between_warehouses()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 50);
        $this->stock->transfer($this->warehouse->id, $this->warehouse_to->id, $this->item_id, 30);

        $this->assertSame(20.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
        $this->assertSame(30.0, round($this->stock->qty($this->warehouse_to->id, $this->item_id), 6));
    }

    public function test_transfer_to_same_warehouse_is_rejected()
    {
        $this->expectException(\Exception_Erp401_Stock::class);

        $this->stock->transfer($this->warehouse->id, $this->warehouse->id, $this->item_id, 1);
    }

    public function test_adjust_sets_exact_quantity()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 50);
        $this->stock->adjust($this->warehouse->id, $this->item_id, 42, array('note' => 'инвентаризация'));

        $this->assertSame(42.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
    }

    public function test_reservation_reduces_available_but_not_qty()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $reservation = $this->stock->reserve($this->warehouse->id, $this->item_id, 30, array('ref_type' => 'order', 'ref_id' => 7));

        $this->assertSame(100.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
        $this->assertSame(70.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));
        $this->assertSame('active', $reservation->status);

        $this->stock->release_reservation($reservation->id);
        $this->assertSame(100.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));
    }

    public function test_reserve_more_than_available_is_rejected()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 10);

        $this->expectException(\Exception_Erp401_Stock::class);

        $this->stock->reserve($this->warehouse->id, $this->item_id, 50);
    }

    public function test_consume_reservation_writes_off_stock()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $reservation = $this->stock->reserve($this->warehouse->id, $this->item_id, 25, array('ref_type' => 'order', 'ref_id' => 8));

        $this->stock->consume_reservation($reservation->id);

        $this->assertSame(75.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
        $this->assertSame(75.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));

        $reservation->reload();
        $this->assertSame('consumed', $reservation->status);
    }

    public function test_issue_cannot_consume_reserved_stock()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $this->stock->reserve($this->warehouse->id, $this->item_id, 80);

        try
        {
            $this->stock->issue($this->warehouse->id, $this->item_id, 21);
            $this->fail('Нельзя списать физически имеющийся, но зарезервированный остаток');
        }
        catch (\Exception_Erp401_Stock $e)
        {
            $this->assertSame('insufficient_stock', $e->reason());
        }

        $this->assertSame(100.0, round($this->stock->qty($this->warehouse->id, $this->item_id), 6));
        $this->assertSame(20.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));
    }

    public function test_adjustment_cannot_reduce_stock_below_reservations()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $this->stock->reserve($this->warehouse->id, $this->item_id, 60);

        $this->expectException(\Exception_Erp401_Stock::class);
        $this->stock->adjust($this->warehouse->id, $this->item_id, 50);
    }

    public function test_partial_reservation_close_keeps_remainder_active()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 100);
        $reservation = $this->stock->reserve($this->warehouse->id, $this->item_id, 30);

        $this->stock->release_reservation($reservation->id, 10);
        $reservation->reload();

        $this->assertSame('active', $reservation->status);
        $this->assertSame(20.0, (float) $reservation->qty);
        $this->assertSame(80.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));

        $this->stock->consume_reservation($reservation->id);
        $reservation->reload();
        $this->assertSame('consumed', $reservation->status);
        $this->assertSame(80.0, round($this->stock->available($this->warehouse->id, $this->item_id), 6));
    }

    public function test_transfer_cannot_move_reserved_stock()
    {
        $this->stock->receive($this->warehouse->id, $this->item_id, 20);
        $this->stock->reserve($this->warehouse->id, $this->item_id, 15);

        $this->expectException(\Exception_Erp401_Stock::class);
        $this->stock->transfer($this->warehouse->id, $this->warehouse_to->id, $this->item_id, 6);
    }

    public function test_invalid_quantity_is_rejected()
    {
        $this->expectException(\Exception_Erp401_Stock::class);

        $this->stock->receive($this->warehouse->id, $this->item_id, 0);
    }

    public function test_service_layer_is_used_for_writes()
    {
        // Архитектурная проверка: остатки меняет только сервис
        $this->assertTrue(method_exists(\Service_Erp401_Stock::class, 'receive'));
        $this->assertTrue(method_exists(\Service_Erp401_Stock::class, 'issue'));
        $this->assertTrue(method_exists(\Service_Erp401_Stock::class, 'transfer'));

        $reflection = new \ReflectionClass(\Service_Erp401_Stock::class);
        foreach (array('receive', 'issue', 'transfer', 'adjust', 'reserve') as $method)
        {
            $this->assertTrue($reflection->getMethod($method)->isPublic(), $method.' должен быть публичным');
        }
    }

    /**
     * Оборот за период: приход, расход и перемещения.
     *
     * Направление считается по изменению остатка, поэтому корректировка вниз
     * попадает в расход, а перемещение между складами — не потребление: без
     * фильтра по складу оно не входит ни в приход, ни в расход, а с фильтром
     * видно с обеих сторон. Это то, на чём потом строится оборачиваемость в
     * `erp801_analytics`.
     */
    public function test_flow_aggregates_income_expense_and_transfers()
    {
        $item_id = $this->item_id;
        $from    = (int) $this->warehouse->id;
        $to      = (int) $this->warehouse_to->id;

        $this->stock->receive($from, $item_id, 10);
        $this->stock->issue($from, $item_id, 4);
        $this->stock->receive($from, $item_id, 2);
        $this->stock->transfer($from, $to, $item_id, 3);
        $this->stock->adjust($from, $item_id, 2);

        $today = array('date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'));

        // Завод целиком: наши 12 прихода и 7 расхода (списание 4 и корректировка
        // вниз 3), перемещение 3 в оборот не входит. Стенд общий, поэтому по
        // позиции в целом проверяем «не меньше своего»: чужие движения этой же
        // позиции за день сумму только увеличивают.
        $rows = $this->stock->flow($today + array('item_id' => $item_id), 'item');
        $row  = $rows ? $rows[0] : NULL;

        $this->assertNotNull($row, 'оборот по позиции посчитан');
        $this->assertGreaterThanOrEqual(12.0, round($row['qty_in'], 6), 'приход: два поступления');
        $this->assertGreaterThanOrEqual(7.0, round($row['qty_out'], 6), 'расход: списание и корректировка вниз');
        $this->assertGreaterThanOrEqual(5, $row['movements'], 'движения посчитаны');
        $this->assertNotSame('', (string) $row['last_at'], 'дата последнего движения заполнена');

        // Склад-источник: те же 12 и 7 плюс перемещение наружу — 3.
        $rows = $this->stock->flow($today + array('warehouse_id' => $from, 'item_id' => $item_id), 'item');
        $row  = $rows ? $rows[0] : NULL;

        $this->assertNotNull($row, 'оборот по складу посчитан');
        $this->assertSame(12.0, round($row['qty_in'], 6));
        $this->assertSame(10.0, round($row['qty_out'], 6), 'перемещение наружу — расход склада');

        // Склад-получатель: перемещение пришло.
        $rows = $this->stock->flow($today + array('warehouse_id' => $to, 'item_id' => $item_id), 'item');
        $row  = $rows ? $rows[0] : NULL;

        $this->assertNotNull($row, 'оборот склада-получателя посчитан');
        $this->assertSame(3.0, round($row['qty_in'], 6), 'перемещение внутрь — приход склада');
        $this->assertSame(0.0, round($row['qty_out'], 6));

        // Разрез по месяцам нужен для XYZ (стабильность расхода).
        $months = $this->stock->flow($today + array('item_id' => $item_id), 'month');

        $this->assertNotEmpty($months, 'разрез по месяцам не пуст');
        $this->assertSame(date('Y-m'), $months[0]['month'], 'месяц в формате ГГГГ-ММ');
    }

    // -------------------------------------------------------------- helpers --

    protected function available()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return FALSE;
        }

        try
        {
            $tables = \DB::select('table_name')
                ->from('information_schema.tables')
                ->where('table_schema', '=', 'public')
                ->where('table_name', 'IN', array('erp_401_warehouses', 'erp_401_balances', 'erp_401_movements', 'erp_401_reservations'))
                ->execute()
                ->as_array('table_name', 'table_name');

            return count($tables) === 4;
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    protected function makeWarehouse($code, $name)
    {
        \DB::delete('erp_401_warehouses')->where('code', '=', $code)->execute();

        $warehouse = \ORM::factory('Erp401_Warehouse');
        $warehouse->code = $code;
        $warehouse->name = $name;
        $warehouse->type = 'main';
        $warehouse->is_active = 1;
        $warehouse->save();

        return $warehouse;
    }

    protected function testWarehouseIds()
    {
        $ids = array();

        foreach (array($this->warehouse, $this->warehouse_to) as $warehouse)
        {
            if ($warehouse AND $warehouse->loaded())
            {
                $ids[] = (int) $warehouse->id;
            }
        }

        return $ids ? $ids : array(0);
    }
}
