<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис складского учёта (модуль erp401_wms).
 *
 * Единственная точка изменения остатков: все проводки идут через этот сервис,
 * он же ведёт журнал движений, держит транзакцию и публикует события.
 * Прямая работа с моделями Model_Erp401_Balance из других модулей запрещена.
 *
 * События (публикуются в шину erp208 и в общий Event):
 *  - erp401.warehouse_created
 *  - erp401.stock_received
 *  - erp401.stock_issued
 *  - erp401.stock_transferred
 *  - erp401.stock_adjusted
 *  - erp401.stock_low
 *  - erp401.reservation_created
 *  - erp401.reservation_released
 *  - erp401.reservation_consumed
 *
 * @package    Erp401
 * @category   Services
 */
class Service_Erp401_Stock
{
    /** @var Service_Erp401_Stock */
    protected static $_instance;

    /** @var array Кэш объектов реестра в рамках одного запроса */
    protected $_items = array();

    /** @var int Глубина вложенности транзакций */
    protected $_tx_depth = 0;

    /**
     * @return Service_Erp401_Stock
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
    // Склады
    // =========================================================================

    /**
     * Список складов.
     *
     * @param  bool $active_only Только активные
     * @return Database_Result|array
     */
    public function warehouses($active_only = TRUE)
    {
        $query = ORM::factory('Erp401_Warehouse');

        if ($active_only)
        {
            $query->where('is_active', '=', 1);
        }

        return $query->order_by('name', 'ASC')->find_all();
    }

    /**
     * Склад по ID.
     *
     * @param  int $id
     * @return Model_Erp401_Warehouse
     * @throws Exception_Erp401_Stock
     */
    public function warehouse($id)
    {
        $warehouse = ORM::factory('Erp401_Warehouse', (int) $id);

        if ( ! $warehouse->loaded())
        {
            throw new Exception_Erp401_Stock('Склад не найден', NULL, 'warehouse_not_found');
        }

        return $warehouse;
    }

    /**
     * Создать склад.
     *
     * @param  array $data code, name, type, address, description
     * @return Model_Erp401_Warehouse
     * @throws ORM_Validation_Exception
     */
    public function create_warehouse(array $data)
    {
        $warehouse = ORM::factory('Erp401_Warehouse');
        $warehouse->values($data, array('code', 'name', 'type', 'object_id', 'address', 'description'));
        $warehouse->is_active = 1;
        $warehouse->created_by = $this->_current_user_id();
        $warehouse->save();

        $this->_publish('erp401.warehouse_created', array(
            'warehouse_id' => $warehouse->id,
            'code'         => $warehouse->code,
            'name'         => $warehouse->name,
        ));

        return $warehouse;
    }

    /**
     * Обновить склад.
     *
     * @param  int   $id
     * @param  array $data
     * @return Model_Erp401_Warehouse
     */
    public function update_warehouse($id, array $data)
    {
        $warehouse = $this->warehouse($id);
        $warehouse->values($data, array('code', 'name', 'type', 'object_id', 'address', 'description', 'is_active'));
        $warehouse->save();

        return $warehouse;
    }

    /**
     * Деактивировать склад. Склад с ненулевыми остатками закрыть нельзя.
     *
     * @param  int $id
     * @return Model_Erp401_Warehouse
     * @throws Exception_Erp401_Stock
     */
    public function deactivate_warehouse($id)
    {
        $warehouse = $this->warehouse($id);

        $totals = $warehouse->totals();

        if ($totals['qty'] > 0)
        {
            throw new Exception_Erp401_Stock(
                'Нельзя закрыть склад с непустыми остатками (:positions поз.)',
                array(':positions' => $totals['positions']),
                'warehouse_not_empty'
            );
        }

        $warehouse->is_active = 0;
        $warehouse->save();

        return $warehouse;
    }

    // =========================================================================
    // Остатки (чтение)
    // =========================================================================

    /**
     * Остатки с фильтрами.
     *
     * @param  array $filters warehouse_id, item_id, low_only, search
     * @param  int   $limit
     * @param  int   $offset
     * @return array {rows: array, total: int}
     */
    public function balances(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);
        $query = DB::select('b.*', array('w.name', 'warehouse_name'), array('w.code', 'warehouse_code'))
            ->from(array('erp_401_balances', 'b'))
            ->join(array('erp_401_warehouses', 'w'), 'LEFT')
            ->on('w.id', '=', 'b.warehouse_id');

        if ( ! empty($filters['warehouse_id']))
        {
            $query->where('b.warehouse_id', '=', (int) $filters['warehouse_id']);
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('b.item_id', '=', (int) $filters['item_id']);
        }

        if ( ! empty($filters['low_only']))
        {
            $query->where('b.min_qty', '>', 0)
                  ->and_where('b.qty', '<=', DB::expr('b.min_qty'));
        }

        // PostgreSQL не разрешает COUNT(*) рядом с b.*/m.*/r.* без GROUP BY,
        // поэтому общее количество считаем оконной функцией в том же запросе.
        $rows = $query
            ->order_by('w.name', 'ASC')
            ->select(array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            $item = $this->item($row['item_id']);
            $row['item']      = $item;
            $row['available'] = round((float) $row['qty'] - (float) $row['reserved'], 6);
            // `&&`, а не `AND`: у `AND` приоритет ниже присваивания, и признак
            // «ниже минимума» был истинным для любой позиции с заданным минимумом.
            $row['is_low']    = ((float) $row['min_qty'] > 0) && ((float) $row['qty'] <= (float) $row['min_qty']);
            unset($row['total']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Строка остатка (без создания записи).
     *
     * @param  int $warehouse_id
     * @param  int $item_id
     * @return Model_Erp401_Balance
     */
    public function balance($warehouse_id, $item_id)
    {
        return ORM::factory('Erp401_Balance')
            ->where('warehouse_id', '=', (int) $warehouse_id)
            ->where('item_id', '=', (int) $item_id)
            ->find();
    }

    /**
     * Физический остаток на складе.
     *
     * @param  int $warehouse_id
     * @param  int $item_id
     * @return float
     */
    public function qty($warehouse_id, $item_id)
    {
        $balance = $this->balance($warehouse_id, $item_id);

        return $balance->loaded() ? (float) $balance->qty : 0.0;
    }

    /**
     * Свободный остаток (физический минус резервы).
     *
     * @param  int $warehouse_id
     * @param  int $item_id
     * @return float
     */
    public function available($warehouse_id, $item_id)
    {
        $balance = $this->balance($warehouse_id, $item_id);

        if ( ! $balance->loaded())
        {
            return 0.0;
        }

        return round((float) $balance->qty - (float) $balance->reserved, 6);
    }

    /**
     * Суммарный остаток позиции по всем складам.
     *
     * @param  int $item_id
     * @return float
     */
    public function total_qty($item_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty'))
            ->from('erp_401_balances')
            ->where('item_id', '=', (int) $item_id)
            ->execute()
            ->current();

        return (float) $row['qty'];
    }

    /**
     * Позиции, у которых остаток опустился до минимума.
     *
     * @param  int $limit
     * @return array
     */
    public function low_stock($limit = 20)
    {
        $rows = DB::select('b.*', array('w.name', 'warehouse_name'))
            ->from(array('erp_401_balances', 'b'))
            ->join(array('erp_401_warehouses', 'w'), 'LEFT')
            ->on('w.id', '=', 'b.warehouse_id')
            ->where('b.min_qty', '>', 0)
            ->and_where('b.qty', '<=', DB::expr('b.min_qty'))
            ->order_by('b.qty', 'ASC')
            ->limit($limit)
            ->execute()
            ->as_array();

        foreach ($rows as &$row)
        {
            $row['item']      = $this->item($row['item_id']);
            $row['available'] = round((float) $row['qty'] - (float) $row['reserved'], 6);
        }
        unset($row);

        return $rows;
    }

    // =========================================================================
    // Движения (запись)
    // =========================================================================

    /**
     * Приход на склад.
     *
     * @param  int   $warehouse_id
     * @param  int   $item_id      ID объекта реестра erp227
     * @param  float $qty
     * @param  array $opt          uom_code, doc_type, doc_id, note, performed_at
     * @return Model_Erp401_Movement
     */
    public function receive($warehouse_id, $item_id, $qty, array $opt = array())
    {
        $qty = $this->_normalize_qty($qty);

        $this->_begin();
        try
        {
            $balance  = $this->_lock_balance($warehouse_id, $item_id, $opt);
            $before   = (float) $balance['qty'];
            $after    = round($before + $qty, 6);

            $this->_set_qty($balance['id'], $before + $qty);

            $movement = $this->_write_movement(array(
                'type'           => 'receipt',
                'item_id'        => (int) $item_id,
                'uom_code'       => $balance['uom_code'],
                'warehouse_id'   => (int) $warehouse_id,
                'warehouse_to_id'=> NULL,
                'qty'            => $qty,
                'balance_before' => $before,
                'balance_after'  => $after,
            ), $opt);

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish('erp401.stock_received', $this->_payload($movement));

        return $movement;
    }

    /**
     * Расход со склада. Нельзя уйти в минус.
     *
     * @param  int   $warehouse_id
     * @param  int   $item_id
     * @param  float $qty
     * @param  array $opt
     * @return Model_Erp401_Movement
     * @throws Exception_Erp401_Stock
     */
    public function issue($warehouse_id, $item_id, $qty, array $opt = array())
    {
        $qty = $this->_normalize_qty($qty);

        $this->_begin();
        try
        {
            $balance = $this->_lock_balance($warehouse_id, $item_id, $opt);
            $before  = (float) $balance['qty'];
            $available = round($before - (float) $balance['reserved'], 6);
            $after   = round($before - $qty, 6);

            if ($qty > $available)
            {
                throw new Exception_Erp401_Stock(
                    'Недостаточно остатка: на складе :have :uom, требуется :need',
                    array(
                        ':have' => $this->_num($available),
                        ':need' => $this->_num($qty),
                        ':uom'  => $balance['uom_code'],
                    ),
                    'insufficient_stock'
                );
            }

            $this->_set_qty($balance['id'], $after);

            $movement = $this->_write_movement(array(
                'type'           => 'issue',
                'item_id'        => (int) $item_id,
                'uom_code'       => $balance['uom_code'],
                'warehouse_id'   => (int) $warehouse_id,
                'warehouse_to_id'=> NULL,
                'qty'            => $qty,
                'balance_before' => $before,
                'balance_after'  => $after,
            ), $opt);

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish('erp401.stock_issued', $this->_payload($movement));
        $this->_check_low($balance, $after);

        return $movement;
    }

    /**
     * Перемещение между складами: расход на складе-источнике + приход на приёмнике.
     *
     * @param  int   $from_id
     * @param  int   $to_id
     * @param  int   $item_id
     * @param  float $qty
     * @param  array $opt
     * @return Model_Erp401_Movement
     * @throws Exception_Erp401_Stock
     */
    public function transfer($from_id, $to_id, $item_id, $qty, array $opt = array())
    {
        $qty = $this->_normalize_qty($qty);

        if ((int) $from_id === (int) $to_id)
        {
            throw new Exception_Erp401_Stock('Склад-получатель совпадает со складом-источником', NULL, 'same_warehouse');
        }

        $this->warehouse($from_id);
        $this->warehouse($to_id);

        $this->_begin();
        try
        {
            // Transfers can run in opposite directions concurrently. Acquire
            // both balance locks in a stable order to avoid a lock cycle.
            $warehouse_ids = array((int) $from_id, (int) $to_id);
            sort($warehouse_ids, SORT_NUMERIC);
            $locked_balances = array();
            foreach ($warehouse_ids as $lock_warehouse_id)
            {
                $locked_balances[$lock_warehouse_id] = $this->_lock_balance($lock_warehouse_id, $item_id, $opt);
            }
            $balance_from = $locked_balances[(int) $from_id];
            $balance_to   = $locked_balances[(int) $to_id];
            $before       = (float) $balance_from['qty'];
            $available    = round($before - (float) $balance_from['reserved'], 6);
            $after        = round($before - $qty, 6);

            if ($qty > $available)
            {
                throw new Exception_Erp401_Stock(
                    'Недостаточно остатка для перемещения: на складе :have :uom, требуется :need',
                    array(
                        ':have' => $this->_num($available),
                        ':need' => $this->_num($qty),
                        ':uom'  => $balance_from['uom_code'],
                    ),
                    'insufficient_stock'
                );
            }

            $this->_set_qty($balance_from['id'], $after);

            // Приёмник: приход (уже заблокирован выше)
            $this->_set_qty($balance_to['id'], round((float) $balance_to['qty'] + $qty, 6));

            $movement = $this->_write_movement(array(
                'type'           => 'transfer',
                'item_id'        => (int) $item_id,
                'uom_code'       => $balance_from['uom_code'],
                'warehouse_id'   => (int) $from_id,
                'warehouse_to_id'=> (int) $to_id,
                'qty'            => $qty,
                'balance_before' => $before,
                'balance_after'  => $after,
            ), $opt);

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish('erp401.stock_transferred', $this->_payload($movement));
        $this->_check_low($balance_from, $after);

        return $movement;
    }

    /**
     * Корректировка остатка до точного значения (по результатам инвентаризации).
     *
     * @param  int    $warehouse_id
     * @param  int    $item_id
     * @param  float  $new_qty
     * @param  array  $opt note — причина корректировки, обязательна для журнала
     * @return Model_Erp401_Movement
     */
    public function adjust($warehouse_id, $item_id, $new_qty, array $opt = array())
    {
        $new_qty = round((float) $new_qty, 6);

        if ($new_qty < 0)
        {
            throw new Exception_Erp401_Stock('Остаток не может быть отрицательным', NULL, 'invalid_qty');
        }

        $this->_begin();
        try
        {
            $balance = $this->_lock_balance($warehouse_id, $item_id, $opt);
            $before  = (float) $balance['qty'];
            if ($new_qty < round((float) $balance['reserved'], 6))
            {
                throw new Exception_Erp401_Stock('Остаток не может быть меньше зарезервированного количества', NULL, 'below_reserved');
            }
            $delta   = round($new_qty - $before, 6);

            // Для журнала храним абсолютную величину изменения
            $qty = abs($delta);

            if ($qty == 0)
            {
                throw new Exception_Erp401_Stock('Остаток уже равен указанному значению', NULL, 'nothing_to_adjust');
            }

            $this->_set_qty($balance['id'], $new_qty);

            $movement = $this->_write_movement(array(
                'type'           => 'adjust',
                'item_id'        => (int) $item_id,
                'uom_code'       => $balance['uom_code'],
                'warehouse_id'   => (int) $warehouse_id,
                'warehouse_to_id'=> NULL,
                'qty'            => $qty,
                'balance_before' => $before,
                'balance_after'  => $new_qty,
            ), $opt + array('note' => isset($opt['note']) ? $opt['note'] : 'Корректировка остатка'));

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish('erp401.stock_adjusted', $this->_payload($movement) + array('delta' => $delta));
        $this->_check_low($balance, $new_qty);

        return $movement;
    }

    /**
     * Журнал движений.
     *
     * @param  array $filters type, item_id, warehouse_id, doc_type, date_from, date_to
     * @param  int   $limit
     * @param  int   $offset
     * @return array {rows: array, total: int}
     */
    public function movements(array $filters = array(), $limit = 50, $offset = 0)
    {
        $query = DB::select('m.*', array('w.name', 'warehouse_name'), array('wt.name', 'warehouse_to_name'))
            ->from(array('erp_401_movements', 'm'))
            ->join(array('erp_401_warehouses', 'w'), 'LEFT')
            ->on('w.id', '=', 'm.warehouse_id')
            ->join(array('erp_401_warehouses', 'wt'), 'LEFT')
            ->on('wt.id', '=', 'm.warehouse_to_id');

        if ( ! empty($filters['type']) AND in_array($filters['type'], array('receipt', 'issue', 'transfer', 'adjust')))
        {
            $query->where('m.type', '=', $filters['type']);
        }

        if ( ! empty($filters['warehouse_id']))
        {
            $query->and_where_open()
                  ->where('m.warehouse_id', '=', (int) $filters['warehouse_id'])
                  ->or_where('m.warehouse_to_id', '=', (int) $filters['warehouse_id'])
                  ->and_where_close();
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('m.item_id', '=', (int) $filters['item_id']);
        }

        if ( ! empty($filters['doc_type']))
        {
            $query->where('m.doc_type', '=', $filters['doc_type']);
        }

        if ( ! empty($filters['date_from']))
        {
            $query->where('m.performed_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if ( ! empty($filters['date_to']))
        {
            $query->where('m.performed_at', '<=', $filters['date_to'].' 23:59:59');
        }

        // PostgreSQL не разрешает COUNT(*) рядом с b.*/m.*/r.* без GROUP BY,
        // поэтому общее количество считаем оконной функцией в том же запросе.
        $rows = $query
            ->order_by('m.performed_at', 'DESC')
            ->select(array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            $row['item'] = $this->item($row['item_id']);
            unset($row['total']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    // =========================================================================
    // Резервы
    // =========================================================================

    /**
     * Зарезервировать свободный остаток.
     *
     * @param  int   $warehouse_id
     * @param  int   $item_id
     * @param  float $qty
     * @param  array $opt ref_type, ref_id, expires_at
     * @return Model_Erp401_Reservation
     * @throws Exception_Erp401_Stock
     */
    public function reserve($warehouse_id, $item_id, $qty, array $opt = array())
    {
        $qty = $this->_normalize_qty($qty);

        $this->_begin();
        try
        {
            $balance = $this->_lock_balance($warehouse_id, $item_id, $opt);
            $free    = round((float) $balance['qty'] - (float) $balance['reserved'], 6);
            if ($free < 0)
            {
                throw new Exception_Erp401_Stock('Резервы превышают физический остаток', NULL, 'reservation_balance_mismatch');
            }

            if ($qty > $free)
            {
                throw new Exception_Erp401_Stock(
                    'Недостаточно свободного остатка: доступно :free :uom, требуется :need',
                    array(':free' => $this->_num($free), ':need' => $this->_num($qty), ':uom' => $balance['uom_code']),
                    'insufficient_stock'
                );
            }

            DB::update('erp_401_balances')
                ->set(array('reserved' => round((float) $balance['reserved'] + $qty, 6)))
                ->where('id', '=', $balance['id'])
                ->execute();

            $reservation = ORM::factory('Erp401_Reservation');
            $reservation->values(array(
                'warehouse_id' => (int) $warehouse_id,
                'item_id'      => (int) $item_id,
                'qty'          => $qty,
                'status'       => 'active',
                'ref_type'     => isset($opt['ref_type']) ? $opt['ref_type'] : NULL,
                'ref_id'       => isset($opt['ref_id']) ? $opt['ref_id'] : NULL,
                'expires_at'   => isset($opt['expires_at']) ? $opt['expires_at'] : NULL,
                'created_by'   => $this->_current_user_id(),
            ));
            $reservation->save();

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish('erp401.reservation_created', array(
            'reservation_id' => $reservation->id,
            'warehouse_id'   => $reservation->warehouse_id,
            'item_id'        => $reservation->item_id,
            'qty'            => (float) $reservation->qty,
            'ref_type'       => $reservation->ref_type,
            'ref_id'         => $reservation->ref_id,
        ));

        return $reservation;
    }

    /**
     * Снять резерв (полностью или частично).
     *
     * @param  int        $id
     * @param  float|null $qty NULL — снять весь резерв
     * @return Model_Erp401_Reservation
     */
    public function release_reservation($id, $qty = NULL)
    {
        return $this->_close_reservation($id, $qty, 'released', 'erp401.reservation_released');
    }

    /**
     * Списать резерв: превратить его в расход со склада.
     *
     * @param  int        $id
     * @param  float|null $qty NULL — списать весь резерв
     * @return Model_Erp401_Reservation
     */
    public function consume_reservation($id, $qty = NULL)
    {
        return $this->_close_reservation($id, $qty, 'consumed', 'erp401.reservation_consumed');
    }

    /**
     * Список резервов.
     *
     * @param  array $filters warehouse_id, item_id, status, ref_type, ref_id
     * @param  int   $limit
     * @param  int   $offset
     * @return array {rows: array, total: int}
     */
    public function reservations(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);
        $query = DB::select('r.*', array('w.name', 'warehouse_name'))
            ->from(array('erp_401_reservations', 'r'))
            ->join(array('erp_401_warehouses', 'w'), 'LEFT')
            ->on('w.id', '=', 'r.warehouse_id');

        foreach (array('warehouse_id', 'item_id', 'ref_id') as $key)
        {
            if ( ! empty($filters[$key]))
            {
                $query->where('r.'.$key, '=', (int) $filters[$key]);
            }
        }

        if ( ! empty($filters['status']))
        {
            $query->where('r.status', '=', $filters['status']);
        }

        if ( ! empty($filters['ref_type']))
        {
            $query->where('r.ref_type', '=', $filters['ref_type']);
        }

        // PostgreSQL не разрешает COUNT(*) рядом с b.*/m.*/r.* без GROUP BY,
        // поэтому общее количество считаем оконной функцией в том же запросе.
        $rows = $query
            ->order_by('r.created_at', 'DESC')
            ->select(array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            $row['item'] = $this->item($row['item_id']);
            unset($row['total']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    // =========================================================================
    // Номенклатура (только через реестр erp227)
    // =========================================================================

    /**
     * Данные позиции номенклатуры из реестра erp227.
     *
     * @param  int $item_id
     * @return array {id, code, name}
     */
    public function item($item_id)
    {
        $item_id = (int) $item_id;

        if (array_key_exists($item_id, $this->_items))
        {
            return $this->_items[$item_id];
        }

        $data = array('id' => $item_id, 'code' => '#'.$item_id, 'name' => 'Объект удалён из реестра');

        if ($item_id > 0 AND class_exists('Service_Erp227_Registry'))
        {
            try
            {
                $object = Service_Erp227_Registry::instance()->get_by_id($item_id);

                if ($object AND $object->loaded())
                {
                    $data = array(
                        'id'   => $item_id,
                        'code' => (string) $object->code,
                        'name' => (string) $object->name,
                    );
                }
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp401: не удалось получить объект реестра :id — :error',
                    array(':id' => $item_id, ':error' => $e->getMessage()));
            }
        }

        $this->_items[$item_id] = $data;

        return $data;
    }

    /**
     * Данные по нескольким позициям одним махом.
     *
     * @param  array $item_ids
     * @return array item_id => {id, code, name}
     */
    public function items(array $item_ids)
    {
        $result = array();

        foreach (array_unique(array_filter(array_map('intval', $item_ids))) as $id)
        {
            $result[$id] = $this->item($id);
        }

        return $result;
    }

    /**
     * Каталог номенклатуры для выпадающих списков.
     *
     * Берём корневые объекты реестра erp227 через его сервис — прямых запросов
     * к erp_227_objects из модуля нет (см. docs/ARCHITECTURE.md, слой Entities).
     *
     * @param  int $limit
     * @return array of {id, code, name}
     */
    public function catalog($limit = 500)
    {
        if ( ! class_exists('Service_Erp227_Registry'))
        {
            return array();
        }

        try
        {
            $objects = Service_Erp227_Registry::instance()->get_children(NULL, NULL);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp401: не удалось получить каталог реестра — :error',
                array(':error' => $e->getMessage()));

            return array();
        }

        $result = array();

        foreach ($objects as $object)
        {
            $result[] = array(
                'id'   => (int) $object->id,
                'code' => (string) $object->code,
                'name' => (string) $object->name,
            );

            if (count($result) >= $limit)
            {
                break;
            }
        }

        return $result;
    }

    /**
     * Поиск номенклатуры по коду/наименованию (полнотекст + вектор реестра erp227).
     *
     * @param  string $term
     * @param  int    $limit
     * @return array of {id, code, name}
     */
    public function search_items($term, $limit = 20)
    {
        $term = trim((string) $term);

        if ($term === '' OR ! class_exists('Service_Erp227_Search'))
        {
            return array();
        }

        try
        {
            $found = Service_Erp227_Search::instance()->execute($term, array(), $limit);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp401: поиск реестра не выполнен — :error',
                array(':error' => $e->getMessage()));

            return array();
        }

        $rows    = isset($found['results']) ? $found['results'] : array();
        $result  = array();

        foreach ($rows as $row)
        {
            // Сервис поиска отдаёт найденные объекты как ORM-модели реестра
            // (Erp227_Search::execute → results), но раньше здесь ожидались
            // массивы: `$row['id']` на модели ронял поиск фатальной ошибкой
            // «Cannot use object of type Model_Erp227_Object as array». Именно
            // на этом ломался подбор состава из техкарты: производство не
            // находило материал в номенклатуре и оставляло заказ без состава
            // (см. docs/IMPROVEMENTS.md, B19). Поддерживаем обе формы ответа —
            // контракт сервиса поиска не меняем.
            $id   = (int) $this->row_value($row, 'id');
            $code = $this->row_value($row, 'code');
            $name = $this->row_value($row, 'name');

            if ($id <= 0)
            {
                continue;
            }

            $known = $this->item($id);

            $result[] = array(
                'id'   => $id,
                'code' => ($code !== NULL AND $code !== '') ? (string) $code : $known['code'],
                'name' => ($name !== NULL AND $name !== '') ? (string) $name : $known['name'],
            );

            // Запоминаем в кэш, чтобы список остатков не дёргал реестр повторно
            if ($code !== NULL AND $code !== '')
            {
                $this->_items[$id] = array('id' => $id, 'code' => (string) $code, 'name' => (string) $name);
            }
        }

        return $result;
    }

    /**
     * Значение поля строки результата: строка может быть массивом или ORM-моделью.
     *
     * @param   mixed   $row
     * @param   string  $field
     * @return  mixed
     */
    protected function row_value($row, $field)
    {
        if (is_array($row))
        {
            return isset($row[$field]) ? $row[$field] : NULL;
        }

        if (is_object($row))
        {
            return isset($row->$field) ? $row->$field : NULL;
        }

        return NULL;
    }

    /**
     * Справочник единиц измерения (через сервис реестра erp227).
     *
     * @return array code => «Наименование (code)»
     */
    public function units()
    {
        if (class_exists('Service_Erp227_Registry')
            AND method_exists('Service_Erp227_Registry', 'get_units'))
        {
            try
            {
                $units = Service_Erp227_Registry::instance()->get_units(TRUE);

                if ( ! empty($units))
                {
                    return $units;
                }
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp401: не удалось получить единицы измерения — :error',
                    array(':error' => $e->getMessage()));
            }
        }

        // Резерв на случай, если реестр недоступен
        return array('pcs' => 'Штука (pcs)');
    }

    // =========================================================================
    // Внутреннее
    // =========================================================================

    /**
     * Закрытие резерва (снятие или списание).
     *
     * @param  int    $id
     * @param  float  $qty
     * @param  string $status
     * @param  string $event
     * @return Model_Erp401_Reservation
     * @throws Exception_Erp401_Stock
     */
    protected function _close_reservation($id, $qty, $status, $event)
    {
        $this->_begin();
        try
        {
            // Lock and re-read the reservation inside the transaction. Without
            // this lock two concurrent consume/release requests can both act on
            // the same stale ACTIVE ORM object.
            $locked = DB::query(Database::SELECT,
                'SELECT id FROM erp_401_reservations WHERE id = :id FOR UPDATE')
                ->param(':id', (int) $id)
                ->execute()
                ->current();

            if ( ! $locked)
            {
                throw new Exception_Erp401_Stock('Резерв не найден', NULL, 'reservation_not_found');
            }

            $reservation = ORM::factory('Erp401_Reservation', (int) $id);
            if ( ! $reservation->loaded())
            {
                throw new Exception_Erp401_Stock('Резерв не найден', NULL, 'reservation_not_found');
            }
            if ($reservation->status !== 'active')
            {
                throw new Exception_Erp401_Stock('Резерв уже закрыт', NULL, 'reservation_closed');
            }

            $remaining_before = (float) $reservation->qty;
            $closed_qty = ($qty === NULL) ? $remaining_before : round((float) $qty, 6);
            if ($closed_qty <= 0 OR $closed_qty > $remaining_before)
            {
                throw new Exception_Erp401_Stock(
                    ($closed_qty <= 0) ? 'Количество должно быть больше нуля' : 'Нельзя закрыть больше, чем зарезервировано',
                    NULL,
                    'invalid_qty'
                );
            }

            $balance = $this->_lock_balance($reservation->warehouse_id, $reservation->item_id);
            if ($closed_qty > (float) $balance['reserved'])
            {
                throw new Exception_Erp401_Stock('Сумма резерва превышает зарезервированный остаток', NULL, 'reservation_balance_mismatch');
            }

            if ($status === 'consumed')
            {
                $new_qty = round((float) $balance['qty'] - $closed_qty, 6);
                if ($new_qty < 0)
                {
                    throw new Exception_Erp401_Stock('Остаток на складе меньше резерва', NULL, 'insufficient_stock');
                }

                $this->_set_qty($balance['id'], $new_qty);
                $this->_write_movement(array(
                    'type' => 'issue',
                    'item_id' => (int) $reservation->item_id,
                    'uom_code' => $balance['uom_code'],
                    'warehouse_id' => (int) $reservation->warehouse_id,
                    'warehouse_to_id' => NULL,
                    'qty' => $closed_qty,
                    'balance_before' => (float) $balance['qty'],
                    'balance_after' => $new_qty,
                ), array(
                    'doc_type' => $reservation->ref_type ? $reservation->ref_type : 'reservation',
                    'doc_id' => $reservation->ref_id,
                    'note' => 'Списание по резерву #'.$reservation->id,
                ));
            }

            $new_reserved = round((float) $balance['reserved'] - $closed_qty, 6);
            DB::update('erp_401_balances')
                ->set(array('reserved' => $new_reserved))
                ->where('id', '=', $balance['id'])
                ->execute();

            $remaining_after = round($remaining_before - $closed_qty, 6);
            // A partial close leaves the remainder active. Only a fully
            // exhausted reservation is terminal; this keeps the aggregate
            // reserved quantity traceable to active reservation rows.
            $reservation->qty = ($remaining_after > 0) ? $remaining_after : $remaining_before;
            if ($remaining_after <= 0)
            {
                $reservation->status = $status;
                $reservation->released_at = date('Y-m-d H:i:s');
            }
            $reservation->save();

            $this->_commit();
        }
        catch (Exception $e)
        {
            $this->_rollback();
            throw $e;
        }

        $this->_publish($event, array(
            'reservation_id' => $reservation->id,
            'warehouse_id' => $reservation->warehouse_id,
            'item_id' => $reservation->item_id,
            'qty' => $closed_qty,
            'remaining_qty' => max(0, $remaining_after),
            'status' => $reservation->status,
            'ref_type' => $reservation->ref_type,
            'ref_id' => $reservation->ref_id,
        ));

        return $reservation;
    }

    /**
     * Получить строку остатка с блокировкой строки (SELECT ... FOR UPDATE).
     * Если строки нет — создать нулевую внутри текущей транзакции.
     *
     * @param  int   $warehouse_id
     * @param  int   $item_id
     * @param  array $opt          может содержать uom_code
     * @return array
     */
    protected function _lock_balance($warehouse_id, $item_id, array $opt = array())
    {
        $warehouse_id = (int) $warehouse_id;
        $item_id      = (int) $item_id;

        $this->warehouse($warehouse_id);

        $row = DB::query(Database::SELECT,
            'SELECT * FROM erp_401_balances WHERE warehouse_id = :w AND item_id = :i FOR UPDATE')
            ->param(':w', $warehouse_id)
            ->param(':i', $item_id)
            ->execute()
            ->current();

        if ( ! $row)
        {
            $uom = isset($opt['uom_code']) ? $opt['uom_code'] : 'pcs';

            // Two first-time operations may both observe no balance row.
            // The unique key plus ON CONFLICT makes row initialization safe;
            // the subsequent SELECT obtains the lock for either transaction.
            DB::query(Database::INSERT,
                'INSERT INTO erp_401_balances (warehouse_id, item_id, uom_code, qty, reserved, min_qty) '
                .'VALUES (:w, :i, :uom, 0, 0, 0) '
                .'ON CONFLICT (warehouse_id, item_id) DO NOTHING')
                ->param(':w', $warehouse_id)
                ->param(':i', $item_id)
                ->param(':uom', $uom)
                ->execute();

            $row = DB::query(Database::SELECT,
                'SELECT * FROM erp_401_balances WHERE warehouse_id = :w AND item_id = :i FOR UPDATE')
                ->param(':w', $warehouse_id)
                ->param(':i', $item_id)
                ->execute()
                ->current();
        }

        return $row;
    }

    /**
     * Установить физический остаток строки.
     *
     * @param int   $balance_id
     * @param float $qty
     */
    protected function _set_qty($balance_id, $qty)
    {
        DB::update('erp_401_balances')
            ->set(array('qty' => round((float) $qty, 6), 'updated_at' => date('Y-m-d H:i:s')))
            ->where('id', '=', (int) $balance_id)
            ->execute();
    }

    /**
     * Записать движение в журнал.
     *
     * @param  array $data
     * @param  array $opt doc_type, doc_id, note, performed_at
     * @return Model_Erp401_Movement
     */
    protected function _write_movement(array $data, array $opt = array())
    {
        $movement = ORM::factory('Erp401_Movement');

        $movement->type   = $data['type'];
        $movement->status = 'posted';
        $movement->values(array(
            'item_id'         => $data['item_id'],
            'uom_code'        => $data['uom_code'],
            'warehouse_id'    => $data['warehouse_id'],
            'warehouse_to_id' => $data['warehouse_to_id'],
            'qty'             => $data['qty'],
            'balance_before'  => $data['balance_before'],
            'balance_after'   => $data['balance_after'],
            'doc_type'        => isset($opt['doc_type']) ? $opt['doc_type'] : 'manual',
            'doc_id'          => isset($opt['doc_id']) ? $opt['doc_id'] : NULL,
            'note'            => isset($opt['note']) ? $opt['note'] : NULL,
            'performed_at'    => isset($opt['performed_at']) ? $opt['performed_at'] : date('Y-m-d H:i:s'),
            'created_by'      => $this->_current_user_id(),
        ));

        $movement->save();

        return $movement;
    }

    /**
     * Проверить остаток на минимум и опубликовать erp401.stock_low.
     *
     * @param array $balance
     * @param float $after
     */
    protected function _check_low($balance, $after)
    {
        $min = (float) $balance['min_qty'];

        if ($min > 0 AND (float) $after <= $min)
        {
            $this->_publish('erp401.stock_low', array(
                'warehouse_id' => (int) $balance['warehouse_id'],
                'item_id'      => (int) $balance['item_id'],
                'qty'          => (float) $after,
                'min_qty'      => $min,
                'uom_code'     => $balance['uom_code'],
            ));
        }
    }

    /**
     * Полезная нагрузка события по движению.
     *
     * @param  Model_Erp401_Movement $movement
     * @return array
     */
    protected function _payload(Model_Erp401_Movement $movement)
    {
        return array(
            'movement_id'    => $movement->id,
            'type'           => $movement->type,
            'warehouse_id'   => (int) $movement->warehouse_id,
            'warehouse_to_id'=> $movement->warehouse_to_id ? (int) $movement->warehouse_to_id : NULL,
            'item_id'        => (int) $movement->item_id,
            'qty'            => (float) $movement->qty,
            'uom_code'       => $movement->uom_code,
            'balance_after'  => (float) $movement->balance_after,
            'doc_type'       => $movement->doc_type,
            'doc_id'         => $movement->doc_id,
        );
    }

    /**
     * Публикация события: в шину erp208 (если модуль подключён) и в общий Event.
     *
     * @param string $event
     * @param array  $payload
     */
    protected function _publish($event, array $payload)
    {
        $initiator = $this->_current_user_id();

        if ( ! class_exists('Service_Erp208_EventBus'))
        {
            return;
        }

        try
        {
            // Одна публикация: Event::fire идёт через шину, она же пишет журнал
            // событий, проксирует уведомления и зовёт слушателей. Парный вызов
            // publish() давал по две записи на каждое событие склада.
            Event::fire($event, $payload, $initiator);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp401: ошибка слушателя события :event — :error',
                array(':event' => $event, ':error' => $e->getMessage()));
        }
    }

    /**
     * Нормализация количества: число > 0.
     *
     * @param  mixed $qty
     * @return float
     * @throws Exception_Erp401_Stock
     */
    protected function _normalize_qty($qty)
    {
        if ( ! is_numeric($qty))
        {
            throw new Exception_Erp401_Stock('Количество должно быть числом', NULL, 'invalid_qty');
        }

        $qty = round((float) $qty, 6);

        if ($qty <= 0)
        {
            throw new Exception_Erp401_Stock('Количество должно быть больше нуля', NULL, 'invalid_qty');
        }

        return $qty;
    }

    /**
     * Формат числа для сообщений об ошибках.
     *
     * @param  float $value
     * @return string
     */
    protected function _num($value)
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    /**
     * ID текущего пользователя.
     *
     * @return int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();
        }
        catch (Exception $e)
        {
            return NULL;
        }

        return ($user AND $user->loaded()) ? (int) $user->id : NULL;
    }

    // -------------------------------------------------------------------------
    // Транзакции (с защитой от вложенности)
    // -------------------------------------------------------------------------

    protected function _begin()
    {
        if ($this->_tx_depth === 0)
        {
            Database::instance()->begin();
        }

        $this->_tx_depth++;
    }

    protected function _commit()
    {
        if ($this->_tx_depth > 0)
        {
            $this->_tx_depth--;
        }

        if ($this->_tx_depth === 0)
        {
            Database::instance()->commit();
        }
    }

    protected function _rollback()
    {
        if ($this->_tx_depth > 0)
        {
            $this->_tx_depth--;
        }

        if ($this->_tx_depth === 0)
        {
            Database::instance()->rollback();
        }
    }

    // =========================================================================
    //  Аналитика оборота
    // =========================================================================

    /**
     * Приход, расход и число движений за период — сырьё для аналитики.
     *
     * Движениями владеет склад, поэтому агрегат считается здесь, а модуль
     * аналитики (`erp801_analytics`) не ходит по таблицам склада сам: он
     * спрашивает готовые суммы и считает по ним оборачиваемость, ABC и прогноз
     * дефицита.
     *
     * Направление берётся по изменению остатка (`balance_after` против
     * `balance_before`), а не по знаку поля `qty`: так приход, расход и
     * корректировки в любую сторону считаются одинаково правильно и не зависят
     * от того, как движение записано.
     *
     * Перемещения между складами без фильтра по складу не попадают ни в приход,
     * ни в расход: для завода это не потребление, а перестановка. С фильтром по
     * складу перемещения видны с обеих сторон — для конкретного склада это
     * реальные приход и расход.
     *
     * @param   array   $filters    date_from, date_to, warehouse_id, item_id
     * @param   string  $group_by   item (по умолчанию), month, warehouse
     * @return  array               строки: ключ, qty_in, qty_out, movements, first_at, last_at
     */
    public function flow(array $filters = array(), $group_by = 'item')
    {
        $group_by = in_array($group_by, array('item', 'month', 'warehouse'), TRUE) ? $group_by : 'item';

        $warehouse_id = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : 0;

        // Расход и приход по изменению остатка; перемещение учитывается только
        // тогда, когда спрашивают про конкретный склад.
        if ($warehouse_id)
        {
            $in = "SUM(CASE"
                ." WHEN m.warehouse_id = $warehouse_id THEN GREATEST(m.balance_after - m.balance_before, 0)"
                ." WHEN m.warehouse_to_id = $warehouse_id THEN m.qty"
                ." ELSE 0 END)";

            $out = "SUM(CASE"
                ." WHEN m.warehouse_id = $warehouse_id THEN GREATEST(m.balance_before - m.balance_after, 0)"
                ." ELSE 0 END)";
        }
        else
        {
            $in = "SUM(CASE WHEN m.type IN ('receipt', 'adjust') THEN GREATEST(m.balance_after - m.balance_before, 0) ELSE 0 END)";
            $out = "SUM(CASE WHEN m.type IN ('issue', 'adjust') THEN GREATEST(m.balance_before - m.balance_after, 0) ELSE 0 END)";
        }

        $columns = array(
            array(DB::expr($in), 'qty_in'),
            array(DB::expr($out), 'qty_out'),
            array(DB::expr('COUNT(*)'), 'movements'),
            array(DB::expr('MIN(m.performed_at)'), 'first_at'),
            array(DB::expr('MAX(m.performed_at)'), 'last_at'),
        );

        switch ($group_by)
        {
            case 'month':
                $query = DB::select_array(array_merge(
                        array('m.item_id', array(DB::expr("to_char(m.performed_at, 'YYYY-MM')"), 'month')),
                        $columns
                    ))
                    ->from(array('erp_401_movements', 'm'))
                    ->group_by('m.item_id')
                    ->group_by(DB::expr("to_char(m.performed_at, 'YYYY-MM')"))
                    ->order_by(DB::expr("to_char(m.performed_at, 'YYYY-MM')"), 'ASC');

                $key_fields = array('item_id', 'month');
            break;

            case 'warehouse':
                $query = DB::select_array(array_merge(array('m.warehouse_id'), $columns))
                    ->from(array('erp_401_movements', 'm'))
                    ->group_by('m.warehouse_id')
                    ->order_by('m.warehouse_id', 'ASC');

                $key_fields = array('warehouse_id');
            break;

            default:
                $query = DB::select_array(array_merge(array('m.item_id'), $columns))
                    ->from(array('erp_401_movements', 'm'))
                    ->group_by('m.item_id')
                    ->order_by('m.item_id', 'ASC');

                $key_fields = array('item_id');
            break;
        }

        $query->where('m.status', '=', 'posted');

        if ($warehouse_id)
        {
            $query->and_where_open()
                  ->where('m.warehouse_id', '=', $warehouse_id)
                  ->or_where('m.warehouse_to_id', '=', $warehouse_id)
                  ->and_where_close();
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('m.item_id', '=', (int) $filters['item_id']);
        }

        if ( ! empty($filters['date_from']))
        {
            $query->where('m.performed_at', '>=', Erp::day($filters['date_from']).' 00:00:00');
        }

        if ( ! empty($filters['date_to']))
        {
            $query->where('m.performed_at', '<=', Erp::day($filters['date_to']).' 23:59:59');
        }

        $rows = $query->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $entry = array(
                'qty_in'    => round((float) $row['qty_in'], 6),
                'qty_out'   => round((float) $row['qty_out'], 6),
                'movements' => (int) $row['movements'],
                'first_at'  => $row['first_at'],
                'last_at'   => $row['last_at'],
                'qty_net'   => round((float) $row['qty_in'] - (float) $row['qty_out'], 6),
            );

            if (isset($row['item_id']))
            {
                $entry['item_id'] = (int) $row['item_id'];
            }

            if (isset($row['month']))
            {
                $entry['month'] = (string) $row['month'];
            }

            if (isset($row['warehouse_id']))
            {
                $entry['warehouse_id'] = (int) $row['warehouse_id'];
            }

            $result[] = $entry;
        }

        return $result;
    }

}
