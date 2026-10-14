<?php defined('SYSPATH') or die('No direct script access.');

/**
 * База контроллеров CRM (erp221_crm).
 *
 * Меню и общие проверки доступа: менеджер работает из браузера и с телефона,
 * руководитель смотрит аналитику, демо-гость — только чтение. Отдельной
 * авторизации для мобильного клиента нет: используется сессия системы или
 * ключ доступа (erp804), как в QR-маркировке.
 *
 * @package    Erp221
 * @category   Controllers
 */
class Controller_Erp221_Main extends Controller_Base
{
    /** @var array|null Ключ доступа, если запрос пришёл с Bearer-токеном */
    protected $_key = NULL;

    /** @var string|null Сообщение о неверном ключе */
    protected $_key_error;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Продажи (CRM)';
        $this->_meta['description'] = 'Воронка сделок, клиенты, лента работы и напоминания менеджеру.';

        $this->set_menu(array(
            array('title' => 'Доска', 'href' => '/crm', 'icon' => 'fas fa-table-columns', 'acl' => 'erp221.view'),
            array('title' => 'Сделки', 'href' => '/crm/deals', 'icon' => 'fas fa-handshake', 'acl' => 'erp221.view'),
            array('title' => 'Клиенты', 'href' => '/crm/clients', 'icon' => 'fas fa-building', 'acl' => 'erp221.clients.view'),
            array('title' => 'Стадии и причины', 'href' => '/crm/stages', 'icon' => 'fas fa-diagram-project', 'acl' => 'erp221.stages.view'),
            array('title' => 'Аналитика', 'href' => '/crm/analytics', 'icon' => 'fas fa-chart-line', 'acl' => 'erp221.analytics.view'),
            array('title' => 'База знаний', 'href' => '/crm/kb', 'icon' => 'fas fa-book', 'acl' => 'erp221.kb.view'),
            array('title' => 'Лента', 'href' => '/crm/journal', 'icon' => 'fas fa-clock-rotate-left', 'acl' => 'erp221.view'),
            array('title' => 'Напоминания', 'href' => '/crm/reminders', 'icon' => 'fas fa-bell', 'acl' => 'erp221.view'),
        ));

        $this->authenticate_key();
    }

    /**
     * Право на действие или понятный отказ (страницы админки).
     *
     * @param   string  $ability
     * @param   boolean $mutating
     * @return  void
     * @throws  HTTP_Exception_403
     */
    protected function allow_or_403($ability, $mutating = FALSE)
    {
        if ($this->allows($ability, $mutating))
        {
            return;
        }

        if ($this->_key === NULL AND ! $this->_current_user)
        {
            throw new HTTP_Exception_403('Требуется вход в систему');
        }

        throw new HTTP_Exception_403('Недостаточно прав: нужно «'.$ability.'»');
    }

    /**
     * Служебное действие: сюда приводят проверки доступа в before().
     *
     * @return  void
     */
    public function action_denied()
    {
        if ($this->response->body() === '')
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }
    }

    /**
     * Разбор ключа доступа из заголовков запроса.
     *
     * @return  void
     */
    protected function authenticate_key()
    {
        $token = $this->bearer_token();

        if ($token === '' OR ! class_exists('Service_Erp804_Keys'))
        {
            return;
        }

        try
        {
            $this->_key = Service_Erp804_Keys::instance()->authenticate($token);
        }
        catch (Exception $e)
        {
            $this->_key       = NULL;
            $this->_key_error = $e->getMessage();
        }
    }

    /**
     * Токен из заголовков: Authorization: Bearer … или X-Api-Key.
     *
     * @return  string
     */
    protected function bearer_token()
    {
        $header = (string) $this->request->headers('Authorization');

        if ($header !== '' AND preg_match('/^Bearer\s+(.+)$/i', trim($header), $match))
        {
            return trim($match[1]);
        }

        return trim((string) $this->request->headers('X-Api-Key'));
    }

    /**
     * Проверка права: ключ доступа (со скоупами) или пользователь сессии.
     *
     * @param   string  $ability
     * @param   boolean $mutating
     * @return  boolean
     */
    protected function allows($ability, $mutating = FALSE)
    {
        if ($this->_key)
        {
            if ($mutating AND ! empty($this->_key['read_only']))
            {
                return FALSE;
            }

            return Service_Erp804_Keys::instance()->allows($this->_key, $ability);
        }

        if ($this->_current_user)
        {
            return $this->can($ability);
        }

        return FALSE;
    }

    /**
     * id пользователя для записи авторства: сессия или actor_id ключа.
     *
     * @return  integer|null
     */
    protected function actor()
    {
        if ($this->_current_user)
        {
            return (int) $this->_current_user->id;
        }

        return $this->_key AND ! empty($this->_key['actor_id']) ? (int) $this->_key['actor_id'] : NULL;
    }

    /**
     * Отказ в доступе для JSON API: 401 без входа, 403 при нехватке прав.
     *
     * @param   string  $ability
     * @param   boolean $mutating
     * @return  void
     */
    protected function guard_api($ability, $mutating = FALSE)
    {
        if ($this->allows($ability, $mutating))
        {
            return;
        }

        $reason  = ($this->_key === NULL AND ! $this->_current_user) ? 'unauthorized' : 'forbidden';
        $message = $reason === 'unauthorized'
            ? 'Требуется вход или ключ доступа (Authorization: Bearer …)'
            : 'Недостаточно прав: нужно «'.$ability.'»';

        if ($reason === 'unauthorized' AND $this->_key_error)
        {
            $message .= ' ('.$this->_key_error.')';
        }

        $this->_json_halt($message, $reason, $reason === 'unauthorized' ? 401 : 403);
    }

    /**
     * Тело запроса как массив (JSON или обычная форма).
     *
     * @return  array
     */
    protected function payload()
    {
        $body = (string) $this->request->body();

        if ($body !== '')
        {
            $decoded = json_decode($body, TRUE);

            if (is_array($decoded))
            {
                return $decoded;
            }
        }

        return array_merge((array) $this->request->query(), (array) $this->request->post());
    }

    /**
     * Загруженные файлы: у Request этой версии Kohana нет метода files().
     *
     * @param   string|null  $key
     * @return  array
     */
    protected function uploaded($key = NULL)
    {
        if ($key === NULL)
        {
            return (array) $_FILES;
        }

        return isset($_FILES[$key]) AND is_array($_FILES[$key]) ? $_FILES[$key] : array();
    }

    /**
     * Добавить flash-сообщение (показывается один раз на следующей странице).
     *
     * @param   string  $type  success|error|warning|info
     * @param   string  $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp221_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp221_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp221: не удалось записать flash — :error', array(
                ':error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Забрать и очистить накопленные flash-сообщения.
     *
     * @return  array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp221_flash', array());
            $session->delete('erp221_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    /**
     * Записи дашборда и карточек всегда нужны с редиректом после POST.
     *
     * @param   string  $action
     * @param   integer $id
     * @return  void
     */
    protected function back_to($action, $id = NULL)
    {
        $params = array('action' => $action);

        if ($id !== NULL)
        {
            $params['id'] = $id;
        }

        $this->redirect(Route::url('erp221_index', $params));
    }
}
