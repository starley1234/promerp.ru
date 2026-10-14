<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Загрузка цеха (erp408_shopfloor): что делать сегодня и где узкие места.
 *
 * Мастер приходит сюда утром: видит доску на день по рабочим центрам, очередь
 * заказов без маршрута и последние отметки. Отсюда же одним нажатием
 * раскладывается маршрут заказа (действие plan).
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Index extends Controller_Erp408_Main
{
    /**
     * GET /shopfloor?day=&center_id=
     */
    public function action_index()
    {
        if ( ! $this->can('erp408.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $day       = Erp::day($this->_query('day'));
        $center_id = (int) $this->_query('center_id', 0);

        $operations = $this->_operations();

        $board = $operations->board($day, $center_id ? $center_id : NULL);

        $stats = array();

        try
        {
            $stats = $operations->stats(7, $center_id ? $center_id : NULL);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp408: сводка не рассчитана — :error', array(':error' => $e->getMessage()));
        }

        $this->template->content = View::factory('erp408/index/index')
            ->set('board', $board)
            ->set('stats', $stats)
            ->set('centers', $this->_centers()->active())
            ->set('queue', $operations->orders_without_route(10))
            ->set('recent', $operations->recent_logs(12, $center_id ? $center_id : NULL))
            ->set('day', $day)
            ->set('center_id', $center_id)
            ->set('shift', $this->_shifts()->current())
            ->set('statuses', Model_Erp408_Operation::$statuses)
            ->set('downtime_reasons', Model_Erp408_Operation::$downtime_reasons)
            ->set('can_print_documents', $this->can('erp404.print'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * POST /shopfloor/plan — разложить маршрут заказа по операциям цеха.
     *
     * Операции берутся из техкарты (erp228); тем самым подтверждается
     * готовность заказа к работе в цехе.
     */
    public function action_plan()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order_id = (int) $this->request->post('order_id');

        if ( ! $order_id)
        {
            $this->_flash('error', 'Не указан заказ для планирования маршрута');
            $this->redirect('/shopfloor');
            return;
        }

        if ( ! class_exists('Service_Erp402_Orders'))
        {
            $this->_flash('error', 'Модуль производства не подключён — маршрут построить нечем');
            $this->redirect('/shopfloor');
            return;
        }

        try
        {
            $order = Service_Erp402_Orders::instance()->get($order_id);

            if ( ! $order)
            {
                throw new Exception_Erp408_Shopfloor('Производственный заказ не найден', NULL, 'order_not_found');
            }

            $count = $this->_operations()->plan_order($order, $this->_current_user_id());

            $this->_flash('success', 'Маршрут заказа '.$order->number.' передан в цех: операций '.$count);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: маршрут не построен — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось построить маршрут. Подробности в журнале.');
        }

        $this->redirect('/shopfloor');
    }
}
