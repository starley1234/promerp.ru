<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Генератор документации из кода: таблицы модулей в README и ARCHITECTURE,
 * карта модулей docs/MODULE_MAP.md.
 *
 * Зачем: документация «про модули» — единственная её часть, которая стареет
 * молча. Пока она пишется руками, агент и человек читают неправду (в таблице
 * README жили модули, которых нет, и отсутствовали те, что есть). Здесь факты
 * берутся из диска и каскада модулей, а `module:docs --check` (и тест
 * DocsSyncTest) не дают им разойтись.
 *
 *   bin/console module:docs            # обновить сгенерированные блоки
 *   bin/console module:docs --check    # только проверить (код 1, если устарело)
 *
 * @package Erp218
 */
class Service_Erp218_Docs {

    /** Файлы с блоками: путь от корня => имя блока. */
    const BLOCKS = [
        'README.md'             => 'erp-modules',
        'docs/ARCHITECTURE.md'  => 'module-inventory',
    ];

    /** Файл, который генерируется целиком. */
    const MAP_FILE = 'docs/MODULE_MAP.md';

    /** @var Service_Erp218_Docs */
    protected static $_instance;

    /**
     * @return Service_Erp218_Docs
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
     * @return string
     */
    protected function root()
    {
        return dirname(APPPATH);
    }

    /**
     * Обновляет сгенерированные файлы.
     *
     * @param   array   $targets  список целей или NULL (все)
     * @return  array   ['updated' => [...], 'skipped' => [...], 'problems' => [...]]
     */
    public function sync(?array $targets = NULL)
    {
        $result = ['updated' => [], 'skipped' => [], 'problems' => []];

        foreach (self::BLOCKS as $file => $block)
        {
            if ($targets !== NULL AND ! in_array(pathinfo($file, PATHINFO_FILENAME), $targets, TRUE))
            {
                $result['skipped'][] = $file;
                continue;
            }

            $path = $this->root().'/'.$file;

            if ( ! is_file($path))
            {
                $result['problems'][] = $file.': файл не найден';
                continue;
            }

            $content = (string) file_get_contents($path);
            $updated = $this->replace_block($content, $block, $this->block($block));

            if ($updated === NULL)
            {
                $result['problems'][] = $file.': нет маркеров GENERATED:'.$block
                    .' — добавьте "<!-- BEGIN GENERATED:'.$block.' -->" и "<!-- END GENERATED:'.$block.' -->"';
                continue;
            }

            if ($updated === $content)
            {
                $result['skipped'][] = $file;
                continue;
            }

            file_put_contents($path, $updated);
            $result['updated'][] = $file;
        }

        if ($targets === NULL OR in_array('map', $targets, TRUE) OR in_array('MODULE_MAP', $targets, TRUE))
        {
            $path = $this->root().'/'.self::MAP_FILE;
            $map  = $this->map();

            if ( ! is_file($path) OR file_get_contents($path) !== $map)
            {
                file_put_contents($path, $map);
                $result['updated'][] = self::MAP_FILE;
            }
            else
            {
                $result['skipped'][] = self::MAP_FILE;
            }
        }

        return $result;
    }

    /**
     * Проверяет, что сгенерированные части документации совпадают с кодом.
     *
     * @return  array   список расхождений (пусто — всё свежо)
     */
    public function check()
    {
        $problems = [];

        foreach (self::BLOCKS as $file => $block)
        {
            $path = $this->root().'/'.$file;

            if ( ! is_file($path))
            {
                $problems[] = $file.': файл не найден';
                continue;
            }

            $content = (string) file_get_contents($path);
            $updated = $this->replace_block($content, $block, $this->block($block));

            if ($updated === NULL)
            {
                $problems[] = $file.': нет маркеров GENERATED:'.$block;
                continue;
            }

            if ($updated !== $content)
            {
                $problems[] = $file.': блок GENERATED:'.$block.' устарел';
            }
        }

        $path = $this->root().'/'.self::MAP_FILE;

        if ( ! is_file($path) OR file_get_contents($path) !== $this->map())
        {
            $problems[] = self::MAP_FILE.': файл устарел или отсутствует';
        }

        return $problems;
    }

    /**
     * Содержимое блока по имени.
     *
     * @param   string  $block
     * @return  string
     */
    public function block($block)
    {
        switch ($block)
        {
            case 'erp-modules':
                return $this->erp_modules_table();

            case 'module-inventory':
                return $this->inventory_block();
        }

        return '';
    }

    /**
     * Заменяет содержимое между маркерами, сохраняя сами маркеры.
     *
     * @param   string  $content
     * @param   string  $block
     * @param   string  $body
     * @return  string|NULL  NULL, если маркеров нет
     */
    protected function replace_block($content, $block, $body)
    {
        $pattern = '/(<!-- BEGIN GENERATED:'.preg_quote($block, '/').' -->).*?(<!-- END GENERATED:'.preg_quote($block, '/').' -->)/s';

        if ( ! preg_match($pattern, $content))
        {
            return NULL;
        }

        return preg_replace($pattern, '$1'.PHP_EOL.$body.'$2', $content, 1);
    }

    /**
     * Таблица ERP-модулей для README: только доменные модули, с назначением.
     *
     * @return  string
     */
    protected function erp_modules_table()
    {
        $context = Service_Erp218_Context::instance();
        $rows    = [];

        foreach (Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ( ! preg_match('/^erp\d{3}_/', $row['name']) OR $row['core'])
            {
                continue;
            }

            $status = $row['enabled'] ? 'работает' : 'выключен';

            if ( ! $row['enabled'] AND $row['migrations'] === 0)
            {
                $status = 'выключен (нет порта)';
            }

            $rows[] = sprintf(
                '| %s | [`%s`](%s/README.md) | %s | %s |',
                $row['id'] === NULL ? '—' : $row['id'],
                $row['name'],
                str_replace($this->root().'/', '', $row['path']),
                $context->purpose($row['path'], 70) ?: '—',
                $status
            );
        }

        return PHP_EOL.'| ID | Модуль | Назначение | Статус |'.PHP_EOL
            .'| :--: | :--- | :--- | :--- |'.PHP_EOL
            .implode(PHP_EOL, $rows).PHP_EOL;
    }

    /**
     * Инвентарь модулей для docs/ARCHITECTURE.md.
     *
     * @return  string
     */
    protected function inventory_block()
    {
        $inventory = Service_Erp218_Inventory::instance();
        $rows      = $inventory->modules();

        $on_disk = 0;
        $enabled = 0;
        $erp     = 0;
        $lines   = 0;

        foreach ($rows as $row)
        {
            $on_disk++;
            $lines += $row['lines'];

            if ($row['enabled'])
            {
                $enabled++;
            }

            if (preg_match('/^erp\d{3}_/', $row['name']))
            {
                $erp++;
            }
        }

        $out = PHP_EOL.$inventory->markdown($rows).PHP_EOL;
        $out .= sprintf(
            ': %d каталогов, подключено %d, ERP-модулей %d, строк PHP ~%s.%s',
            $on_disk,
            $enabled,
            $erp,
            number_format($lines, 0, '.', ' '),
            PHP_EOL
        );

        return $out;
    }

    /**
     * docs/MODULE_MAP.md — карта модулей: что где лежит, кто владеет данными
     * и как получить контекст одного модуля, не читая репозиторий.
     *
     * @return  string
     */
    public function map()
    {
        $context   = Service_Erp218_Context::instance();
        $inventory = Service_Erp218_Inventory::instance();

        $erp      = [];
        $disabled = [];
        $core     = [];

        foreach ($inventory->modules() as $row)
        {
            if ($row['core'])
            {
                $core[] = $row;
                continue;
            }

            if (preg_match('/^erp\d{3}_/', $row['name']))
            {
                if ($row['enabled'])
                {
                    $erp[] = $row;
                }
                else
                {
                    $disabled[] = $row;
                }

                continue;
            }

            $core[] = $row;
        }

        usort($erp, [$this, 'compare_by_id']);
        usort($disabled, [$this, 'compare_by_id']);

        $out = '# Карта модулей PromERP';

        $out .= PHP_EOL.PHP_EOL.'> Файл сгенерирован командой `make docs` (`bin/console module:docs`) из кода.'
            .' Руками не правим: состав и метрики берутся из самих модулей.';

        $out .= PHP_EOL.PHP_EOL.'## Как этим пользоваться';

        $out .= PHP_EOL.PHP_EOL.'- Контекст одного модуля (контракт, таблицы, роуты, права, события, MCP, тесты):'
            .PHP_EOL.'  `bin/console module:context --module=erp401_wms` — карточка на один экран.';
        $out .= PHP_EOL.'- Проверки только этого модуля: `make check-module module=erp401_wms`.';
        $out .= PHP_EOL.'- Правила и Definition of Done: `AGENTS.md`, порядок работы — `docs/AGENT_WORKFLOW.md`.';

        $out .= PHP_EOL.PHP_EOL.'## Включённые ERP-модули ('.count($erp).')';
        $out .= PHP_EOL.PHP_EOL.'| ID | Модуль | Строк | PG | Ключевые таблицы (владелец) | MCP | Тесты | Назначение |';
        $out .= PHP_EOL.'| :--: | :--- | --: | --: | :--- | --: | :--- | :--- |';

        foreach ($erp as $row)
        {
            $out .= PHP_EOL.$this->map_row($context, $row);
        }

        if ($disabled)
        {
            $out .= PHP_EOL.PHP_EOL.'## На диске, но не подключены ('.count($disabled).')';
            $out .= PHP_EOL.PHP_EOL.'| ID | Модуль | Строк | PG-миграции | Причина / что делать |';
            $out .= PHP_EOL.'| :--: | :--- | --: | --: | :--- |';

            foreach ($disabled as $row)
            {
                $reason = $row['migrations'] === 0
                    ? 'схема осталась в `migrations/` (MySQL) — нужен порт, иначе модуль не работает'
                    : 'модуль выключен в `application/bootstrap.php`';

                $out .= PHP_EOL.sprintf(
                    '| %s | `%s` | %d | %d | %s |',
                    $row['id'] === NULL ? '—' : $row['id'],
                    $row['name'],
                    $row['lines'],
                    $row['migrations'],
                    $reason
                );
            }
        }

        $out .= PHP_EOL.PHP_EOL.'## Ядро и служебные модули ('.count($core).')';
        $out .= PHP_EOL.PHP_EOL.'Это каркас (Konine/Kohana), библиотеки и сервисные модули — не доменные.'
            .' Их таблицы и сервисы доменным модулям недоступны напрямую.';
        $out .= PHP_EOL.PHP_EOL.'| Модуль | Статус | Строк | Назначение |';
        $out .= PHP_EOL.'| :--- | :--: | --: | :--- |';

        foreach ($core as $row)
        {
            $out .= PHP_EOL.sprintf(
                '| `%s` | %s | %d | %s |',
                $row['name'],
                $row['enabled'] ? 'включён' : 'выключен',
                $row['lines'],
                $context->purpose($row['path']) ?: '—'
            );
        }

        return $out.PHP_EOL;
    }

    /**
     * Строка карты для одного модуля.
     *
     * @param   Service_Erp218_Context  $context
     * @param   array                   $row
     * @return  string
     */
    protected function map_row(Service_Erp218_Context $context, array $row)
    {
        $card = $context->build($row['name']);

        $tables = isset($card['tables']['свои']) ? $card['tables']['свои'] : [];
        $shown  = array_slice($tables, 0, 5);

        if (count($tables) > count($shown))
        {
            $shown[] = '… ещё '.(count($tables) - count($shown));
        }

        $tests = 0;

        foreach ($card['tests'] as $key => $value)
        {
            if ( ! is_array($value) OR $key === 'запуск')
            {
                continue;
            }

            foreach ($value as $line)
            {
                if (preg_match('/(\d+) тестов/', $line, $matches))
                {
                    $tests += (int) $matches[1];
                }
            }
        }

        return sprintf(
            '| %s | [`%s`](%s/README.md) | %d | %d | %s | %d | %d | %s |',
            $row['id'] === NULL ? '—' : $row['id'],
            $row['name'],
            str_replace($this->root().'/', '', $row['path']),
            $row['lines'],
            $row['migrations'],
            $tables ? '`'.implode('`, `', $shown).'`' : '—',
            count($card['mcp']),
            $tests,
            $context->purpose($row['path']) ?: '—'
        );
    }

    /**
     * Сортировка строк карты по ID (модули без ID — в конец, по имени).
     *
     * @param   array   $a
     * @param   array   $b
     * @return  integer
     */
    public function compare_by_id(array $a, array $b)
    {
        $left  = $a['id'] === NULL ? 1000 : (int) $a['id'];
        $right = $b['id'] === NULL ? 1000 : (int) $b['id'];

        if ($left === $right)
        {
            return strcmp($a['name'], $b['name']);
        }

        return ($left < $right) ? -1 : 1;
    }
}
