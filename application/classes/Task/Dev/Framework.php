<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Проверка целостности каталогов фреймворка (system/, modules/).
 *
 *   bin/console dev:framework            # проверка
 *   bin/console dev:framework --update   # обновить паспорт при обновлении Konine
 *   bin/console dev:framework --quiet    # только ошибки
 *
 * @package Minion
 */
class Task_Dev_Framework extends Minion_Task {

    protected $_options = [
        'update' => FALSE,
        'quiet'  => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;
        $quiet   = $this->_flag($options, 'quiet');
        $update  = $this->_flag($options, 'update');

        $checker = new Dev_Framework();

        if ($update)
        {
            $count = $checker->update_manifest();
            echo "Паспорт фреймворка обновлён: {$count} файлов зафиксировано в {$checker->manifest_file()}".PHP_EOL;
            return;
        }

        $report = $checker->compare();

        if ($report['manifest'] === 0)
        {
            echo "  [FAIL] Паспорт фреймворка отсутствует или пуст. Выполните: bin/console dev:framework --update".PHP_EOL;
            exit(1);
        }

        $has_errors = FALSE;

        if ( ! empty($report['modified']))
        {
            $has_errors = TRUE;
            echo "  [FAIL] Изменены файлы фреймворка:".PHP_EOL;
            foreach ($report['modified'] as $file)
            {
                echo "         - {$file}".PHP_EOL;
            }
        }

        if ( ! empty($report['added']))
        {
            $has_errors = TRUE;
            echo "  [FAIL] Добавлены лишние файлы в каталоги фреймворка:".PHP_EOL;
            foreach ($report['added'] as $file)
            {
                echo "         - {$file}".PHP_EOL;
            }
        }

        if ( ! empty($report['removed']))
        {
            $has_errors = TRUE;
            echo "  [FAIL] Удалены файлы фреймворка:".PHP_EOL;
            foreach ($report['removed'] as $file)
            {
                echo "         - {$file}".PHP_EOL;
            }
        }

        if ($has_errors)
        {
            echo PHP_EOL."Фреймворк Konine 3.4.0 (system/, modules/) изменять нельзя. Все доработки должны жить в application/".PHP_EOL;
            exit(1);
        }

        if ( ! $quiet)
        {
            echo "  [ok]   файлы фреймворка Konine 3.4.0 не изменены ({$report['manifest']} файлов)".PHP_EOL;
        }
    }
}
