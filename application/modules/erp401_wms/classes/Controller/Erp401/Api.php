<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API складского учёта (erp401_wms).
 *
 * Используется виджетами, внешними интеграциями и другими модулями.
 * Формат ответа: {"success": true, "data": ...} или
 * {"success": false, "error": {"message": "...", "reason": "..."}}.
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Api extends Controller_Erp401_Main
{
    /**
     * @var bool Не оборачивать ответ в шаблон
     */
    public $auto_render = FALSE;

    /**
     * @var bool Авторизацию проверяем сами, чтобы отдавать JSON вместо HTML-403
     */
    protected $auth_required = FALSE;

    public function before()
    {
        parent::before();

        // API не требует авторизации шаблона, но требует залогиненного пользователя
        if ( ! $this->_current_user)
        {
            $this->_json_error('Требуется авторизация', 'unauthorized', 401);
            return;
        }
    }

    /**
     * GET /warehouse/api/balances?warehouse_id=&item_id=&low_only=1
     */
    public function action_balances()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'warehouse_id' => $this->request->query('warehouse_id'),
            'item_id'      => $this->request->query('item_id'),
            'low_only'     => (bool) $this->request->query('low_only'),
        );

        $limit  = (int) $this->_query('limit', 50);
        $offset = (int) $this->_query('offset', 0);

        $result = Service_Erp401_Stock::instance()->balances($filters, $limit ?: 50, $offset);

        $this->_json(array(
            'rows'  => array_map(array($this, '_row'), $result['rows']),
            'total' => $result['total'],
        ));
    }

    /**
     * GET /warehouse/api/movements?type=&warehouse_id=&item_id=&limit=
     */
    public function action_movements()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'type'         => $this->request->query('type'),
            'warehouse_id' => $this->request->query('warehouse_id'),
            'item_id'      => $this->request->query('item_id'),
            'date_from'    => $this->request->query('date_from'),
            'date_to'      => $this->request->query('date_to'),
        );

        $limit  = (int) $this->_query('limit', 50);
        $offset = (int) $this->_query('offset', 0);

        $result = Service_Erp401_Stock::instance()->movements($filters, $limit ?: 50, $offset);

        $this->_json(array(
            'rows'  => array_map(array($this, '_row'), $result['rows']),
            'total' => $result['total'],
        ));
    }

    /**
     * GET /warehouse/api/items?q= — поиск номенклатуры в реестре erp227
     */
    public function action_items()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $term   = (string) $this->request->query('q');
        $items  = Service_Erp401_Stock::instance()->search_items($term, 20);

        $this->_json(array('items' => $items));
    }

    /**
     * GET /warehouse/api/stock?item_id=1 — остаток позиции по всем складам
     */
    public function action_stock()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $item_id = (int) $this->request->query('item_id');

        if ($item_id <= 0)
        {
            $this->_json_error('Не указан item_id', 'invalid_request', 400);
            return;
        }

        $stock = Service_Erp401_Stock::instance();
        $rows  = $stock->balances(array('item_id' => $item_id), 100, 0);

        $this->_json(array(
            'item'     => $stock->item($item_id),
            'total'    => $stock->total_qty($item_id),
            'per_warehouse' => array_map(array($this, '_row'), $rows['rows']),
        ));
    }

    /**
     * POST /warehouse/api/receive — приход на склад (для интеграций)
     */
    public function action_receive()
    {
        $this->_post_operation('receive', 'erp401_wms.manage_movements');
    }

    /**
     * POST /warehouse/api/issue — расход со склада (для интеграций)
     */
    public function action_issue()
    {
        $this->_post_operation('issue', 'erp401_wms.manage_movements');
    }

    /**
     * POST /warehouse/api/transfer — перемещение между складами
     */
    public function action_transfer()
    {
        $this->_post_operation('transfer', 'erp401_wms.manage_movements');
    }

    /**
     * POST /warehouse/api/reserve — резерв свободного остатка
     */
    public function action_reserve()
    {
        if ( ! $this->can('erp401_wms.reserve'))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post() ? $this->request->post() : array();

        try
        {
            $reservation = Service_Erp401_Stock::instance()->reserve(
                (int) Arr::get($post, 'warehouse_id'),
                (int) Arr::get($post, 'item_id'),
                str_replace(',', '.', (string) Arr::get($post, 'qty', '0')),
                array(
                    'ref_type'   => Arr::get($post, 'ref_type'),
                    'ref_id'     => Arr::get($post, 'ref_id'),
                    'expires_at' => Arr::get($post, 'expires_at'),
                )
            );

            $this->_json(array('reservation' => $reservation->as_array()));
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_json_error($e->getMessage(), $e->reason(), 422);
            return;
        }
    }

    /**
     * Универсальный обработчик операций приход/расход/перемещение.
     *
     * @param string $operation
     * @param string $ability
     */
    protected function _post_operation($operation, $ability)
    {
        if ( ! $this->can($ability))
        {
            $this->_json_error('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post() ? $this->request->post() : array();

        $opt = array(
            'uom_code' => Arr::get($post, 'uom_code', 'pcs'),
            'doc_type' => Arr::get($post, 'doc_type', 'api'),
            'doc_id'   => Arr::get($post, 'doc_id'),
            'note'     => Arr::get($post, 'note'),
        );

        $stock = Service_Erp401_Stock::instance();

        try
        {
            switch ($operation)
            {
                case 'receive':
                    $movement = $stock->receive(
                        (int) Arr::get($post, 'warehouse_id'),
                        (int) Arr::get($post, 'item_id'),
                        str_replace(',', '.', (string) Arr::get($post, 'qty', '0')),
                        $opt
                    );
                    break;

                case 'issue':
                    $movement = $stock->issue(
                        (int) Arr::get($post, 'warehouse_id'),
                        (int) Arr::get($post, 'item_id'),
                        str_replace(',', '.', (string) Arr::get($post, 'qty', '0')),
                        $opt
                    );
                    break;

                default:
                    $movement = $stock->transfer(
                        (int) Arr::get($post, 'warehouse_id'),
                        (int) Arr::get($post, 'warehouse_to_id'),
                        (int) Arr::get($post, 'item_id'),
                        str_replace(',', '.', (string) Arr::get($post, 'qty', '0')),
                        $opt
                    );
            }

            $this->_json(array('movement' => $movement->as_array()));
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_json_error($e->getMessage(), $e->reason(), 422);
            return;
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp401 API: :error', array(':error' => $e->getMessage()));

            $this->_json_error('Внутренняя ошибка проведения операции', 'internal_error', 500);
            return;
        }
    }

    /**
     * Приведение строки результата к JSON-безопасному виду.
     *
     * @param  array $row
     * @return array
     */
    protected function _row(array $row)
    {
        foreach (array('qty', 'reserved', 'min_qty', 'balance_before', 'balance_after') as $key)
        {
            if (isset($row[$key]))
            {
                $row[$key] = (float) $row[$key];
            }
        }

        return $row;
    }

    /**
     * Успешный ответ.
     *
     * @param array $data
     */
    protected function _json(array $data)
    {
        $this->response
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(array('success' => TRUE, 'data' => $data), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ответ с ошибкой.
     *
     * @param string $message
     * @param string $reason
     * @param int    $code
     */
    protected function _json_error($message, $reason = 'error', $code = 400)
    {
        $this->auto_render = FALSE;

        throw new HTTP_Exception_Erp401_Json($message, $reason, $code);
    }
}
