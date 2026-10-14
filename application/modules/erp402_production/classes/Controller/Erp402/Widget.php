<?php defined('SYSPATH') or die('No direct script access.');

/**
 * HMVC-виджеты производства (erp402_production).
 *
 * Подключаются с других страниц через Request::factory:
 *
 *     echo Request::factory('production/widget/summary')->execute()->body();
 *     echo Request::factory('production/widget/order_card')->query('order_id', 5)->execute()->body();
 *
 * @package    Erp402
 * @category   Controllers
 */
class Controller_Erp402_Widget extends Controller_Erp402_Main
{
    /** @var bool Виджет не рендерит общий шаблон — только свой фрагмент */
    public $auto_render = FALSE;

    /**
     * Сводка производства: заказы по статусам, просроченные, нехватка.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            return;
        }

        $orders = Service_Erp402_Orders::instance();

        $this->response->body(View::factory('erp402/widget/summary')
            ->set('stats', $orders->stats())
            ->set('statuses', Model_Erp402_Order::$statuses)
            ->render());
    }

    /**
     * Карточка заказа для дашбордов и страниц объектов. Параметр order_id или id.
     */
    public function action_order_card()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            return;
        }

        $id = $this->_query('order_id', $this->request->param('id'));

        if ( ! $id)
        {
            return;
        }

        $order = Service_Erp402_Orders::instance()->get($id);

        if ( ! $order)
        {
            return;
        }

        $summary = NULL;

        try
        {
            $summary = Service_Erp402_Materials::instance()->summary($order);
        }
        catch (Exception $e)
        {
            // Виджет показывается и без склада: состав и прогресс видны всегда
            Kohana::$log->add(Log::WARNING, 'erp402: виджет без потребности — :error', array(':error' => $e->getMessage()));
        }

        $this->response->body(View::factory('erp402/widget/order_card')
            ->set('order', $order)
            ->set('summary', $summary)
            ->set('progress', $order->progress())
            ->render());
    }

    /**
     * Заказы для объекта реестра (номенклатуры). Параметр item_id.
     */
    public function action_object_orders()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            return;
        }

        $item_id = (int) $this->_query('item_id', $this->request->param('id'));

        if ($item_id <= 0)
        {
            return;
        }

        $found = Service_Erp402_Orders::instance()->search(array('item_id' => $item_id), 10, 0);

        $this->response->body(View::factory('erp402/widget/object_orders')
            ->set('orders', $found['rows'])
            ->set('total', $found['total'])
            ->set('statuses', Model_Erp402_Order::$statuses)
            ->render());
    }
}
