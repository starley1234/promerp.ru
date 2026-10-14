<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Browser migration console.
 *
 * Deliberately does not inherit Controller_Base and does not call Auth ORM: this
 * page must open while a migration adding columns to users is still pending.
 * Admin membership is checked with a minimal SQL query against the old core
 * columns and the signed current session.
 */
class Controller_Erp111_Migrate extends Controller
{
    protected $_token_authorized = FALSE;
    protected $_admin_authorized = FALSE;

    public function before()
    {
        parent::before();
        $this->_assert_allowed();
    }

    public function action_index()
    {
        $migrator = Service_Erp111_Migrator::instance();
        $message = NULL;
        $message_type = 'info';
        $operation_log = array();

        if ($this->request->method() === Request::POST AND ! self::may_execute($this->request->method(), $this->request->post('operation')))
            throw new HTTP_Exception_400('Неизвестная операция');

        if (self::may_execute($this->request->method(), $this->request->post('operation')))
        {
            if ( ! Security::check((string)$this->request->post('csrf_token')))
                throw new HTTP_Exception_403('Страница устарела. Обновите её и повторите действие.');

            try
            {
                // Группы независимы: ошибка optional MDM не должна блокировать
                // обновление основной ERP-базы. Default всегда идёт первой.
                $before = $migrator->pending();
                $groups = array_values(array_unique(array_column($before, 'db_group')));
                usort($groups, function($a,$b) { return ($a === 'default' ? 0 : 1) <=> ($b === 'default' ? 0 : 1); });
                $applied = 0; $failed = array();
                foreach ($groups as $group)
                {
                    $operation_log[] = array('level'=>'info','message'=>'База '.$group.': начало');
                    $result = $migrator->migrate(array('db_group'=>$group), function($text,$level) use (&$operation_log,$group) {
                        $operation_log[] = array('level'=>$level,'message'=>'['.$group.'] '.$text);
                    });
                    $applied += count($result['applied']);
                    foreach ($result['errors'] as $class=>$error) $failed[$class]=$error;
                }
                if ($failed)
                {
                    $message = 'Применено миграций: '.$applied.'. Не выполнено: '.count($failed).'. Подробная причина показана ниже.';
                    $message_type = $applied ? 'warning' : 'error';
                }
                else
                {
                    $message = $applied
                        ? 'Миграции применены: '.$applied.'. Теперь можно вернуться в систему.'
                        : 'Новых миграций нет — схема уже актуальна.';
                    $message_type = 'success';
                }
            }
            catch (Exception $e)
            {
                $message = 'Миграции не выполнены: '.$e->getMessage();
                $message_type = 'error';
                $operation_log[] = array('level'=>'error','message'=>$e->getMessage());
            }
        }
        elseif ($this->request->query('run'))
        {
            $message = 'Запуск через адрес ?run=1 отключён. Нажмите кнопку «Применить ожидающие миграции» ниже.';
            $message_type = 'warning';
        }

        $inventory = $migrator->inventory();
        $pending = array_values(array_filter($inventory, function($row) { return ! $row['applied']; }));
        $database_config = Kohana::$config->load('database');
        $default_name = $database_config->get('default')['connection']['database'];
        $mdm_name = $database_config->get('mdm')['connection']['database'];
        $configuration_warning = ($default_name === $mdm_name)
            ? 'Основная группа и MDM указывают на одну базу «'.$default_name.'». MDM создаёт собственную таблицу users и не может быть установлен туда же. Укажите отдельную DB_MDM_NAME (например, promerp_mdm). Миграции основной базы всё равно будут применены.'
            : NULL;
        $html = View::factory('erp111/migrate/index')
            ->set('inventory', $inventory)->set('pending', $pending)
            ->set('orphans', $migrator->orphans())->set('message', $message)
            ->set('message_type', $message_type)->set('operation_log',$operation_log)->set('configuration_warning',$configuration_warning)->set('csrf_token', Security::token())
            ->set('action_url', $this->_token_authorized ? '/migrate_pg?token='.urlencode((string)$this->request->query('token')) : '/migrate_pg');
        $this->response->headers('Content-Type','text/html; charset=utf-8')->body((string)$html);
    }

    /** Pure guard used by UI and tests: GET can never change the schema. */
    public static function may_execute($method, $operation)
    {
        return strtoupper((string)$method) === Request::POST AND $operation === 'run';
    }

    protected function _assert_allowed()
    {
        $expected = getenv('MIGRATIONS_TOKEN');
        $supplied = (string)$this->request->query('token');
        $this->_token_authorized = Kohana::$environment !== Kohana::PRODUCTION
            AND $expected !== FALSE AND $expected !== '' AND hash_equals($expected, $supplied);
        $this->_admin_authorized = $this->_session_admin();

        if ( ! $this->_admin_authorized AND ! $this->_token_authorized)
            throw new HTTP_Exception_403('Запуск миграций доступен только администратору.');
    }

    /**
     * Authenticate against tables that exist since core migration 001.
     * Never instantiate Model_User here: its declared columns may be newer than
     * the production users table — exactly why this page is being opened.
     */
    protected function _session_admin()
    {
        $key = Kohana::$config->load('auth.session_key');
        $stored = Session::instance()->get($key);
        if ( ! is_object($stored)) return FALSE;

        try { $user_id = (int)$stored->id; }
        catch (Exception $e) { return FALSE; }
        if ($user_id <= 0) return FALSE;

        try
        {
            $row = DB::query(Database::SELECT, "SELECT u.id
                FROM users u JOIN roles_users ru ON ru.user_id=u.id JOIN roles r ON r.id=ru.role_id
                WHERE u.id=:id AND r.name='admin' LIMIT 1")
                ->param(':id',$user_id)->execute()->current();
            if ( ! $row) return FALSE;

            // The status column itself may be the pending migration. Enforce it
            // only when it already exists; before that, legacy accounts are active.
            $has_status = DB::query(Database::SELECT, "SELECT 1 ok FROM information_schema.columns
                WHERE table_schema=current_schema() AND table_name='users' AND column_name='status'")
                ->execute()->current();
            if ($has_status)
            {
                $status = DB::select('status')->from('users')->where('id','=',$user_id)->execute()->get('status');
                if ($status !== 'active') return FALSE;
            }
            return TRUE;
        }
        catch (Exception $e) { return FALSE; }
    }
}
