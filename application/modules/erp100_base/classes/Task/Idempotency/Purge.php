<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Уборка ключей идемпотентности JSON API (ядро, erp100_base).
 *
 *   bin/console idempotency:purge                 # удалить просроченные (по expires_at)
 *   bin/console idempotency:purge --hours=1       # удалить старше часа (ручная чистка)
 *   bin/console idempotency:purge --dry-run       # только показать, сколько будет удалено
 *   bin/console idempotency:purge --stats         # показать состояние без удаления
 *
 * Ключи живут 24 часа (см. `Service_Erp100_Idempotency::TTL_HOURS`): это защита
 * от повтора, а не журнал. Таблица растёт от повторных запросов агентов и
 * внешних систем, поэтому уборку стоит поставить в расписание:
 *
 *   15 * * * * cd /srv/promerp && bin/console idempotency:purge
 *
 * @package Minion
 */
class Task_Idempotency_Purge extends Minion_Task {

    protected $_options = [
        'hours'   => 0,
        'dry-run' => FALSE,
        'stats'   => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $hours   = (int) $this->_value($options, 'hours', 0);
        $dry_run = $this->_flag($options, 'dry-run');
        $stats   = $this->_flag($options, 'stats');

        if ( ! class_exists('Service_Erp100_Idempotency'))
        {
            echo 'Сервис идемпотентности недоступен — модуль erp100_base выключен?', PHP_EOL;

            return;
        }

        $service = Service_Erp100_Idempotency::instance();

        if ( ! $service->available())
        {
            echo 'Нет таблицы ключей — выполните bin/console db:migrate', PHP_EOL;

            return;
        }

        $counts = $this->_counts();

        echo sprintf('Ключи идемпотентности: всего %d, в работе %d, с ответом %d, просрочено %d',
            $counts['total'], $counts['progress'], $counts['done'], $counts['expired']), PHP_EOL;

        if ($stats)
        {
            echo 'Ничего не удаляем: это только сводка (--stats)', PHP_EOL;

            return;
        }

        if ($dry_run)
        {
            echo sprintf('Режим проверки: будет удалено %d записей',
                $hours > 0 ? $counts['older_done'] : $counts['expired']), PHP_EOL;

            return;
        }

        $deleted = $service->purge($hours);

        echo sprintf('Удалено ключей: %d (%s)',
            $deleted,
            $hours > 0 ? 'старше '.$hours.' ч.' : 'просроченные'), PHP_EOL;
    }

    /**
     * Что в таблице сейчас: по состоянию и по сроку.
     *
     * @return array
     */
    protected function _counts()
    {
        $row = DB::select(
                [DB::expr('COUNT(*)'), 'total'],
                [DB::expr("COUNT(*) FILTER (WHERE state = 'in_progress')"), 'progress'],
                [DB::expr("COUNT(*) FILTER (WHERE state = 'done')"), 'done'],
                [DB::expr("COUNT(*) FILTER (WHERE expires_at < now())"), 'expired'],
                [DB::expr("COUNT(*) FILTER (WHERE created_at < now() - interval '1 hour' AND state != 'in_progress')"), 'older_done']
            )
            ->from(Service_Erp100_Idempotency::TABLE)
            ->execute()
            ->current();

        return array_map('intval', (array) $row);
    }
}
