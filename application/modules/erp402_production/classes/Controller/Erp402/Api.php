<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API производства (erp402_production).
 *
 * Формат ответа: {"success": true, "data": ...} либо
 * {"success": false, "error": {"message": "...", "reason": "..."}}.
 *
 * API используется виджетами, интеграциями и будущими терминалами цеха:
 * список заказов, карточка, потребность в материалах и переходы статусов
 * (утвердить план, зарезервировать материалы, выпустить продукцию).
 *
 * @package    Erp402
 * @category   Controllers
 */
class Controller_Erp402_Api extends Controller_Erp402_Main
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
     * GET /production/api/search?status=&term=&overdue=1&limit=&offset=
     */
    public function action_search()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $query = $this->request->query();

        $result = Service_Erp402_Orders::instance()->search(array(
            'status'  => isset($query['status']) ? $query['status'] : NULL,
            'term'    => isset($query['term']) ? $query['term'] : NULL,
            'overdue' => ! empty($query['overdue']),
            'open'    => ! empty($query['open']),
        ), (int) (isset($query['limit']) ? $query['limit'] : 20), (int) (isset($query['offset']) ? $query['offset'] : 0));

        $this->_json(array(
            'orders' => $result['rows'],
            'total'  => $result['total'],
            'statuses' => Model_Erp402_Order::$statuses,
        ));
    }

    /**
     * GET /production/api/get/<id|uuid>
     */
    public function action_get()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order = Service_Erp402_Orders::instance()->get($this->request->param('id'));

        if ( ! $order)
        {
            $this->_json_error('Заказ не найден', 'not_found', 404);
            return;
        }

        $items = array();

        foreach ($order->items()->find_all() as $item)
        {
            $items[] = array(
                'id'           => (int) $item->id,
                'line_no'      => (int) $item->line_no,
                'item_id'      => $item->item_id ? (int) $item->item_id : NULL,
                'name'         => (string) $item->name,
                'uom_code'     => (string) $item->uom_code,
                'qty_per_unit' => (float) $item->qty_per_unit,
                'qty_total'    => (float) $item->qty_total,
                'qty_issued'   => (float) $item->qty_issued,
                'status'       => (string) $item->status,
            );
        }

        $this->_json(array(
            'order'  => $order->as_array(),
            'progress' => $order->progress(),
            'items'  => $items,
        ));
    }

    /**
     * GET /production/api/requirements/<id> — потребность в материалах.
     */
    public function action_requirements()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $order = Service_Erp402_Orders::instance()->get($this->request->param('id'));

        if ( ! $order)
        {
            $this->_json_error('Заказ не найден', 'not_found', 404);
            return;
        }

        try
        {
            $materials = Service_Erp402_Materials::instance();

            $rows = array();

            foreach ($materials->requirements($order) as $row)
            {
                $rows[] = array(
                    'line_no'  => $row['line_no'],
                    'name'     => $row['name'],
                    'item_id'  => $row['item_id'] ?: NULL,
                    'needed'   => $row['needed'],
                    'have'     => $row['have'],
                    'total'    => $row['total'],
                    'shortage' => $row['shortage'],
                    'mapped'   => $row['mapped'],
                    'uom_code' => $row['uom_code'],
                );
            }

            $this->_json(array('requirements' => $rows, 'summary' => $materials->summary($order)));
        }
        catch (Exception $e)
        {
            $this->_json_error($e->getMessage(), 'stock_unavailable', 409);
            return;
        }
    }

    /**
     * GET /production/api/stats — сводка производства.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json(array(
            'stats'    => Service_Erp402_Orders::instance()->stats(),
            'statuses' => Model_Erp402_Order::$statuses,
        ));
    }

    /**
     * POST /production/api/transition/<plan|release|start|complete|cancel>/<id>
     *
     * Терминал цеха и внешние системы двигают заказ тем же сервисом, что и
     * веб-интерфейс: правила производства не дублируются.
     */
    public function action_transition()
    {
        $action = (string) $this->request->param('action');
        $id     = $this->request->param('id');

        $orders = Service_Erp402_Orders::instance();

        try
        {
            switch ($action)
            {
                case 'plan':
                    if ( ! $this->can('erp402_production.plan'))
                    {
                        $this->_json_error('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $order = $orders->plan($id, $this->_current_user_id());
                    break;

                case 'release':
                    if ( ! $this->can('erp402_production.plan'))
                    {
                        $this->_json_error('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $result = $orders->release($id, $this->_current_user_id());
                    $order  = $result['order'];

                    $this->_json(array(
                        'order'    => $order->as_array(),
                        'reserved' => $result['reserved'],
                        'short'    => $result['short'],
                        'unmapped' => $result['unmapped'],
                    ));
                    return;

                case 'start':
                    if ( ! $this->can('erp402_production.complete'))
                    {
                        $this->_json_error('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $order = $orders->start($id, $this->_current_user_id());
                    break;

                case 'complete':
                    if ( ! $this->can('erp402_production.complete'))
                    {
                        $this->_json_error('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $qty    = $this->request->post('qty');
                    $result = $orders->complete($id, $qty === NULL ? NULL : (float) $qty, $this->_current_user_id());

                    $this->_json(array(
                        'order'  => $result['order']->as_array(),
                        'issued' => $result['issued'],
                        'received' => $result['received'],
                    ));
                    return;

                case 'cancel':
                    if ( ! $this->can('erp402_production.plan'))
                    {
                        $this->_json_error('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $order = $orders->cancel($id, $this->request->post('reason'), $this->_current_user_id());
                    break;

                default:
                    $this->_json_error('Неизвестное действие: '.$action, 'invalid_action', 400);
                    return;
            }

            $this->_json(array('order' => $order->as_array()));
        }
        catch (Exception_Erp402_Production $e)
        {
            $this->_json_error($e->getMessage(), $e->reason(), 409);
            return;
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp402: API переход не выполнен — :error', array(':error' => $e->getMessage()));

            $this->_json_error('Внутренняя ошибка', 'internal', 500);
            return;
        }
    }

    /**
     * GET /production/api/options — справочники для форм и интеграций.
     */
    public function action_options()
    {
        if ( ! $this->can('erp402_production.view'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json(array(
            'statuses'   => Model_Erp402_Order::$statuses,
            'priorities' => Model_Erp402_Order::$priorities,
            'warehouses' => $this->_warehouses(),
            'uoms'       => $this->_units(),
        ));
    }

    /**
     * Ответ «успех».
     *
     * @param   array   $data
     * @param   int     $code
     * @return  void
     */
    protected function _json(array $data, $code = 200)
    {
        $this->response
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->status($code)
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
