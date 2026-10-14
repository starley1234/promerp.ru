<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Производственные заказы: список, карточка, состав, переходы статусов.
 *
 * Контроллер тонкий: разбирает запрос, проверяет права и передаёт работу
 * сервису `Service_Erp402_Orders`. Исключения сервиса показываются
 * пользователю сообщением, а не «белым экраном»: производственник должен
 * видеть, чего не хватает, а не стек вызовов.
 *
 * @package    Erp402
 * @category   Controllers
 */
class Controller_Erp402_Order extends Controller_Erp402_Main
{
    /**
     * Список заказов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $get = $this->request->query();

        $filters = array(
            'status'  => isset($get['status']) ? $get['status'] : NULL,
            'term'    => isset($get['term']) ? $get['term'] : NULL,
            'overdue' => ! empty($get['overdue']),
        );

        $page   = max(1, (int) (isset($get['page']) ? $get['page'] : 1));
        $limit  = 20;
        $result = Service_Erp402_Orders::instance()->search($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp402/order/index')
            ->set('orders', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('pages', (int) ceil($result['total'] / $limit))
            ->set('filters', $filters)
            ->set('statuses', Model_Erp402_Order::$statuses)
            ->set('priorities', Model_Erp402_Order::$priorities)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка заказа: состав, потребность, доступные действия.
     */
    public function action_view()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $orders = Service_Erp402_Orders::instance();
        $order  = $orders->get($this->request->param('id'));

        if ( ! $order)
        {
            $this->_flash('error', 'Заказ не найден');
            $this->redirect('/production/orders');
        }

        $requirements = array();
        $summary      = NULL;

        try
        {
            $materials    = Service_Erp402_Materials::instance();
            $requirements = $materials->requirements($order);
            $summary      = $materials->summary($order);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: потребность не рассчитана — :error', array(':error' => $e->getMessage()));
        }

        $this->template->content = View::factory('erp402/order/view')
            ->set('order', $order)
            ->set('items', $order->items()->find_all()->as_array())
            ->set('requirements', $requirements)
            ->set('summary', $summary)
            ->set('progress', $order->progress())
            ->set('transitions', $orders->transitions($order))
            ->set('warehouses', $this->_warehouses())
            ->set('catalog', $this->_catalog())
            ->set('can_manage', $this->can('erp402_production.manage'))
            ->set('can_plan', $this->can('erp402_production.plan'))
            ->set('can_complete', $this->can('erp402_production.complete'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма создания/правки заказа.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id    = $this->request->param('id');
        $order = $id ? Service_Erp402_Orders::instance()->get($id) : ORM::factory('Erp402_Order');

        if ($id AND ! $order)
        {
            $this->_flash('error', 'Заказ не найден');
            $this->redirect('/production/orders');
        }

        $this->template->content = View::factory('erp402/order/form')
            ->set('order', $order)
            ->set('errors', array())
            ->set('warehouses', $this->_warehouses())
            ->set('items', $this->_catalog())
            ->set('tech_cards', $this->_tech_cards())
            ->set('priorities', Model_Erp402_Order::$priorities)
            ->set('uoms', $this->_units())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить заказ (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/production/orders');
        }

        $post = $this->request->post();
        $id   = (int) Arr::get($post, 'id');

        try
        {
            $orders = Service_Erp402_Orders::instance();

            if ($id)
            {
                $order = $orders->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Заказ '.$order->number.' обновлён');
            }
            else
            {
                $order = $orders->create($post, $this->_current_user_id());
                $this->_flash('success', 'Заказ '.$order->number.' создан');
            }

            $this->redirect('/production/orders/view/'.$order->id);
        }
        catch (ORM_Validation_Exception $e)
        {
            $order = $id ? ORM::factory('Erp402_Order', $id) : ORM::factory('Erp402_Order');
            $order->values($post, array(
                'title', 'tech_card_id', 'item_id', 'uom_code', 'qty', 'priority',
                'warehouse_id', 'output_warehouse_id', 'customer_id', 'contract_id',
                'target_date', 'note',
            ));

            $this->template->content = View::factory('erp402/order/form')
                ->set('order', $order)
                ->set('errors', $e->errors('models'))
                ->set('warehouses', $this->_warehouses())
                ->set('items', $this->_catalog())
                ->set('tech_cards', $this->_tech_cards())
                ->set('priorities', Model_Erp402_Order::$priorities)
                ->set('uoms', $this->_units())
                ->set('messages', $this->_flash_messages());
        }
        catch (Exception_Erp402_Production $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect($id ? '/production/orders/view/'.$id : '/production/orders');
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp402: ошибка сохранения заказа — :error', array(':error' => $e->getMessage()));

            $this->_flash('error', 'Не удалось сохранить заказ. Подробности в журнале.');
            $this->redirect($id ? '/production/orders/view/'.$id : '/production/orders');
        }
    }

    /**
     * Заполнить состав из технологической карты (POST).
     */
    public function action_fill()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/production/orders');
        }

        $id = (int) Arr::get($this->request->post(), 'id');

        try
        {
            $result = Service_Erp402_Orders::instance()->fill_from_tech_card($id, NULL, $this->_current_user_id());

            $this->_flash('success', 'Состав заполнен из техкарты «'.$result['card']['title'].'»: строк '.$result['added']);

            if ( ! empty($result['warnings']))
            {
                foreach (array_slice($result['warnings'], 0, 3) as $warning)
                {
                    $this->_flash('warning', $warning);
                }
            }
        }
        catch (Exception_Erp402_Production $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp402: состав из техкарты не заполнен — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось заполнить состав. Подробности в журнале.');
        }

        $this->redirect('/production/orders/view/'.$id);
    }

    /**
     * Добавить или изменить строку состава (POST).
     */
    public function action_item_save()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/production/orders');
        }

        $post   = $this->request->post();
        $id     = (int) Arr::get($post, 'id');
        $order  = (int) Arr::get($post, 'order_id');
        $orders = Service_Erp402_Orders::instance();

        try
        {
            if ($id)
            {
                $orders->update_item($id, $post);
                $this->_flash('success', 'Строка состава обновлена');
            }
            else
            {
                $orders->add_item($order, $post, $this->_current_user_id());
                $this->_flash('success', 'Строка состава добавлена');
            }
        }
        catch (ORM_Validation_Exception $e)
        {
            $this->_flash('error', 'Проверьте заполнение строки состава: '.implode('; ', Arr::flatten($e->errors('models'))));
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e instanceof Exception_Erp402_Production ? $e->getMessage() : 'Не удалось сохранить строку состава');
        }

        $this->redirect('/production/orders/view/'.$order);
    }

    /**
     * Удалить строку состава.
     */
    public function action_item_delete()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $orders = Service_Erp402_Orders::instance();
        $order  = $orders->get($this->request->param('order_id'));

        try
        {
            $orders->remove_item($this->request->param('id'));
            $this->_flash('success', 'Строка состава удалена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e instanceof Exception_Erp402_Production ? $e->getMessage() : 'Не удалось удалить строку состава');
        }

        $this->redirect($order ? '/production/orders/view/'.$order->id : '/production/orders');
    }

    /**
     * Утвердить план заказа.
     */
    public function action_plan()
    {
        $this->_transition('plan', 'erp402_production.plan');
    }

    /**
     * Зарезервировать материалы.
     */
    public function action_release()
    {
        $this->_transition('release', 'erp402_production.plan');
    }

    /**
     * Начать работу.
     */
    public function action_start()
    {
        $this->_transition('start', 'erp402_production.complete');
    }

    /**
     * Завершить заказ (выпуск продукции).
     */
    public function action_complete()
    {
        if ( ! $this->can('erp402_production.complete'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id  = $this->request->param('id');
        $qty = $this->request->query('qty');

        try
        {
            $result = Service_Erp402_Orders::instance()->complete($id, $qty, $this->_current_user_id());

            $this->_flash('success', 'Выпуск оформлен: '.(float) ($qty === NULL ? $result['order']->qty : $qty).' '.$result['order']->uom_code
                .($result['received'] ? ', продукция принята на склад' : ''));

            if ($result['order']->status !== 'done')
            {
                $this->_flash('info', 'Заказ остаётся в работе: выпущено '.(float) $result['order']->qty_done.' из '.(float) $result['order']->qty);
            }
        }
        catch (Exception_Erp402_Production $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp402: выпуск не оформлен — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось оформить выпуск. Подробности в журнале.');
        }

        $this->redirect('/production/orders/view/'.$id);
    }

    /**
     * Отменить заказ.
     */
    public function action_cancel()
    {
        if ( ! $this->can('erp402_production.plan'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id     = $this->request->param('id');
        $reason = $this->request->query('reason');

        try
        {
            Service_Erp402_Orders::instance()->cancel($id, $reason, $this->_current_user_id());
            $this->_flash('success', 'Заказ отменён, резервы сняты');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e instanceof Exception_Erp402_Production ? $e->getMessage() : 'Не удалось отменить заказ');
        }

        $this->redirect('/production/orders/view/'.$id);
    }

    /**
     * Удалить заказ (черновик или отменённый).
     */
    public function action_delete()
    {
        if ( ! $this->can('erp402_production.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        try
        {
            Service_Erp402_Orders::instance()->delete($this->request->param('id'), $this->_current_user_id());
            $this->_flash('success', 'Заказ удалён');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e instanceof Exception_Erp402_Production ? $e->getMessage() : 'Не удалось удалить заказ');
        }

        $this->redirect('/production/orders');
    }

    /**
     * Общий обработчик перехода статуса.
     *
     * @param   string  $action
     * @param   string  $acl
     * @return  void
     */
    protected function _transition($action, $acl)
    {
        if ( ! $this->can($acl))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = $this->request->param('id');

        try
        {
            $orders = Service_Erp402_Orders::instance();

            switch ($action)
            {
                case 'plan':
                    $order = $orders->plan($id, $this->_current_user_id());
                    $this->_flash('success', 'План утверждён: состав проверен, потребность пересчитана');
                    break;

                case 'release':
                    $result = $orders->release($id, $this->_current_user_id());
                    $order  = $result['order'];

                    if ($result['short'] OR $result['unmapped'])
                    {
                        foreach (array_slice($result['short'], 0, 3) as $problem)
                        {
                            $this->_flash('warning', $problem);
                        }

                        if ($result['unmapped'])
                        {
                            $this->_flash('warning', 'Не сопоставлены с номенклатурой: '.implode(', ', array_slice($result['unmapped'], 0, 5)));
                        }

                        $this->_flash('info', 'Заказ остался в плане: материалы зарезервированы не полностью');
                    }
                    else
                    {
                        $this->_flash('success', 'Материалы зарезервированы: строк '.$result['reserved']);
                    }
                    break;

                case 'start':
                    $order = $orders->start($id, $this->_current_user_id());
                    $this->_flash('success', 'Работа по заказу начата');
                    break;

                default:
                    $this->redirect('/production/orders');
                    return;
            }

            $this->redirect('/production/orders/view/'.$order->id);
        }
        catch (Exception_Erp402_Production $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/production/orders/view/'.$id);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp402: переход статуса не выполнен — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось изменить статус заказа. Подробности в журнале.');
            $this->redirect('/production/orders/view/'.$id);
        }
    }

    /**
     * Номенклатура для выбора в форме (реестр erp227 через сервис склада).
     *
     * @return  array
     */
    protected function _catalog()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return array();
        }

        try
        {
            return Service_Erp401_Stock::instance()->catalog(300);
        }
        catch (Exception $e)
        {
            return array();
        }
    }

    /**
     * Технологические карты для выбора (через сервис erp228).
     *
     * @return  array
     */
    protected function _tech_cards()
    {
        if ( ! class_exists('Service_Erp228_TechCards'))
        {
            return array();
        }

        try
        {
            $found = Service_Erp228_TechCards::instance()->search(array(), 100, 0);

            return isset($found['rows']) ? $found['rows'] : array();
        }
        catch (Exception $e)
        {
            return array();
        }
    }

    /**
     * Единицы измерения (реестр erp227 через сервис склада).
     *
     * @return  array
     */
    protected function _units()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return array();
        }

        try
        {
            return Service_Erp401_Stock::instance()->units();
        }
        catch (Exception $e)
        {
            return array();
        }
    }
}
