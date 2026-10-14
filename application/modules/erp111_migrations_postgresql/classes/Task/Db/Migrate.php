<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Накат миграций PostgreSQL.
 *
 *   bin/console db:migrate                  — применить все новые миграции
 *   bin/console db:migrate --status         — что применено, что ждёт
 *   bin/console db:migrate --dry-run        — показать план без изменений
 *   bin/console db:migrate --module=erp401_wms
 *   bin/console db:migrate --down=pgMigration_001_Erp401_Wms_CreateTables
 *
 * @package Minion
 */
class Task_Db_Migrate extends Minion_Task {

    protected $_options = [
        'status'   => FALSE,
        'dry-run'  => FALSE,
        'down'     => NULL,
        'module'   => NULL,
    ];

    protected function _execute(?array $options = NULL)
    {
        $status   = $this->_flag($options, 'status');
        $dry_run  = $this->_flag($options, 'dry-run');
        $down     = Arr::get($options, 'down');
        $module   = Arr::get($options, 'module');

        $migrator = Service_Erp111_Migrator::instance();

        $logger = function ($message, $level = 'info') {
            $prefix = [
                'ok'    => '  ✔ ',
                'error' => '  ✘ ',
                'info'  => '    ',
                'warn'  => '  ! ',
            ];

            echo (isset($prefix[$level]) ? $prefix[$level] : '    ').$message.PHP_EOL;
        };

        echo 'PromERP · миграции PostgreSQL (группа '.$migrator->db_group().')'.PHP_EOL;

        if ($status)
        {
            echo $migrator->status_report($module).PHP_EOL;

            return;
        }

        if ($down !== NULL)
        {
            $logger('Откат '.$down);
            $migrator->rollback($down);
            $logger('откат выполнен', 'ok');

            return;
        }

        if ($dry_run)
        {
            $logger('Режим проверки: изменения в БД не вносятся', 'warn');
        }

        $stats = $migrator->migrate([
            'module'  => $module,
            'dry_run' => $dry_run,
        ], $logger);

        echo PHP_EOL;

        if ($stats['failed'] !== NULL)
        {
            echo 'Миграции остановлены на '.$stats['failed'].' (исправьте ошибку и повторите).'.PHP_EOL;
            exit(1);
        }

        echo sprintf('Готово: применено %d, уже было применено %d.%s',
            count($stats['applied']),
            $stats['already_applied'],
            PHP_EOL);
    }
}
