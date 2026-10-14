<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты продаж (модуль erp410_shipment).
 *
 * Проверяют обещание модуля на сквозном пути: заказ клиента → подтверждение с
 * проверкой кредитного лимита → резерв товара на складе (erp401) → сборка
 * отгрузки → проведение со списанием со склада → счёт → частичная и полная
 * оплата. Плюс то, что обычно ломается: состояния документов, откат
 * незавершённого резерва, повторный счёт, отгрузка без остатка и просроченная
 * оплата.
 *
 * Тесты работают на своих данных (клиент `ТЕСТ-410-*`, склад `ТЕСТ-410`,
 * заказы/отгрузки/счета с явными номерами `*-ТЕСТ-410-*`) и убирают их в
 * tearDown, поэтому демонстрационные данные не страдают. Если таблиц модуля
 * нет — тесты пропускаются.
 */
class Erp410ShipmentTest extends TestCase
{
    /** @var \Service_Erp410_Orders */
    protected $orders;

    /** @var \Service_Erp410_Shipments */
    protected $shipments;

    /** @var \Service_Erp410_Invoices */
    protected $invoices;

    /** @var \Service_Erp410_Customers */
    protected $customers;

    /** @var \Service_Erp410_Prices */
    protected $prices;

    /** @var int|null Идентификатор склада, созданного тестом */
    protected $warehouse_id;

    /** @var int Тестовый клиент (условия продажи живут отдельно от демо-данных) */
    protected $customer_id = 999410;

    /** @var array Созданные документы: чистим по идентификаторам */
    protected $created = array('orders' => array(), 'shipments' => array(), 'invoices' => array());

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль продаж недоступен — выполните миграции erp410_shipment');
            return;
        }

        $this->orders    = \Service_Erp410_Orders::instance();
        $this->shipments = \Service_Erp410_Shipments::instance();
        $this->invoices  = \Service_Erp410_Invoices::instance();
        $this->customers = \Service_Erp410_Customers::instance();
        $this->prices    = \Service_Erp410_Prices::instance();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $order_ids    = array_merge($this->created['orders'], $this->ids('erp_410_orders', 'ЗКЛ-ТЕСТ-410-%'));
            $shipment_ids = array_merge($this->created['shipments'], $this->ids('erp_410_shipments', 'ОТГ-ТЕСТ-410-%'));
            $invoice_ids  = array_merge($this->created['invoices'], $this->ids('erp_410_invoices', 'СЧ-ТЕСТ-410-%'));

            // Счета по тестовым заказам/отгрузкам могли получить сервисный номер.
            if ($this->created['orders'] OR $this->created['shipments'])
            {
                $query = \DB::select('id')->from('erp_410_invoices');

                if ($this->created['orders'] OR $this->created['shipments'])
                {
                    $query->where_open();

                    if ($this->created['orders'])
                    {
                        $query->or_where('order_id', 'IN', $this->created['orders']);
                    }

                    if ($this->created['shipments'])
                    {
                        $query->or_where('shipment_id', 'IN', $this->created['shipments']);
                    }

                    $query->where_close();
                }

                foreach ($query->execute()->as_array() as $row)
                {
                    $invoice_ids[] = (int) $row['id'];
                }
            }

            $invoice_ids  = array_values(array_unique($invoice_ids));
            $shipment_ids = array_values(array_unique($shipment_ids));
            $order_ids    = array_values(array_unique($order_ids));

            if ($invoice_ids)
            {
                \DB::delete('erp_410_invoice_lines')->where('invoice_id', 'IN', $invoice_ids)->execute();
                \DB::delete('erp_410_invoices')->where('id', 'IN', $invoice_ids)->execute();
            }

            if ($shipment_ids)
            {
                \DB::delete('erp_410_shipment_lines')->where('shipment_id', 'IN', $shipment_ids)->execute();
                \DB::delete('erp_410_shipments')->where('id', 'IN', $shipment_ids)->execute();
            }

            if ($order_ids)
            {
                // Резервы склада, поставленные под тестовые заказы.
                \DB::delete('erp_401_reservations')
                    ->where('ref_type', '=', 'erp410_order')
                    ->where('ref_id', 'IN', $order_ids)
                    ->execute();

                \DB::delete('erp_410_order_lines')->where('order_id', 'IN', $order_ids)->execute();
                \DB::delete('erp_410_orders')->where('id', 'IN', $order_ids)->execute();
            }

            // История цен, оставленная тестом.
            \DB::delete('erp_410_prices')->where('source_ref', 'LIKE', 'ТЕСТ-410%')->execute();

            // Условия тестового клиента.
            \DB::delete('erp_410_customers')->where('customer_id', '=', (int) $this->customer_id)->execute();

            // Склад теста: заводим сами, чтобы не трогать демонстрационные.
            if ($this->warehouse_id)
            {
                \DB::delete('erp_401_balances')->where('warehouse_id', '=', (int) $this->warehouse_id)->execute();
                \DB::delete('erp_401_movements')->where('warehouse_id', '=', (int) $this->warehouse_id)->execute();
                \DB::delete('erp_401_reservations')->where('warehouse_id', '=', (int) $this->warehouse_id)->execute();
                \DB::delete('erp_401_warehouses')->where('id', '=', (int) $this->warehouse_id)->execute();
                $this->warehouse_id = NULL;
            }
        }
        catch (\Exception $e)
        {
            // Уборка не должна ронять прогон: об ошибке скажет следующий запуск.
        }
    }

    // =========================================================================
    //  Нумерация и заказ
    // =========================================================================

    public function test_numbering_is_scoped_per_document()
    {
        $this->assertMatchesRegularExpression('/^ЗКЛ-\d{4}-\d{6}$/', (string) $this->orders->number(),
            'номер заказа клиента — сквозной на год');
        $this->assertMatchesRegularExpression('/^ОТГ-\d{4}-\d{6}$/', (string) $this->shipments->number(),
            'номер отгрузки отличается от номера заказа');
        $this->assertMatchesRegularExpression('/^СЧ-\d{4}-\d{6}$/', (string) $this->invoices->number(),
            'номер счёта отличается от номера отгрузки');
    }

    public function test_order_is_created_with_number_lines_and_amount()
    {
        $order = $this->make_order(array(
            'lines' => array(
                array('name' => 'ТЕСТ-410 позиция', 'qty' => 3, 'price' => 100, 'uom_code' => 'шт'),
                array('name' => 'ТЕСТ-410 вторая', 'qty' => 2, 'price' => 50),
            ),
        ));

        $this->assertMatchesRegularExpression('/^ЗКЛ-ТЕСТ-410-/', (string) $order->number);
        $this->assertSame('draft', (string) $order->status);
        $this->assertSame(400.0, (float) $order->amount, 'сумма заказа = сумма позиций');

        $lines = $this->orders->lines($order->id);

        $this->assertCount(2, $lines);
        $this->assertSame(10, (int) $lines[0]->line_no);
        $this->assertSame(20, (int) $lines[1]->line_no);
        $this->assertSame('шт', (string) $lines[1]->uom_code, 'единица измерения по умолчанию');
        $this->assertSame(3.0, (float) $lines[0]->remaining(), 'пока ничего не отгружено, остаток = количество');
    }

    public function test_order_without_customer_or_lines_is_rejected()
    {
        $this->assertSame('customer_required', $this->reason(function () {
            $this->orders->create(array(
                'lines' => array(array('name' => 'ТЕСТ-410 без клиента', 'qty' => 1, 'price' => 1)),
            ));
        }), 'заказ без покупателя не создаётся');

        $this->assertSame('empty_document', $this->reason(function () {
            $this->orders->create(array('customer_id' => $this->customer_id));
        }), 'заказ без позиций не создаётся');
    }

    public function test_order_is_frozen_once_stock_is_reserved()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 5, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 2);

        // Подтверждённый заказ ещё правят: продавец может уточнить условия до
        // того, как товар заморожен на складе.
        $this->assertTrue($order->is_editable());

        $this->orders->reserve($order->id);

        $order = $this->orders->get($order->id);

        $this->assertFalse($order->is_editable(), 'после резерва состав заказа не меняется');

        $this->assertSame('invalid_transition', $this->reason(function () use ($order) {
            $this->orders->add_line($order->id, array('name' => 'ТЕСТ-410 поздно', 'qty' => 1, 'price' => 1));
        }));

        $this->assertSame('invalid_transition', $this->reason(function () use ($order) {
            $this->orders->update($order->id, array('note' => 'ТЕСТ-410 поздно'));
        }));
    }

    // =========================================================================
    //  Цены
    // =========================================================================

    public function test_price_cascade_prefers_contract_over_base()
    {
        $item_id = $this->item_id();

        $this->prices->record(array(
            'item_id'    => $item_id,
            'price'      => 1000,
            'source_ref' => 'ТЕСТ-410 базовая',
        ));

        $base = $this->prices->price_for($item_id, NULL);

        $this->assertTrue($base['found']);
        $this->assertSame(1000.0, (float) $base['price']);
        $this->assertNull($base['customer_id'], 'базовая цена не привязана к клиенту');

        $this->prices->record(array(
            'item_id'     => $item_id,
            'customer_id' => $this->customer_id,
            'price'       => 900,
            'discount'    => 10,
            'source_ref'  => 'ТЕСТ-410 договорная',
        ));

        $contract = $this->prices->price_for($item_id, $this->customer_id);

        $this->assertSame($this->customer_id, (int) $contract['customer_id']);
        $this->assertSame(900.0, (float) $contract['price'], 'договорная цена клиента важнее базовой');
        $this->assertSame(810.0, (float) $contract['net_price'], 'итоговая цена = цена минус скидка записи');
        $this->assertSame(1000.0, (float) $this->prices->price_for($item_id, NULL)['price'],
            'для другого клиента базовая цена не изменилась');
    }

    public function test_order_line_takes_price_from_customer_price_list()
    {
        $item_id = $this->item_id();

        $this->prices->record(array(
            'item_id'     => $item_id,
            'customer_id' => $this->customer_id,
            'price'       => 250,
            'source_ref'  => 'ТЕСТ-410 цена для заказа',
        ));

        $order = $this->make_order(array(
            'lines' => array(array('item_id' => $item_id, 'name' => 'ТЕСТ-410 из прайса', 'qty' => 4)),
        ));

        $line = $this->orders->lines($order->id)[0];

        $this->assertSame(250.0, (float) $line->price, 'пустая цена подставляется из прайса клиента');
        $this->assertSame(1000.0, (float) $order->amount);
    }

    public function test_price_for_unknown_item_warns_instead_of_inventing()
    {
        $price = $this->prices->price_for(999410, NULL);

        $this->assertFalse($price['found'], 'цены нет и это видно, а не замаскировано нулём');
        $this->assertSame(0.0, (float) $price['price']);
        $this->assertStringContainsString('цены нет', $price['basis']);
    }

    // =========================================================================
    //  Кредитный лимит
    // =========================================================================

    public function test_confirm_checks_credit_limit_and_force_overrides_it()
    {
        $this->customers->save($this->customer_id, array('credit_limit' => 1000, 'credit_days' => 14));

        $order = $this->make_order(array(
            'lines' => array(array('name' => 'ТЕСТ-410 сверх лимита', 'qty' => 1, 'price' => 5000)),
        ));

        $this->assertSame('credit_limit', $this->reason(function () use ($order) {
            $this->orders->confirm($order->id);
        }), 'заказ сверх свободного остатка лимита не подтверждается');

        $order = $this->orders->confirm($order->id, NULL, TRUE);

        $this->assertSame('confirmed', (string) $order->status, 'явное решение человека обходит лимит');

        $check = $this->customers->credit_check($this->customer_id, 5000);

        $this->assertFalse($check['allowed']);
        $this->assertSame(1000.0, (float) $check['limit']);
        $this->assertSame(0.0, (float) $check['exposure'], 'выставленных счетов у клиента ещё нет');
        $this->assertSame(1000.0, (float) $check['available']);
    }

    public function test_credit_limit_without_profile_allows_any_amount()
    {
        $check = $this->customers->credit_check(999411, 100000);

        $this->assertTrue($check['allowed'], 'без условий продажи проверять нечего: работаем по предоплате');
        $this->assertSame(0.0, (float) $check['limit']);
        $this->assertSame('лимит не задан', $check['reason']);
    }

    // =========================================================================
    //  Резерв склада
    // =========================================================================

    public function test_reserve_places_stock_reservation_and_release_removes_it()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 4);

        $available_before = \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id);

        $result = $this->orders->reserve($order->id);

        $this->assertSame(1, $result['reserved']);
        $this->assertSame(array(), $result['warnings']);

        $order = $this->orders->get($order->id);
        $line  = $this->orders->lines($order->id)[0];

        $this->assertSame('reserved', (string) $order->status);
        $this->assertSame(4.0, (float) $line->qty_reserved);
        $this->assertNotNull($line->reservation_id, 'строка помнит резерв склада');
        $this->assertSame($available_before - 4, \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id),
            'свободный остаток уменьшился на зарезервированное');

        $released = $this->orders->release($order->id);

        $this->assertSame(1, $released);
        $this->assertSame($available_before, \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id),
            'снятие резерва возвращает остаток');
        $this->assertSame('confirmed', (string) $this->orders->get($order->id)->status);
    }

    public function test_reserve_rolls_back_when_some_line_has_no_stock()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 5, array('note' => 'ТЕСТ-410 запас'));

        // Вторая позиция без номенклатуры и с несуществующим товаром: склад по ней
        // откажет, и весь резерв заказа должен откатиться.
        $order = $this->orders->create(array(
            'number'       => 'ЗКЛ-ТЕСТ-410-ROLLBACK',
            'customer_id'  => $this->customer_id,
            'warehouse_id' => $warehouse_id,
            'lines'        => array(
                array('item_id' => $item_id, 'name' => 'ТЕСТ-410 есть на складе', 'qty' => 2, 'price' => 100),
                array('item_id' => 999409, 'name' => 'ТЕСТ-410 нет на складе', 'qty' => 3, 'price' => 100),
            ),
        ));

        $this->created['orders'][] = (int) $order->id;

        $this->orders->confirm($order->id);

        $available_before = \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id);

        $this->assertSame('insufficient_stock', $this->reason(function () use ($order) {
            $this->orders->reserve($order->id);
        }));

        $lines = $this->orders->lines($order->id);

        $this->assertNull($lines[0]->reservation_id, 'резерв по первой строке снят вместе с ошибкой');
        $this->assertSame(0.0, (float) $lines[0]->qty_reserved);
        $this->assertSame('open', (string) $lines[0]->status);
        $this->assertSame($available_before, \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id),
            'склад не остался замороженным наполовину');
    }

    public function test_reserve_skips_lines_without_item()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->orders->create(array(
            'number'       => 'ЗКЛ-ТЕСТ-410-SKIP',
            'customer_id'  => $this->customer_id,
            'warehouse_id' => $warehouse_id,
            'lines'        => array(
                array('item_id' => $item_id, 'name' => 'ТЕСТ-410 с номенклатурой', 'qty' => 1, 'price' => 100),
                array('name' => 'ТЕСТ-410 без номенклатуры', 'qty' => 1, 'price' => 100),
            ),
        ));

        $this->created['orders'][] = (int) $order->id;

        $this->orders->confirm($order->id);

        $result = $this->orders->reserve($order->id);

        $this->assertSame(1, $result['reserved'], 'строка без номенклатуры не блокирует резерв остальных');
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('без номенклатуры', $result['warnings'][0]);
    }

    // =========================================================================
    //  Отгрузка
    // =========================================================================

    public function test_shipment_is_built_from_pending_order_lines()
    {
        $item_id = $this->item_id();

        $order = $this->confirmed_order($item_id, 3);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-BUILD'));
        $this->created['shipments'][] = (int) $shipment->id;

        $this->assertMatchesRegularExpression('/^ОТГ-ТЕСТ-410-/', (string) $shipment->number);
        $this->assertSame('draft', (string) $shipment->status);
        $this->assertSame((int) $order->id, (int) $shipment->order_id);
        $this->assertSame($item_id, (int) $this->shipments->lines($shipment->id)[0]->item_id);

        $line = $this->shipments->lines($shipment->id)[0];

        $this->assertSame(3.0, (float) $line->qty, 'в отгрузку попадает то, что заказ ещё не отгрузил');
        $this->assertTrue($this->orders->get($order->id)->can_ship(),
            'пока отгрузка не проведена, заказ остаётся отгружаемым');
    }

    public function test_shipment_is_ready_only_once()
    {
        $item_id = $this->item_id();

        $order = $this->confirmed_order($item_id, 1);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-READY'));
        $this->created['shipments'][] = (int) $shipment->id;

        $shipment = $this->shipments->ready($shipment->id);

        $this->assertSame('ready', (string) $shipment->status);

        $this->assertSame('invalid_transition', $this->reason(function () use ($shipment) {
            $this->shipments->ready($shipment->id);
        }));
    }

    public function test_post_consumes_reservation_and_ships_order()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 4);
        $this->orders->reserve($order->id);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-POST'));
        $this->created['shipments'][] = (int) $shipment->id;

        $before = \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id);

        $shipment = $this->shipments->post($shipment->id);

        $this->assertSame('shipped', (string) $shipment->status);
        $this->assertNotNull($shipment->posted_at);
        $this->assertSame($before - 4, \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id),
            'проведение отгрузки списывает товар со склада');

        $order = $this->orders->get($order->id);
        $line  = $this->orders->lines($order->id)[0];

        $this->assertSame('shipped', (string) $order->status, 'заказ отгружен полностью');
        $this->assertSame(4.0, (float) $line->qty_shipped);
        $this->assertSame('shipped', (string) $line->status);
        $this->assertNull($line->reservation_id, 'израсходованный резерв снят со строки');

        $price = \DB::select('price')->from('erp_410_prices')
            ->where('source_ref', 'LIKE', 'ОТГ-ТЕСТ-410-POST%')
            ->execute()
            ->current();

        $this->assertNotSame(FALSE, $price, 'проведение пишет цену отгрузки в историю');
    }

    public function test_post_without_reservation_issues_stock_directly()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 2);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-ISSUE'));
        $this->created['shipments'][] = (int) $shipment->id;

        $before = \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id);

        $shipment = $this->shipments->post($shipment->id);

        $this->assertSame('shipped', (string) $shipment->status);
        $this->assertSame($before - 2, \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id),
            'без резерва товар списывается напрямую');
    }

    public function test_post_without_stock_is_rejected()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        $order = $this->confirmed_order($item_id, 3);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-EMPTY'));
        $this->created['shipments'][] = (int) $shipment->id;

        $this->assertSame('insufficient_stock', $this->reason(function () use ($shipment) {
            $this->shipments->post($shipment->id);
        }), 'отгрузка без остатка не проводится');

        $this->assertSame('draft', (string) $this->shipments->get($shipment->id)->status,
            'неудачное проведение не меняет состояние документа');
    }

    public function test_posted_shipment_is_frozen()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 1);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-FROZEN'));
        $this->created['shipments'][] = (int) $shipment->id;

        $this->shipments->post($shipment->id);

        $this->assertSame('already_posted', $this->reason(function () use ($shipment) {
            $this->shipments->post($shipment->id);
        }), 'повторное проведение запрещено');

        $this->assertSame('already_posted', $this->reason(function () use ($shipment) {
            $this->shipments->cancel($shipment->id);
        }), 'проведённую отгрузку нельзя отменить');

        $this->assertSame('already_posted', $this->reason(function () use ($shipment) {
            $this->shipments->remove_line($this->shipments->lines($shipment->id)[0]->id);
        }), 'строки проведённой отгрузки не меняются');
    }

    public function test_partial_shipment_keeps_order_in_progress()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 20, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 10);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-PARTIAL'));
        $this->created['shipments'][] = (int) $shipment->id;

        $line = $this->shipments->lines($shipment->id)[0];

        $this->shipments->update_line($line->id, array('qty' => 4));
        $this->shipments->post($shipment->id);

        $order = $this->orders->get($order->id);
        $order_line = $this->orders->lines($order->id)[0];

        $this->assertSame('picking', (string) $order->status,
            'частичная отгрузка оставляет заказ в работе (состояние: '.(string) $order->status.')');
        $this->assertSame(4.0, (float) $order_line->qty_shipped);
        $this->assertSame(6.0, (float) $order_line->remaining(), 'остаток к отгрузке пересчитан');
    }

    // =========================================================================
    //  Счета и оплата
    // =========================================================================

    public function test_invoice_is_issued_from_posted_shipment()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 2);

        $shipment = $this->shipments->build($order->id, array('number' => 'ОТГ-ТЕСТ-410-INVOICE'));
        $this->created['shipments'][] = (int) $shipment->id;

        $this->assertSame('invalid_transition', $this->reason(function () use ($shipment) {
            $this->invoices->from_shipment($shipment->id);
        }), 'счёт выставляется только по проведённой отгрузке');

        $this->shipments->post($shipment->id);

        $invoice = $this->invoices->from_shipment($shipment->id);
        $this->created['invoices'][] = (int) $invoice->id;

        $this->assertMatchesRegularExpression('/^СЧ-/', (string) $invoice->number);
        $this->assertSame('draft', (string) $invoice->status);
        $this->assertSame((int) $order->id, (int) $invoice->order_id);
        $this->assertSame((int) $shipment->id, (int) $invoice->shipment_id);
        $this->assertSame(2.0, (float) $this->invoices->lines($invoice->id)[0]->qty);
        $this->assertSame((float) $this->shipments->amount($shipment->id), (float) $invoice->amount,
            'сумма счёта равна сумме отгрузки');

        $this->assertSame('duplicate_invoice', $this->reason(function () use ($shipment) {
            $this->invoices->from_shipment($shipment->id);
        }), 'второй счёт по той же отгрузке не выставляется');
    }

    public function test_invoice_payment_is_partial_then_full()
    {
        $invoice = $this->issued_invoice();

        $this->assertTrue($invoice->is_payable());

        $invoice = $this->invoices->register_payment($invoice->id, 100);

        $this->assertSame('issued', (string) $invoice->status, 'частичная оплата не закрывает счёт');
        $this->assertSame(100.0, (float) $invoice->paid_amount);
        $this->assertSame(400.0, (float) $invoice->outstanding());

        $this->assertSame('invalid_qty', $this->reason(function () use ($invoice) {
            $this->invoices->register_payment($invoice->id, 1000);
        }), 'оплата больше остатка отклоняется');

        $invoice = $this->invoices->register_payment($invoice->id, 400);

        $this->assertSame('paid', (string) $invoice->status);
        $this->assertSame(0.0, (float) $invoice->outstanding());
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_payment_on_draft_invoice_is_rejected()
    {
        $invoice = $this->draft_invoice();

        $this->assertSame('invalid_transition', $this->reason(function () use ($invoice) {
            $this->invoices->register_payment($invoice->id, 10);
        }), 'оплату принимают только по выставленному счёту');
    }

    public function test_invoice_can_be_cancelled_once()
    {
        $invoice = $this->draft_invoice();

        $invoice = $this->invoices->cancel($invoice->id, 'ТЕСТ-410 ошиблись суммой');

        $this->assertSame('cancelled', (string) $invoice->status);

        $this->assertSame('invalid_transition', $this->reason(function () use ($invoice) {
            $this->invoices->cancel($invoice->id);
        }), 'повторная отмена запрещена');
    }

    public function test_paid_invoice_cannot_be_cancelled()
    {
        $invoice = $this->issued_invoice();

        $this->invoices->register_payment($invoice->id, 500);

        $this->assertSame('invalid_transition', $this->reason(function () use ($invoice) {
            $this->invoices->cancel($invoice->id);
        }), 'оплаченный счёт не отменяется — деньги уже получены');
    }

    // =========================================================================
    //  Отмена заказа и условия клиента
    // =========================================================================

    public function test_cancel_order_releases_reservations()
    {
        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        \Service_Erp401_Stock::instance()->receive($warehouse_id, $item_id, 10, array('note' => 'ТЕСТ-410 запас'));

        $order = $this->confirmed_order($item_id, 5);
        $this->orders->reserve($order->id);

        $available_reserved = \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id);

        $this->assertSame(5.0, (float) $available_reserved, 'резерв уменьшал свободный остаток');

        $order = $this->orders->cancel($order->id, 'ТЕСТ-410 клиент передумал');

        $this->assertSame('cancelled', (string) $order->status);
        $this->assertSame(10.0, \Service_Erp401_Stock::instance()->available($warehouse_id, $item_id),
            'отмена заказа возвращает товар в свободный остаток');
    }

    public function test_customer_conditions_are_saved_and_visible()
    {
        $profile = $this->customers->save($this->customer_id, array(
            'payment_terms' => 'ТЕСТ-410 отсрочка 14 дней',
            'credit_days'   => 14,
            'credit_limit'  => 500000,
            'discount'      => 3,
            'is_active'     => 1,
        ));

        $this->assertSame(14, $profile->credit_days());
        $this->assertTrue($profile->is_active());

        $profile = $this->customers->profile($this->customer_id, FALSE);

        $this->assertSame(500000.0, (float) $profile->credit_limit);
        $this->assertSame(3.0, $profile->discount());
        $this->assertSame('ТЕСТ-410 отсрочка 14 дней', $this->customers->payment_label($this->customer_id));

        // Задолженность считается по выставленным счетам клиента.
        $invoice = $this->issued_invoice();

        $this->invoices->register_payment($invoice->id, 200);

        $this->assertSame(300.0, $this->customers->credit_exposure($this->customer_id),
            'в задолженность попадает только неоплаченный остаток');
        $this->assertSame(300.0, (float) $this->invoices->get($invoice->id)->outstanding());

        $check = $this->customers->credit_check($this->customer_id, 499700);

        $this->assertTrue($check['allowed'], 'свободный остаток лимита учитывает задолженность');
        $this->assertSame(499700.0, (float) $check['available']);
    }

    public function test_empty_customer_conditions_are_rejected()
    {
        $this->assertSame('customer_required', $this->reason(function () {
            $this->customers->save(0, array('credit_limit' => 100));
        }));
    }

    // =========================================================================
    //  Точки входа: MCP и права
    // =========================================================================

    public function test_mcp_declares_read_only_sales_tools()
    {
        $declaration = include APPPATH.'modules/erp410_shipment/mcp.php';

        $this->assertSame('erp410', $declaration['module']);

        $expected = array(
            'sales.stats', 'sales.orders.search', 'sales.order.get',
            'sales.shipments.search', 'sales.shipment.get',
            'sales.invoices.search', 'sales.invoice.get',
            'sales.customers.search', 'sales.customer.get',
            'sales.credit.check', 'sales.due', 'sales.price.get',
            'sales.price.history', 'sales.catalog.prices',
        );

        foreach ($expected as $tool)
        {
            $this->assertArrayHasKey($tool, $declaration['tools'], 'инструмент '.$tool.' объявлен в mcp.php');
        }

        foreach ($declaration['tools'] as $name => $tool)
        {
            $this->assertSame('erp410.view', $tool['scope'], 'чтение продаж — под правом просмотра ('.$name.')');
            $this->assertFalse($tool['mutating'], 'инструмент '.$name.' не должен менять данные');
            $this->assertTrue(is_callable($tool['handler']), 'у инструмента '.$name.' есть обработчик');
        }
    }

    public function test_controller_helpers_are_defined()
    {
        // Дешёвая статическая проверка: контроллер не должен вызывать
        // `$this->helper()`, которого нет в нём или в родителе. Именно такая
        // опечатка (`_posted()` в Api) уронила живые действия JSON API в 500,
        // а дым и тесты сервисов её не видят.
        $missing = array();

        foreach (glob(APPPATH.'modules/erp410_shipment/classes/Controller/Erp410/*.php') as $file)
        {
            $class = 'Controller_Erp410_'.basename($file, '.php');

            $this->assertTrue(class_exists($class), 'контроллер '.$class.' загружается');

            $source = (string) file_get_contents($file);

            if (preg_match_all('/\$this->([a-z_][a-z0-9_]*)\s*\(/i', $source, $matches))
            {
                foreach (array_unique($matches[1]) as $method)
                {
                    if ( ! method_exists($class, $method))
                    {
                        $missing[] = $class.'::'.$method.'() в '.basename($file);
                    }
                }
            }
        }

        $this->assertSame(array(), $missing, 'контроллеры вызывают только объявленные методы');
    }

    public function test_api_actions_exist_for_every_public_action()
    {
        // Действия API перечислены в маршрутах `/sales/api/<action>`: каждому
        // должно соответствовать действие контроллера, иначе агент получит 404.
        $expected = array(
            'stats', 'orders', 'order', 'order_create', 'order_update', 'order_line_add', 'order_act',
            'shipments', 'shipment', 'shipment_build', 'shipment_create', 'shipment_act',
            'invoices', 'invoice', 'invoice_create', 'invoice_act',
            'customers', 'customer', 'customer_save', 'price', 'prices', 'price_record',
        );

        foreach ($expected as $action)
        {
            $this->assertTrue(
                method_exists('Controller_Erp410_Api', 'action_'.$action),
                'метод action_'.$action.' объявлен в Controller_Erp410_Api'
            );
        }
    }

    public function test_acl_declares_sales_rights()
    {
        $acl = \Kohana::$config->load('acl')->as_array();

        foreach (array('erp410.view', 'erp410.manage', 'erp410.ship', 'erp410.invoice') as $ability)
        {
            $this->assertArrayHasKey($ability, $acl, 'право '.$ability.' объявлено');
            $this->assertNotEmpty($acl[$ability], 'право '.$ability.' кому-то выдано');
        }
    }

    // =========================================================================
    //  Данные теста
    // =========================================================================

    /**
     * Заказ с явным тестовым номером, чтобы уборка нашла его даже после падения.
     *
     * @param   array   $data
     * @return  \Model_Erp410_Order
     */
    protected function make_order(array $data = array())
    {
        static $seq = 0;

        $seq++;

        $data += array(
            'number'      => 'ЗКЛ-ТЕСТ-410-'.$seq.'-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'customer_id' => $this->customer_id,
            'warehouse_id'=> $this->warehouse(),
        );

        $order = $this->orders->create($data);

        $this->created['orders'][] = (int) $order->id;

        return $order;
    }

    /**
     * Подтверждённый заказ на одну позицию: с него начинается почти каждый тест.
     *
     * @param   int     $item_id
     * @param   float   $qty
     * @param   float   $price
     * @return  \Model_Erp410_Order
     */
    protected function confirmed_order($item_id, $qty, $price = 100)
    {
        $order = $this->make_order(array(
            'lines' => array(array(
                'item_id' => $item_id,
                'name'    => 'ТЕСТ-410 товар',
                'qty'     => $qty,
                'price'   => $price,
            )),
        ));

        return $this->orders->confirm($order->id);
    }

    /**
     * Черновой счёт на 500 ₽ по ручной отгрузке.
     *
     * @return  \Model_Erp410_Invoice
     */
    protected function draft_invoice()
    {
        $item_id = $this->item_id();

        $invoice = $this->invoices->create(array(
            'number'      => 'СЧ-ТЕСТ-410-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'customer_id' => $this->customer_id,
            'lines'       => array(array(
                'item_id' => $item_id,
                'name'    => 'ТЕСТ-410 счёт',
                'qty'     => 5,
                'price'   => 100,
            )),
        ));

        $this->created['invoices'][] = (int) $invoice->id;

        return $invoice;
    }

    /**
     * Выставленный счёт на 500 ₽: оплата принимается только по нему.
     *
     * @return  \Model_Erp410_Invoice
     */
    protected function issued_invoice()
    {
        return $this->invoices->issue($this->draft_invoice()->id);
    }

    /**
     * Склад теста. Код уникален, поэтому склад от упавшего прогона переиспользуем.
     *
     * @return  int
     */
    protected function warehouse()
    {
        if ($this->warehouse_id)
        {
            return (int) $this->warehouse_id;
        }

        if ( ! class_exists('Service_Erp401_Stock'))
        {
            $this->markTestSkipped('Модуль склада erp401 недоступен');
        }

        $existing = \DB::select('id')->from('erp_401_warehouses')
            ->where('code', '=', 'ТЕСТ-410')
            ->limit(1)
            ->execute()
            ->current();

        if ($existing)
        {
            $this->warehouse_id = (int) $existing['id'];

            return $this->warehouse_id;
        }

        $warehouse = \Service_Erp401_Stock::instance()->create_warehouse(array(
            'code' => 'ТЕСТ-410',
            'name' => 'Склад теста erp410',
            'type' => 'main',
        ));

        $this->warehouse_id = (int) $warehouse->id;

        return $this->warehouse_id;
    }

    /**
     * Первая позиция каталога — номенклатура есть в демонстрационных данных.
     *
     * @return  int
     */
    protected function item_id()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            $this->markTestSkipped('Модуль склада erp401 недоступен');
        }

        static $item_id = NULL;

        if ($item_id === NULL)
        {
            $catalog = \Service_Erp401_Stock::instance()->catalog(1);

            if ( ! $catalog)
            {
                $this->markTestSkipped('В каталоге нет номенклатуры');
            }

            $item_id = (int) $catalog[0]['id'];
        }

        return $item_id;
    }

    // =========================================================================
    //  Вспомогательное
    // =========================================================================

    /**
     * Причина ошибки сервиса — состояния проверяем по коду, а не по тексту.
     *
     * @param   callable    $callback
     * @return  string|null
     */
    protected function reason(callable $callback)
    {
        try
        {
            $callback();
        }
        catch (\Exception_Erp410_Shipment $e)
        {
            return $e->reason();
        }

        return NULL;
    }

    /**
     * Проверка доступности модуля: таблицы созданы миграцией.
     *
     * @return  bool
     */
    protected function available()
    {
        static $available = NULL;

        if ($available !== NULL)
        {
            return $available;
        }

        try
        {
            \DB::select('id')->from('erp_410_orders')->limit(1)->execute();

            $available = TRUE;
        }
        catch (\Exception $e)
        {
            $available = FALSE;
        }

        return $available;
    }

    /**
     * @param   string  $table
     * @param   string  $pattern
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
}
