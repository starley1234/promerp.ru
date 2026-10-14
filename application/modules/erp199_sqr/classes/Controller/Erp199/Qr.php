<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Терминал в браузере (erp199_sqr) — «глупый клиент, умный сервер».
 *
 * Страница не знает ничего о предметах, статусах и модулях: она показывает то,
 * что вернул `/qr/api/resolve`, и отправляет формы на `/qr/api/scan`. Поэтому
 * Android-приложение может быть тонким, а правки сценариев не требуют новой
 * версии приложения.
 *
 * Сюда же ведёт короткая ссылка из QR-кода (`/qr/<код>`) и `mobile.help` —
 * страница-объяснение для того, у кого нет приложения: он откроет её камерой
 * и увидит, что это за предмет, без установки чего-либо.
 *
 * @package    Erp199
 * @category   Controllers
 */
class Controller_Erp199_Qr extends Controller_Erp199_Main
{
    public function before()
    {
        parent::before();

        // Терминал — не страница системы: без меню и общего каркаса, но с
        // проверкой доступа. Сессия пользователя или ключ устройства — оба
        // варианта годятся (см. Controller_Erp199_Main::allows).
        $this->auto_render = FALSE;
    }

    /**
     * Страница сканирования: пустая форма или конкретная наклейка.
     *
     * @return  void
     */
    public function action_index()
    {
        $this->guard_page('erp199.scan');

        $code = (string) $this->request->param('code', $this->_query('code', ''));

        $this->response->body(View::factory('erp199/terminal')
            ->set('code', Service_Erp199_Scan::instance()->clean_code($code))
            ->set('scan_url', Route::url('erp199_api', array('action' => 'resolve')))
            ->set('scan_action_url', Route::url('erp199_api', array('action' => 'scan')))
            ->set('register_url', Route::url('erp199_api', array('action' => 'register')))
            ->set('bind_url', Route::url('erp199_api', array('action' => 'bind')))
            ->set('inventory_url', Route::url('erp199_api', array('action' => 'inventory')))
            ->set('targets_url', Route::url('erp199_api', array('action' => 'targets')))
            ->set('token', $this->_bearer_token())
            ->set('geo', (bool) Kohana::$config->load('erp199_sqr.terminal.geo'))
            ->set('unknown_hint', (string) Kohana::$config->load('erp199_sqr.terminal.unknown_hint'))
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('types', Service_Erp199_Types::instance()->all()));
    }

    /**
     * Короткая ссылка из QR-кода: /qr/<код>.
     *
     * @return  void
     */
    public function action_code()
    {
        $this->action_index();
    }

    /**
     * Справка для того, кто открыл QR обычной камерой.
     *
     * @return  void
     */
    public function action_help()
    {
        $this->response->body(View::factory('erp199/help'));
    }

    /**
     * Отказ в доступе к странице: человеку — понятная страница, а не JSON.
     *
     * @param   string  $ability
     * @return  void
     */
    protected function guard_page($ability)
    {
        if ($this->allows($ability))
        {
            return;
        }

        $this->response->status(403);
        $this->response->body(View::factory('erp199/terminal_denied'));
    }

    /**
     * Токен для встроенной страницы: если человек пришёл по сессии, страница
     * вызывает API от его имени (fetch с cookies), и ключ не нужен.
     *
     * @return  string
     */
    protected function _bearer_token()
    {
        return $this->bearer_token();
    }
}
