<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Доска продаж (erp410_shipment): что решить, что везти и что ждёт денег.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Index extends Controller_Erp410_Main
{
    /**
     * Сводка продаж.
     */
    public function action_index()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->template->content = View::factory('erp410/index/index')
            ->set('order_stats', $this->_orders()->stats())
            ->set('shipment_stats', $this->_shipments()->stats())
            ->set('invoice_stats', $this->_invoices()->stats())
            ->set('customer_stats', $this->_customers()->stats())
            ->set('draft_orders', $this->_orders()->search(array('status' => array('draft')), 5, 0))
            ->set('due_orders', $this->_orders()->due_soon(7, 5))
            ->set('picking', $this->_orders()->search(array('status' => array('reserved', 'picking')), 5, 0))
            ->set('draft_shipments', $this->_shipments()->search(array('open' => TRUE), 5, 0))
            ->set('unpaid', $this->_invoices()->search(array('overdue' => TRUE), 5, 0))
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('can_invoice', $this->can('erp410.invoice'))
            ->set('messages', $this->_flash_messages());
    }
}
