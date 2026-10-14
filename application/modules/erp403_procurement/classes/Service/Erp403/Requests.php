<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заявки на закупку (erp403_procurement).
 *
 * Сервис — единственная точка входа в заявки: контроллеры, JSON API,
 * инструменты MCP и соседние модули работают через него, поэтому правила
 * описаны один раз:
 *
 *  - номер заявки уникален и берётся из последовательности ЗК-ГГГГ-НННННН;
 *  - правки разрешены у черновика и заявки на согласовании; согласованную
 *    заявку меняет уже заказ, отклонённую — никто;
 *  - согласование фиксирует, кто и когда решил (журнал `erp119`), и публикует
 *    `erp403.request.approved` — по этому событию закупщик собирает заказ;
 *  - автоматические заявки из минимума склада (`auto_from_low_stock()`) не
 *    задваиваются: открытая заявка на ту же позицию — не повод заводить вторую.
 *
 * Заявку из планирования (erp407) создаёт `create()` — это тот же путь, что и
 * у человека: сначала заявка, потом согласование, потом заказ.
 *
 * @package    Erp403
 * @category   Services
 */
class Service_Erp403_Requests
{
    const TABLE    = 'erp_403_requests';
    const LINES    = 'erp_403_request_lines';
    const SEQUENCE = 'erp_403_request_number_seq';

    /** @var Service_Erp403_Requests|null */
    protected static $_instance;

    /**
     * @return  Service_Erp403_Requests
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
     * Список заявок с фильтрами.
     *
     * @param   array   $filters    status, source, search, warehouse_id, overdue, item_id
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp403_Request
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp403_Request');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('need_date', 'ASC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько заявок подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp403_Request');

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

        if ( ! empty($filters['source']))
        {
            $query->where('source', '=', (string) $filters['source']);
        }

        if ( ! empty($filters['warehouse_id']))
        {
            $query->where('warehouse_id', '=', (int) $filters['warehouse_id']);
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('need_date', '<', date('Y-m-d'));
            $query->where('status', 'IN', array('draft', 'submitted', 'approved'));
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.$this->normalize((string) $filters['search']).'%');
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('id', 'IN', DB::select('request_id')
                ->from(self::LINES)
                ->where('item_id', '=', (int) $filters['item_id']));
        }

        return $query;
    }

    /**
     * Заявка по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp403_Request|null
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

        $request = ORM::factory('Erp403_Request')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $request->loaded() ? $request : NULL;
    }

    /**
     * Строки заявки по порядку.
     *
     * @param   int     $request_id
     * @return  array   Model_Erp403_RequestLine
     */
    public function lines($request_id)
    {
        return ORM::factory('Erp403_RequestLine')
            ->where('request_id', '=', (int) $request_id)
            ->order_by('line_no', 'ASC')
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Строка заявки по идентификатору.
     *
     * @param   int     $line_id
     * @return  Model_Erp403_RequestLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp403_RequestLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    /**
     * Следующий номер строки (шаг 10 — как в составе заказа производства).
     *
     * @param   int     $request_id
     * @return  int
     */
    public function next_line_no($request_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(MAX(line_no), 0)'), 'line_no'))
            ->from(self::LINES)
            ->where('request_id', '=', (int) $request_id)
            ->execute()
            ->current();

        return (int) $row['line_no'] + 10;
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Создать заявку (вручную, из планирования или из минимума склада).
     *
     * Принимает как одиночную позицию (`item_id`, `name`, `qty`, `uom_code`,
     * `need_date`), так и список строк в ключе `lines` — этим пользуются
     * автоматические заявки.
     *
     * @param   array       $data       number, status, priority, source, source_ref,
     *                                  warehouse_id, need_date, note, item_id, name,
     *                                  qty, uom_code, price, lines[]
     * @param   int|null    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function create(array $data, $user_id = NULL)
    {
        $lines = array();

        if ( ! empty($data['lines']) AND is_array($data['lines']))
        {
            $lines = $data['lines'];
        }
        elseif ( ! empty($data['name']) OR ! empty($data['item_id']))
        {
            $lines[] = $data;
        }

        if ( ! $lines)
        {
            throw new Exception_Erp403_Procurement('Заявка без позиций: укажите, что закупать', NULL, 'empty_document');
        }

        $request = ORM::factory('Erp403_Request');

        $request->number       = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $request->status       = isset($data['status']) ? (string) $data['status'] : 'draft';
        $request->priority     = isset($data['priority']) ? (int) $data['priority'] : 3;
        $request->source       = isset($data['source']) ? (string) $data['source'] : 'manual';
        $request->source_ref   = isset($data['source_ref']) && $data['source_ref'] !== '' ? (string) $data['source_ref'] : NULL;
        $request->warehouse_id = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : $this->default_warehouse();
        $request->need_date    = ! empty($data['need_date']) ? Erp::day($data['need_date']) : date('Y-m-d', strtotime('+14 days'));
        $request->note         = isset($data['note']) ? (string) $data['note'] : NULL;
        $request->created_by   = $user_id ? (int) $user_id : NULL;
        $request->search_text  = $this->search_text($request);
        $request->save();

        foreach ($lines as $line)
        {
            $this->add_line($request->id, $line);
        }

        $this->_journal($request, 'create', array(), $request->as_array(), 'Заявка создана: '.$request->number);

        Event::fire('erp403.request.created', $this->payload($request));

        return $request;
    }

    /**
     * Изменить заявку (шапку).
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   array                       $data
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function update($request, array $data, $user_id = NULL)
    {
        $request = ($request instanceof Model_Erp403_Request) ? $request : $this->get($request);

        if ( ! $request)
        {
            throw new Exception_Erp403_Procurement('Заявка не найдена', NULL, 'not_found');
        }

        if ( ! $request->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number в состоянии «:status» — правки запрещены',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $before = $request->as_array();

        foreach (array('note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $request->$field = (string) $data[$field];
            }
        }

        if (array_key_exists('need_date', $data))
        {
            $request->need_date = ! empty($data['need_date']) ? Erp::day($data['need_date']) : NULL;
        }

        if (array_key_exists('warehouse_id', $data))
        {
            $request->warehouse_id = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : NULL;
        }

        if (array_key_exists('priority', $data))
        {
            $request->priority = (int) $data['priority'];
        }

        $request->search_text = $this->search_text($request);
        $request->save();

        $this->_journal($request, 'update', $before, $request->as_array(), 'Заявка изменена');

        Event::fire('erp403.request.updated', $this->payload($request));

        return $request;
    }

    /**
     * Добавить позицию в заявку.
     *
     * @param   int     $request_id
     * @param   array   $data   item_id, name, code, qty, uom_code, need_date,
     *                          supplier_id, price, note
     * @return  Model_Erp403_RequestLine
     * @throws  Exception_Erp403_Procurement
     */
    public function add_line($request_id, array $data)
    {
        $request = $this->get($request_id);

        if ( ! $request)
        {
            throw new Exception_Erp403_Procurement('Заявка не найдена', NULL, 'not_found');
        }

        if ( ! $request->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number в состоянии «:status» — позиции добавить нельзя',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $line = ORM::factory('Erp403_RequestLine');

        $line->request_id  = (int) $request->id;
        $line->line_no     = isset($data['line_no']) ? (int) $data['line_no'] : $this->next_line_no($request->id);
        $line->item_id     = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name        = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code        = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty         = isset($data['qty']) ? Erp::num($data['qty']) : 0;
        $line->uom_code    = isset($data['uom_code']) && $data['uom_code'] !== '' ? (string) $data['uom_code'] : 'шт';
        $line->need_date   = ! empty($data['need_date']) ? Erp::day($data['need_date']) : NULL;
        $line->supplier_id = ! empty($data['supplier_id']) ? (int) $data['supplier_id'] : NULL;
        $line->price       = isset($data['price']) ? Erp::num($data['price']) : 0;
        $line->status      = isset($data['status']) ? (string) $data['status'] : 'open';
        $line->note        = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = $this->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp403_Procurement('Позиция заявки: укажите номенклатуру или наименование', NULL, 'not_found');
        }

        if ($line->price <= 0 AND $line->item_id)
        {
            // Подсказка цены: последняя известная цена по этой номенклатуре.
            $line->price = $this->last_price($line->item_id, $line->supplier_id);
        }

        $line->save();

        return $line;
    }

    /**
     * Изменить позицию заявки.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp403_RequestLine
     * @throws  Exception_Erp403_Procurement
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp403_Procurement('Позиция заявки не найдена', NULL, 'not_found');
        }

        $request = $this->get($line->request_id);

        if ($request AND ! $request->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number в состоянии «:status» — правки запрещены',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $before = $line->as_array();

        if ( ! empty($data['item_id']) AND empty($data['name']))
        {
            $line->item_id = (int) $data['item_id'];
            $line->name    = $this->item_name($line->item_id);
        }

        foreach (array('name', 'code', 'uom_code', 'status', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = ($data[$field] === '' AND $field === 'note') ? NULL : (string) $data[$field];
            }
        }

        if (array_key_exists('need_date', $data))
        {
            $line->need_date = ! empty($data['need_date']) ? Erp::day($data['need_date']) : NULL;
        }

        foreach (array('qty', 'price') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = Erp::num($data[$field]);
            }
        }

        if (array_key_exists('supplier_id', $data))
        {
            $line->supplier_id = ! empty($data['supplier_id']) ? (int) $data['supplier_id'] : NULL;
        }

        $line->save();

        $this->_journal($request, 'update', $before, $line->as_array(), 'Изменена позиция заявки: '.$line->name, $line);

        return $line;
    }

    /**
     * Удалить позицию заявки.
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
            throw new Exception_Erp403_Procurement('Позиция заявки не найдена', NULL, 'not_found');
        }

        $request = $this->get($line->request_id);

        if ($request AND ! $request->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number в состоянии «:status» — позиции удалить нельзя',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $line->delete();

        return TRUE;
    }

    /**
     * Отправить заявку на согласование.
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function submit($request, $user_id = NULL)
    {
        $request = $this->_load($request);

        if ( ! $request->is_editable())
        {
            throw new Exception_Erp403_Procurement(
                'Заявку :number нельзя отправить: состояние «:status»',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($request->id))
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number пуста — отправлять нечего',
                array(':number' => $request->number),
                'empty_document'
            );
        }

        return $this->_transition($request, 'submitted', 'submitted', 'Заявка отправлена на согласование', $user_id);
    }

    /**
     * Согласовать заявку: дальше её можно заказывать.
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function approve($request, $user_id = NULL)
    {
        $request = $this->_load($request);

        if ( ! $request->can_decide())
        {
            throw new Exception_Erp403_Procurement(
                'Заявку :number нельзя согласовать: состояние «:status»',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($request->id))
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number пуста — согласовывать нечего',
                array(':number' => $request->number),
                'empty_document'
            );
        }

        return $this->_transition($request, 'approved', 'approved', 'Заявка согласована', $user_id);
    }

    /**
     * Отклонить заявку.
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   string|null                 $reason
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function reject($request, $reason = NULL, $user_id = NULL)
    {
        $request = $this->_load($request);

        if ( ! $request->can_decide())
        {
            throw new Exception_Erp403_Procurement(
                'Заявку :number нельзя отклонить: состояние «:status»',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $request = $this->_transition($request, 'rejected', 'rejected', 'Заявка отклонена'.($reason ? ': '.$reason : ''), $user_id);

        if ($reason)
        {
            $request->note = trim((string) $request->note."\n".'Отклонено: '.$reason);
            $request->save();
        }

        return $request;
    }

    /**
     * Отменить заявку (черновик, согласованную или отклонённую).
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function cancel($request, $user_id = NULL)
    {
        $request = $this->_load($request);

        if ( ! in_array((string) $request->status, array('draft', 'submitted', 'approved', 'rejected'), TRUE))
        {
            throw new Exception_Erp403_Procurement(
                'Заявку :number нельзя отменить: состояние «:status»',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        return $this->_transition($request, 'cancelled', 'cancelled', 'Заявка отменена', $user_id);
    }

    /**
     * Связать заявку с заказом: заявка больше не ждёт закупщика.
     *
     * @param   int|Model_Erp403_Request    $request
     * @param   int                         $order_id
     * @param   int|null                    $user_id
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    public function mark_ordered($request, $order_id, $user_id = NULL)
    {
        $request = $this->_load($request);

        if ( ! $request->can_order())
        {
            throw new Exception_Erp403_Procurement(
                'Заявка :number не согласована: состояние «:status»',
                array(':number' => $request->number, ':status' => $request->status_label()),
                'invalid_transition'
            );
        }

        $before = $request->as_array();

        $request->status   = 'ordered';
        $request->order_id = (int) $order_id;
        $request->save();

        foreach ($this->lines($request->id) as $line)
        {
            if ($line->is_open())
            {
                $line->status = 'ordered';
                $line->save();
            }
        }

        $this->_journal($request, 'status', $before, $request->as_array(), 'Заявка попала в заказ #'.$order_id);

        Event::fire('erp403.request.ordered', $this->payload($request) + array('order_id' => (int) $order_id));

        return $request;
    }

    /**
     * Дефицит склада в терминах закупки (erp401 → erp403).
     *
     * `Service_Erp401_Stock::low_stock()` отдаёт строки остатков, а название и
     * код позиции лежат во вложенном `item` — не в плоских `name`/`code`.
     * Приводим это к одному виду здесь, чтобы страница, JSON API и MCP-инструмент
     * не расходились между собой и не зависели от формы чужого сервиса.
     *
     * @param   int     $limit
     * @return  array   строки с ключами item_id, name, code, uom_code,
     *                  warehouse_id, warehouse_name, qty, reserved, min_qty,
     *                  available, deficit_qty, suggested_qty, request_exists
     * @throws  Exception_Erp403_Procurement
     */
    public function low_stock_rows($limit = 200)
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp403_Procurement('Модуль склада (erp401) недоступен', NULL, 'dependency');
        }

        // Открытая заявка считается дублем только для своего склада: заказать ту
        // же позицию на другой склад — это не задвоение, а другая потребность.
        $open = array();

        foreach (DB::select('l.item_id', 'r.warehouse_id')
            ->from(array(self::LINES, 'l'))
            ->join(array(self::TABLE, 'r'), 'INNER')
            ->on('r.id', '=', 'l.request_id')
            ->where('l.status', '=', 'open')
            ->where('r.status', 'IN', array('draft', 'submitted', 'approved'))
            ->execute()
            ->as_array() as $row)
        {
            $open[(int) $row['item_id'].':'.(int) $row['warehouse_id']] = TRUE;
        }

        $rows = array();

        foreach (Service_Erp401_Stock::instance()->low_stock((int) $limit) as $row)
        {
            $item_id = (int) $row['item_id'];
            $item    = (isset($row['item']) AND is_array($row['item'])) ? $row['item'] : array();
            $qty     = (float) $row['qty'];
            $min_qty = (float) $row['min_qty'];
            $deficit = max($min_qty - $qty, 0);

            if ($deficit <= 0)
            {
                $deficit = $min_qty;
            }

            $rows[] = array(
                'item_id'        => $item_id,
                'name'           => isset($item['name']) ? (string) $item['name'] : 'Позиция #'.$item_id,
                'code'           => isset($item['code']) ? (string) $item['code'] : NULL,
                'uom_code'       => ! empty($row['uom_code']) ? (string) $row['uom_code'] : 'шт',
                'warehouse_id'   => (int) $row['warehouse_id'],
                'warehouse_name' => isset($row['warehouse_name']) ? (string) $row['warehouse_name'] : NULL,
                'qty'            => $qty,
                'reserved'       => (float) $row['reserved'],
                'min_qty'        => $min_qty,
                'available'      => isset($row['available']) ? (float) $row['available'] : round($qty - (float) $row['reserved'], 6),
                'deficit_qty'    => $deficit,
                // Запас 20 % и целое количество: закупать «3.4 штуки» нельзя.
                'suggested_qty'  => max(1, (int) ceil($deficit * 1.2)),
                'request_exists' => isset($open[$item_id.':'.(int) $row['warehouse_id']]),
            );
        }

        return $rows;
    }

    /**
     * Автоматические заявки по минимуму склада (erp401).
     *
     * Идём по позициям, у которых остаток опустился до минимума, и заводим по
     * одной заявке на склад: открытая заявка на ту же позицию не задваивается,
     * количество — до минимума с запасом 20 % (округление вверх до целого).
     *
     * @param   array       $options    warehouse_id, priority, need_days, dry_run
     * @param   int|null    $user_id
     * @return  array       created (заявки), skipped, items, total_low_stock
     * @throws  Exception_Erp403_Procurement
     */
    public function auto_from_low_stock(array $options = array(), $user_id = NULL)
    {
        $need_days = isset($options['need_days']) ? (int) $options['need_days'] : 14;
        $priority  = isset($options['priority']) ? (int) $options['priority'] : 2;
        $dry_run   = ! empty($options['dry_run']);

        $rows      = $this->low_stock_rows(200);
        $created   = array();
        $skipped   = array();
        $warehouse = NULL;

        foreach ($rows as $row)
        {
            if ( ! empty($options['warehouse_id']) AND (int) $row['warehouse_id'] !== (int) $options['warehouse_id'])
            {
                continue;
            }

            $item_id = (int) $row['item_id'];
            $qty     = (int) $row['suggested_qty'];

            if ( ! empty($row['request_exists']))
            {
                $skipped[] = array(
                    'item_id'  => $item_id,
                    'name'     => (string) $row['name'],
                    'reason'   => 'Уже есть открытая заявка на эту позицию',
                );
                continue;
            }

            $warehouse = (int) $row['warehouse_id'];

            if ($dry_run)
            {
                $created[] = array(
                    'item_id'      => $item_id,
                    'name'         => (string) $row['name'],
                    'qty'          => $qty,
                    'uom_code'     => (string) $row['uom_code'],
                    'warehouse_id' => $warehouse,
                );
                continue;
            }

            $request = $this->create(array(
                'status'       => 'submitted',
                'priority'     => $priority,
                'source'       => 'auto',
                'source_ref'   => 'erp401:low_stock',
                'warehouse_id' => $warehouse,
                'need_date'    => date('Y-m-d', strtotime('+'.$need_days.' days')),
                'note'         => 'Автоматическая заявка: остаток опустился до минимума ('.$row['qty'].' из '.$row['min_qty'].').',
                'lines'        => array(array(
                    'item_id'  => $item_id,
                    'name'     => (string) $row['name'],
                    'qty'      => $qty,
                    'uom_code' => (string) $row['uom_code'],
                )),
            ), $user_id);

            $created[] = $request;
        }

        if ($created AND ! $dry_run)
        {
            Event::fire('erp403.request.auto_created', array(
                'count' => count($created),
                'ids'   => array_map(function ($request) { return (int) $request->id; }, $created),
            ));
        }

        return array('created' => $created, 'skipped' => $skipped, 'total_low_stock' => count($rows));
    }

    // =========================================================================
    //  Сводки
    // =========================================================================

    /**
     * Сводка по заявкам для доски закупок.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'submitted')"), 'submitted'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'approved')"), 'approved'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'ordered')"), 'ordered'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'rejected')"), 'rejected'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'cancelled')"), 'cancelled'),
                array(DB::expr("COUNT(*) FILTER (WHERE need_date < CURRENT_DATE AND status IN ('draft', 'submitted', 'approved'))"), 'overdue')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        $total = array();

        foreach (DB::select('status', array(DB::expr('COUNT(*)'), 'total'))
            ->from(self::TABLE)
            ->group_by('status')
            ->execute()
            ->as_array() as $status_row)
        {
            $total[(string) $status_row['status']] = (int) $status_row['total'];
        }

        return array(
            'total'     => (int) $row['total'],
            'draft'     => (int) $row['draft'],
            'submitted' => (int) $row['submitted'],
            'approved'  => (int) $row['approved'],
            'ordered'   => (int) $row['ordered'],
            'rejected'  => (int) $row['rejected'],
            'cancelled' => (int) $row['cancelled'],
            'overdue'   => (int) $row['overdue'],
            'by_status' => $total,
        );
    }

    /**
     * Сумма заявок в состоянии «согласована» — сколько денег ждёт заказа.
     *
     * @return  float
     */
    public function approved_amount()
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(l.qty * l.price), 0)'), 'amount'))
            ->from(array(self::LINES, 'l'))
            ->join(array(self::TABLE, 'r'), 'INNER')
            ->on('r.id', '=', 'l.request_id')
            ->where('r.status', '=', 'approved')
            ->execute()
            ->current();

        return round((float) $row['amount'], 2);
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Номер заявки из последовательности (ЗК-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ЗК-%s-%06d', date('Y'), (int) $row['n']);
    }

    /**
     * Текст для поиска (в PHP: кластер в локали C не складывает регистр кириллицы).
     *
     * @param   Model_Erp403_Request    $request
     * @return  string
     */
    public function search_text(Model_Erp403_Request $request)
    {
        $names = array();

        foreach ($this->lines($request->id) as $line)
        {
            $names[] = (string) $line->name;
        }

        return mb_substr($this->normalize($request->number.' '.$request->note.' '.implode(' ', $names)), 0, 500);
    }

    /**
     * Привести строку к нижнему регистру для поиска.
     *
     * @param   string  $value
     * @return  string
     */
    public function normalize($value)
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * Склад по умолчанию: склад компонентов, иначе первый активный.
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
            foreach (Service_Erp401_Stock::instance()->warehouses(TRUE) as $warehouse)
            {
                if (mb_stripos((string) $warehouse->name, 'компонент') !== FALSE)
                {
                    return (int) $warehouse->id;
                }
            }

            $first = Service_Erp401_Stock::instance()->warehouses(TRUE);

            if ($first)
            {
                $warehouse = reset($first);

                return (int) $warehouse->id;
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: склад недоступен — :error', array(':error' => $e->getMessage()));
        }

        return NULL;
    }

    /**
     * Наименование позиции номенклатуры (реестр erp227).
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
     * Последняя известная цена по номенклатуре (и поставщику, если указан).
     *
     * @param   int         $item_id
     * @param   int|null    $supplier_id
     * @return  float
     */
    public function last_price($item_id, $supplier_id = NULL)
    {
        $query = DB::select('price')
            ->from('erp_403_prices')
            ->where('item_id', '=', (int) $item_id);

        if ($supplier_id)
        {
            $query->where('supplier_id', '=', (int) $supplier_id);
        }

        $row = $query->order_by('dated_at', 'DESC')->order_by('id', 'DESC')->limit(1)->execute()->current();

        return $row ? round((float) $row['price'], 6) : 0.0;
    }

    /**
     * Позиции заявки в виде массивов (для API и MCP).
     *
     * @param   int     $request_id
     * @return  array
     */
    public function line_rows($request_id)
    {
        $rows = array();

        foreach ($this->lines($request_id) as $line)
        {
            $rows[] = array(
                'id'          => (int) $line->id,
                'line_no'     => (int) $line->line_no,
                'item_id'     => $line->item_id ? (int) $line->item_id : NULL,
                'name'        => (string) $line->name,
                'code'        => $line->code ? (string) $line->code : NULL,
                'qty'         => (float) $line->qty,
                'uom_code'    => (string) $line->uom_code,
                'need_date'   => $line->need_date ? (string) $line->need_date : NULL,
                'supplier_id' => $line->supplier_id ? (int) $line->supplier_id : NULL,
                'price'       => (float) $line->price,
                'amount'      => $line->amount(),
                'status'      => (string) $line->status,
            );
        }

        return $rows;
    }

    /**
     * Компактный вид заявки (для событий, API и MCP).
     *
     * @param   Model_Erp403_Request    $request
     * @return  array
     */
    public function payload(Model_Erp403_Request $request)
    {
        return array(
            'id'           => (int) $request->id,
            'uuid'         => (string) $request->uuid,
            'number'       => (string) $request->number,
            'status'       => (string) $request->status,
            'priority'     => (int) $request->priority,
            'source'       => (string) $request->source,
            'source_ref'   => $request->source_ref ? (string) $request->source_ref : NULL,
            'warehouse_id' => $request->warehouse_id ? (int) $request->warehouse_id : NULL,
            'need_date'    => $request->need_date ? (string) $request->need_date : NULL,
            'lines'        => count($this->lines($request->id)),
        );
    }

    /**
     * Заявка или исключение.
     *
     * @param   int|Model_Erp403_Request    $request
     * @return  Model_Erp403_Request
     * @throws  Exception_Erp403_Procurement
     */
    protected function _load($request)
    {
        $request = ($request instanceof Model_Erp403_Request) ? $request : $this->get($request);

        if ( ! $request)
        {
            throw new Exception_Erp403_Procurement('Заявка не найдена', NULL, 'not_found');
        }

        return $request;
    }

    /**
     * Смена состояния заявки с журналом и событием.
     *
     * @param   Model_Erp403_Request    $request
     * @param   string                  $status
     * @param   string                  $event
     * @param   string                  $message
     * @param   int|null                $user_id
     * @return  Model_Erp403_Request
     */
    protected function _transition(Model_Erp403_Request $request, $status, $event, $message, $user_id = NULL)
    {
        $before = $request->as_array();

        $request->status = (string) $status;

        if ($status === 'approved')
        {
            $request->approved_by = $user_id ? (int) $user_id : NULL;
            $request->approved_at = date('Y-m-d H:i:s');
        }

        $request->save();

        $this->_journal($request, 'status', $before, $request->as_array(), $message);

        Event::fire('erp403.request.'.$event, $this->payload($request));

        return $request;
    }

    /**
     * Есть ли открытая заявка на эту позицию (склад учитывается).
     *
     * @param   int     $item_id
     * @param   int     $warehouse_id
     * @return  bool
     */
    protected function _has_open_line($item_id, $warehouse_id)
    {
        $row = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from(array(self::LINES, 'l'))
            ->join(array(self::TABLE, 'r'), 'INNER')
            ->on('r.id', '=', 'l.request_id')
            ->where('l.item_id', '=', (int) $item_id)
            ->where('l.status', '=', 'open')
            ->where('r.status', 'IN', array('draft', 'submitted', 'approved'))
            ->execute()
            ->current();

        return ((int) $row['total'] > 0);
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp403_Request|null   $request
     * @param   string                      $action
     * @param   array                       $before
     * @param   array                       $after
     * @param   string|null                 $message
     * @param   Model_Erp403_RequestLine|null $line
     * @return  void
     */
    protected function _journal($request, $action, array $before, array $after, $message = NULL, $line = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $reference = $line ? (string) $line->id : ($request ? (string) $request->uuid : '');

        try
        {
            Service_Erp119_Journal::instance()->change($line ? 'request_line' : 'request', $reference, $action, $before, $after, array(
                'module'       => 'erp403',
                'entity_label' => $request ? (string) $request->number : NULL,
                'message'      => $message,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
