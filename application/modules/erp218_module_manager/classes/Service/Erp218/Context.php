<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Контекстная карточка модуля — то, что нужно агенту (и человеку) перед
 * правкой одного модуля: контракт, таблицы, роуты, права, события, MCP,
 * тесты, зависимости и границы.
 *
 * Зачем: разбор всего репозитория (30+ модулей, ~150 000 строк) стоит тысячи
 * токенов и почти всегда не нужен. Карточка собирается из самого кода за доли
 * секунды и отвечает на вопросы «где контракт», «чужие данные не тронуты»,
 * «что сломается рядом» без чтения соседних модулей.
 *
 *   bin/console module:context --module=erp401_wms
 *   bin/console module:context --module=401 --json
 *   bin/console module:context --module=erp401_wms --files
 *
 * Сервис работает без обращений к БД (только диск и загруженный каскад
 * модулей), поэтому пригоден в любой среде разработки.
 *
 * @package Erp218
 */
class Service_Erp218_Context {

    /** @var Service_Erp218_Context */
    protected static $_instance;

    /** @var array  кеш содержимого файлов: путь => текст */
    protected $_sources = [];

    /**
     * @return Service_Erp218_Context
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
     * Находит модуль по точному имени, ID или началу имени.
     *
     *   'erp401_wms' → erp401_wms
     *   '401'        → erp401_wms
     *   'erp401'     → erp401_wms
     *
     * @param   string  $needle
     * @return  array|NULL  строка инвентаризации модуля
     */
    public function resolve($needle)
    {
        $needle = trim((string) $needle);

        if ($needle === '')
        {
            return NULL;
        }

        $modules = Service_Erp218_Inventory::instance()->modules();
        $by_name = [];

        foreach ($modules as $row)
        {
            $by_name[$row['name']] = $row;
        }

        if (isset($by_name[$needle]))
        {
            return $by_name[$needle];
        }

        if (preg_match('/^\d{3}$/', $needle))
        {
            foreach ($by_name as $row)
            {
                if ((string) $row['id'] === $needle)
                {
                    return $row;
                }
            }

            return NULL;
        }

        // Неоднозначный ввод ('erp' — это все модули): возвращаем NULL,
        // вызывающий покажет список вариантов.
        $matches = [];

        foreach ($by_name as $name => $row)
        {
            if (strpos($name, $needle) === 0)
            {
                $matches[$name] = $row;
            }
        }

        if (count($matches) === 1)
        {
            return reset($matches);
        }

        return NULL;
    }

    /**
     * Подсказка для неоднозначного/неизвестного имени.
     *
     * @param   string  $needle
     * @return  array
     */
    public function suggest($needle)
    {
        $needle  = trim((string) $needle);
        $matches = [];

        foreach (Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ($needle === '' OR stripos($row['name'], $needle) !== FALSE)
            {
                $matches[] = $row['name'];
            }
        }

        sort($matches);

        return $matches;
    }

    /**
     * Назначение модуля одной фразой (из README) — для карты модулей и таблиц
     * документации. Полный текст остаётся в README модуля.
     *
     * @param   string  $path  каталог модуля
     * @return  string|NULL
     */
    public function purpose($path, $limit = 200)
    {
        return $this->_purpose(rtrim($path, '/').'/README.md', $limit);
    }

    /**
     * Собирает карточку модуля.
     *
     * @param   string  $module   имя модуля (см. resolve())
     * @return  array
     * @throws  Kohana_Exception  модуль не найден
     */
    public function build($module)
    {
        $row = $this->resolve($module);

        if ($row === NULL)
        {
            throw new Kohana_Exception(
                'Модуль «:module» не найден. Варианты: :list',
                ['module' => $module, ':list' => implode(', ', $this->suggest($module))]
            );
        }

        $root = $row['path'];
        $id   = $row['id'];

        $files = $this->_php_files($root);

        $card = [
            'module'     => $row['name'],
            'id'         => $id,
            'slug'       => $row['slug'],
            'path'       => str_replace($this->_root().'/', '', $root),
            'enabled'    => (bool) $row['enabled'],
            'core'       => (bool) $row['core'],
            'readme'     => $row['readme'],
            'has_init'   => $row['init'],
            'has_mcp'    => is_file($root.'/mcp.php'),
            'purpose'    => $this->_purpose($root.'/README.md'),
            'files'      => count($files),
            'lines'      => $row['lines'],
        ];

        $card['tables']      = $this->_tables($root, $id);
        $card['migrations']  = $this->_migrations($root);
        $card['models']      = $this->_models($root);
        $card['services']    = $this->_classes_with_methods($root, 'Service', 'public function ');
        $card['controllers'] = $this->_controllers($root);
        $card['routes']      = $this->_routes($row['name'], $id);
        $card['acl']         = $this->_acl($id, $files);
        $card['mcp']         = $this->_mcp($root);
        $card['json_api']    = $this->_json_api($root);
        $card['events']      = $this->_events($files);
        $card['depends_on']  = $this->_depends_on($files, $row['name']);
        $card['used_by']     = $this->_used_by($row['name'], $id);
        $card['tests']       = $this->_tests($id, $row['name']);
        $card['read_order']  = $this->_read_order($card);

        return $card;
    }

    /**
     * Текстовая карточка — компактный вывод для агента и человека.
     *
     * @param   array   $card
     * @param   boolean $with_files  показать файлы для чтения
     * @return  string
     */
    public function render(array $card, $with_files = TRUE)
    {
        $status = $card['core'] ? 'ядро' : ($card['enabled'] ? 'включён' : 'выключен');

        $out = 'PromERP · '.$card['module'].'  (ID '.($card['id'] === NULL ? '—' : $card['id']).', '.$status.')'.PHP_EOL;
        $out .= $card['path'].' · файлов '.$card['files'].' · строк '.number_format($card['lines'], 0, '.', ' ');

        if ($card['purpose'])
        {
            $out .= PHP_EOL.'Назначение: '.$card['purpose'];
        }

        $out .= PHP_EOL.PHP_EOL.$this->_render_block('Таблицы (владелец данных)', $card['tables']);
        $out .= $this->_render_block('Миграции', $card['migrations']);
        $out .= $this->_render_block('Модели', $card['models']);
        $out .= $this->_render_block('Сервисы (единственный вход в данные модуля)', $card['services']);
        $out .= $this->_render_block('Контроллеры', $card['controllers']);
        $out .= $this->_render_block('Роуты', $card['routes']);
        $out .= $this->_render_block('Права (application/config/acl.php)', $card['acl']);
        $out .= $this->_render_block('MCP-инструменты', $card['mcp']);
        $out .= $this->_render_block('JSON API', $card['json_api']);
        $out .= $this->_render_block('События', $card['events']);
        $out .= $this->_render_block('Зависит от (через сервисы)', $card['depends_on']);
        $out .= $this->_render_block('Используют', $card['used_by']);
        $out .= $this->_render_block('Тесты', $card['tests']);

        $out .= PHP_EOL.'Проверки: make check-module module='.$card['module'].PHP_EOL;

        if ($with_files AND $card['read_order'])
        {
            $out .= PHP_EOL.'Читать в этом порядке (и этого достаточно):'.PHP_EOL;

            foreach ($card['read_order'] as $index => $path)
            {
                $out .= '  '.($index + 1).'. '.$path.PHP_EOL;
            }
        }

        return $out;
    }

    /**
     * Блок карточки: заголовок и значения через "; " (пустая строка → пусто).
     *
     * @param   string  $title
     * @param   array   $values
     * @return  string
     */
    protected function _render_block($title, array $values)
    {
        if ( ! $values)
        {
            return $title.': —'.PHP_EOL;
        }

        $items = [];

        foreach ($values as $key => $value)
        {
            if (is_array($value))
            {
                if ( ! $value)
                {
                    continue;
                }

                $value = implode(', ', $value);
            }

            $items[] = is_int($key) ? (string) $value : $key.' — '.$value;
        }

        if ( ! $items)
        {
            return $title.': —'.PHP_EOL;
        }

        // Карточка должна оставаться дешёвой для чтения: длинные блоки
        // обрезаем по границе элемента и говорим, сколько осталось.
        $text  = '';
        $left  = 0;

        foreach ($items as $index => $item)
        {
            $candidate = ($text === '') ? $item : $text.'; '.$item;

            if (mb_strlen($candidate) > 500)
            {
                $left = count($items) - $index;
                break;
            }

            $text = $candidate;
        }

        if ($text === '')
        {
            $text = mb_substr($items[0], 0, 497).'…';
            $left = count($items) - 1;
        }

        if ($left > 0)
        {
            $text .= ' … ещё '.$left;
        }

        return $title.': '.$text.PHP_EOL;
    }

    // =========================================================================
    //  Сбор фактов
    // =========================================================================

    /**
     * @return string
     */
    protected function _root()
    {
        return dirname(APPPATH);
    }

    /**
     * Все PHP-файлы модуля.
     *
     * @param   string  $root
     * @return  array   [абсолютный путь => относительный]
     */
    protected function _php_files($root)
    {
        $files = [];

        if ( ! is_dir($root))
        {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file)
        {
            if ( ! $file->isFile() OR substr($file->getFilename(), -4) !== '.php')
            {
                continue;
            }

            $path = $file->getPathname();
            $files[$path] = str_replace($this->_root().'/', '', $path);
        }

        ksort($files);

        return $files;
    }

    /**
     * Назначение модуля — первый абзац README (для карточки хватает одной фразы).
     *
     * @param   string  $readme
     * @return  string|NULL
     */
    protected function _purpose($readme, $limit = 200)
    {
        if ( ! is_file($readme))
        {
            return NULL;
        }

        $source = $this->_source($readme);

        // Разделы «Назначение»/«О модуле» описывают суть; шапка README —
        // это список ID/владельцев/статусов, для карточки бесполезный.
        $section = NULL;

        if (preg_match('/^#{2,3}\s*(?:Назначение|О модуле|Описание)\s*$\R(.+?)(?=^#{1,3}\s|\z)/msu', $source, $matches))
        {
            $section = $matches[1];
        }
        else
        {
            // Первый абзац после заголовка H1.
            $section = preg_replace('/^#\s+.*$/mu', '', $source, 1);
        }

        // Абзац целиком: строка «Модуль ведёт …:» часто продолжается на
        // следующей строке, и обрыв по первой строке теряет смысл.
        $paragraphs = preg_split('/\R\s*\R/u', (string) $section);

        foreach ($paragraphs as $paragraph)
        {
            $lines = [];

            foreach (preg_split('/\R/u', $paragraph) as $line)
            {
                $line = trim($line);

                if ($line === '' OR strpos($line, '#') === 0 OR strpos($line, '|') === 0
                    OR strpos($line, '>') === 0 OR strpos($line, '```') === 0
                    OR strpos($line, '* ') === 0 OR strpos($line, '- ') === 0
                    OR preg_match('/^\*\*[^*]+:\*\*/u', $line))
                {
                    continue;
                }

                $lines[] = $line;
            }

            $text = trim(preg_replace('/\*\*|`/u', '', preg_replace('/\s+/u', ' ', strip_tags(implode(' ', $lines)))));

            if (mb_strlen($text) < 20)
            {
                continue;
            }

            return mb_strlen($text) > $limit ? $this->_shorten($text, $limit) : $text;
        }

        return NULL;
    }

    /**
     * Таблицы модуля: из миграций, моделей и SQL-строк кода.
     *
     * @param   string  $root
     * @param   integer $id
     * @return  array
     */
    protected function _tables($root, $id)
    {
        $own        = [];
        $foreign    = [];
        $not_tables = [];
        $prefix     = ($id === NULL) ? NULL : 'erp_'.sprintf('%03d', $id).'_';

        $sql_context = '/\b(?:FROM|JOIN|INSERT\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?)\s+'
            .'(?:IF\s+NOT\s+EXISTS\s+)?(erp_\d{3}_[a-z0-9_]+)/i';

        foreach ($this->_php_files($root) as $path => $relative)
        {
            $source  = $this->_source($path);
            $is_migration = (strpos($relative, '/migrations') !== FALSE);

            foreach ($this->_match_all($source, '/\b(erp_\d{3}_[a-z0-9_]+)\b/i') as $table)
            {
                $table = strtolower($table);

                if ( ! $this->_is_table_like($table))
                {
                    continue;
                }

                if ($prefix !== NULL AND strpos($table, $prefix) === 0)
                {
                    $own[$table] = TRUE;
                }
            }

            // Миграции — установочный сценарий (в т.ч. seed подписок в erp208);
            // чужие таблицы в рабочем коде — сигнал нарушения владения данными.
            // Таблицы без префикса (например erp230_mdm в отдельной БД) видны
            // только здесь — по CREATE TABLE и хелперу create_table().
            if ($is_migration)
            {
                foreach ($this->_match_all($source, '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+(?:\w+\.)?["\'`]?(\w+)["\'`]?(?![\w%.])/i') as $table)
                {
                    $table = strtolower($table);

                    if ($this->_is_table_like($table))
                    {
                        $own[$table] = TRUE;
                    }
                }

                foreach ($this->_match_all($source, '/->create_table\(\s*[\'"]([a-z0-9_]+)[\'"]/i') as $table)
                {
                    $own[strtolower($table)] = TRUE;
                }

                // Имена ограничений, индексов и последовательностей — не таблицы
                // (пример: CONSTRAINT erp_100_user_scope_dates CHECK (...)).
                foreach (array(
                    '/CONSTRAINT\s+["\'`]?([a-z0-9_]+)/i',
                    '/\bCREATE\s+(?:UNIQUE\s+)?INDEX(?:\s+IF\s+NOT\s+EXISTS)?\s+["\'`]?([a-z0-9_]+)/i',
                    '/\bCREATE\s+SEQUENCE(?:\s+IF\s+NOT\s+EXISTS)?\s+["\'`]?([a-z0-9_]+)/i',
                ) as $pattern)
                {
                    foreach ($this->_match_all($source, $pattern) as $name)
                    {
                        $not_tables[strtolower($name)] = TRUE;
                    }
                }

                continue;
            }

            foreach ($this->_match_all($source, $sql_context) as $table)
            {
                $table = strtolower($table);

                if ($prefix === NULL OR strpos($table, $prefix) !== 0)
                {
                    $foreign[$table] = TRUE;
                }
            }
        }

        // Ограничения/индексы/последовательности, объявленные рядом с таблицами.
        foreach (array_keys($not_tables) as $name)
        {
            unset($own[$name], $foreign[$name]);
        }

        // Схемы и базы в SQL-строках — не таблицы.
        foreach (['public', 'mdm', 'promerp', 'promerp_mdm'] as $schema)
        {
            unset($own[$schema], $foreign[$schema]);
        }

        // Секционированные таблицы: `objects_y2026`/`objects_default` — части
        // `objects`. Показываем базовую таблицу, а не десяток партиций.
        foreach ($own as $table => $unused)
        {
            if (preg_match('/^(.+)_(?:y\d{4}|default)$/', $table, $match) AND isset($own[$match[1]]))
            {
                unset($own[$table]);
            }
        }

        $own     = array_keys($own);
        $foreign = array_keys($foreign);

        sort($own);
        sort($foreign);

        $result = ['свои' => $own];

        if ($foreign)
        {
            // Обращение к таблицам соседа допустимо только через его сервис
            // (см. AGENTS.md, «Владельцы данных») — такие места и есть кандидаты
            // на ревью.
            $result['чужие в SQL (проверить!)'] = $foreign;
        }

        return $result;
    }

    /**
     * Миграции модуля.
     *
     * @param   string  $root
     * @return  array
     */
    protected function _migrations($root)
    {
        $migrations = [];

        foreach (glob($root.'/migrations_pg/*.php') as $file)
        {
            $source = $this->_source($file);

            $group = $this->_first($source, '/\$_(?:db_group|table_group)\s*=\s*[\'"]([a-z0-9_]+)[\'"]/i');

            $migrations[basename($file, '.php')] = $group ? 'БД '.$group : 'default';
        }

        ksort($migrations);

        if ( ! $migrations AND is_dir($root.'/migrations'))
        {
            $migrations['(MySQL-миграции, не портирован)'] = count(glob($root.'/migrations/*.php')).' файлов в migrations/';
        }

        return $migrations;
    }

    /**
     * Модели модуля и их таблицы.
     *
     * @param   string  $root
     * @return  array
     */
    protected function _models($root)
    {
        $models = [];

        foreach ($this->_classes($root, 'Model') as $class => $file)
        {
            $source = $this->_source($file);
            $table  = $this->_first($source, '/\$_(?:table_name)\s*=\s*[\'"]([a-z0-9_]+)[\'"]/i');

            $models[$class] = $table ? $table : '?';
        }

        return $models;
    }

    /**
     * Контроллеры модуля: публичные действия.
     *
     * @param   string  $root
     * @return  array
     */
    protected function _controllers($root)
    {
        $controllers = [];

        foreach ($this->_classes($root, 'Controller') as $class => $file)
        {
            $source  = $this->_source($file);
            $actions = $this->_match_all($source, '/public function (action_[a-z0-9_]+)\s*\(/i');

            sort($actions);

            $controllers[$class] = $actions ? 'actions: '.implode(', ', $actions) : '—';
        }

        return $controllers;
    }

    /**
     * Классы слоя модуля: класс => файл.
     *
     * @param   string  $root
     * @param   string  $layer  Controller|Service|Model|Task
     * @return  array
     */
    protected function _classes($root, $layer)
    {
        $classes = [];
        $prefix  = $root.'/classes/'.$layer.'/';

        foreach ($this->_php_files($root) as $path => $relative)
        {
            if (strpos($path.'/', $prefix) !== 0)
            {
                continue;
            }

            $source = $this->_source($path);

            if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/mi', $source, $matches))
            {
                $classes[$matches[1]] = $path;
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * Публичные методы классов слоя (сервисы, вспомогательные классы).
     *
     * @param   string  $root
     * @param   string  $layer
     * @param   string  $needle
     * @return  array
     */
    protected function _classes_with_methods($root, $layer, $needle)
    {
        $result = [];

        foreach ($this->_classes($root, $layer) as $class => $file)
        {
            $source = $this->_source($file);

            // Короткая форма array(), длинная и статическая — все варианты публичных методов.
            $methods = $this->_match_all($source, '/(?:public|public static)\s+function\s+([a-zA-Z0-9_]+)\s*\(/i');

            $methods = array_values(array_diff($methods, ['__construct', '__call', '__get']));

            sort($methods);

            $result[$class] = $methods ? implode(', ', $methods) : '—';
        }

        return $result;
    }

    /**
     * Роуты модуля (по реестру Route, а не по regex — точнее).
     *
     * @param   string  $module
     * @param   integer $id
     * @return  array
     */
    protected function _routes($module, $id)
    {
        $directory = $id === NULL ? NULL : 'Erp'.sprintf('%03d', $id);
        $routes    = [];

        foreach (Route::all() as $name => $route)
        {
            $defaults = $route->defaults();

            $own = ($name === $module OR strpos($name, $module.'_') === 0);

            if ( ! $own AND ($directory === NULL OR ! isset($defaults['directory']) OR $defaults['directory'] !== $directory))
            {
                continue;
            }

            $target = isset($defaults['controller']) ? $defaults['controller'] : '?';

            if (isset($defaults['action']))
            {
                $target .= '/'.$defaults['action'];
            }

            // uri() требует значений обязательных параметров; карточка не должна
            // падать из-за маршрута, который сюда не входит.
            try
            {
                $uri = '/'.ltrim($route->uri($this->route_defaults($route)), '/');
            }
            catch (Throwable $e)
            {
                $uri = '(uri с обязательными параметрами)';
            }

            $routes[] = $name.' → '.$uri.' ['.$target.']';
        }

        sort($routes);

        return $routes;
    }

    /**
     * Значения параметров для обратного разбора адреса маршрута (Route::uri).
     *
     * @param   Route   $route
     * @return  array
     */
    protected function route_defaults(Route $route)
    {
        $params = [];

        foreach ($route->defaults() as $key => $value)
        {
            if (is_scalar($value) AND $value !== '')
            {
                $params[$key] = $value;
            }
        }

        // Плейсхолдеры, у которых нет значения по умолчанию: подставляем
        // безопасную заглушку, чтобы получить читаемый адрес.
        foreach (['id' => 1, 'item_id' => 1, 'warehouse_id' => 1, 'action' => 'index'] as $key => $value)
        {
            if ( ! isset($params[$key]))
            {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Права модуля: объявленные в acl.php и запрошенные в коде.
     *
     * @param   integer $id
     * @param   array   $files
     * @return  array
     */
    protected function _acl($id, array $files)
    {
        $declared = [];
        $used     = [];

        if ($id !== NULL)
        {
            $prefix = 'erp'.sprintf('%03d', $id);

            try
            {
                foreach (Kohana::$config->load('acl')->as_array() as $key => $roles)
                {
                    if (strpos($key, $prefix) === 0)
                    {
                        $declared[] = $key.' ['.implode('/', (array) $roles).']';
                    }
                }
            }
            catch (Throwable $e)
            {
                $declared[] = 'не удалось прочитать acl.php: '.$e->getMessage();
            }

            sort($declared);
        }

        foreach ($files as $path => $relative)
        {
            if (strpos($relative, '/views/') !== FALSE)
            {
                continue;
            }

            $source = $this->_source($path);

            foreach ($this->_match_all($source, '/->can\(\s*[\'"]([a-z0-9_.*]+)[\'"]/i') as $permission)
            {
                $used[$permission] = TRUE;
            }
        }

        $result = ['объявлено' => $declared ? $declared : []];

        if ($used)
        {
            $used = array_keys($used);
            sort($used);

            $result['запрошено в коде'] = $used;
        }

        if ( ! $declared AND ! $used)
        {
            return [];
        }

        return $result;
    }

    /**
     * Инструменты MCP, объявленные модулем (mcp.php).
     *
     * @param   string  $root
     * @return  array
     */
    protected function _mcp($root)
    {
        $file = $root.'/mcp.php';

        if ( ! is_file($file))
        {
            return [];
        }

        try
        {
            $declaration = require $file;
        }
        catch (Throwable $e)
        {
            return ['mcp.php не читается: '.$e->getMessage()];
        }

        if ( ! is_array($declaration) OR empty($declaration['tools']))
        {
            return [];
        }

        $tools = [];

        foreach ($declaration['tools'] as $name => $tool)
        {
            $flags = [];

            if ( ! empty($tool['mutating']))
            {
                $flags[] = 'изменяет';
            }

            if ( ! empty($tool['requires_approval']) OR ! empty($tool['approval']))
            {
                $flags[] = 'approval';
            }

            if ( ! empty($tool['scope']))
            {
                $flags[] = $tool['scope'];
            }

            $tools[] = $name.($flags ? ' ('.implode(', ', $flags).')' : ' (read-only)');
        }

        sort($tools);

        return $tools;
    }

    /**
     * JSON API модуля: контроллеры Api и read-only действия.
     *
     * @param   string  $root
     * @return  array
     */
    protected function _json_api($root)
    {
        $result = [];

        foreach ($this->_classes($root, 'Controller') as $class => $file)
        {
            if (strpos($class, '_Api') === FALSE)
            {
                continue;
            }

            $source  = $this->_source($file);
            $actions = $this->_match_all($source, '/public function (action_[a-z0-9_]+)\s*\(/i');

            $result[$class] = $actions ? 'actions: '.implode(', ', $actions) : '—';
        }

        return $result;
    }

    /**
     * События: что модуль публикует и на что подписан.
     *
     * @param   array   $files
     * @return  array
     */
    protected function _events(array $files)
    {
        $published = [];
        $listened  = [];
        $dynamic   = FALSE;

        $published_pattern = '/(?:Event::fire|EventBus::instance\(\)->(?:publish|fire|trigger)|'
            .'->(?:publish|_publish|emit|trigger|dispatch))\(\s*[\'"]([a-z0-9_.\-]+)[\'"]/i';

        $listened_pattern = '/(?:Event::listen|->listen)\(\s*[\'"]([a-z0-9_.\-]+)[\'"]/i';

        foreach ($files as $path => $relative)
        {
            $source = $this->_source($path);

            foreach ($this->_match_all($source, $published_pattern) as $event)
            {
                $published[$event] = TRUE;
            }

            foreach ($this->_match_all($source, $listened_pattern) as $event)
            {
                $listened[$event] = TRUE;
            }

            // Публикация через переменную ($event, $this->_publish($name, …)):
            // имена видит только рантайм — честно сообщаем об этом.
            if (preg_match('/(?:Event::fire|->(?:publish|_publish|emit|trigger|dispatch))\(\s*\$/i', $source))
            {
                $dynamic = TRUE;
            }
        }

        $published = array_keys($published);
        $listened  = array_keys($listened);

        sort($published);
        sort($listened);

        $result = [];

        if ($published)
        {
            $result['публикует'] = $published;
        }

        if ($listened)
        {
            $result['слушает'] = $listened;
        }

        if ($dynamic)
        {
            $result['имена в переменной'] = ['есть вызовы Event::fire($event) — точные имена смотреть в коде'];
        }

        return $result;
    }

    /**
     * От каких модулей зависит: обращения к чужим сервисам/моделям/таблицам.
     *
     * @param   array   $files
     * @param   string  $module
     * @return  array
     */
    protected function _depends_on(array $files, $module)
    {
        $own_id = NULL;

        if (preg_match('/^erp(\d{3})_/', $module, $matches))
        {
            $own_id = (int) $matches[1];
        }

        $found = [];

        foreach ($files as $path => $relative)
        {
            $source = $this->_source($path);

            $patterns = [
                'сервис'  => '/\bService_Erp(\d{3})_/',
                'модель'  => '/\bModel_Erp(\d{3})_/',
                'ORM'     => '/\bORM::factory\(\s*[\'"]Erp(\d{3})_/',
                'таблица' => '/\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+(?:IF NOT EXISTS\s+)?erp_(\d{3})_/i',
            ];

            foreach ($patterns as $kind => $pattern)
            {
                foreach ($this->_match_all($source, $pattern) as $id)
                {
                    $id = (int) $id;

                    if ($id === $own_id)
                    {
                        continue;
                    }

                    if ( ! isset($found[$id]))
                    {
                        $found[$id] = [];
                    }

                    $found[$id][$kind] = TRUE;
                }
            }
        }

        ksort($found);

        $result = [];

        foreach ($found as $id => $kinds)
        {
            $result[] = $this->_module_name($id).' ('.implode(', ', array_keys($kinds)).')';
        }

        return $result;
    }

    /**
     * Кто использует сервисы этого модуля (обратная зависимость).
     *
     * @param   string  $module
     * @param   integer $id
     * @return  array
     */
    protected function _used_by($module, $id)
    {
        if ($id === NULL)
        {
            return [];
        }

        $needle   = 'Service_Erp'.sprintf('%03d', $id).'_';
        $root     = $this->_root().'/application/modules';
        $consumers = [];

        foreach (Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ($row['name'] === $module OR $row['core'] OR ! is_dir($row['path']))
            {
                continue;
            }

            foreach ($this->_php_files($row['path']) as $path => $relative)
            {
                if (strpos($relative, '/views/') !== FALSE OR strpos($relative, '/migrations') !== FALSE)
                {
                    continue;
                }

                if (strpos($this->_source($path), $needle) !== FALSE)
                {
                    $consumers[$row['name']] = TRUE;
                    break;
                }
            }
        }

        $consumers = array_keys($consumers);
        sort($consumers);

        return $consumers;
    }

    /**
     * Имя модуля по ID (для читаемой расшифровки зависимостей).
     *
     * @param   integer $id
     * @return  string
     */
    protected function _module_name($id)
    {
        foreach (Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ((int) $row['id'] === (int) $id)
            {
                return $row['name'];
            }
        }

        return 'erp'.sprintf('%03d', $id);
    }

    /**
     * Тесты модуля: файлы и число тестовых методов.
     *
     * @param   integer $id
     * @param   string  $module
     * @return  array
     */
    protected function _tests($id, $module)
    {
        if ($id === NULL)
        {
            // У модуля без числового ID тесты ищем по имени: llm → LlmTest.
            $needle = strtolower(str_replace('_', '', $module));
            $found  = [];

            foreach (glob($this->_root().'/tests/Unit/*Test.php') as $file)
            {
                if (strpos(strtolower(str_replace('_', '', basename($file))), $needle) !== FALSE)
                {
                    $methods = $this->_match_all($this->_source($file), '/public function (test_[a-zA-Z0-9_]+)\s*\(/');
                    $found[] = str_replace($this->_root().'/', '', $file).' — '.count($methods).' тестов';
                }
            }

            if ($found)
            {
                sort($found);

                return ['тесты по имени модуля' => $found];
            }

            return ['тестов модуля нет' => ['числового ID нет, отдельного файла тестов не нашлось']];
        }

        $marker = 'Erp'.sprintf('%03d', $id);
        $own    = [];
        $related = [];

        foreach (glob($this->_root().'/tests/Unit/*Test.php') as $file)
        {
            $name   = basename($file);
            $source = $this->_source($file);

            if (strpos($name, $marker) === 0)
            {
                $methods = $this->_match_all($source, '/public function (test_[a-zA-Z0-9_]+)\s*\(/');

                $own[] = str_replace($this->_root().'/', '', $file).' — '.count($methods).' тестов';
                continue;
            }

            if (strpos($source, 'Service_'.$marker.'_') !== FALSE)
            {
                $related[] = $name;
            }
        }

        sort($own);
        sort($related);

        $result = [];

        if ($own)
        {
            $result[$marker] = $own;
            $result['запуск'] = ['bin/test --filter='.$marker];
        }
        else
        {
            $result['тестов модуля нет'] = ['проверять через соседние сценарии'];
        }

        if ($related)
        {
            $result['затрагивают в своих сценариях'] = $related;
        }

        return $result;
    }

    /**
     * Минимальный список файлов, которые нужно прочитать перед правкой.
     *
     * @param   array   $card
     * @return  array
     */
    protected function _read_order(array $card)
    {
        $order = [];
        $root  = $this->_root().'/'.$card['path'];

        if ($card['readme'])
        {
            $order[] = $card['path'].'/README.md';
        }

        foreach (['init.php', 'mcp.php'] as $file)
        {
            if (is_file($root.'/'.$file))
            {
                $order[] = $card['path'].'/'.$file;
            }
        }

        foreach (glob($root.'/migrations_pg/*.php') as $file)
        {
            $order[] = str_replace($this->_root().'/', '', $file);
        }

        foreach ($this->_classes($root, 'Service') as $class => $file)
        {
            $order[] = str_replace($this->_root().'/', '', $file);
        }

        return $order;
    }

    // =========================================================================
    //  Мелкие помощники разбора
    // =========================================================================

    /**
     * Отсеивает имена, которые не являются таблицами: индексы, ограничения,
     * последовательности. Иначе в карточке появляются строки вида
     * `erp_100_user_scope_unique`.
     *
     * @param   string  $name
     * @return  boolean
     */
    protected function _is_table_like($name)
    {
        return ! preg_match('/(?:_idx|_uq|_uniq|_unique|_key|_check|_ck|_fkey|_pkey|_seq|_trg|_index|_constraint)$/', $name);
    }

    /**
     * Обрезка фразы по границе слова (аккуратнее, чем просто по символу).
     *
     * @param   string  $text
     * @param   integer $limit
     * @return  string
     */
    protected function _shorten($text, $limit)
    {
        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        if ($space !== FALSE AND $space > (int) ($limit * 0.6))
        {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,;:—-').'…';
    }

    /**
     * Содержимое файла с кешем на время сборки карточки: один модуль
     * просматривается несколько раз (таблицы, события, зависимости, права).
     *
     * @param   string  $path
     * @return  string
     */
    protected function _source($path)
    {
        if ( ! isset($this->_sources[$path]))
        {
            $this->_sources[$path] = is_file($path) ? (string) file_get_contents($path) : '';
        }

        return $this->_sources[$path];
    }

    /**
     * Все совпадения первой группы регулярного выражения.
     *
     * @param   string  $source
     * @param   string  $pattern
     * @return  array
     */
    protected function _match_all($source, $pattern)
    {
        if ( ! preg_match_all($pattern, $source, $matches))
        {
            return [];
        }

        return $matches[1];
    }

    /**
     * Первое совпадение первой группы или NULL.
     *
     * @param   string  $source
     * @param   string  $pattern
     * @return  string|NULL
     */
    protected function _first($source, $pattern)
    {
        if ( ! preg_match($pattern, $source, $matches))
        {
            return NULL;
        }

        return $matches[1];
    }
}
