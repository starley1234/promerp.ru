<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API цеха (erp408_shopfloor).
 *
 * Формат ответа: {"success": true, "data": ...} либо
 * {"success": false, "error": {"message": "...", "reason": "..."}}.
 *
 * Через API работает всё, что делает интерфейс: терминал на планшете, обмен с
 * внешней системой и инструменты MCP (erp804). Мутирующие действия принимают
 * только POST — «начать/завершить операцию» не должно происходить по ссылке.
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Api extends Controller_Erp408_Main
{
    /** @var bool Не оборачивать ответ в шаблон */
    public $auto_render = FALSE;

    /** @var bool Авторизацию проверяем сами, чтобы отдавать JSON вместо HTML-403 */
    protected $auth_required = FALSE;

    public function before()
    {
        parent::before();

        if ( ! $this->_current_user)
        {
            // Ответ 401 + остановка: Konine вызовет действие даже после
            // before(), если не подменить имя действия (см. _json_halt).
            $this->_json_halt('Требуется авторизация', 'unauthorized', 401);

            return;
        }
    }

    /**
     * GET /shopfloor/api/stats?days=&center_id=
     */
    public function action_stats()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json(array(
            'stats'    => $this->_operations()->stats((int) $this->_query('days', 7), (int) $this->_query('center_id', 0) ?: NULL),
            'centers'  => $this->_centers()->stats(),
            'shift'    => $this->_shifts()->current(),
            'statuses' => Model_Erp408_Operation::$statuses,
        ));
    }

    /**
     * GET /shopfloor/api/board?day=&center_id=
     */
    public function action_board()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json($this->_operations()->board($this->_query('day'), (int) $this->_query('center_id', 0) ?: NULL));
    }

    /**
     * GET /shopfloor/api/operations?status=&center_id=&day=&term=&open_only=&limit=&offset=
     */
    public function action_operations()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $query = $this->request->query();

        $result = $this->_operations()->search(array(
            'status'    => isset($query['status']) ? $query['status'] : NULL,
            'center_id' => isset($query['center_id']) ? $query['center_id'] : NULL,
            'day'       => isset($query['day']) ? $query['day'] : NULL,
            'term'      => isset($query['term']) ? $query['term'] : NULL,
            'open_only' => ! empty($query['open_only']),
        ), (int) $this->_query('limit', 25), (int) $this->_query('offset', 0));

        $result['statuses'] = Model_Erp408_Operation::$statuses;

        $this->_json($result);
    }

    /**
     * GET /shopfloor/api/operation/<id|uuid>
     */
    public function action_operation()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $operation = $this->_operations()->get($this->request->param('id'));

        if ( ! $operation)
        {
            $this->_json_error('Операция не найдена', 'not_found', 404);
            return;
        }

        $this->_json(array(
            'operation' => $operation,
            'worklogs'  => $this->_operations()->worklogs($operation['id'], (int) $this->_query('logs', 50)),
            'actions'   => $this->_operations()->allowed_actions($operation),
        ));
    }

    /**
     * GET /shopfloor/api/terminal?center_id=&day=
     */
    public function action_terminal()
    {
        if ( ! $this->can('erp408.terminal'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json(array(
            'tasks'   => $this->_operations()->terminal_tasks((int) $this->_query('center_id', 0) ?: NULL, $this->_query('day')),
            'reasons' => Model_Erp408_Operation::$downtime_reasons,
            'shift'   => $this->_shifts()->current(),
        ));
    }

    /**
     * GET /shopfloor/api/queue — заказы, по которым цех ещё не получил работу.
     */
    public function action_queue()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json(array('orders' => $this->_operations()->orders_without_route((int) $this->_query('limit', 20))));
    }

    /**
     * GET /shopfloor/api/centers?term=&kind=&active=
     */
    public function action_centers()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $query = $this->request->query();

        $this->_json($this->_centers()->search(array(
            'term'   => isset($query['term']) ? $query['term'] : NULL,
            'kind'   => isset($query['kind']) ? $query['kind'] : NULL,
            'active' => isset($query['active']) ? $query['active'] : NULL,
        ), (int) $this->_query('limit', 50), (int) $this->_query('offset', 0)));
    }

    /**
     * GET /shopfloor/api/shifts?from=&to=&center_id=
     */
    public function action_shifts()
    {
        if ( ! $this->can('erp408.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $from = Erp::day($this->_query('from', date('Y-m-01')));
        $to   = Erp::day($this->_query('to', date('Y-m-t')));

        $this->_json(array(
            'shifts'   => $this->_shifts()->search(array(), 50, 0)['rows'],
            'calendar' => $this->_shifts()->calendar($from, $to, (int) $this->_query('center_id', 0) ?: NULL),
            'from'     => $from,
            'to'       => $to,
        ));
    }

    /**
     * POST /shopfloor/api/plan — маршрут заказа (order_id или order_uuid).
     */
    public function action_plan()
    {
        if ( ! $this->can('erp408.manage'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->_json_error('Маршрут строится методом POST', 'method_not_allowed', 405);
            return;
        }

        $post = $this->request->post();

        if ( ! class_exists('Service_Erp402_Orders'))
        {
            $this->_json_error('Модуль производства не подключён', 'no_production_module', 409);
            return;
        }

        $order = Service_Erp402_Orders::instance()->get(isset($post['order_id']) ? $post['order_id'] : NULL);

        if ( ! $order)
        {
            $this->_json_error('Производственный заказ не найден', 'order_not_found', 404);
            return;
        }

        try
        {
            $count = $this->_operations()->plan_order($order, $this->_current_user_id());

            $this->_json(array(
                'order_id'   => (int) $order->id,
                'order_number' => (string) $order->number,
                'operations' => $count,
                'route'      => $this->_operations()->for_order($order->id),
            ));
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_json_error($e->getMessage(), $e->reason(), $this->_http_code($e->reason()));
            return;
        }
    }

    /**
     * POST /shopfloor/api/act — переход по операции.
     *
     * Тело: id (или uuid), action (assign|start|pause|resume|output|finish|
     * cancel|delete|downtime_start|downtime_stop), остальные поля — контекст
     * (qty_ok, qty_scrap, comment, reason, work_center_id, shift_id, day).
     */
    public function action_act()
    {
        if ($this->request->method() !== Request::POST)
        {
            $this->_json_error('Действия выполняются методом POST', 'method_not_allowed', 405);
            return;
        }

        $post   = $this->request->post();
        $action = isset($post['action']) ? (string) $post['action'] : '';
        $id     = isset($post['id']) ? $post['id'] : NULL;

        $rights = array(
            'assign'         => 'erp408.manage',
            'cancel'         => 'erp408.manage',
            'delete'         => 'erp408.manage',
        );

        if ( ! $this->can(isset($rights[$action]) ? $rights[$action] : 'erp408.terminal'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $operation = $this->_operations()->act($id, $action, $post, $this->_current_user_id());

            if ( ! $operation)
            {
                $this->_json_error('Операция не найдена', 'not_found', 404);
                return;
            }

            $this->_json(array(
                'operation' => $operation,
                'actions'   => $this->_operations()->allowed_actions($operation),
            ));
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_json_error($e->getMessage(), $e->reason(), $this->_http_code($e->reason()));
            return;
        }
    }

    /**
     * Код ответа по причине ошибки: клиенту важно отличать «не нашли» от
     * «нельзя в этом статусе».
     *
     * @param   string  $reason
     * @return  int
     */
    protected function _http_code($reason)
    {
        $map = array(
            'order_not_found'   => 404,
            'no_work_center'    => 404,
            'invalid_transition'=> 409,
            'not_assigned'      => 409,
            'nothing_to_do'     => 409,
            'not_working_day'   => 409,
            'no_route'          => 422,
            'qty_invalid'       => 422,
        );

        return isset($map[$reason]) ? $map[$reason] : 400;
    }

    /**
     * Успешный ответ.
     *
     * @param   mixed   $data
     * @return  void
     */
    protected function _json($data)
    {
        $this->response
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->status(200)
            ->body(json_encode(array('success' => TRUE, 'data' => $data), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ответ «ошибка».
     *
     * @param   string  $message
     * @param   string  $reason
     * @param   int     $code
     * @return  void
     */
    protected function _json_error($message, $reason = 'error', $code = 400)
    {
        $this->response
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->status($code)
            ->body(json_encode(array(
                'success' => FALSE,
                'error'   => array('message' => $message, 'reason' => $reason),
            ), JSON_UNESCAPED_UNICODE));
    }
}
