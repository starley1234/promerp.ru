<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API продаж (erp410_shipment).
 *
 * Точка входа для машин (интеграции, внешние воркеры, агенты): заказы клиентов,
 * отгрузки, счета, клиенты и прайс. Ответ об ошибке обязан останавливать
 * выполнение: Konine не прерывает запрос после `before()` и не выходит из
 * действия после отправки ответа, поэтому после `_json_fail()` стоит `return;`,
 * а в `before()` — `_json_halt()` (следит `dev:audit --check=json_guards`).
 *
 * Действия, которые пишут данные, вызывают сервисы модуля: журнал, события,
 * правила состояний, резерв и списание со склада остаются в одном месте — в
 * сервисах.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Api extends Controller_Erp410_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;

        if ( ! $this->request->is_initial())
        {
            return;
        }

        $token = (string) $this->request->query('token');

        if ($token !== '' AND defined('MIGRATIONS_TOKEN') AND $token === MIGRATIONS_TOKEN)
        {
            return;
        }

        if ( ! $this->can('erp410.view'))
        {
            $this->_json_halt('Доступ запрещен', 'forbidden', 403);
        }
    }

    /**
     * Сводка продаж.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'orders'    => $this->_orders()->stats(),
            'shipments' => $this->_shipments()->stats(),
            'invoices'  => $this->_invoices()->stats(),
            'customers' => $this->_customers()->stats(),
            'prices'    => $this->_prices()->stats(),
        ));
    }

    // =========================================================================
    //  Заказы клиента
    // =========================================================================

    /**
     * Список заказов.
     */
    public function action_orders()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'customer_id' => $this->_query('customer_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'open'        => $this->_query('open'),
        );

        $rows = array();

        foreach ($this->_orders()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $order)
        {
            $rows[] = $this->_order_row($order);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_orders()->count($filters)));
    }

    /**
     * Заказ целиком: позиции, отгрузки и счета.
     */
    public function action_order()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order = $this->_orders()->get($this->_query('id', $this->request->param('id')));

        if ( ! $order)
        {
            $this->_json_fail('Заказ клиента не найден', 'not_found', 404);
            return;
        }

        $shipments = array();

        foreach ($this->_shipments()->for_order($order->id) as $shipment)
        {
            $shipments[] = $this->_shipments()->payload($shipment);
        }

        $invoices = array();

        foreach ($this->_invoices()->for_order($order->id) as $invoice)
        {
            $invoices[] = $this->_invoices()->payload($invoice);
        }

        $this->_json_ok(array(
            'order'     => $this->_order_row($order),
            'lines'     => $this->_orders()->line_rows($order->id),
            'shipments' => $shipments,
            'invoices'  => $invoices,
            'credit'    => $this->_customers()->credit_check($order->customer_id, (float) $order->amount),
        ));
    }

    /**
     * Создать заказ (можно списком позиций).
     */
    public function action_order_create()
    {
        if ( ! $this->can('erp410.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array(
            'customer_id', 'contact_id', 'contract_id', 'warehouse_id', 'status', 'source', 'source_ref',
            'currency', 'order_date', 'ship_date', 'delivery_address', 'payment_terms', 'note',
            'item_id', 'name', 'qty', 'uom_code', 'price', 'discount', 'lines',
        ));

        try
        {
            $order = $this->_orders()->create($this->_lines_from_request($data), $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('order' => $this->_order_row($order), 'lines' => $this->_orders()->line_rows($order->id)), 201);
    }

    /**
     * Изменить шапку заказа.
     */
    public function action_order_update()
    {
        if ( ! $this->can('erp410.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $id = $this->_query('id', $this->request->param('id'));

        try
        {
            $order = $this->_orders()->update($id, $this->_posted(array(
                'contact_id', 'contract_id', 'warehouse_id', 'order_date', 'ship_date',
                'delivery_address', 'payment_terms', 'note',
            )), $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('order' => $this->_order_row($order)));
    }

    /**
     * Добавить позицию заказа.
     */
    public function action_order_line_add()
    {
        if ( ! $this->can('erp410.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order_id = (int) $this->_query('order_id', $this->request->param('id'));

        try
        {
            $line = $this->_orders()->add_line($order_id, $this->_posted(array(
                'item_id', 'name', 'code', 'qty', 'uom_code', 'price', 'discount', 'note',
            )));
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('line_id' => (int) $line->id, 'amount' => $line->amount()), 201);
    }

    /**
     * Переход состояния заказа: confirm | reserve | release | cancel | close.
     */
    public function action_order_act()
    {
        $act = (string) $this->_query('act');

        $ability = in_array($act, array('confirm', 'reserve', 'release'), TRUE) ? 'erp410.manage' : 'erp410.manage';

        if ( ! $this->can($ability))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $id   = $this->_query('id', $this->request->param('id'));
        $post = $this->_posted(array('reason', 'force'));
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'confirm')
            {
                $order = $this->_orders()->confirm($id, $user_id, ! empty($post['force']));
                $data  = array('order' => $this->_order_row($order));
            }
            elseif ($act === 'reserve')
            {
                $result = $this->_orders()->reserve($id, $user_id);
                $data   = $result;
            }
            elseif ($act === 'release')
            {
                $data = array('released' => $this->_orders()->release($id, $user_id));
            }
            elseif ($act === 'cancel')
            {
                $order = $this->_orders()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $data  = array('order' => $this->_order_row($order));
            }
            elseif ($act === 'close')
            {
                $order = $this->_orders()->close($id, $user_id);
                $data  = array('order' => $this->_order_row($order));
            }
            else
            {
                $this->_json_fail('Неизвестное действие «'.$act.'»', 'invalid_transition', 400);
                return;
            }
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($data);
    }

    // =========================================================================
    //  Отгрузки
    // =========================================================================

    /**
     * Список отгрузок.
     */
    public function action_shipments()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'customer_id' => $this->_query('customer_id'),
            'order_id'    => $this->_query('order_id'),
            'search'      => $this->_query('search'),
        );

        $rows = array();

        foreach ($this->_shipments()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $shipment)
        {
            $rows[] = $this->_shipment_row($shipment);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_shipments()->count($filters)));
    }

    /**
     * Отгрузка целиком: строки и счёт.
     */
    public function action_shipment()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $shipment = $this->_shipments()->get($this->_query('id', $this->request->param('id')));

        if ( ! $shipment)
        {
            $this->_json_fail('Отгрузка не найдена', 'not_found', 404);
            return;
        }

        $invoice = $this->_invoices()->for_shipment($shipment->id);

        $this->_json_ok(array(
            'shipment' => $this->_shipment_row($shipment),
            'lines'    => $this->_shipments()->line_rows($shipment->id),
            'invoice'  => $invoice ? $this->_invoices()->payload($invoice) : NULL,
        ));
    }

    /**
     * Собрать отгрузку из заказа.
     */
    public function action_shipment_build()
    {
        if ( ! $this->can('erp410.ship'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order_id = (int) $this->_query('order_id', $this->request->param('id'));

        try
        {
            $shipment = $this->_shipments()->build($order_id, $this->_posted(array(
                'carrier', 'transport_number', 'shipment_date', 'delivery_address', 'comment', 'ready',
            )), $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'shipment' => $this->_shipment_row($shipment),
            'lines'    => $this->_shipments()->line_rows($shipment->id),
        ), 201);
    }

    /**
     * Создать отгрузку вручную.
     */
    public function action_shipment_create()
    {
        if ( ! $this->can('erp410.ship'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array(
            'order_id', 'customer_id', 'warehouse_id', 'shipment_date', 'carrier', 'transport_number',
            'delivery_address', 'comment', 'item_id', 'name', 'qty', 'uom_code', 'price', 'lines',
        ));

        $lines = array();

        if ( ! empty($data['item_id']))
        {
            $lines[] = array(
                'item_id'  => (int) $data['item_id'],
                'name'     => isset($data['name']) ? $data['name'] : NULL,
                'qty'      => isset($data['qty']) ? $data['qty'] : 1,
                'uom_code' => isset($data['uom_code']) ? $data['uom_code'] : NULL,
                'price'    => isset($data['price']) ? $data['price'] : NULL,
            );
        }
        elseif ( ! empty($data['lines']) AND is_array($data['lines']))
        {
            $lines = $data['lines'];
        }

        $data['lines'] = $lines;

        try
        {
            $shipment = $this->_shipments()->create($data, $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('shipment' => $this->_shipment_row($shipment)), 201);
    }

    /**
     * Переход отгрузки: ready | post | cancel.
     */
    public function action_shipment_act()
    {
        if ( ! $this->can('erp410.ship'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act = (string) $this->_query('act');
        $id  = $this->_query('id', $this->request->param('id'));
        $post = $this->_posted(array('reason'));
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'ready')
            {
                $shipment = $this->_shipments()->ready($id, $user_id);
            }
            elseif ($act === 'post')
            {
                $shipment = $this->_shipments()->post($id, $user_id);
            }
            elseif ($act === 'cancel')
            {
                $shipment = $this->_shipments()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
            }
            else
            {
                $this->_json_fail('Неизвестное действие «'.$act.'»', 'invalid_transition', 400);
                return;
            }
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('shipment' => $this->_shipment_row($shipment)));
    }

    // =========================================================================
    //  Счета
    // =========================================================================

    /**
     * Список счетов.
     */
    public function action_invoices()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'customer_id' => $this->_query('customer_id'),
            'order_id'    => $this->_query('order_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'unpaid'      => $this->_query('unpaid'),
        );

        $rows = array();

        foreach ($this->_invoices()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $invoice)
        {
            $rows[] = $this->_invoice_row($invoice);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_invoices()->count($filters)));
    }

    /**
     * Счёт целиком: строки и документы-основания.
     */
    public function action_invoice()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $invoice = $this->_invoices()->get($this->_query('id', $this->request->param('id')));

        if ( ! $invoice)
        {
            $this->_json_fail('Счёт не найден', 'not_found', 404);
            return;
        }

        $this->_json_ok(array(
            'invoice' => $this->_invoice_row($invoice),
            'lines'   => $this->_invoices()->line_rows($invoice->id),
        ));
    }

    /**
     * Выставить счёт: по отгрузке или по заказу.
     */
    public function action_invoice_create()
    {
        if ( ! $this->can('erp410.invoice'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $shipment_id = (int) $this->_query('shipment_id');
        $order_id    = (int) $this->_query('order_id');
        $user_id     = $this->_current_user_id();

        try
        {
            if ($shipment_id)
            {
                $invoice = $this->_invoices()->from_shipment($shipment_id, $user_id);
            }
            elseif ($order_id)
            {
                $invoice = $this->_invoices()->from_order($order_id, $user_id);
            }
            else
            {
                $this->_json_fail('Укажите order_id или shipment_id — счёт выставляется по документу', 'not_found', 400);
                return;
            }
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'invoice' => $this->_invoice_row($invoice),
            'lines'   => $this->_invoices()->line_rows($invoice->id),
        ), 201);
    }

    /**
     * Переход счёта: issue | pay | cancel.
     */
    public function action_invoice_act()
    {
        if ( ! $this->can('erp410.invoice'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act  = (string) $this->_query('act');
        $id   = $this->_query('id', $this->request->param('id'));
        $post = $this->_posted(array('amount', 'reason'));
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'issue')
            {
                $invoice = $this->_invoices()->issue($id, $user_id);
            }
            elseif ($act === 'pay')
            {
                $invoice = $this->_invoices()->register_payment($id, isset($post['amount']) ? $post['amount'] : 0, $user_id);
            }
            elseif ($act === 'cancel')
            {
                $invoice = $this->_invoices()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
            }
            else
            {
                $this->_json_fail('Неизвестное действие «'.$act.'»', 'invalid_transition', 400);
                return;
            }
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('invoice' => $this->_invoice_row($invoice)));
    }

    // =========================================================================
    //  Клиенты и прайс
    // =========================================================================

    /**
     * Список клиентов с условиями и задолженностью.
     */
    public function action_customers()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'search'       => $this->_query('search'),
            'active'       => $this->_query('active'),
            'with_profile' => $this->_query('with_profile'),
        );

        $rows = array();

        foreach ($this->_customers()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $row)
        {
            $rows[] = $this->_customer_row($row);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_customers()->count($filters)));
    }

    /**
     * Карточка клиента: условия, задолженность, заказы.
     */
    public function action_customer()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $customer_id = (int) $this->_query('id', $this->request->param('id'));
        $profile     = $this->_customers()->profile($customer_id, FALSE);

        if ( ! $profile AND $this->_customers()->name($customer_id) === 'Клиент #'.$customer_id)
        {
            $this->_json_fail('Клиент не найден', 'not_found', 404);
            return;
        }

        $orders = array();

        foreach ($this->_orders()->search(array('customer_id' => $customer_id), 10, 0) as $order)
        {
            $orders[] = $this->_order_row($order);
        }

        $this->_json_ok(array(
            'customer' => $this->_customer_row(array(
                'customer_id' => $customer_id,
                'name'        => $this->_customers()->name($customer_id),
                'profile'     => $profile,
                'exposure'    => $this->_customers()->credit_exposure($customer_id),
            )),
            'credit'   => $this->_customers()->credit_check($customer_id, 0),
            'orders'   => $orders,
        ));
    }

    /**
     * Сохранить условия клиента.
     */
    public function action_customer_save()
    {
        if ( ! $this->can('erp410.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $customer_id = (int) $this->_query('customer_id', $this->request->param('id'));

        try
        {
            $profile = $this->_customers()->save($customer_id, $this->_posted(array(
                'payment_terms', 'credit_days', 'credit_limit', 'discount', 'manager_id', 'delivery_address', 'terms', 'is_active',
            )), $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('profile_id' => (int) $profile->id));
    }

    /**
     * Цена позиции для клиента: сколько стоит и на каком основании.
     */
    public function action_price()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $item_id     = (int) $this->_query('item_id', $this->request->param('id'));
        $customer_id = (int) $this->_query('customer_id');

        $this->_json_ok(array(
            'item_id'     => $item_id,
            'customer_id' => $customer_id ?: NULL,
            'price'       => $this->_prices()->price_for($item_id, $customer_id ?: NULL),
        ));
    }

    /**
     * История цен по позиции.
     */
    public function action_prices()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $item_id = (int) $this->_query('item_id', $this->request->param('id'));

        if ( ! $item_id)
        {
            $this->_json_fail('Укажите item_id — история цен ведётся по позиции', 'not_found', 400);
            return;
        }

        $rows = array();

        foreach ($this->_prices()->history($item_id, (int) $this->_query('limit', 50)) as $entry)
        {
            $rows[] = $this->_price_row($entry);
        }

        $this->_json_ok(array('item_id' => $item_id, 'rows' => $rows));
    }

    /**
     * Записать цену (прайс или договорную).
     */
    public function action_price_record()
    {
        if ( ! $this->can('erp410.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $entry = $this->_prices()->record(array(
                'item_id'     => $this->_query('item_id'),
                'customer_id' => $this->_query('customer_id'),
                'price'       => $this->_query('price', 0),
                'discount'    => $this->_query('discount', 0),
                'valid_from'  => $this->_query('valid_from'),
                'source'      => 'manual',
            ), $this->_current_user_id());
        }
        catch (Exception_Erp410_Shipment $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('price_id' => (int) $entry->id, 'net_price' => $entry->net_price()), 201);
    }

    // =========================================================================
    //  Строки ответов
    // =========================================================================

    /**
     * Компактный вид заказа.
     *
     * @param   Model_Erp410_Order  $order
     * @return  array
     */
    protected function _order_row(Model_Erp410_Order $order)
    {
        return array(
            'id'            => (int) $order->id,
            'uuid'          => (string) $order->uuid,
            'number'        => (string) $order->number,
            'customer_id'   => $order->customer_id ? (int) $order->customer_id : NULL,
            'customer_name' => $order->customer_id ? $this->_customers()->name($order->customer_id) : NULL,
            'status'        => (string) $order->status,
            'status_label'  => $order->status_label(),
            'source'        => (string) $order->source,
            'currency'      => (string) $order->currency,
            'amount'        => (float) $order->amount,
            'warehouse_id'  => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
            'order_date'    => $order->order_date ? (string) $order->order_date : NULL,
            'ship_date'     => $order->ship_date ? (string) $order->ship_date : NULL,
            'days_left'     => $order->days_left(),
            'overdue'       => $order->is_overdue(),
            'editable'      => $order->is_editable(),
            'can_ship'      => $order->can_ship(),
            'note'          => $order->note ? (string) $order->note : NULL,
        );
    }

    /**
     * Компактный вид отгрузки.
     *
     * @param   Model_Erp410_Shipment   $shipment
     * @return  array
     */
    protected function _shipment_row(Model_Erp410_Shipment $shipment)
    {
        $order = $shipment->order_id ? $this->_orders()->get($shipment->order_id) : NULL;

        return $this->_shipments()->payload($shipment) + array(
            'order_number' => $order ? (string) $order->number : NULL,
            'carrier'      => $shipment->carrier ? (string) $shipment->carrier : NULL,
            'posted_at'    => $shipment->posted_at ? (string) $shipment->posted_at : NULL,
        );
    }

    /**
     * Компактный вид счёта.
     *
     * @param   Model_Erp410_Invoice    $invoice
     * @return  array
     */
    protected function _invoice_row(Model_Erp410_Invoice $invoice)
    {
        return $this->_invoices()->payload($invoice) + array(
            'overdue' => $invoice->is_overdue(),
        );
    }

    /**
     * Компактный вид клиента.
     *
     * @param   array   $row    customer_id, name, profile, exposure
     * @return  array
     */
    protected function _customer_row(array $row)
    {
        $profile = isset($row['profile']) ? $row['profile'] : NULL;

        return array(
            'customer_id'    => (int) $row['customer_id'],
            'name'           => (string) $row['name'],
            'credit_days'    => $profile ? $profile->credit_days() : 0,
            'credit_limit'   => $profile ? (float) $profile->credit_limit : 0.0,
            'discount'       => $profile ? $profile->discount() : 0.0,
            'payment_terms'  => $profile ? $profile->payment_label() : 'Предоплата',
            'active'         => $profile ? $profile->is_active() : TRUE,
            'exposure'       => isset($row['exposure']) ? (float) $row['exposure'] : 0.0,
            'has_profile'    => (bool) $profile,
        );
    }

    /**
     * Компактный вид записи прайса.
     *
     * @param   Model_Erp410_Price  $entry
     * @return  array
     */
    protected function _price_row(Model_Erp410_Price $entry)
    {
        return array(
            'id'           => (int) $entry->id,
            'item_id'      => (int) $entry->item_id,
            'customer_id'  => $entry->customer_id ? (int) $entry->customer_id : NULL,
            'price'        => (float) $entry->price,
            'discount'     => (float) $entry->discount,
            'net_price'    => $entry->net_price(),
            'currency'     => (string) $entry->currency,
            'source'       => (string) $entry->source,
            'source_label' => $entry->source_label(),
            'valid_from'   => $entry->valid_from ? (string) $entry->valid_from : NULL,
            'created_at'   => (string) $entry->created_at,
        );
    }

    /**
     * Данные запроса по списку полей: сначала POST, затем query.
     *
     * Возвращает именно массив (в отличие от `_posted()` в erp403, где одно поле
     * отдаётся скаляром): действия этого API читают результат по ключам, и
     * скаляр на одном поле молча превратился бы в строку с «Illegal string
     * offset». Формы шлют POST, агенты и curl — query, поэтому читаются оба.
     *
     * @param   array   $keys
     * @return  array
     */
    protected function _posted(array $keys)
    {
        $data = array();

        foreach ($keys as $key)
        {
            $value = $this->request->post($key);

            if ($value === NULL OR $value === '')
            {
                $value = $this->request->query($key);
            }

            if ($value !== NULL)
            {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Собрать заказ со строками из плоского запроса: item_id[] / qty[] / price[].
     *
     * @param   array   $data
     * @return  array
     */
    protected function _lines_from_request(array $data)
    {
        if ( ! empty($data['lines']) AND is_array($data['lines']))
        {
            $data['lines'] = array_values($data['lines']);

            return $data;
        }

        $lines = array();

        if ( ! empty($data['item_id']))
        {
            $items = (array) $data['item_id'];

            foreach ($items as $index => $item_id)
            {
                if ( ! $item_id)
                {
                    continue;
                }

                $lines[] = array(
                    'item_id'  => (int) $item_id,
                    'name'     => isset($data['name'][$index]) ? $data['name'][$index] : NULL,
                    'qty'      => isset($data['qty'][$index]) ? $data['qty'][$index] : 1,
                    'uom_code' => isset($data['uom_code'][$index]) ? $data['uom_code'][$index] : NULL,
                    'price'    => isset($data['price'][$index]) ? $data['price'][$index] : NULL,
                    'discount' => isset($data['discount'][$index]) ? $data['discount'][$index] : NULL,
                );
            }
        }

        $data['lines'] = $lines;

        return $data;
    }
}
