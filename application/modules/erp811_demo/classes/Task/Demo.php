<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Демонстрационный стенд: сборка, сброс и состояние.
 *
 *   bin/console demo:status            — что сейчас на стенде
 *   bin/console demo:plan              — что будет создано (без записи)
 *   bin/console demo:build             — создать демо-базу, миграции, наполнить
 *   bin/console demo:build --seed=42   — другой разброс (для разнообразия стенда)
 *   bin/console demo:reset             — снести и собрать заново
 *
 * Стенд — отдельная база: боевые данные в сборке не участвуют. Поэтому команду
 * можно запускать на рабочем сервере, не боясь испортить учёт.
 *
 * @package Minion
 */
class Task_Demo extends Minion_Task
{
    protected $_options = [
        'status' => FALSE,
        'plan'   => FALSE,
        'build'  => FALSE,
        'reset'  => FALSE,
        'seed'   => NULL,
    ];

    protected function _execute(?array $options = NULL)
    {
        $demo = Service_Erp811_Demo::instance();

        $status = $this->_flag($options, 'status');
        $plan   = $this->_flag($options, 'plan');
        $build  = $this->_flag($options, 'build');
        $reset  = $this->_flag($options, 'reset');
        $seed   = $this->_value($options, 'seed');

        if ( ! $demo->enabled())
        {
            echo 'Демо-стенд выключен (application/config/demo.php: enabled = FALSE)'.PHP_EOL;

            return;
        }

        if ($status OR ( ! $plan AND ! $build AND ! $reset))
        {
            $this->status($demo);

            return;
        }

        if ($plan)
        {
            $this->plan();

            return;
        }

        if ($reset)
        {
            $this->build($demo, TRUE, $seed);

            return;
        }

        $this->build($demo, FALSE, $seed);
    }

    // =========================================================================
    //  Состояние и план
    // =========================================================================

    /**
     * @param   Service_Erp811_Demo $demo
     * @return  void
     */
    protected function status(Service_Erp811_Demo $demo)
    {
        $status = $demo->status();

        echo 'PromERP · демонстрационный стенд (erp811_demo)'.PHP_EOL.PHP_EOL;

        echo '  Модуль включён:      '.($status['enabled'] ? 'да' : 'нет').PHP_EOL;
        echo '  Хранилище стенда:    '.$status['database'].' / '.$status['schema'].' ('.$status['storage'].')'.PHP_EOL;
        echo '  Хранилище найдено:           '.($status['available'] ? 'да' : 'нет — выполните bin/console demo:build').PHP_EOL;

        if ($status['available'])
        {
            echo '  Таблиц в базе:       '.$status['tables'].PHP_EOL;
            echo '  Сценарий:            '.$status['scenario'].PHP_EOL;
            echo '  Собран:              '.($status['built_at'] ? $status['built_at'] : '—').PHP_EOL;
        }

        if ($status['counts'])
        {
            echo PHP_EOL.'Что на стенде:'.PHP_EOL;

            $labels = $this->labels();

            foreach ($status['counts'] as $key => $value)
            {
                printf('  %-24s %s'.PHP_EOL, isset($labels[$key]) ? $labels[$key] : $key,
                    (is_float($value) ? number_format($value, 2, ',', ' ') : $value));
            }
        }

        $runs = $demo->runs(3);

        if ($runs)
        {
            echo PHP_EOL.'Последние сборки:'.PHP_EOL;

            foreach ($runs as $run)
            {
                printf('  #%d %s — %s'.PHP_EOL, $run['id'], $run['built_at'], $run['scenario']);
            }
        }

        if ($status['error'])
        {
            echo PHP_EOL.'Замечание: '.$status['error'].PHP_EOL;
        }
    }

    /**
     * Что будет создано — без записи в базу.
     *
     * @return  void
     */
    protected function plan()
    {
        $plan = Service_Erp811_Seeder::instance()->plan();

        echo 'PromERP · план сборки демонстрационного стенда'.PHP_EOL.PHP_EOL;

        echo '  '.$plan['Компания'].' — '.$plan['Сценарий'].PHP_EOL.PHP_EOL;

        foreach ($plan as $key => $value)
        {
            printf('  %-16s %s'.PHP_EOL, $key, $value);
        }

        echo PHP_EOL.'Сценарий описан в application/modules/erp811_demo/config/scenario/print3d.php'.PHP_EOL;
    }

    // =========================================================================
    //  Сборка
    // =========================================================================

    /**
     * @param   Service_Erp811_Demo $demo
     * @param   bool                $reset
     * @param   string|null         $seed
     * @return  void
     */
    protected function build(Service_Erp811_Demo $demo, $reset, $seed = NULL)
    {
        $logger = function ($message, $level = 'info') {
            $prefix = array(
                'ok'    => '  ✔ ',
                'warn'  => '  ! ',
                'error' => '  ✘ ',
                'info'  => '    ',
            );

            echo (isset($prefix[$level]) ? $prefix[$level] : '    ').$message.PHP_EOL;
        };

        $started = microtime(TRUE);

        echo 'PromERP · сборка демонстрационного стенда'.PHP_EOL.PHP_EOL;
        $logger('Хранилище стенда: '.$demo->storage_label().' (рабочая схема не затрагивается)');

        // 1. Сама база: создаём, если её нет.
        try
        {
            $created = $demo->create_database();

            $logger($created ? 'база создана' : 'база уже есть', $created ? 'ok' : 'info');
        }
        catch (Exception $e)
        {
            $logger('не удалось создать базу: '.$e->getMessage(), 'error');

            return;
        }

        // 2. Работаем на демо-базе до конца сборки.
        if ( ! $demo->use_demo_database())
        {
            $logger('демо-база недоступна — проверьте переменные DB_* в .env', 'error');

            return;
        }

        // 3. Чистая схема — при сбросе.
        if ($reset)
        {
            try
            {
                $demo->drop_schema();
                $logger('схема очищена перед сборкой', 'ok');
            }
            catch (Exception $e)
            {
                $logger('очистка схемы не удалась: '.$e->getMessage(), 'error');

                return;
            }
        }

        // 4. Миграции модулей стенда.
        try
        {
            $applied = $demo->migrate($logger);

            $logger('миграции применены: модулей '.count($applied), 'ok');
        }
        catch (Exception $e)
        {
            $logger('миграции: '.$e->getMessage(), 'error');
        }

        // 5. Наполнение по сценарию.
        try
        {
            $result = Service_Erp811_Seeder::instance()->build(
                $seed !== NULL ? array('seed' => (int) $seed) : array(),
                $logger
            );
        }
        catch (Exception $e)
        {
            $logger('наполнение прервано: '.$e->getMessage(), 'error');
            $demo->use_main_database();

            return;
        }

        // 6. Отметка ставится только полностью собранному стенду.
        if ($result['problems'])
        {
            $logger('стенд не принят: замечаний сборки '.count($result['problems']), 'error');
        }
        else
        {
            try
            {
                $demo->mark_built($result['counts'], NULL);
                $logger('отметка о корректной сборке записана', 'ok');
            }
            catch (Exception $e)
            {
                $logger('отметка о сборке: '.$e->getMessage(), 'warn');
            }
        }

        $demo->use_main_database();

        echo PHP_EOL.'Готово за '.sprintf('%.1f', microtime(TRUE) - $started).' с'.PHP_EOL.PHP_EOL;

        $labels = $this->labels();

        foreach ($result['counts'] as $key => $value)
        {
            printf('  %-24s %s'.PHP_EOL, isset($labels[$key]) ? $labels[$key] : $key, $value);
        }

        if ($result['problems'])
        {
            echo PHP_EOL.'Замечания сборки (стенд собран, но эти шаги пропущены):'.PHP_EOL;

            foreach ($result['problems'] as $problem)
            {
                echo '  ! '.$problem['layer'].': '.$problem['message'].PHP_EOL;
            }
        }

        echo PHP_EOL.'Дальше: ключ гостя живёт в демо-базе, вход — страница /demo'.PHP_EOL;
    }

    /**
     * Подписи счётчиков для отчёта.
     *
     * @return  array
     */
    protected function labels()
    {
        return Service_Erp811_Demo::instance()->count_labels();
    }
}
