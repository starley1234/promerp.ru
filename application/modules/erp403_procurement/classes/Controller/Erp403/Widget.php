<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Виджеты закупок (erp403_procurement): сводка для дашбордов и поставщики по
 * позиции номенклатуры (для карточки товара).
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Widget extends Controller_Erp403_Main
{
    /** @var bool Виджет не рендерит общий шаблон — только свой фрагмент */
    public $auto_render = FALSE;

    /**
     * Сводка: что ждёт согласования, что заказано и не пришло, что просрочено.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp403.view'))
        {
            return;
        }

        $this->response->body(View::factory('erp403/widget/summary')
            ->set('request_stats', $this->_requests()->stats())
            ->set('order_stats', $this->_orders()->stats())
            ->set('receipt_stats', $this->_receipts()->stats())
            ->set('submitted', $this->_requests()->search(array('status' => array('submitted')), 5, 0))
            ->set('due_soon', $this->_orders()->due_soon(7, 5))
            ->render());
    }

    /**
     * Поставщики по позиции: цены, сроки и рекомендация «у кого брать».
     */
    public function action_item_suppliers()
    {
        if ( ! $this->can('erp403.view'))
        {
            return;
        }

        $item_id = (int) $this->request->param('id');

        if ( ! $item_id)
        {
            $item_id = (int) $this->_query('item_id');
        }

        $rows = $item_id ? $this->_suppliers()->compare($item_id, $this->_query('qty')) : array();

        $this->response->body(View::factory('erp403/widget/item_suppliers')
            ->set('item_id', $item_id)
            ->set('item_name', $item_id ? $this->_suppliers()->item_name($item_id) : NULL)
            ->set('rows', $rows)
            ->set('history', $item_id ? $this->_suppliers()->history($item_id, 5) : array())
            ->render());
    }

    /**
     * Прогресс приёмки заказа (для карточки заказа производства).
     */
    public function action_order_progress()
    {
        if ( ! $this->can('erp403.view'))
        {
            return;
        }

        $order_id = (int) $this->request->param('id');
        $order    = $order_id ? $this->_orders()->get($order_id) : NULL;

        $this->response->body(View::factory('erp403/widget/order_progress')
            ->set('order', $order)
            ->set('progress', $order_id ? $this->_receipts()->order_progress($order_id) : NULL)
            ->set('receipts', $order_id ? $this->_receipts()->search(array('order_id' => $order_id), 5, 0) : array())
            ->render());
    }
}
