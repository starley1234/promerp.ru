<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Секции журнала: создание, разбор DEFAULT, срок хранения (erp119_audit).
 *
 * Журнал секционирован по месяцам. Это даёт две вещи, которых не даёт обычная
 * таблица:
 *   - удаление по сроку хранения — DROP TABLE партиции, мгновенно и без
 *     раздувания таблицы (DELETE оставил бы мёртвые строки и VACUUM);
 *   - понятный «срез» журнала: сколько записей в месяц, когда писать перестало.
 *
 * DEFAULT-секция — страховка: запись не потеряется, даже если месяц не создан
 * (например, сервер простоял несколько месяцев). Разбирается методом
 * `attach_default()`, вызывается задачей erp119:partitions.
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Partitions
{
    const TABLE = Service_Erp119_Journal::TABLE;

    /** @var Service_Erp119_Partitions */
    protected static $_instance;

    /** @var array */
    protected $_config;

    /**
     * @return  Service_Erp119_Partitions
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
        $this->_config = Kohana::$config->load('erp119_audit');
    }

    /**
     * Создать месячные секции вокруг текущей даты.
     *
     * @param   int     $months_back
     * @param   int     $months_ahead
     * @return  array   список созданных (или уже существующих) секций
     */
    public function ensure($months_back = NULL, $months_ahead = NULL)
    {
        $months_back  = $months_back === NULL ? (int) $this->_config->get('storage.months_back', 6) : (int) $months_back;
        $months_ahead = $months_ahead === NULL ? (int) $this->_config->get('storage.months_ahead', 3) : (int) $months_ahead;

        $created = array();

        for ($offset = -$months_back; $offset <= $months_ahead; $offset++)
        {
            $month = date('Y-m-01', strtotime($offset.' month'));

            if ($this->create_month($month))
            {
                $created[] = $month;
            }
        }

        return $created;
    }

    /**
     * Создать секцию на конкретный месяц (идемпотентно).
     *
     * @param   string  $month  Y-m-01
     * @return  string|null     имя секции, если она была создана
     */
    public function create_month($month)
    {
        $first = date('Y-m-01', strtotime($month));
        $name  = self::TABLE.'_'.date('Y_m', strtotime($first));

        if ($this->exists($name))
        {
            return NULL;
        }

        $to = date('Y-m-d', strtotime($first.' +1 month'));

        DB::query(NULL, sprintf(
            'CREATE TABLE IF NOT EXISTS %s PARTITION OF %s FOR VALUES FROM (%s) TO (%s)',
            $name, self::TABLE,
            $this->quote($first),
            $this->quote($to)
        ))->execute();

        // Уникальность следа события: на родительской таблице такой индекс
        // невозможен (ключ секционирования occurred_at), поэтому он живёт
        // на каждой секции — см. миграцию 003.
        $this->ensure_event_index($name);

        return $name;
    }

    /**
     * Уникальный индекс следа события на секции.
     *
     * @param   string  $partition
     * @return  bool
     */
    public function ensure_event_index($partition)
    {
        try
        {
            DB::query(NULL, sprintf(
                'CREATE UNIQUE INDEX IF NOT EXISTS %s_event_uniq ON %s (event_id) WHERE event_id IS NOT NULL',
                $partition, $partition
            ))->execute();
        }
        catch (Exception $e)
        {
            // Секция без столбца event_id (до миграции 003) — индекс не нужен
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Существует ли секция.
     *
     * @param   string  $name
     * @return  bool
     */
    public function exists($name)
    {
        return (bool) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('pg_class')
            ->where('relname', '=', $name)
            ->execute()
            ->get('total', 0);
    }

    /**
     * Список секций с размером и числом записей.
     *
     * @return  array
     */
    public function inventory()
    {
        $rows = DB::query(Database::SELECT, "
            SELECT
                c.relname                              AS partition,
                pg_get_expr(c.relpartbound, c.oid)     AS bound,
                pg_total_relation_size(c.oid)          AS size_bytes,
                c.reltuples::bigint                    AS approx_rows
            FROM pg_class c
            JOIN pg_inherits i ON i.inhrelid = c.oid
            JOIN pg_class p ON p.oid = i.inhparent
            WHERE p.relname = :table
            ORDER BY c.relname ASC
        ")->param(':table', self::TABLE)->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = array(
                'partition'   => $row['partition'],
                'bound'       => $row['bound'],
                'size_bytes'  => (int) $row['size_bytes'],
                'size'        => $this->human_size((int) $row['size_bytes']),
                'rows'        => $this->count_rows($row['partition']),
                'approx_rows' => (int) $row['approx_rows'],
                'is_default'  => strpos($row['partition'], '_default') !== FALSE,
                'month'       => $this->month_of($row['partition']),
            );
        }

        return $result;
    }

    /**
     * Точное число записей в секции (reltuples без ANALYZE врёт).
     *
     * @param   string  $partition
     * @return  int
     */
    public function count_rows($partition)
    {
        if ( ! preg_match('/^[a-z0-9_]+$/', $partition))
        {
            return 0;
        }

        return (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from($partition)
            ->execute()
            ->get('total', 0);
    }

    /**
     * Месяц секции: erp_119_audit_log_2026_09 → 2026-09.
     *
     * @param   string  $partition
     * @return  string|null
     */
    public function month_of($partition)
    {
        if (preg_match('/_(\d{4})_(\d{2})$/', $partition, $match))
        {
            return $match[1].'-'.$match[2];
        }

        return NULL;
    }

    /**
     * Записи, попавшие в DEFAULT-секцию (месяц не был создан).
     *
     * @return  array  array('YYYY-MM' => int)
     */
    public function default_backlog()
    {
        $rows = DB::query(Database::SELECT, "
            SELECT to_char(date_trunc('month', occurred_at), 'YYYY-MM') AS month, COUNT(*) AS total
            FROM ".self::TABLE."_default
            GROUP BY 1
            ORDER BY 1 ASC
        ")->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[$row['month']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Разобрать DEFAULT-секцию: создать недостающие месяцы и перенести записи
     * в правильные секции.
     *
     * Просто создать секцию нельзя: PostgreSQL откажется («updated partition
     * constraint for default partition would be violated by some row»), пока
     * в DEFAULT лежат строки её диапазона. Поэтому порядок такой:
     *
     *   1. DEFAULT отсоединяется (DETACH) и становится обычной таблицей;
     *   2. создаются месячные секции, которых не было;
     *   3. строки переносятся в родительскую таблицу (INSERT ... SELECT),
     *      откуда их раскладывает секционирование, и удаляются из DEFAULT;
     *   4. DEFAULT присоединяется обратно (ATTACH ... DEFAULT).
     *
     * Триггер append-only на шаге 3 отключается — это единственная штатная
     * операция правки журнала, и она выполняется владельцем таблиц.
     *
     * @return  array  array('months' => array, 'moved' => int)
     */
    public function attach_default()
    {
        $default_table = self::TABLE.'_default';
        $backlog       = $this->default_backlog();

        if ( ! $backlog)
        {
            return array('months' => array(), 'moved' => 0);
        }

        $months = array();
        $moved  = 0;

        DB::query(NULL, 'ALTER TABLE '.self::TABLE.' DETACH PARTITION '.$default_table)->execute();

        try
        {
            foreach (array_keys($backlog) as $month)
            {
                $created = $this->create_month($month.'-01');

                if ($created)
                {
                    $months[] = $created;
                }
            }

            $this->trigger($default_table, FALSE);

            foreach (array_keys($backlog) as $month)
            {
                $first = $month.'-01';
                $next  = date('Y-m-01', strtotime($first.' +1 month'));

                // INSERT в родителя раскладывает строки по своим секциям.
                // Считаем через SELECT: rowCount на INSERT ... SELECT врёт.
                $result = DB::query(Database::SELECT, sprintf("
                    WITH moved AS (
                        INSERT INTO %s
                        SELECT * FROM %s
                        WHERE occurred_at >= %s AND occurred_at < %s
                        RETURNING 1
                    )
                    SELECT COUNT(*) AS total FROM moved
                ", self::TABLE, $default_table, $this->quote($first), $this->quote($next)))->execute();

                $moved += (int) $result->get('total', 0);

                DB::query(NULL, sprintf(
                    'DELETE FROM %s WHERE occurred_at >= %s AND occurred_at < %s',
                    $default_table, $this->quote($first), $this->quote($next)
                ))->execute();
            }

            $this->trigger($default_table, TRUE);
        }
        finally
        {
            DB::query(NULL, 'ALTER TABLE '.self::TABLE.' ATTACH PARTITION '.$default_table.' DEFAULT')->execute();
        }

        return array('months' => $months, 'moved' => $moved);
    }

    /**
     * Отключить защиту от правки на всех секциях журнала.
     *
     * Операция для обслуживания и для тестов: журнал append-only, но бывают
     * задачи, которые нельзя решить иначе — перенос строк из DEFAULT-секции или
     * уборка записей, попавших туда по ошибке (например, запись с секретом до
     * включения правил маскирования). Выполнять может только владелец таблиц.
     *
     * @return  int     сколько таблиц затронуто
     */
    public function disable_immutability()
    {
        $touched = 0;

        foreach ($this->_tables() as $table)
        {
            $this->trigger($table, FALSE);
            $touched++;
        }

        return $touched;
    }

    /**
     * Вернуть защиту от правки на место.
     *
     * @return  int
     */
    public function enable_immutability()
    {
        $touched = 0;

        foreach ($this->_tables() as $table)
        {
            $this->trigger($table, TRUE);
            $touched++;
        }

        return $touched;
    }

    /**
     * Родительская таблица и все её секции.
     *
     * @return  array
     */
    protected function _tables()
    {
        $names = DB::query(Database::SELECT, "
            SELECT c.relname
            FROM pg_class c
            JOIN pg_inherits i ON i.inhrelid = c.oid
            JOIN pg_class p ON p.oid = i.inhparent
            WHERE p.relname = :table
        ")->param(':table', self::TABLE)->execute()->as_array(NULL, 'relname');

        $names[] = self::TABLE;

        return $names;
    }

    /**
     * Включить/выключить триггер неизменяемости на таблице.
     *
     * @param   string  $table
     * @param   bool    $enabled
     * @return  void
     */
    public function trigger($table, $enabled)
    {
        if ( ! preg_match('/^[a-z0-9_]+$/', $table))
        {
            return;
        }

        try
        {
            DB::query(NULL, 'ALTER TABLE '.$table.' '.($enabled ? 'ENABLE' : 'DISABLE').' TRIGGER erp_119_audit_log_immutable_trg')->execute();
        }
        catch (Exception $e)
        {
            // Триггера нет (таблица создана вне миграции) — не мешаем работе
        }
    }

    /**
     * Секции старше срока хранения (кандидаты на удаление).
     *
     * @param   int     $months  срок хранения; NULL — из конфигурации
     * @return  array
     */
    public function expired($months = NULL)
    {
        $months = $months === NULL ? $this->_config->get('storage.retention_months', NULL) : (int) $months;

        if ( ! $months)
        {
            return array();
        }

        $border = date('Y-m-01', strtotime('-'.$months.' month'));
        $result = array();

        foreach ($this->inventory() as $partition)
        {
            if ($partition['is_default'] OR $partition['month'] === NULL)
            {
                continue;
            }

            if ($partition['month'].'-01' < $border)
            {
                $result[] = $partition;
            }
        }

        return $result;
    }

    /**
     * Удалить истёкшие секции.
     *
     * @param   int     $months
     * @return  array   удалённые секции
     */
    public function drop_expired($months = NULL)
    {
        $dropped = array();

        foreach ($this->expired($months) as $partition)
        {
            DB::query(NULL, 'DROP TABLE IF EXISTS '.$partition['partition'])->execute();
            $dropped[] = $partition;
        }

        return $dropped;
    }

    /**
     * Литерал SQL: значения здесь всегда наши (даты и имена секций),
     * но собирать SQL строками без экранирования нельзя — привычка заразна.
     *
     * @param   string  $value
     * @return  string
     */
    protected function quote($value)
    {
        return Database::instance()->quote((string) $value);
    }

    /**
     * Человекочитаемый размер.
     *
     * @param   int     $bytes
     * @return  string
     */
    public function human_size($bytes)
    {
        $units = array('Б', 'КБ', 'МБ', 'ГБ');
        $index = 0;

        while ($bytes >= 1024 AND $index < count($units) - 1)
        {
            $bytes = $bytes / 1024;
            $index++;
        }

        return round($bytes, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
