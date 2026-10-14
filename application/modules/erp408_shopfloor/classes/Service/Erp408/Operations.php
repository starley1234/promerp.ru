<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Операции цеха: маршрут заказа, отметки, выработка, простои (erp408_shopfloor).
 *
 * Сервис — единственная точка входа в данные цеха. Из него работают карточка
 * операции, терминал, JSON API, инструменты MCP и планировщик, поэтому правила
 * (какие переходы допустимы, что считается фактом, когда публикуется событие)
 * описаны один раз.
 *
 * Два принципа, заложенные с самого начала:
 *
 *  1. **Цех не трогает чужие таблицы.** Операция знает заказ только как
 *     мягкую ссылку (`order_id`, `order_uuid`), а факт выполнения возвращает
 *     производству событием `erp408.operation.completed` — его слушает
 *     `erp402_production` и сам решает, что записать в свой журнал.
 *  2. **Журнал отметок — только добавление.** Время работы и простоя
 *     накапливается в операции, но каждая отметка (кто и когда нажал кнопку)
 *     остаётся записью в `erp_408_worklogs`: это и есть доказательство факта
 *     в терминальном режиме.
 *
 * @package    Erp408
 * @category   Services
 */
class Service_Erp408_Operations
{
    const TABLE = 'erp_408_operations';
    const LOGS  = 'erp_408_worklogs';

    /** @var Service_Erp408_Operations|null */
    protected static $_instance;

    /** @var array|null Кэш подписей рабочих центров на время запроса */
    protected $_center_labels = NULL;

    /** @var array Что можно делать с операцией в каждом статусе */
    protected $_actions = array(
        'planned' => array(
            'assign'   => 'Назначить',
            'start'    => 'Начать',
            'finish'   => 'Завершить',
            'cancel'   => 'Отменить',
            'delete'   => 'Удалить',
        ),
        'in_progress' => array(
            'output'         => 'Выработка',
            'pause'          => 'Пауза',
            'finish'         => 'Завершить',
            'downtime_start' => 'Простой',
            'cancel'         => 'Отменить',
        ),
        'paused' => array(
            'resume'       => 'Продолжить',
            'output'       => 'Выработка',
            'finish'       => 'Завершить',
            'downtime_stop'=> 'Закончить простой',
            'cancel'       => 'Отменить',
        ),
        'done'      => array(),
        'cancelled' => array(),
    );

    /**
     * @return  Service_Erp408_Operations
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
    //  Маршрут заказа: контракт с производством (erp402)
    // =========================================================================

    /**
     * Разложить производственный заказ по операциям цеха.
     *
     * Вызывается из `Service_Erp402_Orders::plan_operations()` при планировании
     * заказа. Операции берутся из техкарты (`erp228`): название, норма времени
     * и нормативная стоимость. Если техкарты нет, но у заказа есть состав —
     * создаётся одна операция «Изготовление по заказу», чтобы цех всё равно
     * увидел работу. Если нет ни того, ни другого — исключение `no_route`.
     *
     * Метод идемпотентен: повторное планирование не дублирует операции
     * (`UNIQUE (order_id, op_no)`), а возвращает их число.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  int     сколько операций в маршруте заказа
     * @throws  Exception_Erp408_Shopfloor
     */
    public function plan_order(Model_Erp402_Order $order, $user_id = NULL)
    {
        if ( ! $order->loaded())
        {
            throw new Exception_Erp408_Shopfloor('Заказ не сохранён — маршрут не построить', NULL, 'order_not_found');
        }

        $existing = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from(self::TABLE)
            ->where('order_id', '=', (int) $order->id)
            ->where('status', '!=', 'cancelled')
            ->execute()
            ->get('total', 0);

        if ($existing > 0)
        {
            // Маршрут уже построен: не плодим дубли, но обновляем «шапку»
            // (номер и название заказа могли измениться).
            $this->refresh_order_fields($order);

            return $existing;
        }

        $qty     = (float) $order->qty;
        $day     = $this->planned_day($order);
        $user_id = $user_id ? (int) $user_id : NULL;
        $source  = 'order';
        $rows    = array();

        if ($order->tech_card_id AND class_exists('Service_Erp228_TechCards'))
        {
            $operations = Service_Erp228_TechCards::instance()->operations($order->tech_card_id);

            // Номера операций в техкарте (sort_order) могут повторяться или быть
            // пустыми: в маршруте заказа номер обязан быть уникальным, иначе
            // вторая операция не запишется. Поэтому порядок берём из данных,
            // только если он корректный, иначе нумеруем по позиции (10, 20, 30).
            $sort_orders = array();

            foreach ($operations as $operation)
            {
                $sort_orders[] = (int) (isset($operation['sort_order']) ? $operation['sort_order'] : 0);
            }

            $ordered = ($sort_orders AND min($sort_orders) > 0
                AND count(array_unique($sort_orders)) === count($sort_orders));

            foreach ($operations as $index => $operation)
            {
                $rows[] = array(
                    'op_no'         => $ordered ? (int) $operation['sort_order'] : ($index + 1) * 10,
                    'name'          => (string) $operation['name'],
                    'description'   => isset($operation['description']) ? (string) $operation['description'] : NULL,
                    'planned_hours' => Erp::hours(isset($operation['duration_sec']) ? $operation['duration_sec'] : 0) * $qty,
                    'norm_cost'     => isset($operation['cost_amount']) ? (float) $operation['cost_amount'] : 0,
                );
            }

            if ($rows)
            {
                $source = 'tech_card';
            }
        }

        if ( ! $rows)
        {
            $items = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
                ->from('erp_402_order_items')
                ->where('order_id', '=', (int) $order->id)
                ->execute()
                ->get('total', 0);

            if ($items === 0)
            {
                throw new Exception_Erp408_Shopfloor(
                    'У заказа :number нет ни техкарты, ни состава — маршрут цеха не построить',
                    array(':number' => $order->number),
                    'no_route'
                );
            }

            $rows[] = array(
                'op_no'         => 10,
                'name'          => 'Изготовление по заказу '.$order->number,
                'description'   => 'Операции не заданы техкартой: работа ведётся по составу заказа',
                'planned_hours' => 0,
                'norm_cost'     => 0,
            );
        }

        $created = 0;

        foreach ($rows as $row)
        {
            $data = array(
                'order_id'       => (int) $order->id,
                'order_uuid'     => (string) $order->uuid,
                'order_number'   => (string) $order->number,
                'order_title'    => (string) $order->title,
                'item_id'        => $order->item_id ? (int) $order->item_id : NULL,
                'qty_planned'    => $qty,
                'op_no'          => $row['op_no'],
                'name'           => $row['name'],
                'description'    => $row['description'],
                'work_center_id' => NULL,
                'shift_id'       => NULL,
                'planned_day'    => $day,
                'planned_hours'  => round($row['planned_hours'], 2),
                'norm_cost'      => round($row['norm_cost'], 2),
                'status'         => 'planned',
                'source'         => $source,
                'created_by'     => $user_id,
                'created_at'     => date('c'),
            );

            DB::insert(self::TABLE, array_keys($data))->values(array_values($data))->execute();

            $created++;
        }

        // Маршрут — изменение заказа, а не одной операции: запись идёт в карточку
        // заказа (entity_type 'order'), иначе в журнале операции оказывался
        // чужой идентификатор
        $this->journal('planned', (string) $order->uuid, array(), array(
            'operations' => $created,
            'source'     => $source,
            'day'        => $day,
        ), 'Маршрут заказа '.$order->number.' передан в цех: операций '.$created, $user_id, NULL, 'order');

        $this->publish('erp408.route.planned', NULL, array(
            'order_id'     => (int) $order->id,
            'order_uuid'   => (string) $order->uuid,
            'order_number' => (string) $order->number,
            'operations'   => $created,
            'source'       => $source,
        ));

        return $created;
    }

    /**
     * Добавить операцию вручную (работа, которой нет в техкарте).
     *
     * @param   array       $data   order_id, name, qty_planned, op_no?, work_center_id?, shift_id?, planned_day?, planned_hours?, description?
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function add_operation(array $data, $user_id = NULL)
    {
        $order_id = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        $name     = trim((string) (isset($data['name']) ? $data['name'] : ''));

        if ( ! $order_id OR $name === '')
        {
            throw new Exception_Erp408_Shopfloor('Для операции нужны заказ и название', NULL, 'invalid_transition');
        }

        $order = $this->order_row($order_id);

        if ( ! $order)
        {
            throw new Exception_Erp408_Shopfloor('Производственный заказ :id не найден', array(':id' => $order_id), 'order_not_found');
        }

        $op_no = isset($data['op_no']) AND (int) $data['op_no'] > 0
            ? (int) $data['op_no']
            : $this->next_op_no($order_id);

        $center_id = ! empty($data['work_center_id']) ? (int) $data['work_center_id'] : NULL;

        if ($center_id)
        {
            Service_Erp408_WorkCenters::instance()->model($center_id);
        }

        $shift_id = ! empty($data['shift_id'])
            ? (int) $data['shift_id']
            : ($this->shifts()->current() ? (int) $this->shifts()->current()['id'] : NULL);

        $row = array(
            'order_id'       => $order_id,
            'order_uuid'     => isset($order['uuid']) ? $order['uuid'] : NULL,
            'order_number'   => isset($order['number']) ? $order['number'] : NULL,
            'order_title'    => isset($order['title']) ? $order['title'] : NULL,
            'item_id'        => ! empty($order['item_id']) ? (int) $order['item_id'] : NULL,
            'qty_planned'    => Erp::num(isset($data['qty_planned']) ? $data['qty_planned'] : $order['qty']),
            'op_no'          => $op_no,
            'name'           => $name,
            'description'    => isset($data['description']) ? trim((string) $data['description']) : NULL,
            'work_center_id' => $center_id,
            'shift_id'       => $shift_id,
            'planned_day'    => Erp::day(isset($data['planned_day']) ? $data['planned_day'] : date('Y-m-d')),
            'planned_hours'  => round(Erp::num(isset($data['planned_hours']) ? $data['planned_hours'] : 0), 2),
            'norm_cost'      => round(Erp::num(isset($data['norm_cost']) ? $data['norm_cost'] : 0), 2),
            'status'         => 'planned',
            'source'         => 'manual',
            'created_by'     => $user_id ? (int) $user_id : NULL,
            'created_at'     => date('c'),
        );

        $result = DB::insert(self::TABLE, array_keys($row))->values(array_values($row))->execute();
        $id     = is_array($result) ? (int) reset($result) : (int) $result;

        $operation = $this->get($id);

        $this->journal('create', $operation['uuid'], array(), $operation, 'Добавлена операция '.$operation['name'], $user_id, $operation);

        return $operation;
    }

    /**
     * Изменить операцию: название, количество, норму времени, стоимость, день.
     *
     * Назначение на центр и смену делает `assign()`: у него свои проверки
     * (рабочий день календаря, активность центра).
     *
     * @param   int|string  $id
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $before = $this->get($id);

        if ( ! $before)
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        $merge = function ($key, $default = NULL) use ($data, $before) {
            if (array_key_exists($key, $data) AND $data[$key] !== NULL AND $data[$key] !== '')
            {
                return $data[$key];
            }

            return array_key_exists($key, $before) ? $before[$key] : $default;
        };

        $name = trim((string) $merge('name'));

        if ($name === '')
        {
            throw new Exception_Erp408_Shopfloor('У операции должно быть название', NULL, 'invalid_transition');
        }

        $row = array(
            'name'          => $name,
            'description'   => trim((string) $merge('description', '')) !== '' ? trim((string) $merge('description', '')) : NULL,
            'qty_planned'   => round(Erp::num($merge('qty_planned')), 3),
            'op_no'         => (int) $merge('op_no', $before['op_no']),
            'planned_hours' => round(Erp::num($merge('planned_hours')), 2),
            'norm_cost'     => round(Erp::num($merge('norm_cost')), 2),
            'planned_day'   => Erp::day($merge('planned_day')),
            'updated_at'    => date('c'),
        );

        if ( ! empty($data['work_center_id']))
        {
            Service_Erp408_WorkCenters::instance()->model((int) $data['work_center_id']);
            $row['work_center_id'] = (int) $data['work_center_id'];
        }

        DB::update(self::TABLE)->set($row)->where('id', '=', (int) $before['id'])->execute();

        $after = $this->get($before['id']);

        $this->journal('update', $after['uuid'], $before, $after, 'Операция изменена', $user_id, $after);

        return $after;
    }

    /**
     * Пересобрать маршрут заказа заново (после смены техкарты или состава).
     *
     * Отменённые операции остаются в истории, действующие удаляются только
     * если по ним нет отметок — иначе мастер видит исключение и решает сам.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  int
     */
    public function replan_order(Model_Erp402_Order $order, $user_id = NULL)
    {
        $rows = DB::select()->from(self::TABLE)
            ->where('order_id', '=', (int) $order->id)
            ->execute()
            ->as_array();

        foreach ($rows as $row)
        {
            if ($row['status'] === 'done' OR $row['actual_start'] !== NULL)
            {
                throw new Exception_Erp408_Shopfloor(
                    'По операции «:name» уже есть отметки — перестройте маршрут вручную',
                    array(':name' => $row['name']),
                    'invalid_transition'
                );
            }
        }

        DB::delete(self::TABLE)->where('order_id', '=', (int) $order->id)->execute();

        return $this->plan_order($order, $user_id);
    }

    /**
     * Закрыть операции заказа (заказ отменён в производстве).
     *
     * Вызывается из `Service_Erp402_Orders::cancel()`: если заказ отменён,
     * незакрытые операции цеха обязаны уйти в «отменена», иначе мастер
     * продолжит видеть в сменном задании работу, которой больше нет.
     * Выполненные операции остаются фактом и не переписываются.
     *
     * @param   int|string  $order_id
     * @param   int|null    $user_id
     * @param   string|null $reason
     * @return  int     сколько операций закрыто
     */
    public function cancel_for_order($order_id, $user_id = NULL, $reason = NULL)
    {
        $order_id = (int) $order_id;

        if ( ! $order_id)
        {
            return 0;
        }

        $closed = 0;

        foreach ($this->for_order($order_id) as $operation)
        {
            if ($operation['status'] === 'done' OR $operation['status'] === 'cancelled')
            {
                continue;
            }

            $this->cancel($operation['id'], $user_id,
                $reason !== NULL AND $reason !== '' ? $reason : 'Заказ отменён');

            $closed++;
        }

        return $closed;
    }

    /**
     * Удалить операции вместе с заказом.
     *
     * Удаление заказа — это исправление ошибки (черновик или отменённый
     * заказ), а не способ спрятать факт: пока по операции идёт работа, цех
     * отвечает исключением, а каждая удаляемая операция остаётся записью в
     * журнале действий (`erp119`). Отметки уходят каскадом
     * (`ON DELETE CASCADE`) — до удаления операции журнал фиксирует факт.
     *
     * @param   int|string  $order_id
     * @param   int|null    $user_id
     * @return  int     сколько операций удалено
     * @throws  Exception_Erp408_Shopfloor
     */
    public function delete_for_order($order_id, $user_id = NULL)
    {
        $order_id = (int) $order_id;

        if ( ! $order_id)
        {
            return 0;
        }

        $deleted = 0;

        foreach ($this->for_order($order_id) as $operation)
        {
            if ($operation['status'] === 'in_progress' OR $operation['status'] === 'paused')
            {
                throw new Exception_Erp408_Shopfloor(
                    'Операция «:name» ещё выполняется — сначала отмените заказ',
                    array(':name' => $operation['name']),
                    'invalid_transition'
                );
            }

            if ($operation['status'] !== 'cancelled')
            {
                $this->journal('delete', $operation['uuid'], $operation, array(),
                    'Операция удалена вместе с заказом', $user_id, $operation);
            }

            DB::delete(self::TABLE)->where('id', '=', (int) $operation['id'])->execute();

            $deleted++;
        }

        return $deleted;
    }

    // =========================================================================
    //  Назначение и переходы
    // =========================================================================

    /**
     * Назначить операцию на рабочий центр, смену и день.
     *
     * @param   int|string  $id
     * @param   int         $center_id
     * @param   int|null    $shift_id
     * @param   string|null $day
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function assign($id, $center_id, $shift_id = NULL, $day = NULL, $user_id = NULL)
    {
        $operation = $this->get($id);

        if ( ! $operation)
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        $center = Service_Erp408_WorkCenters::instance()->get($center_id);

        if ( ! $center OR ! $center['is_active'])
        {
            throw new Exception_Erp408_Shopfloor('Рабочий центр не найден или выключен', NULL, 'no_work_center');
        }

        $shifts    = Service_Erp408_Shifts::instance();
        $shift_id  = $shift_id ? (int) $shift_id : ($operation['shift_id'] ? (int) $operation['shift_id'] : NULL);
        $day       = $day ? Erp::day($day) : Erp::day($operation['planned_day']);

        if ( ! $shifts->is_working($day, $center['id']))
        {
            $day = $shifts->next_working_day($day, $center['id']);
        }

        $before = $operation;

        DB::update(self::TABLE)
            ->set(array(
                'work_center_id' => (int) $center['id'],
                'shift_id'       => $shift_id,
                'planned_day'    => $day,
                'updated_at'     => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $operation = $this->get($operation['id']);

        $this->journal('update', $operation['uuid'], $before, $operation,
            'Операция назначена: '.$center['code'].', '.$day, $user_id, $operation);

        return $operation;
    }

    /**
     * Начать работу.
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function start($id, $user_id = NULL)
    {
        $operation = $this->require_action($id, 'start');

        DB::update(self::TABLE)
            ->set(array(
                'status'           => 'in_progress',
                'actual_start'     => $operation['actual_start'] ? $operation['actual_start'] : date('c'),
                'assigned_user_id' => $user_id ? (int) $user_id : $operation['assigned_user_id'],
                'updated_at'       => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'start', $user_id);

        $operation = $this->get($operation['id']);

        $this->journal('start', $operation['uuid'], array(), array('status' => 'in_progress'), 'Работа начата', $user_id, $operation);
        $this->publish('erp408.operation.started', $operation, array('user_id' => $user_id ? (int) $user_id : NULL));

        return $operation;
    }

    /**
     * Пауза: время работы до этой минуты уходит в факт.
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @param   string|null $comment
     * @return  array
     */
    public function pause($id, $user_id = NULL, $comment = NULL)
    {
        $operation = $this->require_action($id, 'pause');
        $seconds   = $this->open_seconds($operation['id']);

        DB::update(self::TABLE)
            ->set(array(
                'status'     => 'paused',
                'worked_sec' => (int) $operation['worked_sec'] + $seconds,
                'updated_at' => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'pause', $user_id, array('seconds' => $seconds, 'comment' => $comment));

        $operation = $this->get($operation['id']);

        $this->journal('pause', $operation['uuid'], array(), array('status' => 'paused', 'worked_sec' => $operation['worked_sec']),
            'Пауза'.($comment ? ': '.$comment : ''), $user_id, $operation);

        return $operation;
    }

    /**
     * Продолжить после паузы.
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @return  array
     */
    public function resume($id, $user_id = NULL)
    {
        $operation = $this->require_action($id, 'resume');

        DB::update(self::TABLE)
            ->set(array('status' => 'in_progress', 'updated_at' => date('c')))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'resume', $user_id);

        $operation = $this->get($operation['id']);

        $this->journal('start', $operation['uuid'], array(), array('status' => 'in_progress'), 'Работа продолжена', $user_id, $operation);

        return $operation;
    }

    /**
     * Выработка: годные и брак.
     *
     * @param   int|string  $id
     * @param   float       $qty_ok
     * @param   float       $qty_scrap
     * @param   int|null    $user_id
     * @param   string|null $comment
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function output($id, $qty_ok, $qty_scrap = 0, $user_id = NULL, $comment = NULL)
    {
        $operation = $this->require_action($id, 'output');

        $qty_ok    = round(Erp::num($qty_ok), 3);
        $qty_scrap = round(Erp::num($qty_scrap), 3);

        if ($qty_ok < 0 OR $qty_scrap < 0)
        {
            throw new Exception_Erp408_Shopfloor('Количество не может быть отрицательным', NULL, 'qty_invalid');
        }

        if ($qty_ok + $qty_scrap == 0)
        {
            throw new Exception_Erp408_Shopfloor('Укажите количество годных или брак', NULL, 'qty_invalid');
        }

        $planned = (float) $operation['qty_planned'];

        if ($planned > 0 AND (float) $operation['qty_ok'] + $qty_ok > $planned * 5)
        {
            // Защита от опечатки в терминале: «120» вместо «12» ловим сразу
            throw new Exception_Erp408_Shopfloor(
                'Годных больше плана в пять раз (:qty из :planned) — проверьте количество',
                array(':qty' => $qty_ok, ':planned' => $planned),
                'qty_invalid'
            );
        }

        DB::update(self::TABLE)
            ->set(array(
                'qty_ok'     => (float) $operation['qty_ok'] + $qty_ok,
                'qty_scrap'  => (float) $operation['qty_scrap'] + $qty_scrap,
                'updated_at' => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'output', $user_id, array(
            'qty_ok'    => $qty_ok,
            'qty_scrap' => $qty_scrap,
            'comment'   => $comment,
        ));

        $operation = $this->get($operation['id']);

        $this->journal('update', $operation['uuid'], array(), array(
            'qty_ok'    => $operation['qty_ok'],
            'qty_scrap' => $operation['qty_scrap'],
        ), 'Выработка: годных '.$qty_ok.', брак '.$qty_scrap, $user_id, $operation);

        $this->publish('erp408.operation.output', $operation, array(
            'qty_ok'    => $qty_ok,
            'qty_scrap' => $qty_scrap,
            'user_id'   => $user_id ? (int) $user_id : NULL,
        ));

        return $operation;
    }

    /**
     * Завершить операцию. Можно и без нажатого «Начать»: в цехе оператор
     * закрывает работу вечером, а старт ставит уже потом.
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @param   float|null  $qty_ok     По умолчанию — остаток до плана
     * @param   float|null  $qty_scrap
     * @param   string|null $comment
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function finish($id, $user_id = NULL, $qty_ok = NULL, $qty_scrap = NULL, $comment = NULL)
    {
        $operation = $this->require_action($id, 'finish');
        $seconds   = $this->open_seconds($operation['id']);

        if ($qty_ok !== NULL AND $qty_ok !== '')
        {
            $this->output($operation['id'], $qty_ok, $qty_scrap === NULL ? 0 : $qty_scrap, $user_id, 'при завершении');
            $operation = $this->get($operation['id']);
        }

        DB::update(self::TABLE)
            ->set(array(
                'status'       => 'done',
                'actual_start' => $operation['actual_start'] ? $operation['actual_start'] : date('c'),
                'actual_end'   => date('c'),
                'worked_sec'   => (int) $operation['worked_sec'] + $seconds,
                'updated_at'   => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'finish', $user_id, array('seconds' => $seconds, 'comment' => $comment));

        $operation = $this->get($operation['id']);

        $this->journal('status', $operation['uuid'], array('status' => 'in_progress'), array(
            'status'     => 'done',
            'qty_ok'     => $operation['qty_ok'],
            'qty_scrap'  => $operation['qty_scrap'],
            'worked_sec' => $operation['worked_sec'],
        ), 'Операция выполнена: годных '.$operation['qty_ok'].', брак '.$operation['qty_scrap'], $user_id, $operation);

        $this->publish('erp408.operation.completed', $operation, array(
            'user_id' => $user_id ? (int) $user_id : NULL,
        ));

        return $operation;
    }

    /**
     * Отменить операцию (работа по ней не ведётся).
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @param   string|null $reason
     * @return  array
     */
    public function cancel($id, $user_id = NULL, $reason = NULL)
    {
        $operation = $this->require_action($id, 'cancel');
        $seconds   = $this->open_seconds($operation['id']);

        DB::update(self::TABLE)
            ->set(array(
                'status'     => 'cancelled',
                'note'       => $reason !== NULL AND $reason !== '' ? (string) $reason : $operation['note'],
                'worked_sec' => (int) $operation['worked_sec'] + $seconds,
                'updated_at' => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $operation = $this->get($operation['id']);

        $this->journal('status', $operation['uuid'], array(), array('status' => 'cancelled', 'reason' => $reason),
            'Операция отменена'.($reason ? ': '.$reason : ''), $user_id, $operation);

        $this->publish('erp408.operation.cancelled', $operation, array('reason' => $reason));

        return $operation;
    }

    /**
     * Удалить операцию: можно только «к выполнению» и без отметок — это
     * исправление ошибки мастера, а не способ спрятать факт.
     *
     * @param   int|string  $id
     * @return  bool
     * @throws  Exception_Erp408_Shopfloor
     */
    public function delete($id)
    {
        $operation = $this->get($id);

        if ( ! $operation)
        {
            return FALSE;
        }

        $logs = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from(self::LOGS)
            ->where('operation_id', '=', (int) $operation['id'])
            ->execute()->get('total', 0);

        if ($operation['status'] !== 'planned' OR $logs > 0)
        {
            throw new Exception_Erp408_Shopfloor(
                'Операцию «:name» можно удалить только до начала работ — отмените её',
                array(':name' => $operation['name']),
                'invalid_transition'
            );
        }

        DB::delete(self::TABLE)->where('id', '=', (int) $operation['id'])->execute();

        return TRUE;
    }

    // =========================================================================
    //  Простои
    // =========================================================================

    /**
     * Начать простой: станок встал — операция уходит в паузу, а причина
     * запоминается, чтобы потом считать потери по причинам.
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @param   string      $reason
     * @param   string|null $comment
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function downtime_start($id, $user_id = NULL, $reason = 'other', $comment = NULL)
    {
        $operation = $this->get($id);

        if ( ! $operation)
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        if ( ! isset(Model_Erp408_Operation::$downtime_reasons[$reason]))
        {
            throw new Exception_Erp408_Shopfloor('Неизвестная причина простоя: :reason', array(':reason' => $reason), 'invalid_transition');
        }

        if ( ! empty($operation['downtime_started_at']))
        {
            throw new Exception_Erp408_Shopfloor('Простой уже идёт — сначала закончите текущий', NULL, 'invalid_transition');
        }

        if ( ! $operation['is_open'])
        {
            throw new Exception_Erp408_Shopfloor(
                'Простой отмечают по открытой операции (сейчас «:status»)',
                array(':status' => $operation['status_label']),
                'invalid_transition'
            );
        }

        $seconds = $this->open_seconds($operation['id']);

        DB::update(self::TABLE)
            ->set(array(
                'status'              => 'paused',
                'downtime_started_at' => date('c'),
                'worked_sec'          => (int) $operation['worked_sec'] + $seconds,
                'updated_at'          => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'downtime_start', $user_id, array(
            'downtime_reason' => $reason,
            'comment'         => $comment,
        ));

        $operation = $this->get($operation['id']);

        $this->journal('update', $operation['uuid'], array(), array('downtime_reason' => $reason),
            'Простой: '.$operation['downtime_reason_label'], $user_id, $operation);

        $this->publish('erp408.downtime.started', $operation, array(
            'reason'    => $reason,
            'reason_label' => $operation['downtime_reason_label'],
            'user_id'   => $user_id ? (int) $user_id : NULL,
        ));

        return $operation;
    }

    /**
     * Закончить простой: секунды уходят в `downtime_sec`, операция остаётся
     * в паузе (работа возобновляется отдельной кнопкой).
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @param   string|null $comment
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function downtime_stop($id, $user_id = NULL, $comment = NULL)
    {
        $operation = $this->get($id);

        if ( ! $operation)
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        if ( empty($operation['downtime_started_at']))
        {
            throw new Exception_Erp408_Shopfloor('Простой не отмечен — нечего завершать', NULL, 'invalid_transition');
        }

        $seconds = max(0, time() - strtotime($operation['downtime_started_at']));
        $reason  = $this->last_downtime_reason($operation['id']);

        DB::update(self::TABLE)
            ->set(array(
                'downtime_started_at' => NULL,
                'downtime_sec'        => (int) $operation['downtime_sec'] + $seconds,
                'updated_at'          => date('c'),
            ))
            ->where('id', '=', (int) $operation['id'])
            ->execute();

        $this->log($operation['id'], 'downtime_end', $user_id, array(
            'seconds'         => $seconds,
            'downtime_reason' => $reason,
            'comment'         => $comment,
        ));

        $operation = $this->get($operation['id']);

        $this->journal('update', $operation['uuid'], array(), array('downtime_sec' => $operation['downtime_sec']),
            'Простой закончен: '.$seconds.' с', $user_id, $operation);

        $this->publish('erp408.downtime.finished', $operation, array(
            'seconds' => $seconds,
            'reason'  => $reason,
            'user_id' => $user_id ? (int) $user_id : NULL,
        ));

        return $operation;
    }

    /**
     * Единая точка переходов: «начать», «пауза», «выработка», «завершить»,
     * «отменить», «простой», «назначить», «удалить».
     *
     * Так действие описывается одним словом — и в интерфейсе, и в JSON API, и
     * в инструментах MCP, и в тестах. Список слов совпадает с ключами
     * `allowed_actions()`, поэтому кнопка и вызов не расходятся.
     *
     * @param   int|string  $id
     * @param   string      $action
     * @param   array       $context    qty_ok, qty_scrap, comment, reason, work_center_id/center_id, shift_id, day/planned_day
     * @param   int|null    $user_id
     * @return  array|null
     * @throws  Exception_Erp408_Shopfloor
     */
    public function act($id, $action, array $context = array(), $user_id = NULL)
    {
        $value = function ($key, $default = NULL) use ($context) {
            return array_key_exists($key, $context) ? $context[$key] : $default;
        };

        switch ($action)
        {
            case 'assign':
                return $this->assign(
                    $id,
                    $value('work_center_id', $value('center_id')),
                    $value('shift_id'),
                    $value('planned_day', $value('day')),
                    $user_id
                );

            case 'start':
                return $this->start($id, $user_id);

            case 'pause':
                return $this->pause($id, $user_id, $value('comment'));

            case 'resume':
                return $this->resume($id, $user_id);

            case 'output':
                return $this->output($id, $value('qty_ok'), $value('qty_scrap', 0), $user_id, $value('comment'));

            case 'finish':
                return $this->finish($id, $user_id, $value('qty_ok'), $value('qty_scrap'), $value('comment'));

            case 'cancel':
                return $this->cancel($id, $user_id, $value('reason', $value('comment')));

            case 'delete':
                $operation = $this->get($id);
                $this->delete($id);

                return $operation;

            case 'downtime_start':
                return $this->downtime_start($id, $user_id, $value('reason', 'other'), $value('comment'));

            case 'downtime_stop':
                return $this->downtime_stop($id, $user_id, $value('comment'));
        }

        throw new Exception_Erp408_Shopfloor(
            'Неизвестное действие «:action»',
            array(':action' => (string) $action),
            'invalid_transition'
        );
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * @param   int|string  $id_or_uuid
     * @return  array|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        // Строка должна быть идентификатором или uuid: произвольный текст
        // («ПО-2026-000001») в сравнении с uuid роняет PostgreSQL.
        // Числовой id или uuid — и то и другое приходит из адреса; мусор
        // превращается в «не найдено», а не в ошибку приведения к uuid.
        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $query = DB::select()->from(self::TABLE)->limit(1);
        $query->where($ref[0], '=', $ref[1]);

        $row = $query->execute()->current();

        return $row ? $this->prepare($row) : NULL;
    }

    /**
     * @param   int|string  $id
     * @return  Model_Erp408_Operation
     * @throws  Exception_Erp408_Shopfloor
     */
    public function model($id)
    {
        $operation = ORM::factory('Erp408_Operation', (int) $id);

        if ( ! $operation->loaded())
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        return $operation;
    }

    /**
     * Операции заказа — для карточки производства и маршрутного листа.
     *
     * @param   int|string  $order_id
     * @return  array
     */
    public function for_order($order_id)
    {
        $rows = DB::select()
            ->from(self::TABLE)
            ->where('order_id', '=', (int) $order_id)
            ->order_by('op_no', 'ASC')
            ->order_by('id', 'ASC')
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->prepare($row);
        }

        return $rows;
    }

    /**
     * Поиск операций.
     *
     * @param   array   $filters    status, center_id, day, from, to, order_id, order_uuid, term, open_only, assigned, unassigned
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset, pages
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $apply = function ($query) use ($filters) {
            if ( ! empty($filters['status']))
            {
                if (is_array($filters['status']))
                {
                    $query->where('status', 'IN', $filters['status']);
                }
                else
                {
                    $query->where('status', '=', (string) $filters['status']);
                }
            }

            if ( ! empty($filters['center_id']))
            {
                $query->where('work_center_id', '=', (int) $filters['center_id']);
            }

            if ( ! empty($filters['day']))
            {
                $query->where('planned_day', '=', Erp::day($filters['day']));
            }

            if ( ! empty($filters['from']))
            {
                $query->where('planned_day', '>=', Erp::day($filters['from']));
            }

            if ( ! empty($filters['to']))
            {
                $query->where('planned_day', '<=', Erp::day($filters['to']));
            }

            if ( ! empty($filters['order_id']))
            {
                $query->where('order_id', '=', (int) $filters['order_id']);
            }

            if ( ! empty($filters['order_uuid']))
            {
                $query->where('order_uuid', '=', (string) $filters['order_uuid']);
            }

            if ( ! empty($filters['term']))
            {
                $term = '%'.mb_strtolower(trim((string) $filters['term']), 'UTF-8').'%';

                $query->and_where_open()
                    ->where(DB::expr("LOWER(name)"), 'LIKE', $term)
                    ->or_where(DB::expr("LOWER(order_number)"), 'LIKE', $term)
                    ->and_where_close();
            }

            if ( ! empty($filters['open_only']))
            {
                $query->where('status', 'IN', array('planned', 'in_progress', 'paused'));
            }

            if ( ! empty($filters['unassigned']))
            {
                $query->where('work_center_id', 'IS', NULL);
            }

            return $query;
        };

        $total = (int) $apply(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from(self::TABLE))
            ->execute()
            ->get('total', 0);

        $rows = $apply(DB::select()->from(self::TABLE))
            ->order_by('planned_day', 'ASC')
            ->order_by('op_no', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->prepare($row);
        }

        return array(
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'pages'  => (int) ceil($total / $limit),
        );
    }

    /**
     * Загрузка цеха на день: центры и их операции (диспетчерская доска).
     *
     * @param   string      $day
     * @param   int|null    $center_id
     * @return  array       day, shifts, centers (с операциями и итогами), unassigned
     */
    public function board($day = NULL, $center_id = NULL)
    {
        $day    = Erp::day($day);
        $shifts = Service_Erp408_Shifts::instance();
        $result = array(
            'day'        => $day,
            'centers'    => array(),
            'unassigned' => array(),
            'totals'     => array('operations' => 0, 'hours' => 0.0, 'done' => 0),
        );

        $centers = $center_id
            ? array_filter(array(Service_Erp408_WorkCenters::instance()->get($center_id)))
            : Service_Erp408_WorkCenters::instance()->active();

        $operations = DB::select()
            ->from(self::TABLE)
            ->where('planned_day', '=', $day)
            ->where('status', '!=', 'cancelled')
            ->order_by('op_no', 'ASC')
            ->execute()
            ->as_array();

        $by_center = array();

        foreach ($operations as $row)
        {
            $row = $this->prepare($row);

            if ($row['work_center_id'])
            {
                $by_center[(int) $row['work_center_id']][] = $row;
            }
            else
            {
                $result['unassigned'][] = $row;
            }

            $result['totals']['operations']++;
            $result['totals']['hours'] += (float) $row['planned_hours'];

            if ($row['status'] === 'done')
            {
                $result['totals']['done']++;
            }
        }

        foreach ($centers as $center)
        {
            $rows  = isset($by_center[(int) $center['id']]) ? $by_center[(int) $center['id']] : array();
            $hours = 0.0;
            $done  = 0;

            foreach ($rows as $row)
            {
                $hours += (float) $row['planned_hours'];

                if ($row['status'] === 'done')
                {
                    $done++;
                }
            }

            $available = $shifts->hours_for($center['id'], $day);

            $result['centers'][] = array(
                'center'    => $center,
                'operations'=> $rows,
                'hours'     => round($hours, 2),
                'available' => round($available, 2),
                'load_pct'  => $available > 0 ? (int) min(150, round($hours / $available * 100)) : ($hours > 0 ? 150 : 0),
                'done'      => $done,
                'is_working'=> $available > 0,
            );
        }

        return $result;
    }

    /**
     * Задания терминала: открытые операции центра на день (и просроченные).
     *
     * @param   int|null    $center_id
     * @param   string|null $day
     * @return  array
     */
    public function terminal_tasks($center_id = NULL, $day = NULL)
    {
        $day = Erp::day($day);

        $apply = function ($query) use ($center_id, $day) {
            $query->where('status', 'IN', array('planned', 'in_progress', 'paused'))
                ->where('planned_day', '<=', $day)
                ->order_by('planned_day', 'ASC')
                ->order_by('op_no', 'ASC');

            if ($center_id)
            {
                $query->where('work_center_id', '=', (int) $center_id);
            }

            return $query;
        };

        $rows = $apply(DB::select()->from(self::TABLE))->execute()->as_array();

        $tasks = array();

        foreach ($rows as $row)
        {
            $row = $this->prepare($row);

            $row['is_overdue'] = $row['planned_day'] < $day;
            $tasks[]           = $row;
        }

        return $tasks;
    }

    /**
     * Журнал отметок операции.
     *
     * @param   int|string  $operation_id
     * @param   int         $limit
     * @return  array
     */
    public function worklogs($operation_id, $limit = 100)
    {
        $rows = DB::select()
            ->from(self::LOGS)
            ->where('operation_id', '=', (int) $operation_id)
            ->order_by('at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, (int) $limit))
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $row['qty_ok']    = $row['qty_ok'] !== NULL ? (float) $row['qty_ok'] : NULL;
            $row['qty_scrap'] = $row['qty_scrap'] !== NULL ? (float) $row['qty_scrap'] : NULL;
            $row['kind_label'] = isset(Model_Erp408_Worklog::$kinds[$row['kind']])
                ? Model_Erp408_Worklog::$kinds[$row['kind']]
                : $row['kind'];
            $row['reason_label'] = $row['downtime_reason'] AND isset(Model_Erp408_Operation::$downtime_reasons[$row['downtime_reason']])
                ? Model_Erp408_Operation::$downtime_reasons[$row['downtime_reason']]
                : NULL;
            $row['label'] = $this->log_label($row);

            $rows[$index] = $row;
        }

        return $rows;
    }

    /**
     * Подпись отметки для журнала: «Выработка · годных 12 · брак 1».
     *
     * @param   array   $row
     * @return  string
     */
    protected function log_label(array $row)
    {
        $parts = array(isset($row['kind_label']) ? $row['kind_label'] : (string) $row['kind']);

        if (isset($row['qty_ok']) AND $row['qty_ok'] !== NULL AND (float) $row['qty_ok'] != 0)
        {
            $parts[] = 'годных '.rtrim(rtrim(number_format((float) $row['qty_ok'], 3, '.', ''), '0'), '.');
        }

        if (isset($row['qty_scrap']) AND $row['qty_scrap'] !== NULL AND (float) $row['qty_scrap'] != 0)
        {
            $parts[] = 'брак '.rtrim(rtrim(number_format((float) $row['qty_scrap'], 3, '.', ''), '0'), '.');
        }

        if ( ! empty($row['reason_label']))
        {
            $parts[] = $row['reason_label'];
        }

        if (isset($row['seconds']) AND $row['seconds'])
        {
            $parts[] = (int) $row['seconds'].' с';
        }

        return implode(' · ', $parts);
    }

    /**
     * Подписи рабочих центров: «СТ-01 Токарный станок №1».
     *
     * Список центров невелик и кэшируется на время запроса, поэтому подписи
     * дешевле подставлять в PHP, чем соединять таблицы в каждом списке.
     *
     * @return  array   id => подпись
     */
    protected function center_labels()
    {
        if ($this->_center_labels !== NULL)
        {
            return $this->_center_labels;
        }

        $this->_center_labels = array();

        foreach (Service_Erp408_WorkCenters::instance()->active() as $center)
        {
            $this->_center_labels[(int) $center['id']] = $center['code'].' '.$center['name'];
        }

        return $this->_center_labels;
    }

    /**
     * Сводка по смене и цеху: сколько сделано, сколько в работе, потери.
     *
     * @param   int     $days
     * @param   int|null $center_id
     * @return  array
     */
    public function stats($days = 7, $center_id = NULL)
    {
        $days = max(1, (int) $days);
        $from = date('Y-m-d', strtotime('-'.($days - 1).' days'));

        $apply = function ($query) use ($from, $center_id) {
            $query->where('planned_day', '>=', $from);

            if ($center_id)
            {
                $query->where('work_center_id', '=', (int) $center_id);
            }

            return $query;
        };

        $totals = $apply(DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'done')"), 'done'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'in_progress')"), 'in_progress'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'paused')"), 'paused'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'planned')"), 'planned'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'done' AND actual_end > (planned_day + interval '1 day'))"), 'late'),
                array(DB::expr('COALESCE(SUM(qty_ok), 0)'), 'qty_ok'),
                array(DB::expr('COALESCE(SUM(qty_scrap), 0)'), 'qty_scrap'),
                array(DB::expr('COALESCE(SUM(worked_sec), 0)'), 'worked_sec'),
                array(DB::expr('COALESCE(SUM(downtime_sec), 0)'), 'downtime_sec')
            ))
            ->from(self::TABLE)
            ->execute()
            ->current();

        $by_day = $apply(DB::select(
                'planned_day',
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'done')"), 'done'),
                array(DB::expr('COALESCE(SUM(qty_ok), 0)'), 'qty_ok')
            ))
            ->from(self::TABLE)
            ->group_by('planned_day')
            ->order_by('planned_day', 'ASC')
            ->execute()
            ->as_array();

        $by_center = $apply(DB::select(
                'work_center_id',
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'done')"), 'done'),
                array(DB::expr('COALESCE(SUM(worked_sec), 0)'), 'worked_sec'),
                array(DB::expr('COALESCE(SUM(downtime_sec), 0)'), 'downtime_sec')
            ))
            ->from(self::TABLE)
            ->group_by('work_center_id')
            ->execute()
            ->as_array();

        $centers = array();

        foreach (Service_Erp408_WorkCenters::instance()->active() as $center)
        {
            $centers[(int) $center['id']] = $center;
        }

        foreach ($by_center as $index => $row)
        {
            $id = (int) $row['work_center_id'];
            $by_center[$index]['center_label'] = isset($centers[$id]) ? $centers[$id]['code'].' '.$centers[$id]['name'] : 'Не назначено';
        }

        return array(
            'days'        => $days,
            'from'        => $from,
            'total'       => (int) $totals['total'],
            'done'        => (int) $totals['done'],
            'in_progress' => (int) $totals['in_progress'],
            'paused'      => (int) $totals['paused'],
            'planned'     => (int) $totals['planned'],
            'late'        => (int) $totals['late'],
            'qty_ok'      => (float) $totals['qty_ok'],
            'qty_scrap'   => (float) $totals['qty_scrap'],
            'worked_sec'  => (int) $totals['worked_sec'],
            'downtime_sec'=> (int) $totals['downtime_sec'],
            'by_day'      => $by_day,
            'by_center'   => $by_center,
        );
    }

    /**
     * Заказы, по которым цех ещё не получил работы: в производстве есть, а
     * операций нет. Так планировщик видит очередь «разложить маршрут» и не
     * ходит по заказам вручную.
     *
     * @param   int     $limit
     * @param   bool    $strict     TRUE — только заказы без операций вообще;
     *                              FALSE — и те, где операции не назначены
     * @return  array
     */
    public function orders_without_route($limit = 20)
    {
        return DB::select('o.id', 'o.uuid', 'o.number', 'o.title', 'o.status', 'o.qty', 'o.uom_code', 'o.target_date', 'o.tech_card_id')
            ->from(array('erp_402_orders', 'o'))
            ->join(array(self::TABLE, 'op'), 'LEFT')->on('op.order_id', '=', 'o.id')
            ->where('o.status', 'IN', array('planned', 'released', 'in_progress'))
            ->where('op.id', 'IS', NULL)
            ->order_by(DB::expr('o.target_date'), 'ASC')
            ->order_by('o.id', 'ASC')
            ->limit(max(1, (int) $limit))
            ->execute()
            ->as_array();
    }

    /**
     * Последние отметки цеха — лента событий для дашборда мастера.
     *
     * @param   int     $limit
     * @param   int|null $center_id
     * @return  array
     */
    public function recent_logs($limit = 15, $center_id = NULL)
    {
        $query = DB::select(
                array(self::LOGS.'.id', 'id'),
                array(self::LOGS.'.kind', 'kind'),
                array(self::LOGS.'.at', 'at'),
                array(self::LOGS.'.qty_ok', 'qty_ok'),
                array(self::LOGS.'.qty_scrap', 'qty_scrap'),
                array(self::LOGS.'.seconds', 'seconds'),
                array(self::LOGS.'.downtime_reason', 'downtime_reason'),
                array(self::LOGS.'.comment', 'comment'),
                array(self::LOGS.'.user_id', 'user_id'),
                array(self::TABLE.'.id', 'operation_id'),
                array(self::TABLE.'.op_no', 'op_no'),
                array(self::TABLE.'.name', 'operation_name'),
                array(self::TABLE.'.order_number', 'order_number'),
                array(self::TABLE.'.work_center_id', 'work_center_id')
            )
            ->from(self::LOGS)
            ->join(self::TABLE)->on(self::TABLE.'.id', '=', self::LOGS.'.operation_id')
            ->order_by(self::LOGS.'.at', 'DESC')
            ->order_by(self::LOGS.'.id', 'DESC')
            ->limit(max(1, (int) $limit));

        if ($center_id)
        {
            $query->where(self::TABLE.'.work_center_id', '=', (int) $center_id);
        }

        $rows = $query->execute()->as_array();

        foreach ($rows as $index => $row)
        {
            $row['kind_label'] = isset(Model_Erp408_Worklog::$kinds[$row['kind']])
                ? Model_Erp408_Worklog::$kinds[$row['kind']]
                : $row['kind'];
            $row['qty_ok']    = $row['qty_ok'] !== NULL ? (float) $row['qty_ok'] : NULL;
            $row['qty_scrap'] = $row['qty_scrap'] !== NULL ? (float) $row['qty_scrap'] : NULL;
            $row['reason_label'] = $row['downtime_reason'] AND isset(Model_Erp408_Operation::$downtime_reasons[$row['downtime_reason']])
                ? Model_Erp408_Operation::$downtime_reasons[$row['downtime_reason']]
                : NULL;

            $rows[$index] = $row;
        }

        return $rows;
    }

    // =========================================================================
    //  Правила и помощники
    // =========================================================================

    /**
     * Что можно делать с операцией — единственный источник правды для
     * интерфейса и API (кнопки не расходятся с правилами сервиса).
     *
     * @param   array|null  $operation
     * @return  array       действие => подпись
     */
    public function allowed_actions($operation = NULL)
    {
        if ( ! $operation)
        {
            return $this->_actions;
        }

        $status = isset($operation['status']) ? $operation['status'] : 'planned';

        return isset($this->_actions[$status]) ? $this->_actions[$status] : array();
    }

    /**
     * Граф допустимых переходов (для документации и тестов).
     *
     * @return  array
     */
    public function transitions()
    {
        return array(
            'planned'     => array('assign', 'start', 'finish', 'cancel', 'delete'),
            'in_progress' => array('output', 'pause', 'finish', 'downtime_start', 'cancel'),
            'paused'      => array('resume', 'output', 'finish', 'downtime_stop', 'cancel'),
            'done'        => array(),
            'cancelled'   => array(),
        );
    }

    /**
     * Проверить, что действие разрешено, и вернуть операцию.
     *
     * @param   int|string  $id
     * @param   string      $action
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    protected function require_action($id, $action)
    {
        $operation = $this->get($id);

        if ( ! $operation)
        {
            throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'invalid_transition');
        }

        if ( ! array_key_exists($action, $this->allowed_actions($operation)))
        {
            throw new Exception_Erp408_Shopfloor(
                'Для операции «:name» действие «:action» недоступно в статусе «:status»',
                array(':name' => $operation['name'], ':action' => $action, ':status' => $operation['status_label']),
                'invalid_transition'
            );
        }

        return $operation;
    }

    /**
     * Привести строку операции к удобному виду: числа числами, булевы поля —
     * bool, плюс подписи и время.
     *
     * @param   array   $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['qty_planned']   = (float) $row['qty_planned'];
        $row['qty_ok']        = (float) $row['qty_ok'];
        $row['qty_scrap']     = (float) $row['qty_scrap'];
        $row['planned_hours'] = (float) $row['planned_hours'];
        $row['norm_cost']     = (float) $row['norm_cost'];
        $row['worked_sec']    = (int) $row['worked_sec'];
        $row['downtime_sec']  = (int) $row['downtime_sec'];
        $row['is_open']       = in_array($row['status'], array('planned', 'in_progress', 'paused'), TRUE);
        $row['status_label']  = isset(Model_Erp408_Operation::$statuses[$row['status']])
            ? Model_Erp408_Operation::$statuses[$row['status']]
            : (string) $row['status'];
        $row['status_class']  = isset(Model_Erp408_Operation::$status_classes[$row['status']])
            ? Model_Erp408_Operation::$status_classes[$row['status']]
            : '';
        $row['source_label']  = isset(Model_Erp408_Operation::$sources[$row['source']])
            ? Model_Erp408_Operation::$sources[$row['source']]
            : (string) $row['source'];
        $row['remaining']     = max(0, $row['qty_planned'] - $row['qty_ok']);
        $row['progress']      = $row['qty_planned'] > 0
            ? (int) min(100, round($row['qty_ok'] / $row['qty_planned'] * 100))
            : ($row['status'] === 'done' ? 100 : 0);
        $row['worked_hours']  = Erp::hours($row['worked_sec']);
        $row['downtime_hours']= Erp::hours($row['downtime_sec']);
        $row['is_running']    = ($row['status'] === 'in_progress');
        $row['is_downtime']   = ! empty($row['downtime_started_at']);
        $row['downtime_reason_label'] = $this->last_downtime_reason($row['id'], TRUE);
        $row['actions']       = $this->allowed_actions($row);

        $labels = $this->center_labels();
        $row['work_center_label'] = ($row['work_center_id'] AND isset($labels[(int) $row['work_center_id']]))
            ? $labels[(int) $row['work_center_id']]
            : NULL;

        return $row;
    }

    /**
     * Сколько секунд операция «идёт» прямо сейчас (с последнего старта или
     * возобновления, если после него не было паузы).
     *
     * @param   int     $operation_id
     * @return  int
     */
    protected function open_seconds($operation_id)
    {
        $row = DB::select()
            ->from(self::LOGS)
            ->where('operation_id', '=', (int) $operation_id)
            ->order_by('at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        if ( ! $row OR ! in_array($row['kind'], array('start', 'resume'), TRUE))
        {
            return 0;
        }

        return max(0, time() - strtotime((string) $row['at']));
    }

    /**
     * Причина последнего простоя (для подписи) или её код.
     *
     * @param   int     $operation_id
     * @param   bool    $label
     * @return  string|null
     */
    protected function last_downtime_reason($operation_id, $label = FALSE)
    {
        $row = DB::select()
            ->from(self::LOGS)
            ->where('operation_id', '=', (int) $operation_id)
            ->where('kind', 'IN', array('downtime_start', 'downtime_end'))
            ->where('downtime_reason', 'IS NOT', NULL)
            ->order_by('at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        if ( ! $row)
        {
            return NULL;
        }

        if ( ! $label)
        {
            return (string) $row['downtime_reason'];
        }

        $reasons = Model_Erp408_Operation::$downtime_reasons;

        return isset($reasons[$row['downtime_reason']]) ? $reasons[$row['downtime_reason']] : (string) $row['downtime_reason'];
    }

    /**
     * Добавить отметку в журнал цеха.
     *
     * @param   int         $operation_id
     * @param   string      $kind
     * @param   int|null    $user_id
     * @param   array       $extra  qty_ok, qty_scrap, seconds, downtime_reason, comment
     * @return  void
     */
    protected function log($operation_id, $kind, $user_id = NULL, array $extra = array())
    {
        DB::insert(self::LOGS, array(
                'operation_id', 'user_id', 'kind', 'at', 'day',
                'qty_ok', 'qty_scrap', 'seconds', 'downtime_reason', 'comment', 'created_at',
            ))
            ->values(array(
                (int) $operation_id,
                $user_id ? (int) $user_id : NULL,
                (string) $kind,
                date('c'),
                date('Y-m-d'),
                isset($extra['qty_ok']) ? (float) $extra['qty_ok'] : NULL,
                isset($extra['qty_scrap']) ? (float) $extra['qty_scrap'] : NULL,
                isset($extra['seconds']) ? (int) $extra['seconds'] : NULL,
                isset($extra['downtime_reason']) ? (string) $extra['downtime_reason'] : NULL,
                isset($extra['comment']) AND $extra['comment'] !== '' ? (string) $extra['comment'] : NULL,
                date('c'),
            ))
            ->execute();
    }

    /**
     * Обновить в операциях шапку заказа (номер и название могли измениться).
     *
     * @param   Model_Erp402_Order  $order
     * @return  void
     */
    protected function refresh_order_fields(Model_Erp402_Order $order)
    {
        DB::update(self::TABLE)
            ->set(array(
                'order_number' => (string) $order->number,
                'order_title'  => (string) $order->title,
                'order_uuid'   => (string) $order->uuid,
                'updated_at'   => date('c'),
            ))
            ->where('order_id', '=', (int) $order->id)
            ->execute();
    }

    /**
     * День, на который ставится маршрут: срок заказа (или сегодня), сдвинутый
     * на ближайший рабочий день календаря.
     *
     * @param   Model_Erp402_Order  $order
     * @return  string
     */
    protected function planned_day(Model_Erp402_Order $order)
    {
        $day = $order->target_date ? Erp::day($order->target_date) : date('Y-m-d');

        if ($day > date('Y-m-d'))
        {
            $day = date('Y-m-d');
        }

        try
        {
            return $this->shifts()->next_working_day($day);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            return $day;
        }
    }

    /**
     * @return  Service_Erp408_Shifts
     */
    protected function shifts()
    {
        return Service_Erp408_Shifts::instance();
    }

    /**
     * Строка заказа из таблицы производства — только чтение (владелец данных
     * остаётся erp402; цех не пишет в чужие таблицы).
     *
     * @param   int     $order_id
     * @return  array|null
     */
    protected function order_row($order_id)
    {
        return DB::select()
            ->from('erp_402_orders')
            ->where('id', '=', (int) $order_id)
            ->limit(1)
            ->execute()
            ->current();
    }

    /**
     * Следующий номер операции в маршруте заказа.
     *
     * @param   int     $order_id
     * @return  int
     */
    protected function next_op_no($order_id)
    {
        $max = (int) DB::select(array(DB::expr('COALESCE(MAX(op_no), 0)'), 'max_no'))
            ->from(self::TABLE)
            ->where('order_id', '=', (int) $order_id)
            ->execute()
            ->get('max_no', 0);

        return $max + 10;
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * Отдельно от операций: сделанное в цехе должно быть видно на общей
     * временной линии заказа, а не только в терминале.
     *
     * @param   string      $action
     * @param   string      $entity_id
     * @param   array       $before
     * @param   array       $after
     * @param   string|null $message
     * @param   int|null    $user_id
     * @param   array|null  $operation
     * @param   string      $entity_type  operation (по умолчанию) или order — планирование маршрута
     * @return  void
     */
    protected function journal($action, $entity_id, array $before, array $after, $message = NULL, $user_id = NULL, ?array $operation = NULL, $entity_type = 'operation')
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $label = $operation ? ('Операция '.$operation['op_no'].' · '.$operation['name']) : NULL;

        try
        {
            Service_Erp119_Journal::instance()->change($entity_type, $entity_id, $action, $before, $after, array(
                'module'       => 'erp408',
                'entity_label' => $label,
                'message'      => $message,
                'user_id'      => $user_id,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp408: журнал недоступен — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * Публикация события цеха: шина `erp208` доводит его до подписчиков —
     * прежде всего до производства (`erp402`).
     *
     * В полезную нагрузку кладём и агрегат по заказу: подписчику не нужно
     * ходить в таблицы цеха, чтобы понять, все ли операции закрыты.
     *
     * @param   string      $event
     * @param   array|null  $operation
     * @param   array       $extra
     * @return  void
     */
    protected function publish($event, $operation = NULL, array $extra = array())
    {
        $payload = array(
            'at' => date('c'),
        );

        if ($operation)
        {
            $payload += array(
                'operation_id'  => (int) $operation['id'],
                'operation_uuid'=> (string) $operation['uuid'],
                'op_no'         => (int) $operation['op_no'],
                'name'          => (string) $operation['name'],
                'order_id'      => (int) $operation['order_id'],
                'order_uuid'    => (string) $operation['order_uuid'],
                'order_number'  => (string) $operation['order_number'],
                'order_title'   => (string) $operation['order_title'],
                'center_id'     => $operation['work_center_id'] ? (int) $operation['work_center_id'] : NULL,
                'planned_day'   => (string) $operation['planned_day'],
                'qty_planned'   => (float) $operation['qty_planned'],
                'qty_ok'        => (float) $operation['qty_ok'],
                'qty_scrap'     => (float) $operation['qty_scrap'],
                'worked_sec'    => (int) $operation['worked_sec'],
                'downtime_sec'  => (int) $operation['downtime_sec'],
                'status'        => (string) $operation['status'],
            );

            $payload += $this->order_aggregate($operation['order_id']);
        }

        $payload += $extra;

        // Публикация одна: Event::fire идёт через шину erp208
        // (Service_Erp208_EventBus), а та сама пишет событие в журнал и
        // вызывает слушателей с плоским payload. Второй вызов publish()
        // давал две записи на каждое событие, а обёртка {'payload': …} ломала
        // слушателей: они читают поля на верхнем уровне.
        if ( ! class_exists('Service_Erp208_EventBus'))
        {
            return;
        }

        try
        {
            Event::fire($event, $payload);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp408: ошибка слушателя события :event — :error',
                array(':event' => $event, ':error' => $e->getMessage()));
        }
    }

    /**
     * Сколько операций заказа закрыто: производство по этому счётчику понимает,
     * готов ли заказ к выпуску.
     *
     * @param   int     $order_id
     * @return  array   operations_total, operations_done, operations_open, qty_ok_total, qty_scrap_total
     */
    protected function order_aggregate($order_id)
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'done')"), 'done'),
                array(DB::expr("COUNT(*) FILTER (WHERE status IN ('planned', 'in_progress', 'paused'))"), 'open'),
                array(DB::expr('COALESCE(SUM(qty_ok), 0)'), 'qty_ok'),
                array(DB::expr('COALESCE(SUM(qty_scrap), 0)'), 'qty_scrap')
            )
            ->from(self::TABLE)
            ->where('order_id', '=', (int) $order_id)
            ->where('status', '!=', 'cancelled')
            ->execute()
            ->current();

        return array(
            'operations_total' => (int) $row['total'],
            'operations_done'  => (int) $row['done'],
            'operations_open'  => (int) $row['open'],
            'qty_ok_total'     => (float) $row['qty_ok'],
            'qty_scrap_total'  => (float) $row['qty_scrap'],
        );
    }

    // =========================================================================
    //  Аналитика участка
    // =========================================================================

    /**
     * Производственная статистика за период — сырьё для OEE и разбора простоев.
     *
     * Цех владеет операциями и журналом работ, поэтому агрегаты считаются здесь,
     * а модуль аналитики (`erp801_analytics`) считает по ним коэффициенты
     * (доступность, производительность, качество) и рисует Парето простоев.
     *
     * Возвращается «сырьё», а не готовый OEE: формулы — предмет аналитики, их
     * можно менять там, не трогая цех. Простои по причинам берутся из журнала
     * работ (`downtime_end`, поле `seconds`): это единственное место, где у
     * простоя есть и причина, и длительность.
     *
     * @param   string      $from       начало периода (ГГГГ-ММ-ДД)
     * @param   string      $to         конец периода (ГГГГ-ММ-ДД)
     * @param   int|null    $center_id  рабочий центр (NULL — все)
     * @return  array                   totals, by_center, downtime_reasons
     */
    public function production_stats($from = NULL, $to = NULL, $center_id = NULL)
    {
        $from = Erp::day($from, date('Y-m-d', strtotime('-30 days')));
        $to   = Erp::day($to, date('Y-m-d'));

        $apply = function ($query) use ($from, $to, $center_id) {
            $query->where('planned_day', '>=', $from)
                  ->where('planned_day', '<=', $to);

            if ($center_id)
            {
                $query->where('work_center_id', '=', (int) $center_id);
            }

            return $query;
        };

        $fields = array(
            array(DB::expr('COUNT(*)'), 'operations'),
            array(DB::expr("COUNT(*) FILTER (WHERE status = 'done')"), 'done'),
            array(DB::expr("COUNT(*) FILTER (WHERE status = 'done' AND actual_end > (planned_day + interval '1 day'))"), 'late'),
            array(DB::expr('COALESCE(SUM(qty_planned), 0)'), 'qty_planned'),
            array(DB::expr('COALESCE(SUM(qty_ok), 0)'), 'qty_ok'),
            array(DB::expr('COALESCE(SUM(qty_scrap), 0)'), 'qty_scrap'),
            array(DB::expr('COALESCE(SUM(planned_hours), 0)'), 'planned_hours'),
            array(DB::expr('COALESCE(SUM(worked_sec), 0)'), 'worked_sec'),
            array(DB::expr('COALESCE(SUM(downtime_sec), 0)'), 'downtime_sec'),
        );

        $totals = $apply(DB::select_array($fields))->from(self::TABLE)->execute()->current();

        $labels = $this->center_labels();

        $rows = $apply(DB::select_array(array_merge(array('work_center_id'), $fields)))
            ->from(self::TABLE)
            ->group_by('work_center_id')
            ->order_by('work_center_id', 'ASC')
            ->execute()
            ->as_array();

        $by_center = array();

        foreach ($rows as $row)
        {
            $id = (int) $row['work_center_id'];

            $by_center[] = array(
                'work_center_id'    => $id ? $id : NULL,
                'work_center_label' => ($id AND isset($labels[$id])) ? $labels[$id] : ($id ? 'Центр #'.$id : 'Без центра'),
                'operations'        => (int) $row['operations'],
                'done'              => (int) $row['done'],
                'late'              => (int) $row['late'],
                'qty_planned'       => (float) $row['qty_planned'],
                'qty_ok'            => (float) $row['qty_ok'],
                'qty_scrap'         => (float) $row['qty_scrap'],
                'planned_hours'     => (float) $row['planned_hours'],
                'worked_sec'        => (int) $row['worked_sec'],
                'downtime_sec'      => (int) $row['downtime_sec'],
            );
        }

        // Простои по причинам: события журнала работ с длительностью.
        $logs = DB::select(
                array(self::LOGS.'.downtime_reason', 'reason'),
                array(DB::expr('COUNT(*)'), 'events'),
                array(DB::expr('COALESCE(SUM('.self::LOGS.'.seconds), 0)'), 'seconds')
            )
            ->from(array(self::LOGS, self::LOGS))
            ->join(array(self::TABLE, self::TABLE), 'INNER')
            ->on(self::TABLE.'.id', '=', self::LOGS.'.operation_id')
            ->where(self::LOGS.'.downtime_reason', 'IS NOT', NULL)
            ->where(self::LOGS.'.seconds', 'IS NOT', NULL)
            ->where(self::TABLE.'.planned_day', '>=', $from)
            ->where(self::TABLE.'.planned_day', '<=', $to);

        if ($center_id)
        {
            $logs->where(self::TABLE.'.work_center_id', '=', (int) $center_id);
        }

        $rows = $logs->group_by(self::LOGS.'.downtime_reason')
            ->order_by(DB::expr('COALESCE(SUM('.self::LOGS.'.seconds), 0)'), 'DESC')
            ->execute()
            ->as_array();

        $reasons = array();
        $downtime_total = 0.0;

        foreach ($rows as $row)
        {
            $seconds = (int) $row['seconds'];
            $downtime_total += $seconds;

            $reasons[] = array(
                'reason'       => (string) $row['reason'],
                'reason_label' => isset(Model_Erp408_Operation::$downtime_reasons[$row['reason']])
                    ? Model_Erp408_Operation::$downtime_reasons[$row['reason']]
                    : (string) $row['reason'],
                'events'   => (int) $row['events'],
                'seconds'  => $seconds,
            );
        }

        foreach ($reasons as $index => $reason)
        {
            $reasons[$index]['share'] = $downtime_total > 0
                ? round($reason['seconds'] / $downtime_total * 100, 1)
                : 0.0;
        }

        return array(
            'from'   => $from,
            'to'     => $to,
            'totals' => array(
                'operations'    => (int) $totals['operations'],
                'done'          => (int) $totals['done'],
                'late'          => (int) $totals['late'],
                'qty_planned'   => (float) $totals['qty_planned'],
                'qty_ok'        => (float) $totals['qty_ok'],
                'qty_scrap'     => (float) $totals['qty_scrap'],
                'planned_hours' => (float) $totals['planned_hours'],
                'worked_sec'    => (int) $totals['worked_sec'],
                'downtime_sec'  => (int) $totals['downtime_sec'],
            ),
            'by_center'        => $by_center,
            'downtime_reasons' => $reasons,
        );
    }

}
