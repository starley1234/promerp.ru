<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Синхронизация документации с кодом.
 *
 *   bin/console module:docs           # обновить сгенерированные блоки
 *   bin/console module:docs --check   # код 1, если документация устарела
 *
 * Обновляет:
 *   * таблицу ERP-модулей в README.md (блок GENERATED:erp-modules);
 *   * инвентарь модулей в docs/ARCHITECTURE.md (блок GENERATED:module-inventory);
 *   * docs/MODULE_MAP.md целиком — карту модулей для быстрого входа.
 *
 * Проверка входит в `make check-module` и в тест DocsSyncTest, поэтому
 * «документация врёт» больше не проходит молча.
 *
 * @package Minion
 */
class Task_Module_Docs extends Minion_Task {

    protected $_options = [
        'check' => FALSE,
        'only'  => NULL,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $docs = Service_Erp218_Docs::instance();

        if ($this->_flag($options, 'check'))
        {
            $problems = $docs->check();

            if ($problems)
            {
                echo 'Документация устарела:'.PHP_EOL;

                foreach ($problems as $problem)
                {
                    echo '  ! '.$problem.PHP_EOL;
                }

                echo PHP_EOL.'Обновите: make docs'.PHP_EOL;

                exit(1);
            }

            echo '  [ok]   документация совпадает с кодом (README, ARCHITECTURE, MODULE_MAP)'.PHP_EOL;

            return;
        }

        $only = $this->_value($options, 'only');
        $targets = $only ? array_map('trim', explode(',', $only)) : NULL;

        $result = $docs->sync($targets);

        foreach ($result['updated'] as $file)
        {
            echo '  [upd]  '.$file.PHP_EOL;
        }

        if ($result['problems'])
        {
            foreach ($result['problems'] as $problem)
            {
                echo '  [fail] '.$problem.PHP_EOL;
            }

            exit(1);
        }

        echo 'Готово: обновлено '.count($result['updated']).', без изменений '.count($result['skipped']).'.'.PHP_EOL;
    }
}
