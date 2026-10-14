<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Быстрый статический аудит кода (рефлексия без запуска и без БД).
 *
 *   bin/console dev:audit            # компактный отчёт
 *   bin/console dev:audit --quiet    # только ошибки
 *   bin/console dev:audit --json     # машиночитаемый отчёт (для агентов и CI)
 *   bin/console dev:audit --check=signature   # только одна проверка
 *
 * Находит то, что обычно падает только в рантайме: дубли классов, несовместимые
 * сигнатуры методов, нереализованные абстрактные методы, классы не по пути
 * автозагрузчика, битые контракты Task_/pgMigration_, отсутствующие view.
 * Один проход по 1200 файлам — доли секунды.
 *
 * @package Minion
 */
class Task_Dev_Audit extends Minion_Task {

    protected $_options = [
        'quiet' => FALSE,
        'json'  => FALSE,
        'check'  => NULL,
        'path'   => NULL,
        'module' => NULL,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $quiet  = $this->_flag($options, 'quiet');
        $json   = $this->_flag($options, 'json');
        $check  = $this->_value($options, 'check');
        $path   = $this->_value($options, 'path');
        $module = $this->_value($options, 'module');

        // --path ограничивает сам обход (быстро, но родительские классы вне
        // каталога выглядят «отсутствующими»). Для одного модуля правильнее
        // --module: обход полный, а в отчёт попадают только его находки.
        $paths = $path ? [$this->_root().'/'.trim($path, '/')] : NULL;

        $result = (new Dev_Auditor)->audit($paths);

        if ($module)
        {
            $prefix = 'application/modules/'.trim($module, '/');

            $in_module = function ($finding) use ($prefix) {
                return isset($finding['file']) AND strpos($finding['file'], $prefix) === 0;
            };

            foreach (['errors', 'warnings', 'baseline'] as $bucket)
            {
                $result[$bucket] = array_values(array_filter($result[$bucket], $in_module));
            }

            $result['stale'] = array_values(array_filter($result['stale'], $in_module));

            echo $result['stats']['files'].' файлов проверено, показаны только находки модуля '.$module.PHP_EOL;
        }

        if ($check)
        {
            $filter = function ($finding) use ($check) {
                return $finding['check'] === $check;
            };

            foreach (['errors', 'warnings', 'baseline'] as $bucket)
            {
                $result[$bucket] = array_values(array_filter($result[$bucket], $filter));
            }
        }

        if ($json)
        {
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

            exit($result['errors'] ? 1 : 0);
        }

        echo sprintf('PromERP · аудит кода: %d файлов, %d классов, %d методов за %.3f с%s',
            $result['stats']['files'],
            $result['stats']['classes'],
            $result['stats']['methods'],
            $result['stats']['time'],
            PHP_EOL);

        $groups = [];

        foreach ($result['errors'] as $finding)
        {
            $groups[$finding['check']][] = $finding;
        }

        foreach ($result['warnings'] as $finding)
        {
            $groups[$finding['check']][] = $finding;
        }

        if ( ! $groups)
        {
            echo '  [ok]   проблем не найдено'.PHP_EOL;

            return;
        }

        ksort($groups);

        $labels = [
            'duplicate_class' => 'Дубли классов',
            'class_shadow'    => 'Перекрытия классов',
            'legacy_migration' => 'Устаревшие миграции MySQL (не портированы)',
            'signature'       => 'Несовместимые сигнатуры',
            'abstract'        => 'Нереализованные абстрактные методы',
            'missing_parent'  => 'Отсутствующие родители',
            'class_file'      => 'Классы не по пути',
            'task_contract'   => 'Контракты задач Minion',
            'task_file'       => 'Задачи: имя класса не совпадает с путём',
            'migration'       => 'Контракты миграций',
            'view'            => 'Отсутствующие представления',
            'route_action'    => 'Маршруты и действия контроллеров',
            'redirect_in_try' => 'Редиректы внутри try',
            'acl'             => 'Права без объявления',
            'fluent_call'     => 'Вызовы построителя запросов',
            'dependency'      => 'Внешние библиотеки без composer install',
            'http_405'        => 'Код ответа на неверный метод запроса',
        ];

        foreach ($groups as $name => $findings)
        {
            echo PHP_EOL.($labels[$name] ?? $name).' ('.count($findings).')'.PHP_EOL;

            foreach ($findings as $finding)
            {
                if ($quiet AND $finding['severity'] == Dev_Auditor::WARN)
                {
                    continue;
                }

                echo sprintf('  %s %s:%d%s    %s%s',
                    $finding['severity'] === Dev_Auditor::ERROR ? '[FAIL]' : '[warn]',
                    $finding['file'],
                    $finding['line'],
                    PHP_EOL,
                    $finding['message'],
                    PHP_EOL);
            }
        }

        $known = $result['baseline'];

        if ($known)
        {
            echo PHP_EOL.'Известные отклонения (application/audit-baseline.php): '.count($known).PHP_EOL;

            foreach ($known as $finding)
            {
                if ($quiet)
                {
                    break;
                }

                printf('  %s:%d%s    %s%s    причина: %s%s',
                    $finding['file'], $finding['line'], PHP_EOL, $finding['message'], PHP_EOL,
                    $finding['reason'], PHP_EOL);
            }
        }

        if ($result['stale'])
        {
            echo PHP_EOL.'Записи baseline, которые больше не нужны (удалите их): '.count($result['stale']).PHP_EOL;

            foreach ($result['stale'] as $entry)
            {
                printf('  %s -> %s%s', $entry['check'], isset($entry['file']) ? $entry['file'] : '*', PHP_EOL);
            }
        }

        echo PHP_EOL.sprintf('Итого: ошибок %d, предупреждений %d, известных %d%s',
            count($result['errors']), count($result['warnings']), count($known), PHP_EOL);

        exit($result['errors'] ? 1 : 0);
    }
}
