<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Предложения плана (erp407_planning).
 *
 * Расчёт говорит, чего не хватает; предложение — это решение и его судьба.
 * Сервис ведёт предложение по состояниям и передаёт его в исполнение:
 *
 *   new ──approve──► approved ──export──► exported ──done──► done
 *    └──cancel──► cancelled
 *
 * Передача в исполнение (главное обещание модуля: «предложения превращаются в
 * заявки одной кнопкой»):
 *
 *  - `produce` — создаётся заказ производства (erp402) в статусе черновика:
 *    планировщик утверждает программу, а состав и маршрут цеха появляются
 *    при обычном запуске заказа, как и для заказов, созданных руками;
 *  - `purchase` — если модуль закупок (erp403) подключён, вызывается его
 *    сервис; пока его нет, предложение помечается `exported` с пометкой
 *    `waiting:erp403` и уходит событие `erp407.proposal.exported` — закупщик
 *    видит очередь, а система остаётся честной: заявка ещё не создана.
 *
 * Числа для плана берутся из снимка расчёта, поэтому связь «предложение →
 * строка потребности → прогон» сохраняется и после перерасчёта.
 *
 * @package    Erp407
 * @category   Services
 */
class Service_Erp407_Proposals
{
    const TABLE = 'erp_407_proposals';
    const SEQUENCE = 'erp_407_proposal_number_seq';

    /** @var Service_Erp407_Proposals|null */
    protected static $_instance;

    /**
     * @return  Service_Erp407_Proposals
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
     * Список предложений.
     *
     * @param   array   $filters    status, kind, program_id, item_id, search
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp407_Proposal
     */
    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $query = ORM::factory('Erp407_Proposal');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('status', 'ASC')
            ->order_by('order_date', 'ASC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all()
            ->as_array();
    }

    /**
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp407_Proposal');

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

        if ( ! empty($filters['kind']))
        {
            $query->where('kind', '=', (string) $filters['kind']);
        }

        if ( ! empty($filters['program_id']))
        {
            $query->where('program_id', '=', (int) $filters['program_id']);
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('item_id', '=', (int) $filters['item_id']);
        }

        if ( ! empty($filters['search']))
        {
            $query->where(DB::expr('LOWER(name)'), 'LIKE', '%'.mb_strtolower(trim((string) $filters['search'])).'%');
        }

        return $query;
    }

    /**
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp407_Proposal|null
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

        $proposal = ORM::factory('Erp407_Proposal')->where($ref[0], '=', $ref[1])->find();

        return $proposal->loaded() ? $proposal : NULL;
    }

    /**
     * Предложения программы с группировкой по состоянию.
     *
     * @param   int     $program_id
     * @return  array   status => строки
     */
    public function by_status($program_id, $limit = 200)
    {
        $grouped = array();

        foreach ($this->search(array('program_id' => (int) $program_id), $limit) as $proposal)
        {
            $grouped[$proposal->status][] = $proposal;
        }

        return $grouped;
    }

    // =========================================================================
    //  Создание из расчёта
    // =========================================================================

    /**
     * Создать предложения по дефицитам последнего расчёта программы.
     *
     * Повторный вызов не задваивает предложения: по каждой позиции проверяется,
     * нет ли уже открытого предложения (new/approved/exported) на этот же
     * `item_id` из этой программы.
     *
     * @param   int|Model_Erp407_Program    $program
     * @param   array                       $filters    only_late, supply_type, min_qty
     * @param   int|null                    $user_id
     * @return  array   created, skipped, ids
     * @throws  Exception_Erp407_Planning
     */
    public function create_from_requirements($program, array $filters = array(), $user_id = NULL)
    {
        $programs = Service_Erp407_Programs::instance();
        $mrp      = Service_Erp407_Mrp::instance();

        $program = ($program instanceof Model_Erp407_Program) ? $program : $programs->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        $requirements = $mrp->requirements($program, array(
            'only_deficit' => TRUE,
            'supply_type'  => isset($filters['supply_type']) ? $filters['supply_type'] : NULL,
        ), 500);

        if ( ! $requirements)
        {
            return array('created' => 0, 'skipped' => 0, 'ids' => array());
        }

        $open = $this->_open_items($program->id);

        $created = 0;
        $skipped = 0;
        $ids     = array();

        foreach ($requirements as $requirement)
        {
            if ( ! empty($filters['only_late']) AND ! $requirement->is_late())
            {
                $skipped++;
                continue;
            }

            if ( ! empty($filters['min_qty']) AND (float) $requirement->order_qty < (float) $filters['min_qty'])
            {
                $skipped++;
                continue;
            }

            $key = $this->_item_key($requirement->item_id, $requirement->name);

            if (isset($open[$key]))
            {
                $skipped++;
                continue;
            }

            $proposal = $this->create(array(
                'requirement_id' => (int) $requirement->id,
                'program_id'     => (int) $program->id,
                'kind'           => $requirement->supply_type,
                'item_id'        => $requirement->item_id ? (int) $requirement->item_id : NULL,
                'name'           => (string) $requirement->name,
                'code'           => $requirement->code ? (string) $requirement->code : NULL,
                'uom_code'       => (string) $requirement->uom_code,
                'qty'            => (float) $requirement->order_qty,
                'due_date'       => $requirement->due_date ? (string) $requirement->due_date : NULL,
                'order_date'     => $requirement->order_date ? (string) $requirement->order_date : NULL,
                'priority'       => $requirement->is_late() ? 1 : 3,
                'note'           => $requirement->parent_name
                    ? 'Из расчёта программы '.$program->number.' (состав: '.$requirement->parent_name.')'
                    : 'Из расчёта программы '.$program->number,
            ), $user_id);

            $open[$key] = TRUE;

            $ids[]   = (int) $proposal->id;
            $created++;
        }

        return array('created' => $created, 'skipped' => $skipped, 'ids' => $ids);
    }

    /**
     * Создать предложение вручную.
     *
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp407_Proposal
     * @throws  Exception_Erp407_Planning
     */
    public function create(array $data, $user_id = NULL)
    {
        $proposal = ORM::factory('Erp407_Proposal');

        $proposal->number         = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $proposal->requirement_id = ! empty($data['requirement_id']) ? (int) $data['requirement_id'] : NULL;
        $proposal->program_id     = ! empty($data['program_id']) ? (int) $data['program_id'] : NULL;
        $proposal->kind           = isset($data['kind']) ? (string) $data['kind'] : 'purchase';
        $proposal->item_id        = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $proposal->name           = isset($data['name']) ? trim((string) $data['name']) : '';
        $proposal->code           = isset($data['code']) ? (string) $data['code'] : NULL;
        $proposal->uom_code       = isset($data['uom_code']) ? (string) $data['uom_code'] : 'шт';
        $proposal->qty            = isset($data['qty']) ? (float) $data['qty'] : 0;
        $proposal->due_date       = ! empty($data['due_date']) ? (string) $data['due_date'] : NULL;
        $proposal->order_date     = ! empty($data['order_date']) ? (string) $data['order_date'] : NULL;
        $proposal->priority       = isset($data['priority']) ? (int) $data['priority'] : 3;
        $proposal->note           = isset($data['note']) ? (string) $data['note'] : NULL;
        $proposal->created_by     = $user_id ? (int) $user_id : NULL;

        if ($proposal->name === '' AND $proposal->item_id AND class_exists('Service_Erp227_Registry'))
        {
            $proposal->name = (string) Service_Erp407_Programs::instance()->item_name($proposal->item_id);
        }

        if ($proposal->name === '')
        {
            throw new Exception_Erp407_Planning('Предложение: укажите позицию', NULL, 'not_found');
        }

        if ((float) $proposal->qty <= 0)
        {
            throw new Exception_Erp407_Planning('Предложение: количество должно быть больше нуля', NULL, 'invalid_transition');
        }

        $proposal->save();

        $this->_journal($proposal, 'create', array(), $proposal->as_array(),
            'Предложение '.$proposal->number.': '.$proposal->kind_label().' «'.$proposal->name.'» '.$proposal->qty.' '.$proposal->uom_code);

        Event::fire('erp407.proposal.created', $this->_payload($proposal));

        return $proposal;
    }

    /**
     * Изменить предложение (до утверждения).
     *
     * @param   int     $id
     * @param   array   $data
     * @return  Model_Erp407_Proposal
     * @throws  Exception_Erp407_Planning
     */
    public function update($id, array $data)
    {
        $proposal = $this->get($id);

        if ( ! $proposal)
        {
            throw new Exception_Erp407_Planning('Предложение не найдено', NULL, 'not_found');
        }

        if ($proposal->is_locked())
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number уже :status — правки запрещены',
                array(':number' => $proposal->number, ':status' => $proposal->status_label()),
                'proposal_locked'
            );
        }

        $before = $proposal->as_array();

        foreach (array('name', 'code', 'uom_code', 'due_date', 'order_date', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $proposal->$field = ($data[$field] === '') ? NULL : (string) $data[$field];
            }
        }

        foreach (array('qty', 'priority', 'item_id') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $proposal->$field = $data[$field] === '' ? NULL : (float) $data[$field];
            }
        }

        $proposal->save();

        $this->_journal($proposal, 'update', $before, $proposal->as_array(), 'Предложение изменено');

        return $proposal;
    }

    /**
     * Утвердить предложение — с этого момента оно идёт в покрытие потребности.
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp407_Proposal
     * @throws  Exception_Erp407_Planning
     */
    public function approve($id, $user_id = NULL)
    {
        $proposal = $this->get($id);

        if ( ! $proposal)
        {
            throw new Exception_Erp407_Planning('Предложение не найдено', NULL, 'not_found');
        }

        if ($proposal->status === 'approved' OR $proposal->status === 'exported')
        {
            return $proposal;
        }

        if ($proposal->status !== 'new')
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number в состоянии «:status» утвердить нельзя',
                array(':number' => $proposal->number, ':status' => $proposal->status_label()),
                'invalid_transition'
            );
        }

        $before = $proposal->as_array();

        $proposal->status      = 'approved';
        $proposal->approved_by = $user_id ? (int) $user_id : NULL;
        $proposal->approved_at = date('Y-m-d H:i:s');
        $proposal->updated_at  = date('Y-m-d H:i:s');
        $proposal->save();

        $this->_journal($proposal, 'status', $before, array('status' => 'approved'), 'Предложение утверждено');

        Event::fire('erp407.proposal.approved', $this->_payload($proposal));

        return $proposal;
    }

    /**
     * Передать предложение в исполнение.
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  array   proposal, target, warning
     * @throws  Exception_Erp407_Planning
     */
    public function export($id, $user_id = NULL)
    {
        $proposal = $this->get($id);

        if ( ! $proposal)
        {
            throw new Exception_Erp407_Planning('Предложение не найдено', NULL, 'not_found');
        }

        if ($proposal->status === 'exported')
        {
            return array('proposal' => $proposal, 'target' => (string) $proposal->target_ref, 'warning' => NULL);
        }

        if ($proposal->status !== 'approved')
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number нельзя передать: состояние «:status»',
                array(':number' => $proposal->number, ':status' => $proposal->status_label()),
                'invalid_transition'
            );
        }

        $target  = NULL;
        $warning = NULL;

        if ($proposal->kind === 'produce')
        {
            $target = $this->_export_to_production($proposal);
        }
        else
        {
            $target = $this->_export_to_procurement($proposal, $warning);
        }

        $before = $proposal->as_array();

        $proposal->status     = 'exported';
        $proposal->target_ref = $target;
        $proposal->updated_at = date('Y-m-d H:i:s');
        $proposal->save();

        $this->_journal($proposal, 'status', $before, array(
            'status'     => 'exported',
            'target_ref' => $target,
        ), 'Предложение передано в исполнение: '.$target);

        Event::fire('erp407.proposal.exported', $this->_payload($proposal) + array('target' => $target));

        return array('proposal' => $proposal, 'target' => $target, 'warning' => $warning);
    }

    /**
     * Отметить предложение исполненным (потребность закрыта).
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp407_Proposal
     * @throws  Exception_Erp407_Planning
     */
    public function complete($id, $user_id = NULL)
    {
        $proposal = $this->get($id);

        if ( ! $proposal)
        {
            throw new Exception_Erp407_Planning('Предложение не найдено', NULL, 'not_found');
        }

        if ( ! in_array($proposal->status, array('exported', 'approved'), TRUE))
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number в состоянии «:status» нельзя закрыть',
                array(':number' => $proposal->number, ':status' => $proposal->status_label()),
                'invalid_transition'
            );
        }

        $before = $proposal->as_array();

        $proposal->status     = 'done';
        $proposal->done_at    = date('Y-m-d H:i:s');
        $proposal->updated_at = date('Y-m-d H:i:s');
        $proposal->save();

        $this->_journal($proposal, 'status', $before, array('status' => 'done'), 'Предложение исполнено');

        Event::fire('erp407.proposal.done', $this->_payload($proposal));

        return $proposal;
    }

    /**
     * Отклонить предложение.
     *
     * @param   int     $id
     * @param   string|null $reason
     * @param   int|null $user_id
     * @return  Model_Erp407_Proposal
     * @throws  Exception_Erp407_Planning
     */
    public function cancel($id, $reason = NULL, $user_id = NULL)
    {
        $proposal = $this->get($id);

        if ( ! $proposal)
        {
            throw new Exception_Erp407_Planning('Предложение не найдено', NULL, 'not_found');
        }

        if (in_array($proposal->status, array('done', 'cancelled'), TRUE))
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number уже «:status»',
                array(':number' => $proposal->number, ':status' => $proposal->status_label()),
                'invalid_transition'
            );
        }

        $before = $proposal->as_array();

        $proposal->status     = 'cancelled';
        $proposal->note       = trim((string) $proposal->note.($reason ? "\nОтклонено: ".$reason : ''));
        $proposal->updated_at = date('Y-m-d H:i:s');
        $proposal->save();

        $this->_journal($proposal, 'status', $before, array('status' => 'cancelled'),
            'Предложение отклонено'.($reason ? ': '.$reason : ''));

        Event::fire('erp407.proposal.cancelled', $this->_payload($proposal) + array('reason' => $reason));

        return $proposal;
    }

    /**
     * Сводка по предложениям для дашборда.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'new')"), 'new'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'approved')"), 'approved'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'exported')"), 'exported'),
                array(DB::expr("COUNT(*) FILTER (WHERE kind = 'produce' AND status IN ('new','approved'))"), 'produce'),
                array(DB::expr("COUNT(*) FILTER (WHERE kind = 'purchase' AND status IN ('new','approved'))"), 'purchase'),
                array(DB::expr("COUNT(*) FILTER (WHERE order_date < CURRENT_DATE AND status = 'new')"), 'late')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        return array(
            'total'    => (int) $row['total'],
            'new'      => (int) $row['new'],
            'approved' => (int) $row['approved'],
            'exported' => (int) $row['exported'],
            'produce'  => (int) $row['produce'],
            'purchase' => (int) $row['purchase'],
            'late'     => (int) $row['late'],
        );
    }

    /**
     * Номер предложения (ПЗ-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ПЗ-%s-%06d', date('Y'), (int) $row['n']);
    }

    // =========================================================================
    //  Передача в исполнение
    // =========================================================================

    /**
     * Создать заказ производства (erp402) по предложению.
     *
     * @param   Model_Erp407_Proposal   $proposal
     * @return  string  ссылка вида erp402:12
     * @throws  Exception_Erp407_Planning
     */
    protected function _export_to_production(Model_Erp407_Proposal $proposal)
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            throw new Exception_Erp407_Planning(
                'Предложение :number не передано: модуль производства недоступен',
                array(':number' => $proposal->number),
                'export_failed'
            );
        }

        $orders = Service_Erp402_Orders::instance();

        $order = $orders->create(array(
            'title'   => $proposal->name,
            'item_id' => $proposal->item_id ? (int) $proposal->item_id : NULL,
            'qty'     => (float) $proposal->qty,
            'uom_code' => (string) $proposal->uom_code,
            'target_date' => $proposal->due_date ? (string) $proposal->due_date : NULL,
            'priority'    => (int) $proposal->priority,
            'note'        => 'Предложение плана '.$proposal->number.' (срок заказа '.$proposal->order_date.')',
        ));

        return 'erp402:'.(int) $order->id;
    }

    /**
     * Передать закупку в модуль закупок (erp403), если он подключён.
     *
     * @param   Model_Erp407_Proposal   $proposal
     * @param   string|null             $warning
     * @return  string
     */
    protected function _export_to_procurement(Model_Erp407_Proposal $proposal, & $warning)
    {
        if (class_exists('Service_Erp403_Requests'))
        {
            try
            {
                $request = Service_Erp403_Requests::instance()->create(array(
                    'item_id'    => $proposal->item_id ? (int) $proposal->item_id : NULL,
                    'name'       => (string) $proposal->name,
                    'qty'        => (float) $proposal->qty,
                    'uom_code'   => (string) $proposal->uom_code,
                    'need_date'  => $proposal->due_date ? (string) $proposal->due_date : NULL,
                    'source'     => 'erp407',
                    'source_ref' => (string) $proposal->number,
                    'note'       => (string) $proposal->note,
                ));

                return 'erp403:'.(int) $request->id;
            }
            catch (Exception $e)
            {
                $warning = 'Заявка закупки не создана: '.$e->getMessage();
            }
        }
        else
        {
            $warning = 'Модуль закупок (erp403) не подключён: предложение лежит в очереди закупщика (waiting:erp403)';
        }

        return 'waiting:erp403';
    }

    /**
     * Открытые предложения программы: item_id|имя => TRUE.
     *
     * @param   int     $program_id
     * @return  array
     */
    protected function _open_items($program_id)
    {
        $open = array();

        foreach (DB::select('item_id', 'name')
            ->from(self::TABLE)
            ->where('program_id', '=', (int) $program_id)
            ->where('status', 'IN', array('new', 'approved', 'exported'))
            ->execute()
            ->as_array() as $row)
        {
            $open[$this->_item_key($row['item_id'], $row['name'])] = TRUE;
        }

        return $open;
    }

    /**
     * Ключ позиции: по номенклатуре, иначе по наименованию.
     *
     * @param   int|null    $item_id
     * @param   string      $name
     * @return  string
     */
    protected function _item_key($item_id, $name)
    {
        return $item_id ? 'item:'.(int) $item_id : 'name:'.mb_strtolower(trim((string) $name));
    }

    /**
     * Payload события по предложению.
     *
     * @param   Model_Erp407_Proposal   $proposal
     * @return  array
     */
    protected function _payload(Model_Erp407_Proposal $proposal)
    {
        return array(
            'id'         => (int) $proposal->id,
            'uuid'       => (string) $proposal->uuid,
            'title'      => (string) $proposal->number.' · '.$proposal->name,
            'number'     => (string) $proposal->number,
            'kind'       => (string) $proposal->kind,
            'item_id'    => (int) $proposal->item_id,
            'qty'        => (float) $proposal->qty,
            'status'     => (string) $proposal->status,
            'program_id' => (int) $proposal->program_id,
            'order_date' => $proposal->order_date ? (string) $proposal->order_date : NULL,
        );
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp407_Proposal   $proposal
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   string|null             $message
     * @return  void
     */
    protected function _journal(Model_Erp407_Proposal $proposal, $action, array $before, array $after, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('proposal', (string) $proposal->uuid, $action, $before, $after, array(
                'module'       => 'erp407',
                'entity_label' => (string) $proposal->number.' · '.(string) $proposal->name,
                'message'      => $message,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp407: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
