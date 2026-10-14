<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Инвентаризация модулей: что лежит на диске, что подключено, насколько полно
 * портировано на PostgreSQL. Работает без обращений к БД — пригодно для CI и
 * для диагностики «с нуля».
 *
 * @package Erp218
 */
class Service_Erp218_Inventory {

    /**
     * @var Service_Erp218_Inventory
     */
    protected static $_instance;

    /**
     * @return Service_Erp218_Inventory
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    /**
     * Разбирает системное имя модуля: erp218_module_manager => [218, module_manager].
     *
     * @param   string  $name
     * @return  array   [id|NULL, slug]
     */
    public static function parse_name($name)
    {
        if (preg_match('/^erp(\d{3})_(.+)$/', $name, $matches))
        {
            return [(int) $matches[1], $matches[2]];
        }

        return [NULL, $name];
    }

    /**
     * Полный список модулей проекта (включая отключённые).
     *
     * @return  array   [ ['name' => …, 'enabled' => bool, 'path' => …, …], … ]
     */
    public function modules()
    {
        $enabled = Kohana::modules();
        $root    = APPPATH.'modules';
        $result  = [];

        $dirs = [];

        if (is_dir($root))
        {
            foreach (new DirectoryIterator($root) as $item)
            {
                if ($item->isDot() OR ! $item->isDir())
                {
                    continue;
                }

                $dirs[$item->getFilename()] = $item->getPathname();
            }
        }

        // Модули ядра (system: minion, orm, database …)
        foreach ($enabled as $name => $path)
        {
            if (strpos($path, $root) === FALSE)
            {
                $dirs[$name] = $path;
            }
        }

        ksort($dirs);

        foreach ($dirs as $name => $path)
        {
            list($id, $slug) = self::parse_name($name);

            $row = [
                'name'        => $name,
                'id'          => $id,
                'slug'        => $slug,
                'path'        => $path,
                'enabled'     => array_key_exists($name, $enabled),
                'core'        => strpos($path, $root) === FALSE,
                'readme'      => is_file($path.'/README.md'),
                'init'        => is_file($path.'/init.php'),
                'controllers' => 0,
                'services'    => 0,
                'models'      => 0,
                'tasks'       => 0,
                'views'       => 0,
                'migrations'  => 0,
                'migrations_legacy' => 0,
                'files'       => 0,
                'lines'       => 0,
            ];

            $this->collect($row, $path);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * Собирает метрики модуля.
     *
     * @param   array   $row   строка отчёта (по ссылке)
     * @param   string  $path  каталог модуля
     * @return  void
     */
    protected function collect(array & $row, $path)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file)
        {
            if ( ! $file->isFile())
            {
                continue;
            }

            $relative = str_replace($path.'/', '', $file->getPathname());

            if (substr($file->getFilename(), -4) !== '.php')
            {
                if (strpos($relative, 'views/') === 0)
                {
                    $row['views']++;
                }

                continue;
            }

            $row['files']++;
            $row['lines'] += count(file($file->getPathname()));

            if (strpos($relative, 'classes/Controller/') === 0)
            {
                $row['controllers']++;
            }
            elseif (strpos($relative, 'classes/Service/') === 0)
            {
                $row['services']++;
            }
            elseif (strpos($relative, 'classes/Model/') === 0)
            {
                $row['models']++;
            }
            elseif (strpos($relative, 'classes/Task/') === 0)
            {
                $row['tasks']++;
            }
            elseif (strpos($relative, 'migrations_pg/') === 0 AND substr($file->getFilename(), 0, 12) === 'pgMigration_')
            {
                $row['migrations']++;
            }
            elseif (strpos($relative, 'migrations/') === 0)
            {
                $row['migrations_legacy']++;
            }
        }
    }

    /**
     * Модули, подключённые в bootstrap (порядок = порядок загрузки).
     *
     * @return array
     */
    public function enabled_modules()
    {
        $rows = [];

        foreach ($this->modules() as $row)
        {
            if ($row['enabled'])
            {
                $rows[$row['name']] = $row;
            }
        }

        return $rows;
    }

    /**
     * Модули на диске, которые не подключены в application/bootstrap.php.
     *
     * @return array
     */
    public function disabled_modules()
    {
        $rows = [];

        foreach ($this->modules() as $row)
        {
            if ( ! $row['enabled'] AND ! $row['core'])
            {
                $rows[$row['name']] = $row;
            }
        }

        return $rows;
    }

    /**
     * Модули с устаревшими (MySQL-синтаксис) миграциями и без pg-миграций.
     *
     * @return array
     */
    public function not_ported_to_postgresql()
    {
        $rows = [];

        foreach ($this->modules() as $row)
        {
            if ($row['migrations_legacy'] > 0 AND $row['migrations'] === 0)
            {
                $rows[$row['name']] = $row;
            }
        }

        return $rows;
    }

    /**
     * Таблица в формате Markdown (README, docs/ARCHITECTURE.md, docs/MODULE_MAP.md).
     *
     * Раньше таблица собиралась внутри задачи module:list, поэтому документация
     * и CLI могли разойтись. Теперь формат один: и список модулей, и
     * документация берут его отсюда.
     *
     * @param   array   $rows
     * @param   boolean $enabled_only
     * @return  string
     */
    public function markdown(array $rows, $enabled_only = FALSE)
    {
        $out = '| Модуль | ID | Статус | Ctrl | Service | Model | Task | PG-миграции | Строк PHP | README |'.PHP_EOL;
        $out .= '|---|---|:--:|--:|--:|--:|--:|--:|--:|:--:|'.PHP_EOL;

        foreach ($rows as $row)
        {
            if ($enabled_only AND ! $row['enabled'])
            {
                continue;
            }

            $out .= sprintf(
                '| `%s` | %s | %s | %d | %d | %d | %d | %d | %d | %s |%s',
                $row['name'],
                $row['id'] === NULL ? '—' : $row['id'],
                $row['core'] ? 'ядро' : ($row['enabled'] ? 'вкл' : 'выкл'),
                $row['controllers'],
                $row['services'],
                $row['models'],
                $row['tasks'],
                $row['migrations'],
                $row['lines'],
                $row['readme'] ? 'да' : 'нет',
                PHP_EOL
            );
        }

        $not_ported = $this->not_ported_to_postgresql();

        if ($not_ported)
        {
            $out .= PHP_EOL.'Не портированы на PostgreSQL (только устаревшие `migrations/`): ';

            $names = [];

            foreach ($not_ported as $name => $row)
            {
                $names[] = '`'.$name.'`'.' ('.$row['migrations_legacy'].')';
            }

            $out .= implode(', ', $names).'.'.PHP_EOL;
        }

        return $out;
    }

    /**
     * Текстовый отчёт для CLI.
     *
     * @param   boolean  $show_disabled
     * @return  string
     */
    public function report($show_disabled = TRUE)
    {
        $rows = $this->modules();

        $out  = sprintf(
            "%-26s %-4s %-4s %6s %6s %8s %6s %7s %7s%s",
            'Модуль', 'ID', 'Вкл', 'Ctrl', 'Srv', 'Model', 'Task', 'PG-мир', 'Строк', PHP_EOL
        );

        $out .= str_repeat('-', 96).PHP_EOL;

        foreach ($rows as $row)
        {
            if ( ! $row['enabled'] AND ! $show_disabled)
            {
                continue;
            }

            $out .= sprintf(
                "%-26s %-4s %-4s %6d %6d %8d %6d %7d %7d%s",
                $row['name'],
                $row['id'] === NULL ? '—' : $row['id'],
                $row['enabled'] ? 'да' : 'нет',
                $row['controllers'],
                $row['services'],
                $row['models'],
                $row['tasks'],
                $row['migrations'],
                $row['lines'],
                PHP_EOL
            );
        }

        $not_ported = $this->not_ported_to_postgresql();

        if ($not_ported)
        {
            $out .= PHP_EOL.'Не портированы на PostgreSQL (есть только устаревшие migrations/):'.PHP_EOL;

            foreach ($not_ported as $name => $row)
            {
                $out .= sprintf('  ! %-26s устаревших миграций: %d%s', $name, $row['migrations_legacy'], PHP_EOL);
            }
        }

        return $out;
    }
}
