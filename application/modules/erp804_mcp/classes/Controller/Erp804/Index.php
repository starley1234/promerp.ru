<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Интерфейс MCP-сервера (erp804_mcp): обзор, инструменты, ключи, вызовы.
 *
 * Всё, что здесь видно, доступно и агенту через `/mcp` — человеческий
 * интерфейс нужен, чтобы выдавать ключи, смотреть, что делал агент, и
 * подтверждать опасные операции (выпуск продукции, отмену заказа).
 *
 * @package    Erp804
 * @category   Controllers
 */
class Controller_Erp804_Index extends Controller_Erp804_Main
{
    /**
     * GET /mcp — обзор: состояние сервера, статистика вызовов, ожидающие
     * подтверждения, последние вызовы.
     */
    public function action_index()
    {
        if ( ! $this->can('erp804_mcp.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $server = Service_Erp804_Server::instance();
        $calls  = Service_Erp804_Calls::instance();

        $this->template->content = View::factory('erp804/index/index')
            ->set('messages', $this->_flash_messages())
            ->set('info', $server->info())
            ->set('stats', $calls->stats(30))
            ->set('pending', $calls->pending(10))
            ->set('recent', $calls->search(array(), 10, 0)['rows'])
            ->set('can_approve', $this->can('erp804_mcp.approve'));
    }

    /**
     * GET /mcp/tools — каталог инструментов: что умеет агент и какие права нужны.
     */
    public function action_tools()
    {
        if ( ! $this->can('erp804_mcp.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $registry = Service_Erp804_Registry::instance();

        $this->template->content = View::factory('erp804/index/tools')
            ->set('messages', $this->_flash_messages())
            ->set('tools', $registry->catalogue())
            ->set('sources', $registry->sources())
            ->set('problems', $registry->problems());
    }

    /**
     * GET /mcp/calls — журнал вызовов; POST — подтверждение или отклонение.
     */
    public function action_calls()
    {
        if ( ! $this->can('erp804_mcp.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $calls = Service_Erp804_Calls::instance();

        if ($this->request->method() === HTTP_Request::POST)
        {
            $this->_decide($calls);

            HTTP::redirect('/mcp/calls');
        }

        $filters = array(
            'status'    => trim((string) $this->request->query('status')),
            'tool_name' => trim((string) $this->request->query('tool_name')),
            'term'      => trim((string) $this->request->query('term')),
            'mutating'  => $this->request->query('mutating'),
        );

        foreach ($filters as $key => $value)
        {
            if ($value === '' OR $value === NULL)
            {
                unset($filters[$key]);
            }
        }

        $page  = max(1, (int) $this->_query('page', 1));
        $limit = 25;

        $data = $calls->search($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp804/index/calls')
            ->set('messages', $this->_flash_messages())
            ->set('calls', $data['rows'])
            ->set('total', $data['total'])
            ->set('page', $page)
            ->set('pages', $data['pages'])
            ->set('filters', $filters)
            ->set('statuses', array('ok', 'error', 'awaiting_approval', 'approved', 'rejected', 'pending'))
            ->set('can_approve', $this->can('erp804_mcp.approve'));
    }

    /**
     * GET /mcp/keys — ключи агентов; POST — выдача, изменение, отзыв.
     */
    public function action_keys()
    {
        if ( ! $this->can('erp804_mcp.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $keys = Service_Erp804_Keys::instance();

        if ($this->request->method() === HTTP_Request::POST)
        {
            $post = $this->request->post();
            $do   = isset($post['do']) ? (string) $post['do'] : 'create';

            try
            {
                if ($do === 'create')
                {
                    $scopes = array_filter(array_map('trim', explode(',', (string) Arr::get($post, 'scopes', ''))));

                    $issued = $keys->issue(
                        (string) Arr::get($post, 'name', ''),
                        $scopes,
                        array(
                            'client'     => Arr::get($post, 'client'),
                            'read_only'  => (bool) Arr::get($post, 'read_only'),
                            'expires_at' => Arr::get($post, 'expires_at') ?: NULL,
                            'note'       => Arr::get($post, 'note'),
                            'created_by' => $this->_current_user_id(),
                        )
                    );

                    $this->_flash('success', 'Ключ «'.$issued['key']['name'].'» создан. Токен показывается один раз: '.$issued['token']);
                }
                elseif ($do === 'revoke')
                {
                    $keys->revoke((int) Arr::get($post, 'id'), $this->_current_user_id());
                    $this->_flash('success', 'Ключ отозван');
                }
                elseif ($do === 'enable')
                {
                    $keys->update((int) Arr::get($post, 'id'), array('enabled' => TRUE), $this->_current_user_id());
                    $this->_flash('success', 'Ключ включён');
                }
                elseif ($do === 'read_only')
                {
                    $read_only = (bool) Arr::get($post, 'read_only');
                    $keys->update((int) Arr::get($post, 'id'), array('read_only' => $read_only), $this->_current_user_id());
                    $this->_flash('success', $read_only ? 'Ключ переведён в режим «только чтение»' : 'Ключу разрешены изменяющие инструменты');
                }
                elseif ($do === 'delete')
                {
                    $keys->delete((int) Arr::get($post, 'id'));
                    $this->_flash('success', 'Ключ удалён (история вызовов осталась)');
                }
            }
            catch (Exception_Erp804_Mcp $e)
            {
                $this->_flash('error', $e->getMessage());
            }

            HTTP::redirect('/mcp/keys');
        }

        $this->template->content = View::factory('erp804/index/keys')
            ->set('messages', $this->_flash_messages())
            ->set('keys', $keys->search(array(), 100, 0)['rows'])
            ->set('token', $keys->last_token());
    }

    /**
     * Подтверждение или отклонение вызова агентом-человеком.
     *
     * @param   Service_Erp804_Calls    $calls
     * @return  void
     */
    protected function _decide(Service_Erp804_Calls $calls)
    {
        if ( ! $this->can('erp804_mcp.approve'))
        {
            throw new HTTP_Exception_403('Подтверждать операции может мастер или администратор');
        }

        $post = $this->request->post();
        $id   = (int) Arr::get($post, 'id');
        $do   = (string) Arr::get($post, 'do');

        try
        {
            if ($do === 'approve')
            {
                $call = $calls->approve($id, $this->_current_user_id());

                $this->_flash(
                    $call['status'] === 'ok' ? 'success' : 'error',
                    $call['status'] === 'ok'
                        ? 'Вызов #'.$id.' выполнен'
                        : 'Вызов #'.$id.' завершился ошибкой: '.$call['error']
                );
            }
            elseif ($do === 'reject')
            {
                $calls->reject($id, $this->_current_user_id(), (string) Arr::get($post, 'reason'));
                $this->_flash('warning', 'Вызов #'.$id.' отклонён');
            }
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            $this->_flash('error', 'Не удалось выполнить: '.$e->getMessage());
        }
    }
}
