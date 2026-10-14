<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля MCP (erp804_mcp).
 *
 * @package    Erp804
 * @category   Controllers
 */
class Controller_Erp804_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'MCP-сервер';
        $this->_meta['description'] = 'Подключение LLM-агентов к системе: инструменты, ключи, журнал вызовов, подтверждения.';
        $this->_meta['keywords']    = 'mcp, llm, агент, api, инструменты, erp';

        $this->set_menu(array(
            array(
                'title' => 'Обзор',
                'href'  => '/mcp/console',
                'icon'  => 'fas fa-robot',
                'acl'   => 'erp804_mcp.view',
            ),
            array(
                'title' => 'Инструменты',
                'href'  => '/mcp/tools',
                'icon'  => 'fas fa-tools',
                'acl'   => 'erp804_mcp.view',
            ),
            array(
                'title' => 'Вызовы',
                'href'  => '/mcp/calls',
                'icon'  => 'fas fa-list-check',
                'acl'   => 'erp804_mcp.view',
            ),
            array(
                'title' => 'Ключи',
                'href'  => '/mcp/keys',
                'icon'  => 'fas fa-key',
                'acl'   => 'erp804_mcp.manage',
            ),
        ));
    }

    /**
     * @param   string  $type     success|error|warning|info
     * @param   string  $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp804_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp804_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp804: не удалось записать flash — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp804_flash', array());
            $session->set('erp804_flash', array());

            return is_array($flash) ? $flash : array();
        }
        catch (Exception $e)
        {
            return array();
        }
    }

    /**
     * Ответ «успех» в формате {"success": true, "data": …}.
     *
     * @param   array   $data
     * @param   int     $code
     * @return  void
     */
    protected function _json(array $data, $code = 200)
    {
        $this->_json_response(array('success' => TRUE, 'data' => $data), $code);
    }

    /**
     * Ответ «ошибка»: {"success": false, "error": {"message": …}}.
     *
     * Внимание: вызов не прерывает действие — после него нужен `return;`.
     *
     * @param   string  $message
     * @param   string  $reason
     * @param   int     $code
     * @return  void
     */
    protected function _error_json($message, $reason = 'error', $code = 400)
    {
        $this->_json_response(array(
            'success' => FALSE,
            'error'   => array('message' => $message, 'reason' => $reason),
        ), $code);
    }

    /**
     * Проверка авторизации для JSON-действий: если пользователя нет, ответ
     * 401 уже подготовлен, и действию нужно немедленно вернуть управление.
     *
     * @return  bool
     */
    protected function _json_denied()
    {
        if ($this->_current_user)
        {
            return FALSE;
        }

        $this->_error_json('Требуется авторизация', 'unauthorized', 401);

        return TRUE;
    }

    /**
     * @return  int|null
     */
    protected function _current_user_id()
    {
        if (isset($this->_current_user) AND $this->_current_user)
        {
            return (int) $this->_current_user->id;
        }

        return NULL;
    }
}
