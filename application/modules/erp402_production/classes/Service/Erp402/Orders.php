<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Производственные заказы (erp402_production).
 *
 * Сервис ведёт заказ по производственному циклу:
 *
 *   draft → planned → released → in_progress → done
 *                      ↘ cancelled ↙
 *
 * Смысл статусов (а не «галочек»):
 *  - `draft`       — заказ заведён, состав ещё уточняется;
 *  - `planned`     — план утверждён: состав полон, потребность посчитана;
 *  - `released`    — материалы зарезервированы на складе (`erp401`);
 *  - `in_progress` — работа начата (цех ведёт операции — `erp408`);
 *  - `done`        — материалы списаны, продукция принята на склад.
 *
 * Переходы разрешены только «вперёд» по этому графу: завершённый заказ нельзя
 * вернуть в работу, а из отменённого можно начать заново только правкой плана.
 * Резерв и списание делает `Service_Erp402_Materials` — сервис склада остаётся
 * единственным местом, где меняются остатки.
 *
 * Каждое изменение заказа пишется в журнал действий (`erp119`) и публикуется
 * в шину событий (`erp208`): производство обязано быть прослеживаемым.
 *
 * @package    Erp402
 * @category   Services
 */
class Service_Erp402_Orders
{
    /** @var Service_Erp402_Orders */
    protected static $_instance;

    /** @var int Глубина вложенных транзакций */
    protected $_tx_depth = 0;

    /** @var array Допустимые переходы статусов */
    protected $_transitions = array(
        'draft'       => array('planned', 'cancelled'),
        'planned'     => array('released', 'draft', 'cancelled'),
        'released'    => array('in_progress', 'planned', 'cancelled'),
        'in_progress' => array('done', 'cancelled'),
        'done'        => array(),
        'cancelled'   => array('draft'),
    );

    /**
     * Какие действия предлагаются заказу в каждом статусе.
     *
     * Шире, чем граф переходов: начать работу можно и из плана (материалы
     * спишутся при выпуске), а выпустить — сразу из зарезервированного
     * состояния, без отдельного «начать».
     *
     * @var array
     */
    protected $_actions = array(
        'draft'       => array('plan', 'cancel'),
        'planned'     => array('release', 'start', 'cancel'),
        'released'    => array('start', 'complete', 'cancel'),
        'in_progress' => array('complete', 'cancel'),
        'done'        => array(),
        'cancelled'   => array(),
    );

    /**
     * @return  Service_Erp402_Orders
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
     * Поиск заказов.
     *
     * @param   array   $filters    status, tech_card_id, item_id, warehouse_id, customer_id, term, overdue, from, to
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset
     */
    public function search(array $filters = array(), $limit = 20, $offset = 0)
    {
        $count = $this->apply_filters(
            DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_402_orders'),
            $filters
        );

        $total = (int) $count->execute()->get('total', 0);

        $query = $this->apply_filters(DB::select('*')->from('erp_402_orders'), $filters);

        $rows = $query
            ->order_by('priority', 'ASC')
            ->order_by(DB::expr('target_date ASC NULLS LAST'))
            ->order_by('id', 'DESC')
            ->limit(max(1, (int) $limit))
            ->offset(max(0, (int) $offset))
            ->execute()
            ->as_array();

        return array(
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => (int) $limit,
            'offset' => (int) $offset,
        );
    }

    /**
     * Общие фильтры списка заказов (для выборки и для подсчёта).
     *
     * Один и тот же набор условий применяется и к строкам, и к COUNT(*):
     * иначе пагинация показывает не то количество страниц, что есть на самом
     * деле. Отдельного метода требует и то, что у запроса нет «reset» —
     * в этой версии построителя запросов его просто нет.
     *
     * @param   object  $query      Database_Query_Builder_Select
     * @param   array   $filters
     * @return  object
     */
    public function apply_filters($query, array $filters)
    {
        if ( ! empty($filters['status']))
        {
            $statuses = is_array($filters['status']) ? $filters['status'] : array($filters['status']);
            $query->where('status', 'IN', $statuses);
        }

        if ( ! empty($filters['open']))
        {
            $query->where('status', 'IN', array('planned', 'released', 'in_progress'));
        }

        foreach (array('tech_card_id', 'item_id', 'warehouse_id', 'customer_id', 'priority') as $field)
        {
            if ( ! empty($filters[$field]))
            {
                $query->where($field, '=', (int) $filters[$field]);
            }
        }

        if ( ! empty($filters['target_date']))
        {
            $query->where('target_date', '=', (string) $filters['target_date']);
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('target_date', '<', date('Y-m-d'))
                  ->where('status', 'NOT IN', array('done', 'cancelled'));
        }

        if ( ! empty($filters['term']))
        {
            $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim($filters['term'])).'%');
        }

        return $query;
    }

    /**
     * Заказ по id или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp402_Order|null
     */
    public function get($id_or_uuid)
    {
        // Пустой идентификатор — это не «заказ с пустым uuid», а отсутствие
        // запроса: без проверки СУБД падает на приведении к uuid
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        $order = ORM::factory('Erp402_Order');

        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $order->where($ref[0], '=', $ref[1])->find();

        return $order->loaded() ? $order : NULL;
    }

    /**
     * Строки состава заказа.
     *
     * @param   int     $order_id
     * @return  array
     */
    public function items($order_id)
    {
        return ORM::factory('Erp402_Order')
            ->where('id', '=', (int) $order_id)
            ->find()
            ->items()
            ->find_all()
            ->as_array();
    }

    // =========================================================================
    //  Запись: заказ и состав
    // =========================================================================

    /**
     * Создать заказ.
     *
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    public function create(array $data, $user_id = NULL)
    {
        if (empty($data['title']))
        {
            throw new Exception_Erp402_Production('Укажите изделие или наименование заказа', NULL, 'invalid_data');
        }

        $order = ORM::factory('Erp402_Order');

        $order->values(array(
            'number'              => ! empty($data['number']) ? (string) $data['number'] : $this->number(),
            'title'               => (string) $data['title'],
            'tech_card_id'        => ! empty($data['tech_card_id']) ? (int) $data['tech_card_id'] : NULL,
            'item_id'             => ! empty($data['item_id']) ? (int) $data['item_id'] : NULL,
            'uom_code'            => ! empty($data['uom_code']) ? (string) $data['uom_code'] : 'pcs',
            'qty'                 => isset($data['qty']) ? (float) $data['qty'] : 1,
            'status'              => 'draft',
            'priority'            => ! empty($data['priority']) ? (int) $data['priority'] : 5,
            'warehouse_id'        => ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : NULL,
            'output_warehouse_id' => ! empty($data['output_warehouse_id']) ? (int) $data['output_warehouse_id'] : NULL,
            'customer_id'         => ! empty($data['customer_id']) ? (int) $data['customer_id'] : NULL,
            'contract_id'         => ! empty($data['contract_id']) ? (int) $data['contract_id'] : NULL,
            'target_date'         => ! empty($data['target_date']) ? (string) $data['target_date'] : NULL,
            'note'                => ! empty($data['note']) ? (string) $data['note'] : NULL,
            'search_text'         => '',
            'created_by'          => $user_id ? (int) $user_id : $this->_current_user_id(),
        ));

        $order->search_text = $this->search_text($order);

        $order->create();

        // uuid и created_at выдаёт СУБД — забираем их, иначе в журнале
        // и в событиях останется пустой идентификатор объекта
        $order->reload();

        $this->_journal($order, 'create', array(), $order->as_array(), 'Заказ создан');
        $this->publish('erp402.order.created', $order);

        return $order;
    }

    /**
     * Изменить заказ (черновик, план, зарезервированный).
     *
     * @param   int         $id
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $order = $this->get($id);

        if ( ! $order)
        {
            throw new Exception_Erp402_Production('Заказ не найден', NULL, 'not_found');
        }

        if ( ! $order->is_editable())
        {
            throw new Exception_Erp402_Production('Заказ в статусе «:status» изменять нельзя', array(
                ':status' => $order->status_label(),
            ), 'locked');
        }

        $before = $order->as_array();

        $fields = array(
            'title', 'tech_card_id', 'item_id', 'uom_code', 'qty', 'priority',
            'warehouse_id', 'output_warehouse_id', 'customer_id', 'contract_id',
            'target_date', 'note',
        );

        foreach ($fields as $field)
        {
            if (array_key_exists($field, $data))
            {
                $order->$field = $data[$field];
            }
        }

        $order->search_text = $this->search_text($order);

        $order->save();
        $order->reload();

        // План выпуска изменился — потребность в материалах пересчитывается
        if ((float) $before['qty'] !== (float) $order->qty)
        {
            $this->recalc($order);
        }

        $this->_journal($order, 'update', $before, $order->as_array(), 'Заказ изменён');
        $this->publish('erp402.order.updated', $order);

        return $order;
    }

    /**
     * Удалить заказ: только черновик или отменённый.
     *
     * @param   int         $id
     * @param   int|null    $user_id
     * @return  bool
     * @throws  Exception_Erp402_Production
     */
    public function delete($id, $user_id = NULL)
    {
        $order = $this->get($id);

        if ( ! $order)
        {
            throw new Exception_Erp402_Production('Заказ не найден', NULL, 'not_found');
        }

        if ( ! in_array($order->status, array('draft', 'cancelled'), TRUE))
        {
            throw new Exception_Erp402_Production('Удалить можно только черновик или отменённый заказ: сначала снимите резервы', NULL, 'locked');
        }

        $before = $order->as_array();
        $uuid   = (string) $order->uuid;

        // Операции цеха уходят вместе с заказом: иначе терминал и сменное
        // задание показывают работу по заказу, которого больше нет
        $this->drop_operations($order, $user_id);

        $order->delete();

        $this->_journal_raw($uuid, 'delete', $before, array(), 'Заказ удалён');
        $this->publish('erp402.order.deleted', NULL, array('uuid' => $uuid, 'number' => $before['number'], 'title' => $before['title']));

        return TRUE;
    }

    /**
     * Добавить строку состава.
     *
     * @param   int         $order_id
     * @param   array       $data       name, item_id, qty_per_unit, uom_code, warehouse_id, note
     * @param   int|null    $user_id
     * @return  Model_Erp402_OrderItem
     * @throws  Exception_Erp402_Production
     */
    public function add_item($order_id, array $data, $user_id = NULL)
    {
        $order = $this->get($order_id);

        if ( ! $order)
        {
            throw new Exception_Erp402_Production('Заказ не найден', NULL, 'not_found');
        }

        if ( ! in_array($order->status, array('draft', 'planned'), TRUE))
        {
            throw new Exception_Erp402_Production('Состав можно менять только до резервирования материалов', NULL, 'locked');
        }

        $item = ORM::factory('Erp402_OrderItem');

        $item->values(array(
            'order_id'     => (int) $order->id,
            'line_no'      => ! empty($data['line_no']) ? (int) $data['line_no'] : $this->next_line_no($order),
            'item_id'      => ! empty($data['item_id']) ? (int) $data['item_id'] : NULL,
            'name'         => (string) (isset($data['name']) ? $data['name'] : ''),
            'uom_code'     => ! empty($data['uom_code']) ? (string) $data['uom_code'] : 'pcs',
            'qty_per_unit' => isset($data['qty_per_unit']) ? (float) $data['qty_per_unit'] : 0,
            'qty_total'    => round((float) (isset($data['qty_per_unit']) ? $data['qty_per_unit'] : 0) * (float) $order->qty, 3),
            'warehouse_id' => ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : $order->warehouse_id,
            'status'       => 'planned',
            'note'         => ! empty($data['note']) ? (string) $data['note'] : NULL,
        ));

        $item->create();

        $this->_journal($order, 'update', array(), array('item' => $item->name), 'Добавлена строка состава: '.$item->name);

        return $item;
    }

    /**
     * Изменить строку состава.
     *
     * @param   int     $item_id
     * @param   array   $data
     * @return  Model_Erp402_OrderItem
     * @throws  Exception_Erp402_Production
     */
    public function update_item($item_id, array $data)
    {
        $item = ORM::factory('Erp402_OrderItem', (int) $item_id);

        if ( ! $item->loaded())
        {
            throw new Exception_Erp402_Production('Строка состава не найдена', NULL, 'not_found');
        }

        $order = $item->order();

        if ($order AND ! in_array($order->status, array('draft', 'planned'), TRUE))
        {
            throw new Exception_Erp402_Production('Состав можно менять только до резервирования материалов', NULL, 'locked');
        }

        foreach (array('item_id', 'name', 'uom_code', 'qty_per_unit', 'warehouse_id', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $item->$field = $data[$field];
            }
        }

        if ($order)
        {
            $item->qty_total = round((float) $item->qty_per_unit * (float) $order->qty, 3);
        }

        $item->status = 'planned';
        $item->save();

        return $item;
    }

    /**
     * Удалить строку состава.
     *
     * @param   int     $item_id
     * @return  bool
     * @throws  Exception_Erp402_Production
     */
    public function remove_item($item_id)
    {
        $item = ORM::factory('Erp402_OrderItem', (int) $item_id);

        if ( ! $item->loaded())
        {
            throw new Exception_Erp402_Production('Строка состава не найдена', NULL, 'not_found');
        }

        $order = $item->order();

        if ($order AND ! in_array($order->status, array('draft', 'planned'), TRUE))
        {
            throw new Exception_Erp402_Production('Состав можно менять только до резервирования материалов', NULL, 'locked');
        }

        $name = (string) $item->name;

        $item->delete();

        if ($order)
        {
            $this->_journal($order, 'update', array(), array('item' => $name), 'Удалена строка состава: '.$name);
        }

        return TRUE;
    }

    /**
     * Заполнить состав из технологической карты (erp228).
     *
     * @param   int         $order_id
     * @param   int|null    $card_id    по умолчанию — карта, указанная в заказе
     * @param   int|null    $user_id
     * @return  array       added, skipped, warnings, card
     * @throws  Exception_Erp402_Production
     */
    public function fill_from_tech_card($order_id, $card_id = NULL, $user_id = NULL)
    {
        $order = $this->get($order_id);

        if ( ! $order)
        {
            throw new Exception_Erp402_Production('Заказ не найден', NULL, 'not_found');
        }

        $card_id = $card_id ? (int) $card_id : (int) $order->tech_card_id;

        if ( ! $card_id)
        {
            throw new Exception_Erp402_Production('У заказа не указана технологическая карта', NULL, 'card_not_found');
        }

        $bom = Service_Erp402_Bom::instance()->from_tech_card($card_id);

        $added   = 0;
        $skipped = 0;
        $line_no = $this->next_line_no($order);

        foreach ($bom['items'] as $line)
        {
            if (empty($line['name']) OR (float) $line['qty_per_unit'] <= 0)
            {
                $skipped++;
                continue;
            }

            try
            {
                $this->add_item($order->id, array(
                    'line_no'      => $line_no,
                    'item_id'      => $line['item_id'],
                    'name'         => $line['name'],
                    'uom_code'     => $line['uom_code'],
                    'qty_per_unit' => $line['qty_per_unit'],
                    'note'         => ($line['match'] === 'exact')
                        ? NULL
                        : 'Требуется сопоставить с номенклатурой реестра'
                          .($line['candidate'] ? ' (похоже: '.$line['candidate'].')' : ''),
                ));

                $line_no += 10;
                $added++;
            }
            catch (Exception $e)
            {
                $skipped++;

                $bom['warnings'][] = $line['name'].': '.$e->getMessage();
            }
        }

        $order->tech_card_id = $card_id;
        $order->search_text  = $this->search_text($order);
        $order->save();

        $this->_journal($order, 'update', array(), array(
            'tech_card_id' => $card_id,
            'added'        => $added,
        ), 'Состав заполнен из техкарты: строк '.$added);

        return array(
            'added'    => $added,
            'skipped'  => $skipped,
            'warnings' => $bom['warnings'],
            'card'     => $bom['card'],
            'operations' => $bom['operations'],
        );
    }

    // =========================================================================
    //  Производственный цикл
    // =========================================================================

    /**
     * Утвердить план: состав полон, потребность посчитана.
     *
     * @param   int         $id
     * @param   int|null    $user_id
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    public function plan($id, $user_id = NULL)
    {
        $order = $this->require_status($id, array('draft', 'planned', 'cancelled'));

        if ($this->count_items($order) === 0)
        {
            throw new Exception_Erp402_Production('Пустой состав: заполните материалы из техкарты или вручную', NULL, 'empty_bom');
        }

        $before = $order->as_array();

        $this->recalc($order);

        $order->status      = 'planned';
        $order->search_text = $this->search_text($order);
        $order->save();

        $this->_journal($order, 'status', $before, array('status' => 'planned'), 'План утверждён');
        $this->publish('erp402.order.planned', $order);

        return $order;
    }

    /**
     * Зарезервировать материалы под заказ.
     *
     * @param   int         $id
     * @param   int|null    $user_id
     * @return  array       order, reserved, short, unmapped
     * @throws  Exception_Erp402_Production
     */
    public function release($id, $user_id = NULL)
    {
        $order = $this->require_status($id, array('draft', 'planned'));

        if ($order->status === 'draft')
        {
            $order = $this->plan($order->id, $user_id);
        }

        $before = $order->as_array();

        $this->_tx_begin();

        try
        {
            $result = Service_Erp402_Materials::instance()->reserve($order, $user_id);

            if ( ! $result['short'] AND ! $result['unmapped'])
            {
                $order->status = 'released';
            }

            $order->search_text = $this->search_text($order);
            $order->save();

            $this->_tx_commit();
        }
        catch (Exception $e)
        {
            $this->_tx_rollback();

            throw $e;
        }

        // Маршрут операций ведёт цех (erp408): если модуль подключён, заказ
        // получает план операций, не дожидаясь первого запуска
        $this->plan_operations($order, $user_id);

        $message = $result['short'] || $result['unmapped']
            ? 'Материалы зарезервированы не полностью'
            : 'Материалы зарезервированы';

        $this->_journal($order, 'status', $before, array('status' => $order->status), $message);
        $this->publish('erp402.order.released', $order, array(
            'reserved' => $result['reserved'],
            'short'    => $result['short'],
        ));

        return array(
            'order'    => $order,
            'reserved' => $result['reserved'],
            'short'    => $result['short'],
            'unmapped' => $result['unmapped'],
        );
    }

    /**
     * Начать работу по заказу.
     *
     * @param   int         $id
     * @param   int|null    $user_id
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    public function start($id, $user_id = NULL)
    {
        $order  = $this->require_status($id, array('released', 'planned'));
        $before = $order->as_array();

        $order->status      = 'in_progress';
        $order->started_at  = date('c');
        $order->search_text = $this->search_text($order);
        $order->save();

        $this->plan_operations($order, $user_id);

        $this->_journal($order, 'status', $before, array('status' => 'in_progress'), 'Работа начата');
        $this->publish('erp402.order.started', $order);

        return $order;
    }

    /**
     * Завершить заказ: списать материалы и принять продукцию на склад.
     *
     * Частичный выпуск поддерживается: если выпущено меньше плана, заказ
     * остаётся «в работе», а `qty_done` растёт — так закрывают партии постепенно.
     *
     * @param   int         $id
     * @param   float|null  $qty        сколько выпущено (по умолчанию — остаток плана)
     * @param   int|null    $user_id
     * @return  array       order, issued, errors, received
     * @throws  Exception_Erp402_Production
     */
    public function complete($id, $qty = NULL, $user_id = NULL)
    {
        $order = $this->require_status($id, array('in_progress', 'released'));

        $qty = ($qty === NULL) ? round((float) $order->qty - (float) $order->qty_done, 3) : round((float) $qty, 3);

        if ($qty <= 0)
        {
            throw new Exception_Erp402_Production('Выпуск должен быть больше нуля', NULL, 'invalid_qty');
        }

        $before = $order->as_array();

        $this->_tx_begin();

        try
        {
            $materials = Service_Erp402_Materials::instance()->issue($order, $user_id);

            if ($materials['errors'])
            {
                throw new Exception_Erp402_Production('Материалы не списаны: :errors', array(
                    ':errors' => implode('; ', $materials['errors']),
                ), 'material_shortage');
            }

            $received = $this->receive_output($order, $qty);

            $order->qty_done = round((float) $order->qty_done + $qty, 3);

            if ($order->qty_done >= (float) $order->qty)
            {
                $order->status      = 'done';
                $order->finished_at = date('c');
            }

            $order->search_text = $this->search_text($order);
            $order->save();

            $this->_tx_commit();
        }
        catch (Exception $e)
        {
            $this->_tx_rollback();

            throw $e;
        }

        $this->_journal($order, 'status', $before, array(
            'status'   => $order->status,
            'qty_done' => (float) $order->qty_done,
        ), 'Выпуск: '.(float) $qty.' '.$order->uom_code);

        $this->publish('erp402.order.completed', $order, array('qty' => $qty));

        return array(
            'order'    => $order,
            'issued'   => $materials['issued'],
            'errors'   => $materials['errors'],
            'received' => $received,
        );
    }

    /**
     * Отменить заказ и снять резервы.
     *
     * @param   int         $id
     * @param   string|null $reason
     * @param   int|null    $user_id
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    public function cancel($id, $reason = NULL, $user_id = NULL)
    {
        $order = $this->require_status($id, array('draft', 'planned', 'released', 'in_progress'));

        $before   = $order->as_array();
        $released = 0;
        $closed   = 0;

        $this->_tx_begin();

        try
        {
            $released = Service_Erp402_Materials::instance()->release_all($order);

            $order->status = 'cancelled';

            if ($reason)
            {
                $order->note = trim((string) $order->note."\nОтмена: ".(string) $reason);
            }

            $order->search_text = $this->search_text($order);
            $order->save();

            // Цех не должен остаться с работой по отменённому заказу
            $closed = $this->close_operations($order, $reason, $user_id);

            $this->_tx_commit();
        }
        catch (Exception $e)
        {
            $this->_tx_rollback();

            throw $e;
        }

        $this->_journal($order, 'status', $before, array('status' => 'cancelled'), 'Заказ отменён'.($reason ? ': '.$reason : '').($closed ? ' (операции цеха закрыты: '.$closed.')' : ''));
        $this->publish('erp402.order.cancelled', $order, array('reason' => $reason, 'released' => $released, 'operations_closed' => $closed));

        return $order;
    }

    /**
     * Пересчитать потребность по составу (норма × план выпуска).
     *
     * @param   Model_Erp402_Order  $order
     * @return  int     сколько строк обновлено
     */
    public function recalc(Model_Erp402_Order $order)
    {
        $updated = 0;

        foreach ($order->items()->find_all() as $item)
        {
            $qty_total = round((float) $item->qty_per_unit * (float) $order->qty, 3);

            if (round((float) $item->qty_total, 3) !== $qty_total)
            {
                $item->qty_total = $qty_total;
                $item->save();

                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Доступные действия по заказу (для кнопок интерфейса).
     *
     * @param   Model_Erp402_Order  $order
     * @return  array   ключ => подпись
     */
    /**
     * Действия, доступные заказу в его текущем статусе.
     *
     * Единый источник правды для интерфейса, API и проверок: если действие
     * здесь есть, сервис его выполнит (см. require_status в plan/release/
     * start/complete/cancel).
     *
     * @param   Model_Erp402_Order  $order
     * @return  array   action => label
     */
    public function allowed_actions(Model_Erp402_Order $order)
    {
        $labels = array(
            'plan'     => 'Утвердить план',
            'release'  => 'Зарезервировать материалы',
            'start'    => 'Начать работу',
            'complete' => 'Выпустить продукцию',
            'cancel'   => 'Отменить',
        );

        $allowed = array();

        foreach (Arr::get($this->_actions, $order->status, array()) as $action)
        {
            if (isset($labels[$action]))
            {
                $allowed[$action] = $labels[$action];
            }
        }

        return $allowed;
    }

    /**
     * Действия заказа для интерфейса (синоним allowed_actions).
     *
     * @param   Model_Erp402_Order  $order
     * @return  array   action => label
     */
    public function transitions(Model_Erp402_Order $order)
    {
        return $this->allowed_actions($order);
    }

    /**
     * Сводка производства.
     *
     * @return  array
     */
    public function stats()
    {
        $by_status = array();

        $statuses = DB::select(
                'status',
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty')
            )
            ->from('erp_402_orders')
            ->group_by('status')
            ->execute()
            ->as_array();

        foreach ($statuses as $row)
        {
            $by_status[$row['status']] = array('total' => (int) $row['total'], 'qty' => (float) $row['qty']);
        }

        $rows = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty'),
                array(DB::expr('COALESCE(SUM(qty_done), 0)'), 'qty_done'),
                array(DB::expr("COUNT(*) FILTER (WHERE target_date < CURRENT_DATE AND status NOT IN ('done', 'cancelled'))"), 'overdue')
            )
            ->from('erp_402_orders')
            ->execute()
            ->current();

        $short_lines = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_402_order_items')
            ->where('status', '=', 'short')
            ->execute()
            ->get('total', 0);

        $unmapped = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_402_order_items')
            ->where('item_id', 'IS', NULL)
            ->execute()
            ->get('total', 0);

        return array(
            'total'        => (int) $rows['total'],
            'qty'          => (float) $rows['qty'],
            'qty_done'     => (float) $rows['qty_done'],
            'overdue'      => (int) $rows['overdue'],
            'short_lines'  => (int) $short_lines,
            'unmapped'     => (int) $unmapped,
            'by_status'    => $by_status,
            'open'         => (int) (isset($by_status['planned']) ? $by_status['planned']['total'] : 0)
                            + (int) (isset($by_status['released']) ? $by_status['released']['total'] : 0)
                            + (int) (isset($by_status['in_progress']) ? $by_status['in_progress']['total'] : 0),
        );
    }

    /**
     * Следующий номер заказа.
     *
     * @return  string
     */
    public function number()
    {
        $next = (int) DB::query(Database::SELECT, 'SELECT nextval(\'erp_402_order_number_seq\') AS n')
            ->execute()
            ->get('n', 0);

        return sprintf('ПО-%s-%06d', date('Y'), $next);
    }

    /**
     * Строка поиска заказа.
     *
     * @param   Model_Erp402_Order  $order
     * @return  string
     */
    public function search_text(Model_Erp402_Order $order)
    {
        $parts = array(
            (string) $order->number,
            (string) $order->title,
            (string) $order->note,
            (string) $order->status,
            $order->priority ? (string) Model_Erp402_Order::$priorities[(int) $order->priority] : '',
        );

        return mb_substr(mb_strtolower(implode(' ', array_filter($parts, 'strlen'))), 0, 512);
    }

    /**
     * Сколько строк в составе заказа.
     *
     * Отдельный запрос, а не `items()->count_all()`: у выборки состава есть
     * сортировка (для интерфейса), а COUNT с ORDER BY по невыбранному полю
     * PostgreSQL запрещает.
     *
     * @param   Model_Erp402_Order  $order
     * @return  int
     */
    public function count_items(Model_Erp402_Order $order)
    {
        return (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_402_order_items')
            ->where('order_id', '=', (int) $order->id)
            ->execute()
            ->get('total', 0);
    }

    /**
     * Следующий номер строки состава.
     *
     * @param   Model_Erp402_Order  $order
     * @return  int
     */
    public function next_line_no(Model_Erp402_Order $order)
    {
        $max = (int) DB::select(array(DB::expr('COALESCE(MAX(line_no), 0)'), 'max'))
            ->from('erp_402_order_items')
            ->where('order_id', '=', (int) $order->id)
            ->execute()
            ->get('max', 0);

        return $max + 10;
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Заказ в одном из ожидаемых статусов.
     *
     * @param   int     $id
     * @param   array   $statuses
     * @return  Model_Erp402_Order
     * @throws  Exception_Erp402_Production
     */
    protected function require_status($id, array $statuses)
    {
        $order = $this->get($id);

        if ( ! $order)
        {
            throw new Exception_Erp402_Production('Заказ не найден', NULL, 'not_found');
        }

        if ( ! in_array($order->status, $statuses, TRUE))
        {
            throw new Exception_Erp402_Production('Действие недоступно: заказ в статусе «:status»', array(
                ':status' => $order->status_label(),
            ), 'invalid_transition');
        }

        return $order;
    }

    /**
     * Приём готовой продукции на склад.
     *
     * @param   Model_Erp402_Order  $order
     * @param   float               $qty
     * @return  array|null          приход либо NULL, если принимать некуда
     */
    protected function receive_output(Model_Erp402_Order $order, $qty)
    {
        if ( ! $order->item_id OR ! $order->output_warehouse_id)
        {
            return NULL;
        }

        try
        {
            $movement = Service_Erp402_Materials::instance()->stock()->receive(
                (int) $order->output_warehouse_id,
                (int) $order->item_id,
                (float) $qty,
                array(
                    'uom_code' => (string) $order->uom_code,
                    'doc_type' => 'erp402_order',
                    'doc_id'   => (int) $order->id,
                    'note'     => 'Выпуск по производственному заказу '.$order->number,
                )
            );

            return array('movement_id' => $movement->id, 'qty' => (float) $movement->qty);
        }
        catch (Exception $e)
        {
            // Продукция не принята — это ошибка выпуска: заказ не закрываем
            throw new Exception_Erp402_Production('Готовая продукция не принята на склад: :error', array(
                ':error' => $e->getMessage(),
            ), 'output_failed');
        }
    }

    /**
     * Передать маршрут операций цеху (erp408), если модуль подключён.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  int     сколько операций запланировано
     */
    protected function plan_operations(Model_Erp402_Order $order, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            return 0;
        }

        try
        {
            return Service_Erp408_Operations::instance()->plan_order($order, $user_id);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: маршрут заказа :number не передан цеху — :error', array(
                ':number' => $order->number,
                ':error'  => $e->getMessage(),
            ));

            return 0;
        }
    }

    /**
     * Закрыть операции цеха по заказу (erp408), если модуль подключён.
     *
     * @param   Model_Erp402_Order  $order
     * @param   string|null         $reason
     * @param   int|null            $user_id
     * @return  int     сколько операций закрыто
     */
    protected function close_operations(Model_Erp402_Order $order, $reason = NULL, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            return 0;
        }

        try
        {
            return Service_Erp408_Operations::instance()->cancel_for_order($order->id, $user_id, $reason);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: операции заказа :number не закрыты в цехе — :error', array(
                ':number' => $order->number,
                ':error'  => $e->getMessage(),
            ));

            return 0;
        }
    }

    /**
     * Удалить операции цеха по заказу (erp408), если модуль подключён.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  int     сколько операций удалено
     * @throws  Exception_Erp402_Production
     */
    protected function drop_operations(Model_Erp402_Order $order, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            return 0;
        }

        try
        {
            return Service_Erp408_Operations::instance()->delete_for_order($order->id, $user_id);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            // Работа идёт прямо сейчас: удалять заказ нельзя, цех против
            throw new Exception_Erp402_Production(
                'Заказ :number нельзя удалить — :error',
                array(':number' => $order->number, ':error' => $e->getMessage()),
                'locked'
            );
        }
    }

    /**
     * Запись в журнал действий по заказу.
     *
     * @param   Model_Erp402_Order  $order
     * @param   string              $action
     * @param   array               $before
     * @param   array               $after
     * @param   string|null         $message
     * @return  void
     */
    protected function _journal(Model_Erp402_Order $order, $action, array $before, array $after, $message = NULL)
    {
        $this->_journal_raw((string) $order->uuid, $action, $before, $after, $message, (string) $order->number.' · '.(string) $order->title);
    }

    /**
     * Запись в журнал действий (по идентификатору объекта).
     *
     * @param   string      $uuid
     * @param   string      $action
     * @param   array       $before
     * @param   array       $after
     * @param   string|null $message
     * @param   string|null $label
     * @return  void
     */
    protected function _journal_raw($uuid, $action, array $before, array $after, $message = NULL, $label = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('order', $uuid, $action, $before, $after, array(
                'module'       => 'erp402',
                'entity_label' => $label,
                'message'      => $message,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * Публикация события производства.
     *
     * @param   string                   $event
     * @param   Model_Erp402_Order|null  $order
     * @param   array                    $extra
     * @return  void
     */
    protected function publish($event, ?Model_Erp402_Order $order = NULL, array $extra = array())
    {
        $payload = array(
            'order_id' => $order ? (int) $order->id : NULL,
            'uuid'     => $order ? (string) $order->uuid : NULL,
            'number'   => $order ? (string) $order->number : NULL,
            'title'    => $order ? (string) $order->title : NULL,
            'qty'      => $order ? (float) $order->qty : NULL,
            'qty_done' => $order ? (float) $order->qty_done : NULL,
            'status'   => $order ? (string) $order->status : NULL,
            'at'       => date('c'),
        );

        $payload += $extra;

        if ( ! class_exists('Service_Erp208_EventBus'))
        {
            return;
        }

        try
        {
            // Одна публикация: Event::fire идёт через шину (журнал событий,
            // уведомления, слушатели). Парный вызов publish() давал по две
            // записи на каждое событие производства.
            Event::fire($event, $payload, $this->_current_user_id());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: не удалось опубликовать событие :event — :error',
                array(':event' => $event, ':error' => $e->getMessage()));
        }
    }

    /**
     * Текущий пользователь (NULL для CLI).
     *
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    protected function _tx_begin()
    {
        if ($this->_tx_depth === 0)
        {
            Database::instance()->begin();
        }

        $this->_tx_depth++;
    }

    protected function _tx_commit()
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

    protected function _tx_rollback()
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
}
