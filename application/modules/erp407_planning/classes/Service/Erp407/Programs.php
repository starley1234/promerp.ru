<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Производственные программы (erp407_planning).
 *
 * Сервис — единственная точка входа в программы и их строки: контроллеры, JSON
 * API, инструменты MCP и задачи работают через него, поэтому правила описаны
 * один раз:
 *
 *  - номер программы уникален и генерируется последовательностью ПП-ГГГГ-НННННН;
 *  - строку можно добавить только в открытую программу (черновик или
 *    утверждённую), закрытая программа — история;
 *  - строка с номенклатурой подтягивает её подпись и единицу измерения из
 *    реестра, чтобы план читался людьми, а не кодами;
 *  - утверждение программы публикует `erp407.program.approved` — по этому
 *    событию планировщик запускает расчёт, а закупки видят горизонт.
 *
 * Расчёт потребности живёт в `Service_Erp407_Mrp` (наследник этого сервиса:
 * ему нужны `get()`, `lines()`, журнал и события).
 *
 * @package    Erp407
 * @category   Services
 */
class Service_Erp407_Programs
{
    const TABLE = 'erp_407_programs';
    const LINES = 'erp_407_program_lines';
    const SEQUENCE = 'erp_407_program_number_seq';

    /** @var array Синглтоны по классу: сервис расчёта — наследник, и у него
     *       должен быть свой экземпляр (иначе Mrp::instance() вернул бы
     *       программы и «не нашёл» calculate()). */
    protected static $_instances = array();

    /**
     * @return  Service_Erp407_Programs|Service_Erp407_Mrp
     */
    public static function instance()
    {
        $class = get_called_class();

        if ( ! isset(self::$_instances[$class]))
        {
            self::$_instances[$class] = new $class;
        }

        return self::$_instances[$class];
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Список программ с фильтрами.
     *
     * @param   array   $filters    status, search, period_from, period_to
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp407_Program
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp407_Program');

        $this->apply_filters($query, $filters);

        return $query
            ->order_by('period_from', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all()
            ->as_array();
    }

    /**
     * Сколько программ подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = ORM::factory('Erp407_Program');

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
            $statuses = (array) $filters['status'];
            $query->where('status', 'IN', $statuses);
        }

        if ( ! empty($filters['period_from']))
        {
            $query->where('period_to', '>=', (string) $filters['period_from']);
        }

        if ( ! empty($filters['period_to']))
        {
            $query->where('period_from', '<=', (string) $filters['period_to']);
        }

        if ( ! empty($filters['search']))
        {
            $query->where('search_text', 'LIKE', '%'.$this->normalize((string) $filters['search']).'%');
        }

        return $query;
    }

    /**
     * Программа по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp407_Program|null
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

        $program = ORM::factory('Erp407_Program')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $program->loaded() ? $program : NULL;
    }

    /**
     * Строки программы по порядку.
     *
     * @param   int     $program_id
     * @return  array   Model_Erp407_ProgramLine
     */
    public function lines($program_id)
    {
        return ORM::factory('Erp407_ProgramLine')
            ->where('program_id', '=', (int) $program_id)
            ->order_by('line_no', 'ASC')
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Строка программы по идентификатору.
     *
     * @param   int     $line_id
     * @return  Model_Erp407_ProgramLine|null
     */
    public function line($line_id)
    {
        $line = ORM::factory('Erp407_ProgramLine', (int) $line_id);

        return $line->loaded() ? $line : NULL;
    }

    /**
     * Следующий номер строки (шаг 10 — как в составе заказа).
     *
     * @param   int     $program_id
     * @return  int
     */
    public function next_line_no($program_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(MAX(line_no), 0)'), 'line_no'))
            ->from(self::LINES)
            ->where('program_id', '=', (int) $program_id)
            ->execute()
            ->current();

        return (int) $row['line_no'] + 10;
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Создать программу.
     *
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp407_Program
     * @throws  Exception_Erp407_Planning
     */
    public function create(array $data, $user_id = NULL)
    {
        $program = ORM::factory('Erp407_Program');

        $program->number       = isset($data['number']) && $data['number'] !== '' ? (string) $data['number'] : $this->number();
        $program->title        = isset($data['title']) ? (string) $data['title'] : 'Производственная программа';
        $program->period_from  = isset($data['period_from']) ? (string) $data['period_from'] : date('Y-m-d');
        $program->period_to    = isset($data['period_to']) ? (string) $data['period_to'] : date('Y-m-d', strtotime('+1 month'));
        $program->status       = isset($data['status']) ? (string) $data['status'] : 'draft';
        $program->source       = isset($data['source']) ? (string) $data['source'] : 'manual';
        $program->horizon_days = isset($data['horizon_days']) ? (int) $data['horizon_days'] : 30;
        $program->note         = isset($data['note']) ? (string) $data['note'] : NULL;
        $program->created_by   = $user_id ? (int) $user_id : NULL;
        $program->search_text  = $this->search_text($program);
        $program->save();

        $this->_journal($program, 'create', array(), $program->as_array(), 'Программа создана: '.$program->number);

        Event::fire('erp407.program.created', array(
            'id'     => (int) $program->id,
            'uuid'   => (string) $program->uuid,
            'title'  => (string) $program->title,
            'number' => (string) $program->number,
            'status' => (string) $program->status,
        ));

        return $program;
    }

    /**
     * Изменить программу.
     *
     * @param   int|Model_Erp407_Program  $program
     * @param   array                     $data
     * @param   int|null                  $user_id
     * @return  Model_Erp407_Program
     * @throws  Exception_Erp407_Planning
     */
    public function update($program, array $data, $user_id = NULL)
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ( ! $program->is_editable())
        {
            throw new Exception_Erp407_Planning(
                'Программа :number закрыта — правки запрещены',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        $before = $program->as_array();

        foreach (array('title', 'period_from', 'period_to', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $program->$field = (string) $data[$field];
            }
        }

        if (isset($data['horizon_days']))
        {
            $program->horizon_days = (int) $data['horizon_days'];
        }

        $program->search_text = $this->search_text($program);
        $program->save();

        $this->_journal($program, 'update', $before, $program->as_array(), 'Программа изменена');

        Event::fire('erp407.program.updated', array(
            'id'     => (int) $program->id,
            'uuid'   => (string) $program->uuid,
            'title'  => (string) $program->title,
            'number' => (string) $program->number,
        ));

        return $program;
    }

    /**
     * Удалить программу вместе со строками и снимками расчёта.
     *
     * @param   int|Model_Erp407_Program  $program
     * @param   int|null                  $user_id
     * @return  bool
     * @throws  Exception_Erp407_Planning
     */
    public function delete($program, $user_id = NULL)
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ($program->status === 'approved' AND ! $user_id)
        {
            throw new Exception_Erp407_Planning(
                'Утверждённую программу :number удаляет только человек с правами (подтверждение)',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        $payload = array(
            'id'     => (int) $program->id,
            'uuid'   => (string) $program->uuid,
            'title'  => (string) $program->title,
            'number' => (string) $program->number,
        );

        DB::delete(self::LINES)->where('program_id', '=', (int) $program->id)->execute();
        $program->delete();

        Event::fire('erp407.program.deleted', $payload);

        return TRUE;
    }

    /**
     * Утвердить программу: с этого момента по ней считают потребность.
     *
     * @param   int|Model_Erp407_Program  $program
     * @param   int|null                  $user_id
     * @return  Model_Erp407_Program
     * @throws  Exception_Erp407_Planning
     */
    public function approve($program, $user_id = NULL)
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ($program->status === 'approved')
        {
            return $program;
        }

        if ($program->status === 'closed')
        {
            throw new Exception_Erp407_Planning(
                'Программа :number закрыта — утвердить заново нельзя',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        if ( ! $this->lines($program->id))
        {
            throw new Exception_Erp407_Planning(
                'В программе :number нет строк — утверждать нечего',
                array(':number' => $program->number),
                'no_demand'
            );
        }

        $before = $program->as_array();

        $program->status      = 'approved';
        $program->updated_at  = date('Y-m-d H:i:s');
        $program->save();

        $this->_journal($program, 'status', $before, array('status' => 'approved'), 'Программа утверждена');

        Event::fire('erp407.program.approved', array(
            'id'     => (int) $program->id,
            'uuid'   => (string) $program->uuid,
            'title'  => (string) $program->title,
            'number' => (string) $program->number,
            'status' => 'approved',
        ));

        return $program;
    }

    /**
     * Закрыть программу: период отработан, правки и расчёты запрещены.
     *
     * @param   int|Model_Erp407_Program  $program
     * @param   int|null                  $user_id
     * @return  Model_Erp407_Program
     * @throws  Exception_Erp407_Planning
     */
    public function close($program, $user_id = NULL)
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ($program->status === 'closed')
        {
            return $program;
        }

        $open = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_407_proposals')
            ->where('program_id', '=', (int) $program->id)
            ->where('status', 'IN', array('new', 'approved'))
            ->execute()
            ->get('total', 0);

        if ((int) $open > 0)
        {
            throw new Exception_Erp407_Planning(
                'По программе :number есть нерешённые предложения (:count) — сначала утвердите или отклоните их',
                array(':number' => $program->number, ':count' => (int) $open),
                'invalid_transition'
            );
        }

        $before = $program->as_array();

        $program->status     = 'closed';
        $program->updated_at = date('Y-m-d H:i:s');
        $program->save();

        $this->_journal($program, 'status', $before, array('status' => 'closed'), 'Программа закрыта');

        Event::fire('erp407.program.closed', array(
            'id'     => (int) $program->id,
            'uuid'   => (string) $program->uuid,
            'title'  => (string) $program->title,
            'number' => (string) $program->number,
            'status' => 'closed',
        ));

        return $program;
    }

    /**
     * Добавить строку программы.
     *
     * @param   int     $program_id
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp407_ProgramLine
     * @throws  Exception_Erp407_Planning
     */
    public function add_line($program_id, array $data, $user_id = NULL)
    {
        $program = $this->get($program_id);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ( ! $program->is_editable())
        {
            throw new Exception_Erp407_Planning(
                'Программа :number закрыта — строки добавить нельзя',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        $line = ORM::factory('Erp407_ProgramLine');

        $line->program_id   = (int) $program->id;
        $line->line_no      = isset($data['line_no']) ? (int) $data['line_no'] : $this->next_line_no($program->id);
        $line->item_id      = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $line->name         = isset($data['name']) ? trim((string) $data['name']) : '';
        $line->code         = isset($data['code']) ? (string) $data['code'] : NULL;
        $line->qty          = isset($data['qty']) ? (float) $data['qty'] : 0;
        $line->uom_code     = isset($data['uom_code']) ? (string) $data['uom_code'] : 'шт';
        $line->due_date     = ! empty($data['due_date']) ? (string) $data['due_date'] : (string) $program->period_to;
        $line->tech_card_id = ! empty($data['tech_card_id']) ? (int) $data['tech_card_id'] : NULL;
        $line->order_id     = ! empty($data['order_id']) ? (int) $data['order_id'] : NULL;
        $line->status       = isset($data['status']) ? (string) $data['status'] : 'planned';
        $line->note         = isset($data['note']) ? (string) $data['note'] : NULL;

        if ($line->name === '' AND $line->item_id)
        {
            $line->name = $this->item_name($line->item_id);
        }

        if ($line->name === '')
        {
            throw new Exception_Erp407_Planning('Строка программы: укажите изделие или наименование', NULL, 'not_found');
        }

        $line->save();

        $this->_journal($program, 'update', array(), array('line' => $line->name), 'Добавлена строка программы: '.$line->name);

        return $line;
    }

    /**
     * Изменить строку программы.
     *
     * @param   int     $line_id
     * @param   array   $data
     * @return  Model_Erp407_ProgramLine
     * @throws  Exception_Erp407_Planning
     */
    public function update_line($line_id, array $data)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp407_Planning('Строка программы не найдена', NULL, 'not_found');
        }

        if ( ! empty($data['item_id']) AND empty($data['name']))
        {
            $line->name     = $this->item_name((int) $data['item_id']);
            $line->item_id  = (int) $data['item_id'];
        }

        foreach (array('name', 'code', 'uom_code', 'due_date', 'status', 'note') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = ($data[$field] === '' AND in_array($field, array('due_date', 'note'), TRUE)) ? NULL : (string) $data[$field];
            }
        }

        foreach (array('qty', 'tech_card_id', 'order_id') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $line->$field = $data[$field] === '' ? NULL : (float) $data[$field];
            }
        }

        $line->save();

        return $line;
    }

    /**
     * Удалить строку программы.
     *
     * @param   int     $line_id
     * @return  bool
     * @throws  Exception_Erp407_Planning
     */
    public function remove_line($line_id)
    {
        $line = $this->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp407_Planning('Строка программы не найдена', NULL, 'not_found');
        }

        $program = $this->get($line->program_id);

        if ($program AND ! $program->is_editable())
        {
            throw new Exception_Erp407_Planning(
                'Программа :number закрыта — строки удалить нельзя',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        $line->delete();

        return TRUE;
    }

    /**
     * Собрать программу из открытых заказов производства: строки = остатки
     * заказов. Повторный вызов не задваивает строки (сверка по `order_id`).
     *
     * @param   int     $program_id
     * @param   int|null $user_id
     * @return  array   added, skipped, warnings
     * @throws  Exception_Erp407_Planning
     */
    public function fill_from_orders($program_id, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            throw new Exception_Erp407_Planning('Модуль производства (erp402) недоступен', NULL, 'dependency');
        }

        $program = $this->get($program_id);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        $existing = array();

        foreach ($this->lines($program->id) as $line)
        {
            if ($line->order_id)
            {
                $existing[] = (int) $line->order_id;
            }
        }

        // `search()` у erp402 возвращает конверт {rows, total, limit, offset},
        // а строки — ассоциативные массивы (выборка идёт через DB::select).
        // Без этого в цикл попадали сам конверт и счётчик: «Attempt to read
        // property id on array». Ошибка вылезала только на реальных открытых
        // заказах, поэтому форма ответа разбирается явно.
        $page = Service_Erp402_Orders::instance()->search(array(
            'status' => array('planned', 'released', 'in_progress'),
        ), 200);

        $orders = (is_array($page) AND isset($page['rows'])) ? $page['rows'] : (array) $page;
        $orders = array_map(function ($row) { return is_array($row) ? (object) $row : $row; }, $orders);

        $added = 0;
        $skipped = 0;

        foreach ($orders as $order)
        {
            if (in_array((int) $order->id, $existing, TRUE))
            {
                $skipped++;
                continue;
            }

            $qty = (float) $order->qty - (float) $order->qty_done;

            if ($qty <= 0)
            {
                $skipped++;
                continue;
            }

            $this->add_line($program->id, array(
                'item_id'      => $order->item_id ? (int) $order->item_id : NULL,
                'name'         => $order->title ? (string) $order->title : (string) $order->number,
                'code'         => (string) $order->number,
                'qty'          => $qty,
                'uom_code'     => (string) $order->uom_code,
                'due_date'     => $order->target_date ? (string) $order->target_date : (string) $program->period_to,
                'tech_card_id' => $order->tech_card_id ? (int) $order->tech_card_id : NULL,
                'order_id'     => (int) $order->id,
                'note'         => 'Из заказа '.$order->number,
            ), $user_id);

            $added++;
        }

        $program->source     = 'orders';
        $program->updated_at = date('Y-m-d H:i:s');
        $program->save();

        return array('added' => $added, 'skipped' => $skipped, 'warnings' => array());
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Номер программы из последовательности (ПП-ГГГГ-НННННН).
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::query(Database::SELECT, "SELECT nextval('".self::SEQUENCE."') AS n")->execute()->current();

        return sprintf('ПП-%s-%06d', date('Y'), (int) $row['n']);
    }

    /**
     * Текст для поиска (в PHP: кластер в локали C не складывает регистр кириллицы).
     *
     * @param   Model_Erp407_Program    $program
     * @return  string
     */
    public function search_text(Model_Erp407_Program $program)
    {
        return mb_substr($this->normalize($program->number.' '.$program->title.' '.$program->note), 0, 500);
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
     * Наименование позиции по идентификатору номенклатуры.
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
     * Запись в журнал действий (erp119).
     *
     * @param   Model_Erp407_Program    $program
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   string|null             $message
     * @return  void
     */
    protected function _journal(Model_Erp407_Program $program, $action, array $before, array $after, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('program', (string) $program->uuid, $action, $before, $after, array(
                'module'       => 'erp407',
                'entity_label' => (string) $program->number.' · '.(string) $program->title,
                'message'      => $message,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp407: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * Новый uuid для снимка расчёта.
     *
     * @return  string
     */
    protected function _uuid()
    {
        $row = DB::query(Database::SELECT, 'SELECT gen_random_uuid() AS uuid')->execute()->current();

        return (string) $row['uuid'];
    }

    /**
     * Удалить прежний снимок расчёта программы.
     *
     * @param   int     $program_id
     * @return  void
     */
    protected function _delete_requirements($program_id)
    {
        DB::delete('erp_407_requirements')->where('program_id', '=', (int) $program_id)->execute();
    }
}
