<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис миграций PostgreSQL.
 *
 * Вся логика наката/откатa миграций живёт здесь, а не в контроллере:
 *   * CLI-задача  db:migrate      (application/modules/erp111_migrations_postgresql/classes/Task/Db/Migrate.php)
 *   * HTTP-контроллер /migrate_pg (только DEVELOPMENT или по токену MIGRATE_TOKEN)
 *
 * Соглашения PromERP:
 *   * миграции лежат в <module>/migrations_pg/pgMigration_*.php;
 *   * класс миграции называется так же, как файл (pgMigration_001_Erp401_Wms_CreateTables);
 *   * порядок: порядок подключения модулей в application/bootstrap.php, внутри модуля — по имени файла;
 *   * каждая миграция выполняется в транзакции (PostgreSQL поддерживает DDL в транзакциях).
 */
class Service_Erp111_Migrator {

    /**
     * Таблица учёта применённых миграций.
     */
    const TABLE = 'migrations_pg';

    /**
     * @var Service_Erp111_Migrator
     */
    protected static $_instance;

    /**
     * @var string Группа подключения к БД (реестр применённых миграций по умолчанию)
     */
    protected $_db_group;

    /**
     * @var array Кэш применённых миграций по группам: group => [class => applied_at]
     */
    protected $_applied_cache = [];

    /**
     * @param string $db_group группа из application/config/database.php
     */
    public function __construct($db_group = NULL)
    {
        if ($db_group === NULL OR $db_group === '')
        {
            $db_group = Kohana::$config->load('erp111_migrations_postgresql.db_group');
        }

        if (empty($db_group))
        {
            $db_group = Database::$default;
        }

        $this->_db_group = $db_group;
    }

    /**
     * @param   string  $db_group
     * @return  Service_Erp111_Migrator
     */
    public static function instance($db_group = NULL)
    {
        if (self::$_instance === NULL OR $db_group !== NULL)
        {
            self::$_instance = new self($db_group);
        }

        return self::$_instance;
    }

    /**
     * @return string
     */
    public function db_group()
    {
        return $this->_db_group;
    }

    /**
     * @return Database
     */
    public function db()
    {
        return Database::instance($this->_db_group);
    }

    // -- Учёт применённых миграций ---------------------------------------------

    /**
     * Создаёт служебную таблицу реестра в указанной группе, если её нет.
     *
     * Реестр ведётся в каждой базе отдельно: миграция модуля erp230_mdm меняет
     * базу группы `mdm`, и её отметка хранится там же.
     *
     * @param   string  $db_group
     * @return  boolean TRUE — таблица создана этим вызовом
     */
    public function ensure_table($db_group = NULL)
    {
        $db_group = ($db_group === NULL) ? $this->_db_group : $db_group;

        $exists = DB::query(Database::SELECT, "SELECT to_regclass(:table)")
            ->param(':table', self::TABLE)
            ->execute($db_group)
            ->get('to_regclass');

        if ($exists !== NULL)
        {
            return FALSE;
        }

        DB::query(NULL, '
            CREATE TABLE '.self::TABLE.' (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL
            )
        ')->execute($db_group);

        unset($this->_applied_cache[$db_group]);

        return TRUE;
    }

    /**
     * Применённые миграции группы: класс => дата применения.
     *
     * @param   string  $db_group
     * @return  array
     */
    public function applied($db_group = NULL)
    {
        $db_group = ($db_group === NULL) ? $this->_db_group : $db_group;

        if (isset($this->_applied_cache[$db_group]))
        {
            return $this->_applied_cache[$db_group];
        }

        $this->ensure_table($db_group);

        $rows = DB::select('migration', 'applied_at')
            ->from(self::TABLE)
            ->execute($db_group)
            ->as_array();

        $result = [];

        foreach ($rows as $row)
        {
            $result[$row['migration']] = $row['applied_at'];
        }

        return $this->_applied_cache[$db_group] = $result;
    }

    /**
     * Помечает миграцию применённой (в реестре её базы).
     *
     * @param   string  $class
     * @param   string  $db_group
     * @return  void
     */
    public function mark_applied($class, $db_group = NULL)
    {
        $db_group = ($db_group === NULL) ? $this->_db_group : $db_group;

        DB::insert(self::TABLE, ['migration', 'applied_at'])
            ->values([$class, date('Y-m-d H:i:s')])
            ->execute($db_group);

        unset($this->_applied_cache[$db_group]);
    }

    /**
     * Снимает отметку о применении.
     *
     * @param   string  $class
     * @param   string  $db_group
     * @return  void
     */
    public function mark_rolled_back($class, $db_group = NULL)
    {
        $db_group = ($db_group === NULL) ? $this->_db_group : $db_group;

        DB::delete(self::TABLE)
            ->where('migration', '=', $class)
            ->execute($db_group);

        unset($this->_applied_cache[$db_group]);
    }

    // -- Инвентарь миграций ----------------------------------------------------

    /**
     * Все миграции проекта в порядке применения.
     *
     * @param   string  $module_filter  только один модуль (его системное имя)
     * @return  array                   [ ['class' => …, 'module' => …, 'file' => …, 'applied' => bool, 'applied_at' => string|NULL], … ]
     */
    public function inventory($module_filter = NULL)
    {
        $result  = [];

        foreach (Kohana::modules() as $module => $path)
        {
            if ($module_filter !== NULL AND $module !== $module_filter)
            {
                continue;
            }

            $dir = rtrim($path, '/').'/migrations_pg';

            if ( ! is_dir($dir))
            {
                continue;
            }

            $files = glob($dir.'/pgMigration_*.php');
            sort($files);

            foreach ($files as $file)
            {
                $class  = basename($file, '.php');
                $migr   = $this->load_migration($class, $file);
                $group  = $migr->db_group();

                $applied = $this->applied($group);

                $result[] = [
                    'class'      => $class,
                    'module'     => $module,
                    'file'       => $file,
                    'db_group'   => $group,
                    'applied'    => isset($applied[$class]),
                    'applied_at' => isset($applied[$class]) ? $applied[$class] : NULL,
                ];
            }
        }

        return $result;
    }

    /**
     * Не применённые миграции в порядке применения.
     *
     * @param   string  $module_filter
     * @return  array
     */
    public function pending($module_filter = NULL)
    {
        return array_values(array_filter($this->inventory($module_filter), function ($row) {
            return ! $row['applied'];
        }));
    }

    /**
     * Записи в migrations_pg, для которых больше нет файла миграции.
     *
     * @return array
     */
    public function orphans()
    {
        $known   = [];
        $groups  = [];

        foreach ($this->inventory() as $row)
        {
            $known[$row['class']] = TRUE;
            $groups[$row['db_group']] = TRUE;
        }

        $groups[$this->_db_group] = TRUE;

        $orphans = [];

        foreach (array_keys($groups) as $group)
        {
            foreach ($this->applied($group) as $class => $applied_at)
            {
                if ( ! isset($known[$class]))
                {
                    $orphans[$group.'::'.$class] = $applied_at;
                }
            }
        }

        return $orphans;
    }

    // -- Применение и откат ----------------------------------------------------

    /**
     * Создаёт объект миграции по классу.
     *
     * @param   string  $class
     * @param   string  $file
     * @return  Model_Erp111_Migration
     */
    protected function load_migration($class, $file)
    {
        if ( ! class_exists($class))
        {
            require_once $file;
        }

        if ( ! class_exists($class))
        {
            throw new Kohana_Exception('Класс миграции :class не объявлен в :file', [
                ':class' => $class,
                ':file'  => $file,
            ]);
        }

        return new $class;
    }

    /**
     * Накатывает миграции.
     *
     * @param   array   $options  ['module' => string|NULL, 'dry_run' => bool]
     * @param   callable $logger  function (string $message, string $level)
     * @return  array             статистика ['applied' => [], 'already_applied' => int, 'failed' => NULL|string]
     */
    public function migrate(array $options = [], $logger = NULL)
    {
        $module  = Arr::get($options, 'module');
        $group_filter = Arr::get($options, 'db_group');
        $dry_run = (bool) Arr::get($options, 'dry_run', FALSE);

        $log = function ($message, $level = 'info') use ($logger) {
            if ($logger !== NULL)
            {
                call_user_func($logger, $message, $level);
            }
        };

        if ($this->ensure_table())
        {
            $log('Создана служебная таблица '.self::TABLE, 'ok');
        }

        $inventory = array_values(array_filter($this->inventory($module), function ($row) use ($group_filter) {
            return $group_filter === NULL OR $row['db_group'] === $group_filter;
        }));
        $stats = [
            'applied'         => [],
            'already_applied' => count(array_filter($inventory, function ($row) { return $row['applied']; })),
            'failed'          => NULL,
            'errors'          => [],
        ];
        $pending = array_values(array_filter($inventory, function ($row) { return ! $row['applied']; }));

        if ( ! $pending)
        {
            $log('Нет новых миграций' . ($module ? ' для модуля '.$module : ''), 'ok');

            return $stats;
        }

        foreach ($pending as $row)
        {
            if ($dry_run)
            {
                $log('[dry-run] '.$row['class'].' ('.$row['module'].' → '.$row['db_group'].')');
                $stats['applied'][] = $row['class'];
                continue;
            }

            $migration = $this->load_migration($row['class'], $row['file']);
            $db = Database::instance($migration->db_group());

            $db->begin();

            try
            {
                $migration->up();
                $this->mark_applied($row['class'], $migration->db_group());
                $db->commit();

                $stats['applied'][] = $row['class'];
                $note = method_exists($migration, 'migration_note') ? $migration->migration_note() : NULL;
                $log(
                    'применена '.$row['class'].' ('.$row['module'].' → '.$migration->db_group().')'
                    .($note ? ': '.$note : ''),
                    'ok'
                );
            }
            catch (Exception $e)
            {
                $db->rollback();

                $stats['failed'] = $row['class'];
                $stats['errors'][$row['class']] = $e->getMessage();
                $log('ОШИБКА в '.$row['class'].': '.$e->getMessage(), 'error');
                $log('Транзакция откатана, БД осталась в согласованном состоянии', 'error');

                break;
            }
        }

        return $stats;
    }

    /**
     * Откатывает миграцию (вызывает down() и снимает отметку).
     *
     * @param   string  $class
     * @return  boolean
     */
    public function rollback($class)
    {
        $this->ensure_table();

        $target = NULL;

        foreach ($this->inventory() as $row)
        {
            if ($row['class'] === $class)
            {
                $target = $row;
                break;
            }
        }

        if ($target === NULL)
        {
            throw new Kohana_Exception('Миграция :class не найдена в модулях', [':class' => $class]);
        }

        if ( ! $target['applied'])
        {
            throw new Kohana_Exception('Миграция :class не была применена', [':class' => $class]);
        }

        $migration = $this->load_migration($target['class'], $target['file']);
        $db        = Database::instance($migration->db_group());

        $db->begin();

        try
        {
            $migration->down();
            $this->mark_rolled_back($class, $migration->db_group());
            $db->commit();
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * Текстовый отчёт о состоянии миграций.
     *
     * @param   string  $module_filter
     * @return  string
     */
    public function status_report($module_filter = NULL)
    {
        $rows    = $this->inventory($module_filter);
        $applied = 0;
        $total   = count($rows);
        $lines   = [];

        foreach ($rows as $row)
        {
            if ($row['applied'])
            {
                $applied++;
            }

            $lines[] = sprintf(
                '  %s %-58s %-24s %-10s %s',
                $row['applied'] ? '[x]' : '[ ]',
                $row['class'],
                $row['module'],
                $row['db_group'],
                $row['applied'] ? $row['applied_at'] : 'ожидает'
            );
        }

        $report = implode(PHP_EOL, $lines);

        $orphans = $this->orphans();

        if ($orphans)
        {
            $report .= PHP_EOL.PHP_EOL.'  Записи без файла миграции (модуль отключён или файл удалён):'.PHP_EOL;

            foreach ($orphans as $class => $applied_at)
            {
                $report .= sprintf('  [!] %-60s %s'.PHP_EOL, $class, $applied_at);
            }
        }

        $report .= PHP_EOL.PHP_EOL.sprintf('  Итого: применено %d из %d', $applied, $total);

        return $report;
    }
}
