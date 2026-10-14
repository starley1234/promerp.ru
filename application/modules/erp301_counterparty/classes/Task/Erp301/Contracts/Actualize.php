<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Актуализация статусов договоров контрагентов (erp301_counterparty).
 *
 *   bin/console erp301:contracts:actualize              # истёкшие active → expired
 *   bin/console erp301:contracts:actualize --dry-run    # только показать список
 *   bin/console erp301:contracts:actualize --notice     # + напоминания об истечении
 *
 * Задача регламентная: запускать раз в сутки,
 *   `0 6 * * * cd /srv/promerp && bin/console erp301:contracts:actualize --notice`
 *
 * @package Minion
 */
class Task_Erp301_Contracts_Actualize extends Minion_Task
{
    protected $_options = array(
        'dry-run' => FALSE,
        'notice'  => FALSE,
        'days'    => 30,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $dry_run = $this->_flag($options, 'dry-run');
        $days    = (int) $this->_value($options, 'days', 30);
        $days    = $days > 0 ? $days : 30;

        $service = Service_Erp301_Counterparties::instance();

        $expired = ORM::factory('Erp301_Contract')
            ->where('status', '=', 'active')
            ->where('is_deleted', '=', 0)
            ->where('valid_to', 'IS NOT', NULL)
            ->where('valid_to', '<', date('Y-m-d'))
            ->order_by('valid_to', 'ASC')
            ->find_all();

        echo sprintf('Договоров с истёкшим сроком: %d', count($expired)).PHP_EOL;

        foreach ($expired as $contract)
        {
            echo sprintf('  договор № %s (до %s, контрагент #%d)',
                $contract->number, $contract->valid_to, (int) $contract->counterparty_id).PHP_EOL;
        }

        if ($dry_run)
        {
            echo 'Режим проверки (dry-run): изменения не записаны.'.PHP_EOL;
        }
        else
        {
            $updated = $service->actualize_contract_statuses();
            echo sprintf('Обновлено статусов: %d', $updated).PHP_EOL;
        }

        if ($this->_flag($options, 'notice'))
        {
            $soon = $service->expiring_contracts($days);

            echo sprintf('Истекают в течение %d дней: %d', $days, count($soon)).PHP_EOL;

            foreach ($soon as $contract)
            {
                echo sprintf('  № %s → %s', $contract->number, $contract->valid_to).PHP_EOL;
            }
        }

        Kohana::$log->add(Log::INFO, 'erp301: актуализация договоров — истёкших :expired, dry-run :dry', array(
            ':expired' => count($expired),
            ':dry'     => $dry_run ? 'да' : 'нет',
        ))->write();
    }
}
