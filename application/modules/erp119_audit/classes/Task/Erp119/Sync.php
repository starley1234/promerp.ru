<?php defined('SYSPATH') or die('No such file or directory.');

/**
 * Разбор событий шины в журнал действий (erp119_audit).
 *
 *   bin/console erp119:sync                  # дочитать события шины после указателя
 *   bin/console erp119:sync --dry-run        # показать, что попадёт в журнал
 *   bin/console erp119:sync --limit=500      # порция за запуск
 *   bin/console erp119:sync --replay         # вернуть в БД записи из файла-страховки
 *
 * Зачем задача, а не слушатель события: подписка на шину выполняется в запросе
 * пользователя, и её сбой (или сбой записи в журнал) бьёт по бизнес-операции.
 * Здесь обратный порядок: шина уже сохранила событие в erp_208_events
 * (config/erp208_events.php → log_published_events), а журнал спокойно
 * дочитывает его по указателю. Повторный запуск ничего не задваивает.
 *
 * Регламент: раз в минуту-пять вместе с очередью уведомлений
 *   *​/5 * * * * cd /srv/promerp && bin/console erp119:sync --quiet && bin/console erp119:sync --replay --quiet
 *
 * @package Minion
 */
class Task_Erp119_Sync extends Minion_Task
{
    protected $_options = array(
        'limit'    => 1000,
        'dry-run'  => FALSE,
        'replay'   => FALSE,
        'reset'    => FALSE,
        'quiet'    => FALSE,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $limit   = (int) $this->_value($options, 'limit', 1000);
        $dry_run = $this->_flag($options, 'dry-run');
        $quiet   = $this->_flag($options, 'quiet');

        $journal = Service_Erp119_Journal::instance();

        if ($this->_flag($options, 'reset'))
        {
            // Перечитать шину с начала: полезно после изменения правил разбора
            $journal->set_cursor(0);
            $this->out('Указатель чтения событий сброшен (записи журнала не удаляются).', $quiet);
        }

        if ($this->_flag($options, 'replay'))
        {
            $result = $journal->replay();
            $this->out(sprintf('Файл-страховка: возвращено %d записей, пропущено строк %d (%s)',
                $result['imported'], $result['failed'], $result['file']), $quiet);
        }

        $this->sync_events($journal, $limit, $dry_run, $quiet);
    }

    /**
     * Дочитать события шины и отчитаться о результате.
     *
     * @param   Service_Erp119_Journal  $journal
     * @param   int     $limit
     * @param   bool    $dry_run
     * @param   bool    $quiet
     * @return  array
     */
    protected function sync_events(Service_Erp119_Journal $journal, $limit, $dry_run, $quiet)
    {
        $stat = Service_Erp119_Sync::instance()->run($limit, $dry_run);

        if ( ! $stat['read'])
        {
            $this->out('Новых событий нет (указатель на #'.$stat['cursor'].').', $quiet);

            return $stat;
        }

        if ($dry_run)
        {
            foreach ($stat['entries'] as $entry)
            {
                $this->out(sprintf('  %s/%s #%s %s → %s', $entry['module'], $entry['entity_type'],
                    $entry['entity_id'] !== '' ? $entry['entity_id'] : '—', $entry['action'],
                    $entry['entity_label'] ? $entry['entity_label'] : '—'), $quiet);
            }

            $this->out(sprintf('Событий прочитано: %d, в журнал попало бы: %d, без правил: %d, уже было: %d',
                $stat['read'], count($stat['entries']), $stat['skipped'], $stat['known']), $quiet);

            $this->report_skipped($stat, $quiet);

            return $stat;
        }

        $this->out(sprintf('Событий прочитано: %d, записано в журнал: %d, без правил: %d, уже было: %d, указатель: #%d',
            $stat['read'], $stat['journaled'], $stat['skipped'], $stat['known'], $stat['cursor']), $quiet);

        $this->report_skipped($stat, $quiet);

        return $stat;
    }

    /**
     * Отчёт о событиях, не попавших в журнал.
     *
     * Разделены на два случая: событие отброшено по списку игнорирования
     * (`ignore_events` — шум шины, миграции, сам журнал) и событие не разобрано
     * правилами (`event_rules` + разбор по имени). Второй список — рабочий:
     * по нему видно, какое правило дописать в `config/erp119_audit.php`.
     *
     * @param   array   $stat
     * @param   bool    $quiet
     * @return  void
     */
    protected function report_skipped(array $stat, $quiet)
    {
        foreach (array('unparsed' => 'без правила разбора', 'ignored' => 'отброшено по игнорированию') as $bucket => $title)
        {
            if (empty($stat[$bucket]))
            {
                continue;
            }

            arsort($stat[$bucket]);

            $parts = array();

            foreach (array_slice($stat[$bucket], 0, 5, TRUE) as $name => $count)
            {
                $parts[] = $name.' ×'.$count;
            }

            $this->out(sprintf('  %s: %d (%s)', $title, array_sum($stat[$bucket]), implode(', ', $parts)), $quiet);
        }
    }

    /**
     * Вывод с учётом --quiet.
     *
     * @param   string  $text
     * @param   bool    $quiet
     * @return  void
     */
    protected function out($text, $quiet)
    {
        if ($quiet)
        {
            return;
        }

        echo $text.PHP_EOL;
    }
}
