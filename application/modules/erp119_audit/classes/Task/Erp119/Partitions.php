<?php defined('SYSPATH') or die('No such file or directory.');

/**
 * Обслуживание секций журнала действий (erp119_audit).
 *
 *   bin/console erp119:partitions                       # сводка + создать месяцы вперёд
 *   bin/console erp119:partitions --months-ahead=6      # держать полгода вперёд
 *   bin/console erp119:partitions --attach-default      # разобрать DEFAULT-секцию
 *   bin/console erp119:partitions --drop-expired        # удалить секции старше срока хранения
 *   bin/console erp119:partitions --drop-expired --retention=12 --dry-run
 *
 * Регламент: раз в сутки
 *   10 3 * * * cd /srv/promerp && bin/console erp119:partitions --attach-default --drop-expired --quiet
 *
 * @package Minion
 */
class Task_Erp119_Partitions extends Minion_Task
{
    protected $_options = array(
        'months-back'    => NULL,
        'months-ahead'   => NULL,
        'retention'      => NULL,
        'attach-default' => FALSE,
        'drop-expired'   => FALSE,
        'dry-run'        => FALSE,
        'quiet'          => FALSE,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $dry_run = $this->_flag($options, 'dry-run');
        $quiet   = $this->_flag($options, 'quiet');

        $service = Service_Erp119_Partitions::instance();

        $back = $this->_value($options, 'months-back');
        $ahead = $this->_value($options, 'months-ahead');

        if ( ! $dry_run)
        {
            $created = $service->ensure($back !== NULL ? (int) $back : NULL, $ahead !== NULL ? (int) $ahead : NULL);

            $this->out($created
                ? 'Созданы секции: '.implode(', ', $created)
                : 'Все месячные секции уже существуют.', $quiet);
        }

        $this->inventory($service, $quiet);

        $backlog = $service->default_backlog();

        if ($backlog)
        {
            $this->out('Записи в DEFAULT-секции (месяц не был создан):', $quiet);

            foreach ($backlog as $month => $total)
            {
                $this->out(sprintf('  %s — %d', $month, $total), $quiet);
            }

            if ($this->_flag($options, 'attach-default'))
            {
                if ($dry_run)
                {
                    $this->out('Режим проверки: перенос не выполнен.', $quiet);
                }
                else
                {
                    $result = $service->attach_default();
                    $this->out(sprintf('Разобрано: перенесено %d записей в %d новых секций.',
                        $result['moved'], count($result['months'])), $quiet);
                }
            }
            else
            {
                $this->out('Перенести записи: erp119:partitions --attach-default', $quiet);
            }
        }

        $retention = $this->_value($options, 'retention');
        $expired   = $service->expired($retention !== NULL ? (int) $retention : NULL);

        if ($expired)
        {
            $this->out('Секции старше срока хранения:', $quiet);

            foreach ($expired as $partition)
            {
                $this->out(sprintf('  %s (%s, %d записей)', $partition['partition'], $partition['size'], $partition['rows']), $quiet);
            }

            if ($this->_flag($options, 'drop-expired'))
            {
                if ($dry_run)
                {
                    $this->out('Режим проверки: удаление не выполнено.', $quiet);
                }
                else
                {
                    $dropped = $service->drop_expired($retention !== NULL ? (int) $retention : NULL);
                    $this->out('Удалено секций: '.count($dropped), $quiet);
                }
            }
            else
            {
                $this->out('Удалить: erp119:partitions --drop-expired', $quiet);
            }
        }
    }

    /**
     * Таблица секций с размерами.
     *
     * @param   Service_Erp119_Partitions   $service
     * @param   bool    $quiet
     * @return  void
     */
    protected function inventory(Service_Erp119_Partitions $service, $quiet)
    {
        if ($quiet)
        {
            return;
        }

        echo PHP_EOL.'Секции '.Service_Erp119_Journal::TABLE.':'.PHP_EOL;
        echo str_pad('секция', 34).str_pad('записей', 12).str_pad('размер', 10).'границы'.PHP_EOL;

        foreach ($service->inventory() as $partition)
        {
            echo str_pad($partition['partition'], 34)
                .str_pad((string) $partition['rows'], 12)
                .str_pad($partition['size'], 10)
                .$partition['bound'].PHP_EOL;
        }

        echo PHP_EOL;
    }

    /**
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
