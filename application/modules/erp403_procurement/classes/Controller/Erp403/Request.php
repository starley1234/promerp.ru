<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заявки на закупку (erp403_procurement): список, карточка, правка, позиции,
 * согласование и превращение в заказ поставщику.
 *
 * Контроллер тонкий: правила (кто может править, что происходит при
 * согласовании, как заявка уходит в заказ) живут в `Service_Erp403_Requests`
 * и `Service_Erp403_Orders`.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Request extends Controller_Erp403_Main
{
    /**
     * Список заявок с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status' => $this->_query('status'),
            'source' => $this->_query('source'),
            'search' => $this->_query('search'),
            'overdue' => $this->_query('overdue'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp403/request/index')
            ->set('rows', $this->_requests()->search($filters, $limit, $offset))
            ->set('total', $this->_requests()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp403_Request::$statuses)
            ->set('sources', Model_Erp403_Request::$sources)
            ->set('stats', $this->_requests()->stats())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка заявки: позиции, переходы, заказ.
     */
    public function action_view()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $request = $this->_requests()->get($this->request->param('id'));

        if ( ! $request)
        {
            throw new HTTP_Exception_404('Заявка не найдена');
        }

        $order = $request->order_id ? $this->_orders()->get($request->order_id) : NULL;

        $this->template->content = View::factory('erp403/request/view')
            ->set('request', $request)
            ->set('lines', $this->_requests()->lines($request->id))
            ->set('order', $order)
            ->set('warehouses', $this->_warehouses())
            ->set('suppliers', $this->_supplier_options())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('can_approve', $this->can('erp403.approve'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма заявки (создание и правка).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $request = $this->_requests()->get($this->request->param('id'));

        if ($this->request->param('id') AND ! $request)
        {
            throw new HTTP_Exception_404('Заявка не найдена');
        }

        $this->template->content = View::factory('erp403/request/form')
            ->set('request', $request)
            ->set('lines', $request ? $this->_requests()->lines($request->id) : array())
            ->set('priorities', Model_Erp403_Request::$priorities)
            ->set('warehouses', $this->_warehouses())
            ->set('suppliers', $this->_supplier_options())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить заявку (создание или правка) вместе с позициями.
     */
    public function action_save()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/requests');
        }

        $post = $this->request->post();
        $id   = ! empty($post['id']) ? (int) $post['id'] : NULL;

        try
        {
            if ($id)
            {
                $request = $this->_requests()->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Заявка '.$request->number.' сохранена');
            }
            else
            {
                $request = $this->_requests()->create($post, $this->_current_user_id());
                $this->_flash('success', 'Заявка '.$request->number.' создана');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить заявку');

            $this->redirect($id ? '/procurement/requests/edit/'.$id : '/procurement/requests/edit');
        }

        $this->redirect('/procurement/requests/view/'.$request->id);
    }

    /**
     * Переход состояния: submit | approve | reject | cancel | to_order.
     */
    public function action_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/requests');
        }

        $act      = (string) $this->request->param('act');
        $id       = (int) $this->request->param('id');
        $post     = $this->request->post();
        $user_id  = $this->_current_user_id();

        if ($act === 'approve' OR $act === 'reject')
        {
            if ( ! $this->can('erp403.approve'))
            {
                throw new HTTP_Exception_403('Доступ запрещен');
            }
        }

        try
        {
            if ($act === 'submit')
            {
                $request = $this->_requests()->submit($id, $user_id);
                $this->_flash('success', 'Заявка '.$request->number.' отправлена на согласование');
            }
            elseif ($act === 'approve')
            {
                $request = $this->_requests()->approve($id, $user_id);
                $this->_flash('success', 'Заявка '.$request->number.' согласована — можно заказывать');
            }
            elseif ($act === 'reject')
            {
                $request = $this->_requests()->reject($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Заявка '.$request->number.' отклонена');
            }
            elseif ($act === 'cancel')
            {
                $request = $this->_requests()->cancel($id, $user_id);
                $this->_flash('warning', 'Заявка '.$request->number.' отменена');
            }
            elseif ($act === 'to_order')
            {
                $supplier_id = ! empty($post['supplier_id']) ? (int) $post['supplier_id'] : NULL;
                $request     = $this->_requests()->get($id);

                if ( ! $request)
                {
                    throw new Exception_Erp403_Procurement('Заявка не найдена', NULL, 'not_found');
                }

                if ($supplier_id)
                {
                    // Явный выбор поставщика: проставляем строкам и собираем заказ.
                    foreach ($this->_requests()->lines($request->id) as $line)
                    {
                        if ($line->is_open())
                        {
                            $this->_requests()->update_line($line->id, array('supplier_id' => $supplier_id));
                        }
                    }
                }

                $result = $this->_orders()->from_requests(array($request->id), array(
                    'warehouse_id' => $request->warehouse_id,
                ), $user_id);

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

                $this->_flash('success', 'Создан заказ поставщику '.$order->number);
                $this->redirect('/procurement/orders/view/'.$order->id);
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
            $this->_flash_error($e, 'Не удалось изменить заявку');
        }

        $this->redirect('/procurement/requests/view/'.$id);
    }

    /**
     * Позиции заявки: add | update | remove.
     */
    public function action_line()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/requests');
        }

        $action = (string) $this->request->param('action');
        $id     = (int) $this->request->param('id');
        $post   = $this->request->post();

        $request_id = ! empty($post['request_id']) ? (int) $post['request_id'] : NULL;

        try
        {
            if ($action === 'add')
            {
                if ( ! $request_id)
                {
                    throw new Exception_Erp403_Procurement('Не указана заявка', NULL, 'not_found');
                }

                $line = $this->_requests()->add_line($request_id, $post);

                $this->_flash('success', 'Позиция «'.$line->name.'» добавлена');
                $request_id = (int) $line->request_id;
            }
            elseif ($action === 'update')
            {
                $line = $this->_requests()->update_line($id, $post);

                $request_id = (int) $line->request_id;
                $this->_flash('success', 'Позиция обновлена');
            }
            elseif ($action === 'remove')
            {
                $line = $this->_requests()->line($id);
                $request_id = $line ? (int) $line->request_id : $request_id;

                $this->_requests()->remove_line($id);
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

        $this->redirect($request_id ? '/procurement/requests/view/'.$request_id : '/procurement/requests');
    }
}
