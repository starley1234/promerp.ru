<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Виджеты продаж (erp410_shipment) для дашбордов и карточек.
 *
 * HMVC-вызовы: `/sales/widget/summary`, `/sales/widget/customer_orders/<id>`.
 * Виджет не проверяет права сам — это делает страница-хозяин, но данные
 * отдаются только при `erp410.view`.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Widget extends Controller_Erp410_Main
{
    /**
     * Сводка продаж для дашборда.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->response->body('')->status(403);
            return;
        }

        $this->auto_render = FALSE;

        $this->response->body(View::factory('erp410/widget/summary')
            ->set('order_stats', $this->_orders()->stats())
            ->set('shipment_stats', $this->_shipments()->stats())
            ->set('invoice_stats', $this->_invoices()->stats())
            ->render());
    }

    /**
     * Заказы клиента (для карточки контрагента).
     */
    public function action_customer_orders()
    {
        if ( ! $this->can('erp410.view'))
        {
            $this->response->body('')->status(403);
            return;
        }

        $this->auto_render = FALSE;

        $customer_id = (int) $this->request->param('id');

        $this->response->body(View::factory('erp410/widget/customer_orders')
            ->set('customer_id', $customer_id)
            ->set('orders', $this->_orders()->search(array('customer_id' => $customer_id), 5, 0))
            ->set('invoices', $this->_invoices()->search(array('customer_id' => $customer_id, 'unpaid' => TRUE), 5, 0))
            ->render());
    }
}
