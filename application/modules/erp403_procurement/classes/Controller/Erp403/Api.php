<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API закупок (erp403_procurement).
 *
 * Точка входа для машин (интеграции, внешние воркеры, агенты): заявки, заказы
 * поставщикам, приёмка, поставщики и история цен. Ответ об ошибке обязан
 * останавливать выполнение: Konine не прерывает запрос после `before()` и не
 * выходит из действия после отправки ответа, поэтому после `_json_fail()` стоит
 * `return;`, а в `before()` — `_json_halt()` (следит `dev:audit
 * --check=json_guards`).
 *
 * Действия, которые пишут данные, вызывают сервисы модуля: журнал, события,
 * правила состояний и приход на склад остаются в одном месте — в сервисах.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Api extends Controller_Erp403_Main
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

        if ( ! $this->can('erp403.view'))
        {
            $this->_json_halt('Доступ запрещен', 'forbidden', 403);
        }
    }

    /**
     * Сводка закупок.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'requests'        => $this->_requests()->stats(),
            'orders'          => $this->_orders()->stats(),
            'receipts'        => $this->_receipts()->stats(),
            'suppliers'       => $this->_suppliers()->stats(),
            'approved_amount' => $this->_requests()->approved_amount(),
        ));
    }

    // =========================================================================
    //  Заявки
    // =========================================================================

    /**
     * Список заявок.
     */
    public function action_requests()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'  => $this->_query('status'),
            'source'  => $this->_query('source'),
            'search'  => $this->_query('search'),
            'overdue' => $this->_query('overdue'),
        );

        $rows = array();

        foreach ($this->_requests()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $request)
        {
            $rows[] = $this->_request_row($request);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_requests()->count($filters)));
    }

    /**
     * Заявка целиком: позиции и заказ.
     */
    public function action_request()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $request = $this->_requests()->get($this->_query('id', $this->request->param('id')));

        if ( ! $request)
        {
            $this->_json_fail('Заявка не найдена', 'not_found', 404);
            return;
        }

        $order = $request->order_id ? $this->_orders()->get($request->order_id) : NULL;

        $this->_json_ok(array(
            'request' => $this->_request_row($request),
            'lines'   => $this->_requests()->line_rows($request->id),
            'order'   => $order ? $this->_orders()->payload($order) : NULL,
        ));
    }

    /**
     * Создать заявку (можно списком позиций).
     */
    public function action_request_create()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('status', 'priority', 'warehouse_id', 'need_date', 'source', 'source_ref', 'note', 'item_id', 'name', 'qty', 'uom_code', 'price', 'lines'));

        try
        {
            $request = $this->_requests()->create($data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'request' => $this->_request_row($request),
            'lines'   => $this->_requests()->line_rows($request->id),
        ), 201);
    }

    /**
     * Изменить заявку.
     */
    public function action_request_update()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('id', 'need_date', 'warehouse_id', 'priority', 'note'));

        if (empty($data['id']))
        {
            $this->_json_fail('Не указана заявка (id)', 'validation', 400);
            return;
        }

        try
        {
            $request = $this->_requests()->update((int) $data['id'], $data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($this->_request_row($request));
    }

    /**
     * Переход состояния заявки: submit | approve | reject | cancel.
     */
    public function action_request_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act = (string) $this->_posted(array('act'));
        $id  = $this->_posted(array('id'));

        try
        {
            $requests = $this->_requests();

            switch ($act)
            {
                case 'submit':
                    $request = $requests->submit((int) $id, $this->_current_user_id());
                    break;

                case 'approve':
                    if ( ! $this->can('erp403.approve'))
                    {
                        $this->_json_fail('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $request = $requests->approve((int) $id, $this->_current_user_id());
                    break;

                case 'reject':
                    if ( ! $this->can('erp403.approve'))
                    {
                        $this->_json_fail('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $request = $requests->reject((int) $id, $this->_posted(array('reason')), $this->_current_user_id());
                    break;

                case 'cancel':
                    $request = $requests->cancel((int) $id, $this->_current_user_id());
                    break;

                default:
                    $this->_json_fail('Неизвестное действие: '.$act, 'invalid_transition', 400);
                    return;
            }
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($this->_request_row($request));
    }

    /**
     * Добавить позицию в заявку.
     */
    public function action_request_line_add()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('request_id', 'item_id', 'name', 'code', 'qty', 'uom_code', 'need_date', 'supplier_id', 'price', 'note'));

        if (empty($data['request_id']))
        {
            $this->_json_fail('Не указана заявка (request_id)', 'validation', 400);
            return;
        }

        try
        {
            $line = $this->_requests()->add_line((int) $data['request_id'], $data);
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'line'   => array(
                'id'       => (int) $line->id,
                'line_no'  => (int) $line->line_no,
                'name'     => (string) $line->name,
                'qty'      => (float) $line->qty,
                'uom_code' => (string) $line->uom_code,
                'price'    => (float) $line->price,
                'amount'   => $line->amount(),
                'status'   => (string) $line->status,
            ),
            'lines'  => $this->_requests()->line_rows((int) $data['request_id']),
        ), 201);
    }

    /**
     * Автоматические заявки по минимуму склада.
     */
    public function action_auto_requests()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('warehouse_id', 'need_days', 'priority', 'dry_run'));

        try
        {
            $result = $this->_requests()->auto_from_low_stock($data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $rows = array();

        foreach ($result['created'] as $request)
        {
            $rows[] = $this->_request_row($request);
        }

        $this->_json_ok(array(
            'created' => $rows,
            'skipped' => $result['skipped'],
            'count'   => count($rows),
        ), $rows ? 201 : 200);
    }

    // =========================================================================
    //  Заказы поставщику
    // =========================================================================

    /**
     * Список заказов.
     */
    public function action_orders()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'supplier_id' => $this->_query('supplier_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'open'        => $this->_query('open'),
        );

        $rows = array();

        foreach ($this->_orders()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $order)
        {
            $rows[] = $this->_orders()->payload($order);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_orders()->count($filters)));
    }

    /**
     * Заказ целиком: позиции, приёмки, ссылка на заявку.
     */
    public function action_order()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order = $this->_orders()->get($this->_query('id', $this->request->param('id')));

        if ( ! $order)
        {
            $this->_json_fail('Заказ не найден', 'not_found', 404);
            return;
        }

        $receipts = array();

        foreach ($this->_receipts()->search(array('order_id' => $order->id), 50, 0) as $receipt)
        {
            $receipts[] = $this->_receipts()->payload($receipt);
        }

        $this->_json_ok(array(
            'order'    => $this->_orders()->payload($order),
            'lines'    => $this->_orders()->line_rows($order->id),
            'progress' => $this->_receipts()->order_progress($order->id),
            'receipts' => $receipts,
        ));
    }

    /**
     * Создать заказ: из согласованной заявки или вручную.
     */
    public function action_order_create()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $request_id = $this->_posted(array('request_id'));

        try
        {
            if ( ! empty($request_id))
            {
                $result = $this->_orders()->from_requests(array((int) $request_id), array(
                    'delivery_date' => $this->_posted(array('delivery_date')),
                    'note'          => $this->_posted(array('note')),
                ), $this->_current_user_id());

                if ( ! $result['orders'])
                {
                    $reason = $result['skipped'] ? $result['skipped'][0]['reason'] : 'Заказ не создан';

                    $this->_json_fail($reason, 'invalid_transition', 400);
                    return;
                }

                $order = $result['orders'][0];
            }
            else
            {
                $data = $this->_posted(array('supplier_id', 'contract_id', 'warehouse_id', 'currency', 'delivery_date', 'payment_terms', 'note', 'lines'));

                $order = $this->_orders()->create($data, $this->_current_user_id());
            }
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($this->_orders()->payload($order), 201);
    }

    /**
     * Переход состояния заказа: send | confirm | cancel.
     */
    public function action_order_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act = (string) $this->_posted(array('act'));
        $id  = $this->_posted(array('id'));

        try
        {
            $orders = $this->_orders();

            switch ($act)
            {
                case 'send':
                    $order = $orders->send((int) $id, $this->_current_user_id());
                    break;

                case 'confirm':
                    $order = $orders->confirm((int) $id, $this->_current_user_id());
                    break;

                case 'cancel':
                    $order = $orders->cancel((int) $id, $this->_posted(array('reason')), $this->_current_user_id());
                    break;

                default:
                    $this->_json_fail('Неизвестное действие: '.$act, 'invalid_transition', 400);
                    return;
            }
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'order'  => $this->_orders()->payload($order),
            'lines'  => $this->_orders()->line_rows($order->id),
        ));
    }

    /**
     * Добавить позицию в заказ.
     */
    public function action_order_line_add()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('order_id', 'item_id', 'name', 'qty', 'price', 'uom_code', 'delivery_date', 'note'));

        if (empty($data['order_id']) OR empty($data['name']))
        {
            $this->_json_fail('Нужны order_id и name', 'validation', 400);
            return;
        }

        try
        {
            $line = $this->_orders()->add_line((int) $data['order_id'], $data);
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $order = $this->_orders()->get((int) $data['order_id']);

        $this->_json_ok(array(
            'line'   => array(
                'id'      => (int) $line->id,
                'line_no' => (int) $line->line_no,
                'name'    => (string) $line->name,
                'qty'     => (float) $line->qty,
                'price'   => (float) $line->price,
                'amount'  => $line->amount(),
            ),
            'order'  => $this->_orders()->payload($order),
            'amount' => (float) $order->amount,
        ), 201);
    }

    // =========================================================================
    //  Приёмки
    // =========================================================================

    /**
     * Список приёмок.
     */
    public function action_receipts()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'supplier_id' => $this->_query('supplier_id'),
            'order_id'    => $this->_query('order_id'),
            'search'      => $this->_query('search'),
        );

        $rows = array();

        foreach ($this->_receipts()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $receipt)
        {
            $rows[] = $this->_receipts()->payload($receipt);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_receipts()->count($filters)));
    }

    /**
     * Приёмка целиком: позиции и заказ.
     */
    public function action_receipt()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $receipt = $this->_receipts()->get($this->_query('id', $this->request->param('id')));

        if ( ! $receipt)
        {
            $this->_json_fail('Приёмка не найдена', 'not_found', 404);
            return;
        }

        $order = $receipt->order_id ? $this->_orders()->get($receipt->order_id) : NULL;

        $this->_json_ok(array(
            'receipt' => $this->_receipts()->payload($receipt),
            'lines'   => $this->_receipts()->line_rows($receipt->id),
            'order'   => $order ? $this->_orders()->payload($order) : NULL,
        ));
    }

    /**
     * Создать приёмку по заказу.
     */
    public function action_receipt_create()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('order_id', 'supplier_id', 'warehouse_id', 'doc_number', 'doc_date', 'received_at', 'note', 'lines'));

        try
        {
            $receipt = $this->_receipts()->create($data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'receipt' => $this->_receipts()->payload($receipt),
            'lines'   => $this->_receipts()->line_rows($receipt->id),
        ), 201);
    }

    /**
     * Переход состояния приёмки: post | cancel | quality.
     */
    public function action_receipt_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act  = (string) $this->_posted(array('act'));
        $id   = $this->_posted(array('id'));
        $data = $this->_posted(array('note', 'warehouse_id', 'reason', 'line_id', 'quality_status'));

        try
        {
            $receipts = $this->_receipts();

            switch ($act)
            {
                case 'post':
                    if ( ! empty($data['warehouse_id']))
                    {
                        $receipts->update((int) $id, array('warehouse_id' => (int) $data['warehouse_id']));
                    }

                    $result = $receipts->post((int) $id, $this->_current_user_id());

                    $this->_json_ok(array(
                        'receipt' => $receipts->payload($result['receipt']),
                        'posted'  => $result['posted'],
                        'skipped' => $result['skipped'],
                    ));
                    return;

                case 'cancel':
                    $receipt = $receipts->cancel((int) $id, isset($data['reason']) ? $data['reason'] : NULL, $this->_current_user_id());

                    $this->_json_ok($receipts->payload($receipt));
                    return;

                case 'quality':
                    if (empty($data['line_id']) OR empty($data['quality_status']))
                    {
                        $this->_json_fail('Нужны line_id и quality_status', 'validation', 400);
                        return;
                    }

                    $line = $receipts->set_quality((int) $data['line_id'], (string) $data['quality_status'], isset($data['note']) ? $data['note'] : NULL);

                    $this->_json_ok(array(
                        'line_id'        => (int) $line->id,
                        'quality_status' => (string) $line->quality_status,
                        'quality_label'  => $line->quality_label(),
                    ));
                    return;
            }
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_fail('Неизвестное действие: '.$act, 'invalid_transition', 400);
    }

    // =========================================================================
    //  Поставщики и цены
    // =========================================================================

    /**
     * Список поставщиков с условиями.
     */
    public function action_suppliers()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'search'    => $this->_query('search'),
            'preferred' => $this->_query('preferred'),
        );

        $rows = array();

        foreach ($this->_suppliers()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $row)
        {
            $profile = $row['profile'];

            $rows[] = array(
                'id'            => (int) $row['counterparty']->id,
                'name'          => (string) $row['counterparty']->name,
                'inn'           => $row['counterparty']->inn ? (string) $row['counterparty']->inn : NULL,
                'status'        => (string) $row['counterparty']->status,
                'lead_time'     => $profile ? $profile->lead_time() : 7,
                'currency'      => $profile ? (string) $profile->currency : 'RUB',
                'min_order'     => $profile ? (float) $profile->min_order : 0.0,
                'rating'        => $profile ? (int) $profile->rating : 3,
                'preferred'     => $profile ? $profile->is_preferred() : FALSE,
                'goods'         => (int) $row['goods'],
                'last_price_at' => $row['last_price_at'],
            );
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_suppliers()->count($filters)));
    }

    /**
     * Поставщик: условия, последние цены, исполнительность.
     */
    public function action_supplier()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $supplier_id  = (int) $this->_query('id', $this->request->param('id'));
        $counterparty = $this->_suppliers()->counterparty($supplier_id);

        if ( ! $counterparty)
        {
            $this->_json_fail('Поставщик не найден', 'not_found', 404);
            return;
        }

        $profile = $this->_suppliers()->profile($supplier_id, TRUE);

        $this->_json_ok(array(
            'supplier' => array(
                'id'     => (int) $counterparty->id,
                'name'   => (string) $counterparty->name,
                'inn'    => $counterparty->inn ? (string) $counterparty->inn : NULL,
                'status' => (string) $counterparty->status,
            ),
            'profile'  => array(
                'lead_time_days' => (int) $profile->lead_time_days,
                'lead_time'      => $profile->lead_time(),
                'currency'       => (string) $profile->currency,
                'min_order'      => (float) $profile->min_order,
                'payment_terms'  => $profile->payment_terms ? (string) $profile->payment_terms : NULL,
                'rating'         => (int) $profile->rating,
                'preferred'      => $profile->is_preferred(),
                'active'         => $profile->is_active(),
            ),
            'on_time'  => $this->_suppliers()->on_time_rate($supplier_id),
            'prices'   => $this->_suppliers()->supplier_prices($supplier_id, (int) $this->_query('limit', 20)),
        ));
    }

    /**
     * Сравнение поставщиков по позиции («у кого брать»).
     */
    public function action_supplier_compare()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $item_id = (int) $this->_query('item_id');
        $qty     = $this->_query('qty');

        if ( ! $item_id)
        {
            $this->_json_fail('Нужен item_id', 'validation', 400);
            return;
        }

        $rows   = $this->_suppliers()->compare($item_id, $qty);
        $choice = $rows ? $rows[0] : NULL;

        $this->_json_ok(array(
            'item_id'    => $item_id,
            'item_name'  => $this->_suppliers()->item_name($item_id),
            'best_price' => $this->_suppliers()->best_price($item_id),
            'choice'     => $choice,
            'rows'       => $rows,
            'history'    => $this->_suppliers()->history($item_id, (int) $this->_query('history_limit', 10)),
        ));
    }

    /**
     * Сохранить закупочные условия поставщика.
     */
    public function action_supplier_save()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('supplier_id', 'lead_time_days', 'currency', 'min_order', 'payment_terms', 'rating', 'is_preferred', 'is_active', 'note'));

        if (empty($data['supplier_id']))
        {
            $this->_json_fail('Нужен supplier_id', 'validation', 400);
            return;
        }

        try
        {
            $profile = $this->_suppliers()->save_profile((int) $data['supplier_id'], $data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'supplier_id'    => (int) $profile->supplier_id,
            'lead_time_days' => (int) $profile->lead_time_days,
            'lead_time'      => $profile->lead_time(),
            'rating'         => (int) $profile->rating,
            'preferred'      => $profile->is_preferred(),
        ));
    }

    /**
     * История цен по номенклатуре.
     */
    public function action_prices()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $item_id = (int) $this->_query('item_id');

        if ( ! $item_id)
        {
            $this->_json_fail('Нужен item_id', 'validation', 400);
            return;
        }

        $rows = $this->_suppliers()->history($item_id, (int) $this->_query('limit', 50));

        $this->_json_ok(array(
            'item_id'   => $item_id,
            'item_name' => $this->_suppliers()->item_name($item_id),
            'rows'      => $rows,
            'best'      => $this->_suppliers()->best_price($item_id),
            'stats'     => $this->_suppliers()->stats_by_item($item_id),
        ));
    }

    /**
     * Записать цену поставщика в историю.
     */
    public function action_price_record()
    {
        if ( ! $this->can('erp403.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $data = $this->_posted(array('supplier_id', 'item_id', 'name', 'price', 'qty', 'uom_code', 'currency', 'dated_at', 'note'));

        try
        {
            $price = $this->_suppliers()->record_price($data, $this->_current_user_id());
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'price_id'    => (int) $price->id,
            'supplier_id' => (int) $price->supplier_id,
            'item_id'     => $price->item_id ? (int) $price->item_id : NULL,
            'price'       => (float) $price->price,
            'currency'    => (string) $price->currency,
            'dated_at'    => (string) $price->dated_at,
            'source'      => (string) $price->source,
        ), 201);
    }

    /**
     * Дефицит склада: позиции, дошедшие до минимума.
     */
    public function action_low_stock()
    {
        if ( ! $this->can('erp403.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $warehouse_id = (int) $this->_query('warehouse_id');
        $rows         = array();

        try
        {
            foreach ($this->_requests()->low_stock_rows((int) $this->_query('limit', 50)) as $row)
            {
                if ($warehouse_id AND (int) $row['warehouse_id'] !== $warehouse_id)
                {
                    continue;
                }

                $rows[] = array(
                    'warehouse_id'   => (int) $row['warehouse_id'],
                    'warehouse_name' => $row['warehouse_name'],
                    'item_id'        => (int) $row['item_id'],
                    'name'           => (string) $row['name'],
                    'code'           => $row['code'],
                    'qty'            => (float) $row['qty'],
                    'min_qty'        => (float) $row['min_qty'],
                    'reserved'       => (float) $row['reserved'],
                    'available'      => (float) $row['available'],
                    'order_qty'      => (int) $row['suggested_qty'],
                    'request_exists' => (bool) $row['request_exists'],
                );
            }
        }
        catch (Exception_Erp403_Procurement $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'rows'    => $rows,
            'total'   => count($rows),
            'waiting' => count(array_filter($rows, function ($row) { return ! $row['request_exists']; })),
        ));
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Компактный вид заявки.
     *
     * @param   Model_Erp403_Request    $request
     * @return  array
     */
    protected function _request_row(Model_Erp403_Request $request)
    {
        return array(
            'id'            => (int) $request->id,
            'uuid'          => (string) $request->uuid,
            'number'        => (string) $request->number,
            'status'        => (string) $request->status,
            'status_label'  => $request->status_label(),
            'priority'      => (int) $request->priority,
            'priority_label' => $request->priority_label(),
            'source'        => (string) $request->source,
            'source_ref'    => $request->source_ref ? (string) $request->source_ref : NULL,
            'warehouse_id'  => $request->warehouse_id ? (int) $request->warehouse_id : NULL,
            'need_date'     => $request->need_date ? (string) $request->need_date : NULL,
            'order_id'      => $request->order_id ? (int) $request->order_id : NULL,
            'days_left'     => $request->days_left(),
            'overdue'       => $request->is_overdue(),
            'lines'         => count($this->_requests()->lines($request->id)),
            'amount'        => $this->request_amount($request->id),
        );
    }

    /**
     * Сумма позиций заявки (оценка).
     *
     * @param   int     $request_id
     * @return  float
     */
    protected function request_amount($request_id)
    {
        $amount = 0.0;

        foreach ($this->_requests()->lines($request_id) as $line)
        {
            $amount += $line->amount();
        }

        return round($amount, 2);
    }

    /**
     * Данные POST с запасным чтением из query (удобно для агентов и curl).
     *
     * @param   array   $keys
     * @return  mixed
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

        if (count($keys) === 1)
        {
            return isset($data[$keys[0]]) ? $data[$keys[0]] : NULL;
        }

        return $data;
    }
}
