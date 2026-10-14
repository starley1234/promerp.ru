<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказы поставщику (erp403_procurement).
 *
 * Заказ связывает заявку, поставщика и деньги:
 *
 *   draft ──send──► sent ──confirm──► confirmed ──приёмка──► partially_received
 *                                                              └──► received
 *   (draft/sent/confirmed/partially_received) ──cancel──► cancelled
 *
 * Два пути появления заказа:
 *
 *  - из согласованных заявок (`from_requests()`): позиции группируются по
 *    поставщику, заявки уходят в состояние «в заказе» — это обещание модуля
 *    «заявка превращается в заказ»;
 *  - вручную (`create()`), когда закупщик уже договорился и хочет зафиксировать
 *    заказ одной формой.
 *
 * Цена попадает в историю цен (`erp403_prices`) в момент отправки заказа —
 * дальше закупщик выбирает поставщика по данным, а не по памяти.
 *
 * @package    Erp403
 * @category   Services
 */
class Service_Erp403_Orders
{
    const TABLE    = 'erp_403_orders';
    const LINES    = 'erp_403_order_lines';
    const SEQUENCE = 'erp_403_order_number_seq';

    /** @var Service_Erp403_Orders|null */
    protected static $_instance;

    /**
     * @return  Service_Erp403_Orders
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Список заказов с фильтрами.
     *
     * @param   array   $filters    status, supplier_id, search, overdue, open
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp403_Order
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp403_Order');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('delivery_date', 'ASC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько заказов подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp403_Order');

        $this->apply_filters($query, $filters);

        return (int) $query->count_all();
    }

    /**
     * Фильтры выборки (общие для списка и счётчика).
     *
     * @param   ORM     $query
     * @param   array   $filters
     * @return  ORM
     */
    public function apply_filters($query, array $filters)
    {
        if ( ! empty($filters['status']))
        {
            $query->where('status', 'IN', (array) $filters['status']);
        }

        if ( ! empty($filters['supplier_id']))
        {
            $query->where('supplier_id', '=', (int) $filters['supplier_id']);
        }

        if ( ! empty($filters['open']))
        {
            $query->where('status', 'IN', array('draft', 'sent', 'confirmed', 'partially_received'));
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('delivery_date', '<', date('Y-m-d'));
            $query->where('status', 'IN', array('draft', 'sent', 'confirmed', 'partially_received'));
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim((string) $filters['search'])).'%');
        }

        return $query;
    }

    /**
     * Заказ по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp403_Order|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $order = ORM::factory('Erp403_Order')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $order->loaded() ? $order : NULL;
    }

    /**
     * Строки заказа по порядку.
     *
     * @param   int     $order_id
     * @return  array   Model_Erp403_OrderLine
     */
    public function lines($order_id)
    {
        return ORM::factory('Erp403_OrderLine')
            ->where('order_id', '=', (int) $order_id)
            ->order_by('line_no', 'ASC')
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Строка заказа по идентификатору.
     *
     * @param   int     $line_id
     * @return  Model_Erp403_OrderLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp403_OrderLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    /**
     * Следующий номер строки.
     *
     * @param   int     $order_id
     * @return  int
     */
    public function next_line_no($order_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(MAX(line_no), 0)'), 'line_no'))
            ->from(self::LINES)
            ->where('order_id', '=', (int) $order_id)
            ->execute()
            ->current();

        return (int) $row['line_no'] + 10;
    }

    /**
     * Открытые заказы (в работе): для приёмки и доски закупок.
     *
     * @param   int     $limit
     * @return  array   Model_Erp403_Order
     */
    public function open($limit = 50)
    {
        return $this->search(array('open' => TRUE), $limit, 0);
    }

    /**
     * Заказы со сроком поставки в ближайшие дни.
     *
     * @param   int     $days
     * @param   int     $limit
     * @return  array   Model_Erp403_Order
     */
    public function due_soon($days = 7, $limit = 10)
    {
        return ORM::factory('Erp403_Order')
            ->where('status', 'IN', array('draft', 'sent', 'confirmed', 'partially_received'))
            ->where('delivery_date', '<=', date('Y-m-d', strtotime('+'.(int) $days.' days')))
            ->order_by('delivery_date', 'ASC')
            ->limit($limit)
            ->find_all()
            ->as_array();
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Создать заказ вручную.
     *
     * @param   array       $data   supplier_id, contract_id, warehouse_id,
     *                              currency, delivery_date, payment_terms, note,
     *                              lines[]
     * @param   int|null    $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function create(array $data, $user_id = NULL)
    {
        if (empty($data['supplier_id']))
        {
            throw new Exception_Erp403_Procurement('Заказ поставщику: укажите поставщика', NULL, 'supplier_required');
        }

        $lines = ( ! empty($data['lines']) AND is_array($data['lines'])) ? $data['lines'] : array();

        if ( ! $lines)
        {
            throw new Exception_Erp403_Procurement('Заказ без позиций: укажите, что заказываем', NULL, 'empty_document');
        }

        $order = ORM::factory('Erp403_Order');

        $order->number       = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $order->supplier_id  = (int) $data['supplier_id'];
        $order->contract_id  = ! empty($data['contract_id']) ? (int) $data['contract_id'] : NULL;
        $order->request_id   = ! empty($data['request_id']) ? (int) $data['request_id'] : NULL;
        $order->warehouse_id = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : $this->_default_warehouse();
        $order->status       = isset($data['status']) ? (string) $data['status'] : 'draft';
        $order->currency     = ! empty($data['currency']) ? (string) $data['currency'] : 'RUB';
        $order->delivery_date = ! empty($data['delivery_date']) ? Erp::day($data['delivery_date']) : date('Y-m-d', strtotime('+'.$this->_lead_time($order->supplier_id).' days'));
        $order->payment_terms = isset($data['payment_terms']) ? (string) $data['payment_terms'] : NULL;
        $order->note         = isset($data['note']) ? (string) $data['note'] : NULL;
        $order->created_by   = $user_id ? (int) $user_id : NULL;
        $order->amount       = 0;
        $order->search_text  = '';
        $order->save();

        foreach ($lines as $line)
        {
            $this->add_line($order->id, $line);
        }

        $order->search_text = $this->search_text($order);
        $order->amount      = $this->amount($order->id);
        $order->save();

        $this->_journal($order, 'create', array(), $order->as_array(), 'Заказ создан: '.$order->number);

        Event::fire('erp403.order.created', $this->payload($order));

        return $order;
    }

    /**
     * Собрать заказы из согласованных заявок (по одному заказу на поставщика).
     *
     * Позиции заявок без явного поставщика получают рекомендованного
     * (`Service_Erp403_Suppliers::choose()`), а если цену никто не подсказал —
     * берётся последняя известная цена позиции.
     *
     * @param   array       $request_ids
     * @param   array       $options    delivery_date, currency, note
     * @param   int|null    $user_id
     * @return  array       orders, skipped, warnings
     * @throws  Exception_Erp403_Procurement
     */
    public function from_requests(array $request_ids, array $options = array(), $user_id = NULL)
    {
        $requests = Service_Erp403_Requests::instance();
        $suppliers = Service_Erp403_Suppliers::instance();

        $buckets  = array();
        $skipped  = array();
        $warnings = array();

        foreach ($request_ids as $request_id)
        {
            $request = $requests->get($request_id);

            if ( ! $request)
            {
                $skipped[] = array('request_id' => (int) $request_id, 'reason' => 'Заявка не найдена');
                continue;
            }

            if ( ! $request->can_order())
            {
                $skipped[] = array(
                    'request_id' => (int) $request->id,
                    'number'     => (string) $request->number,
                    'reason'     => 'Состояние «'.$request->status_label().'»: заказать можно только согласованную',
                );
                continue;
            }

            foreach ($requests->lines($request->id) as $line)
            {
                if ( ! $line->is_open())
                {
                    continue;
                }

                $supplier_id = $line->supplier_id ? (int) $line->supplier_id : NULL;
                $price       = (float) $line->price;

                if ( ! $supplier_id)
                {
                    $choice = $suppliers->choose($line->item_id, (float) $line->qty);

                    if ($choice)
                    {
                        $supplier_id = (int) $choice['supplier_id'];

                        if ($price <= 0)
                        {
                            $price = (float) $choice['price'];
                        }
                    }
                }

                if ( ! $supplier_id)
                {
                    $warnings[] = 'Позиция «'.$line->name.'»: поставщик не определён — выберите вручную';
                    continue;
                }

                if ($price <= 0)
                {
                    $price = $requests->last_price($line->item_id, $supplier_id);
                }

                $buckets[$supplier_id][] = array(
                    'item_id'         => $line->item_id ? (int) $line->item_id : NULL,
                    'name'            => (string) $line->name,
                    'code'            => $line->code,
                    'qty'             => (float) $line->qty,
                    'uom_code'        => (string) $line->uom_code,
                    'price'           => $price,
                    'delivery_date'   => $line->due_date(),
                    'request_line_id' => (int) $line->id,
                    'request_id'      => (int) $request->id,
                    'note'            => 'Заявка '.$request->number,
                );
            }
        }

        $orders = array();

        foreach ($buckets as $supplier_id => $lines)
        {
            $request_id = (int) $lines[0]['request_id'];

            $order = $this->create(array(
                'supplier_id'   => (int) $supplier_id,
                'request_id'    => $request_id,
                'warehouse_id'  => $options['warehouse_id'] ?? NULL,
                'currency'      => isset($options['currency']) ? $options['currency'] : 'RUB',
                'delivery_date' => isset($options['delivery_date']) ? $options['delivery_date'] : NULL,
                'note'          => isset($options['note']) ? $options['note'] : 'Собрано из заявок одной кнопкой.',
                'lines'         => $lines,
            ), $user_id);

            // Заявки, чьи позиции попали в заказ, закрываются как «в заказе».
            foreach (array_unique(array_column($lines, 'request_id')) as $included_request_id)
            {
                $request = $requests->get((int) $included_request_id);

                if ($request AND $request->can_order())
                {
                    $requests->mark_ordered($request, $order->id, $user_id);
                }
            }

            $orders[] = $order;
        }

        if ($orders)
        {
            Event::fire('erp403.orders.collected', array(
                'count'   => count($orders),
                'ids'     => array_map(function ($order) { return (int) $order->id; }, $orders),
                'suppliers' => array_keys($buckets),
            ));
        }

        return array('orders' => $orders, 'skipped' => $skipped, 'warnings' => $warnings);
    }

    /**
     * Добавить позицию в заказ.
     *
     * @param   int     $order_id
     * @param   array   $data
     * @return  Model_Erp403_OrderLine
     * @throws  Exception_Erp403_Procurement
     */
    public function add_line($order_id, array $data)
    {
        $order = $this->get($order_id);

        if ( ! $order)
        {
            throw new Exception_Erp403_Procurement('Заказ не найден', NULL, 'not_found');
        }

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number в состоянии «:status» — позиции добавить нельзя',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $line = ORM::factory('Erp403_OrderLine');

        $line->order_id        = (int) $order->id;
        $line->line_no         = isset($data['line_no']) ? (int) $data['line_no'] : $this->next_line_no($order->id);
        $line->item_id         = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name            = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code            = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty             = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->qty_received    = isset($data['qty_received']) ? Erp::num($data['qty_received']) : 0;
        $line->price           = isset($data['price']) ? Erp::num($data['price']) : 0;
        $line->uom_code        = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->delivery_date   = ! empty($data['delivery_date']) ? Erp::day($data['delivery_date']) : $order->delivery_date;
        $line->request_line_id = ! empty($data['request_line_id']) ? (int) $data['request_line_id'] : NULL;
        $line->status          = isset($data['status']) ? (string) $data['status'] : 'open';
        $line->note            = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = Service_Erp403_Requests::instance()->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp403_Procurement('Позиция заказа: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        if ($line->price <= 0 AND $line->item_id)
        {
            $line->price = Service_Erp403_Requests::instance()->last_price($line->item_id, $order->supplier_id);
        }

        $line->save();

        $this->_recalculate($order);

        return $line;
    }

    /**
     * Изменить позицию заказа.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp403_OrderLine
     * @throws  Exception_Erp403_Procurement
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp403_Procurement('Позиция заказа не найдена', NULL, 'not_found');
        }

        $order = $this->get($line->order_id);

        if ($order AND ! $order->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number в состоянии «:status» — правки запрещены',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        if ( ! empty($data['item_id']) AND empty($data['name']))
        {
            $line->item_id = (int) $data['item_id'];
            $line->name    = Service_Erp403_Requests::instance()->item_name($line->item_id);
        }

        foreach (array('name', 'code', 'uom_code', 'status', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = ($data[$field] === '' AND $field === 'note') ? NULL : (string) $data[$field];
            }
        }

        foreach (array('qty', 'price') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = Erp::num($data[$field]);
            }
        }

        if (array_key_exists('delivery_date', $data))
        {
            $line->delivery_date = ! empty($data['delivery_date']) ? Erp::day($data['delivery_date']) : NULL;
        }

        $line->save();

        $this->_recalculate($order);

        return $line;
    }

    /**
     * Удалить позицию заказа.
     *
     * @param   int     $line_id
     * @return  bool
     * @throws  Exception_Erp403_Procurement
     */
    public function remove_line($line_id)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp403_Procurement('Позиция заказа не найдена', NULL, 'not_found');
        }

        $order = $this->get($line->order_id);

        if ($order AND ! $order->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number в состоянии «:status» — позиции удалить нельзя',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $line->delete();

        $this->_recalculate($order);

        return TRUE;
    }

    /**
     * Изменить шапку заказа.
     *
     * @param   int|Model_Erp403_Order  $order
     * @param   array                   $data
     * @param   int|null                $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function update($order, array $data, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number в состоянии «:status» — правки запрещены',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $before = $order->as_array();

        if (array_key_exists('delivery_date', $data))
        {
            $order->delivery_date = ! empty($data['delivery_date']) ? Erp::day($data['delivery_date']) : NULL;
        }

        if (array_key_exists('warehouse_id', $data))
        {
            $order->warehouse_id = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : NULL;
        }

        foreach (array('payment_terms', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $order->$field = ($data[$field] === '') ? NULL : (string) $data[$field];
            }
        }

        if ( ! empty($data['currency']))
        {
            $order->currency = (string) $data['currency'];
        }

        if ( ! empty($data['supplier_id']))
        {
            $order->supplier_id = (int) $data['supplier_id'];
        }

        $order->search_text = $this->search_text($order);
        $order->save();

        $this->_journal($order, 'update', $before, $order->as_array(), 'Заказ изменён');

        Event::fire('erp403.order.updated', $this->payload($order));

        return $order;
    }

    /**
     * Отправить заказ поставщику: цены уходят в историю цен.
     *
     * @param   int|Model_Erp403_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function send($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number нельзя отправить: состояние «:status»',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($order->id))
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number пуст — отправлять нечего',
                array(':number' => $order->number),
                'empty_document'
            );
        }

        $order = $this->_transition($order, 'sent', 'sent', 'Заказ отправлен поставщику', $user_id);

        // Отправка = согласованная цена. Пишем историю здесь, чтобы выбор
        // поставщика опирался на реальные заказы, а не на черновики.
        foreach ($this->lines($order->id) as $line)
        {
            if ((float) $line->price > 0)
            {
                try
                {
                    Service_Erp403_Suppliers::instance()->record_price(array(
                        'supplier_id' => (int) $order->supplier_id,
                        'item_id'     => $line->item_id ? (int) $line->item_id : NULL,
                        'name'        => (string) $line->name,
                        'price'       => (float) $line->price,
                        'qty'         => (float) $line->qty,
                        'currency'    => (string) $order->currency,
                        'uom_code'    => (string) $line->uom_code,
                        'source'      => 'order',
                        'order_id'    => (int) $order->id,
                        'dated_at'    => date('Y-m-d'),
                        'note'        => 'Цена из заказа '.$order->number,
                    ), $user_id);
                }
                catch (Exception_Erp403_Procurement $e)
                {
                    Kohana::$log->add(Log::WARNING, 'erp403: цена заказа :number не записана — :error', array(
                        ':number' => $order->number,
                        ':error'  => $e->getMessage(),
                    ));
                }
            }
        }

        return $order;
    }

    /**
     * Поставщик подтвердил заказ.
     *
     * @param   int|Model_Erp403_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function confirm($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ((string) $order->status !== 'sent')
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number нельзя подтвердить: состояние «:status»',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        return $this->_transition($order, 'confirmed', 'confirmed', 'Заказ подтверждён поставщиком', $user_id);
    }

    /**
     * Отменить заказ.
     *
     * @param   int|Model_Erp403_Order  $order
     * @param   string|null             $reason
     * @param   int|null                $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function cancel($order, $reason = NULL, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ( ! in_array((string) $order->status, array('draft', 'sent', 'confirmed', 'partially_received'), TRUE))
        {
            throw new Exception_Erp403_Procurement(
                'Заказ :number нельзя отменить: состояние «:status»',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $order = $this->_transition($order, 'cancelled', 'cancelled', 'Заказ отменён'.($reason ? ': '.$reason : ''), $user_id);

        foreach ($this->lines($order->id) as $line)
        {
            if ( ! $line->is_done())
            {
                $line->status = 'cancelled';
                $line->save();
            }
        }

        return $order;
    }

    /**
     * Пересчитать состояние заказа по принятым количествам.
     *
     * Вызывается приёмкой: заказ сам понимает, принят он частично или целиком.
     *
     * @param   int|Model_Erp403_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    public function refresh_state($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ($order->is_closed())
        {
            return $order;
        }

        $total     = 0.0;
        $received  = 0.0;

        foreach ($this->lines($order->id) as $line)
        {
            if ((string) $line->status === 'cancelled')
            {
                continue;
            }

            $total    += (float) $line->qty;
            $received += (float) $line->qty_received;
        }

        $status = (string) $order->status;

        if ($total > 0 AND $received >= $total)
        {
            $status = 'received';
        }
        elseif ($received > 0)
        {
            $status = 'partially_received';
        }
        elseif (in_array($status, array('received', 'partially_received'), TRUE))
        {
            $status = 'confirmed';
        }

        if ($status === (string) $order->status)
        {
            return $order;
        }

        $before = $order->as_array();

        $order->status = $status;

        if ($status === 'received')
        {
            $order->closed_at = date('Y-m-d H:i:s');
        }

        $order->save();

        $this->_journal($order, 'status', $before, $order->as_array(), 'Состояние заказа: '.$order->status_label(), $user_id);

        Event::fire('erp403.order.'.$status, $this->payload($order));

        return $order;
    }

    // =========================================================================
    //  Сводки
    // =========================================================================

    /**
     * Сводка по заказам для доски закупок.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'sent')"), 'sent'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'confirmed')"), 'confirmed'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'partially_received')"), 'partially_received'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'received')"), 'received'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'cancelled')"), 'cancelled'),
                array(DB::expr("COUNT(*) FILTER (WHERE delivery_date < CURRENT_DATE AND status IN ('draft', 'sent', 'confirmed', 'partially_received'))"), 'overdue'),
                array(DB::expr("COALESCE(SUM(amount) FILTER (WHERE status IN ('sent', 'confirmed', 'partially_received')), 0)"), 'amount_open')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        return array(
            'total'              => (int) $row['total'],
            'draft'              => (int) $row['draft'],
            'sent'               => (int) $row['sent'],
            'confirmed'          => (int) $row['confirmed'],
            'partially_received' => (int) $row['partially_received'],
            'received'           => (int) $row['received'],
            'cancelled'          => (int) $row['cancelled'],
            'overdue'            => (int) $row['overdue'],
            'open'               => (int) $row['draft'] + (int) $row['sent'] + (int) $row['confirmed'] + (int) $row['partially_received'],
            'amount_open'        => round((float) $row['amount_open'], 2),
        );
    }

    /**
     * Сумма заказа по строкам.
     *
     * @param   int     $order_id
     * @return  float
     */
    public function amount($order_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(qty * price), 0)'), 'amount'))
            ->from(self::LINES)
            ->where('order_id', '=', (int) $order_id)
            ->where('status', '<>', 'cancelled')
            ->execute()
            ->current();

        return round((float) $row['amount'], 2);
    }

    /**
     * Строки заказа в виде массивов (для API и MCP).
     *
     * @param   int     $order_id
     * @return  array
     */
    public function line_rows($order_id)
    {
        $rows = array();

        foreach ($this->lines($order_id) as $line)
        {
            $rows[] = array(
                'id'            => (int) $line->id,
                'line_no'       => (int) $line->line_no,
                'item_id'       => $line->item_id ? (int) $line->item_id : NULL,
                'name'          => (string) $line->name,
                'code'          => $line->code ? (string) $line->code : NULL,
                'qty'           => (float) $line->qty,
                'qty_received'  => (float) $line->qty_received,
                'remaining'     => $line->remaining(),
                'price'         => (float) $line->price,
                'amount'        => $line->amount(),
                'uom_code'      => (string) $line->uom_code,
                'delivery_date' => $line->delivery_date ? (string) $line->delivery_date : NULL,
                'status'        => (string) $line->status,
                'progress'      => $line->progress(),
            );
        }

        return $rows;
    }

    /**
     * Компактный вид заказа (для событий, API и MCP).
     *
     * @param   Model_Erp403_Order  $order
     * @return  array
     */
    public function payload(Model_Erp403_Order $order)
    {
        return array(
            'id'            => (int) $order->id,
            'uuid'          => (string) $order->uuid,
            'number'        => (string) $order->number,
            'supplier_id'   => $order->supplier_id ? (int) $order->supplier_id : NULL,
            'supplier_name' => $order->supplier_id ? Service_Erp403_Suppliers::instance()->name($order->supplier_id) : NULL,
            'status'        => (string) $order->status,
            'currency'      => (string) $order->currency,
            'amount'        => (float) $order->amount,
            'delivery_date' => $order->delivery_date ? (string) $order->delivery_date : NULL,
            'warehouse_id'  => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
            'lines'         => count($this->lines($order->id)),
        );
    }

    /**
     * Номер заказа из последовательности (ЗП-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ЗП-%s-%06d', date('Y'), (int) $row['n']);
    }

    /**
     * Текст для поиска (номер заказа, поставщик, позиции).
     *
     * @param   Model_Erp403_Order  $order
     * @return  string
     */
    public function search_text(Model_Erp403_Order $order)
    {
        $names = array();

        foreach ($this->lines($order->id) as $line)
        {
            $names[] = (string) $line->name;
        }

        $supplier = $order->supplier_id ? Service_Erp403_Suppliers::instance()->name($order->supplier_id) : '';

        return mb_substr(mb_strtolower(trim($order->number.' '.$supplier.' '.$order->note.' '.implode(' ', $names))), 0, 500);
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   int|Model_Erp403_Order  $order
     * @return  Model_Erp403_Order
     * @throws  Exception_Erp403_Procurement
     */
    protected function _load($order)
    {
        $order = ($order instanceof Model_Erp403_Order) ? $order : $this->get($order);

        if ( ! $order)
        {
            throw new Exception_Erp403_Procurement('Заказ не найден', NULL, 'not_found');
        }

        return $order;
    }

    /**
     * Смена состояния заказа с журналом и событием.
     *
     * @param   Model_Erp403_Order  $order
     * @param   string              $status
     * @param   string              $event
     * @param   string              $message
     * @param   int|null            $user_id
     * @return  Model_Erp403_Order
     */
    protected function _transition(Model_Erp403_Order $order, $status, $event, $message, $user_id = NULL)
    {
        $before = $order->as_array();

        $order->status = (string) $status;

        if ($status === 'sent')
        {
            $order->sent_at = date('Y-m-d H:i:s');
        }

        if ($status === 'received')
        {
            $order->closed_at = date('Y-m-d H:i:s');
        }

        $order->save();

        $this->_journal($order, 'status', $before, $order->as_array(), $message, $user_id);

        Event::fire('erp403.order.'.$event, $this->payload($order));

        return $order;
    }

    /**
     * Пересчитать сумму и текст поиска заказа.
     *
     * @param   Model_Erp403_Order|null $order
     * @return  void
     */
    protected function _recalculate($order)
    {
        if ( ! $order)
        {
            return;
        }

        $order->amount      = $this->amount($order->id);
        $order->search_text = $this->search_text($order);
        $order->save();
    }

    /**
     * Срок поставки поставщика (для даты по умолчанию).
     *
     * @param   int     $supplier_id
     * @return  int
     */
    protected function _lead_time($supplier_id)
    {
        $profile = Service_Erp403_Suppliers::instance()->profile($supplier_id, FALSE);

        return $profile ? $profile->lead_time() : 7;
    }

    /**
     * Склад по умолчанию (склад компонентов, иначе первый активный).
     *
     * @return  int|null
     */
    protected function _default_warehouse()
    {
        return Service_Erp403_Requests::instance()->default_warehouse();
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp403_Order  $order
     * @param   string              $action
     * @param   array               $before
     * @param   array               $after
     * @param   string|null         $message
     * @param   int|null            $user_id
     * @return  void
     */
    protected function _journal(Model_Erp403_Order $order, $action, array $before, array $after, $message = NULL, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $options = array(
            'module'       => 'erp403',
            'entity_label' => (string) $order->number,
            'message'      => $message,
        );

        if ($user_id)
        {
            $options['actor_id'] = (int) $user_id;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('order', (string) $order->uuid, $action, $before, $after, $options);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
