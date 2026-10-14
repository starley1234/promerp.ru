<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказы клиента (erp410_shipment): список, карточка, форма, позиции и
 * переходы состояний.
 *
 * Контроллер тонкий: правила живут в `Service_Erp410_Orders` (резерв, лимит,
 * переходы), отгрузка — в `Service_Erp410_Shipments`, счёт — в
 * `Service_Erp410_Invoices`.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Order extends Controller_Erp410_Main
{
    /**
     * Список заказов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'customer_id' => $this->_query('customer_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'open'        => $this->_query('open'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp410/order/index')
            ->set('rows', $this->_orders()->search($filters, $limit, $offset))
            ->set('total', $this->_orders()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp410_Order::$statuses)
            ->set('customers', $this->_customer_options())
            ->set('stats', $this->_orders()->stats())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка заказа: позиции, резерв, отгрузки и счета.
     */
    public function action_view()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order = $this->_orders()->get($this->request->param('id'));

        if ( ! $order)
        {
            throw new HTTP_Exception_404('Заказ клиента не найден');
        }

        $this->template->content = View::factory('erp410/order/view')
            ->set('order', $order)
            ->set('lines', $this->_orders()->lines($order->id))
            ->set('shipments', $this->_shipments()->for_order($order->id))
            ->set('invoices', $this->_invoices()->for_order($order->id))
            ->set('customer', $order->customer_id ? $this->_customers()->name($order->customer_id) : NULL)
            ->set('credit', $this->_customers()->credit_check($order->customer_id, (float) $order->amount))
            ->set('warehouses', $this->_warehouses())
            ->set('items', $this->_items())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('can_ship', $this->can('erp410.ship'))
            ->set('can_invoice', $this->can('erp410.invoice'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма заказа (создание и правка).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order = $this->_orders()->get($this->request->param('id'));

        if ($this->request->param('id') AND ! $order)
        {
            throw new HTTP_Exception_404('Заказ клиента не найден');
        }

        $this->template->content = View::factory('erp410/order/form')
            ->set('order', $order)
            ->set('lines', $order ? $this->_orders()->lines($order->id) : array())
            ->set('customers', $this->_customer_options())
            ->set('warehouses', $this->_warehouses())
            ->set('items', $this->_items())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить заказ (создание или правка).
     */
    public function action_save()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/orders');
        }

        $post = $this->request->post();
        $id   = ! empty($post['id']) ? (int) $post['id'] : NULL;

        try
        {
            if ($id)
            {
                $order = $this->_orders()->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Заказ '.$order->number.' сохранён');
            }
            else
            {
                $lines = array();

                if ( ! empty($post['item_id']))
                {
                    foreach ((array) $post['item_id'] as $index => $item_id)
                    {
                        if ( ! $item_id)
                        {
                            continue;
                        }

                        $lines[] = array(
                            'item_id'  => (int) $item_id,
                            'qty'      => isset($post['qty'][$index]) ? $post['qty'][$index] : 1,
                            'price'    => isset($post['price'][$index]) ? $post['price'][$index] : NULL,
                            'discount' => isset($post['line_discount'][$index]) ? $post['line_discount'][$index] : NULL,
                        );
                    }
                }

                $order = $this->_orders()->create($post + array('lines' => $lines), $this->_current_user_id());

                $this->_flash('success', 'Заказ '.$order->number.' создан');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить заказ');

            $this->redirect($id ? '/sales/orders/edit/'.$id : '/sales/orders/edit');
        }

        $this->redirect('/sales/orders/view/'.$order->id);
    }

    /**
     * Переход состояния: confirm | reserve | release | cancel | close | ship | invoice.
     */
    public function action_act()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/orders');
        }

        $act     = (string) $this->request->param('act');
        $id      = (int) $this->request->param('id');
        $post    = $this->request->post();
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'confirm')
            {
                $order = $this->_orders()->confirm($id, $user_id, ! empty($post['force']));
                $this->_flash('success', 'Заказ '.$order->number.' подтверждён');
            }
            elseif ($act === 'reserve')
            {
                $result = $this->_orders()->reserve($id, $user_id);

                $this->_flash('success', 'Зарезервировано позиций: '.$result['reserved']);

                foreach ($result['warnings'] as $warning)
                {
                    $this->_flash('warning', $warning);
                }
            }
            elseif ($act === 'release')
            {
                $released = $this->_orders()->release($id, $user_id);

                $this->_flash('warning', 'Снято резервов: '.$released);
            }
            elseif ($act === 'cancel')
            {
                $order = $this->_orders()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Заказ '.$order->number.' отменён');
            }
            elseif ($act === 'close')
            {
                $order = $this->_orders()->close($id, $user_id);
                $this->_flash('success', 'Заказ '.$order->number.' закрыт');
            }
            elseif ($act === 'ship')
            {
                if ( ! $this->can('erp410.ship'))
                {
                    throw new HTTP_Exception_403('Доступ запрещен');
                }

                $shipment = $this->_shipments()->build($id, array(
                    'carrier'          => isset($post['carrier']) ? $post['carrier'] : NULL,
                    'transport_number' => isset($post['transport_number']) ? $post['transport_number'] : NULL,
                    'shipment_date'    => isset($post['shipment_date']) ? $post['shipment_date'] : NULL,
                ), $user_id);

                $this->_flash('success', 'Создана отгрузка '.$shipment->number.' — проверьте позиции и проведите её');
                $this->redirect('/sales/shipments/view/'.$shipment->id);
            }
            else
            {
                throw new HTTP_Exception_404('Неизвестное действие');
            }
        }
        catch (HTTP_Exception $e)
        {
            throw $e;
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось изменить заказ');
        }

        $this->redirect('/sales/orders/view/'.$id);
    }

    /**
     * Позиции заказа: add | update | remove.
     */
    public function action_line()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/orders');
        }

        $action = (string) $this->request->param('action');
        $post   = $this->request->post();
        $id     = (int) $this->request->param('id');
        $order_id = 0;

        try
        {
            if ($action === 'add')
            {
                $order_id = ! empty($post['order_id']) ? (int) $post['order_id'] : 0;

                $this->_orders()->add_line($order_id, array(
                    'item_id'  => isset($post['item_id']) ? $post['item_id'] : NULL,
                    'name'     => isset($post['name']) ? $post['name'] : NULL,
                    'qty'      => isset($post['qty']) ? $post['qty'] : 1,
                    'price'    => isset($post['price']) ? $post['price'] : NULL,
                    'discount' => isset($post['discount']) ? $post['discount'] : NULL,
                    'uom_code' => isset($post['uom_code']) ? $post['uom_code'] : NULL,
                ));

                $this->_flash('success', 'Позиция добавлена');
            }
            elseif ($action === 'update')
            {
                $line = $this->_orders()->update_line($id, $post);

                $order_id = (int) $line->order_id;
                $this->_flash('success', 'Позиция сохранена');
            }
            elseif ($action === 'remove')
            {
                $line = $this->_orders()->line($id);

                if ( ! $line)
                {
                    throw new Exception_Erp410_Shipment('Позиция заказа не найдена', NULL, 'not_found');
                }

                $order_id = (int) $line->order_id;

                $this->_orders()->remove_line($id);
                $this->_flash('warning', 'Позиция убрана');
            }
            else
            {
                throw new HTTP_Exception_404('Неизвестное действие');
            }
        }
        catch (HTTP_Exception $e)
        {
            throw $e;
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось изменить позицию');

            if ( ! $order_id AND ! empty($post['order_id']))
            {
                $order_id = (int) $post['order_id'];
            }
        }

        $this->redirect($order_id ? '/sales/orders/view/'.$order_id : '/sales/orders');
    }

    /**
     * Отменить позицию заказа.
     */
    public function action_cancel_line()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/orders');
        }

        $line_id  = (int) $this->request->param('id');
        $order_id = 0;

        try
        {
            $line = $this->_orders()->cancel_line($line_id, $this->_current_user_id());

            $order_id = (int) $line->order_id;

            $this->_flash('warning', 'Позиция отменена');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось отменить позицию');

            $line = $this->_orders()->line($line_id);

            if ($line)
            {
                $order_id = (int) $line->order_id;
            }
        }

        $this->redirect($order_id ? '/sales/orders/view/'.$order_id : '/sales/orders');
    }
}
