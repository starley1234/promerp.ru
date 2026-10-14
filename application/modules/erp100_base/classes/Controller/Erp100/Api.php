<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API базового модуля (erp100_base): пользователи, роли, кто я.
 *
 * Модуль отвечает за вход и права, поэтому его API — про доступ: агент или
 * внешний сервис должен уметь спросить «кто я и что мне разрешено», а
 * административные панели — получить список пользователей и ролей.
 *
 * Ответ: {"success": true, "data": ...} либо
 * {"success": false, "error": {"message": ..., "reason": ...}}.
 *
 * @package    Erp100
 * @category   Controllers
 */
class Controller_Erp100_Api extends Controller_Base
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
            // 401 и остановка: иначе действие выполнится с пустым пользователем
            $this->_json_halt('Требуется авторизация', 'unauthorized', 401);
            return;
        }
    }

    /**
     * GET /erp100/api/me — кто я: учётная запись, роли и права.
     *
     * Права собираются из config/acl.php по ролям пользователя: это тот же
     * список, по которому работает интерфейс, поэтому агент видит ровно свои
     * возможности.
     */
    public function action_me()
    {
        $user  = $this->_current_user;
        $roles = method_exists($user, 'get_role_names') ? (array) $user->get_role_names() : array();

        $this->_json_ok(array(
            'id'       => (int) $user->id,
            'username' => (string) $user->username,
            'email'    => (string) $user->email,
            'roles'    => array_values($roles),
            'rights'   => $this->rights($roles),
        ));
    }

    /**
     * GET /erp100/api/users?term=&limit=&offset=
     */
    public function action_users()
    {
        if ( ! $this->can('erp100.users'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);

            return;
        }

        $term   = trim((string) $this->_query('term', ''));
        $limit  = min(100, max(1, (int) $this->_query('limit', 25)));
        $offset = max(0, (int) $this->_query('offset', 0));

        $apply = function ($query) use ($term) {
            if ($term !== '')
            {
                $query->and_where_open()
                    ->where(DB::expr('LOWER(username)'), 'LIKE', '%'.mb_strtolower($term, 'UTF-8').'%')
                    ->or_where(DB::expr('LOWER(email)'), 'LIKE', '%'.mb_strtolower($term, 'UTF-8').'%')
                    ->and_where_close();
            }

            return $query;
        };

        $total = (int) $apply(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('users'))
            ->execute()
            ->get('total', 0);

        $rows = $apply(DB::select(
            'id', 'username', 'email', 'logins', 'last_login', 'last_ip',
            'email_confirmed', 'failed_login_attempts', 'created_at'
        ))
            ->from('users')
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $user = ORM::factory('User', (int) $row['id']);

            if ($user->loaded())
            {
                $rows[$index] = $this->present_user($user);
            }
        }

        $this->_json_ok(array('users' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset));
    }

    /**
     * GET /erp100/api/user/<id>
     */
    public function action_user()
    {
        if ( ! $this->can('erp100.users'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);

            return;
        }

        $user = ORM::factory('User', (int) $this->request->param('id'));

        if ( ! $user->loaded())
        {
            $this->_json_fail('Пользователь не найден', 'not_found', 404);

            return;
        }

        $this->_json_ok($this->present_user($user));
    }

    /** GET /erp100/api/access/<id> — effective account governance card. */
    public function action_access()
    {
        if ( ! $this->can('erp100.users'))
        {
            $this->_json_fail('Доступ запрещен','forbidden',403);
            return;
        }
        try
        {
            $this->_json_ok(Service_Erp100_Access::instance()->present((int)$this->request->param('id')));
        }
        catch(Exception $e)
        {
            $this->_json_fail($e->getMessage(),'not_found',404);
        }
    }

    /**
     * GET /erp100/api/roles
     */
    public function action_roles()
    {
        if ( ! $this->can('erp100.roles'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);

            return;
        }

        $rows = DB::select('id', 'name', 'description')
            ->from('roles')
            ->order_by('id', 'ASC')
            ->execute()
            ->as_array();

        $this->_json_ok(array('roles' => $rows));
    }

    /**
     * Учётная запись для ответа: типы приведены, пароль и токены не отдаются.
     *
     * @param   Model_User  $user
     * @return  array
     */
    protected function present_user($user)
    {
        return array(
            'id'       => (int) $user->id,
            'username' => (string) $user->username,
            'email'    => (string) $user->email,
            'roles'    => method_exists($user, 'get_role_names')
                ? array_values((array) $user->get_role_names())
                : array(),
            'logins'   => (int) $user->logins,
            'last_login' => $user->last_login ? (string) $user->last_login : NULL,
            'last_ip'  => $user->last_ip ? (string) $user->last_ip : NULL,
            'email_confirmed' => (bool) $user->email_confirmed,
            'status' => (string) ($user->status ?: 'active'),
            'access_policy' => (string) ($user->access_policy ?: 'unrestricted'),
            'created_at' => $user->created_at ? (string) $user->created_at : NULL,
        );
    }

    /**
     * Права ролей из config/acl.php.
     *
     * @param   array   $roles
     * @return  array
     */
    protected function rights(array $roles)
    {
        $acl    = Kohana::$config->load('acl')->as_array();
        $rights = array();

        foreach ($acl as $right => $allowed)
        {
            if ( ! is_array($allowed))
            {
                continue;
            }

            if (array_intersect($roles, $allowed))
            {
                $rights[] = $right;
            }
        }

        return $rights;
    }
}
