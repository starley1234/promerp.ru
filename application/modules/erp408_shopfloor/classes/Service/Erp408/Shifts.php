<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Смены и рабочий календарь (erp408_shopfloor).
 *
 * Смена — шаблон («первая, 08:00–16:00»), календарь — конкретные дни: сколько
 * часов работает центр (или весь цех) в этот день. Планировщик и мастер
 * спрашивают у сервиса одно и то же: «сколько часов доступно» и «какой
 * следующий рабочий день» — поэтому ответы живут здесь, а не в контроллерах.
 *
 * Календарь можно не заполнять: если на день записей нет, сервис считает
 * будни рабочими по 8 часов (в цехе это обычная норма), а выходные — нет.
 *
 * @package    Erp408
 * @category   Services
 */
class Service_Erp408_Shifts
{
    const TABLE = 'erp_408_shifts';
    const CALENDAR = 'erp_408_calendar';

    /** @var Service_Erp408_Shifts|null */
    protected static $_instance;

    /** @var float Часы дня по умолчанию, когда календарь пуст */
    const DEFAULT_HOURS = 8.0;

    /**
     * @return  Service_Erp408_Shifts
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
    //  Смены
    // =========================================================================

    /**
     * @param   array   $filters    active
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, pages
     */
    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $apply = function ($query) use ($filters) {
            if (isset($filters['active']) AND $filters['active'] !== '' AND $filters['active'] !== NULL)
            {
                $query->where('is_active', '=', Erp::bool($filters['active']));
            }

            return $query;
        };

        $total = (int) $apply(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from(self::TABLE))
            ->execute()->get('total', 0);

        $rows = $apply(DB::select()->from(self::TABLE))
            ->order_by('sort_order', 'ASC')
            ->order_by('start_time', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->prepare($row);
        }

        return array('rows' => $rows, 'total' => $total, 'pages' => (int) ceil($total / $limit));
    }

    /**
     * @return  array
     */
    public function active()
    {
        return $this->search(array('active' => TRUE), 50, 0)['rows'];
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
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
     */
    public function create(array $data, $user_id = NULL)
    {
        $data = $this->normalize($data);
        $data['created_at'] = date('c');

        $result = DB::insert(self::TABLE, array_keys($data))->values(array_values($data))->execute();
        $id     = is_array($result) ? (int) reset($result) : (int) $result;

        $shift = $this->get($id);

        $this->journal('create', $shift, $user_id);

        return $shift;
    }

    /**
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
            throw new Exception_Erp408_Shopfloor('Смена не найдена', NULL, 'invalid_transition');
        }

        $data = $this->normalize($data, $before);
        $data['updated_at'] = date('c');

        DB::update(self::TABLE)->set($data)->where('id', '=', (int) $before['id'])->execute();

        $after = $this->get($before['id']);

        $this->journal('update', $after, $user_id, $before, $after);

        return $after;
    }

    /**
     * @param   int|string  $id
     * @return  bool
     * @throws  Exception_Erp408_Shopfloor
     */
    public function delete($id)
    {
        $shift = $this->get($id);

        if ( ! $shift)
        {
            return FALSE;
        }

        $busy = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_408_operations')
            ->where('shift_id', '=', (int) $shift['id'])
            ->execute()->get('total', 0);

        if ($busy > 0)
        {
            throw new Exception_Erp408_Shopfloor(
                'Смена :name используется в операциях (:count) — выключите её вместо удаления',
                array(':name' => $shift['name'], ':count' => $busy),
                'invalid_transition'
            );
        }

        DB::delete(self::TABLE)->where('id', '=', (int) $shift['id'])->execute();

        return TRUE;
    }

    /**
     * Смена, в которую попадает время.
     *
     * @param   string|null $time   ЧЧ:ММ (по умолчанию — сейчас)
     * @return  array|null
     */
    public function current($time = NULL)
    {
        $time = $time ? substr((string) $time, 0, 5) : date('H:i');

        $row = DB::select()
            ->from(self::TABLE)
            ->where('is_active', '=', TRUE)
            ->where('start_time', '<=', $time)
            ->order_by('start_time', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        if ( ! $row)
        {
            // До первой смены суток работает та, что начинается позже всех
            $row = DB::select()
                ->from(self::TABLE)
                ->where('is_active', '=', TRUE)
                ->order_by('start_time', 'DESC')
                ->limit(1)
                ->execute()
                ->current();
        }

        return $row ? $this->prepare($row) : NULL;
    }

    // =========================================================================
    //  Календарь
    // =========================================================================

    /**
     * Записи календаря за период.
     *
     * @param   string      $from    ГГГГ-ММ-ДД
     * @param   string      $to      ГГГГ-ММ-ДД
     * @param   int|null    $center_id
     * @return  array
     */
    public function calendar($from, $to, $center_id = NULL)
    {
        $query = DB::select()
            ->from(self::CALENDAR)
            ->where('day', '>=', Erp::day($from))
            ->where('day', '<=', Erp::day($to))
            ->order_by('day', 'ASC')
            ->order_by('work_center_id', 'ASC')
            ->order_by('shift_id', 'ASC');

        if ($center_id)
        {
            $query->where('work_center_id', '=', (int) $center_id);
        }

        $rows = $query->execute()->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index]['is_working'] = Erp::bool($row['is_working']);
            $rows[$index]['hours']      = (float) $row['hours'];
        }

        return $rows;
    }

    /**
     * Отметить день в календаре (вставка или обновление одной записи).
     *
     * @param   string      $day
     * @param   int|null    $center_id
     * @param   int|null    $shift_id
     * @param   bool        $is_working
     * @param   float|null  $hours
     * @param   string|null $note
     * @return  array
     */
    public function set_day($day, $center_id = NULL, $shift_id = NULL, $is_working = TRUE, $hours = NULL, $note = NULL)
    {
        $day        = Erp::day($day);
        $center_id  = $center_id ? (int) $center_id : 0;
        $shift_id   = $shift_id ? (int) $shift_id : 0;
        $is_working = Erp::bool($is_working);
        $hours      = $hours === NULL ? ($is_working ? self::DEFAULT_HOURS : 0) : Erp::num($hours);

        // COALESCE вместо «= NULL»: NULL в сравнении всегда даёт неизвестно,
        // поэтому «день целиком по цеху» ищем явным приведением к нулю.
        $existing = DB::query(Database::SELECT,
            'SELECT id FROM erp_408_calendar
             WHERE day = CAST(:day AS date)
               AND COALESCE(work_center_id, 0) = :center
               AND COALESCE(shift_id, 0) = :shift
             LIMIT 1')
            ->param(':day', $day)
            ->param(':center', $center_id)
            ->param(':shift', $shift_id)
            ->execute()
            ->current();

        if ($existing)
        {
            DB::query(Database::UPDATE,
                'UPDATE erp_408_calendar
                 SET is_working = :working, hours = :hours, note = :note, updated_at = now()
                 WHERE id = :id')
                ->param(':working', $is_working)
                ->param(':hours', $hours)
                ->param(':note', $note)
                ->param(':id', (int) $existing['id'])
                ->execute();

            return array('day' => $day, 'updated' => TRUE, 'id' => (int) $existing['id']);
        }

        DB::query(Database::INSERT,
            'INSERT INTO erp_408_calendar (day, work_center_id, shift_id, is_working, hours, note, created_at)
             VALUES (CAST(:day AS date), :center, :shift, :working, :hours, :note, now())')
            ->param(':day', $day)
            ->param(':center', $center_id ? $center_id : NULL)
            ->param(':shift', $shift_id ? $shift_id : NULL)
            ->param(':working', $is_working)
            ->param(':hours', $hours)
            ->param(':note', $note)
            ->execute();

        return array('day' => $day, 'updated' => FALSE);
    }

    /**
     * Сколько часов доступно центру в этот день.
     *
     * Порядок: записи центра → общие записи цеха → норма буднего дня.
     *
     * @param   int|null    $center_id
     * @param   string      $day
     * @return  float
     */
    public function hours_for($center_id, $day)
    {
        $day = Erp::day($day);

        foreach (array((int) $center_id, 0) as $scope)
        {
            $row = DB::query(Database::SELECT,
                'SELECT COALESCE(SUM(hours), 0) AS hours, COUNT(*) AS rows
                 FROM erp_408_calendar
                 WHERE day = CAST(:day AS date)
                   AND is_working
                   AND COALESCE(work_center_id, 0) = :scope')
                ->param(':day', $day)
                ->param(':scope', $scope)
                ->execute()
                ->current();

            if ($row AND (int) $row['rows'] > 0)
            {
                return (float) $row['hours'];
            }
        }

        return $this->weekday_default($day);
    }

    /**
     * @param   string      $day
     * @param   int|null    $center_id
     * @return  bool
     */
    public function is_working($day, $center_id = NULL)
    {
        return $this->hours_for($center_id, $day) > 0;
    }

    /**
     * Ближайший рабочий день (включая сам день, если он рабочий).
     *
     * @param   string      $day
     * @param   int|null    $center_id
     * @param   int         $max_days
     * @return  string
     * @throws  Exception_Erp408_Shopfloor
     */
    public function next_working_day($day, $center_id = NULL, $max_days = 60)
    {
        $candidate = strtotime(Erp::day($day));

        for ($i = 0; $i <= $max_days; $i++)
        {
            $date = date('Y-m-d', $candidate);

            if ($this->is_working($date, $center_id))
            {
                return $date;
            }

            $candidate = strtotime('+1 day', $candidate);
        }

        throw new Exception_Erp408_Shopfloor(
            'В календаре нет рабочих дней на :days дней вперёд',
            array(':days' => $max_days),
            'not_working_day'
        );
    }

    /**
     * Заполнить календарь на период: будни — рабочие, суббота и воскресенье —
     * выходные. Повторный вызов обновляет часы, а не плодит записи.
     *
     * @param   string  $from
     * @param   string  $to
     * @param   array   $center_ids
     * @param   array   $shift_ids
     * @return  int     сколько дней заполнено
     */
    public function generate($from, $to, array $center_ids = array(), array $shift_ids = array())
    {
        $from  = Erp::day($from);
        $to    = Erp::day($to);
        $shifts = $shift_ids ? $shift_ids : array_column($this->active(), 'id');

        if ( ! $shifts)
        {
            return 0;
        }

        $sql = "
            INSERT INTO erp_408_calendar (day, work_center_id, shift_id, is_working, hours, created_at)
            SELECT d::date,
                   c.id,
                   s.id,
                   (EXTRACT(ISODOW FROM d) < 6),
                   CASE WHEN EXTRACT(ISODOW FROM d) < 6 THEN s.duration_hours ELSE 0 END,
                   now()
            FROM generate_series(CAST(:from_date AS date), CAST(:to_date AS date), interval '1 day') AS d
            CROSS JOIN erp_408_work_centers c
            CROSS JOIN erp_408_shifts s
            WHERE c.id = ANY(CAST(:centers AS int[]))
              AND s.id = ANY(CAST(:shifts AS int[]))
              AND c.is_active
              AND s.is_active
            ON CONFLICT (day, COALESCE(work_center_id, 0), COALESCE(shift_id, 0))
            DO UPDATE SET is_working = EXCLUDED.is_working,
                          hours      = EXCLUDED.hours,
                          updated_at = now()
        ";

        $centers = $center_ids ? array_map('intval', $center_ids) : array_column($this->active_centers(), 'id');

        if ( ! $centers)
        {
            return 0;
        }

        DB::query(Database::INSERT, $sql)
            ->param(':from_date', $from)
            ->param(':to_date', $to)
            ->param(':centers', '{'.implode(',', $centers).'}')
            ->param(':shifts', '{'.implode(',', array_map('intval', $shifts)).'}')
            ->execute();

        return (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   array   $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['is_active']      = Erp::bool(isset($row['is_active']) ? $row['is_active'] : FALSE);
        $row['duration_hours'] = isset($row['duration_hours']) ? (float) $row['duration_hours'] : 0.0;
        $row['start_hhmm']     = isset($row['start_time']) ? substr((string) $row['start_time'], 0, 5) : '';
        $row['end_hhmm']       = isset($row['end_time']) ? substr((string) $row['end_time'], 0, 5) : '';
        $row['label']          = $row['start_hhmm'].'–'.$row['end_hhmm'];

        return $row;
    }

    /**
     * @return  array
     */
    protected function active_centers()
    {
        if (class_exists('Service_Erp408_WorkCenters'))
        {
            return Service_Erp408_WorkCenters::instance()->active();
        }

        return array();
    }

    /**
     * Норма дня, когда календарь пуст: будни рабочие, выходные — нет.
     *
     * @param   string  $day
     * @return  float
     */
    protected function weekday_default($day)
    {
        $isodow = (int) date('N', strtotime($day));

        return $isodow < 6 ? self::DEFAULT_HOURS : 0.0;
    }

    /**
     * @param   array   $data
     * @param   array   $current
     * @return  array
     * @throws  Exception_Erp408_Shopfloor
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
            'code'           => mb_strtoupper(trim((string) $merge('code', '')), 'UTF-8'),
            'name'           => trim((string) $merge('name', '')),
            'start_time'     => substr((string) $merge('start_time', '08:00'), 0, 5),
            'end_time'       => substr((string) $merge('end_time', '16:00'), 0, 5),
            'duration_hours' => Erp::num($merge('duration_hours', self::DEFAULT_HOURS), self::DEFAULT_HOURS),
            'is_active'      => Erp::bool($merge('is_active', TRUE)),
            'sort_order'     => (int) $merge('sort_order', 0),
            'note'           => trim((string) $merge('note', '')),
        );

        if ($result['code'] === '' OR $result['name'] === '')
        {
            throw new Exception_Erp408_Shopfloor('Укажите код и название смены', NULL, 'invalid_transition');
        }

        if ($result['duration_hours'] <= 0 OR $result['duration_hours'] > 24)
        {
            throw new Exception_Erp408_Shopfloor('Длительность смены должна быть от 0.5 до 24 часов', NULL, 'invalid_transition');
        }

        $result['note'] = $result['note'] !== '' ? $result['note'] : NULL;

        return $result;
    }

    /**
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
            Service_Erp119_Journal::instance()->change('shift', $after['uuid'], $action, $before, $changed, array(
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
