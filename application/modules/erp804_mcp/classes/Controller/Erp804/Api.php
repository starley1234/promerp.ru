<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API управления MCP-сервером (erp804_mcp).
 *
 * Это API для людей и внешних консолей: посмотреть инструменты и вызовы,
 * выдать и отозвать ключ, подтвердить или отклонить операцию агента.
 * Сами агенты работают через `/mcp` (JSON-RPC) — там свои права и свой
 * журнал, поэтому смешивать транспорты нельзя.
 *
 * Формат ответа: `{"success": true, "data": …}` либо
 * `{"success": false, "error": {"message": …, "reason": …}}`.
 *
 * @package    Erp804
 * @category   Controllers
 */
class Controller_Erp804_Api extends Controller_Erp804_Main
{
    /**
     * @var bool
     */
    public $auto_render = FALSE;

    /**
     * @var bool Авторизацию проверяем сами, чтобы отвечать JSON
     */
    protected $auth_required = FALSE;

    public function before()
    {
        parent::before();

        if ( ! $this->_current_user)
        {
            $this->_error_json('Требуется авторизация', 'unauthorized', 401);
        }
    }

    /**
     * GET /mcp/api/info — состояние сервера: инструменты, модули, замечания.
     */
    public function action_info()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.view')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $this->_json(Service_Erp804_Server::instance()->info());
    }

    /**
     * GET /mcp/api/tools — каталог инструментов.
     */
    public function action_tools()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.view')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $registry = Service_Erp804_Registry::instance();

        $this->_json(array(
            'tools'    => $registry->catalogue(),
            'modules'  => $registry->sources(),
            'problems' => $registry->problems(),
        ));
    }

    /**
     * GET /mcp/api/calls?status=&tool_name=&limit=&page=
     */
    public function action_calls()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.view')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $filters = array(
            'status'    => trim((string) $this->request->query('status')),
            'tool_name' => trim((string) $this->request->query('tool_name')),
            'term'      => trim((string) $this->request->query('term')),
        );

        foreach ($filters as $key => $value)
        {
            if ($value === '')
            {
                unset($filters[$key]);
            }
        }

        $limit  = min(100, max(1, (int) $this->_query('limit', 25)));
        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp804_Calls::instance()->search($filters, $limit, ($page - 1) * $limit);

        $this->_json(array(
            'calls'  => $result['rows'],
            'total'  => $result['total'],
            'page'   => $page,
            'pages'  => $result['pages'],
            'stats'  => Service_Erp804_Calls::instance()->stats(30),
        ));
    }

    /**
     * GET /mcp/api/call/<id|uuid>
     */
    public function action_call()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.view')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $call = Service_Erp804_Calls::instance()->find($this->request->param('id'));

        if ( ! $call)
        {
            $this->_error_json('Вызов не найден', 'not_found', 404);

            return;
        }

        $this->_json(array('call' => $call));
    }

    /**
     * GET /mcp/api/keys — список ключей агентов.
     */
    public function action_keys()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.manage')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $result = Service_Erp804_Keys::instance()->search(array(
            'term' => trim((string) $this->request->query('term')),
        ), min(100, max(1, (int) $this->_query('limit', 50))), 0);

        $this->_json($result);
    }

    /**
     * POST /mcp/api/key — выдать ключ. Токен возвращается один раз.
     */
    public function action_key()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.manage')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $post = $this->request->post();

        try
        {
            // Arr::get, а не $post['scopes']: действие может быть вызвано
            // внутренним запросом без тела, и «Undefined array key» превратился
            // бы в 500 вместо понятного отказа сервиса (см. B16).
            $scopes = Arr::get($post, 'scopes', array());

            if (is_string($scopes))
            {
                $scopes = array_filter(array_map('trim', explode(',', $scopes)));
            }

            $issued = Service_Erp804_Keys::instance()->issue(
                isset($post['name']) ? (string) $post['name'] : '',
                (array) $scopes,
                array(
                    'client'     => isset($post['client']) ? $post['client'] : NULL,
                    'read_only'  => ! empty($post['read_only']),
                    'expires_at' => ! empty($post['expires_at']) ? $post['expires_at'] : NULL,
                    'note'       => isset($post['note']) ? $post['note'] : NULL,
                    'created_by' => $this->_current_user_id(),
                )
            );

            $this->_json(array(
                'token' => $issued['token'],
                'key'   => $issued['key'],
                'hint'  => 'Токен показывается один раз — сохраните его у клиента MCP',
            ));
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_error_json($e->getMessage(), $e->reason(), 400);

            return;
        }
    }

    /**
     * POST /mcp/api/key_update — включить, отозвать, изменить права, удалить.
     */
    public function action_key_update()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.manage')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        $post = $this->request->post();
        $keys = Service_Erp804_Keys::instance();

        try
        {
            $do = isset($post['do']) ? (string) $post['do'] : 'update';

            if ($do === 'delete')
            {
                $keys->delete((int) Arr::get($post, 'id'));

                $this->_json(array('deleted' => TRUE));
            }

            if ($do === 'revoke')
            {
                $this->_json(array('key' => $keys->revoke((int) Arr::get($post, 'id'), $this->_current_user_id())));
            }

            $data = array();

            foreach (array('scopes', 'read_only', 'enabled', 'expires_at', 'note') as $field)
            {
                if (array_key_exists($field, $post))
                {
                    $data[$field] = $post[$field];
                }
            }

            if (isset($data['scopes']) AND is_string($data['scopes']))
            {
                $data['scopes'] = array_filter(array_map('trim', explode(',', $data['scopes'])));
            }

            $this->_json(array('key' => $keys->update((int) Arr::get($post, 'id'), $data, $this->_current_user_id())));
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_error_json($e->getMessage(), $e->reason(), 400);

            return;
        }
    }

    /**
     * POST /mcp/api/approve/<id> — подтвердить операцию агента.
     */
    public function action_approve()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.approve')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        try
        {
            $call = Service_Erp804_Calls::instance()->approve((int) $this->request->param('id'), $this->_current_user_id());

            $this->_json(array('call' => $call));
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_error_json($e->getMessage(), $e->reason(), 400);

            return;
        }
    }

    /**
     * POST /mcp/api/reject/<id> — отклонить операцию агента.
     */
    public function action_reject()
    {
        if ($this->_json_denied()) { return; }

        if ( ! $this->can('erp804_mcp.approve')) { $this->_error_json('Доступ запрещён', 'forbidden', 403); return; }

        try
        {
            $call = Service_Erp804_Calls::instance()->reject(
                (int) $this->request->param('id'),
                $this->_current_user_id(),
                (string) Arr::get($this->request->post(), 'reason')
            );

            $this->_json(array('call' => $call));
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_error_json($e->getMessage(), $e->reason(), 400);

            return;
        }
    }

}
