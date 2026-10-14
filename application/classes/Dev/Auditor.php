<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Быстрый статический аудит кодовой базы («рефлексия без запуска»).
 *
 * Разбирает PHP-файлы токенайзером (без include/require, без БД) и ищет класс
 * дефектов, которые обычно валят приложение уже в рантайме:
 *
 *   1. duplicate_class  — один и тот же класс объявлен дважды в одном слое (fatal при загрузке);
 *   2. signature        — метод ребёнка не совместим с методом родителя (PHP fatal / LSP);
 *   3. abstract         — не реализованы абстрактные методы родителя/интерфейса;
 *   4. class_file       — имя класса не соответствует пути (автозагрузчик не найдёт файл);
 *   5. missing_parent   — родитель/интерфейс не найден ни в проекте, ни среди встроенных;
 *   6. task_contract    — Task_* без унаследованного Minion_Task или без _execute();
 *   7. migration        — pgMigration_* без up()/down() или не от pgMigration;
 *   8. view             — литеральный View::factory('a/b'), которого нет в файловой системе;
 *   9. events           — событие публикуется дважды (шина + Event::fire) → дубли в журнале;
 *  10. json_guards      — ответ об ошибке в API без остановки: действие доходит до данных;
 *  11. http_405         — HTTP_Exception_405 без ->allowed(): 500 вместо честного 405;
 *  12. ui_components    — компонент интерфейса вызван, а представления нет; общий
 *      компонент потерял подпись/`scope`; сколько представлений ещё верстают
 *      таблицу руками (одна сводная находка, а не сотня одинаковых).
 *
 * Стоимость: один проход по файлам, сотни миллисекунд — пригодно для запуска
 * после каждой правки (в т.ч. как обратная связь для LLM-агентов):
 *
 *   bin/console dev:audit            # компактный отчёт
 *   bin/console dev:audit --quiet    # только ошибки
 *   bin/console dev:audit --json     # машиночитаемо (для агентов/CI)
 *
 * @package Dev
 */
class Dev_Auditor {

    const ERROR = 'error';
    const WARN  = 'warn';

    /** @var array  имя класса => список объявлений */
    protected $_classes = [];

    /** @var array  файл => относительный путь */
    protected $_paths = [];

    /** @var array */
    protected $_findings = [];

    /** @var array  какие записи baseline сработали */
    protected $_baseline_used = [];

    /** @var array */
    protected $_stats = ['files' => 0, 'classes' => 0, 'methods' => 0, 'time' => 0.0];

    /**
     * @param   array  $paths  каталоги для анализа (по умолчанию application, modules, system)
     * @return  array  ['errors' => […], 'warnings' => […], 'stats' => […] ]
     */
    public function audit(?array $paths = NULL)
    {
        $started = microtime(TRUE);

        $paths = $paths ?: $this->default_paths();

        $this->_classes = [];
        $this->_paths   = [];
        $this->_findings = [];
        $this->_baseline_used = [];

        foreach ($paths as $path)
        {
            $this->parse_tree($path);
        }

        $this->check_duplicates();
        $this->check_hierarchy();
        $this->check_class_file_names();
        $this->check_tasks();
        $this->check_task_files();
        $this->check_migrations();
        $this->check_views();
        $this->check_view_static_context();
        $this->check_ui_components();
        $this->check_routes();
        $this->check_redirect_in_try();
        $this->check_acl();
        $this->check_fluent_calls();
        $this->check_dependencies();
        $this->check_mcp();
        $this->check_events();
        $this->check_json_guards();
        $this->check_api_read_only();
        $this->check_http_405();
        $this->check_uuid_lookup();

        $errors = $warnings = $baseline = [];

        $accepted = $this->baseline();

        foreach ($this->_findings as $finding)
        {
            if ($this->in_baseline($finding, $accepted, $used))
            {
                $finding['reason'] = $used;
                $baseline[] = $finding;
                continue;
            }

            if ($finding['severity'] === self::ERROR)
            {
                $errors[] = $finding;
            }
            else
            {
                $warnings[] = $finding;
            }
        }

        // Записи baseline, которые больше ни на что не указывают — их пора удалить
        $stale = [];

        foreach ($accepted as $index => $entry)
        {
            if ( ! isset($this->_baseline_used[$index]))
            {
                $stale[] = $entry;
            }
        }

        $this->_stats['time'] = round(microtime(TRUE) - $started, 3);

        return [
            'errors'   => $errors,
            'warnings' => $warnings,
            'baseline' => $baseline,
            'stale'    => $stale,
            'stats'    => $this->_stats,
        ];
    }

    // -- Разбор ---------------------------------------------------------------

    /**
     * Каталоги разбора по умолчанию: код приложения и включённые модули.
     *
     * Выключенный модуль в контур не входит: его init.php не выполняется,
     * классы не участвуют в каскаде, страницы не открываются. Находки в нём —
     * шум, который прячет настоящие дефекты: `unittest` и `userguide` тянут
     * родительские классы чужого фреймворка, `PostgreSQL_PDO` дублирует класс
     * рабочего `PostgreSQL`, `yandexauth` держит маршруты к контроллеру,
     * которого нет. Поэтому обходим ровно то, что загружает bootstrap.
     *
     * @return  array
     */
    protected function default_paths()
    {
        $paths = [APPPATH.'classes'];

        foreach (Kohana::modules() as $path)
        {
            $paths[] = $path;
        }

        // Классы ядра нужны, чтобы находить родителей и реализации интерфейсов
        $paths[] = SYSPATH.'classes';

        return $paths;
    }

    /**
     * @param   string  $path
     * @return  void
     */
    protected function parse_tree($path)
    {
        if (is_file($path))
        {
            $this->_paths[$path] = $this->relative($path);

            $this->parse_file($path);

            return;
        }

        if ( ! is_dir($path))
        {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file)
        {
            if ( ! $file->isFile() OR substr($file->getFilename(), -4) !== '.php')
            {
                continue;
            }

            $pathname = $file->getPathname();

            // /tests/ — чужие наборы тестов модулей: их родители (PHPUnit,
            // Kohana_Unittest_TestCase) живут вне рабочего контура, а сами
            // файлы не попадают в каскад. Свои тесты проекта лежат в /tests.
            foreach (['/vendor/', '/logs/', '/cache/', '/sessions/', '/tests/'] as $skip)
            {
                if (strpos($pathname, $skip) !== FALSE)
                {
                    continue 2;
                }
            }

            $this->_paths[$pathname] = $this->relative($pathname);

            $this->parse_file($pathname);
        }
    }

    /**
     * Разбирает один файл: объявления классов, наследование, методы.
     *
     * @param   string  $file
     * @return  void
     */
    protected function parse_file($file)
    {
        $source = @file_get_contents($file);

        if ($source === FALSE)
        {
            return;
        }

        $this->_stats['files']++;

        if (strpos($source, 'class ') === FALSE AND strpos($source, 'interface ') === FALSE AND strpos($source, 'trait ') === FALSE)
        {
            // Файл без объявлений (view, конфиг) — он уже в инвентаре _paths,
            // его проверят проверки представлений и зависимостей
            return;
        }

        try
        {
            $tokens = token_get_all($source);
        }
        catch (Throwable $e)
        {
            return;
        }

        $relative = $this->relative($file);
        $count    = count($tokens);

        for ($i = 0; $i < $count; $i++)
        {
            if ( ! is_array($tokens[$i]))
            {
                continue;
            }

            $type = $tokens[$i][0];

            if ($type !== T_CLASS AND $type !== T_INTERFACE AND $type !== T_TRAIT)
            {
                continue;
            }

            // Пропускаем ::class и анонимные классы (new class)
            $prev = $this->prev_significant($tokens, $i);

            if ($prev !== NULL AND (is_array($prev) AND in_array($prev[0], [T_DOUBLE_COLON, T_NEW, T_OBJECT_OPERATOR], TRUE)))
            {
                continue;
            }

            $declaration = $this->parse_declaration($tokens, $i, $file, $relative);

            if ($declaration === NULL)
            {
                continue;
            }

            $this->_classes[$declaration['name']][] = $declaration;
            $this->_stats['classes']++;
            $this->_stats['methods'] += count($declaration['methods']);
        }
    }

    /**
     * Разбор объявления класса вместе с методами.
     *
     * @param   array   $tokens
     * @param   integer $index  позиция T_CLASS/T_INTERFACE/T_TRAIT
     * @param   string  $file
     * @param   string  $relative
     * @return  array|null
     */
    protected function parse_declaration(array $tokens, $index, $file, $relative)
    {
        $count = count($tokens);

        $kind = strtolower($tokens[$index][1]);

        // Имя класса
        $name  = NULL;
        $line  = $tokens[$index][2];
        $i     = $index + 1;

        for (; $i < $count; $i++)
        {
            if (is_array($tokens[$i]) AND $tokens[$i][0] === T_STRING)
            {
                $name = $tokens[$i][1];
                break;
            }

            if ($tokens[$i] === '{' OR $tokens[$i] === ';')
            {
                return NULL;
            }
        }

        if ($name === NULL)
        {
            return NULL;
        }

        // Наследование
        $extends    = NULL;
        $implements = [];

        for (; $i < $count; $i++)
        {
            if ($tokens[$i] === '{')
            {
                break;
            }

            if (is_array($tokens[$i]) AND $tokens[$i][0] === T_EXTENDS)
            {
                $extends = $this->read_name($tokens, $i + 1, $i);
            }
            elseif (is_array($tokens[$i]) AND $tokens[$i][0] === T_IMPLEMENTS)
            {
                $buffer = '';

                for ($j = $i + 1; $j < $count; $j++)
                {
                    if ($tokens[$j] === '{')
                    {
                        $i = $j - 1;
                        break;
                    }

                    $buffer .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }

                foreach (explode(',', $buffer) as $interface)
                {
                    $interface = trim($interface);

                    if ($interface !== '')
                    {
                        $implements[] = $interface;
                    }
                }
            }
        }

        $body_start = $i;
        $body_end   = $this->matching_brace($tokens, $body_start);

        $methods = [];

        for ($j = $body_start + 1; $j < $body_end; $j++)
        {
            if ( ! is_array($tokens[$j]) OR $tokens[$j][0] !== T_FUNCTION)
            {
                continue;
            }

            $method = $this->parse_method($tokens, $j, $body_end);

            if ($method !== NULL)
            {
                $methods[strtolower($method['name'])] = $method;
            }
        }

        return [
            'name'       => $name,
            'kind'       => $kind,
            'file'       => $file,
            'relative'   => $relative,
            'line'       => $line,
            'extends'    => $extends,
            'implements' => $implements,
            'abstract'   => $this->is_abstract($tokens, $index),
            'methods'    => $methods,
        ];
    }

    /**
     * Разбор сигнатуры метода.
     *
     * @param   array   $tokens
     * @param   integer $index
     * @param   integer $body_end
     * @return  array|null
     */
    protected function parse_method(array $tokens, $index, $body_end)
    {
        $count = count($tokens);
        $name  = NULL;

        for ($i = $index + 1; $i < $body_end; $i++)
        {
            if (($tokens[$i] === '(' OR $tokens[$i] === ';') AND $name === NULL)
            {
                if ($tokens[$i] === ';')
                {
                    return NULL;
                }

                break;
            }

            if (is_array($tokens[$i]) AND $tokens[$i][0] === T_STRING)
            {
                $name = $tokens[$i][1];
                break;
            }
        }

        if ($name === NULL OR $name === '')
        {
            return NULL;
        }

        // Тело параметров
        $buffer = '';
        $depth  = 0;

        for ($i = $index; $i < $count; $i++)
        {
            $token = $tokens[$i];
            $text  = is_array($token) ? $token[1] : $token;

            if ($text === '(')
            {
                $depth++;

                if ($depth === 1)
                {
                    continue;
                }
            }
            elseif ($text === ')')
            {
                $depth--;

                if ($depth === 0)
                {
                    break;
                }
            }

            if ($depth >= 1)
            {
                $buffer .= $text;
            }
        }

        $parameters = [];

        foreach ($this->split_params($buffer) as $param)
        {
            $param = trim($param);

            if ($param === '')
            {
                continue;
            }

            preg_match('/(?P<type>[\?\w\\\\\[\]]+)?\s*(?P<ref>&)?\s*(?P<variadic>\.\.\.)?\s*\$(?P<name>\w+)\s*(?P<default>=.*)?$/', $param, $matches);

            if (empty($matches['name']))
            {
                continue;
            }

            $parameters[] = [
                'name'     => $matches['name'],
                'type'     => isset($matches['type']) ? trim($matches['type']) : '',
                'variadic' => ! empty($matches['variadic']),
                'default'  => isset($matches['default']) AND trim($matches['default']) !== '',
            ];
        }

        return [
            'name'       => $name,
            'parameters' => $parameters,
            'abstract'   => ! $this->has_body($tokens, $index, $body_end),
        ];
    }

    // -- Проверки -------------------------------------------------------------

    protected function check_duplicates()
    {
        foreach ($this->_classes as $name => $declarations)
        {
            if (count($declarations) < 2)
            {
                continue;
            }

            $layers = [];

            foreach ($declarations as $declaration)
            {
                $layers[$this->layer($declaration['file'], $declaration['relative'])][] = $declaration;
            }

            foreach ($layers as $layer => $in_layer)
            {
                if (count($in_layer) < 2)
                {
                    continue;
                }

                $files = [];

                foreach ($in_layer as $declaration)
                {
                    $files[] = $declaration['relative'].':'.$declaration['line'];
                }

                $this->add(self::ERROR, 'duplicate_class',
                    'класс '.$name.' объявлен '.count($in_layer).' раза в одном слое ('.$layer.'): '.implode(', ', $files),
                    $in_layer[0]);
            }

            // Перекрытие класса ядра (system) и перекрытие классом приложения —
            // штатный механизм каскада Kohana: application/classes подменяет
            // модульную реализацию, это документированный способ расширения
            // (так живут ORM с reset_database_instances(), Minion_Task с
            // _flag(), модели пользователя и ролей). Сообщаем только о
            // конфликте двух модулей: там непонятно, чья реализация победит
            // в зависимости от порядка в bootstrap.
            $modules = [];

            foreach (array_keys($layers) as $layer)
            {
                if (strpos($layer, 'module:') === 0)
                {
                    $modules[] = $layer;
                }
            }

            if (count($modules) > 1)
            {
                $this->add(self::WARN, 'class_shadow',
                    'класс '.$name.' объявлен в нескольких модулях: '.implode(', ', $modules),
                    $declarations[0]);
            }
            elseif ($modules AND isset($layers['application']))
            {
                // Прозрачность: перекрытие остаётся видимым в --json-отчёте
                $this->_stats['class_overrides'][] = $name;
            }
        }
    }

    protected function check_hierarchy()
    {
        foreach ($this->_classes as $name => $declarations)
        {
            foreach ($declarations as $declaration)
            {
                if ($declaration['kind'] !== 'class')
                {
                    continue;
                }

                $parents = [];

                if ($declaration['extends'] !== NULL)
                {
                    $parents[] = $declaration['extends'];
                }

                foreach ($declaration['implements'] as $interface)
                {
                    $parents[] = $interface;
                }

                foreach ($parents as $parent)
                {
                    if ( ! $this->class_exists($parent))
                    {
                        // Устаревшие миграции MySQL (каталог migrations/) наследуют
                        // класс Migration из удалённого модуля migrations — это
                        // ожидаемое наследство «мёртвой» ветки, а не поломка сборки.
                        if (strpos($declaration['relative'], '/migrations/') !== FALSE)
                        {
                            $this->add(self::WARN, 'legacy_migration',
                                'устаревшая миграция '.$name.' (MySQL) наследует отсутствующий '.$parent,
                                $declaration);
                        }
                        else
                        {
                            $this->add($this->severity_for($declaration), 'missing_parent',
                                'класс '.$name.' наследует '.$parent.', которого нет в проекте',
                                $declaration);
                        }

                        continue;
                    }

                    if ( ! isset($this->_classes[$parent]))
                    {
                        continue;
                    }

                    $parent_declaration = $this->_classes[$parent][0];

                    if ($declaration['abstract'] === FALSE)
                    {
                        foreach ($parent_declaration['methods'] as $method)
                        {
                            if ($method['abstract'] AND ! isset($declaration['methods'][$method['name']]))
                            {
                                $this->add(self::ERROR, 'abstract',
                                    $name.' не реализует абстрактный метод '.$parent.'::'.$method['name'].'()',
                                    $declaration);
                            }
                        }
                    }

                    foreach ($parent_declaration['methods'] as $method_name => $parent_method)
                    {
                        if ( ! isset($declaration['methods'][$method_name]))
                        {
                            continue;
                        }

                        if (in_array($method_name, ['__construct', '__destruct'], TRUE))
                        {
                            continue;
                        }

                        $this->compare_signature($declaration, $declaration['methods'][$method_name],
                            $parent_declaration, $parent_method);
                    }
                }
            }
        }
    }

    /**
     * Сравнение сигнатуры метода ребёнка и родителя (правила совместимости PHP).
     */
    protected function compare_signature(array $child, array $child_method, array $parent, array $parent_method)
    {
        $child_required  = $this->required_count($child_method['parameters']);
        $parent_required = $this->required_count($parent_method['parameters']);
        $child_total     = count($child_method['parameters']);
        $parent_total    = count($parent_method['parameters']);

        $where = $child['relative'].':'.$child['line'];

        if ($child_required > $parent_required)
        {
            $this->add(self::ERROR, 'signature',
                $child['name'].'::'.$child_method['name'].'() требует '.$child_required.' обязательных аргументов, а родитель '.$parent['name'].' — '.$parent_required.' (PHP fatal)',
                $child);
        }

        if ($child_total < $parent_total)
        {
            $this->add(self::ERROR, 'signature',
                $child['name'].'::'.$child_method['name'].'() принимает '.$child_total.' аргументов вместо '.$parent_total.' у '.$parent['name'].' (PHP fatal)',
                $child);
        }

        foreach ($parent_method['parameters'] as $position => $parent_param)
        {
            if ( ! isset($child_method['parameters'][$position]))
            {
                continue;
            }

            $child_param = $child_method['parameters'][$position];

            if ($parent_param['type'] === '' OR $child_param['type'] === '')
            {
                continue;
            }

            if ($child_param['type'] === $parent_param['type'])
            {
                continue;
            }

            $this->add(self::WARN, 'signature',
                $child['name'].'::'.$child_method['name'].'() менят тип аргумента #'.($position + 1).' с '.$parent_param['type'].' на '.$child_param['type'].' (родитель '.$parent['name'].')',
                $child);
        }
    }

    protected function check_class_file_names()
    {
        foreach ($this->_classes as $name => $declarations)
        {
            foreach ($declarations as $declaration)
            {
                if (strpos($declaration['file'], DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR) === FALSE)
                {
                    continue;
                }

                $expected = str_replace('_', DIRECTORY_SEPARATOR, $name).'.php';

                if (substr($declaration['file'], -strlen($expected)) === $expected)
                {
                    continue;
                }

                // Интерфейсы и трейты в этом проекте живут по тому же правилу
                $this->add(self::WARN, 'class_file',
                    'класс '.$name.' лежит в '.$declaration['relative'].' (ожидался .../'.$expected.') — автозагрузчик его не найдёт',
                    $declaration);
            }
        }
    }

    protected function check_tasks()
    {
        foreach ($this->_classes as $name => $declarations)
        {
            if (strpos($name, 'Task_') !== 0)
            {
                continue;
            }

            foreach ($declarations as $declaration)
            {
                // Задачи самого фреймворка (modules/) править нельзя — паспорт
                // фреймворка это запрещает (FrameworkTest), поэтому и требовать
                // от них контракта нечего. Проверяем только код проекта.
                if (strpos($declaration['file'], MODPATH) === 0)
                {
                    continue;
                }

                if ( ! $this->is_subclass_of($name, 'Minion_Task'))
                {
                    $this->add(self::ERROR, 'task_contract',
                        $name.' не наследует Minion_Task — задача не будет найдена CLI',
                        $declaration);
                    continue;
                }

                if ( ! isset($declaration['methods']['_execute']))
                {
                    $this->add(self::ERROR, 'task_contract',
                        $name.' не реализует _execute()',
                        $declaration);
                }

                if ( ! $this->declares_property($declaration['file'], '_options'))
                {
                    $this->add(self::WARN, 'task_contract',
                        $name.' без $_options — параметры не будут видны в --help',
                        $declaration);
                }
            }
        }
    }

    /**
     * Minion находит задачу по пути файла (classes/Task/A/B.php → Task_A_B),
     * поэтому имя класса в файле обязано совпадать с этим путём.
     * Именно так ломаются «переехавшие» из других фреймворков файлы:
     * файл найден, но класс не существует → fatal при запуске.
     */
    protected function check_task_files()
    {
        foreach (array_keys($this->_paths) as $file)
        {
            if (strpos($file, DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'Task'.DIRECTORY_SEPARATOR) === FALSE)
            {
                continue;
            }

            $relative = $this->relative($file);

            $parts = explode(DIRECTORY_SEPARATOR.'Task'.DIRECTORY_SEPARATOR, $file);
            $expected = 'Task_'.str_replace(DIRECTORY_SEPARATOR, '_', substr($parts[1], 0, -4));

            $declared = FALSE;

            foreach ($this->_classes as $name => $declarations)
            {
                foreach ($declarations as $declaration)
                {
                    if ($declaration['file'] === $file AND $name === $expected)
                    {
                        $declared = TRUE;
                        break 2;
                    }
                }
            }

            if ($declared)
            {
                continue;
            }

            $this->add($this->severity_for(['relative' => $relative]), 'task_file',
                'в файле нет класса '.$expected.' — Minion найдёт задачу по имени файла и упадёт с fatal error',
                ['relative' => $relative, 'line' => 1]);
        }
    }

    protected function check_migrations()
    {
        foreach ($this->_classes as $name => $declarations)
        {
            if (strpos($name, 'pgMigration_') !== 0)
            {
                continue;
            }

            foreach ($declarations as $declaration)
            {
                if ( ! $this->is_subclass_of($name, 'pgMigration'))
                {
                    $this->add(self::ERROR, 'migration',
                        $name.' не наследует pgMigration', $declaration);
                }

                foreach (['up', 'down'] as $method)
                {
                    if ( ! isset($declaration['methods'][$method]))
                    {
                        $this->add(self::ERROR, 'migration',
                            $name.' без метода '.$method.'()', $declaration);
                    }
                }
            }
        }
    }

    protected function check_views()
    {
        foreach ($this->_paths as $file => $relative)
        {
            // Представления каркаса (modules/userguide и т.п.) не входят в каскад
            // выключенных dev-модулей — проверяем только код проекта.
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, 'View::factory') === FALSE)
            {
                continue;
            }

            // Комментарии и docblock-примеры не считаем реальными вызовами
            $code = preg_replace('#/\*.*?\*/#s', ' ', $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', ' ', $code);

            if ( ! preg_match_all("/View::factory\(\s*'([^']+)'\s*\)/", $code, $matches, PREG_SET_ORDER))
            {
                continue;
            }

            foreach ($matches as $match)
            {
                $view = $match[1];

                // Имена с подстановками (View::factory($name)) проверять нельзя
                if (strpos($view, ':') !== FALSE OR strpos($view, '$') !== FALSE)
                {
                    continue;
                }

                if ($this->view_exists($view, $relative))
                {
                    continue;
                }

                $this->add(self::ERROR, 'view',
                    "View::factory('".$view."') — файл представления не найден",
                    ['relative' => $relative, 'line' => $this->line_of($code, $match[0])]);
            }
        }
    }

    /**
     * `$this` в представлении — всегда ошибка.
     *
     * Konine включает файл представления статическим методом `View::capture()`,
     * поэтому объекта в области видимости нет: `$this->can(...)` в шаблоне
     * падает «Using $this when not in object context» и страница отдаёт 500 —
     * причём только тогда, когда до этой строки доходит очередь (то есть на
     * реальных данных, а не при пустой таблице). Права и прочие данные
     * передаются из контроллера через `->set()`.
     *
     * @return  void
     */
    /**
     * Компоненты интерфейса (`application/views/components`).
     *
     * Три вопроса, на которые проверка отвечает без запуска приложения:
     *
     *   1. вызов `Component::render('card')` / `Component::begin('card')` ведёт к
     *      существующему представлению `views/components/card.php`;
     *   2. сам общий компонент не потерял обещания: у таблицы есть подпись и
     *      `scope`, у показателя — подпись и единица измерения (это тот случай,
     *      когда дефект в одном файле ломает сразу все экраны);
     *   3. сколько представлений ещё верстают таблицу руками (`<table` без
     *      `<caption` — то, из-за чего таблица на телефоне обрезается).
     *
     * Третья находка — сводная: одна строка с числом и примерами вместо сотни
     * одинаковых сообщений.
     */
    protected function check_ui_components()
    {
        $views = [];
        $components = [];

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0 OR strpos($relative, '/views/') === FALSE)
            {
                continue;
            }

            $views[$file] = $relative;

            if (strpos($relative, '/views/components/') !== FALSE)
            {
                $components[$file] = $relative;
            }
        }

        if ( ! $views)
        {
            return;
        }

        // --- 1. Вызовы компонентов указывают на существующие представления ---

        $directory = APPPATH.'views/components';

        foreach ($views as $file => $relative)
        {
            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, 'Component::') === FALSE)
            {
                continue;
            }

            if ( ! preg_match_all("/Component::(?:render|begin)\(\s*'([^']+)'/", $source, $matches, PREG_SET_ORDER))
            {
                continue;
            }

            foreach ($matches as $match)
            {
                $path = $directory.'/'.$match[1].'.php';

                if ( ! is_file($path))
                {
                    $this->add(self::ERROR, 'ui_components',
                        'Компонент «'.$match[1].'» вызван, но представления '.$match[1].'.php нет в application/views/components',
                        ['relative' => $relative]);
                }
            }
        }

        // --- 2. Обещания общих компонентов ---

        $contracts = [
            'table.php' => [
                'pattern' => '#<caption#',
                'message' => 'components/table без <caption: таблица потеряла подпись, и скринридер не знает, что в ней',
            ],
            'table.php:scope' => [
                'pattern' => '#scope="col"#',
                'message' => 'components/table без scope="col": заголовки колонок не связаны с ячейками',
            ],
            'kpi.php' => [
                'pattern' => '#HTML::chars\(\$unit\)#',
                'message' => 'components/kpi не печатает единицу измерения: цифра снова читается как угодно',
            ],
            'card.php' => [
                'pattern' => '#HTML::chars\(\$title\)#',
                'message' => 'components/card не экранирует заголовок: данные становятся разметкой',
            ],
        ];

        foreach ($contracts as $key => $contract)
        {
            list($name) = explode(':', $key);
            $file = $directory.'/'.$name;

            if ( ! is_file($file))
            {
                $this->add(self::ERROR, 'ui_components',
                    'Общий компонент '.$name.' отсутствует — вызовы Component::render() упадут',
                    ['relative' => 'application/views/components/'.$name]);

                continue;
            }

            if ( ! preg_match($contract['pattern'], (string) file_get_contents($file)))
            {
                $this->add(self::ERROR, 'ui_components', $contract['message'],
                    ['relative' => 'application/views/components/'.$name]);
            }
        }

        // --- 3. Сколько ещё верстают таблицы руками ---

        $without_caption = [];
        $inline_handlers = 0;

        foreach ($views as $file => $relative)
        {
            if (strpos($relative, '/views/components/') !== FALSE)
            {
                continue;
            }

            $source = (string) @file_get_contents($file);

            if (preg_match('#<table#i', $source) AND ! preg_match('#<caption#i', $source))
            {
                $without_caption[] = $relative;
            }

            $inline_handlers += (int) preg_match_all('#\son(?:click|change|submit|input|load)=#i', $source);
        }

        if ($without_caption)
        {
            $examples = implode(', ', array_slice($without_caption, 0, 3));

            $this->add(self::WARN, 'ui_components',
                'Долг интерфейса: '.count($without_caption).' представлений верстают <table> без <caption (например: '.$examples
                    .'). Перевод на components/table снимает обрезку таблицы на узком экране',
                ['relative' => 'application/views']);
        }

        if ($inline_handlers > 0)
        {
            $this->add(self::WARN, 'ui_components',
                'Долг интерфейса: '.$inline_handlers.' inline-обработчиков (onclick/onchange) в представлениях — '
                    .'они не работают при строгой политике CSP и не видны скринридеру',
                ['relative' => 'application/views']);
        }

        // --- 4. Сообщения после действия ---
        //
        // Подшаблон `*/flash` ждёт `messages` — список ['type' => …, 'message' => …],
        // который отдают `_flash_messages()` базовых контроллеров модулей. Если
        // представление передаёт туда старое `flash` (state/text) или не передаёт
        // ничего, страница после действия молча теряет сообщение: отказ сохранения
        // выглядит как успех, и это замечают только в поле.

        $stale_flash = [];

        foreach ($views as $file => $relative)
        {
            if (strpos($relative, '/views/components/') !== FALSE)
            {
                continue;
            }

            $source = (string) @file_get_contents($file);

            $has_partial = (bool) preg_match('#View::factory\(\s*[\'"][^\'"]*flash[\'"]\s*\)#', $source);
            $passes_messages = (bool) preg_match('#set\(\s*[\'"]messages[\'"]\s*,#', $source);
            $uses_old_name = (bool) preg_match('#set\(\s*[\'"]flash[\'"]\s*,#', $source);

            if ($uses_old_name OR ($has_partial AND ! $passes_messages))
            {
                $stale_flash[] = $relative;
            }
        }

        if ($stale_flash)
        {
            $examples = implode(', ', array_slice($stale_flash, 0, 3));

            $this->add(self::ERROR, 'ui_components',
                'Подшаблон flash получает не `messages` ('.$examples.'): контроллеры отдают список '
                    .'[\'type\', \'message\'] через _flash_messages(), иначе сообщение после действия пропадёт молча',
                ['relative' => 'application/views']);
        }

        // --- 5. Поля строки фильтров не «скачут» по вертикали ---
        //
        // Подсказка под полем — лишняя строка в блоке поля. Если строка
        // выровнена по низу (`items-end`), поле с подсказкой становится выше
        // соседнего селекта, и его собственный контрол уезжает вверх: в строке
        // фильтров поля стоят на разной высоте. Лечится выравниванием по верху
        // (`items-start`) и отступом кнопки на высоту подписи (`mt-6`).
        // Проверяется форма целиком: подсказка и соседний контрол должны быть
        // в одной форме, иначе это разные блоки.

        $skewed_rows = [];

        foreach ($views as $file => $relative)
        {
            if (strpos($relative, '/views/components/') !== FALSE)
            {
                continue;
            }

            $source = (string) @file_get_contents($file);

            if (stripos($source, 'items-end') === FALSE)
            {
                continue;
            }

            if ( ! preg_match_all('#<form\b.*?</form>#is', $source, $forms))
            {
                continue;
            }

            foreach ($forms[0] as $form)
            {
                if (stripos($form, 'items-end') === FALSE)
                {
                    continue;
                }

                // Подсказка у поля в этой же форме.
                if ( ! preg_match("#Component::(?:render|begin)\(\s*'form_field'.*?'hint'#s", $form))
                {
                    continue;
                }

                // Соседний контрол: селект или кнопка отправки.
                if ( ! preg_match('#<select\b|<button[^>]+type="submit"#i', $form))
                {
                    continue;
                }

                $skewed_rows[] = $relative;
                break;
            }
        }

        if ($skewed_rows)
        {
            $examples = implode(', ', array_slice(array_unique($skewed_rows), 0, 3));

            $this->add(self::ERROR, 'ui_components',
                'Строка фильтров выровнена по низу (`items-end`) рядом с полем, у которого есть подсказка ('
                    .$examples.'): поле с подсказкой выше соседнего селекта и «скачет» по вертикали. '
                    .'Нужны `items-start` у строки и `mt-6` у кнопки',
                ['relative' => 'application/views']);
        }
    }

    protected function check_view_static_context()
    {
        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0 OR strpos($relative, '/views/') === FALSE)
            {
                continue;
            }

            $source = @file_get_contents($file);

            // Ищем именно переменную PHP: в разметке того же файла живут
            // jQuery-выражения вида `$(this)`, и они к PHP отношения не имеют.
            if ($source === FALSE OR strpos($source, '$this') === FALSE)
            {
                continue;
            }

            $line = NULL;

            foreach (token_get_all($source) as $token)
            {
                if (is_array($token) AND $token[0] === T_VARIABLE AND $token[1] === '$this')
                {
                    $line = (int) $token[2];
                    break;
                }
            }

            if ($line === NULL)
            {
                continue;
            }

            $this->add(self::ERROR, 'view',
                'Представление использует $this, а View::capture() работает в статическом контексте — передайте данные из контроллера (->set)',
                ['relative' => $relative, 'line' => $line]);
        }
    }

    /**
     * Ищет представление: сначала рядом с файлом (в своём модуле), затем в каскаде
     * Kohana. Представления отключённых модулей в каскад не попадают, поэтому
     * проверка «своих» файлов идёт по файловой системе.
     *
     * @param   string  $view
     * @param   string  $relative  путь файла, который ссылается на представление
     * @return  boolean
     */
    protected function view_exists($view, $relative)
    {
        $view = ltrim($view, '/');

        if (preg_match('#^application/modules/([^/]+)/#', $relative, $matches))
        {
            $module = $matches[1];

            foreach ([APPPATH.'modules/'.$module.'/views/', MODPATH.$module.'/views/'] as $dir)
            {
                if (is_file($dir.$view.'.php'))
                {
                    return TRUE;
                }
            }
        }

        return (bool) Kohana::find_file('views', $view);
    }

    /**
     * Внешние библиотеки: код ссылается на классы composer-пакетов, которых нет
     * в vendor/. Пока не выполнен `composer install`, такие модули упадут в рантайме
     * (экспорт, печать, почта, MQTT) — об этом лучше знать заранее.
     */
    /**
     * Маршруты и действия контроллеров.
     *
     * `Route::set(...)->defaults(array('controller' => …, 'action' => …))` — это
     * статическая связь адреса с методом `action_*`. Опечатка в имени действия
     * или переименованный контроллер обнаруживаются только при заходе на
     * страницу (404/500), а проверка видит их за миллисекунды — до запуска.
     *
     * @return  void
     */
    protected function check_routes()
    {
        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0 OR basename($file) !== 'init.php')
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            // Комментарии не считаем объявлениями маршрутов
            $code = preg_replace('#/\*.*?\*/#s', ' ', $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', ' ', $code);

            foreach (explode(';', $code) as $statement)
            {
                if (strpos($statement, 'Route::set(') === FALSE OR strpos($statement, '->defaults(') === FALSE)
                {
                    continue;
                }

                // Значения по умолчанию — только внутри ->defaults(...):
                // в третьем аргументе Route::set лежат регулярки URI, и там тоже
                // встречается ключ 'controller' («baselines|structure|type»)
                if ( ! preg_match('/->defaults\(\s*array\((.*)\)\s*\)\s*$/s', $statement, $defaults))
                {
                    continue;
                }

                $defaults = $defaults[1];

                if ( ! preg_match("/'controller'\s*=>\s*'([^']+)'/", $defaults, $controller))
                {
                    continue;
                }

                if ( ! preg_match("/'action'\s*=>\s*'([^']+)'/", $defaults, $action))
                {
                    continue;
                }

                // Динамические значения (подстановки, регулярки) статически не проверить
                if (preg_match('/[()|\\\\$<>]/', $controller[1].$action[1]))
                {
                    continue;
                }

                $directory = preg_match("/'directory'\s*=>\s*'([^']+)'/", $defaults, $found) ? $found[1] : '';
                $name      = preg_match("/Route::set\(\s*'([^']+)'/", $statement, $found) ? $found[1] : '?';

                $class  = 'Controller_'.($directory !== '' ? $directory.'_' : '').$controller[1];
                $method = 'action_'.strtolower($action[1]);

                $where = [
                    'relative' => $relative,
                    'line'     => $this->line_of($code, $statement),
                ];

                if ( ! $this->class_exists($class))
                {
                    $this->add(self::ERROR, 'route_action',
                        "маршрут ".$name." ведёт на ".$class.", которого нет", $where);

                    continue;
                }

                if ($this->is_abstract_class($class))
                {
                    $this->add(self::ERROR, 'route_action',
                        "маршрут ".$name." ведёт на абстрактный ".$class, $where);

                    continue;
                }

                if ( ! $this->method_in_hierarchy($class, $method))
                {
                    $this->add(self::ERROR, 'route_action',
                        "маршрут ".$name." ведёт на ".$class."::".$method."(), метода нет", $where);
                }
            }
        }
    }

    /**
     * Абстрактный ли класс: на такой нельзя вешать маршрут — страница упадёт.
     *
     * @param   string  $class
     * @return  boolean
     */
    protected function is_abstract_class($class)
    {
        if ( ! isset($this->_classes[$class]))
        {
            return FALSE;
        }

        // Проверяем только сам класс: абстрактный предок (базовый контроллер
        // модуля) — это норма, а вот абстрактный контроллер на маршруте — нет
        foreach ($this->_classes[$class] as $declaration)
        {
            if ($declaration['abstract'])
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Есть ли метод у класса или у его предков (по разобранным файлам).
     *
     * @param   string  $class
     * @param   string  $method
     * @return  boolean
     */
    protected function method_in_hierarchy($class, $method)
    {
        $seen = [];

        while ($class !== NULL AND ! isset($seen[$class]))
        {
            $seen[$class] = TRUE;

            if ( ! isset($this->_classes[$class]))
            {
                // Класс вне разобранных файлов (ядро фреймворка) — не судим
                return $this->class_exists($class) AND method_exists($class, $method);
            }

            $parent = NULL;

            foreach ($this->_classes[$class] as $declaration)
            {
                if (isset($declaration['methods'][$method]))
                {
                    return TRUE;
                }

                if ($declaration['extends'] !== NULL)
                {
                    $parent = $declaration['extends'];
                }
            }

            $class = $parent;
        }

        return FALSE;
    }

    /**
     * Редирект внутри try с общим catch.
     *
     * `HTTP::redirect()` бросает `HTTP_Exception_302`, а `HTTP_Exception` наследует
     * `Kohana_Exception`. Если redirect() стоит внутри try, а ниже есть
     * `catch (Kohana_Exception ...)` или `catch (Exception ...)`, успешное действие
     * заканчивается ошибкой: исключение-редирект перехватывается и подменяется
     * (например, 404 «не найдено») — при этом запись в БД уже создана.
     * Редирект должен стоять после try, а не внутри него.
     *
     * @return  void
     */
    protected function check_redirect_in_try()
    {
        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, 'redirect(') === FALSE OR strpos($source, 'try') === FALSE)
            {
                continue;
            }

            // Комментарии и примеры из docblock не считаем кодом, но пустые строки
            // на их месте сохраняем — иначе номера строк в отчёте «уезжают»
            $code = preg_replace_callback('#/\*.*?\*/#s', function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            }, $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

            if ( ! preg_match_all('/\btry\s*\{/', $code, $tries, PREG_OFFSET_CAPTURE))
            {
                continue;
            }

            foreach ($tries[0] as $try)
            {
                $body_end = $this->matching_brace_text($code, $try[1] + strlen($try[0]) - 1);

                if ($body_end === NULL)
                {
                    continue;
                }

                $body = substr($code, $try[1], $body_end - $try[1]);

                if (strpos($body, 'redirect(') === FALSE)
                {
                    continue;
                }

                // Цепочка catch сразу после try
                $position = $body_end + 1;
                $catches  = array();

                while (preg_match('/\G\s*catch\s*\(([^)]*)\)\s*\{/', $code, $match, 0, $position))
                {
                    $catches[] = trim($match[1]);
                    $catch_end = $this->matching_brace_text($code, $position + strlen($match[0]) - 1);

                    if ($catch_end === NULL)
                    {
                        break;
                    }

                    $position = $catch_end + 1;
                }

                $generic = FALSE;

                foreach ($catches as $catch)
                {
                    if (strpos($catch, 'HTTP_Exception') !== FALSE)
                    {
                        continue;
                    }

                    if (strpos($catch, 'Kohana_Exception') !== FALSE
                        OR trim($catch, '\\ ') === 'Exception')
                    {
                        $generic = TRUE;
                        break;
                    }
                }

                if ($generic)
                {
                    $this->add(self::ERROR, 'redirect_in_try',
                        'redirect() внутри try и общий catch — успешный редирект будет перехвачен '
                        .'(HTTP_Exception_302 наследует Kohana_Exception); вынесите redirect() за try',
                        ['relative' => $relative, 'line' => substr_count(substr($code, 0, $try[1]), "\n") + 1]);
                }
            }
        }
    }

    /**
     * Права: всё, что проверяется через can('…'), должно быть объявлено в config/acl.php.
     *
     * Необъявленное право означает, что доступ молча зависит от реализации ACL,
     * а опечатка в имени («tech_cards.view» вместо «erp228.view») не будет замечена
     * ни тестом, ни ревью. Модуль-дубликат правила `erp401_wms.*` покрывает
     * свои права автоматически.
     *
     * Находка одна на файл (со списком прав) — так её можно принять записью
     * baseline на каталог модуля и закрывать по мере приведения модуля в порядок.
     *
     * @return  void
     */
    protected function check_acl()
    {
        $declared = array();
        $acl_file = APPPATH.'config/acl.php';

        if (is_file($acl_file))
        {
            $source = (string) @file_get_contents($acl_file);

            if (preg_match_all("/'([a-zA-Z0-9_]+\.[a-zA-Z0-9_*]+)'\s*=>/", $source, $matches))
            {
                foreach ($matches[1] as $right)
                {
                    $declared[$right] = TRUE;
                }
            }
        }

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, 'can(') === FALSE)
            {
                continue;
            }

            // Комментарии заменяем пустыми строками, чтобы номера строк не «уезжали»
            $code = preg_replace_callback('#/\*.*?\*/#s', function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            }, $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

            if ( ! preg_match_all("/->can\(\s*'([^']+)'/", $code, $matches, PREG_OFFSET_CAPTURE))
            {
                continue;
            }

            $missing = array();

            foreach ($matches[1] as $match)
            {
                $right = $match[0];

                // Динамические права ('module_manager.'.$ability) проверить нельзя
                if (strpos($right, '$') !== FALSE OR substr($right, -1) === '.')
                {
                    continue;
                }

                if (isset($declared[$right]))
                {
                    continue;
                }

                // 'crm.activity.view' покрывается объявленным 'crm.*'
                $covered = FALSE;
                $parts   = explode('.', $right);

                for ($i = count($parts) - 1; $i >= 1; $i--)
                {
                    $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';

                    if (isset($declared[$wildcard]))
                    {
                        $covered = TRUE;
                        break;
                    }
                }

                if ( ! $covered)
                {
                    $missing[$right] = $right;
                }
            }

            if ( ! $missing)
            {
                continue;
            }

            $this->add(self::WARN, 'acl',
                'права используются, но не объявлены в config/acl.php: '.implode(', ', $missing),
                ['relative' => $relative, 'line' => substr_count(substr($code, 0, $matches[1][0][1]), "\n") + 1]);
        }
    }

    /**
     * Индекс закрывающей скобки для блока, начинающегося с `{` на позиции $start.
     *
     * @param   string   $code
     * @param   integer  $start
     * @return  integer|null
     */
    protected function matching_brace_text($code, $start)
    {
        if ( ! isset($code[$start]) OR $code[$start] !== '{')
        {
            return NULL;
        }

        $length = strlen($code);
        $depth  = 0;

        for ($i = $start; $i < $length; $i++)
        {
            if ($code[$i] === '{')
            {
                $depth++;
            }
            elseif ($code[$i] === '}')
            {
                $depth--;

                if ($depth === 0)
                {
                    return $i;
                }
            }
        }

        return NULL;
    }

    /**
     * Вызовы «текучих» методов с недостаточным числом аргументов.
     *
     * `->where(DB::expr('...'))` в Kohana компилируется и падает только в рантайме
     * (ArgumentCountError: where() ждёт три аргумента), а `->limit()` без
     * аргумента — ошибка того же класса. Проверка статическая, стоит доли
     * миллисекунды и ловит целое семейство ошибок, которые иначе видны только
     * при открытии страницы.
     *
     * Находка одна на метод в файле — чтобы список оставался читаемым.
     *
     * @return  void
     */
    protected function check_fluent_calls()
    {
        $expectations = array(
            'where'     => 3,
            'and_where' => 3,
            'or_where'  => 3,
            'having'    => 3,
            'on'        => 3,
            'param'     => 1,
            'limit'     => 1,
            'offset'    => 1,
            'order_by'  => 1,
            'group_by'  => 1,
            'values'    => 1,
            'set'       => 1,
        );

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, '->') === FALSE)
            {
                continue;
            }

            $code = preg_replace_callback('#/\*.*?\*/#s', function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            }, $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

            foreach ($expectations as $method => $minimum)
            {
                if ( ! preg_match_all('/->'.preg_quote($method, '/').'\s*\(/', $code, $matches, PREG_OFFSET_CAPTURE))
                {
                    continue;
                }

                foreach ($matches[0] as $match)
                {
                    $start = $match[1] + strlen($match[0]) - 1;
                    $count = $this->_argument_count($code, $start);

                    if ($count === NULL OR $count >= $minimum)
                    {
                        continue;
                    }

                    $this->add(self::WARN, 'fluent_call',
                        '->'.$method.'() вызван с '.$count.' арг., нужно минимум '.$minimum,
                        array(
                            'relative' => $relative,
                            'line'     => substr_count(substr($code, 0, $match[1]), "\n") + 1,
                        ));

                    // Одна находка на метод в файле: остальные вызовы того же
                    // метода почти наверняка такие же
                    continue 2;
                }
            }
        }
    }

    /**
     * Число аргументов вызова, начиная с открывающей скобки на позиции $start.
     *
     * Учитываются вложенные скобки, строки в кавычках и короткий синтаксис
     * массивов — иначе запятая внутри DB::expr('a, b') считалась бы разделителем.
     *
     * @param   string  $code
     * @param   int     $start
     * @return  int|null    NULL, если скобка не закрыта (не наш случай)
     */
    protected function _argument_count($code, $start)
    {
        $length = strlen($code);
        $depth  = 0;
        $commas = 0;
        $filled = FALSE;
        $quote  = NULL;

        for ($i = $start; $i < $length; $i++)
        {
            $char = $code[$i];

            if ($quote !== NULL)
            {
                if ($char === '\\')
                {
                    $i++;
                }
                elseif ($char === $quote)
                {
                    $quote = NULL;
                }

                continue;
            }

            if ($char === "'" OR $char === '"')
            {
                $quote  = $char;
                $filled = TRUE;

                continue;
            }

            if ($char === '(' OR $char === '[' OR $char === '{')
            {
                $depth++;
                continue;
            }

            if ($char === ')' OR $char === ']' OR $char === '}')
            {
                $depth--;

                if ($depth === 0)
                {
                    return $filled ? $commas + 1 : 0;
                }

                continue;
            }

            if ($depth === 1 AND $char === ',')
            {
                $commas++;
                $filled = TRUE;

                continue;
            }

            if ($depth >= 1 AND ! ctype_space($char))
            {
                $filled = TRUE;
            }
        }

        return NULL;
    }

    /**
     * Точки входа для агентов и внешних систем.
     *
     *  - каждый `mcp.php` должен объявлять инструменты с обработчиком,
     *    описанием, схемой аргументов и правом, объявленным в acl.php
     *    (либо признаком `self` — «доступно любому действующему ключу»);
     *  - модуль с `mcp.php`, не подключённый в bootstrap.php, агентам
     *    инструментов не отдаёт — это замечание, а не ошибка молчания;
     *  - у каждого подключённого модуля проекта должна быть точка входа:
     *    JSON API (контроллер Api в своём модуле) или инструменты MCP.
     */
    protected function check_mcp()
    {
        $acl     = $this->declared_acl();
        $enabled = $this->enabled_modules();

        foreach (glob(APPPATH.'modules/*/mcp.php') as $file)
        {
            $module   = basename(dirname($file));
            $relative = $this->relative($file);
            $source   = (string) @file_get_contents($file);

            if ( ! preg_match_all("/'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'\s*=>\s*array\(/", $source, $matches, PREG_OFFSET_CAPTURE))
            {
                $this->add(self::WARN, 'mcp', 'mcp.php не объявляет ни одного инструмента ('.$module.')', array(
                    'relative' => $relative,
                    'line'     => 1,
                ));
                continue;
            }

            foreach ($matches[1] as $match)
            {
                $name   = $match[0];
                $offset = $match[1];
                $block  = substr($source, $offset, 2500);
                $line   = substr_count(substr($source, 0, $offset), "\n") + 1;
                $decl   = array('relative' => $relative, 'line' => $line);

                if (strpos($block, "'handler'") === FALSE)
                {
                    $this->add(self::ERROR, 'mcp', 'инструмент '.$name.' объявлен без обработчика (handler)', $decl);
                }

                if (strpos($block, "'description'") === FALSE)
                {
                    $this->add(self::WARN, 'mcp', 'у инструмента '.$name.' нет описания — LLM выбирает инструмент по описанию', $decl);
                }

                if (strpos($block, "'schema'") === FALSE)
                {
                    $this->add(self::WARN, 'mcp', 'у инструмента '.$name.' нет схемы аргументов (schema)', $decl);
                }

                if (strpos($block, "'self'") !== FALSE)
                {
                    continue;
                }

                if ( ! preg_match("/'scope'\s*=>\s*'([^']+)'/", $block, $scope))
                {
                    $this->add(self::ERROR, 'mcp', 'у инструмента '.$name.' не указано право (scope) и нет признака self', $decl);
                    continue;
                }

                if ( ! $this->acl_covers($acl, $scope[1]))
                {
                    $this->add(self::WARN, 'mcp', 'право '.$scope[1].' (инструмент '.$name.') не объявлено в acl.php', $decl);
                }
            }

            if ( ! isset($enabled[$module]))
            {
                $this->add(self::WARN, 'mcp', 'модуль '.$module.' с mcp.php не подключён в bootstrap.php — инструменты не видны агентам', array(
                    'relative' => $relative,
                    'line'     => 1,
                ));
            }
        }

        foreach ($enabled as $module => $path)
        {
            if (strpos($module, 'erp') !== 0)
            {
                continue;
            }

            if (glob($path.'/classes/Controller/*/Api.php') OR is_file($path.'/mcp.php'))
            {
                continue;
            }

            $this->add(self::WARN, 'mcp', 'у подключённого модуля '.$module.' нет ни JSON API, ни инструментов MCP', array(
                'relative' => 'application/bootstrap.php',
                'line'     => 0,
            ));
        }
    }

    /**
     * Права, объявленные в acl.php.
     *
     * @return  array
     */
    protected function declared_acl()
    {
        $declared = array();
        $acl_file = APPPATH.'config/acl.php';

        if ( ! is_file($acl_file))
        {
            return $declared;
        }

        $source = (string) @file_get_contents($acl_file);

        if (preg_match_all("/'([a-zA-Z0-9_]+\.[a-zA-Z0-9_*]+)'\s*=>/", $source, $matches))
        {
            foreach ($matches[1] as $right)
            {
                $declared[$right] = TRUE;
            }
        }

        return $declared;
    }

    /**
     * Право покрыто объявленными: точно, префиксом (`erp402_production.*`) или `*`.
     *
     * @param   array   $declared
     * @param   string  $right
     * @return  bool
     */
    protected function acl_covers(array $declared, $right)
    {
        if (isset($declared[$right]) OR isset($declared['*']))
        {
            return TRUE;
        }

        $parts = explode('.', $right);

        while (count($parts) > 1)
        {
            array_pop($parts);

            if (isset($declared[implode('.', $parts).'.*']))
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Подключённые модули: имя => путь (из application/bootstrap.php).
     *
     * @return  array
     */
    protected function enabled_modules()
    {
        $modules = array();
        $file    = APPPATH.'bootstrap.php';

        if ( ! is_file($file))
        {
            return $modules;
        }

        foreach (explode("\n", (string) @file_get_contents($file)) as $line)
        {
            if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>\s*APPPATH\.'modules\/([a-zA-Z0-9_]+)'/", $line, $match))
            {
                $modules[$match[2]] = APPPATH.'modules/'.$match[2];
            }
        }

        return $modules;
    }

    /**
     * Внешние библиотеки: код ссылается на пакет, которого нет в vendor/.
     *
     * Проверка отвечает на два разных вопроса, и путать их нельзя:
     *
     *  - «пакет объявлен, но не установлен» — состояние окружения, лечится
     *    `composer install`; сообщаем одной строкой, иначе отчёт тонет в
     *    перечислении каждой находки;
     *  - «код ссылается на пакет, которого нет в composer.json» — дефект
     *    манифеста: сколько ни запускай установку, пакет не появится. Для
     *    необязательных библиотек (S3-драйвер erp212_file_storage) правильное
     *    место — секция suggest: тогда это осознанный выбор, а не забытая
     *    зависимость.
     *
     * Файл самого аудитора из разбора исключается: его таблица пространств
     * имён — не использование пакетов, и раньше она находила «зависимости»
     * сама в себе.
     */
    protected function check_dependencies()
    {
        // Префикс пространства имён (как он встречается в коде) => пакет composer
        $packages = [
            'GuzzleHttp\\'               => 'guzzlehttp/guzzle',
            'PhpOffice\\PhpSpreadsheet'  => 'phpoffice/phpspreadsheet',
            'PhpOffice\\PhpWord'         => 'phpoffice/phpword',
            'Dompdf\\'                   => 'dompdf/dompdf',
            'Mpdf\\'                     => 'mpdf/mpdf',
            'Monolog\\'                  => 'monolog/monolog',
            'Carbon\\'                   => 'nesbot/carbon',
            'MqttClient\\'               => 'php-mqtt/client',
            'PhpAmqpLib\\'               => 'php-amqplib/php-amqplib',
            'Dotenv\\'                   => 'vlucas/phpdotenv',
            'Parsedown'                  => 'erusev/parsedown',
            'Aws\\'                      => 'aws/aws-sdk-php',
            'Predis\\'                   => 'predis/predis',
            'Symfony\\Component\\Mailer' => 'symfony/mailer',
        ];

        $root       = dirname(APPPATH);
        $vendor     = $root.'/vendor';
        $self       = realpath(__FILE__);
        $used       = [];
        $missing    = [];
        $optional   = [];
        $undeclared = [];

        $declared = $this->declared_packages($root);

        foreach ($this->_paths as $file => $relative)
        {
            // Свой файл: в таблице выше перечислены те же пространства имён
            if (realpath($file) === $self)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            foreach ($packages as $namespace => $package)
            {
                if ( ! $this->mentions_namespace($source, $namespace))
                {
                    continue;
                }

                $used[$package] = TRUE;

                // Необязательная библиотека объявлена в suggest — так и задумано
                if (isset($declared['suggest'][$package]))
                {
                    $optional[$package] = TRUE;

                    continue;
                }

                if ( ! isset($declared['require'][$package]) AND ! isset($declared['require-dev'][$package]))
                {
                    $undeclared[$package] = $relative;

                    continue;
                }

                if (is_dir($vendor.'/'.$package))
                {
                    continue;
                }

                $missing[$package] = TRUE;
            }
        }

        // Один пакет — одно сообщение: важно, что установка не сделана, а не
        // сколько модулей успели сослаться на библиотеку.
        if ($missing AND ! is_dir($vendor))
        {
            $this->add(self::WARN, 'dependency',
                'vendor/ не установлен: в коде используются пакеты composer ('.implode(', ', array_keys($missing))
                    .') — до `composer install` эти возможности недоступны',
                ['relative' => 'composer.json', 'line' => 0]);
        }
        elseif ($missing)
        {
            foreach (array_keys($missing) as $package)
            {
                $this->add(self::WARN, 'dependency',
                    'используется '.$package.', но пакет не установлен в vendor/ — код упадёт в рантайме; выполните composer install',
                    ['relative' => 'composer.json', 'line' => 0]);
            }
        }

        foreach ($undeclared as $package => $relative)
        {
            $this->add(self::WARN, 'dependency',
                'используется '.$package.', но пакет не объявлен в composer.json (ни в require, ни в suggest) — '
                    .'composer install его не поставит',
                ['relative' => $relative, 'line' => 0]);
        }

        $this->_stats['dependencies_used']     = array_keys($used);
        $this->_stats['dependencies_optional'] = array_keys($optional);
        $this->_stats['dependencies_missing']  = array_keys($missing);
    }

    /**
     * Пакеты, объявленные в composer.json.
     *
     * @param   string  $root  каталог проекта
     * @return  array   ['require' => [пакет => TRUE], 'require-dev' => [...], 'suggest' => [...]]
     */
    protected function declared_packages($root)
    {
        $declared = ['require' => [], 'require-dev' => [], 'suggest' => []];

        $manifest = @file_get_contents($root.'/composer.json');

        if ($manifest === FALSE)
        {
            return $declared;
        }

        $json = json_decode($manifest, TRUE);

        if ( ! is_array($json))
        {
            return $declared;
        }

        foreach (array_keys($declared) as $section)
        {
            if (empty($json[$section]) OR ! is_array($json[$section]))
            {
                continue;
            }

            foreach (array_keys($json[$section]) as $package)
            {
                $declared[$section][$package] = TRUE;
            }
        }

        return $declared;
    }

    /**
     * Упоминается ли библиотека как использование, а не как строка в таблице.
     *
     * Годится `use GuzzleHttp\Client`, `new \Aws\S3\S3Client(...)` и вызов
     * статического метода (`Carbon\Carbon::now()`); простого вхождения
     * подстроки мало — иначе карта соответствий в этом файле считалась бы
     * зависимостью.
     *
     * @param   string  $source     содержимое файла
     * @param   string  $namespace  префикс пространства имён
     * @return  bool
     */
    protected function mentions_namespace($source, $namespace)
    {
        $quoted = preg_quote($namespace, '/');

        $patterns = [
            '/(?:new|use|extends|implements)\s+\\\\?'.$quoted.'/',
            '/\\\\?'.$quoted.'[A-Za-z0-9_\\\\]*::/',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $source))
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    // -- Утилиты --------------------------------------------------------------

    /**
     * Ошибки в коде проекта критичны, проблемы модулей фреймворка — предупреждения.
     *
     * @param   array   $declaration
     * @return  string
     */
    protected function severity_for(array $declaration)
    {
        return strpos($declaration['relative'], 'application/') === 0 ? self::ERROR : self::WARN;
    }

    /**
     * Известные (принятые) отклонения: файл отдаёт список записей
     *   ['check' => 'missing_parent', 'file' => 'application/modules/…', 'reason' => '…'].
     * Записи из этого списка не считаются новыми проблемами (и не ломают CI),
     * но остаются в отчёте как «известные».
     *
     * @return array
     */
    public function baseline()
    {
        $file = APPPATH.'audit-baseline.php';

        if ( ! is_file($file))
        {
            return [];
        }

        $entries = include $file;

        return is_array($entries) ? $entries : [];
    }

    /**
     * @param   array   $finding
     * @param   array   $entries
     * @param   string  $reason  (выход) причина из baseline
     * @return  boolean
     */
    protected function in_baseline(array $finding, array $entries, & $reason)
    {
        foreach ($entries as $index => $entry)
        {
            if (strpos($finding['check'], (string) $entry['check']) !== 0)
            {
                continue;
            }

            if ( ! empty($entry['file']) AND strpos($finding['file'], $entry['file']) === FALSE)
            {
                continue;
            }

            $this->_baseline_used[$index] = TRUE;
            $reason = isset($entry['reason']) ? $entry['reason'] : '';

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Публикация событий: одно событие — одна запись.
     *
     * Шина erp208 сама пишет событие в журнал (erp_208_events), проксирует его
     * в центр уведомлений и вызывает слушателей с плоским payload. Поэтому в
     * модуле достаточно одного вызова `Event::fire()`. Прямой вызов
     * `Service_Erp208_EventBus::instance()->publish()` рядом с ним — дефект:
     * событие попадает в журнал дважды, уведомления дублируются, слушатели
     * срабатывают дважды. Так в базе появились тысячи парных записей.
     */
    protected function check_events()
    {
        $needle = 'Service_Erp208_EventBus::instance()->publish(';

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0
                OR strpos($relative, 'erp208_events') !== FALSE
                OR $relative === 'application/classes/Dev/Auditor.php')
            {
                // сама шина публикует события по своему устройству,
                // а этот файл содержит сами шаблоны поиска
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            // Комментарии не код: в них встречаются примеры того, как делать не надо
            $code = preg_replace_callback('#/\*.*?\*/#s', function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            }, $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

            if (strpos($code, $needle) === FALSE OR strpos($code, 'Event::fire(') === FALSE)
            {
                continue;
            }

            $this->add(self::WARN, 'events', 'событие публикуется дважды: напрямую в шину и через Event::fire', array(
                'relative' => $relative,
                'line'     => $this->line_of($source, $needle),
            ));
        }
    }

    /**
     * API отвечает об ошибке — и обязан остановиться.
     *
     * Konine не прерывает запрос: после before() вызывается действие, а внутри
     * действия следующий оператор выполняется всегда. Поэтому `_json_error()`
     * (или другой ответ об ошибке) без `return;`/`throw` — дыра: проверка
     * «нет прав» отвечает 403, а потом тот же запрос выполняет операцию и
     * переписывает ответ на 200. Именно так анонимный POST в цех выполнял
     * действие у станка. Здесь проверяются все JSON-контроллеры проекта.
     */
    protected function check_json_guards()
    {
        $calls = array(
            '$this->_json_error(',
            '$this->_json_fail(',
            '$this->_json_denied(',
        );

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0 OR basename($file) !== 'Api.php')
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            $lines = explode("\n", $source);

            foreach ($lines as $index => $line)
            {
                $statement = trim($line);

                if ($statement === '' OR strpos($statement, 'function ') === 0)
                {
                    continue;
                }

                $is_call = FALSE;

                foreach ($calls as $call)
                {
                    if (strpos($statement, $call) !== FALSE)
                    {
                        $is_call = TRUE;
                    }
                }

                if ( ! $is_call)
                {
                    continue;
                }

                // Остановка на той же строке: return $this->_json_error(...)
                // или if ($this->_json_denied()) { return; }
                if (preg_match('/^(return|throw)\b/', $statement)
                    OR preg_match('/\{\s*(return|throw)\b/', $statement))
                {
                    continue;
                }

                $next = '';

                for ($probe = $index + 1; $probe < count($lines); $probe++)
                {
                    if (trim($lines[$probe]) === '')
                    {
                        continue;
                    }

                    $next = trim($lines[$probe]);
                    break;
                }

                if (strpos($next, 'return') === 0 OR strpos($next, 'throw') === 0)
                {
                    continue;
                }

                // Последний оператор метода или ветки: `…error(404); }` — после
                // закрывающей скобки идёт уже следующий метод класса
                if ($next === '}' AND $this->line_is_class_level($lines, $index))
                {
                    continue;
                }

                // Номер строки в сообщении: иначе второй такой же промах в том же
                // файле схлопнулся бы с первым и остался незамеченным
                $this->add(self::ERROR, 'json_guards',
                    'ответ об ошибке в API без остановки (строка '.($index + 1).'): выполнение дойдёт до данных', array(
                    'relative' => $relative,
                    'line'     => $index + 1,
                ));
            }
        }
    }

    /**
     * Контракт методов JSON API: список действий чтения против кода.
     *
     * Список `application/config/api.php` решает две вещи: по нему ходит
     * дымовой прогон (`Service_Dev_Smoke::api_urls()`) и по нему
     * `Controller_Base` пропускает GET (всё остальное считается изменяющим и
     * требует POST). Ошибка в списке стоит дорого с двух сторон, поэтому
     * проверяем его машинно:
     *
     *   * запись, которой нет ни в одном `Controller_*_Api` — устарела
     *     (действие переименовали или удалили);
     *   * действие из списка читает POST-данные (`request->post()`/`$_POST`) —
     *     значит, оно изменяющее и GET на него недопустим;
     *   * изменяющее действие API не проверяет метод и читает POST-данные
     *     (совет: отдавать 405 вместо 500 на GET — так делает центральный
     *     `Controller_Base`, а свои точки входа этого лишены).
     *
     * @return void
     */
    /**
     * Поиск «по id или uuid» обязан проверять формат до запроса.
     *
     * Тринадцать сервисов восьми модулей делали это по-своему, и «умный» поиск
     * отправлял любую нечисловую строку в сравнение с uuid-колонкой: PostgreSQL
     * отвечал `invalid input syntax for type uuid`, то есть адрес
     * `/orders/view/abc` падал 500 вместо 404 (B17). Проверка ищет возврат к
     * самодельному разбору: файл, который ищет документ по `$id_or_uuid` или
     * сравнивает `where('uuid', …)` напрямую, обязан звать `Erp::id_or_uuid()`
     * (или хотя бы `Erp::is_uuid()`).
     *
     * @return  void
     */
    /**
     * `HTTP_Exception_405` без списка разрешённых методов.
     *
     * Класс требует `->allowed([...])`: без него `check()` бросает
     * «A list of allowed methods must be specified», и вместо честного 405
     * клиент получает 500. Дефект невидим, пока в действие приходят правильным
     * методом, поэтому живёт в коде годами — а выстреливает на первом же
     * GET-запросе от робота, предзагрузки ссылки или дымового прогона.
     *
     * @return  void
     */
    protected function check_http_405()
    {
        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE OR strpos($source, 'HTTP_Exception_405') === FALSE)
            {
                continue;
            }

            $code = preg_replace_callback('#/\*.*?\*/#s', function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            }, $source);
            $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

            if ( ! preg_match_all('/new\s+HTTP_Exception_405\s*\(/', $code, $matches, PREG_OFFSET_CAPTURE))
            {
                continue;
            }

            foreach ($matches[0] as $match)
            {
                $tail = substr($code, $match[1], 240);

                if (strpos($tail, '->allowed(') !== FALSE)
                {
                    continue;
                }

                $this->add(self::ERROR, 'http_405',
                    'HTTP_Exception_405 без ->allowed(): ответ не соберётся, клиент получит 500 вместо 405',
                    array('relative' => $relative, 'line' => substr_count(substr($code, 0, $match[1]), "\n") + 1));
            }
        }
    }

    protected function check_uuid_lookup()
    {
        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0)
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            if (strpos($source, 'Erp::id_or_uuid(') !== FALSE OR strpos($source, 'Erp::is_uuid(') !== FALSE)
            {
                continue;
            }

            $line = 0;
            $message = NULL;

            if (preg_match('/\$id_or_uuid\b/', $source, $match, PREG_OFFSET_CAPTURE))
            {
                $line    = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
                $message = 'поиск по id или uuid без проверки формата: используйте Erp::id_or_uuid() — иначе мусор из адреса дойдёт до СУБД';
            }
            elseif (strpos($source, "where('uuid'") !== FALSE)
            {
                $line    = substr_count(substr($source, 0, strpos($source, "where('uuid'")), "\n") + 1;
                $message = 'запрос по uuid без проверки формата: используйте Erp::id_or_uuid() — иначе СУБД ответит ошибкой приведения';
            }

            if ($message !== NULL)
            {
                $this->add('warn', 'uuid_lookup', $message, array('relative' => $relative, 'line' => $line));
            }
        }
    }

    protected function check_api_read_only()
    {
        $config_file = APPPATH.'config/api.php';

        if ( ! is_file($config_file))
        {
            return;
        }

        $read_only = include $config_file;
        $read_only = isset($read_only['read_only_actions']) ? (array) $read_only['read_only_actions'] : array();

        if ( ! $read_only)
        {
            $this->add(self::ERROR, 'api_read_only',
                'application/config/api.php не объявляет read_only_actions: контракт методов API пуст');
        }

        $declared = array();

        foreach ($this->_paths as $file => $relative)
        {
            if (strpos($relative, 'application/') !== 0 OR basename($file) !== 'Api.php')
            {
                continue;
            }

            $source = @file_get_contents($file);

            if ($source === FALSE)
            {
                continue;
            }

            // Разбираем тела методов action_*: имя + фрагмент кода до следующего
            // «public function» (грубée токенизации, но для этого класса проверок
            // достаточно и не требует запуска приложения).
            if ( ! preg_match_all(
                '/function\s+action_(\w+)\s*\([^)]*\)\s*\{(.*?)(?=\n\s*(?:public|protected|private)\s+function|\n\})/s',
                $source, $matches, PREG_SET_ORDER))
            {
                continue;
            }

            $class = NULL;

            if (preg_match('/class\s+(\w+)/', $source, $class_found))
            {
                $class = $class_found[1];
            }

            foreach ($matches as $match)
            {
                $declared[$match[1]] = array(
                    'file'  => $relative,
                    'class' => $class,
                    'body'  => $match[2],
                );
            }
        }

        foreach ($read_only as $action)
        {
            $action = (string) $action;

            if ( ! isset($declared[$action]))
            {
                $this->add(self::WARN, 'api_read_only',
                    'действия чтения «'.$action.'» нет ни в одном Controller_*_Api — запись устарела', array(
                    'relative' => 'application/config/api.php',
                ));

                continue;
            }

            $body = $declared[$action]['body'];

            if (strpos($body, 'request->post(') !== FALSE OR strpos($body, '$_POST') !== FALSE)
            {
                $this->add(self::ERROR, 'api_read_only',
                    'действие «'.$action.'» объявлено чтением, но читает POST-данные — значит, оно изменяющее', array(
                    'relative' => $declared[$action]['file'],
                ));
            }
        }

        // Изменяющие действия, принимающие POST, обязаны проверять метод сами,
        // если их контроллер не наследует Controller_Base (там это централизовано)
        foreach ($declared as $action => $info)
        {
            if (in_array($action, $read_only, TRUE))
            {
                continue;
            }

            $body = $info['body'];

            $uses_post = (strpos($body, 'request->post(') !== FALSE OR strpos($body, '$_POST') !== FALSE);

            if ( ! $uses_post)
            {
                continue;
            }

            $guarded = (strpos($body, 'Request::POST') !== FALSE
                OR strpos($body, '_require_post') !== FALSE
                OR strpos($body, '_api_method_error') !== FALSE);

            if ($guarded)
            {
                continue;
            }

            if ( ! $this->extends_controller_base($info['class']))
            {
                $this->add(self::WARN, 'api_read_only',
                    'действие «'.$action.'» принимает POST-данные, но не проверяет метод и не наследует Controller_Base: GET приведёт к 500 вместо 405', array(
                    'relative' => $info['file'],
                ));
            }
        }
    }

    /**
     * Наследует ли класс (по цепочке) `Controller_Base`: там контракт методов и
     * ответ 405 на изменяющее действие по GET уже централизованы.
     *
     * Цепочка идёт по разобранным объявлениям, без загрузки классов: большинство
     * API-контроллеров наследуют свой `Controller_ErpNNN_Main`, который, в свою
     * очередь, наследует `Controller_Base`.
     *
     * @param   string|null $class
     * @return  bool
     */
    protected function extends_controller_base($class)
    {
        for ($depth = 0; $depth < 20; $depth++)
        {
            if ($class === NULL OR $class === '')
            {
                return FALSE;
            }

            if ($class === 'Controller_Base')
            {
                return TRUE;
            }

            if ( ! isset($this->_classes[$class][0]['extends']))
            {
                return FALSE;
            }

            $class = $this->_classes[$class][0]['extends'];
        }

        return FALSE;
    }

    /**
     * Идёт ли после закрывающей скобки (строка $index + 1) уже следующий метод класса.
     *
     * @param   array   $lines
     * @param   int     $index
     * @return  bool
     */
    protected function line_is_class_level(array $lines, $index)
    {
        $significant = 0;

        for ($probe = $index + 1; $probe < count($lines); $probe++)
        {
            $line = trim($lines[$probe]);

            if ($line === '')
            {
                continue;
            }

            $significant++;

            if ($significant === 1)
            {
                if ($line !== '}')
                {
                    return FALSE;
                }

                continue;
            }

            // Второй значимый ряд — уровень класса: закрывающая скобка класса,
            // комментарий или объявление следующего метода
            return (bool) preg_match('#^(\}|/\*\*|\*/|//|public |protected |private |abstract |final )#', $line);
        }

        return TRUE;
    }

    protected function add($severity, $check, $message, array $declaration)
    {
        // Ключ включает файл: одно и то же сообщение в разных файлах — разные
        // находки, иначе повтор дефекта в другом модуле остаётся невидимым
        $key = $severity.'|'.$check.'|'.$message.'|'.(isset($declaration['relative']) ? $declaration['relative'] : '');

        if (isset($this->_findings[$key]))
        {
            return;
        }

        $this->_findings[$key] = [
            'severity' => $severity,
            'check'    => $check,
            'message'  => $message,
            'file'     => isset($declaration['relative']) ? $declaration['relative'] : '',
            'line'     => isset($declaration['line']) ? $declaration['line'] : 0,
        ];
    }

    protected function required_count(array $parameters)
    {
        $required = 0;

        foreach ($parameters as $parameter)
        {
            if ( ! $parameter['default'] AND ! $parameter['variadic'])
            {
                $required++;
            }
        }

        return $required;
    }

    protected function prev_significant(array $tokens, $index)
    {
        for ($i = $index - 1; $i >= 0; $i--)
        {
            if (is_array($tokens[$i]) AND in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE))
            {
                continue;
            }

            return $tokens[$i];
        }

        return NULL;
    }

    protected function matching_brace(array $tokens, $index)
    {
        $depth = 0;

        for ($i = $index; $i < count($tokens); $i++)
        {
            $text = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];

            if ($text === '{')
            {
                $depth++;
            }
            elseif ($text === '}')
            {
                $depth--;

                if ($depth === 0)
                {
                    return $i;
                }
            }
        }

        return count($tokens);
    }

    protected function is_abstract(array $tokens, $index)
    {
        for ($i = $index - 1; $i >= max(0, $index - 4); $i--)
        {
            if (is_array($tokens[$i]) AND $tokens[$i][0] === T_ABSTRACT)
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    protected function read_name(array $tokens, $start, & $index)
    {
        $name = '';

        for ($i = $start; $i < count($tokens); $i++)
        {
            $token = $tokens[$i];

            // Пробелы и комментарии внутри имени класса пропускаем
            if (is_array($token) AND in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE))
            {
                continue;
            }

            if (is_array($token) AND in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], TRUE))
            {
                $name .= $token[1];
                continue;
            }

            break;
        }

        $index = $i - 1;

        return trim($name, '\\');
    }

    protected function split_params($buffer)
    {
        $parts  = [];
        $depth  = 0;
        $current = '';

        for ($i = 0; $i < strlen($buffer); $i++)
        {
            $char = $buffer[$i];

            if (in_array($char, ['(', '[', '{'], TRUE))
            {
                $depth++;
            }
            elseif (in_array($char, [')', ']', '}'], TRUE))
            {
                $depth--;
            }

            if ($char === ',' AND $depth === 0)
            {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '')
        {
            $parts[] = $current;
        }

        return $parts;
    }

    protected function has_body(array $tokens, $index, $body_end)
    {
        for ($i = $index; $i < $body_end; $i++)
        {
            if ($tokens[$i] === '{')
            {
                return TRUE;
            }

            if ($tokens[$i] === ';')
            {
                return FALSE;
            }
        }

        return FALSE;
    }

    protected function declares_property($file, $property)
    {
        $source = @file_get_contents($file);

        return ($source !== FALSE AND strpos($source, '$'.$property) !== FALSE);
    }

    /**
     * Слой каскада: application, system, ядро модуля, конкретный модуль.
     *
     * @param   string  $file
     * @param   string  $relative
     * @return  string
     */
    protected function layer($file, $relative)
    {
        if (strpos($relative, 'application/modules/') === 0)
        {
            $parts = explode('/', $relative);

            return 'module:'.(isset($parts[2]) ? $parts[2] : 'unknown');
        }

        if (strpos($relative, 'modules/') === 0)
        {
            $parts = explode('/', $relative);

            return 'module:'.(isset($parts[1]) ? $parts[1] : 'unknown');
        }

        if (strpos($relative, 'application/') === 0)
        {
            return 'application';
        }

        if (strpos($relative, 'system/') === 0)
        {
            return 'system';
        }

        return 'other';
    }

    protected function class_exists($name)
    {
        $name = ltrim($name, '\\');

        if (isset($this->_classes[$name]))
        {
            return TRUE;
        }

        if (class_exists($name, FALSE) OR interface_exists($name, FALSE) OR trait_exists($name, FALSE))
        {
            return TRUE;
        }

        // Встроенные и распространённые SPL-классы
        static $builtin = [
            'Exception', 'Error', 'Throwable', 'ArrayObject', 'ArrayIterator', 'RecursiveIteratorIterator',
            'DirectoryIterator', 'FilesystemIterator', 'RecursiveDirectoryIterator', 'Closure', 'Generator',
            'LogicException', 'RuntimeException', 'InvalidArgumentException', 'OutOfBoundsException',
            'OutOfRangeException', 'UnexpectedValueException', 'DomainException', 'RangeException',
            'BadMethodCallException', 'BadFunctionCallException', 'JsonSerializable', 'IteratorAggregate',
            'Countable', 'ArrayAccess', 'Iterator', 'Traversable', 'Serializable', 'Stringable',
            'PDO', 'PDOStatement', 'PDOException', 'ReflectionClass', 'ReflectionMethod', 'SplFileInfo',
            'SplObjectStorage', 'SplStack', 'SplQueue', 'SplFixedArray', 'SplPriorityQueue', 'SplDoublyLinkedList',
        ];

        if (in_array($name, $builtin, TRUE))
        {
            return TRUE;
        }

        // Классы, которые PHP подгружает из расширений (проверяем без автозагрузки)
        return class_exists($name, FALSE) OR interface_exists($name, FALSE) OR extension_loaded('spl') AND class_exists($name, FALSE);
    }

    protected function is_subclass_of($class, $parent)
    {
        $seen = [$class => TRUE];
        $queue = [$class];

        while ($queue)
        {
            $current = array_shift($queue);

            if ( ! isset($this->_classes[$current]))
            {
                return $current === $parent;
            }

            foreach ($this->_classes[$current] as $declaration)
            {
                $bases = $declaration['implements'];

                if ($declaration['extends'] !== NULL)
                {
                    $bases[] = $declaration['extends'];
                }

                foreach ($bases as $base)
                {
                    if ($base === $parent)
                    {
                        return TRUE;
                    }

                    if ( ! isset($seen[$base]))
                    {
                        $seen[$base] = TRUE;
                        $queue[] = $base;
                    }
                }
            }
        }

        return FALSE;
    }

    protected function relative($file)
    {
        $root = realpath(DOCROOT.'..').DIRECTORY_SEPARATOR;

        return str_replace($root, '', $file);
    }

    protected function line_of($source, $needle)
    {
        $position = strpos($source, $needle);

        return $position === FALSE ? 0 : substr_count(substr($source, 0, $position), "\n") + 1;
    }
}
