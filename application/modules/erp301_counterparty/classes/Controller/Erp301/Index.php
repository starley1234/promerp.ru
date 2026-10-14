<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сводка по контрагентам: количество по ролям и статусам, истекающие договоры.
 *
 * @package    Erp301
 * @category   Controllers
 */
class Controller_Erp301_Index extends Controller_Erp301_Main
{
    public function action_index()
    {
        if ( ! $this->can('erp301.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $service = Service_Erp301_Counterparties::instance();

        $this->template->content = View::factory('erp301/index/index')
            ->set('stats', $service->stats())
            ->set('expiring', $service->expiring_contracts(30)->limit(10)->find_all())
            ->set('messages', $this->_flash_messages());
    }
}
