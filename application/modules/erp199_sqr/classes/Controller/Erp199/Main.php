<?php defined('SYSPATH') or die('No direct script access.');

/**
 * База контроллеров QR-маркировки (erp199_sqr).
 *
 * Одна общая для всех входов задача — понять, кто пришёл: человек с сессией или
 * терминал с ключом доступа. Приложение в поле работает тем же ключом, что и
 * агенты MCP (`Service_Erp804_Keys`): у ключа есть скоупы, срок жизни и признак
 * «только чтение». Отдельная система паролей для телефонов не нужна — отозвали
 * один ключ, и терминал потерял доступ.
 *
 * @package    Erp199
 * @category   Controllers
 */
class Controller_Erp199_Main extends Controller_Base
{
    /** @var array|null Ключ устройства, если запрос пришёл с Bearer-токеном */
    protected $_key = NULL;

    /** @var string|null Сообщение о неверном ключе (для понятного отказа) */
    protected $_key_error;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'QR-маркировка';
        $this->_meta['description'] = 'Наклейки, объекты и полевые сценарии: связь информационной системы с физическим миром.';

        $this->set_menu(array(
            array('title' => 'Обзор', 'href' => '/qr', 'icon' => 'fas fa-qrcode', 'acl' => 'erp199.view'),
            array('title' => 'Наклейки', 'href' => '/qr/tokens', 'icon' => 'fas fa-tags', 'acl' => 'erp199.tokens.view'),
            array('title' => 'Объекты', 'href' => '/qr/objects', 'icon' => 'fas fa-box', 'acl' => 'erp199.objects.view'),
            array('title' => 'Инвентаризация', 'href' => '/qr/inventories', 'icon' => 'fas fa-clipboard-check', 'acl' => 'erp199.inventories.view'),
            array('title' => 'Сценарии', 'href' => '/qr/scenarios', 'icon' => 'fas fa-list-check', 'acl' => 'erp199.scenarios.view'),
            array('title' => 'Типы', 'href' => '/qr/types', 'icon' => 'fas fa-cubes', 'acl' => 'erp199.types.view'),
            array('title' => 'Журнал', 'href' => '/qr/activity', 'icon' => 'fas fa-clock-rotate-left', 'acl' => 'erp199.activity.view'),
        ));

        $this->authenticate_key();
    }

    /**
     * Право на действие или понятный отказ (для страниц админки).
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
     * Ответ уже сформирован (401/403 JSON). Если действие вызвано напрямую
     * адресом `/qr/api/denied`, тела ещё нет — отвечаем честной 403.
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
     * Разбор ключа устройства из заголовков запроса.
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
            // Ключ предъявлен, но неверен: молча продолжать нельзя — иначе
            // терминал с истёкшим ключом увидит интерфейс как аноним.
            $this->_key = NULL;
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
     * Проверка права: ключ устройства (со скоупом) или пользователь сессии.
     *
     * @param   string  $ability
     * @param   boolean $mutating  изменяющее действие
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
     * Отказ в доступе для JSON API: 401 без ключа/сессии, 403 при нехватке прав.
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
            ? 'Требуется вход или ключ устройства (Authorization: Bearer …)'
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
     * Загруженные файлы: в этой версии Kohana у Request нет метода files(),
     * поэтому читаем суперглобальный массив, как это делает erp212.
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
     * Контекст запроса для журнала: ключ, адрес, устройство, координаты.
     *
     * @param   array  $payload  сюда могут прийти geo_lat/geo_lon
     * @return  array
     */
    protected function meta(array $payload = array())
    {
        return array(
            'geo_lat' => Arr::get($payload, 'geo_lat', Arr::get($payload, 'lat')),
            'geo_lon' => Arr::get($payload, 'geo_lon', Arr::get($payload, 'lon')),
            'key_id'  => $this->_key ? (int) $this->_key['id'] : NULL,
            'ip'      => Request::$client_ip,
            'device'  => mb_substr((string) $this->request->headers('User-Agent'), 0, 160) ?: NULL,
        );
    }

    /**
     * Добавить flash-сообщение: показывается один раз на следующей странице.
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
            $flash   = $session->get('erp199_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp199_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp199: не удалось записать flash — :error', array(
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
            $flash   = $session->get('erp199_flash', array());
            $session->delete('erp199_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    /**
     * @return  Service_Erp199_Scan
     */
    protected function scan_service()
    {
        return Service_Erp199_Scan::instance();
    }
}
