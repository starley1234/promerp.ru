<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инвентаризация модулей.
 *
 *   bin/console module:list                 # все модули (включая отключённые)
 *   bin/console module:list --enabled-only  # только подключённые в bootstrap
 *   bin/console module:list --markdown      # таблица для docs/ARCHITECTURE.md
 *
 * @package Minion
 */
class Task_Module_List extends Minion_Task {

    protected $_options = [
        'enabled-only' => FALSE,
        'markdown'     => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $markdown     = $this->_flag($options, 'markdown');
        $enabled_only = $this->_flag($options, 'enabled-only');

        $inventory = Service_Erp218_Inventory::instance();

        if ($markdown)
        {
            echo $this->markdown($inventory->modules(), $enabled_only);

            return;
        }

        echo $inventory->report( ! $enabled_only);
    }

    /**
     * Таблица в формате Markdown (для документации) — формат живёт в сервисе,
     * чтобы CLI и сгенерированные документы не расходились.
     *
     * @param   array    $rows
     * @param   boolean  $enabled_only
     * @return  string
     */
    protected function markdown(array $rows, $enabled_only = FALSE)
    {
        return Service_Erp218_Inventory::instance()->markdown($rows, $enabled_only);
    }
}
