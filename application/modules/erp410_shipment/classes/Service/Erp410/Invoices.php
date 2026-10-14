<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Счета на оплату (erp410_shipment).
 *
 * Счёт — последний шаг цепочки продажи: он превращает отгруженное в деньги.
 * Выставляется по отгрузке (все позиции или одна) либо по заказу целиком, если
 * у клиента предоплата.
 *
 * Правила простые и проверяемые:
 *
 *  - черновик счёта можно удалить, выставленный — только отменить;
 *  - оплата отмечается суммой, а не флагом: клиент платит частями, и «оплачен»
 *    наступает, когда сумма дошла до итога;
 *  - срок оплаты считается от отсрочки клиента (`Service_Erp410_Customers`);
 *  - счёт на отгруженное не дублируется: второй счёт по той же отгрузке
 *    вернёт `duplicate_invoice`.
 *
 * @package    Erp410
 * @category   Services
 */
class Service_Erp410_Invoices
{
    const TABLE    = 'erp_410_invoices';
    const LINES    = 'erp_410_invoice_lines';
    const SEQUENCE = 'erp_410_invoice_number_seq';

    /** @var Service_Erp410_Invoices|null */
    protected static $_instance;

    /**
     * @return  Service_Erp410_Invoices
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
     * Список счетов с фильтрами.
     *
     * @param   array   $filters    status, customer_id, order_id, search, overdue, unpaid
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp410_Invoice
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp410_Invoice');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('issue_date', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->offset(max(0, (int) $offset))
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько счетов подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp410_Invoice');

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

        if ( ! empty($filters['unpaid']))
        {
            $query->where('status', '=', 'issued');
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('status', '=', 'issued');
            $query->where('due_date', '<', date('Y-m-d'));
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim((string) $filters['search'])).'%');
        }

        return $query;
    }

    /**
     * Счёт по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp410_Invoice|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid instanceof Model_Erp410_Invoice)
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

        $invoice = ORM::factory('Erp410_Invoice')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $invoice->loaded() ? $invoice : NULL;
    }

    /**
     * Строки счёта.
     *
     * @param   int     $invoice_id
     * @return  array   Model_Erp410_InvoiceLine
     */
    public function lines($invoice_id)
    {
        return ORM::factory('Erp410_InvoiceLine')
            ->where('invoice_id', '=', (int) $invoice_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Счёт, выставленный по отгрузке (если уже есть).
     *
     * @param   int     $shipment_id
     * @return  Model_Erp410_Invoice|null
     */
    public function for_shipment($shipment_id)
    {
        $invoice = ORM::factory('Erp410_Invoice')
            ->where('shipment_id', '=', (int) $shipment_id)
            ->where('status', '<>', 'cancelled')
            ->order_by('id', 'DESC')
            ->find();

        return $invoice->loaded() ? $invoice : NULL;
    }

    /**
     * Счета заказа.
     *
     * @param   int     $order_id
     * @return  array   Model_Erp410_Invoice
     */
    public function for_order($order_id)
    {
        return ORM::factory('Erp410_Invoice')
            ->where('order_id', '=', (int) $order_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Выставить счёт по отгрузке.
     *
     * @param   int|Model_Erp410_Shipment   $shipment
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function from_shipment($shipment, $user_id = NULL)
    {
        $shipments = Service_Erp410_Shipments::instance();
        $shipment  = $shipments->get($shipment);

        if ( ! $shipment)
        {
            throw new Exception_Erp410_Shipment('Отгрузка не найдена', NULL, 'not_found');
        }

        if ( ! $shipment->is_posted())
        {
            throw new Exception_Erp410_Shipment(
                'Счёт выставляется по проведённой отгрузке: «:number» ещё не отгружена',
                array(':number' => $shipment->number),
                'invalid_transition'
            );
        }

        if ($this->for_shipment($shipment->id))
        {
            throw new Exception_Erp410_Shipment(
                'По отгрузке :number счёт уже выставлен',
                array(':number' => $shipment->number),
                'duplicate_invoice'
            );
        }

        $lines = array();

        foreach ($shipments->lines($shipment->id) as $line)
        {
            $lines[] = array(
                'order_line_id'    => $line->order_line_id ? (int) $line->order_line_id : NULL,
                'shipment_line_id' => (int) $line->id,
                'item_id'          => $line->item_id ? (int) $line->item_id : NULL,
                'name'             => (string) $line->name,
                'code'             => $line->code ? (string) $line->code : NULL,
                'qty'              => (float) $line->qty,
                'uom_code'         => (string) $line->uom_code,
                'price'            => (float) $line->price,
            );
        }

        $invoice = $this->create(array(
            'order_id'    => $shipment->order_id ? (int) $shipment->order_id : NULL,
            'shipment_id' => (int) $shipment->id,
            'customer_id' => $shipment->customer_id ? (int) $shipment->customer_id : NULL,
            'issue_date'  => date('Y-m-d'),
            'note'        => 'По отгрузке '.$shipment->number,
            'lines'       => $lines,
        ), $user_id);

        return $invoice;
    }

    /**
     * Выставить счёт по заказу целиком (предоплата или работа без отгрузки).
     *
     * @param   int|Model_Erp410_Order  $order
     * @param   int|null                $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function from_order($order, $user_id = NULL)
    {
        $orders = Service_Erp410_Orders::instance();
        $order  = $orders->get($order);

        if ( ! $order)
        {
            throw new Exception_Erp410_Shipment('Заказ клиента не найден', NULL, 'not_found');
        }

        $lines = array();

        foreach ($orders->lines($order->id) as $line)
        {
            if ((string) $line->status === 'cancelled')
            {
                continue;
            }

            $lines[] = array(
                'order_line_id' => (int) $line->id,
                'item_id'       => $line->item_id ? (int) $line->item_id : NULL,
                'name'          => (string) $line->name,
                'code'          => $line->code ? (string) $line->code : NULL,
                'qty'           => (float) $line->qty,
                'uom_code'      => (string) $line->uom_code,
                'price'         => $line->net_price(),
            );
        }

        if ( ! $lines)
        {
            throw new Exception_Erp410_Shipment('В заказе нет позиций для счёта', NULL, 'empty_document');
        }

        return $this->create(array(
            'order_id'    => (int) $order->id,
            'customer_id' => $order->customer_id ? (int) $order->customer_id : NULL,
            'issue_date'  => date('Y-m-d'),
            'note'        => 'По заказу '.$order->number,
            'lines'       => $lines,
        ), $user_id);
    }

    /**
     * Создать счёт.
     *
     * @param   array       $data       customer_id, order_id, shipment_id, issue_date, due_date, note, lines
     * @param   int|null    $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function create(array $data, $user_id = NULL)
    {
        if (empty($data['customer_id']))
        {
            throw new Exception_Erp410_Shipment('Счёт: укажите покупателя', NULL, 'customer_required');
        }

        $lines = ( ! empty($data['lines']) AND is_array($data['lines'])) ? $data['lines'] : array();

        if ( ! $lines)
        {
            throw new Exception_Erp410_Shipment('Счёт без позиций: выставлять нечего', NULL, 'empty_document');
        }

        $invoice = ORM::factory('Erp410_Invoice');

        $invoice->number      = ! empty($data['number']) ? (string) $data['number'] : $this->number();
        $invoice->order_id    = ! empty($data['order_id']) ? (int) $data['order_id'] : NULL;
        $invoice->shipment_id = ! empty($data['shipment_id']) ? (int) $data['shipment_id'] : NULL;
        $invoice->customer_id = (int) $data['customer_id'];
        $invoice->status      = ! empty($data['status']) ? (string) $data['status'] : 'draft';
        $invoice->issue_date  = ! empty($data['issue_date']) ? Erp::day($data['issue_date']) : date('Y-m-d');
        $invoice->currency    = ! empty($data['currency']) ? (string) $data['currency'] : 'RUB';
        $invoice->note        = isset($data['note']) ? (string) $data['note'] : NULL;
        $invoice->created_by  = $user_id ? (int) $user_id : NULL;
        $invoice->amount      = 0;
        $invoice->paid_amount = isset($data['paid_amount']) ? Erp::num($data['paid_amount']) : 0;
        $invoice->search_text = '';
        $invoice->save();

        foreach ($lines as $line)
        {
            $this->add_line($invoice->id, $line);
        }

        // Срок оплаты: из данных или от отсрочки клиента
        $credit_days = Service_Erp410_Customers::instance()->credit_days($invoice->customer_id);

        $invoice->due_date = ! empty($data['due_date'])
            ? Erp::day($data['due_date'])
            : date('Y-m-d', strtotime((string) $invoice->issue_date.' +'.max(0, $credit_days).' days'));

        $this->_recalculate($invoice);

        if ((string) $invoice->status === 'issued')
        {
            $invoice->issued_by = $user_id ? (int) $user_id : NULL;
            $invoice->issued_at = date('Y-m-d H:i:s');
            $invoice->save();
        }

        Event::fire('erp410.invoice.created', $this->payload($invoice) + array(
            'lines' => count($this->lines($invoice->id)),
        ));

        return $invoice;
    }

    /**
     * Добавить строку счёта.
     *
     * @param   int     $invoice_id
     * @param   array   $data
     * @return  Model_Erp410_InvoiceLine
     * @throws  Exception_Erp410_Shipment
     */
    public function add_line($invoice_id, array $data)
    {
        $invoice = $this->_load($invoice_id);

        if ( ! in_array((string) $invoice->status, array('draft'), TRUE))
        {
            throw new Exception_Erp410_Shipment('Счёт :number уже выставлен — строки не меняются', array(':number' => $invoice->number), 'invalid_transition');
        }

        $line = ORM::factory('Erp410_InvoiceLine');

        $line->invoice_id       = (int) $invoice->id;
        $line->order_line_id    = ! empty($data['order_line_id']) ? (int) $data['order_line_id'] : NULL;
        $line->shipment_line_id = ! empty($data['shipment_line_id']) ? (int) $data['shipment_line_id'] : NULL;
        $line->item_id          = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name             = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code             = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty              = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->uom_code         = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->price            = isset($data['price']) ? Erp::num($data['price']) : 0;
        $line->note             = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = Service_Erp410_Orders::instance()->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp410_Shipment('Строка счёта: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        $line->amount = $line->amount();
        $line->save();

        return $line;
    }

    /**
     * Убрать строку счёта (только черновик).
     *
     * @param   int     $line_id
     * @return  void
     * @throws  Exception_Erp410_Shipment
     */
    public function remove_line($line_id)
    {
        $line = ORM::factory('Erp410_InvoiceLine', (int) $line_id);

        if ( ! $line->loaded())
        {
            throw new Exception_Erp410_Shipment('Строка счёта не найдена', NULL, 'not_found');
        }

        $invoice = $this->_load($line->invoice_id);

        if ((string) $invoice->status !== 'draft')
        {
            throw new Exception_Erp410_Shipment('Счёт :number уже выставлен — строки не меняются', array(':number' => $invoice->number), 'invalid_transition');
        }

        $line->delete();

        $this->_recalculate($invoice);
    }

    /**
     * Выставить счёт клиенту (черновик → выставлен).
     *
     * @param   int|Model_Erp410_Invoice    $invoice
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function issue($invoice, $user_id = NULL)
    {
        $invoice = $this->_load($invoice);

        if ((string) $invoice->status !== 'draft')
        {
            throw new Exception_Erp410_Shipment(
                'Выставить можно только черновик счёта (сейчас «:status»)',
                array(':status' => $invoice->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($invoice->id))
        {
            throw new Exception_Erp410_Shipment('Счёт без позиций: выставлять нечего', NULL, 'empty_document');
        }

        $credit_days = Service_Erp410_Customers::instance()->credit_days($invoice->customer_id);

        $invoice->due_date  = $invoice->due_date ? $invoice->due_date : date('Y-m-d', strtotime('+'.max(0, $credit_days).' days'));
        $invoice->issued_by = $user_id ? (int) $user_id : NULL;
        $invoice->issued_at = date('Y-m-d H:i:s');

        return $this->_transition($invoice, 'issued', 'issued', 'Счёт выставлен: '.$invoice->number, $user_id);
    }

    /**
     * Отметить оплату (полную или частичную).
     *
     * @param   int|Model_Erp410_Invoice    $invoice
     * @param   float                       $amount
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function register_payment($invoice, $amount, $user_id = NULL)
    {
        $invoice = $this->_load($invoice);

        if ( ! $invoice->is_payable())
        {
            throw new Exception_Erp410_Shipment(
                'Оплату принимают по выставленному счёту (сейчас «:status»)',
                array(':status' => $invoice->status_label()),
                'invalid_transition'
            );
        }

        $amount = Erp::num($amount);

        if ($amount <= 0)
        {
            throw new Exception_Erp410_Shipment('Сумма оплаты должна быть больше нуля', NULL, 'invalid_qty');
        }

        if ($amount > $invoice->outstanding())
        {
            throw new Exception_Erp410_Shipment(
                'Оплата :amount больше остатка :left по счёту — проверьте сумму',
                array(
                    ':amount' => number_format($amount, 2, '.', ' '),
                    ':left'   => number_format($invoice->outstanding(), 2, '.', ' '),
                ),
                'invalid_qty'
            );
        }

        $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);

        if ($invoice->outstanding() <= 0.005)
        {
            $invoice->paid_amount = (float) $invoice->amount;
            $invoice->paid_at = date('Y-m-d H:i:s');
            $invoice->save();

            return $this->_transition($invoice, 'paid', 'paid', 'Счёт оплачен: '.$invoice->number, $user_id, array(
                'amount' => (float) $invoice->paid_amount,
            ));
        }

        $invoice->save();

        Event::fire('erp410.invoice.partially_paid', $this->payload($invoice) + array(
            'amount'     => $amount,
            'outstanding'=> $invoice->outstanding(),
        ));

        $this->_journal($invoice, 'payment', array(), array('paid_amount' => (float) $invoice->paid_amount), 'Частичная оплата счёта: '.$invoice->number, $user_id);

        return $invoice;
    }

    /**
     * Отменить счёт.
     *
     * @param   int|Model_Erp410_Invoice    $invoice
     * @param   string|null                 $reason
     * @param   int|null                    $user_id
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    public function cancel($invoice, $reason = NULL, $user_id = NULL)
    {
        $invoice = $this->_load($invoice);

        if (in_array((string) $invoice->status, array('paid', 'cancelled'), TRUE))
        {
            throw new Exception_Erp410_Shipment(
                'Счёт в состоянии «:status» не отменяется',
                array(':status' => $invoice->status_label()),
                'invalid_transition'
            );
        }

        if ($reason)
        {
            $invoice->note = trim((string) $invoice->note.' Отмена: '.$reason);
        }

        return $this->_transition($invoice, 'cancelled', 'cancelled', 'Счёт отменён: '.$invoice->number, $user_id, array(
            'reason' => $reason,
        ));
    }

    // =========================================================================
    //  Сводки и подписи
    // =========================================================================

    /**
     * Сводка по счетам: сколько выставлено, сколько ждёт денег, что просрочено.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'all'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'issued')"), 'issued'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'paid')"), 'paid'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'issued' AND due_date < CURRENT_DATE)"), 'overdue'),
                array(DB::expr("COALESCE(SUM(amount - paid_amount) FILTER (WHERE status = 'issued'), 0)"), 'outstanding'),
                array(DB::expr("COALESCE(SUM(paid_amount) FILTER (WHERE status = 'paid' AND paid_at >= date_trunc('month', CURRENT_DATE)), 0)"), 'paid_month')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        return array(
            'all'         => (int) $row['all'],
            'draft'       => (int) $row['draft'],
            'issued'      => (int) $row['issued'],
            'paid'        => (int) $row['paid'],
            'overdue'     => (int) $row['overdue'],
            'outstanding' => round((float) $row['outstanding'], 2),
            'paid_month'  => round((float) $row['paid_month'], 2),
        );
    }

    /**
     * Сумма счёта по позициям.
     *
     * @param   int     $invoice_id
     * @return  float
     */
    public function amount($invoice_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(amount), 0)'), 'total'))
            ->from(self::LINES)
            ->where('invoice_id', '=', (int) $invoice_id)
            ->execute()
            ->current();

        return round((float) $row['total'], 2);
    }

    /**
     * Строки счёта для интерфейса и API.
     *
     * @param   int     $invoice_id
     * @return  array
     */
    public function line_rows($invoice_id)
    {
        $rows = array();

        foreach ($this->lines($invoice_id) as $line)
        {
            $rows[] = array(
                'id'               => (int) $line->id,
                'order_line_id'    => $line->order_line_id ? (int) $line->order_line_id : NULL,
                'shipment_line_id' => $line->shipment_line_id ? (int) $line->shipment_line_id : NULL,
                'item_id'          => $line->item_id ? (int) $line->item_id : NULL,
                'name'             => (string) $line->name,
                'code'             => $line->code ? (string) $line->code : NULL,
                'qty'              => (float) $line->qty,
                'uom_code'         => (string) $line->uom_code,
                'price'            => (float) $line->price,
                'amount'           => $line->amount(),
                'note'             => $line->note ? (string) $line->note : NULL,
            );
        }

        return $rows;
    }

    /**
     * Компактное представление счёта.
     *
     * @param   Model_Erp410_Invoice    $invoice
     * @return  array
     */
    public function payload(Model_Erp410_Invoice $invoice)
    {
        return array(
            'invoice_id'   => (int) $invoice->id,
            'uuid'         => (string) $invoice->uuid,
            'number'       => (string) $invoice->number,
            'order_id'     => $invoice->order_id ? (int) $invoice->order_id : NULL,
            'shipment_id'  => $invoice->shipment_id ? (int) $invoice->shipment_id : NULL,
            'customer_id'  => $invoice->customer_id ? (int) $invoice->customer_id : NULL,
            'status'       => (string) $invoice->status,
            'status_label' => $invoice->status_label(),
            'issue_date'   => $invoice->issue_date ? (string) $invoice->issue_date : NULL,
            'due_date'     => $invoice->due_date ? (string) $invoice->due_date : NULL,
            'amount'       => (float) $invoice->amount,
            'paid_amount'  => (float) $invoice->paid_amount,
            'outstanding'  => $invoice->outstanding(),
            'title'        => 'Счёт '.$invoice->number.' — '.$invoice->status_label(),
        );
    }

    /**
     * Номер счёта из последовательности (СЧ-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('СЧ-%s-%06d', date('Y'), (int) $row['n']);
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   int|Model_Erp410_Invoice    $invoice
     * @return  Model_Erp410_Invoice
     * @throws  Exception_Erp410_Shipment
     */
    protected function _load($invoice)
    {
        $invoice = ($invoice instanceof Model_Erp410_Invoice) ? $invoice : $this->get($invoice);

        if ( ! $invoice)
        {
            throw new Exception_Erp410_Shipment('Счёт не найден', NULL, 'not_found');
        }

        return $invoice;
    }

    /**
     * Смена состояния счёта с журналом и событием.
     *
     * @param   Model_Erp410_Invoice    $invoice
     * @param   string                  $status
     * @param   string                  $event
     * @param   string                  $message
     * @param   int|null                $user_id
     * @param   array                   $extra
     * @return  Model_Erp410_Invoice
     */
    protected function _transition(Model_Erp410_Invoice $invoice, $status, $event, $message, $user_id = NULL, array $extra = array())
    {
        $before = $invoice->as_array();

        $invoice->status = (string) $status;
        $invoice->save();

        $this->_recalculate($invoice);
        $this->_journal($invoice, 'status', $before, $invoice->as_array(), $message, $user_id);

        Event::fire('erp410.invoice.'.$event, $this->payload($invoice) + $extra);

        return $invoice;
    }

    /**
     * Пересчитать сумму и текст поиска счёта.
     *
     * @param   Model_Erp410_Invoice|null   $invoice
     * @return  void
     */
    protected function _recalculate($invoice)
    {
        if ( ! $invoice)
        {
            return;
        }

        $customer = $invoice->customer_id
            ? Service_Erp410_Customers::instance()->name($invoice->customer_id)
            : '';

        $invoice->amount      = $this->amount($invoice->id);
        $invoice->search_text = mb_substr(mb_strtolower(trim($invoice->number.' '.$customer.' '.$invoice->note)), 0, 500);
        $invoice->save();

        if ($invoice->outstanding() < 0)
        {
            $invoice->paid_amount = (float) $invoice->amount;
            $invoice->save();
        }
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp410_Invoice    $invoice
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   string|null             $message
     * @param   int|null                $user_id
     * @return  void
     */
    protected function _journal(Model_Erp410_Invoice $invoice, $action, array $before, array $after, $message = NULL, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $options = array(
            'module'       => 'erp410',
            'entity_label' => (string) $invoice->number,
            'message'      => $message,
        );

        if ($user_id)
        {
            $options['actor_id'] = (int) $user_id;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('invoice', (string) $invoice->uuid, $action, $before, $after, $options);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp410: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
