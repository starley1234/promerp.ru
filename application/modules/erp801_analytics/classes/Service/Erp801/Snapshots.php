<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Снимки витрин (erp801_analytics).
 *
 * Витрина считается на живых данных — это правильно, но у живых данных есть
 * свойство меняться: документ перепроведут, партию пересчитают, и цифра за
 * прошлый месяц перестанет совпадать с той, что показывали на планёрке. Снимок
 * фиксирует «как считалось тогда»: код витрины, фильтры и готовый результат.
 *
 * Сравнение двух снимков — ответ на вопрос «что изменилось с прошлого раза»:
 * скалярные показатели (KPI, итоги) сопоставляются по путям (`kpi.oee.value`),
 * а большие списки пропускаются: сравнивать строки по позициям — это уже
 * задача владельца данных, а не сводки.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Snapshots
{
    /** @var Service_Erp801_Snapshots|null */
    protected static $_instance;

    /** @var array Поля, которые не несут смысла при сравнении */
    protected $_volatile = array('generated_at', 'created_at', 'filters', 'from', 'to', 'days');

    /** @var array Ветки payload, которые не сравниваются (списки и графики) */
    protected $_skipped = array('rows', 'by_center', 'pareto', 'cause_pareto', 'code_pareto', 'by_month', 'by_supplier', 'quarantine', 'due_rows', 'overdue_rows', 'deficit_rows', 'sources', 'months', 'notes');

    /**
     * @return  Service_Erp801_Snapshots
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Собрать payload витрины по коду.
     *
     * @param   string  $code
     * @param   array   $filters
     * @return  array
     */
    public function build($code, array $filters = array())
    {
        switch ($code)
        {
            case 'summary':
                return Service_Erp801_Summary::instance()->build($filters);

            case 'stock':
                return Service_Erp801_Stock::instance()->turnover($filters);

            case 'abc':
                return Service_Erp801_Stock::instance()->abc_xyz($filters);

            case 'production':
                return Service_Erp801_Production::instance()->panel($filters);

            case 'quality':
                return Service_Erp801_Quality::instance()->panel($filters);

            case 'purchases':
                return Service_Erp801_Purchases::instance()->panel($filters);
        }

        throw new Exception_Erp801_Analytics(
            'Неизвестная витрина «:code»',
            array(':code' => (string) $code),
            'invalid_argument'
        );
    }

    /**
     * Снять снимок.
     *
     * @param   string      $code
     * @param   array       $filters
     * @param   int|null    $user_id
     * @param   string|null $title
     * @param   string|null $note
     * @param   string|null $day
     * @return  Model_Erp801_Snapshot
     */
    public function save($code, array $filters = array(), $user_id = NULL, $title = NULL, $note = NULL, $day = NULL)
    {
        if ( ! isset(Model_Erp801_Snapshot::$codes[$code]))
        {
            throw new Exception_Erp801_Analytics(
                'Неизвестная витрина «:code»',
                array(':code' => (string) $code),
                'invalid_argument'
            );
        }

        $payload = $this->build($code, $filters);

        $snapshot = ORM::factory('Erp801_Snapshot');
        $snapshot->uuid       = Erp::uuid();
        $snapshot->code       = (string) $code;
        $snapshot->day        = Erp::day($day, date('Y-m-d'));
        $snapshot->title      = $title !== NULL AND $title !== '' ? (string) $title : Model_Erp801_Snapshot::code_label($code).' на '.Erp::day($day, date('Y-m-d'));
        $snapshot->filters    = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $snapshot->payload    = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $snapshot->note       = ($note === NULL OR $note === '') ? NULL : (string) $note;
        $snapshot->created_by = $user_id ? (int) $user_id : NULL;
        $snapshot->save();

        $this->_journal('create', $snapshot, $user_id, 'Снимок витрины: '.$snapshot->title);

        return $snapshot;
    }

    /**
     * Список снимков (свежие сверху).
     *
     * @param   string|null $code
     * @param   int         $limit
     * @param   int         $offset
     * @return  array
     */
    public function rows($code = NULL, $limit = 50, $offset = 0)
    {
        $query = ORM::factory('Erp801_Snapshot');

        if ($code)
        {
            $query->where('code', '=', (string) $code);
        }

        $found = $query
            ->order_by('day', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, (int) $limit))
            ->offset(max(0, (int) $offset))
            ->find_all();

        $rows = array();

        foreach ($found as $snapshot)
        {
            $rows[] = $snapshot->as_row();
        }

        return $rows;
    }

    /**
     * Снимок по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp801_Snapshot|null
     */
    public function find($id_or_uuid)
    {
        // Формат проверяет общий помощник: мусор из адреса («abc», пустая строка,
        // ноль) не должен доходить до СУБД — PostgreSQL ответит ошибкой приведения
        // типа uuid, и вместо 404 пользователь получит 500.
        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $snapshot = ORM::factory('Erp801_Snapshot')->where($ref[0], '=', $ref[1])->find();

        return $snapshot->loaded() ? $snapshot : NULL;
    }

    /**
     * Удалить снимок.
     *
     * @param   int|string  $id_or_uuid
     * @param   int|null    $user_id
     * @return  bool
     */
    public function delete($id_or_uuid, $user_id = NULL)
    {
        $snapshot = $this->find($id_or_uuid);

        if ( ! $snapshot)
        {
            throw new Exception_Erp801_Analytics(
                'Снимок не найден: :id',
                array(':id' => (string) $id_or_uuid),
                'not_found'
            );
        }

        $row = $snapshot->as_row();
        $uuid = (string) $snapshot->uuid;

        $snapshot->delete();

        $this->_journal('delete', NULL, $user_id, 'Снимок удалён: '.$row['title'], $uuid);

        return TRUE;
    }

    /**
     * Сравнить два снимка: что изменилось в скалярных показателях.
     *
     * @param   int|string  $left_id
     * @param   int|string  $right_id
     * @param   int         $limit
     * @return  array
     */
    public function compare($left_id, $right_id, $limit = 100)
    {
        $left  = $this->find($left_id);
        $right = $this->find($right_id);

        if ( ! $left OR ! $right)
        {
            throw new Exception_Erp801_Analytics('Не найден один из снимков для сравнения', NULL, 'not_found');
        }

        if ((string) $left->code !== (string) $right->code)
        {
            throw new Exception_Erp801_Analytics(
                'Снимки разных витрин не сравниваются: :left и :right',
                array(':left' => Model_Erp801_Snapshot::code_label((string) $left->code), ':right' => Model_Erp801_Snapshot::code_label((string) $right->code)),
                'invalid_argument'
            );
        }

        $before = $this->_flatten($left->payload());
        $after  = $this->_flatten($right->payload());

        $keys   = array_unique(array_merge(array_keys($before), array_keys($after)));
        $rows   = array();

        sort($keys);

        foreach ($keys as $path)
        {
            $old = isset($before[$path]) ? $before[$path] : NULL;
            $new = isset($after[$path]) ? $after[$path] : NULL;

            if ($old === $new)
            {
                continue;
            }

            $rate = NULL;

            if (is_numeric($old) AND is_numeric($new) AND (float) $old != 0.0)
            {
                $rate = round(((float) $new - (float) $old) / abs((float) $old) * 100, 1);
            }

            $rows[] = array(
                'path'   => $path,
                'label'  => $this->_label($path),
                'before' => $old,
                'after'  => $new,
                'delta'  => (is_numeric($old) AND is_numeric($new)) ? round((float) $new - (float) $old, 6) : NULL,
                'rate'   => $rate,
            );

            if (count($rows) >= max(1, (int) $limit))
            {
                break;
            }
        }

        return array(
            'code'  => (string) $left->code,
            'left'  => $left->as_row(),
            'right' => $right->as_row(),
            'rows'  => $rows,
        );
    }

    /**
     * Плоский список скалярных полей payload: `a.b.c => значение`.
     *
     * Списки и ветки из `_skipped` пропускаются: сравнение строк по позициям —
     * задача владельца данных, а не сводки.
     *
     * @param   array   $payload
     * @param   string  $prefix
     * @param   int     $depth
     * @return  array
     */
    protected function _flatten(array $payload, $prefix = '', $depth = 0)
    {
        $rows = array();

        if ($depth > 4)
        {
            return $rows;
        }

        foreach ($payload as $key => $value)
        {
            if (in_array((string) $key, $this->_skipped, TRUE) OR in_array((string) $key, $this->_volatile, TRUE))
            {
                continue;
            }

            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value))
            {
                $numeric = TRUE;

                foreach (array_keys($value) as $index)
                {
                    if ( ! is_int($index))
                    {
                        $numeric = FALSE;
                        break;
                    }
                }

                if ($numeric)
                {
                    // Список: построчное сравнение — задача владельца данных.
                    continue;
                }

                $rows = $rows + $this->_flatten($value, $path, $depth + 1);
                continue;
            }

            if ($value === NULL OR is_bool($value) OR is_numeric($value) OR is_string($value))
            {
                $rows[$path] = is_bool($value) ? (int) $value : $value;
            }
        }

        return $rows;
    }

    /**
     * Человеческая подпись пути: `kpi.cover_days.value` → «KPI: Покрытие запасов».
     *
     * @param   string  $path
     * @return  string
     */
    protected function _label($path)
    {
        $labels = array(
            'totals'      => 'Итоги',
            'summary'     => 'Показатели',
            'kpi'         => 'KPI',
            'plan_fact'   => 'План/факт',
            'deficit'     => 'Дефицит',
            'value'       => 'значение',
            'state'       => 'состояние',
            'target'      => 'цель',
        );

        $parts  = explode('.', $path);
        $result = array();

        foreach ($parts as $part)
        {
            if (isset($labels[$part]))
            {
                $result[] = $labels[$part];
            }
            elseif (is_numeric($part))
            {
                $result[] = '#'.((int) $part + 1);
            }
            else
            {
                $result[] = $part;
            }
        }

        return implode(' · ', $result);
    }

    /**
     * Запись в журнал (erp119), если модуль аудита подключён.
     *
     * @param   string                    $action
     * @param   Model_Erp801_Snapshot|null $snapshot
     * @param   int|null                  $user_id
     * @param   string                    $message
     * @param   string|null               $uuid
     * @return  void
     */
    protected function _journal($action, $snapshot = NULL, $user_id = NULL, $message = '', $uuid = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $entity_id = $uuid ? $uuid : ($snapshot ? (string) $snapshot->uuid : '');

        if ($entity_id === '')
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('analytics_snapshot', $entity_id, $action, array(), array(), array(
                'message'      => (string) $message,
                'entity_label' => 'Снимок витрины',
                'module'       => 'erp801_analytics',
                'actor_id'     => $user_id ? (int) $user_id : NULL,
                'force'        => TRUE,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp801: журнал недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
