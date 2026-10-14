<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Рабочие центры и участки (erp408_shopfloor).
 *
 * Сервис — единственная точка входа в справочник центров: контроллеры, API,
 * терминал и инструменты MCP работают через него, поэтому правила (код
 * уникален, центр с операциями не удаляется, выключенный центр нельзя
 * назначать) описаны один раз.
 *
 * Поиск идёт по `search_text`: кластер PostgreSQL живёт в локали `C`, где
 * `ILIKE`/`lower()` не складывают регистр кириллицы, поэтому текст заранее
 * приводится к нижнему регистру в PHP и сравнивается через `LIKE`.
 *
 * @package    Erp408
 * @category   Services
 */
class Service_Erp408_WorkCenters
{
    const TABLE = 'erp_408_work_centers';

    /** @var Service_Erp408_WorkCenters|null */
    protected static $_instance;

    /**
     * @return  Service_Erp408_WorkCenters
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
     * Список центров.
     *
     * @param   array   $filters    term, kind, active, workshop
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset, pages
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $apply = function ($query) use ($filters) {
            if ( ! empty($filters['term']))
            {
                $query->where('search_text', 'LIKE', '%'.mb_strtolower(trim((string) $filters['term']), 'UTF-8').'%');
            }

            if ( ! empty($filters['kind']))
            {
                $query->where('kind', '=', (string) $filters['kind']);
            }

            if (isset($filters['active']) AND $filters['active'] !== '' AND $filters['active'] !== NULL)
            {
                $query->where('is_active', '=', Erp::bool($filters['active']));
            }

            if ( ! empty($filters['workshop']))
            {
                $query->where('workshop', '=', (string) $filters['workshop']);
            }

            return $query;
        };

        $total = (int) $apply(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from(self::TABLE))
            ->execute()
            ->get('total', 0);

        $rows = $apply(DB::select()->from(self::TABLE))
            ->order_by('is_active', 'DESC')
            ->order_by('kind', 'ASC')
            ->order_by('code', 'ASC')
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
     * Активные центры — для селектов и терминала.
     *
     * @return  array
     */
    public function active()
    {
        $rows = DB::select()
            ->from(self::TABLE)
            ->where('is_active', '=', TRUE)
            ->order_by('kind', 'ASC')
            ->order_by('code', 'ASC')
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->prepare($row);
        }

        return $rows;
    }

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

        // На uuid похоже не всё: код центра («СТ-01») в этом месте уронил бы
        // PostgreSQL на приведении типа, поэтому проверяем форму строки.
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
     * Модель центра (для форм и связей).
     *
     * @param   int|string  $id
     * @return  Model_Erp408_WorkCenter
     * @throws  Exception_Erp408_Shopfloor
     */
    public function model($id)
    {
        $center = ORM::factory('Erp408_WorkCenter', (int) $id);

        if ( ! $center->loaded())
        {
            throw new Exception_Erp408_Shopfloor('Рабочий центр не найден', NULL, 'no_work_center');
        }

        return $center;
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Создать центр.
     *
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function create(array $data, $user_id = NULL)
    {
        $data = $this->normalize($data);

        if ($this->code_taken($data['code']))
        {
            throw new Exception_Erp408_Shopfloor(
                'Центр с кодом :code уже есть',
                array(':code' => $data['code']),
                'invalid_transition'
            );
        }

        $data['created_by'] = $user_id ? (int) $user_id : NULL;
        $data['created_at'] = date('c');

        $id = $this->insert($data);

        $center = $this->get($id);

        $this->journal('create', $center, $user_id);

        return $center;
    }

    /**
     * Изменить центр.
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
            throw new Exception_Erp408_Shopfloor('Рабочий центр не найден', NULL, 'no_work_center');
        }

        $data = $this->normalize($data, $before);

        if ($this->code_taken($data['code'], $before['id']))
        {
            throw new Exception_Erp408_Shopfloor(
                'Центр с кодом :code уже есть',
                array(':code' => $data['code']),
                'invalid_transition'
            );
        }

        $data['updated_at'] = date('c');

        DB::update(self::TABLE)
            ->set($data)
            ->where('id', '=', (int) $before['id'])
            ->execute();

        $after = $this->get($before['id']);

        $this->journal('update', $after, $user_id, $before, $after);

        return $after;
    }

    /**
     * Удалить центр: только если по нему нет операций (иначе предложить
     * выключить — история цеха должна остаться целой).
     *
     * @param   int|string  $id
     * @return  bool
     * @throws  Exception_Erp408_Shopfloor
     */
    public function delete($id)
    {
        $center = $this->get($id);

        if ( ! $center)
        {
            return FALSE;
        }

        $operations = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_408_operations')
            ->where('work_center_id', '=', (int) $center['id'])
            ->execute()
            ->get('total', 0);

        if ($operations > 0)
        {
            throw new Exception_Erp408_Shopfloor(
                'По центру :name есть операции (:count) — выключите его вместо удаления',
                array(':name' => $center['name'], ':count' => $operations),
                'invalid_transition'
            );
        }

        DB::delete(self::TABLE)->where('id', '=', (int) $center['id'])->execute();

        return TRUE;
    }

    /**
     * Включить или выключить центр.
     *
     * @param   int|string  $id
     * @param   bool        $active
     * @param   int|null    $user_id
     * @return  array
     */
    public function set_active($id, $active, $user_id = NULL)
    {
        return $this->update($id, array('is_active' => Erp::bool($active)), $user_id);
    }

    // =========================================================================
    //  Сводки
    // =========================================================================

    /**
     * Состояние справочника и загрузка на день.
     *
     * @param   string  $day    ГГГГ-ММ-ДД
     * @return  array
     */
    public function stats($day = NULL)
    {
        $day = Erp::day($day);

        $totals = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COUNT(*) FILTER (WHERE is_active)'), 'active'),
                array(DB::expr("COUNT(*) FILTER (WHERE kind = 'area')"), 'areas')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        $planned = DB::select(
                'work_center_id',
                array(DB::expr('COUNT(*)'), 'operations'),
                array(DB::expr('COALESCE(SUM(planned_hours), 0)'), 'hours')
            )
            ->from('erp_408_operations')
            ->where('planned_day', '=', $day)
            ->where('status', 'NOT IN', array('cancelled'))
            ->group_by('work_center_id')
            ->execute()
            ->as_array();

        $load = array();

        foreach ($planned as $row)
        {
            $load[(int) $row['work_center_id']] = array(
                'operations' => (int) $row['operations'],
                'hours'      => (float) $row['hours'],
            );
        }

        return array(
            'total'  => (int) $totals['total'],
            'active' => (int) $totals['active'],
            'areas'  => (int) $totals['areas'],
            'load'   => $load,
            'day'    => $day,
        );
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Привести строку центра к видам, удобным PHP: булевы поля — настоящими
     * bool, числа — числами.
     *
     * @param   array   $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['is_active']         = Erp::bool(isset($row['is_active']) ? $row['is_active'] : FALSE);
        $row['capacity_per_hour'] = isset($row['capacity_per_hour']) ? (float) $row['capacity_per_hour'] : NULL;
        $row['cost_per_hour']     = isset($row['cost_per_hour']) ? (float) $row['cost_per_hour'] : NULL;
        $row['kind_label']        = isset(Model_Erp408_WorkCenter::$kinds[$row['kind']])
            ? Model_Erp408_WorkCenter::$kinds[$row['kind']]
            : (string) $row['kind'];

        return $row;
    }

    /**
     * Нормализовать данные формы.
     *
     * @param   array   $data
     * @param   array   $current
     * @return  array
     */
    protected function normalize(array $data, array $current = array())
    {
        $merge = function ($key, $default = NULL) use ($data, $current) {
            if (array_key_exists($key, $data))
            {
                return $data[$key];
            }

            return array_key_exists($key, $current) ? $current[$key] : $default;
        };

        $result = array(
            'code'              => mb_strtoupper(trim((string) $merge('code', '')), 'UTF-8'),
            'name'              => trim((string) $merge('name', '')),
            'kind'              => (string) $merge('kind', 'center'),
            'workshop'          => trim((string) $merge('workshop', '')),
            'capacity_per_hour' => $merge('capacity_per_hour') !== NULL AND $merge('capacity_per_hour') !== '' ? Erp::num($merge('capacity_per_hour')) : NULL,
            'cost_per_hour'     => $merge('cost_per_hour') !== NULL AND $merge('cost_per_hour') !== '' ? Erp::num($merge('cost_per_hour')) : NULL,
            'is_active'         => Erp::bool($merge('is_active', TRUE)),
            'note'              => trim((string) $merge('note', '')),
        );

        if ($result['code'] === '' OR $result['name'] === '')
        {
            throw new Exception_Erp408_Shopfloor('Укажите код и название рабочего центра', NULL, 'invalid_transition');
        }

        if ( ! isset(Model_Erp408_WorkCenter::$kinds[$result['kind']]))
        {
            $result['kind'] = 'center';
        }

        $result['workshop']    = $result['workshop'] !== '' ? $result['workshop'] : NULL;
        $result['note']        = $result['note'] !== '' ? $result['note'] : NULL;
        $result['search_text'] = mb_strtolower(implode(' ', array_filter(array(
            $result['code'],
            $result['name'],
            $result['workshop'],
        ))), 'UTF-8');

        return $result;
    }

    /**
     * @param   string      $code
     * @param   int|null    $except_id
     * @return  bool
     */
    protected function code_taken($code, $except_id = NULL)
    {
        $query = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from(self::TABLE)
            ->where('code', '=', (string) $code);

        if ($except_id)
        {
            $query->where('id', '!=', (int) $except_id);
        }

        return (int) $query->execute()->get('total', 0) > 0;
    }

    /**
     * @param   array   $data
     * @return  int
     */
    protected function insert(array $data)
    {
        $result = DB::insert(self::TABLE, array_keys($data))
            ->values(array_values($data))
            ->execute();

        return is_array($result) ? (int) reset($result) : (int) $result;
    }

    /**
     * Запись в журнал действий (erp119) — справочник тоже меняется людьми.
     *
     * @param   string      $action
     * @param   array       $after
     * @param   int|null    $user_id
     * @param   array       $before
     * @param   array       $changed
     * @return  void
     */
    protected function journal($action, array $after, $user_id = NULL, array $before = array(), array $changed = array())
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('work_center', $after['uuid'], $action, $before, $changed, array(
                'module'       => 'erp408',
                'entity_label' => $after['code'].' · '.$after['name'],
                'user_id'      => $user_id,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp408: журнал недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
