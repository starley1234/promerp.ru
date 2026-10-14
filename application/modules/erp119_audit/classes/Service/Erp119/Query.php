<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Чтение журнала действий (erp119_audit): лента с фильтрами, карточка записи,
 * история объекта, сводка для панели и справочники для фильтров.
 *
 * Запросы опираются на индексы секций: «по объекту», «по человеку», «по
 * времени», «по запросу» и GIN по изменениям — поэтому разбор инцидента
 * («кто поменял цену в этом договоре») не превращается в скан всей таблицы.
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Query
{
    /** @var Service_Erp119_Query */
    protected static $_instance;

    /** @var Service_Erp119_Journal */
    protected $_journal;

    /**
     * @return  Service_Erp119_Query
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct()
    {
        $this->_journal = Service_Erp119_Journal::instance();
    }

    // -------------------------------------------------------------------------
    // Лента
    // -------------------------------------------------------------------------

    /**
     * Найти записи журнала.
     *
     * Фильтры: term, module, entity_type, entity_id, action, severity, source,
     * actor_id, request_id, from, to, has_changes, field.
     *
     * @param   array   $filters
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset, pages
     */
    public function search(array $filters = array(), $limit = 30, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $total = $this->count($filters);

        $query = DB::select()->from(Service_Erp119_Journal::TABLE);

        $this->_apply_filters($query, $filters);

        $result = $query
            ->order_by('occurred_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->execute();

        return array(
            'rows'   => $this->_rows($result),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'pages'  => (int) ceil($total / $limit),
        );
    }

    /**
     * Сколько записей подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from(Service_Erp119_Journal::TABLE);

        $this->_apply_filters($query, $filters);

        $row = $query->execute()->current();

        return (int) $row['cnt'];
    }

    /**
     * Одна запись по идентификатору.
     *
     * @param   int     $id
     * @return  array|null
     */
    public function find($id)
    {
        $result = DB::select()->from(Service_Erp119_Journal::TABLE)
            ->where('id', '=', (int) $id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->execute();

        $rows = $this->_rows($result);

        return $rows ? $rows[0] : NULL;
    }

    /**
     * Записи одного запроса пользователя (сквозная трассировка request_id).
     *
     * @param   string  $request_id
     * @return  array
     */
    public function by_request($request_id)
    {
        $request_id = trim((string) $request_id);

        if ($request_id === '')
        {
            return array();
        }

        return $this->search(array('request_id' => $request_id), 200, 0)['rows'];
    }

    /**
     * История одного объекта: «что с ним делали и кто».
     *
     * @param   string  $entity_type
     * @param   string  $entity_id
     * @param   int     $limit
     * @param   int     $offset
     * @return  array
     */
    public function timeline($entity_type, $entity_id, $limit = 30, $offset = 0)
    {
        return $this->search(array(
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
        ), $limit, $offset);
    }

    /**
     * Последние записи (панель, виджет).
     *
     * @param   int     $limit
     * @return  array
     */
    public function recent($limit = 10)
    {
        return $this->search(array(), $limit, 0);
    }

    // -------------------------------------------------------------------------
    // Сводка
    // -------------------------------------------------------------------------

    /**
     * Показатели панели за период.
     *
     * @param   int     $days
     * @return  array
     */
    public function stats($days = 30)
    {
        $days  = max(1, (int) $days);
        $since = date('c', strtotime('-'.$days.' days'));

        $stats = array(
            'period_days' => $days,
            'total'       => $this->count(),
            'day'         => $this->count(array('from' => date('c', strtotime('-1 day')))),
            'week'        => $this->count(array('from' => date('c', strtotime('-7 days')))),
            'period'      => $this->count(array('from' => $since)),
            'severity'    => array('info' => 0, 'notice' => 0, 'warning' => 0, 'critical' => 0),
            'actors'      => 0,
            'last_at'     => NULL,
            'top_actors'  => array(),
            'top_entities'=> array(),
            'top_actions' => array(),
        );

        foreach ($this->_pairs("
            SELECT severity AS name, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            WHERE occurred_at >= :since
            GROUP BY 1
        ", array(':since' => $since)) as $pair)
        {
            if (isset($stats['severity'][$pair['name']]))
            {
                $stats['severity'][$pair['name']] = $pair['count'];
            }
        }

        $last = DB::select('occurred_at')->from(Service_Erp119_Journal::TABLE)
            ->order_by('occurred_at', 'DESC')->limit(1)->execute()->current();

        $stats['last_at'] = $last ? $last['occurred_at'] : NULL;

        $actors = DB::select(array(DB::expr('COUNT(DISTINCT actor_id)'), 'cnt'))
            ->from(Service_Erp119_Journal::TABLE)
            ->where('occurred_at', '>=', $since)
            ->where('actor_id', '>', 0)
            ->execute()->current();

        $stats['actors'] = (int) $actors['cnt'];

        $stats['top_actors'] = $this->_pairs("
            SELECT COALESCE(actor_name, 'система') AS name, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            WHERE occurred_at >= :since
            GROUP BY 1 ORDER BY cnt DESC, 1 LIMIT 5
        ", array(':since' => $since));

        $stats['top_entities'] = $this->_labelled_pairs("
            SELECT entity_type AS name, MIN(module) AS module, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            WHERE occurred_at >= :since AND entity_type <> 'audit_log'
            GROUP BY 1 ORDER BY cnt DESC, 1 LIMIT 8
        ", array(':since' => $since));

        $stats['top_actions'] = $this->_pairs("
            SELECT action AS name, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            WHERE occurred_at >= :since
            GROUP BY 1 ORDER BY cnt DESC, 1 LIMIT 8
        ", array(':since' => $since), TRUE);

        return $stats;
    }

    /**
     * Активность по дням: день → сколько записей (для графика на панели).
     *
     * @param   int     $days
     * @return  array
     */
    public function activity($days = 14)
    {
        $days  = max(1, (int) $days);
        $since = date('c', strtotime('-'.($days - 1).' days'));

        $activity = array();

        foreach ($this->_pairs("
            SELECT to_char(occurred_at AT TIME ZONE :tz, 'YYYY-MM-DD') AS name, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            WHERE occurred_at >= :since
            GROUP BY 1 ORDER BY 1
        ", array(':since' => $since, ':tz' => $this->timezone())) as $pair)
        {
            $activity[$pair['name']] = $pair['count'];
        }

        // Пустые дни нужны графику: без них «тишина» выглядит как пропуск
        $filled = array();

        for ($i = $days - 1; $i >= 0; $i--)
        {
            $day = date('Y-m-d', strtotime('-'.$i.' days'));

            $filled[$day] = isset($activity[$day]) ? $activity[$day] : 0;
        }

        return $filled;
    }

    // -------------------------------------------------------------------------
    // Справочники
    // -------------------------------------------------------------------------

    /**
     * Справочник аудита сущностей (erp_119_audit_settings).
     *
     * @return  array
     */
    public function settings()
    {
        try
        {
            return DB::select()->from(Service_Erp119_Journal::SETTINGS)
                ->order_by('module', 'ASC')
                ->order_by('entity_type', 'ASC')
                ->execute()
                ->as_array();
        }
        catch (Exception $e)
        {
            return array();
        }
    }

    /**
     * Значения для выпадающих фильтров ленты.
     *
     * @return  array   entities, modules, actions
     */
    public function facets()
    {
        $entities = array();
        $seen     = array();

        foreach ($this->settings() as $row)
        {
            $key = $row['module'].'|'.$row['entity_type'];

            if (isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = TRUE;

            $entities[] = array(
                'module'      => $row['module'],
                'entity_type' => $row['entity_type'],
                'label'       => $row['label'],
            );
        }

        // Сущности, которых нет в справочнике, но которые уже есть в журнале
        foreach ($this->_raw_pairs("
            SELECT entity_type AS name, MIN(module) AS module, COUNT(*) AS cnt
            FROM ".Service_Erp119_Journal::TABLE."
            GROUP BY 1 ORDER BY cnt DESC LIMIT 60
        ") as $pair)
        {
            $module = isset($pair['module']) ? $pair['module'] : 'app';
            $key    = $module.'|'.$pair['name'];

            if (isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = TRUE;

            $entities[] = array(
                'module'      => $module,
                'entity_type' => $pair['name'],
                'label'       => $this->_journal->label_for($module, $pair['name']),
            );
        }

        return array(
            'entities' => $entities,
            'modules'  => $this->_column('module', 40),
            'actions'  => $this->_column('action', 40),
        );
    }

    /**
     * Часовой пояс для календарных разрезов.
     *
     * @return  string
     */
    public function timezone()
    {
        $timezone = $this->_journal->config('timezone');

        return ($timezone === NULL OR $timezone === '') ? date_default_timezone_get() : $timezone;
    }

    // -------------------------------------------------------------------------
    // Фильтры
    // -------------------------------------------------------------------------

    /**
     * Применить фильтры к запросу.
     *
     * @param   Database_Query_Builder $query
     * @param   array   $filters
     * @return  Database_Query_Builder
     */
    protected function _apply_filters($query, array $filters)
    {
        foreach (array('module', 'entity_type', 'entity_id', 'action', 'severity', 'source', 'request_id') as $field)
        {
            if ( ! empty($filters[$field]))
            {
                $query->where($field, '=', is_array($filters[$field]) ? reset($filters[$field]) : $filters[$field]);
            }
        }

        if ( ! empty($filters['actor_id']))
        {
            $query->where('actor_id', '=', (int) $filters['actor_id']);
        }

        if ( ! empty($filters['from']))
        {
            $query->where('occurred_at', '>=', $this->_moment($filters['from']));
        }

        if ( ! empty($filters['to']))
        {
            $query->where('occurred_at', '<=', $this->_moment($filters['to'], TRUE));
        }

        if ( ! empty($filters['has_changes']))
        {
            // Пустое изменение — это '[]' в jsonb-колонке
            $query->where('changes', '<>', DB::expr("'[]'::jsonb"));
        }

        if ( ! empty($filters['alerts_only']))
        {
            $query->where('severity', 'IN', array('warning', 'critical'));
        }

        if ( ! empty($filters['term']))
        {
            // Поиск идёт по search_text: там уже нижний регистр (mb_strtolower),
            // потому что локаль кластера C не складывает регистр кириллицы
            $term = mb_strtolower(trim((string) $filters['term']));

            $query->where('search_text', 'LIKE', '%'.$this->_like_safe($term).'%');
        }

        if ( ! empty($filters['field']))
        {
            $field = (string) $filters['field'];

            if (preg_match('/^[\p{L}\p{N}_.\-]+$/u', $field))
            {
                // «в каких записях менялось это поле» — поиск по GIN-индексу
                $query->where('changes', '@>', DB::expr("'[{\"field\": \"".$field."\"}]'::jsonb"));
            }
            else
            {
                // Пустое поле — заведомо ничего не найдено
                $query->where('entity_type', '=', '__нет_такого_поля__');
            }
        }

        return $query;
    }

    /**
     * Экранирование служебных символов шаблона LIKE.
     *
     * @param   string  $value
     * @return  string
     */
    protected function _like_safe($value)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value);
    }

    /**
     * Момент времени для фильтра «с даты / по дату».
     *
     * Дата без времени — календарный день в часовом поясе приложения: «с 15.09»
     * это 00:00, «по 15.09» это 23:59:59. В базе время с зоной (TIMESTAMPTZ),
     * поэтому сравнение идёт по абсолютному моменту, а не по строке.
     *
     * @param   string  $value
     * @param   bool    $end
     * @return  string
     */
    protected function _moment($value, $end = FALSE)
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
        {
            return date('c', strtotime($value.($end ? ' 23:59:59' : ' 00:00:00')));
        }

        $time = strtotime($value);

        return ($time === FALSE) ? date('c') : date('c', $time);
    }

    // -------------------------------------------------------------------------
    // Строки
    // -------------------------------------------------------------------------

    /**
     * Разобрать строки: JSONB — в массивы, идентификаторы — в числа.
     *
     * @param   mixed   $result
     * @return  array
     */
    protected function _rows($result)
    {
        $rows = array();

        foreach ($result as $row)
        {
            $row['id']       = (int) $row['id'];
            $row['actor_id'] = ($row['actor_id'] === NULL) ? NULL : (int) $row['actor_id'];
            $row['changes']  = $this->_json(isset($row['changes']) ? $row['changes'] : NULL);
            $row['context']  = $this->_json(isset($row['context']) ? $row['context'] : NULL);

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * JSONB-значение в массив.
     *
     * @param   mixed   $value
     * @return  array
     */
    protected function _json($value)
    {
        if (is_array($value))
        {
            return $value;
        }

        if ( ! is_string($value) OR $value === '')
        {
            return array();
        }

        $decoded = json_decode($value, TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Группировка «имя → количество» (+ подписи для интерфейса).
     *
     * @param   string  $sql
     * @param   array   $params
     * @param   bool    $label_actions   подписать действия по-русски
     * @return  array
     */
    protected function _pairs($sql, array $params = array(), $label_actions = FALSE)
    {
        $pairs = array();

        foreach ($this->_raw_pairs($sql, $params) as $row)
        {
            $name = isset($row['name']) ? $row['name'] : '';

            $pairs[] = array(
                'name'  => $name,
                'label' => $label_actions ? Erp119_Ui::action_label($name) : $name,
                'count' => (int) $row['cnt'],
            );
        }

        return $pairs;
    }

    /**
     * Группировка сущностей с «человеческими» подписями.
     *
     * @param   string  $sql
     * @param   array   $params
     * @return  array
     */
    protected function _labelled_pairs($sql, array $params = array())
    {
        $pairs = array();

        foreach ($this->_raw_pairs($sql, $params) as $row)
        {
            $name   = isset($row['name']) ? $row['name'] : '';
            $module = isset($row['module']) ? $row['module'] : 'app';

            $pairs[] = array(
                'name'   => $name,
                'module' => $module,
                'label'  => $this->_journal->label_for($module, $name),
                'count'  => (int) $row['cnt'],
            );
        }

        return $pairs;
    }

    /**
     * Строки группировки как есть (без подписей).
     *
     * @param   string  $sql
     * @param   array   $params
     * @return  array
     */
    protected function _raw_pairs($sql, array $params = array())
    {
        try
        {
            $query = DB::query(Database::SELECT, $sql);

            foreach ($params as $key => $value)
            {
                $query->param($key, $value);
            }

            return $query->execute()->as_array();
        }
        catch (Exception $e)
        {
            return array();
        }
    }

    /**
     * Список значений колонки по частоте (для фильтров).
     *
     * @param   string  $column
     * @param   int     $limit
     * @return  array
     */
    protected function _column($column, $limit)
    {
        $values = array();

        try
        {
            $rows = DB::select($column)->from(Service_Erp119_Journal::TABLE)
                ->group_by($column)
                ->order_by(DB::expr('COUNT(*)'), 'DESC')
                ->limit(max(1, (int) $limit))
                ->execute();

            foreach ($rows as $row)
            {
                $values[] = $row[$column];
            }
        }
        catch (Exception $e)
        {
            return array();
        }

        return $values;
    }
}
