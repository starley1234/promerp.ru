<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказы поставщику (erp403_procurement): список, карточка, правка, позиции,
 * отправка поставщику, подтверждение, отмена и создание приёмки.
 *
 * Контроллер тонкий: правила живут в `Service_Erp403_Orders` и
 * `Service_Erp403_Receipts`.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Order extends Controller_Erp403_Main
{
    /**
     * Список заказов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'supplier_id' => $this->_query('supplier_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'open'        => $this->_query('open'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp403/order/index')
            ->set('rows', $this->_orders()->search($filters, $limit, $offset))
            ->set('total', $this->_orders()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp403_Order::$statuses)
            ->set('suppliers', $this->_supplier_options())
            ->set('stats', $this->_orders()->stats())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка заказа: позиции, прогресс приёмки, приёмки и цены.
     */
    public function action_view()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order = $this->_orders()->get($this->request->param('id'));

        if ( ! $order)
        {
            throw new HTTP_Exception_404('Заказ не найден');
        }

        $request = $order->request_id ? $this->_requests()->get($order->request_id) : NULL;

        $this->template->content = View::factory('erp403/order/view')
            ->set('order', $order)
            ->set('lines', $this->_orders()->lines($order->id))
            ->set('progress', $this->_receipts()->order_progress($order->id))
            ->set('receipts', $this->_receipts()->search(array('order_id' => $order->id), 50, 0))
            ->set('request', $request)
            ->set('supplier', $order->supplier_id ? $this->_suppliers()->counterparty($order->supplier_id) : NULL)
            ->set('supplier_profile', $order->supplier_id ? $this->_suppliers()->profile($order->supplier_id, FALSE) : NULL)
            ->set('warehouses', $this->_warehouses())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('can_approve', $this->can('erp403.approve'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма заказа (создание и правка).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order = $this->_orders()->get($this->request->param('id'));

        if ($this->request->param('id') AND ! $order)
        {
            throw new HTTP_Exception_404('Заказ не найден');
        }

        $this->template->content = View::factory('erp403/order/form')
            ->set('order', $order)
            ->set('lines', $order ? $this->_orders()->lines($order->id) : array())
            ->set('suppliers', $this->_supplier_options())
            ->set('warehouses', $this->_warehouses())
            ->set('request_id', $this->_query('request_id'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить заказ (создание или правка).
     */
    public function action_save()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/orders');
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
                // Заказ из заявки: поставщик известен из согласованных позиций.
                if ( ! empty($post['request_id']))
                {
                    $result = $this->_orders()->from_requests(array((int) $post['request_id']), array(
                        'delivery_date' => isset($post['delivery_date']) ? $post['delivery_date'] : NULL,
                        'currency'      => isset($post['currency']) ? $post['currency'] : NULL,
                        'note'          => isset($post['note']) ? $post['note'] : NULL,
                    ), $this->_current_user_id());

                    if ( ! $result['orders'])
                    {
                        $reason = $result['skipped'] ? $result['skipped'][0]['reason'] : 'Не удалось собрать заказ';

                        throw new Exception_Erp403_Procurement($reason, NULL, 'invalid_transition');
                    }

                    $order = $result['orders'][0];

                    foreach ($result['warnings'] as $warning)
                    {
                        $this->_flash('warning', $warning);
                    }
                }
                else
                {
                    $order = $this->_orders()->create($post, $this->_current_user_id());
                }

                $this->_flash('success', 'Заказ '.$order->number.' создан');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить заказ');

            $this->redirect($id ? '/procurement/orders/edit/'.$id : '/procurement/orders/edit');
        }

        $this->redirect('/procurement/orders/view/'.$order->id);
    }

    /**
     * Переход состояния: send | confirm | cancel | receipt.
     */
    public function action_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/orders');
        }

        $act     = (string) $this->request->param('act');
        $id      = (int) $this->request->param('id');
        $post    = $this->request->post();
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'send')
            {
                $order = $this->_orders()->send($id, $user_id);
                $this->_flash('success', 'Заказ '.$order->number.' отправлен поставщику');
            }
            elseif ($act === 'confirm')
            {
                $order = $this->_orders()->confirm($id, $user_id);
                $this->_flash('success', 'Заказ '.$order->number.' подтверждён поставщиком');
            }
            elseif ($act === 'cancel')
            {
                $order = $this->_orders()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Заказ '.$order->number.' отменён');
            }
            elseif ($act === 'receipt')
            {
                if ( ! $this->can('erp403.approve'))
                {
                    throw new HTTP_Exception_403('Доступ запрещен');
                }

                $receipt = $this->_receipts()->create(array(
                    'order_id'   => $id,
                    'doc_number' => isset($post['doc_number']) ? $post['doc_number'] : NULL,
                    'doc_date'   => isset($post['doc_date']) ? $post['doc_date'] : NULL,
                    'received_at' => isset($post['received_at']) ? $post['received_at'] : NULL,
                ), $user_id);

                $this->_flash('success', 'Создана приёмка '.$receipt->number.' — проверьте позиции и проведите её');
                $this->redirect('/procurement/receipts/view/'.$receipt->id);
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

        $this->redirect('/procurement/orders/view/'.$id);
    }

    /**
     * Позиции заказа: add | update | remove.
     */
    public function action_line()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/orders');
        }

        $action   = (string) $this->request->param('action');
        $id       = (int) $this->request->param('id');
        $post     = $this->request->post();
        $order_id = ! empty($post['order_id']) ? (int) $post['order_id'] : NULL;

        try
        {
            if ($action === 'add')
            {
                if ( ! $order_id)
                {
                    throw new Exception_Erp403_Procurement('Не указан заказ', NULL, 'not_found');
                }

                $line = $this->_orders()->add_line($order_id, $post);

                $this->_flash('success', 'Позиция «'.$line->name.'» добавлена');
                $order_id = (int) $line->order_id;
            }
            elseif ($action === 'update')
            {
                $line = $this->_orders()->update_line($id, $post);

                $order_id = (int) $line->order_id;
                $this->_flash('success', 'Позиция обновлена');
            }
            elseif ($action === 'remove')
            {
                $line = $this->_orders()->line($id);
                $order_id = $line ? (int) $line->order_id : $order_id;

                $this->_orders()->remove_line($id);
                $this->_flash('warning', 'Позиция удалена');
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
        }

        $this->redirect($order_id ? '/procurement/orders/view/'.$order_id : '/procurement/orders');
    }
}
