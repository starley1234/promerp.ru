<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Отгрузки (erp410_shipment): список, карточка, сборка из заказа, проведение.
 *
 * Проведение отгрузки — единственное место, где продажа списывает товар со
 * склада. Право на него отдельное (`erp410.ship`).
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Shipment extends Controller_Erp410_Main
{
    /**
     * Список отгрузок с фильтрами.
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
            'order_id'    => $this->_query('order_id'),
            'search'      => $this->_query('search'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp410/shipment/index')
            ->set('rows', $this->_shipments()->search($filters, $limit, $offset))
            ->set('total', $this->_shipments()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp410_Shipment::$statuses)
            ->set('customers', $this->_customer_options())
            ->set('stats', $this->_shipments()->stats())
            ->set('can_ship', $this->can('erp410.ship'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка отгрузки: строки, заказ и счёт.
     */
    public function action_view()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $shipment = $this->_shipments()->get($this->request->param('id'));

        if ( ! $shipment)
        {
            throw new HTTP_Exception_404('Отгрузка не найдена');
        }

        $order = $shipment->order_id ? $this->_orders()->get($shipment->order_id) : NULL;

        $this->template->content = View::factory('erp410/shipment/view')
            ->set('shipment', $shipment)
            ->set('lines', $this->_shipments()->lines($shipment->id))
            ->set('order', $order)
            ->set('invoice', $this->_invoices()->for_shipment($shipment->id))
            ->set('customer', $shipment->customer_id ? $this->_customers()->name($shipment->customer_id) : NULL)
            ->set('warehouse', $this->_warehouse_name($shipment->warehouse_id))
            ->set('items', $this->_items())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('can_ship', $this->can('erp410.ship'))
            ->set('can_invoice', $this->can('erp410.invoice'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Собрать отгрузку из заказа.
     */
    public function action_build()
    {
        if ( ! $this->can('erp410.ship'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order_id = (int) $this->request->param('id');

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect($order_id ? '/sales/orders/view/'.$order_id : '/sales/orders');
        }

        $post = $this->request->post();

        try
        {
            $shipment = $this->_shipments()->build($order_id, array(
                'carrier'          => isset($post['carrier']) ? $post['carrier'] : NULL,
                'transport_number' => isset($post['transport_number']) ? $post['transport_number'] : NULL,
                'shipment_date'    => isset($post['shipment_date']) ? $post['shipment_date'] : NULL,
                'delivery_address' => isset($post['delivery_address']) ? $post['delivery_address'] : NULL,
                'comment'          => isset($post['comment']) ? $post['comment'] : NULL,
                'ready'            => ! empty($post['ready']),
            ), $this->_current_user_id());

            $this->_flash('success', 'Отгрузка '.$shipment->number.' создана');

            $this->redirect('/sales/shipments/view/'.$shipment->id);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось собрать отгрузку');
        }

        $this->redirect($order_id ? '/sales/orders/view/'.$order_id : '/sales/orders');
    }

    /**
     * Переход: ready | post | cancel.
     */
    public function action_act()
    {
        if ( ! $this->can('erp410.ship'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/shipments');
        }

        $act     = (string) $this->request->param('act');
        $id      = (int) $this->request->param('id');
        $post    = $this->request->post();
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'ready')
            {
                $shipment = $this->_shipments()->ready($id, $user_id);
                $this->_flash('success', 'Отгрузка '.$shipment->number.' готова к отправке');
            }
            elseif ($act === 'post')
            {
                $shipment = $this->_shipments()->post($id, $user_id);

                $this->_flash('success', 'Отгрузка '.$shipment->number.' проведена: товар списан со склада');

                if ( ! empty($post['invoice']))
                {
                    if ( ! $this->can('erp410.invoice'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $invoice = $this->_invoices()->from_shipment($shipment, $user_id);

                    $this->_flash('success', 'Выставлен счёт '.$invoice->number);

                    $this->redirect('/sales/invoices/view/'.$invoice->id);
                }
            }
            elseif ($act === 'cancel')
            {
                $shipment = $this->_shipments()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Отгрузка '.$shipment->number.' отменена');
            }
            elseif ($act === 'line_add')
            {
                $this->_shipments()->add_line($id, array(
                    'item_id'  => isset($post['item_id']) ? $post['item_id'] : NULL,
                    'name'     => isset($post['name']) ? $post['name'] : NULL,
                    'qty'      => isset($post['qty']) ? $post['qty'] : 1,
                    'price'    => isset($post['price']) ? $post['price'] : NULL,
                    'uom_code' => isset($post['uom_code']) ? $post['uom_code'] : NULL,
                ));

                $this->_flash('success', 'Строка добавлена');
            }
            elseif ($act === 'line_remove')
            {
                $this->_shipments()->remove_line((int) (isset($post['line_id']) ? $post['line_id'] : 0));

                $this->_flash('warning', 'Строка убрана');
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
            $this->_flash_error($e, 'Не удалось изменить отгрузку');
        }

        $this->redirect('/sales/shipments/view/'.$id);
    }

    /**
     * Имя склада по идентификатору.
     *
     * @param   int|null    $warehouse_id
     * @return  string|null
     */
    protected function _warehouse_name($warehouse_id)
    {
        if ( ! $warehouse_id)
        {
            return NULL;
        }

        foreach ($this->_warehouses() as $warehouse)
        {
            if ((int) $warehouse->id === (int) $warehouse_id)
            {
                return (string) $warehouse->name;
            }
        }

        return 'Склад #'.(int) $warehouse_id;
    }
}
