<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API ссылок (erp118_links): личные закладки и пункты меню.
 *
 * Зачем это модулю, у которого и так есть AJAX-действия: интерфейс модуля —
 * про перетаскивание ссылок мышкой, а API — про машинный доступ. Мобильное
 * приложение, браузерное расширение и агент (erp804) читают закладки и меню
 * одинаково, не разбирая HTML страницы `/erp118_links/manage`.
 *
 * Безопасность: данные строго личные. Ни один обработчик не принимает
 * `user_id` из запроса — владелец всегда берётся из сессии.
 *
 * Формат ответа: {"success": true, "data": ...} либо
 * {"success": false, "error": {"message": ..., "reason": ...}}.
 *
 * @package    Erp118
 * @category   Controllers
 */
class Controller_Erp118_Api extends Controller_Base
{
    /** @var bool Не оборачивать ответ в шаблон */
    public $auto_render = FALSE;

    /** @var bool Авторизацию проверяем сами, чтобы отдавать JSON вместо HTML-403 */
    protected $auth_required = FALSE;

    /** @var int Владелец закладок (из сессии, не из запроса) */
    protected $user_id;

    public function before()
    {
        parent::before();

        if ( ! $this->_current_user)
        {
            $this->_json_halt('Требуется авторизация', 'unauthorized', 401);
            return;
        }

        if ( ! $this->can('erp118.links'))
        {
            $this->_json_halt('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->user_id = (int) $this->_current_user->id;
    }

    /**
     * GET /erp118_links/api/links?group=&menu=1&term=
     *
     * @return  void
     */
    public function action_links()
    {
        $links = ORM::factory('Erp118_Links')->get_user_links($this->user_id, (bool) $this->_query('menu', FALSE));

        $group = $this->_query('group');
        $term  = mb_strtolower(trim((string) $this->_query('term', '')), 'UTF-8');
        $data  = array();

        foreach ($links as $link)
        {
            if ($group !== NULL AND (string) $link->group_name !== (string) $group)
            {
                continue;
            }

            if ($term !== '' AND mb_strpos(mb_strtolower((string) $link->title.' '.(string) $link->url, 'UTF-8'), $term) === FALSE)
            {
                continue;
            }

            $data[] = $this->present($link);
        }

        $this->_json_ok(array('links' => $data, 'total' => count($data), 'groups' => $this->groups()));
    }

    /**
     * GET /erp118_links/api/menu — только пункты пользовательского меню.
     *
     * @return  void
     */
    public function action_menu()
    {
        $data = array();

        foreach (ORM::factory('Erp118_Links')->get_user_links($this->user_id, TRUE) as $link)
        {
            $data[] = $this->present($link);
        }

        $this->_json_ok(array('menu' => $data, 'total' => count($data)));
    }

    /**
     * GET /erp118_links/api/groups — группы закладок пользователя.
     *
     * @return  void
     */
    public function action_groups()
    {
        $this->_json_ok(array('groups' => $this->groups()));
    }

    /**
     * GET /erp118_links/api/check?url=… — есть ли адрес в закладках (для кнопки «в избранное»).
     *
     * @return  void
     */
    public function action_check()
    {
        $url = trim((string) $this->_query('url', ''));

        if ($url === '')
        {
            $this->_json_fail('Не передан адрес ссылки', 'validation', 422);

            return;
        }

        $link = ORM::factory('Erp118_Links')
            ->where('user_id', '=', $this->user_id)
            ->and_where('url', '=', $url)
            ->find();

        $this->_json_ok(array('url' => $url, 'is_saved' => (bool) $link->loaded()));
    }

    /**
     * POST /erp118_links/api/toggle — добавить адрес в закладки или убрать его.
     *
     * Тело: {"url": "...", "title": "...", "icon_class": "...", "group_name": "...", "is_menu_item": 1}
     *
     * @return  void
     */
    public function action_toggle()
    {
        if (strtoupper($this->request->method()) !== Request::POST)
        {
            $this->_json_fail('Закладки меняются только методом POST', 'method_not_allowed', 405);

            return;
        }

        $url = trim((string) Arr::get($_POST, 'url', ''));

        if ($url === '')
        {
            $this->_json_fail('Не передан адрес ссылки', 'validation', 422);

            return;
        }

        if ( ! preg_match('~^(https?://|/)~i', $url))
        {
            $this->_json_fail('Адрес должен начинаться с http://, https:// или /', 'validation', 422);

            return;
        }

        $result = ORM::factory('Erp118_Links')->toggle_link($this->user_id, array(
            'url'          => $url,
            'title'        => (string) Arr::get($_POST, 'title', $url),
            'description'  => Arr::get($_POST, 'description'),
            'color'        => Arr::get($_POST, 'color'),
            'emoji'        => Arr::get($_POST, 'emoji'),
            'icon_class'   => Arr::get($_POST, 'icon_class'),
            'group_name'   => Arr::get($_POST, 'group_name'),
            'is_menu_item' => (int) Arr::get($_POST, 'is_menu_item', 0),
        ));

        $this->_json_ok(array(
            'url'    => $url,
            'action' => $result['action'],
            'saved'  => ($result['action'] === 'saved'),
            'id'     => isset($result['id']) ? (int) $result['id'] : NULL,
        ));
    }

    /**
     * Строка ссылки для ответа.
     *
     * @param   Model_Erp118_Links  $link
     * @return  array
     */
    protected function present($link)
    {
        return array(
            'id'           => (int) $link->id,
            'url'          => (string) $link->url,
            'title'        => (string) $link->title,
            'description'  => $link->description !== NULL ? (string) $link->description : NULL,
            'group_name'   => $link->group_name !== NULL ? (string) $link->group_name : NULL,
            'color'        => $link->color !== NULL ? (string) $link->color : NULL,
            'emoji'        => $link->emoji !== NULL ? (string) $link->emoji : NULL,
            'icon_class'   => $link->icon_class !== NULL ? (string) $link->icon_class : NULL,
            'is_menu_item' => (int) $link->is_menu_item === 1,
            'order'        => (int) $link->order,
        );
    }

    /**
     * Группы закладок пользователя (пустые группы отбрасываются).
     *
     * @return  array
     */
    protected function groups()
    {
        $groups = array();

        foreach (ORM::factory('Erp118_Links')->get_user_groups($this->user_id) as $name)
        {
            if (trim((string) $name) !== '')
            {
                $groups[] = (string) $name;
            }
        }

        return $groups;
    }
}
