<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Операции цеха (erp408_shopfloor): список, карточка и переходы.
 *
 * Все переходы идут через `Service_Erp408_Operations::act()` — одно слово на
 * действие, поэтому кнопка в интерфейсе, вызов API и инструмент MCP делают
 * ровно одно и то же. Права: `erp408.manage` — планирование, назначение,
 * отмена и удаление; `erp408.terminal` — отметки по работе (старт, пауза,
 * выработка, простой, завершение).
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Operations extends Controller_Erp408_Main
{
    /** @var array Действие => право */
    protected $_rights = array(
        'assign'         => 'erp408.manage',
        'start'          => 'erp408.terminal',
        'pause'          => 'erp408.terminal',
        'resume'         => 'erp408.terminal',
        'output'         => 'erp408.terminal',
        'finish'         => 'erp408.terminal',
        'downtime_start' => 'erp408.terminal',
        'downtime_stop'  => 'erp408.terminal',
        'cancel'         => 'erp408.manage',
        'delete'         => 'erp408.manage',
    );

    /**
     * GET /shopfloor/operations?status=&center_id=&day=&term=&open_only=
     */
    public function action_index()
    {
        if ( ! $this->can('erp408.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $query  = $this->request->query();
        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;

        $filters = array(
            'status'    => isset($query['status']) ? $query['status'] : NULL,
            'center_id' => isset($query['center_id']) ? (int) $query['center_id'] : NULL,
            'day'       => isset($query['day']) ? Erp::day($query['day'], NULL) : NULL,
            'term'      => isset($query['term']) ? $query['term'] : NULL,
            'open_only' => ! empty($query['open_only']),
        );

        $result = $this->_operations()->search($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp408/operations/index')
            ->set('operations', $result['rows'])
            ->set('total', $result['total'])
            ->set('pages', $result['pages'])
            ->set('page', $page)
            ->set('filters', $filters)
            ->set('centers', $this->_centers()->active())
            ->set('statuses', Model_Erp408_Operation::$statuses)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * GET /shopfloor/operations/view/<id>
     */
    public function action_view()
    {
        if ( ! $this->can('erp408.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $operation = $this->_operations()->get($this->request->param('id'));

        if ( ! $operation)
        {
            $this->_flash('error', 'Операция не найдена');
            $this->redirect('/shopfloor/operations');
            return;
        }

        // Права передаются во представление флагами: View::capture() включается
        // статически, поэтому $this в представлении недоступен (см. dev:audit).
        $this->template->content = View::factory('erp408/operations/view')
            ->set('operation', $operation)
            ->set('can_terminal', $this->can('erp408.terminal'))
            ->set('can_manage', $this->can('erp408.manage'))
            ->set('logs', $this->_operations()->worklogs($operation['id'], 50))
            ->set('centers', $this->_centers()->active())
            ->set('shifts', $this->_shifts()->active())
            ->set('downtime_reasons', Model_Erp408_Operation::$downtime_reasons)
            ->set('logs_kinds', Model_Erp408_Worklog::$kinds)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * GET /shopfloor/operations/edit(/<id>) — форма операции (ручная работа).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id        = $this->request->param('id');
        $operation = $id ? $this->_operations()->get($id) : NULL;
        $order_id  = (int) $this->_query('order_id', 0);

        if ($id AND ! $operation)
        {
            $this->_flash('error', 'Операция не найдена');
            $this->redirect('/shopfloor/operations');
            return;
        }

        $orders = array();

        if (class_exists('Service_Erp402_Orders'))
        {
            $orders = Service_Erp402_Orders::instance()->search(array('open' => TRUE), 100, 0);
            $orders = $orders['rows'];
        }

        $this->template->content = View::factory('erp408/operations/form')
            ->set('operation', $operation)
            ->set('order_id', $operation ? (int) $operation['order_id'] : $order_id)
            ->set('orders', $orders)
            ->set('centers', $this->_centers()->active())
            ->set('shifts', $this->_shifts()->active())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * POST /shopfloor/operations/save — создать или изменить операцию.
     */
    public function action_save()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $post = $this->request->post();
        $id   = isset($post['id']) ? (int) $post['id'] : 0;

        try
        {
            if ($id)
            {
                $operation = $this->_operations()->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Операция сохранена');
            }
            else
            {
                $operation = $this->_operations()->add_operation($post, $this->_current_user_id());
                $this->_flash('success', 'Операция добавлена в маршрут заказа');
            }

            $this->redirect('/shopfloor/operations/view/'.$operation['id']);
            return;
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: операция не сохранена — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось сохранить операцию. Подробности в журнале.');
        }

        $this->redirect($id ? '/shopfloor/operations/edit/'.$id : '/shopfloor/operations/edit');
    }

    /**
     * POST /shopfloor/operations/act/<action>/<id> — все переходы цеха.
     *
     * Один маршрут на «начать», «пауза», «выработка», «завершить», «простой»,
     * «отменить», «назначить» и «удалить»: список действий берётся из
     * `allowed_actions()` сервиса.
     */
    public function action_act()
    {
        // Сегмент маршрута называется <act>: имя 'action' занято самим
        // действием контроллера, и перепутать их очень легко.
        $action = (string) $this->request->param('act');
        $id     = $this->request->param('id');

        $right = isset($this->_rights[$action]) ? $this->_rights[$action] : 'erp408.manage';

        if ( ! $this->can($right))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $post = $this->request->post();

        $context = array(
            'qty_ok'         => isset($post['qty_ok']) ? $post['qty_ok'] : NULL,
            'qty_scrap'      => isset($post['qty_scrap']) ? $post['qty_scrap'] : NULL,
            'comment'        => isset($post['comment']) ? $post['comment'] : NULL,
            'reason'         => isset($post['reason']) ? $post['reason'] : NULL,
            'work_center_id' => isset($post['work_center_id']) ? (int) $post['work_center_id'] : NULL,
            'shift_id'       => isset($post['shift_id']) ? (int) $post['shift_id'] : NULL,
            'planned_day'    => isset($post['planned_day']) ? $post['planned_day'] : NULL,
        );

        $operation = $this->_act($id, $action, $context);

        if ($operation AND ! $this->_query('quiet'))
        {
            $this->_flash('success', 'Операция '.$operation['op_no'].': '.$this->_act_label($action));
        }

        $back = (string) $this->_query('back', '');

        if ($back === 'terminal')
        {
            $this->redirect('/shopfloor/terminal?center_id='.(int) $this->_query('center_id', 0));
            return;
        }

        if ($back === 'operations')
        {
            $this->redirect('/shopfloor/operations');
            return;
        }

        $this->redirect('/shopfloor/operations/view/'.$id);
    }

    /**
     * POST /shopfloor/operations/plan — маршрут заказа из техкарты.
     */
    public function action_plan()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order_id = (int) $this->request->post('order_id');

        if ( ! $order_id OR ! class_exists('Service_Erp402_Orders'))
        {
            $this->_flash('error', 'Заказ не указан или модуль производства не подключён');
            $this->redirect('/shopfloor/operations');
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

            $this->_flash('success', 'Маршрут заказа '.$order->number.': операций '.$count);
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

        $this->redirect($this->_query('back', '') === 'order' ? '/production/orders/view/'.$order_id : '/shopfloor/operations');
    }

    /**
     * POST /shopfloor/operations/replan — пересобрать маршрут заказа заново.
     */
    public function action_replan()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $order_id = (int) $this->request->post('order_id');

        try
        {
            $order = Service_Erp402_Orders::instance()->get($order_id);

            if ( ! $order)
            {
                throw new Exception_Erp408_Shopfloor('Производственный заказ не найден', NULL, 'order_not_found');
            }

            $count = $this->_operations()->replan_order($order, $this->_current_user_id());

            $this->_flash('success', 'Маршрут пересобран: операций '.$count);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/operations');
    }

    /**
     * Подпись действия для сообщения мастеру.
     *
     * @param   string  $action
     * @return  string
     */
    protected function _act_label($action)
    {
        $labels = array(
            'assign'         => 'назначена',
            'start'          => 'начата',
            'pause'          => 'поставлена на паузу',
            'resume'         => 'продолжена',
            'output'         => 'выработка записана',
            'finish'         => 'завершена',
            'downtime_start' => 'простой отмечен',
            'downtime_stop'  => 'простой закончен',
            'cancel'         => 'отменена',
            'delete'         => 'удалена',
            'planned'        => 'маршрут построен',
        );

        return isset($labels[$action]) ? $labels[$action] : $action;
    }
}
