<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приёмка товара от поставщика (erp403_procurement).
 *
 * Приёмка — единственное место, где закупка меняет склад. Поэтому правила
 * собраны здесь и работают одинаково для человека, JSON API и агента:
 *
 *  - приёмку заводят по заказу: строки подставляются из того, что ещё не
 *    принято (можно уменьшить количество — «привезли меньше»);
 *  - позиция без номенклатуры (`item_id`) на склад не попадает: при проведении
 *    она пропускается с предупреждением, а не создаёт остаток «непонятно чего»;
 *  - позиция, не прошедшая входной контроль (`quality_status = 'failed'`),
 *    блокирует проведение — это правила erp405, но проверяет их закупка;
 *  - проведение пишет приход в `erp401` (`receive`) с ссылкой на документ,
 *    обновляет принятое количество в строках заказа, тянет статус заказа,
 *    пишет цену в историю (`erp403_prices`) и публикует
 *    `erp403.receipt.posted`;
 *  - проведённую приёмку не правят и не отменяют: обратное движение — это
 *    расход на складе (erp401), а не «отмена документа».
 *
 * @package    Erp403
 * @category   Services
 */
class Service_Erp403_Receipts
{
    const TABLE    = 'erp_403_receipts';
    const LINES    = 'erp_403_receipt_lines';
    const SEQUENCE = 'erp_403_receipt_number_seq';

    /** @var Service_Erp403_Receipts|null */
    protected static $_instance;

    /**
     * @return  Service_Erp403_Receipts
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
     * Список приёмок с фильтрами.
     *
     * @param   array   $filters    status, supplier_id, order_id, search
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp403_Receipt
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp403_Receipt');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('received_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько приёмок подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp403_Receipt');

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

        if ( ! empty($filters['order_id']))
        {
            $query->where('order_id', '=', (int) $filters['order_id']);
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim((string) $filters['search'])).'%');
        }

        return $query;
    }

    /**
     * Приёмка по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp403_Receipt|null
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

        $receipt = ORM::factory('Erp403_Receipt')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $receipt->loaded() ? $receipt : NULL;
    }

    /**
     * Строки приёмки по порядку.
     *
     * @param   int     $receipt_id
     * @return  array   Model_Erp403_ReceiptLine
     */
    public function lines($receipt_id)
    {
        return ORM::factory('Erp403_ReceiptLine')
            ->where('receipt_id', '=', (int) $receipt_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Строка приёмки по идентификатору.
     *
     * @param   int     $line_id
     * @return  Model_Erp403_ReceiptLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp403_ReceiptLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Создать приёмку: по заказу (строки подставляются) или вручную.
     *
     * @param   array       $data   order_id, supplier_id, warehouse_id,
     *                              doc_number, doc_date, received_at, note, lines[]
     * @param   int|null    $user_id
     * @return  Model_Erp403_Receipt
     * @throws  Exception_Erp403_Procurement
     */
    public function create(array $data, $user_id = NULL)
    {
        $order = NULL;

        if ( ! empty($data['order_id']))
        {
            $order = Service_Erp403_Orders::instance()->get($data['order_id']);

            if ( ! $order)
            {
                throw new Exception_Erp403_Procurement('Заказ поставщику не найден', NULL, 'not_found');
            }

            if ($order->is_closed())
            {
                throw new Exception_Erp403_Procurement(
                    'Заказ :number в состоянии «:status» — принимать нечего',
                    array(':number' => $order->number, ':status' => $order->status_label()),
                    'invalid_transition'
                );
            }
        }

        $receipt = ORM::factory('Erp403_Receipt');

        $receipt->number       = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $receipt->order_id     = $order ? (int) $order->id : NULL;
        $receipt->supplier_id  = ! empty($data['supplier_id']) ? (int) $data['supplier_id'] : ($order ? (int) $order->supplier_id : NULL);
        $receipt->warehouse_id = $this->_receipt_warehouse($data, $order);
        $receipt->status       = 'draft';
        $receipt->doc_number   = isset($data['doc_number']) ? (string) $data['doc_number'] : NULL;
        $receipt->doc_date     = ! empty($data['doc_date']) ? Erp::day($data['doc_date']) : NULL;
        $receipt->received_at  = ! empty($data['received_at']) ? Erp::day($data['received_at']) : date('Y-m-d');
        $receipt->note         = isset($data['note']) ? (string) $data['note'] : NULL;
        $receipt->created_by   = $user_id ? (int) $user_id : NULL;
        $receipt->search_text  = '';
        $receipt->save();

        if ( ! empty($data['lines']) AND is_array($data['lines']))
        {
            foreach ($data['lines'] as $line)
            {
                $this->add_line($receipt->id, $line);
            }
        }
        elseif ($order)
        {
            // Строки из заказа: то, что ещё не принято.
            foreach (Service_Erp403_Orders::instance()->lines($order->id) as $order_line)
            {
                if ($order_line->is_done() OR $order_line->remaining() <= 0)
                {
                    continue;
                }

                $this->add_line($receipt->id, array(
                    'order_line_id' => (int) $order_line->id,
                    'item_id'       => $order_line->item_id ? (int) $order_line->item_id : NULL,
                    'name'          => (string) $order_line->name,
                    'qty'           => $order_line->remaining(),
                    'price'         => (float) $order_line->price,
                    'uom_code'      => (string) $order_line->uom_code,
                ));
            }
        }

        if ( ! $this->lines($receipt->id))
        {
            $receipt->delete();

            throw new Exception_Erp403_Procurement('Приёмка без позиций: принимать нечего', NULL, 'empty_document');
        }

        $receipt->search_text = $this->search_text($receipt);
        $receipt->save();

        $this->_journal($receipt, 'create', array(), $receipt->as_array(), 'Приёмка создана: '.$receipt->number);

        Event::fire('erp403.receipt.created', $this->payload($receipt));

        return $receipt;
    }

    /**
     * Добавить позицию приёмки.
     *
     * @param   int     $receipt_id
     * @param   array   $data
     * @return  Model_Erp403_ReceiptLine
     * @throws  Exception_Erp403_Procurement
     */
    public function add_line($receipt_id, array $data)
    {
        $receipt = $this->get($receipt_id);

        if ( ! $receipt)
        {
            throw new Exception_Erp403_Procurement('Приёмка не найдена', NULL, 'not_found');
        }

        if ( ! $receipt->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number в состоянии «:status» — правки запрещены',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        $line = ORM::factory('Erp403_ReceiptLine');

        $line->receipt_id    = (int) $receipt->id;
        $line->order_line_id = ! empty($data['order_line_id']) ? (int) $data['order_line_id'] : NULL;
        $line->item_id       = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name          = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->qty           = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->price         = isset($data['price']) ? Erp::num($data['price']) : 0;
        $line->uom_code      = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->quality_status = isset($data['quality_status']) ? (string) $data['quality_status'] : 'pending';
        $line->note          = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = Service_Erp403_Requests::instance()->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp403_Procurement('Позиция приёмки: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        if ($line->qty <= 0)
        {
            throw new Exception_Erp403_Procurement('Позиция приёмки: количество должно быть больше нуля', NULL, 'not_found');
        }

        $line->save();

        return $line;
    }

    /**
     * Изменить позицию приёмки.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp403_ReceiptLine
     * @throws  Exception_Erp403_Procurement
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp403_Procurement('Позиция приёмки не найдена', NULL, 'not_found');
        }

        $receipt = $this->get($line->receipt_id);

        if ($receipt AND ! $receipt->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number в состоянии «:status» — правки запрещены',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        if ( ! empty($data['item_id']))
        {
            $line->item_id = (int) $data['item_id'];

            if (empty($data['name']))
            {
                $line->name = Service_Erp403_Requests::instance()->item_name($line->item_id);
            }
        }

        foreach (array('name', 'uom_code', 'quality_status', 'note') as $field)
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

        $line->save();

        return $line;
    }

    /**
     * Удалить позицию приёмки.
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
            throw new Exception_Erp403_Procurement('Позиция приёмки не найдена', NULL, 'not_found');
        }

        $receipt = $this->get($line->receipt_id);

        if ($receipt AND ! $receipt->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number в состоянии «:status» — позиции удалить нельзя',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        $line->delete();

        return TRUE;
    }

    /**
     * Записать результат входного контроля (вызывает erp405 или человек).
     *
     * @param   int         $line_id
     * @param   string      $status     pending|passed|failed
     * @param   string|null $note
     * @return  Model_Erp403_ReceiptLine
     * @throws  Exception_Erp403_Procurement
     */
    public function set_quality($line_id, $status, $note = NULL)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp403_Procurement('Позиция приёмки не найдена', NULL, 'not_found');
        }

        if ( ! isset(Model_Erp403_ReceiptLine::$quality_statuses[$status]))
        {
            throw new Exception_Erp403_Procurement('Неизвестный результат контроля: :status', array(':status' => (string) $status), 'not_found');
        }

        $receipt = $this->get($line->receipt_id);

        if ($receipt AND (string) $receipt->status !== 'draft')
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number уже проведена — результат контроля менять нельзя',
                array(':number' => $receipt->number),
                'invalid_transition'
            );
        }

        $before = $line->as_array();

        $line->quality_status = (string) $status;

        if ($note !== NULL)
        {
            $line->note = (string) $note;
        }

        $line->save();

        $this->_journal($receipt, 'update', $before, $line->as_array(), 'Входной контроль: '.$line->quality_label());

        Event::fire('erp403.receipt.quality', array(
            'receipt_id' => (int) $line->receipt_id,
            'line_id'    => (int) $line->id,
            'item_id'    => $line->item_id ? (int) $line->item_id : NULL,
            'quality'    => (string) $line->quality_status,
        ));

        return $line;
    }

    /**
     * Изменить шапку приёмки (черновик).
     *
     * @param   int|Model_Erp403_Receipt    $receipt
     * @param   array                       $data
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Receipt
     * @throws  Exception_Erp403_Procurement
     */
    public function update($receipt, array $data, $user_id = NULL)
    {
        $receipt = $this->_load($receipt);

        if ( ! $receipt->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number в состоянии «:status» — правки запрещены',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        $before = $receipt->as_array();

        foreach (array('doc_number') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $receipt->$field = ($data[$field] === '') ? NULL : (string) $data[$field];
            }
        }

        if (array_key_exists('note', $data))
        {
            $receipt->note = ($data['note'] === '') ? NULL : (string) $data['note'];
        }

        if (array_key_exists('warehouse_id', $data))
        {
            $receipt->warehouse_id = (int) $data['warehouse_id'];
        }

        foreach (array('doc_date', 'received_at') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $receipt->$field = ! empty($data[$field]) ? Erp::day($data[$field]) : NULL;
            }
        }

        $receipt->search_text = $this->search_text($receipt);
        $receipt->save();

        $this->_journal($receipt, 'update', $before, $receipt->as_array(), 'Приёмка изменена');

        return $receipt;
    }

    /**
     * Провести приёмку: приход на склад по каждой позиции.
     *
     * @param   int|Model_Erp403_Receipt    $receipt
     * @param   int|null                    $user_id
     * @return  array   posted (проведённые строки), skipped (предупреждения)
     * @throws  Exception_Erp403_Procurement
     */
    public function post($receipt, $user_id = NULL)
    {
        $receipt = $this->_load($receipt);

        if ( ! $receipt->can_post())
        {
            throw new Exception_Erp403_Procurement(
                'Приёмку :number нельзя провести: состояние «:status»',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        $lines = $this->lines($receipt->id);

        if ( ! $lines)
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number пуста — проводить нечего',
                array(':number' => $receipt->number),
                'empty_document'
            );
        }

        if ( ! $receipt->warehouse_id)
        {
            throw new Exception_Erp403_Procurement('У приёмки не указан склад — приход оформить некуда', NULL, 'dependency');
        }

        // Сначала проверяем всё, что может помешать: контроль качества.
        foreach ($lines as $line)
        {
            if ($line->is_blocked())
            {
                throw new Exception_Erp403_Procurement(
                    'Позиция «:name» не прошла входной контроль — приход на склад запрещён',
                    array(':name' => (string) $line->name),
                    'quality_blocked'
                );
            }
        }

        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp403_Procurement('Модуль склада (erp401) недоступен: приход оформить нечем', NULL, 'dependency');
        }

        $posted  = array();
        $skipped = array();

        foreach ($lines as $line)
        {
            if ( ! $line->item_id)
            {
                $skipped[] = array(
                    'line_id' => (int) $line->id,
                    'name'    => (string) $line->name,
                    'reason'  => 'У позиции нет номенклатуры: приход на склад не оформлен',
                );
                continue;
            }

            $movement = Service_Erp401_Stock::instance()->receive((int) $receipt->warehouse_id, (int) $line->item_id, (float) $line->qty, array(
                'uom_code'     => (string) $line->uom_code,
                'doc_type'     => 'erp403_receipt',
                'doc_id'       => (int) $receipt->id,
                'note'         => 'Приёмка '.$receipt->number.($receipt->doc_number ? ' (документ поставщика '.$receipt->doc_number.')' : ''),
                'performed_at' => $receipt->received_at ? (string) $receipt->received_at.' 12:00:00' : NULL,
            ));

            $posted[] = array(
                'line_id'     => (int) $line->id,
                'movement_id' => (int) $movement->id,
                'item_id'     => (int) $line->item_id,
                'qty'         => (float) $line->qty,
            );

            // Принятое количество в строке заказа и судьба строки заявки.
            if ($line->order_line_id)
            {
                $order_line = Service_Erp403_Orders::instance()->line($line->order_line_id);

                if ($order_line)
                {
                    $order_line->qty_received = round((float) $order_line->qty_received + (float) $line->qty, 6);
                    $order_line->status = ($order_line->remaining() <= 0) ? 'received' : 'partial';
                    $order_line->save();

                    if ($order_line->request_line_id)
                    {
                        $request_line = Service_Erp403_Requests::instance()->line($order_line->request_line_id);

                        if ($request_line AND (string) $request_line->status !== 'cancelled')
                        {
                            $request_line->status = 'received';
                            $request_line->save();
                        }
                    }
                }
            }

            // Фактическая цена из накладной поставщика — в историю цен.
            if ((float) $line->price > 0 AND $receipt->supplier_id)
            {
                try
                {
                    Service_Erp403_Suppliers::instance()->record_price(array(
                        'supplier_id' => (int) $receipt->supplier_id,
                        'item_id'     => (int) $line->item_id,
                        'name'        => (string) $line->name,
                        'price'       => (float) $line->price,
                        'qty'         => (float) $line->qty,
                        'uom_code'    => (string) $line->uom_code,
                        'source'      => 'receipt',
                        'order_id'    => $receipt->order_id ? (int) $receipt->order_id : NULL,
                        'receipt_id'  => (int) $receipt->id,
                        'dated_at'    => $receipt->received_at ? (string) $receipt->received_at : date('Y-m-d'),
                        'note'        => 'Цена из приёмки '.$receipt->number,
                    ), $user_id);
                }
                catch (Exception_Erp403_Procurement $e)
                {
                    Kohana::$log->add(Log::WARNING, 'erp403: цена приёмки :number не записана — :error', array(
                        ':number' => $receipt->number,
                        ':error'  => $e->getMessage(),
                    ));
                }
            }
        }

        $before = $receipt->as_array();

        $receipt->status    = 'posted';
        $receipt->posted_by = $user_id ? (int) $user_id : NULL;
        $receipt->posted_at = date('Y-m-d H:i:s');
        $receipt->save();

        $this->_journal($receipt, 'status', $before, $receipt->as_array(), 'Приёмка проведена: приход на склад оформлен');

        // Заказ сам понимает, принят он частично или целиком.
        if ($receipt->order_id)
        {
            $order = Service_Erp403_Orders::instance()->get($receipt->order_id);

            if ($order)
            {
                Service_Erp403_Orders::instance()->refresh_state($order, $user_id);
            }
        }

        Event::fire('erp403.receipt.posted', $this->payload($receipt) + array(
            'posted'  => count($posted),
            'skipped' => count($skipped),
        ));

        return array('receipt' => $receipt, 'posted' => $posted, 'skipped' => $skipped);
    }

    /**
     * Отменить приёмку (только черновик: проведённый приход отменяет склад).
     *
     * @param   int|Model_Erp403_Receipt    $receipt
     * @param   string|null                 $reason
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Receipt
     * @throws  Exception_Erp403_Procurement
     */
    public function cancel($receipt, $reason = NULL, $user_id = NULL)
    {
        $receipt = $this->_load($receipt);

        if ((string) $receipt->status === 'posted')
        {
            throw new Exception_Erp403_Procurement(
                'Приёмка :number проведена: обратное движение оформляет склад (erp401), а не отмена документа',
                array(':number' => $receipt->number),
                'already_posted'
            );
        }

        if ((string) $receipt->status !== 'draft')
        {
            throw new Exception_Erp403_Procurement(
                'Приёмку :number нельзя отменить: состояние «:status»',
                array(':number' => $receipt->number, ':status' => $receipt->status_label()),
                'invalid_transition'
            );
        }

        $before = $receipt->as_array();

        $receipt->status = 'cancelled';

        if ($reason)
        {
            $receipt->note = trim((string) $receipt->note."\n".'Отменено: '.$reason);
        }

        $receipt->save();

        $this->_journal($receipt, 'status', $before, $receipt->as_array(), 'Приёмка отменена'.($reason ? ': '.$reason : ''), $user_id);

        Event::fire('erp403.receipt.cancelled', $this->payload($receipt));

        return $receipt;
    }

    // =========================================================================
    //  Сводки
    // =========================================================================

    /**
     * Сводка по приёмкам для доски закупок.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'posted')"), 'posted'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'cancelled')"), 'cancelled'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'posted' AND received_at >= CURRENT_DATE - 30)"), 'posted_month'),
                array(DB::expr("COALESCE(SUM(l.qty * l.price) FILTER (WHERE r.status = 'posted' AND r.received_at >= CURRENT_DATE - 30), 0)"), 'amount_month')
            )
            ->from(array(self::TABLE, 'r'))
            ->join(array(self::LINES, 'l'), 'LEFT')
            ->on('l.receipt_id', '=', 'r.id')
            ->execute()
            ->current();

        $pending_quality = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from(array(self::LINES, 'l'))
            ->join(array(self::TABLE, 'r'), 'INNER')
            ->on('r.id', '=', 'l.receipt_id')
            ->where('l.quality_status', '=', 'failed')
            ->where('r.status', '=', 'draft')
            ->execute()
            ->current();

        return array(
            'total'           => (int) $row['total'],
            'draft'           => (int) $row['draft'],
            'posted'          => (int) $row['posted'],
            'cancelled'       => (int) $row['cancelled'],
            'posted_month'    => (int) $row['posted_month'],
            'amount_month'    => round((float) $row['amount_month'], 2),
            'quality_blocked' => (int) $pending_quality['total'],
        );
    }

    /**
     * Строки приёмки в виде массивов (для API и MCP).
     *
     * @param   int     $receipt_id
     * @return  array
     */
    public function line_rows($receipt_id)
    {
        $rows = array();

        foreach ($this->lines($receipt_id) as $line)
        {
            $rows[] = array(
                'id'             => (int) $line->id,
                'order_line_id'  => $line->order_line_id ? (int) $line->order_line_id : NULL,
                'item_id'        => $line->item_id ? (int) $line->item_id : NULL,
                'name'           => (string) $line->name,
                'qty'            => (float) $line->qty,
                'price'          => (float) $line->price,
                'amount'         => $line->amount(),
                'uom_code'       => (string) $line->uom_code,
                'quality_status' => (string) $line->quality_status,
                'quality_label'  => $line->quality_label(),
            );
        }

        return $rows;
    }

    /**
     * Компактный вид приёмки (для событий, API и MCP).
     *
     * @param   Model_Erp403_Receipt    $receipt
     * @return  array
     */
    public function payload(Model_Erp403_Receipt $receipt)
    {
        return array(
            'id'            => (int) $receipt->id,
            'uuid'          => (string) $receipt->uuid,
            'number'        => (string) $receipt->number,
            'order_id'      => $receipt->order_id ? (int) $receipt->order_id : NULL,
            'supplier_id'   => $receipt->supplier_id ? (int) $receipt->supplier_id : NULL,
            'supplier_name' => $receipt->supplier_id ? Service_Erp403_Suppliers::instance()->name($receipt->supplier_id) : NULL,
            'warehouse_id'  => $receipt->warehouse_id ? (int) $receipt->warehouse_id : NULL,
            'status'        => (string) $receipt->status,
            'doc_number'    => $receipt->doc_number ? (string) $receipt->doc_number : NULL,
            'received_at'   => $receipt->received_at ? (string) $receipt->received_at : NULL,
            'lines'         => count($this->lines($receipt->id)),
        );
    }

    /**
     * Склад приёмки: явный склад → склад заказа → склад по умолчанию.
     *
     * Разбор вынесен в отдельный метод, потому что здесь легко написать
     * `$order AND $order->warehouse_id ? ... : ...` и получить `TRUE`: у `AND`
     * приоритет ниже, чем у тернарного оператора, поэтому выражение считается
     * как `$order AND (...)` и возвращает булево — приёмка уходила на склад №1,
     * а приход оформлялся не туда, куда его ждали.
     *
     * @param   array                   $data
     * @param   Model_Erp403_Order|null $order
     * @return  int
     * @throws  Exception_Erp403_Procurement
     */
    protected function _receipt_warehouse(array $data, $order)
    {
        if ( ! empty($data['warehouse_id']))
        {
            return (int) $data['warehouse_id'];
        }

        if ($order AND $order->warehouse_id)
        {
            return (int) $order->warehouse_id;
        }

        $warehouse_id = (int) Service_Erp403_Requests::instance()->default_warehouse();

        if ( ! $warehouse_id)
        {
            throw new Exception_Erp403_Procurement(
                'У приёмки не определён склад: укажите склад приёмки или настройте склад по умолчанию',
                NULL,
                'dependency'
            );
        }

        return $warehouse_id;
    }

    /**
     * Номер приёмки из последовательности (ПР-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ПР-%s-%06d', date('Y'), (int) $row['n']);
    }

    /**
     * Текст для поиска (номер приёмки, документ поставщика, позиции).
     *
     * @param   Model_Erp403_Receipt    $receipt
     * @return  string
     */
    public function search_text(Model_Erp403_Receipt $receipt)
    {
        $names = array();

        foreach ($this->lines($receipt->id) as $line)
        {
            $names[] = (string) $line->name;
        }

        return mb_substr(mb_strtolower(trim($receipt->number.' '.$receipt->doc_number.' '.$receipt->note.' '.implode(' ', $names))), 0, 500);
    }

    /**
     * Строки приёмки по заказу в виде массивов (для формы приёмки).
     *
     * @param   int     $order_id
     * @return  array
     */
    public function order_progress($order_id)
    {
        $rows = DB::select(
                array(DB::expr('COUNT(*)'), 'lines'),
                array(DB::expr('COALESCE(SUM(l.qty), 0)'), 'qty'),
                array(DB::expr('COALESCE(SUM(l.qty_received), 0)'), 'qty_received')
            )
            ->from(array('erp_403_order_lines', 'l'))
            ->where('l.order_id', '=', (int) $order_id)
            ->where('l.status', '<>', 'cancelled')
            ->execute()
            ->current();

        return array(
            'lines'        => (int) $rows['lines'],
            'qty'          => (float) $rows['qty'],
            'qty_received' => (float) $rows['qty_received'],
            'progress'     => ((float) $rows['qty'] > 0) ? (int) min(100, round((float) $rows['qty_received'] / (float) $rows['qty'] * 100)) : 0,
        );
    }

    /**
     * @param   int|Model_Erp403_Receipt    $receipt
     * @return  Model_Erp403_Receipt
     * @throws  Exception_Erp403_Procurement
     */
    protected function _load($receipt)
    {
        $receipt = ($receipt instanceof Model_Erp403_Receipt) ? $receipt : $this->get($receipt);

        if ( ! $receipt)
        {
            throw new Exception_Erp403_Procurement('Приёмка не найдена', NULL, 'not_found');
        }

        return $receipt;
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp403_Receipt|null   $receipt
     * @param   string                      $action
     * @param   array                       $before
     * @param   array                       $after
     * @param   string|null                 $message
     * @return  void
     */
    protected function _journal($receipt, $action, array $before, array $after, $message = NULL)
    {
        if ( ! $receipt OR ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('receipt', (string) $receipt->uuid, $action, $before, $after, array(
                'module'       => 'erp403',
                'entity_label' => (string) $receipt->number,
                'message'      => $message,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
