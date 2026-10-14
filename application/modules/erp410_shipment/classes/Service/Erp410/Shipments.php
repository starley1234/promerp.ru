<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Отгрузки (erp410_shipment).
 *
 * Отгрузка — момент, когда товар покидает склад. До неё всё обратимо: строки
 * правятся, отгрузка отменяется. После проведения (`posted`) движение уже
 * случилось: склад уменьшился, позиции заказа отметили отгруженное, цена ушла
 * в историю, а обратное движение оформляется отдельным документом.
 *
 * Сборка (`build`) берёт из заказа то, что ещё не уехало, и готовит строки
 * отгрузки. Резерв под эти строки уже стоит (заказ зарезервирован), поэтому
 * проведение списывает именно замороженный товар:
 *
 *   есть резерв строки → `consume_reservation` (списание по резерву);
 *   резерва нет         → `issue` со ссылкой `doc_type = erp410_shipment`,
 *                         чтобы склад видел основание расхода.
 *
 * @package    Erp410
 * @category   Services
 */
class Service_Erp410_Shipments
{
    const TABLE    = 'erp_410_shipments';
    const LINES    = 'erp_410_shipment_lines';
    const SEQUENCE = 'erp_410_shipment_number_seq';

    /** @var Service_Erp410_Shipments|null */
    protected static $_instance;

    /**
     * @return  Service_Erp410_Shipments
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Список отгрузок с фильтрами.
     *
     * @param   array   $filters    status, customer_id, order_id, search, overdue
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp410_Shipment
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp410_Shipment');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('shipment_date', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->offset(max(0, (int) $offset))
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько отгрузок подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp410_Shipment');

        $this->apply_filters($query, $filters);

        return (int) $query->count_all();
    }

    /**
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

        if ( ! empty($filters['customer_id']))
        {
            $query->where('customer_id', '=', (int) $filters['customer_id']);
        }

        if ( ! empty($filters['order_id']))
        {
            $query->where('order_id', '=', (int) $filters['order_id']);
        }

        if ( ! empty($filters['open']))
        {
            $query->where('status', 'IN', array('draft', 'ready'));
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim((string) $filters['search'])).'%');
        }

        return $query;
    }

    /**
     * Отгрузка по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp410_Shipment|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid instanceof Model_Erp410_Shipment)
        {
            return $id_or_uuid->loaded() ? $id_or_uuid : NULL;
        }

        if ( ! $id_or_uuid)
        {
            return NULL;
        }

        // Erp::id_or_uuid() возвращает пару «колонка — значение»: по числу ищем
        // по идентификатору, по uuid — по uuid. Без разбора пары запрос ушёл бы
        // в базу как `uuid = ('id', 17)` и база ответила бы ошибкой типа.
        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $shipment = ORM::factory('Erp410_Shipment')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $shipment->loaded() ? $shipment : NULL;
    }

    /**
     * Строки отгрузки.
     *
     * @param   int     $shipment_id
     * @return  array   Model_Erp410_ShipmentLine
     */
    public function lines($shipment_id)
    {
        return ORM::factory('Erp410_ShipmentLine')
            ->where('shipment_id', '=', (int) $shipment_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Строка отгрузки.
     *
     * @param   int     $line_id
     * @return  Model_Erp410_ShipmentLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp410_ShipmentLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    /**
     * Отгрузки заказа.
     *
     * @param   int     $order_id
     * @return  array   Model_Erp410_Shipment
     */
    public function for_order($order_id)
    {
        return ORM::factory('Erp410_Shipment')
            ->where('order_id', '=', (int) $order_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Собрать отгрузку из заказа: в строки попадает то, что ещё не уехало.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   array                   $data   shipment_date, carrier, transport_number, delivery_address, comment
     * @param   int|null                $user_id
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    public function build($order, array $data = array(), $user_id = NULL)
    {
        $orders = Service_Erp410_Orders::instance();
        $order  = $orders->get($order);

        if ( ! $order)
        {
            throw new Exception_Erp410_Shipment('Заказ клиента не найден', NULL, 'not_found');
        }

        if ( ! $order->can_ship())
        {
            throw new Exception_Erp410_Shipment(
                'Отгружать можно подтверждённый заказ (сейчас «:status»)',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $pending = array();

        foreach ($orders->lines($order->id) as $line)
        {
            if ($line->is_open())
            {
                $pending[] = $line;
            }
        }

        if ( ! $pending)
        {
            throw new Exception_Erp410_Shipment('В заказе нечего отгружать: все позиции уже уехали', NULL, 'empty_document');
        }

        $shipment = ORM::factory('Erp410_Shipment');

        $shipment->number           = ! empty($data['number']) ? (string) $data['number'] : $this->number();
        $shipment->order_id         = (int) $order->id;
        $shipment->customer_id      = $order->customer_id ? (int) $order->customer_id : NULL;
        $shipment->warehouse_id     = $order->warehouse_id ? (int) $order->warehouse_id : $orders->default_warehouse();
        $shipment->status           = 'draft';
        $shipment->shipment_date    = ! empty($data['shipment_date']) ? Erp::day($data['shipment_date']) : date('Y-m-d');
        $shipment->carrier          = ! empty($data['carrier']) ? trim((string) $data['carrier']) : NULL;
        $shipment->transport_number = ! empty($data['transport_number']) ? trim((string) $data['transport_number']) : NULL;
        $shipment->delivery_address = ! empty($data['delivery_address'])
            ? (string) $data['delivery_address']
            : ($order->delivery_address ? (string) $order->delivery_address : NULL);
        $shipment->comment          = ! empty($data['comment']) ? (string) $data['comment'] : NULL;
        $shipment->created_by       = $user_id ? (int) $user_id : NULL;
        $shipment->save();

        foreach ($pending as $line)
        {
            $this->add_line($shipment->id, array(
                'order_line_id' => (int) $line->id,
                'item_id'       => $line->item_id ? (int) $line->item_id : NULL,
                'name'          => (string) $line->name,
                'code'          => $line->code ? (string) $line->code : NULL,
                'qty'           => $line->remaining(),
                'uom_code'      => (string) $line->uom_code,
                'price'         => $line->net_price(),
            ));
        }

        $this->_recalculate($shipment);

        Event::fire('erp410.shipment.created', $this->payload($shipment) + array(
            'order_number' => (string) $order->number,
            'lines'        => count($this->lines($shipment->id)),
        ));

        if ( ! empty($data['ready']))
        {
            $shipment = $this->ready($shipment, $user_id);
        }

        return $shipment;
    }

    /**
     * Создать отгрузку вручную (без заказа): например, продажа со склада.
     *
     * @param   array       $data       customer_id, warehouse_id, shipment_date, lines, order_id
     * @param   int|null    $user_id
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    public function create(array $data, $user_id = NULL)
    {
        $lines = ( ! empty($data['lines']) AND is_array($data['lines'])) ? $data['lines'] : array();

        if ( ! $lines)
        {
            throw new Exception_Erp410_Shipment('Отгрузка без позиций: укажите, что уезжает', NULL, 'empty_document');
        }

        $shipment = ORM::factory('Erp410_Shipment');

        $shipment->number           = ! empty($data['number']) ? (string) $data['number'] : $this->number();
        $shipment->order_id         = ! empty($data['order_id']) ? (int) $data['order_id'] : NULL;
        $shipment->customer_id      = ! empty($data['customer_id']) ? (int) $data['customer_id'] : NULL;
        $shipment->warehouse_id     = ! empty($data['warehouse_id'])
            ? (int) $data['warehouse_id']
            : Service_Erp410_Orders::instance()->default_warehouse();
        $shipment->status           = 'draft';
        $shipment->shipment_date    = ! empty($data['shipment_date']) ? Erp::day($data['shipment_date']) : date('Y-m-d');
        $shipment->carrier          = ! empty($data['carrier']) ? trim((string) $data['carrier']) : NULL;
        $shipment->transport_number = ! empty($data['transport_number']) ? trim((string) $data['transport_number']) : NULL;
        $shipment->delivery_address = ! empty($data['delivery_address']) ? (string) $data['delivery_address'] : NULL;
        $shipment->comment          = ! empty($data['comment']) ? (string) $data['comment'] : NULL;
        $shipment->created_by       = $user_id ? (int) $user_id : NULL;
        $shipment->save();

        foreach ($lines as $line)
        {
            $this->add_line($shipment->id, $line);
        }

        $this->_recalculate($shipment);

        Event::fire('erp410.shipment.created', $this->payload($shipment) + array(
            'lines' => count($this->lines($shipment->id)),
        ));

        return $shipment;
    }

    /**
     * Добавить строку отгрузки.
     *
     * @param   int     $shipment_id
     * @param   array   $data
     * @return  Model_Erp410_ShipmentLine
     * @throws  Exception_Erp410_Shipment
     */
    public function add_line($shipment_id, array $data)
    {
        $shipment = $this->_load($shipment_id);

        if ( ! $shipment->is_editable())
        {
            throw new Exception_Erp410_Shipment('Отгрузка :number уже проведена — строки не меняются', array(':number' => $shipment->number), 'already_posted');
        }

        $line = ORM::factory('Erp410_ShipmentLine');

        $line->shipment_id   = (int) $shipment->id;
        $line->order_line_id = ! empty($data['order_line_id']) ? (int) $data['order_line_id'] : NULL;
        $line->item_id       = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name          = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code          = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty           = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->uom_code      = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->price         = isset($data['price']) ? Erp::num($data['price']) : 0;
        $line->note          = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = Service_Erp410_Orders::instance()->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp410_Shipment('Строка отгрузки: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        $line->amount = $line->amount();
        $line->save();

        return $line;
    }

    /**
     * Изменить строку отгрузки.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp410_ShipmentLine
     * @throws  Exception_Erp410_Shipment
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp410_Shipment('Строка отгрузки не найдена', NULL, 'not_found');
        }

        $shipment = $this->_load($line->shipment_id);

        if ( ! $shipment->is_editable())
        {
            throw new Exception_Erp410_Shipment('Отгрузка уже проведена — строки не меняются', NULL, 'already_posted');
        }

        if (isset($data['qty']))
        {
            $line->qty = Erp::num($data['qty']);
        }

        if (isset($data['price']))
        {
            $line->price = Erp::num($data['price']);
        }

        if (isset($data['uom_code']) AND $data['uom_code'] !== '')
        {
            $line->uom_code = (string) $data['uom_code'];
        }

        if (array_key_exists('note', $data))
        {
            $line->note = trim((string) $data['note']) !== '' ? (string) $data['note'] : NULL;
        }

        $line->amount = $line->amount();
        $line->save();

        $this->_recalculate($shipment);

        return $line;
    }

    /**
     * Убрать строку отгрузки.
     *
     * @param   int     $line_id
     * @return  void
     * @throws  Exception_Erp410_Shipment
     */
    public function remove_line($line_id)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp410_Shipment('Строка отгрузки не найдена', NULL, 'not_found');
        }

        $shipment = $this->_load($line->shipment_id);

        if ( ! $shipment->is_editable())
        {
            throw new Exception_Erp410_Shipment('Отгрузка уже проведена — строки не меняются', NULL, 'already_posted');
        }

        $line->delete();

        $this->_recalculate($shipment);
    }

    /**
     * Отметить отгрузку готовой к отправке.
     *
     * @param   int|Model_Erp410_Shipment   $shipment
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    public function ready($shipment, $user_id = NULL)
    {
        $shipment = $this->_load($shipment);

        if ((string) $shipment->status !== 'draft')
        {
            throw new Exception_Erp410_Shipment(
                'Готовой можно отметить черновик отгрузки (сейчас «:status»)',
                array(':status' => $shipment->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($shipment->id))
        {
            throw new Exception_Erp410_Shipment('Отгрузка без позиций', NULL, 'empty_document');
        }

        return $this->_transition($shipment, 'ready', 'ready', 'Отгрузка собрана: '.$shipment->number, $user_id);
    }

    /**
     * Провести отгрузку: списать товар со склада и отметить позиции заказа.
     *
     * @param   int|Model_Erp410_Shipment   $shipment
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    public function post($shipment, $user_id = NULL)
    {
        $shipment = $this->_load($shipment);

        if ($shipment->is_posted())
        {
            throw new Exception_Erp410_Shipment('Отгрузка :number уже проведена', array(':number' => $shipment->number), 'already_posted');
        }

        if ( ! $shipment->is_editable())
        {
            throw new Exception_Erp410_Shipment(
                'Отгрузка в состоянии «:status» не проводится',
                array(':status' => $shipment->status_label()),
                'invalid_transition'
            );
        }

        $lines = $this->lines($shipment->id);

        if ( ! $lines)
        {
            throw new Exception_Erp410_Shipment('Отгрузка без позиций: проводить нечего', NULL, 'empty_document');
        }

        if ( ! $shipment->warehouse_id)
        {
            throw new Exception_Erp410_Shipment('У отгрузки не указан склад', NULL, 'warehouse_required');
        }

        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp410_Shipment('Модуль склада (erp401) недоступен: отгрузка невозможна', NULL, 'dependency');
        }

        $orders     = Service_Erp410_Orders::instance();
        $warehouse  = Service_Erp401_Stock::instance();
        $stock      = $warehouse;

        $order_line_ids = array();
        $warnings       = array();

        foreach ($lines as $line)
        {
            if ((float) $line->qty <= 0)
            {
                throw new Exception_Erp410_Shipment(
                    'Строка «:name» без количества — отгрузка не проводится',
                    array(':name' => $line->name),
                    'invalid_qty'
                );
            }

            if ( ! $line->item_id)
            {
                $warnings[] = 'Строка «'.$line->name.'» без номенклатуры: склад по ней не спишется, оформите расход вручную';

                continue;
            }

            $order_line = $line->order_line_id ? $orders->line($line->order_line_id) : NULL;
            $reservation_id = ($order_line AND $order_line->reservation_id) ? (int) $order_line->reservation_id : NULL;

            try
            {
                if ($reservation_id)
                {
                    $stock->consume_reservation($reservation_id, (float) $line->qty);
                }
                else
                {
                    $stock->issue((int) $shipment->warehouse_id, (int) $line->item_id, (float) $line->qty, array(
                        'doc_type' => 'erp410_shipment',
                        'doc_id'   => (int) $shipment->id,
                        'note'     => 'Отгрузка '.$shipment->number,
                    ));
                }
            }
            catch (Exception $e)
            {
                if ($e instanceof Exception_Erp401_Stock)
                {
                    throw new Exception_Erp410_Shipment(
                        'Не удалось списать «:name»: :error',
                        array(':name' => $line->name, ':error' => $e->getMessage()),
                        'insufficient_stock'
                    );
                }

                throw $e;
            }

            if ($order_line)
            {
                // Резерв мог закрыться частично: у строки заказа остаётся
                // актуальный идентификатор либо он снимается, если резерв
                // израсходован полностью.
                $remaining_reserved = max(0, (float) $order_line->qty_reserved - (float) $line->qty);

                if ($remaining_reserved <= 0 AND $reservation_id)
                {
                    $order_line->reservation_id = NULL;
                }

                $order_line->qty_reserved = $remaining_reserved;
                $order_line->save();

                $order_line_ids[] = (int) $order_line->id;
            }

            Service_Erp410_Prices::instance()->record_from_document(
                'shipment',
                (string) $shipment->number.'/'.$line->id,
                (int) $line->item_id,
                (float) $line->price,
                0,
                $shipment->customer_id ? (int) $shipment->customer_id : NULL,
                $user_id
            );
        }

        $shipment->posted_by = $user_id ? (int) $user_id : NULL;
        $shipment->posted_at = date('Y-m-d H:i:s');

        $shipment = $this->_transition($shipment, 'shipped', 'posted', 'Товар отгружен: '.$shipment->number, $user_id, array(
            'warehouse_id' => (int) $shipment->warehouse_id,
            'warnings'     => $warnings,
        ));

        // Позиции заказа и его состояние пересчитываются по проведённым отгрузкам
        if ($shipment->order_id)
        {
            $orders->sync_after_shipment($shipment->order_id, $user_id);
        }

        return $shipment;
    }

    /**
     * Отменить отгрузку (только черновик или готовую).
     *
     * @param   int|Model_Erp410_Shipment   $shipment
     * @param   string|null                 $reason
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    public function cancel($shipment, $reason = NULL, $user_id = NULL)
    {
        $shipment = $this->_load($shipment);

        if (in_array((string) $shipment->status, array('shipped', 'cancelled'), TRUE))
        {
            throw new Exception_Erp410_Shipment(
                'Отгрузка в состоянии «:status» не отменяется',
                array(':status' => $shipment->status_label()),
                'already_posted'
            );
        }

        if ($reason)
        {
            $shipment->comment = trim((string) $shipment->comment.' Отмена: '.$reason);
        }

        return $this->_transition($shipment, 'cancelled', 'cancelled', 'Отгрузка отменена: '.$shipment->number, $user_id, array(
            'reason' => $reason,
        ));
    }

    // =========================================================================
    //  Сводки и подписи
    // =========================================================================

    /**
     * Сводка по отгрузкам.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'all'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'ready')"), 'ready'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'shipped')"), 'shipped'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'shipped' AND posted_at >= date_trunc('month', CURRENT_DATE))"), 'shipped_month'),
                array(DB::expr("COALESCE(SUM((SELECT COALESCE(SUM(l.qty * l.price), 0) FROM erp_410_shipment_lines l WHERE l.shipment_id = erp_410_shipments.id)) FILTER (WHERE status = 'shipped' AND posted_at >= date_trunc('month', CURRENT_DATE)), 0)"), 'amount_month')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        return array(
            'all'            => (int) $row['all'],
            'draft'          => (int) $row['draft'],
            'ready'          => (int) $row['ready'],
            'shipped'        => (int) $row['shipped'],
            'shipped_month'  => (int) $row['shipped_month'],
            'amount_month'   => round((float) $row['amount_month'], 2),
        );
    }

    /**
     * Сумма отгрузки.
     *
     * @param   int     $shipment_id
     * @return  float
     */
    public function amount($shipment_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(amount), 0)'), 'total'))
            ->from(self::LINES)
            ->where('shipment_id', '=', (int) $shipment_id)
            ->execute()
            ->current();

        return round((float) $row['total'], 2);
    }

    /**
     * Строки отгрузки для интерфейса и API.
     *
     * @param   int     $shipment_id
     * @return  array
     */
    public function line_rows($shipment_id)
    {
        $rows = array();

        foreach ($this->lines($shipment_id) as $line)
        {
            $rows[] = array(
                'id'            => (int) $line->id,
                'order_line_id' => $line->order_line_id ? (int) $line->order_line_id : NULL,
                'item_id'       => $line->item_id ? (int) $line->item_id : NULL,
                'name'          => (string) $line->name,
                'code'          => $line->code ? (string) $line->code : NULL,
                'qty'           => (float) $line->qty,
                'uom_code'      => (string) $line->uom_code,
                'price'         => (float) $line->price,
                'amount'        => $line->amount(),
                'note'          => $line->note ? (string) $line->note : NULL,
            );
        }

        return $rows;
    }

    /**
     * Компактное представление отгрузки.
     *
     * @param   Model_Erp410_Shipment   $shipment
     * @return  array
     */
    public function payload(Model_Erp410_Shipment $shipment)
    {
        return array(
            'shipment_id'  => (int) $shipment->id,
            'uuid'         => (string) $shipment->uuid,
            'number'       => (string) $shipment->number,
            'order_id'     => $shipment->order_id ? (int) $shipment->order_id : NULL,
            'customer_id'  => $shipment->customer_id ? (int) $shipment->customer_id : NULL,
            'warehouse_id' => $shipment->warehouse_id ? (int) $shipment->warehouse_id : NULL,
            'status'       => (string) $shipment->status,
            'status_label' => $shipment->status_label(),
            'shipment_date'=> $shipment->shipment_date ? (string) $shipment->shipment_date : NULL,
            'amount'       => $this->amount($shipment->id),
            'title'        => 'Отгрузка '.$shipment->number.' — '.$shipment->status_label(),
        );
    }

    /**
     * Номер отгрузки из последовательности (ОТГ-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ОТГ-%s-%06d', date('Y'), (int) $row['n']);
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   int|Model_Erp410_Shipment    $shipment
     * @return  Model_Erp410_Shipment
     * @throws  Exception_Erp410_Shipment
     */
    protected function _load($shipment)
    {
        $shipment = ($shipment instanceof Model_Erp410_Shipment) ? $shipment : $this->get($shipment);

        if ( ! $shipment)
        {
            throw new Exception_Erp410_Shipment('Отгрузка не найдена', NULL, 'not_found');
        }

        return $shipment;
    }

    /**
     * Смена состояния отгрузки с событием.
     *
     * @param   Model_Erp410_Shipment   $shipment
     * @param   string                  $status
     * @param   string                  $event
     * @param   string                  $message
     * @param   int|null                $user_id
     * @param   array                   $extra
     * @return  Model_Erp410_Shipment
     */
    protected function _transition(Model_Erp410_Shipment $shipment, $status, $event, $message, $user_id = NULL, array $extra = array())
    {
        $shipment->status = (string) $status;
        $shipment->save();

        $this->_recalculate($shipment);

        Event::fire('erp410.shipment.'.$event, $this->payload($shipment) + $extra + array('message' => $message));

        if (class_exists('Service_Erp119_Journal'))
        {
            try
            {
                $options = array(
                    'module'       => 'erp410',
                    'entity_label' => (string) $shipment->number,
                    'message'      => $message,
                );

                if ($user_id)
                {
                    $options['actor_id'] = (int) $user_id;
                }

                Service_Erp119_Journal::instance()->change(
                    'shipment',
                    (string) $shipment->uuid,
                    'status',
                    array('status' => $status),
                    array('status' => $status),
                    $options
                );
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp410: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
            }
        }

        return $shipment;
    }

    /**
     * Пересчитать текст поиска отгрузки.
     *
     * @param   Model_Erp410_Shipment|null  $shipment
     * @return  void
     */
    protected function _recalculate($shipment)
    {
        if ( ! $shipment)
        {
            return;
        }

        $names = array();

        foreach ($this->lines($shipment->id) as $line)
        {
            $names[] = (string) $line->name;
        }

        $customer = $shipment->customer_id
            ? Service_Erp410_Customers::instance()->name($shipment->customer_id)
            : '';

        $shipment->search_text = mb_substr(mb_strtolower(trim(
            $shipment->number.' '.$customer.' '.$shipment->comment.' '.implode(' ', $names)
        )), 0, 500);

        $shipment->save();
    }
}
