<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Диагностика среды разработки: PHP, конфиги, каталоги, БД, миграции, модули.
 *
 *   bin/console dev:doctor
 *   bin/console dev:doctor --quiet     (только проблемы)
 *
 * Возвращает код 1, если есть блокирующие ошибки — пригодно для CI.
 *
 * @package Minion
 */
class Task_Dev_Doctor extends Minion_Task {

    protected $_options = [
        'quiet' => FALSE,
    ];

    /** @var array */
    protected $_problems = [];

    protected function _execute(?array $options = NULL)
    {
        $quiet = $this->_flag((array) $options, 'quiet');

        $this->_header('PromERP · диагностика среды');

        $this->_php($quiet);
        $this->_filesystem($quiet);
        $this->_config($quiet);
        $this->_database($quiet);
        $this->_modules($quiet);
        $this->_dependencies($quiet);

        if ($this->_problems)
        {
            echo PHP_EOL.'Проблемы: '.count($this->_problems).PHP_EOL;

            foreach ($this->_problems as $problem)
            {
                echo '  - '.$problem.PHP_EOL;
            }

            echo PHP_EOL.'Подсказка: bash bin/setup.sh, затем bash bin/restore-env.sh --pg-only'.PHP_EOL;
            exit(1);
        }

        echo PHP_EOL.'Замечаний нет — среда готова к работе.'.PHP_EOL;
    }

    // -- Разделы ---------------------------------------------------------------

    protected function _header($title)
    {
        echo $title.PHP_EOL.str_repeat('=', strlen($title) * 2).PHP_EOL;
    }

    protected function _line($status, $text, $quiet = FALSE)
    {
        if ($quiet AND $status === 'ok')
        {
            return;
        }

        $mark = ($status === 'ok') ? '[ok]  ' : (($status === 'warn') ? '[warn]' : '[FAIL]');

        echo '  '.$mark.' '.$text.PHP_EOL;
    }

    protected function _php($quiet)
    {
        echo PHP_EOL.'PHP'.PHP_EOL;

        $this->_line(version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'fail',
            'версия '.PHP_VERSION.' (минимум 8.1, целевая 8.4)', $quiet);

        if (Kohana::$environment !== Kohana::PRODUCTION AND version_compare(PHP_VERSION, '8.4', '<'))
        {
            $this->_line('warn', 'окружение не на PHP 8.4 — проверяйте совместимость', $quiet);
        }

        $required = ['pgsql', 'mbstring', 'json', 'session', 'ctype', 'tokenizer', 'curl', 'fileinfo'];

        foreach ($required as $extension)
        {
            $loaded = extension_loaded($extension);

            if ( ! $loaded)
            {
                $this->_problems[] = 'не загружено расширение PHP: '.$extension;
            }

            $this->_line($loaded ? 'ok' : 'fail', 'расширение '.$extension, $quiet);
        }

        foreach (['intl', 'gd', 'zip', 'openssl', 'soap', 'pcntl'] as $extension)
        {
            if ( ! extension_loaded($extension))
            {
                $this->_line('warn', 'расширение '.$extension.' отсутствует (часть функций будет недоступна)', $quiet);
            }
        }

        $this->_line((php_ini_loaded_file() ?: '') !== '' ? 'ok' : 'warn',
            'php.ini: '.(php_ini_loaded_file() ?: 'не используется'), $quiet);
    }

    protected function _filesystem($quiet)
    {
        echo PHP_EOL.'Каталоги'.PHP_EOL;

        $dirs = [
            'application/cache'    => APPPATH.'cache',
            'application/logs'     => APPPATH.'logs',
            'application/sessions' => APPPATH.'sessions',
            'public_html/uploads'  => DOCROOT.'uploads',
        ];

        foreach ($dirs as $label => $path)
        {
            $ok = is_dir($path) AND is_writable($path);

            if ( ! $ok)
            {
                $this->_problems[] = 'каталог '.$label.' отсутствует или недоступен для записи';
            }

            $this->_line($ok ? 'ok' : 'fail', $label, $quiet);
        }
    }

    protected function _config($quiet)
    {
        echo PHP_EOL.'Конфигурация'.PHP_EOL;

        $this->_line(is_file($this->_root().'/.env') ? 'ok' : 'warn', '.env', $quiet);

        foreach (['COOKIE_SALT', 'AUTH_HASH_KEY'] as $secret)
        {
            $value = getenv($secret);

            $ok = ($value !== FALSE AND strlen($value) >= 16);

            if ( ! $ok)
            {
                $this->_problems[] = 'переменная окружения '.$secret.' не задана (или короче 16 символов)';
            }

            $this->_line($ok ? 'ok' : 'fail', 'секрет '.$secret, $quiet);
        }

        $required_configs = ['database', 'cookie', 'auth', 'url', 'site_config', 'erp111_migrations_postgresql'];

        foreach ($required_configs as $name)
        {
            $found = Kohana::find_file('config', $name);

            if ( ! $found)
            {
                $this->_problems[] = 'не найден конфиг config/'.$name.'.php';
            }

            $this->_line($found ? 'ok' : 'fail', 'config/'.$name.'.php', $quiet);
        }

        $environments = [
            Kohana::PRODUCTION  => 'PRODUCTION',
            Kohana::STAGING     => 'STAGING',
            Kohana::TESTING     => 'TESTING',
            Kohana::DEVELOPMENT => 'DEVELOPMENT',
        ];

        $environment = isset($environments[Kohana::$environment])
            ? $environments[Kohana::$environment]
            : 'UNKNOWN ('.Kohana::$environment.')';

        $this->_line(Kohana::$environment === Kohana::PRODUCTION ? 'warn' : 'ok',
            'ядро: '.Kohana::version().', окружение: '.$environment, $quiet);
    }

    protected function _database($quiet)
    {
        echo PHP_EOL.'PostgreSQL'.PHP_EOL;

        $groups = ['default', 'mdm'];

        foreach ($groups as $group)
        {
            $config = Kohana::$config->load('database.'.$group);

            if (empty($config['connection']['database']))
            {
                $this->_line('warn', 'группа '.$group.' не настроена', $quiet);
                continue;
            }

            try
            {
                $version = DB::query(Database::SELECT, 'SHOW server_version')
                    ->execute($group)
                    ->get('server_version');

                $database = $config['connection']['database'];

                $this->_line('ok', 'группа '.$group.' → '.$database.' (PostgreSQL '.$version.')', $quiet);

                $extensions = DB::query(Database::SELECT,
                        "SELECT extname FROM pg_extension ORDER BY extname")
                    ->execute($group)
                    ->as_array(NULL, 'extname');

                foreach (['ltree', 'btree_gist', 'pgcrypto'] as $extension)
                {
                    $installed = in_array($extension, $extensions, TRUE);

                    if ( ! $installed)
                    {
                        $this->_line('warn', 'в базе '.$database.' нет расширения '.$extension, $quiet);
                    }
                }

                // Не обязательно: без него поиск по части строки работает, но читает таблицу целиком
                if ($group === 'default' AND ! in_array('pg_trgm', $extensions, TRUE))
                {
                    $this->_line('warn', 'в базе '.$database.' нет расширения pg_trgm: поиск в списках без ускорения (CREATE EXTENSION pg_trgm, затем откатить и применить заново миграции *PerformanceIndexes)', $quiet);
                }
            }
            catch (Exception $e)
            {
                $this->_problems[] = 'нет подключения к группе БД '.$group.': '.$e->getMessage();
                $this->_line('fail', 'группа '.$group.': '.$e->getMessage(), $quiet);
            }
        }

        try
        {
            $migrator  = Service_Erp111_Migrator::instance();
            $inventory = $migrator->inventory();
            $pending   = array_values(array_filter($inventory, function ($row) {
                return ! $row['applied'];
            }));

            $by_group = [];

            foreach ($inventory as $row)
            {
                $group = $row['db_group'];
                isset($by_group[$group]) OR $by_group[$group] = ['applied' => 0, 'total' => 0];

                $by_group[$group]['total']++;

                if ($row['applied'])
                {
                    $by_group[$group]['applied']++;
                }
            }

            if ($pending)
            {
                $this->_problems[] = 'не применено миграций: '.count($pending).' — выполните make migrate';
            }

            $summary = [];

            foreach ($by_group as $group => $stats)
            {
                $summary[] = $group.' '.$stats['applied'].'/'.$stats['total'];
            }

            $this->_line($pending ? 'warn' : 'ok',
                'миграции: '.count($inventory).' всего ('.implode(', ', $summary).'), ожидает '.count($pending),
                $quiet);
        }
        catch (Exception $e)
        {
            $this->_problems[] = 'не удалось проверить миграции: '.$e->getMessage();
            $this->_line('fail', 'миграции: '.$e->getMessage(), $quiet);
        }
    }

    protected function _modules($quiet)
    {
        echo PHP_EOL.'Модули'.PHP_EOL;

        $enabled = Kohana::modules();
        $on_disk = [];

        foreach (new DirectoryIterator(APPPATH.'modules') as $item)
        {
            if ($item->isDir() AND ! $item->isDot())
            {
                $on_disk[] = $item->getFilename();
            }
        }

        sort($on_disk);

        $erp_on_disk = array_values(array_filter($on_disk, function ($name) {
            return strpos($name, 'erp') === 0 OR ctype_digit(substr($name, 0, 3));
        }));

        $enabled_erp = [];

        foreach (array_keys($enabled) as $name)
        {
            if (strpos($name, 'erp') === 0 OR ctype_digit(substr($name, 0, 3)))
            {
                $enabled_erp[] = $name;
            }
        }

        $not_enabled = array_diff($erp_on_disk, $enabled_erp);
        $missing     = array_diff($enabled_erp, $on_disk);

        foreach ($missing as $name)
        {
            $this->_problems[] = 'модуль '.$name.' включён в bootstrap, но отсутствует на диске';
        }

        $this->_line($missing ? 'fail' : 'ok',
            'подключено модулей: '.count($enabled).' (из них модулей ERP: '.count($enabled_erp).')', $quiet);

        if ($not_enabled)
        {
            $this->_line('warn', 'на диске, но не подключены: '.implode(', ', $not_enabled), $quiet);
        }
    }

    protected function _dependencies($quiet)
    {
        echo PHP_EOL.'Зависимости'.PHP_EOL;

        $composer = $this->_root().'/vendor/composer/installed.json';
        $stub     = $this->_root().'/vendor/autoload.php';

        if (is_file($composer))
        {
            $installed = json_decode(file_get_contents($composer), TRUE);
            $count = isset($installed['packages']) ? count($installed['packages']) : count($installed);

            $this->_line('ok', 'composer: установлено пакетов '.$count, $quiet);
        }
        elseif (is_file($stub))
        {
            $this->_line('warn', 'vendor/autoload.php — заглушка (composer install не выполнялся)', $quiet);
        }
        else
        {
            $this->_problems[] = 'нет vendor/autoload.php — выполните composer install';
            $this->_line('fail', 'vendor/autoload.php отсутствует', $quiet);
        }
    }
}
