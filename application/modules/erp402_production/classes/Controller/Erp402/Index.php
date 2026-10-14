<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сводка производства (erp402_production).
 *
 * @package    Erp402
 * @category   Controllers
 */
class Controller_Erp402_Index extends Controller_Erp402_Main
{
    /**
     * Дашборд: состояние заказов, просроченные, нехватка материалов.
     */
    public function action_index()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $orders = Service_Erp402_Orders::instance();

        $stats = $orders->stats();

        $open  = $orders->search(array('open' => TRUE), 10, 0);
        $late  = $orders->search(array('overdue' => TRUE), 10, 0);

        $shortages = array();

        try
        {
            $shortages = Service_Erp402_Materials::instance()->shortages(10);
        }
        catch (Exception $e)
        {
            // Склад недоступен — сводка всё равно должна открыться
            Kohana::$log->add(Log::WARNING, 'erp402: потребность не рассчитана — :error', array(':error' => $e->getMessage()));
        }

        $this->template->content = View::factory('erp402/index/index')
            ->set('stats', $stats)
            ->set('open_orders', $open['rows'])
            ->set('overdue', $late['rows'])
            ->set('shortages', $shortages)
            ->set('statuses', Model_Erp402_Order::$statuses)
            ->set('messages', $this->_flash_messages());
    }
}
