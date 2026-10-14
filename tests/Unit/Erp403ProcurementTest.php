<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты закупок (модуль erp403_procurement).
 *
 * Проверяют обещание модуля на сквозном пути: заявка (руками или по минимуму
 * склада) → согласование → заказ поставщику с ценой и сроком → приёмка →
 * приход на склад (erp401) → история цен. Плюс то, что обычно ломается:
 * состояния документов, задвоение заявок, позиции без номенклатуры и
 * проведённая приёмка.
 *
 * Тесты работают на своих данных (`ЗК-ТЕСТ-403-*`, `ЗП-ТЕСТ-403-*`,
 * `ПР-ТЕСТ-403-*`) и убирают их в tearDown, поэтому демонстрационные данные и
 * чужие документы не страдают. Если таблиц модуля нет — тесты пропускаются.
 */
class Erp403ProcurementTest extends TestCase
{
    /** @var \Service_Erp403_Requests */
    protected $requests;

    /** @var \Service_Erp403_Orders */
    protected $orders;

    /** @var \Service_Erp403_Receipts */
    protected $receipts;

    /** @var \Service_Erp403_Suppliers */
    protected $suppliers;

    /** @var int|null Идентификатор склада, созданного тестом */
    protected $warehouse_id;

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль закупок недоступен — выполните миграции erp403');
            return;
        }

        $this->requests  = \Service_Erp403_Requests::instance();
        $this->orders    = \Service_Erp403_Orders::instance();
        $this->receipts  = \Service_Erp403_Receipts::instance();
        $this->suppliers = \Service_Erp403_Suppliers::instance();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $order_ids   = $this->ids('erp_403_orders', 'ЗП-ТЕСТ-403-%');
            $receipt_ids = $this->ids('erp_403_receipts', 'ПР-ТЕСТ-403-%');
            $request_ids = $this->ids('erp_403_requests', 'ЗК-ТЕСТ-403-%');

            // Заказы, собранные из заявок теста, нумерует сервис (ЗП-ГГГГ-НННННН):
            // ищем их по ссылке на строки заявок, иначе они остались бы в базе.
            if ($request_ids)
            {
                $line_ids = array();

                foreach (\DB::select('id')->from('erp_403_request_lines')
                    ->where('request_id', 'IN', $request_ids)
                    ->execute()
                    ->as_array() as $row)
                {
                    $line_ids[] = (int) $row['id'];
                }

                if ($line_ids)
                {
                    foreach (\DB::select('order_id')->from('erp_403_order_lines')
                        ->where('request_line_id', 'IN', $line_ids)
                        ->execute()
                        ->as_array() as $row)
                    {
                        $order_ids[] = (int) $row['order_id'];
                    }

                    \DB::update('erp_403_orders')
                        ->set(array('status' => 'cancelled'))
                        ->where('id', 'IN', array_unique($order_ids))
                        ->execute();
                }
            }

            $order_ids = array_values(array_unique($order_ids));

            // Приёмки по этим заказам тоже убираем: они ссылаются на склад теста.
            if ($order_ids)
            {
                foreach (\DB::select('id')->from('erp_403_receipts')
                    ->where('order_id', 'IN', $order_ids)
                    ->execute()
                    ->as_array() as $row)
                {
                    $receipt_ids[] = (int) $row['id'];
                }

                $receipt_ids = array_values(array_unique($receipt_ids));
            }

            // Авто-заявки теста нумерует сервис (ЗК-ГГГГ-НННННН): их опознаём по
            // складу теста и источнику, иначе они остались бы в базе.
            if ($this->warehouse_id)
            {
                foreach (\DB::select('id')->from('erp_403_requests')
                    ->where('source', '=', 'auto')
                    ->where('warehouse_id', '=', (int) $this->warehouse_id)
                    ->execute()
                    ->as_array() as $row)
                {
                    $request_ids[] = (int) $row['id'];
                }

                $request_ids = array_unique($request_ids);
            }

            // Приёмки уходят каскадом со строками.
            if ($receipt_ids)
            {
                \DB::delete('erp_403_receipt_lines')->where('receipt_id', 'IN', $receipt_ids)->execute();
                \DB::delete('erp_403_receipts')->where('id', 'IN', $receipt_ids)->execute();
            }

            if ($order_ids)
            {
                \DB::delete('erp_403_order_lines')->where('order_id', 'IN', $order_ids)->execute();
                \DB::delete('erp_403_orders')->where('id', 'IN', $order_ids)->execute();
            }

            if ($request_ids)
            {
                \DB::delete('erp_403_request_lines')->where('request_id', 'IN', $request_ids)->execute();
                \DB::delete('erp_403_requests')->where('id', 'IN', $request_ids)->execute();
            }

            // История цен, оставленная тестом.
            \DB::delete('erp_403_prices')
                ->where('name', 'LIKE', 'ТЕСТ-403%')
                ->execute();

            // Склад теста: заводим его сами, чтобы не трогать демонстрационные.
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
    //  Заявки
    // =========================================================================

    public function test_request_is_created_with_number_and_lines()
    {
        $request = $this->make_request(array(
            'lines' => array(
                array('name' => 'ТЕСТ-403 резистор', 'qty' => 100, 'uom_code' => 'шт'),
                array('name' => 'ТЕСТ-403 конденсатор', 'qty' => 50),
            ),
        ));

        $this->assertMatchesRegularExpression('/^ЗК-ТЕСТ-403-/', (string) $request->number);
        $this->assertMatchesRegularExpression('/^ЗК-\d{4}-\d{6}$/', (string) $this->requests->number(),
            'номер, который выдаёт сервис, — сквозной на год');
        $this->assertSame('draft', (string) $request->status);
        $this->assertCount(2, $this->requests->lines($request->id));

        $lines = $this->requests->lines($request->id);

        $this->assertSame(10, (int) $lines[0]->line_no);
        $this->assertSame(20, (int) $lines[1]->line_no);
        $this->assertSame('шт', (string) $lines[1]->uom_code, 'единица измерения по умолчанию');
    }

    public function test_request_without_lines_is_rejected()
    {
        $this->expectException(\Exception_Erp403_Procurement::class);

        $this->make_request();
    }

    public function test_request_state_machine()
    {
        $request = $this->make_request(array(
            'priority' => 1,
            'lines'    => array(array('name' => 'ТЕСТ-403 срочное', 'qty' => 5)),
        ));

        $this->assertTrue($request->is_editable());

        $request = $this->requests->submit($request->id);

        $this->assertSame('submitted', (string) $request->status);
        $this->assertSame('Срочно', $request->priority_label());

        $request = $this->requests->approve($request->id);

        $this->assertSame('approved', (string) $request->status);
        $this->assertNotNull($request->approved_at);
        $this->assertFalse($request->is_editable(), 'согласованную заявку правят только через отмену');

        // Повторное согласование — уже недопустимый переход.
        $this->assertSame('invalid_transition', $this->reason(function () use ($request) {
            $this->requests->approve($request->id);
        }));
    }

    public function test_empty_request_cannot_be_submitted()
    {
        $request = $this->make_request(array(
            'lines' => array(array('name' => 'ТЕСТ-403 пустая', 'qty' => 1)),
        ));

        foreach ($this->requests->lines($request->id) as $line)
        {
            $this->requests->remove_line($line->id);
        }

        $this->assertSame('empty_document', $this->reason(function () use ($request) {
            $this->requests->submit($request->id);
        }));
    }

    // =========================================================================
    //  Заказы поставщику
    // =========================================================================

    public function test_approved_request_becomes_order()
    {
        $supplier_id = $this->supplier_id();

        $request = $this->make_request(array(
            'lines' => array(array(
                'name'        => 'ТЕСТ-403 заказываемое',
                'qty'         => 10,
                'price'       => 100,
                'supplier_id' => $supplier_id,
            )),
        ));

        $this->requests->submit($request->id);
        $this->requests->approve($request->id);

        $result = $this->orders->from_requests(array($request->id));

        $this->assertCount(1, $result['orders'], 'по одной заявке — один заказ');
        $this->assertSame(array(), $result['warnings']);

        $order = $result['orders'][0];

        $this->assertMatchesRegularExpression('/^ЗП-\d{4}-\d{6}$/', (string) $order->number);
        $this->assertSame($supplier_id, (int) $order->supplier_id);
        $this->assertSame(1000.0, (float) $order->amount, 'сумма заказа = количество × цена');
        $this->assertSame('draft', (string) $order->status);

        $request = $this->requests->get($request->id);

        $this->assertSame('ordered', (string) $request->status);
        $this->assertSame((int) $order->id, (int) $request->order_id);
        $this->assertSame('ordered', (string) $this->requests->lines($request->id)[0]->status);
    }

    public function test_order_cannot_be_sent_twice_and_records_prices()
    {
        $order = $this->make_order();

        $order = $this->orders->send($order->id);

        $this->assertSame('sent', (string) $order->status);
        $this->assertNotNull($order->sent_at);

        $price = \DB::select('price')->from('erp_403_prices')
            ->where('order_id', '=', (int) $order->id)
            ->execute()
            ->current();

        $this->assertNotSame(FALSE, $price, 'отправка заказа пишет цену в историю цен');
        $this->assertSame(250.0, (float) $price['price']);

        $this->assertSame('invalid_transition', $this->reason(function () use ($order) {
            $this->orders->send($order->id);
        }));

        $order = $this->orders->confirm($order->id);

        $this->assertSame('confirmed', (string) $order->status);
    }

    // =========================================================================
    //  Приёмка и склад
    // =========================================================================

    public function test_posting_receipt_puts_goods_on_stock()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id() OR ! $this->supplier_id())
        {
            $this->markTestSkipped('Для проверки прихода нужны склад erp401, номенклатура erp227 и поставщик erp301');
        }

        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        // Сквозной путь — обещание модуля: заявка → заказ → приёмка → склад.
        $request = $this->make_request(array(
            'lines' => array(array(
                'item_id'     => $item_id,
                'name'        => 'ТЕСТ-403 приход',
                'qty'         => 4,
                'price'       => 250,
                'uom_code'    => 'шт',
                'supplier_id' => $this->supplier_id(),
            )),
        ));

        $this->requests->submit($request->id);
        $this->requests->approve($request->id);

        $collected = $this->orders->from_requests(array($request->id), array('warehouse_id' => $warehouse_id));

        $this->assertCount(1, $collected['orders'], 'согласованная заявка собирается в заказ');

        $order = $collected['orders'][0];

        $this->orders->send($order->id);
        $this->orders->confirm($order->id);

        $before = \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id);

        $receipt = $this->receipts->create(array(
            'order_id'    => $order->id,
            'doc_number'  => 'ТЕСТ-403 накладная',
            'received_at' => date('Y-m-d'),
        ));

        $this->assertSame($warehouse_id, (int) $receipt->warehouse_id,
            'приёмка принимает на склад заказа, а не на первый по списку');
        $this->assertCount(1, $this->receipts->lines($receipt->id));
        $this->assertSame(4.0, (float) $this->receipts->lines($receipt->id)[0]->qty,
            'в приёмку попадает то, что заказ ещё не принял');

        $result = $this->receipts->post($receipt->id);

        $this->assertCount(1, $result['posted']);
        $this->assertSame(array(), $result['skipped']);
        $this->assertSame('posted', (string) $result['receipt']->status);
        $this->assertSame($before + 4, \Service_Erp401_Stock::instance()->qty($warehouse_id, $item_id),
            'проведение приёмки оформляет приход на склад');

        $order_line = $this->orders->lines($order->id)[0];

        $this->assertSame(4.0, (float) $order_line->qty_received);
        $this->assertSame('received', (string) $order_line->status);

        $order = $this->orders->get($order->id);

        $this->assertSame('received', (string) $order->status, 'заказ сам закрывается, когда принято всё');

        $request_lines = $this->requests->lines($request->id);

        $this->assertSame('received', (string) $request_lines[0]->status,
            'строка заявки, из которой вырос заказ, отмечается исполненной');
    }

    public function test_posting_receipt_is_blocked_by_quality_control()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для проверки контроля нужны склад erp401 и номенклатура erp227');
        }

        $warehouse_id = $this->warehouse();

        $order = $this->make_order(array(
            'warehouse_id' => $warehouse_id,
            'lines'        => array(array(
                'item_id' => $this->item_id(),
                'name'    => 'ТЕСТ-403 брак',
                'qty'     => 2,
                'price'   => 100,
            )),
        ));

        $receipt = $this->receipts->create(array('order_id' => $order->id));
        $line    = $this->receipts->lines($receipt->id)[0];

        $line = $this->receipts->set_quality($line->id, 'failed', 'ТЕСТ-403: несоответствие');

        $this->assertTrue($line->is_blocked());

        $error = NULL;

        try
        {
            $this->receipts->post($receipt->id);
        }
        catch (\Exception_Erp403_Procurement $e)
        {
            $error = $e;
        }

        $this->assertNotNull($error, 'позиция с проваленным контролем блокирует проведение');
        $this->assertSame('quality_blocked', $error->reason());
        $this->assertSame('draft', (string) $this->receipts->get($receipt->id)->status,
            'неудачное проведение не меняет состояние приёмки');
    }

    public function test_posted_receipt_cannot_be_cancelled()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для проверки нужен склад erp401');
        }

        $warehouse_id = $this->warehouse();

        $order = $this->make_order(array(
            'warehouse_id' => $warehouse_id,
            'lines'        => array(array(
                'item_id' => $this->item_id(),
                'name'    => 'ТЕСТ-403 отмена',
                'qty'     => 1,
                'price'   => 50,
            )),
        ));

        $receipt = $this->receipts->create(array('order_id' => $order->id));
        $this->receipts->post($receipt->id);

        $this->assertSame('already_posted', $this->reason(function () use ($receipt) {
            $this->receipts->cancel($receipt->id);
        }), 'обратное движение оформляет склад, а не отмена документа');
    }

    public function test_receipt_line_without_item_is_skipped_with_warning()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            $this->markTestSkipped('Для проверки нужен склад erp401');
        }

        $order = $this->make_order(array(
            'warehouse_id' => $this->warehouse(),
            'lines'        => array(array(
                'name'  => 'ТЕСТ-403 без номенклатуры',
                'qty'   => 3,
                'price' => 10,
            )),
        ));

        $receipt = $this->receipts->create(array('order_id' => $order->id));
        $result  = $this->receipts->post($receipt->id);

        $this->assertSame(array(), $result['posted']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('posted', (string) $result['receipt']->status);
    }

    // =========================================================================
    //  Поставщики и цены
    // =========================================================================

    public function test_supplier_choice_prefers_cheaper_and_faster()
    {
        if ( ! $this->supplier_id())
        {
            $this->markTestSkipped('Нет контрагента с ролью «поставщик»');
        }

        $item_id = $this->item_id();
        $suppliers = $this->suppliers->counterparties(array(), 2, 0);

        foreach ($suppliers as $index => $counterparty)
        {
            $this->suppliers->record_price(array(
                'supplier_id' => (int) $counterparty->id,
                'item_id'     => $item_id,
                'name'        => 'ТЕСТ-403 цена '.$index,
                'price'       => $index === 0 ? 100 : 130,
                'qty'         => 1,
                'dated_at'    => date('Y-m-d'),
            ));
        }

        if (count($suppliers) < 2 OR ! $item_id)
        {
            $this->markTestSkipped('Для сравнения нужны два поставщика и номенклатура');
        }

        $choice = $this->suppliers->choose($item_id, 10);

        $this->assertNotNull($choice);
        $this->assertSame((int) $suppliers[0]->id, (int) $choice['supplier_id'], 'дешевле — значит лучше при равных условиях');
        $this->assertSame(100.0, (float) $choice['price']);
        $this->assertGreaterThan(0, (float) $choice['score']);
        $this->assertNotEmpty($choice['reasons']);
    }

    public function test_price_history_is_written_and_read()
    {
        $supplier_id = $this->supplier_id();

        if ( ! $supplier_id)
        {
            $this->markTestSkipped('Нет контрагента с ролью «поставщик»');
        }

        $this->suppliers->record_price(array(
            'supplier_id' => $supplier_id,
            'name'        => 'ТЕСТ-403 история',
            'price'       => 77.5,
            'qty'         => 10,
            'source'      => 'manual',
        ));

        $rows = \DB::select()->from('erp_403_prices')
            ->where('name', '=', 'ТЕСТ-403 история')
            ->execute()
            ->as_array();

        $this->assertCount(1, $rows);
        $this->assertSame(77.5, (float) $rows[0]['price']);

        $profile = $this->suppliers->profile($supplier_id);

        $before = array(
            'lead_time_days' => (int) $profile->lead_time_days,
            'rating'         => (int) $profile->rating,
            'is_preferred'   => (int) $profile->is_preferred,
        );

        $profile = $this->suppliers->save_profile($supplier_id, array(
            'lead_time_days' => $before['lead_time_days'] + 3,
            'rating'         => 5,
            'is_preferred'   => 't',
        ));

        $this->assertSame($before['lead_time_days'] + 3, (int) $profile->lead_time_days);
        $this->assertSame(5, (int) $profile->rating);
        $this->assertTrue($profile->is_preferred(), 'флаг «предпочтительный» — число, а не строка t/f');

        // Возвращаем условия как были: демонстрационные данные не портим.
        $this->suppliers->save_profile($supplier_id, array(
            'lead_time_days' => $before['lead_time_days'],
            'rating'         => $before['rating'],
            'is_preferred'   => $before['is_preferred'],
        ));
    }

    public function test_auto_requests_from_low_stock_do_not_double()
    {
        if ( ! class_exists('Service_Erp401_Stock') OR ! $this->item_id())
        {
            $this->markTestSkipped('Для авто-заявок нужны склад erp401 и номенклатура erp227');
        }

        $warehouse_id = $this->warehouse();
        $item_id      = $this->item_id();

        // Остаток ровно 5, минимум 20 — позиция «на минимуме». Остаток правим
        // через adjust, а не receive: иначе число зависит от прошлых прогонов.
        try
        {
            \Service_Erp401_Stock::instance()->adjust($warehouse_id, $item_id, 5, array('note' => 'ТЕСТ-403 остаток'));
        }
        catch (\Exception $e)
        {
            // Баланс уже равен 5 — это и есть нужное состояние.
        }

        $this->set_min_qty($warehouse_id, $item_id, 20);

        $first = $this->requests->auto_from_low_stock(array('warehouse_id' => $warehouse_id), NULL);

        $this->assertCount(1, $first['created'], 'позиция на минимуме даёт одну автоматическую заявку');

        $request = $first['created'][0];
        $line    = $this->requests->lines($request->id)[0];

        $this->assertSame('submitted', (string) $request->status, 'авто-заявка сразу идёт на согласование');
        $this->assertSame('auto', (string) $request->source);
        $this->assertSame(18.0, (float) $line->qty, 'минимум 20 − остаток 5 = 15, с запасом 20 % → 18');

        $second = $this->requests->auto_from_low_stock(array('warehouse_id' => $warehouse_id), NULL);

        $this->assertCount(0, $second['created'], 'повторный запуск не задваивает заявку');
        $this->assertGreaterThan(0, count($second['skipped']));
    }

    // =========================================================================
    //  Страницы, API и MCP
    // =========================================================================

    public function test_pages_render_on_real_data()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $request = $this->make_request(array(
            'lines' => array(array('name' => 'ТЕСТ-403 страница', 'qty' => 3, 'price' => 15)),
        ));

        $order = $this->make_order();
        $this->orders->send($order->id);

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        $result = $smoke->run(array(
            array('url' => '/procurement',                          'name' => 'Доска закупок'),
            array('url' => '/procurement/requests',                 'name' => 'Заявки'),
            array('url' => '/procurement/requests/view/'.(int) $request->id, 'name' => 'Карточка заявки'),
            array('url' => '/procurement/orders',                   'name' => 'Заказы'),
            array('url' => '/procurement/orders/view/'.(int) $order->id, 'name' => 'Карточка заказа'),
            array('url' => '/procurement/receipts',                 'name' => 'Приёмки'),
            array('url' => '/procurement/suppliers',                'name' => 'Поставщики'),
            array('url' => '/procurement/low-stock',                'name' => 'Дефицит склада'),
            array('url' => '/procurement/api/stats',                'name' => 'API: сводка'),
            array('url' => '/procurement/api/requests',             'name' => 'API: заявки'),
            array('url' => '/procurement/api/orders',               'name' => 'API: заказы'),
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

        // Выходим из сессии: предыдущие проверки могли войти администратором.
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
            array('url' => '/procurement/api/stats', 'name' => 'API без входа'),
        ));

        $row = reset($result['rows']);

        $this->assertSame(403, (int) $row['status'], 'JSON API закупок не отдаёт данные без прав');
    }

    public function test_mcp_exposes_procurement_tools()
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

        foreach (array('procurement.stats', 'procurement.requests.search', 'procurement.order.create', 'procurement.receipt.post', 'procurement.suppliers.compare') as $tool)
        {
            $this->assertContains($tool, $names, 'инструмент '.$tool.' объявлен в mcp.php');
        }
    }

    // =========================================================================
    //  Помощники
    // =========================================================================

    /**
     * Есть ли таблицы модуля.
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
            \DB::select('id')->from('erp_403_requests')->limit(1)->execute();

            $available = TRUE;
        }
        catch (\Exception $e)
        {
            $available = FALSE;
        }

        return $available;
    }

    /**
     * Заявка теста.
     *
     * @param   array   $data
     * @return  \Model_Erp403_Request
     */
    protected function make_request(array $data = array())
    {
        $request = $this->requests->create(array_merge(array(
            'number'    => $this->number('ЗК-ТЕСТ-403-'),
            'note'      => 'ТЕСТ-403: автоматический тест',
            'need_date' => date('Y-m-d', strtotime('+10 days')),
        ), $data));

        return $request;
    }

    /**
     * Заказ теста (вручную, без заявки).
     *
     * @param   array   $data
     * @return  \Model_Erp403_Order
     */
    protected function make_order(array $data = array())
    {
        $supplier_id = $this->supplier_id();

        if ( ! $supplier_id)
        {
            $this->markTestSkipped('Нет контрагента с ролью «поставщик»');
        }

        return $this->orders->create(array_merge(array(
            'number'        => $this->number('ЗП-ТЕСТ-403-'),
            'supplier_id'   => $supplier_id,
            'warehouse_id'  => $this->warehouse(),
            'delivery_date' => date('Y-m-d', strtotime('+7 days')),
            'lines'         => array(array(
                'name'  => 'ТЕСТ-403 позиция заказа',
                'qty'   => 2,
                'price' => 250,
            )),
        ), $data));
    }

    /**
     * Склад теста: создаётся один на прогон и удаляется в tearDown.
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

        // Склад мог остаться от упавшего прогона: коды складов уникальны, поэтому
        // такой склад переиспользуем, а не падаем на валидации уникальности.
        $existing = \DB::select('id')->from('erp_401_warehouses')
            ->where('code', '=', 'ТЕСТ-403')
            ->limit(1)
            ->execute()
            ->current();

        if ($existing)
        {
            $this->warehouse_id = (int) $existing['id'];

            return $this->warehouse_id;
        }

        $warehouse = \Service_Erp401_Stock::instance()->create_warehouse(array(
            'code' => 'ТЕСТ-403',
            'name' => 'Склад теста erp403',
            'type' => 'main', // тип обязателен в правилах склада (erp401)
        ));

        $this->warehouse_id = (int) $warehouse->id;

        return $this->warehouse_id;
    }

    /**
     * Идентификаторы документов теста по маске номера.
     *
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

    /**
     * Первый контрагент с ролью «поставщик».
     *
     * @return  int|null
     */
    protected function supplier_id()
    {
        $counterparties = $this->suppliers->counterparties(array(), 1, 0);

        if ( ! $counterparties)
        {
            return NULL;
        }

        $counterparty = reset($counterparties);

        return (int) $counterparty->id;
    }

    /**
     * Поставщик, помеченный предпочтительным (для возврата условий).
     *
     * @return  int|null
     */
    protected function first_supplier_id()
    {
        $row = \DB::select('supplier_id')->from('erp_403_suppliers')
            ->where('is_preferred', '=', 1)
            ->order_by('supplier_id', 'ASC')
            ->limit(1)
            ->execute()
            ->current();

        return $row ? (int) $row['supplier_id'] : NULL;
    }

    /**
     * Свободная номенклатура из реестра (для прихода на склад).
     *
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
     * Выставить минимум остатка (для проверки авто-заявок).
     *
     * @param   int     $warehouse_id
     * @param   int     $item_id
     * @param   float   $min_qty
     * @return  void
     */
    protected function set_min_qty($warehouse_id, $item_id, $min_qty)
    {
        \DB::update('erp_401_balances')
            ->set(array('min_qty' => $min_qty))
            ->where('warehouse_id', '=', (int) $warehouse_id)
            ->where('item_id', '=', (int) $item_id)
            ->execute();
    }

    /**
     * Уникальный номер документа теста.
     *
     * @param   string  $prefix
     * @return  string
     */
    protected function number($prefix)
    {
        return $prefix.substr(md5(uniqid('', TRUE)), 0, 8);
    }

    /**
     * Код причины исключения (или NULL, если исключения не было).
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
        catch (\Exception_Erp403_Procurement $e)
        {
            return $e->reason();
        }
        catch (\Exception $e)
        {
            return get_class($e);
        }

        return NULL;
    }
}
