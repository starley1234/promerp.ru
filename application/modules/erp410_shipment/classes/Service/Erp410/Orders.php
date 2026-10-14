<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказы клиента (erp410_shipment).
 *
 * Заказ связывает покупателя, товар и сроки:
 *
 *   draft ──confirm──► confirmed ──reserve──► reserved ──сборка──► picking
 *        ──отгрузка──► shipped ──► closed
 *   (draft/confirmed/reserved/picking) ──cancel──► cancelled
 *
 * Что сервис делает сам:
 *
 *  - считает суммы позиций и заказа (скидка — у позиции);
 *  - берёт цену из прайса клиента или базового, если цена не указана руками;
 *  - резервирует товар на складе через erp401 (`reserve`/`release`) — продажа
 *    не трогает склад напрямую и не создаёт своих остатков;
 *  - проверяет кредитный лимит клиента при подтверждении (`credit_check()`),
 *    а решение обходить лимит остаётся человеку (`$force`);
 *  - пишет журнал (erp119) и публикует события erp410.*.
 *
 * Чего сервис не делает: не списывает товар со склада (это отгрузка —
 * `Service_Erp410_Shipments`) и не выставляет счета (`Service_Erp410_Invoices`).
 *
 * @package    Erp410
 * @category   Services
 */
class Service_Erp410_Orders
{
    const TABLE    = 'erp_410_orders';
    const LINES    = 'erp_410_order_lines';
    const SEQUENCE = 'erp_410_order_number_seq';

    /** @var Service_Erp410_Orders|null */
    protected static $_instance;

    /**
     * @return  Service_Erp410_Orders
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
     * Список заказов с фильтрами.
     *
     * @param   array   $filters    status, customer_id, search, overdue, open
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp410_Order
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp410_Order');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('ship_date', 'ASC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->offset(max(0, (int) $offset))
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
        $query = ORM::factory('Erp410_Order');

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

        if ( ! empty($filters['customer_id']))
        {
            $query->where('customer_id', '=', (int) $filters['customer_id']);
        }

        if ( ! empty($filters['open']))
        {
            $query->where('status', 'IN', array('draft', 'confirmed', 'reserved', 'picking'));
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('ship_date', '<', date('Y-m-d'));
            $query->where('status', 'IN', array('draft', 'confirmed', 'reserved', 'picking'));
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
     * @return  Model_Erp410_Order|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid instanceof Model_Erp410_Order)
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

        $order = ORM::factory('Erp410_Order')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $order->loaded() ? $order : NULL;
    }

    /**
     * Позиции заказа.
     *
     * @param   int     $order_id
     * @return  array   Model_Erp410_OrderLine
     */
    public function lines($order_id)
    {
        return ORM::factory('Erp410_OrderLine')
            ->where('order_id', '=', (int) $order_id)
            ->order_by('line_no', 'ASC')
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Позиция по идентификатору.
     *
     * @param   int     $line_id
     * @return  Model_Erp410_OrderLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp410_OrderLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    /**
     * Следующий номер строки в заказе.
     *
     * @param   int     $order_id
     * @return  int
     */
    public function next_line_no($order_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(MAX(line_no), 0)'), 'last'))
            ->from(self::LINES)
            ->where('order_id', '=', (int) $order_id)
            ->execute()
            ->current();

        return ((int) $row['last']) + 10;
    }

    /**
     * Заказы, которые пора везти (подтверждённые и ближайшие по сроку).
     *
     * @param   int     $limit
     * @return  array   Model_Erp410_Order
     */
    public function due_soon($days = 7, $limit = 10)
    {
        return ORM::factory('Erp410_Order')
            ->where('status', 'IN', array('confirmed', 'reserved', 'picking'))
            ->where('ship_date', 'IS NOT', DB::expr('NULL'))
            ->where('ship_date', '<=', date('Y-m-d', strtotime('+'.(int) $days.' days')))
            ->order_by('ship_date', 'ASC')
            ->limit(max(1, (int) $limit))
            ->find_all()
            ->as_array();
    }

    /**
     * Заказы в работе.
     *
     * @param   int     $limit
     * @return  array   Model_Erp410_Order
     */
    public function open_orders($limit = 50)
    {
        return $this->search(array('open' => TRUE), $limit, 0);
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Создать заказ клиента.
     *
     * @param   array       $data       customer_id, warehouse_id, ship_date, lines, …
     * @param   int|null    $user_id
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    public function create(array $data, $user_id = NULL)
    {
        if (empty($data['customer_id']))
        {
            throw new Exception_Erp410_Shipment('Заказ клиента: укажите покупателя', NULL, 'customer_required');
        }

        $lines = ( ! empty($data['lines']) AND is_array($data['lines'])) ? $data['lines'] : array();

        if ( ! $lines)
        {
            throw new Exception_Erp410_Shipment('Заказ без позиций: укажите, что продаём', NULL, 'empty_document');
        }

        $customer_id = (int) $data['customer_id'];

        $order = ORM::factory('Erp410_Order');

        $order->number       = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $order->customer_id  = $customer_id;
        $order->contact_id   = ! empty($data['contact_id']) ? (int) $data['contact_id'] : NULL;
        $order->contract_id  = ! empty($data['contract_id']) ? (int) $data['contract_id'] : NULL;
        $order->warehouse_id = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : $this->default_warehouse();
        $order->status       = isset($data['status']) ? (string) $data['status'] : 'draft';
        $order->source       = ! empty($data['source']) ? (string) $data['source'] : 'manual';
        $order->source_ref   = ! empty($data['source_ref']) ? (string) $data['source_ref'] : NULL;
        $order->currency     = ! empty($data['currency']) ? (string) $data['currency'] : 'RUB';
        $order->order_date   = ! empty($data['order_date']) ? Erp::day($data['order_date']) : date('Y-m-d');
        $order->ship_date    = ! empty($data['ship_date']) ? Erp::day($data['ship_date']) : date('Y-m-d', strtotime('+7 days'));
        $order->delivery_address = ! empty($data['delivery_address']) ? (string) $data['delivery_address'] : $this->delivery_address($customer_id);
        $order->payment_terms = isset($data['payment_terms']) ? (string) $data['payment_terms'] : Service_Erp410_Customers::instance()->payment_label($customer_id);
        $order->note         = isset($data['note']) ? (string) $data['note'] : NULL;
        $order->created_by   = $user_id ? (int) $user_id : NULL;
        $order->amount       = 0;
        $order->search_text  = '';
        $order->save();

        foreach ($lines as $line)
        {
            $this->add_line($order->id, $line);
        }

        $this->_recalculate($order);

        $this->_journal($order, 'create', array(), $order->as_array(), 'Заказ клиента создан: '.$order->number, $user_id);

        Event::fire('erp410.order.created', $this->payload($order));

        return $order;
    }

    /**
     * Добавить позицию.
     *
     * Цена берётся в таком порядке: из данных, из прайса клиента, базовая; если
     * и её нет — позиция сохраняется с нулевой ценой и предупреждением в
     * карточке, а не с выдуманной суммой.
     *
     * @param   int     $order_id
     * @param   array   $data
     * @return  Model_Erp410_OrderLine
     * @throws  Exception_Erp410_Shipment
     */
    public function add_line($order_id, array $data)
    {
        $order = $this->_load($order_id);

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp410_Shipment(
                'Заказ :number в состоянии «:status» — позиции добавить нельзя',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $line = ORM::factory('Erp410_OrderLine');

        $line->order_id = (int) $order->id;
        $line->line_no  = isset($data['line_no']) ? (int) $data['line_no'] : $this->next_line_no($order->id);
        $line->item_id  = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name     = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code     = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty      = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->uom_code = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->status   = 'open';
        $line->note     = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = $this->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp410_Shipment('Позиция заказа: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        if (isset($data['price']) AND Erp::num($data['price']) > 0)
        {
            $line->price    = Erp::num($data['price']);
            $line->discount = isset($data['discount']) ? min(100, max(0, Erp::num($data['discount']))) : 0;
        }
        else
        {
            $price = Service_Erp410_Prices::instance()->price_for($line->item_id, $order->customer_id);

            $line->price    = (float) $price['price'];
            $line->discount = ($price['discount'] > 0)
                ? (float) $price['discount']
                : Service_Erp410_Customers::instance()->discount($order->customer_id);
        }

        $line->amount = $line->amount();
        $line->save();

        $this->_recalculate($order);

        return $line;
    }

    /**
     * Изменить позицию.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp410_OrderLine
     * @throws  Exception_Erp410_Shipment
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp410_Shipment('Позиция заказа не найдена', NULL, 'not_found');
        }

        $order = $this->_load($line->order_id);

        if ( ! $order->is_editable() OR (float) $line->qty_reserved > 0)
        {
            throw new Exception_Erp410_Shipment(
                'Позиция уже зарезервирована или заказ не правится',
                NULL,
                'invalid_transition'
            );
        }

        if (isset($data['item_id']))
        {
            $line->item_id = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        }

        if (isset($data['name']))
        {
            $line->name = trim((string) $data['name']);
        }

        if (isset($data['code']))
        {
            $line->code = trim((string) $data['code']) !== '' ? (string) $data['code'] : NULL;
        }

        if (isset($data['qty']))
        {
            $line->qty = Erp::num($data['qty']);
        }

        if (isset($data['price']))
        {
            $line->price = Erp::num($data['price']);
        }

        if (isset($data['discount']))
        {
            $line->discount = min(100, max(0, Erp::num($data['discount'])));
        }

        if (isset($data['uom_code']) AND $data['uom_code'] !== '')
        {
            $line->uom_code = (string) $data['uom_code'];
        }

        if (array_key_exists('note', $data))
        {
            $line->note = trim((string) $data['note']) !== '' ? (string) $data['note'] : NULL;
        }

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = $this->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp410_Shipment('Позиция заказа: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        $line->amount = $line->amount();
        $line->save();

        $this->_recalculate($order);

        return $line;
    }

    /**
     * Убрать позицию из заказа.
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
            throw new Exception_Erp410_Shipment('Позиция заказа не найдена', NULL, 'not_found');
        }

        $order = $this->_load($line->order_id);

        if ( ! $order->is_editable() OR (float) $line->qty_reserved > 0)
        {
            throw new Exception_Erp410_Shipment('Позиция зарезервирована — убрать её нельзя', NULL, 'invalid_transition');
        }

        $line_id = (int) $line->id;
        $line->delete();

        $this->_recalculate($order);
    }

    /**
     * Править шапку заказа.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   array                   $data
     * @param   int|null                $user_id
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    public function update($order, array $data, $user_id = NULL)
    {
        $order  = $this->_load($order);
        $before = $order->as_array();

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp410_Shipment(
                'Заказ :number в состоянии «:status» — правка запрещена',
                array(':number' => $order->number, ':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        foreach (array('contact_id', 'contract_id') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $order->$field = ! empty($data[$field]) ? (int) $data[$field] : NULL;
            }
        }

        if ( ! empty($data['warehouse_id']))
        {
            $order->warehouse_id = (int) $data['warehouse_id'];
        }

        if ( ! empty($data['ship_date']))
        {
            $order->ship_date = Erp::day($data['ship_date']);
        }

        if ( ! empty($data['order_date']))
        {
            $order->order_date = Erp::day($data['order_date']);
        }

        foreach (array('delivery_address', 'payment_terms', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $order->$field = trim((string) $data[$field]) !== '' ? (string) $data[$field] : NULL;
            }
        }

        $order->save();

        $this->_recalculate($order);
        $this->_journal($order, 'update', $before, $order->as_array(), 'Заказ изменён: '.$order->number, $user_id);

        Event::fire('erp410.order.updated', $this->payload($order));

        return $order;
    }

    /**
     * Подтвердить заказ: с этого момента позиции можно резервировать.
     *
     * Кредитный лимит проверяется здесь: если сумма заказа не влезает в
     * свободный лимит, сервис отказывает — и человек решает осознанно
     * (`$force`), а не обнаруживает перерасход при отгрузке.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @param   bool                    $force  подтвердить несмотря на лимит
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    public function confirm($order, $user_id = NULL, $force = FALSE)
    {
        $order = $this->_load($order);

        if ((string) $order->status !== 'draft')
        {
            throw new Exception_Erp410_Shipment(
                'Подтвердить можно только черновик (сейчас «:status»)',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($order->id))
        {
            throw new Exception_Erp410_Shipment('Заказ без позиций: подтверждать нечего', NULL, 'empty_document');
        }

        $check = Service_Erp410_Customers::instance()->credit_check($order->customer_id, (float) $order->amount);

        if ( ! $check['allowed'] AND ! $force)
        {
            throw new Exception_Erp410_Shipment(
                'Кредитный лимит клиента не позволяет подтвердить заказ: :reason',
                array(':reason' => $check['reason']),
                'credit_limit'
            );
        }

        $order->confirmed_by = $user_id ? (int) $user_id : NULL;
        $order->confirmed_at = date('Y-m-d H:i:s');

        return $this->_transition($order, 'confirmed', 'confirmed', 'Заказ подтверждён: '.$order->number, $user_id, array(
            'credit' => $check,
        ));
    }

    /**
     * Зарезервировать товар под заказ на складе (erp401).
     *
     * Резерв ставится по каждой позиции отдельно: неудача (не хватает остатка)
     * откатывает уже созданные резервы этого заказа, чтобы склад не остался
     * замороженным наполовину.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @return  array                   reserved (строк), skipped (без номенклатуры), warnings
     * @throws  Exception_Erp410_Shipment
     */
    public function reserve($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ( ! in_array((string) $order->status, array('confirmed', 'reserved'), TRUE))
        {
            throw new Exception_Erp410_Shipment(
                'Резервировать можно подтверждённый заказ (сейчас «:status»)',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp410_Shipment('Модуль склада (erp401) недоступен: резерв невозможен', NULL, 'dependency');
        }

        $warehouse_id = $order->warehouse_id ? (int) $order->warehouse_id : $this->default_warehouse();

        if ( ! $warehouse_id)
        {
            throw new Exception_Erp410_Shipment('У заказа не указан склад отгрузки', NULL, 'warehouse_required');
        }

        $reserved  = 0;
        $skipped   = array();
        $warnings  = array();
        $created   = array();

        try
        {
            foreach ($this->lines($order->id) as $line)
            {
                if ( ! $line->is_open())
                {
                    continue;
                }

                if ( ! $line->item_id)
                {
                    $skipped[] = (string) $line->name;
                    $warnings[] = 'Позиция «'.$line->name.'» без номенклатуры: зарезервировать нельзя, её придётся отгружать вручную';
                    continue;
                }

                $qty = $line->remaining();

                if ($qty <= 0)
                {
                    continue;
                }

                $reservation = Service_Erp401_Stock::instance()->reserve($warehouse_id, (int) $line->item_id, $qty, array(
                    'ref_type' => 'erp410_order',
                    'ref_id'   => (int) $order->id,
                    'note'     => 'Резерв под заказ '.$order->number,
                ));

                $created[] = (int) $reservation->id;

                $line->reservation_id = (int) $reservation->id;
                $line->qty_reserved   = $qty;
                $line->status         = 'reserved';
                $line->save();

                $reserved++;
            }
        }
        catch (Exception $e)
        {
            // Откат: снимаем то, что успели заморозить, и возвращаем строки.
            foreach ($created as $reservation_id)
            {
                try
                {
                    Service_Erp401_Stock::instance()->release_reservation($reservation_id);
                }
                catch (Exception $release_error)
                {
                    Kohana::$log->add(Log::WARNING, 'erp410: не удалось снять резерв #:id — :error', array(
                        ':id'    => $reservation_id,
                        ':error' => $release_error->getMessage(),
                    ));
                }
            }

            foreach ($this->lines($order->id) as $line)
            {
                if ($line->reservation_id AND in_array((int) $line->reservation_id, $created, TRUE))
                {
                    $line->reservation_id = NULL;
                    $line->qty_reserved   = 0;
                    $line->status         = 'open';
                    $line->save();
                }
            }

            if ($e instanceof Exception_Erp401_Stock)
            {
                throw new Exception_Erp410_Shipment(
                    'Не удалось зарезервировать товар: :error',
                    array(':error' => $e->getMessage()),
                    'insufficient_stock'
                );
            }

            throw $e;
        }

        if ( ! $reserved AND $skipped)
        {
            throw new Exception_Erp410_Shipment(
                'В заказе нет позиций с номенклатурой — резервировать нечего',
                NULL,
                'empty_document'
            );
        }

        if ((string) $order->status !== 'reserved' AND $reserved > 0)
        {
            $order = $this->_transition($order, 'reserved', 'reserved', 'Товар зарезервирован под заказ: '.$order->number, $user_id, array(
                'reserved' => $reserved,
            ));
        }

        return array(
            'reserved' => $reserved,
            'skipped'  => $skipped,
            'warnings' => $warnings,
        );
    }

    /**
     * Снять резервы заказа (до отгрузки).
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @return  int                     сколько резервов снято
     * @throws  Exception_Erp410_Shipment
     */
    public function release($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ( ! in_array((string) $order->status, array('reserved', 'picking'), TRUE))
        {
            throw new Exception_Erp410_Shipment(
                'Снять резерв можно у зарезервированного заказа (сейчас «:status»)',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $released = 0;

        foreach ($this->lines($order->id) as $line)
        {
            if ( ! $line->reservation_id OR (float) $line->qty_reserved <= 0)
            {
                continue;
            }

            try
            {
                Service_Erp401_Stock::instance()->release_reservation((int) $line->reservation_id);
                $released++;
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp410: резерв #:id не снят — :error', array(
                    ':id'    => $line->reservation_id,
                    ':error' => $e->getMessage(),
                ));
            }

            $line->reservation_id = NULL;
            $line->qty_reserved   = 0;
            $line->status         = $line->is_complete() ? 'shipped' : 'open';
            $line->save();
        }

        if ((string) $order->status === 'reserved')
        {
            $order = $this->_transition($order, 'confirmed', 'released', 'Резерв снят, заказ снова подтверждён: '.$order->number, $user_id, array(
                'released' => $released,
            ));
        }
        else
        {
            Event::fire('erp410.order.released', $this->payload($order) + array('released' => $released));
        }

        return $released;
    }

    /**
     * Отменить заказ. Резервы снимаются, отгруженный заказ отменить нельзя.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   string|null             $reason
     * @param   int|null                $user_id
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    public function cancel($order, $reason = NULL, $user_id = NULL)
    {
        $order = $this->_load($order);

        if (in_array((string) $order->status, array('shipped', 'closed', 'cancelled'), TRUE))
        {
            throw new Exception_Erp410_Shipment(
                'Заказ в состоянии «:status» отменить нельзя',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        if (in_array((string) $order->status, array('reserved', 'picking'), TRUE))
        {
            $this->release($order, $user_id);
        }

        $order->note = trim((string) $order->note.' '.($reason ? 'Отмена: '.$reason : ''));

        return $this->_transition($order, 'cancelled', 'cancelled', 'Заказ отменён: '.$order->number, $user_id, array(
            'reason' => $reason,
        ));
    }

    /**
     * Закрыть отгруженный заказ (работа по нему закончена).
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    public function close($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        if ((string) $order->status !== 'shipped')
        {
            throw new Exception_Erp410_Shipment(
                'Закрыть можно только отгруженный заказ (сейчас «:status»)',
                array(':status' => $order->status_label()),
                'invalid_transition'
            );
        }

        $order->closed_at = date('Y-m-d H:i:s');

        return $this->_transition($order, 'closed', 'closed', 'Заказ закрыт: '.$order->number, $user_id);
    }

    /**
     * Пересчитать состояния заказа и его позиций по проведённым отгрузкам.
     *
     * Единственный источник правды о том, что уехало, — проведённые отгрузки;
     * после каждой из них заказ пересчитывается, а не «помнит» статус сам.
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp410_Order
     */
    public function sync_after_shipment($order, $user_id = NULL)
    {
        $order = $this->_load($order);

        $rows = DB::select(
                'l.order_line_id',
                array(DB::expr('SUM(l.qty)'), 'shipped')
            )
            ->from(array('erp_410_shipment_lines', 'l'))
            ->join(array('erp_410_shipments', 's'), 'INNER')
            ->on('s.id', '=', 'l.shipment_id')
            ->where('s.order_id', '=', (int) $order->id)
            ->where('s.status', '=', 'shipped')
            ->group_by('l.order_line_id')
            ->execute()
            ->as_array();

        $shipped_by_line = array();

        foreach ($rows as $row)
        {
            $shipped_by_line[(int) $row['order_line_id']] = (float) $row['shipped'];
        }

        $complete = 0;
        $total    = 0;
        $partial  = 0;

        foreach ($this->lines($order->id) as $line)
        {
            $total++;

            $line->qty_shipped = isset($shipped_by_line[(int) $line->id])
                ? round($shipped_by_line[(int) $line->id], 6)
                : 0;

            if ((string) $line->status !== 'cancelled')
            {
                if ($line->is_complete())
                {
                    $line->status = 'shipped';
                    $complete++;
                }
                elseif ((float) $line->qty_shipped > 0)
                {
                    // Отгружена часть количества, а не вся строка: заказ тоже
                    // отгружен частично, и это должно быть видно состоянием.
                    $partial++;
                }
                elseif ((float) $line->qty_reserved > 0)
                {
                    $line->status = 'reserved';
                }
                else
                {
                    $line->status = 'open';
                }
            }

            $line->save();
        }

        $status = (string) $order->status;

        if ($total > 0 AND $complete === $total AND $status !== 'closed')
        {
            $order = $this->_transition($order, 'shipped', 'shipped', 'Заказ отгружен полностью: '.$order->number, $user_id);
        }
        elseif (($complete > 0 OR $partial > 0) AND in_array($status, array('confirmed', 'reserved', 'picking'), TRUE))
        {
            $order = $this->_transition($order, 'picking', 'partially_shipped', 'Заказ отгружен частично: '.$order->number, $user_id, array(
                'complete' => $complete,
                'total'    => $total,
            ));
        }

        $this->_recalculate($order);

        return $order;
    }

    /**
     * Отменить позицию заказа (до отгрузки).
     *
     * @param   int         $line_id
     * @param   int|null    $user_id
     * @return  Model_Erp410_OrderLine
     * @throws  Exception_Erp410_Shipment
     */
    public function cancel_line($line_id, $user_id = NULL)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp410_Shipment('Позиция заказа не найдена', NULL, 'not_found');
        }

        if ((float) $line->qty_shipped > 0)
        {
            throw new Exception_Erp410_Shipment('Позиция уже отгружена — отменить нельзя', NULL, 'invalid_transition');
        }

        if ($line->reservation_id)
        {
            try
            {
                Service_Erp401_Stock::instance()->release_reservation((int) $line->reservation_id);
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp410: резерв #:id не снят — :error', array(
                    ':id'    => $line->reservation_id,
                    ':error' => $e->getMessage(),
                ));
            }

            $line->reservation_id = NULL;
            $line->qty_reserved   = 0;
        }

        $line->status = 'cancelled';
        $line->save();

        $order = $this->_load($line->order_id);
        $this->_recalculate($order);

        Event::fire('erp410.order.line_cancelled', $this->payload($order) + array(
            'line_id' => (int) $line->id,
            'name'    => (string) $line->name,
        ));

        return $line;
    }

    // =========================================================================
    //  Сводки, сумма, подписи
    // =========================================================================

    /**
     * Сводка продаж.
     *
     * @return  array
     */
    public function stats()
    {
        $now = DB::select(
                array(DB::expr('COUNT(*)'), 'all'),
                array(DB::expr("COUNT(*) FILTER (WHERE status IN ('draft', 'confirmed', 'reserved', 'picking'))"), 'open'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'reserved')"), 'reserved'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'picking')"), 'picking'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'shipped')"), 'shipped'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'closed')"), 'closed'),
                array(DB::expr("COALESCE(SUM(amount) FILTER (WHERE status IN ('draft', 'confirmed', 'reserved', 'picking')), 0)"), 'amount_open'),
                array(DB::expr("COUNT(*) FILTER (WHERE ship_date < CURRENT_DATE AND status IN ('draft', 'confirmed', 'reserved', 'picking'))"), 'overdue')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        $month = DB::select(array(DB::expr('COALESCE(SUM(amount), 0)'), 'amount_month'))
            ->from(self::TABLE)
            ->where('status', 'IN', array('shipped', 'closed'))
            ->where(DB::expr("date_trunc('month', COALESCE(closed_at, updated_at))"), '=', DB::expr("date_trunc('month', CURRENT_DATE)"))
            ->execute()
            ->current();

        return array(
            'all'           => (int) $now['all'],
            'open'          => (int) $now['open'],
            'draft'         => (int) $now['draft'],
            'reserved'      => (int) $now['reserved'],
            'picking'       => (int) $now['picking'],
            'shipped'       => (int) $now['shipped'],
            'closed'        => (int) $now['closed'],
            'amount_open'   => round((float) $now['amount_open'], 2),
            'amount_month'  => round((float) $month['amount_month'], 2),
            'overdue'       => (int) $now['overdue'],
        );
    }

    /**
     * Сумма заказа по позициям.
     *
     * @param   int     $order_id
     * @return  float
     */
    public function amount($order_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(amount), 0)'), 'total'))
            ->from(self::LINES)
            ->where('order_id', '=', (int) $order_id)
            ->where('status', '<>', 'cancelled')
            ->execute()
            ->current();

        return round((float) $row['total'], 2);
    }

    /**
     * Позиции заказа для интерфейса и API.
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
                'id'             => (int) $line->id,
                'line_no'        => (int) $line->line_no,
                'item_id'        => $line->item_id ? (int) $line->item_id : NULL,
                'name'           => (string) $line->name,
                'code'           => $line->code ? (string) $line->code : NULL,
                'qty'            => (float) $line->qty,
                'qty_reserved'   => (float) $line->qty_reserved,
                'qty_shipped'    => (float) $line->qty_shipped,
                'remaining'      => $line->remaining(),
                'uom_code'       => (string) $line->uom_code,
                'price'          => (float) $line->price,
                'discount'       => (float) $line->discount,
                'net_price'      => $line->net_price(),
                'amount'         => $line->amount(),
                'status'         => (string) $line->status,
                'status_label'   => $line->status_label(),
                'progress'       => $line->progress(),
                'reservation_id' => $line->reservation_id ? (int) $line->reservation_id : NULL,
                'note'           => $line->note ? (string) $line->note : NULL,
            );
        }

        return $rows;
    }

    /**
     * Компактное представление заказа (события, API, MCP).
     *
     * @param   Model_Erp410_Order  $order
     * @return  array
     */
    public function payload(Model_Erp410_Order $order)
    {
        return array(
            'order_id'      => (int) $order->id,
            'uuid'          => (string) $order->uuid,
            'number'        => (string) $order->number,
            'customer_id'   => $order->customer_id ? (int) $order->customer_id : NULL,
            'status'        => (string) $order->status,
            'status_label'  => $order->status_label(),
            'source'        => (string) $order->source,
            'currency'      => (string) $order->currency,
            'amount'        => (float) $order->amount,
            'ship_date'     => $order->ship_date ? (string) $order->ship_date : NULL,
            'warehouse_id'  => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
            'title'         => 'Заказ клиента '.$order->number.' — '.$order->status_label(),
        );
    }

    /**
     * Номер заказа из последовательности (ЗКЛ-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ЗКЛ-%s-%06d', date('Y'), (int) $row['n']);
    }

    /**
     * Текст для поиска (номер, клиент, позиции, пояснение).
     *
     * @param   Model_Erp410_Order  $order
     * @return  string
     */
    public function search_text(Model_Erp410_Order $order)
    {
        $names = array();

        foreach ($this->lines($order->id) as $line)
        {
            $names[] = (string) $line->name;
        }

        $customer = $order->customer_id ? Service_Erp410_Customers::instance()->name($order->customer_id) : '';

        return mb_substr(mb_strtolower(trim($order->number.' '.$customer.' '.$order->note.' '.implode(' ', $names))), 0, 500);
    }

    /**
     * Наименование номенклатуры (реестр erp227).
     *
     * @param   int     $item_id
     * @return  string
     */
    public function item_name($item_id)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp227_Registry'))
        {
            return '';
        }

        try
        {
            $object = Service_Erp227_Registry::instance()->get_by_id((int) $item_id);

            return $object->loaded() ? (string) $object->name : '';
        }
        catch (Exception $e)
        {
            return '';
        }
    }

    /**
     * Код номенклатуры (реестр erp227).
     *
     * @param   int     $item_id
     * @return  string
     */
    public function item_code($item_id)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp227_Registry'))
        {
            return '';
        }

        try
        {
            $object = Service_Erp227_Registry::instance()->get_by_id((int) $item_id);

            return ($object->loaded() AND (string) $object->code !== '') ? (string) $object->code : '';
        }
        catch (Exception $e)
        {
            return '';
        }
    }

    /**
     * Сколько позиции доступно к отгрузке на складе (свободный остаток).
     *
     * @param   int         $item_id
     * @param   int|null    $warehouse_id
     * @return  float|null  NULL — склад недоступен, 0 — данных нет
     */
    public function available_qty($item_id, $warehouse_id = NULL)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp401_Stock'))
        {
            return NULL;
        }

        if ( ! $warehouse_id)
        {
            $warehouse_id = $this->default_warehouse();
        }

        if ( ! $warehouse_id)
        {
            return NULL;
        }

        try
        {
            return Service_Erp401_Stock::instance()->available((int) $warehouse_id, (int) $item_id);
        }
        catch (Exception $e)
        {
            return 0.0;
        }
    }

    /**
     * Склад по умолчанию: основной, иначе первый активный.
     *
     * @return  int|null
     */
    public function default_warehouse()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return NULL;
        }

        try
        {
            $warehouses = Service_Erp401_Stock::instance()->warehouses(TRUE);
        }
        catch (Exception $e)
        {
            return NULL;
        }

        foreach ($warehouses as $warehouse)
        {
            if ((string) $warehouse->type === 'main')
            {
                return (int) $warehouse->id;
            }
        }

        foreach ($warehouses as $warehouse)
        {
            return (int) $warehouse->id;
        }

        return NULL;
    }

    /**
     * Адрес доставки по умолчанию: из условий клиента.
     *
     * @param   int     $customer_id
     * @return  string|null
     */
    public function delivery_address($customer_id)
    {
        $profile = Service_Erp410_Customers::instance()->profile($customer_id);

        return ($profile AND (string) $profile->delivery_address !== '') ? (string) $profile->delivery_address : NULL;
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   int|Model_Erp410_Order  $order
     * @return  Model_Erp410_Order
     * @throws  Exception_Erp410_Shipment
     */
    protected function _load($order)
    {
        $order = ($order instanceof Model_Erp410_Order) ? $order : $this->get($order);

        if ( ! $order)
        {
            throw new Exception_Erp410_Shipment('Заказ клиента не найден', NULL, 'not_found');
        }

        return $order;
    }

    /**
     * Смена состояния заказа с журналом и событием.
     *
     * @param   Model_Erp410_Order  $order
     * @param   string              $status
     * @param   string              $event
     * @param   string              $message
     * @param   int|null            $user_id
     * @param   array               $extra
     * @return  Model_Erp410_Order
     */
    protected function _transition(Model_Erp410_Order $order, $status, $event, $message, $user_id = NULL, array $extra = array())
    {
        $before = $order->as_array();

        $order->status = (string) $status;

        if ($status === 'closed')
        {
            $order->closed_at = $order->closed_at ? $order->closed_at : date('Y-m-d H:i:s');
        }

        $order->save();

        $this->_journal($order, 'status', $before, $order->as_array(), $message, $user_id);

        Event::fire('erp410.order.'.$event, $this->payload($order) + $extra);

        return $order;
    }

    /**
     * Пересчитать сумму и текст поиска заказа.
     *
     * @param   Model_Erp410_Order|null $order
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
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp410_Order  $order
     * @param   string              $action
     * @param   array               $before
     * @param   array               $after
     * @param   string|null         $message
     * @param   int|null            $user_id
     * @return  void
     */
    protected function _journal(Model_Erp410_Order $order, $action, array $before, array $after, $message = NULL, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $options = array(
            'module'       => 'erp410',
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
            Kohana::$log->add(Log::WARNING, 'erp410: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
