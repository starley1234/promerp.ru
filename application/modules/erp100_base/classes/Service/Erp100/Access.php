<?php defined('SYSPATH') or die('No direct script access.');

/** Central account lifecycle, role and data-scope policy service. */
class Service_Erp100_Access
{
    protected static $_instance;
    public static function instance() { return self::$_instance ?: (self::$_instance = new self); }
    protected function __construct() {}

    public function user($id)
    {
        $user = ORM::factory('User', (int) $id);
        if ( ! $user->loaded()) throw new Kohana_Exception('Учётная запись не найдена');
        return $user;
    }

    public function present($id)
    {
        $user = $this->user($id);
        return array(
            'id'=>(int)$user->id, 'username'=>$user->username, 'email'=>$user->email,
            'status'=>$user->status ?: 'active', 'blocked_at'=>$user->blocked_at,
            'blocked_reason'=>$user->blocked_reason, 'access_policy'=>$user->access_policy ?: 'unrestricted',
            'session_version'=>(int)$user->session_version,
            'roles'=>array_values((array)$user->get_role_names()), 'scopes'=>$this->scopes($user->id),
            'remember_sessions'=>(int)DB::select(array(DB::expr('COUNT(*)'),'n'))->from('user_tokens')->where('user_id','=',$user->id)->execute()->get('n',0),
        );
    }

    public function set_roles($user_id, array $role_names, $actor_id)
    {
        $user = $this->user($user_id);
        $role_names = array_values(array_unique(array_filter(array_map('strval', $role_names))));
        if ( ! in_array('login', $role_names, TRUE)) $role_names[] = 'login';
        $roles = ORM::factory('Role')->where('name','IN',$role_names)->find_all();
        if (count($roles) !== count($role_names)) throw new Kohana_Exception('Передана неизвестная роль');
        $before = array_values((array)$user->get_role_names());
        DB::query(NULL,'BEGIN')->execute();
        try {
            DB::query(Database::SELECT,'SELECT pg_advisory_xact_lock(100001)')->execute();
            if (in_array('admin',$before,TRUE) AND ! in_array('admin',$role_names,TRUE) AND $this->active_admin_count() <= 1)
                throw new Kohana_Exception('Нельзя снять роль у последнего активного администратора');
            DB::delete('roles_users')->where('user_id','=',(int)$user->id)->execute();
            foreach($roles as $role) DB::insert('roles_users',array('user_id','role_id'))->values(array($user->id,$role->id))->execute();
            DB::query(NULL,'COMMIT')->execute();
        } catch(Exception $e) { DB::query(NULL,'ROLLBACK')->execute(); throw $e; }
        $this->publish('erp100.user.roles_changed',array('user_id'=>(int)$user->id,'before'=>$before,'after'=>$role_names,'actor_id'=>$actor_id));
        return $this->present($user->id);
    }

    public function set_status($user_id, $status, $reason, $actor_id)
    {
        $user = $this->user($user_id); $status=(string)$status;
        if ( ! in_array($status,array('active','blocked'),TRUE)) throw new Kohana_Exception('Неизвестный статус учётной записи');
        if ($status==='blocked' AND (int)$user->id===(int)$actor_id) throw new Kohana_Exception('Нельзя заблокировать собственную учётную запись');
        if (($user->status ?: 'active') === $status) return $user;
        DB::query(NULL,'BEGIN')->execute();
        try {
            DB::query(Database::SELECT,'SELECT pg_advisory_xact_lock(100001)')->execute();
            if ($status==='blocked' AND in_array('admin',(array)$user->get_role_names(),TRUE) AND $this->active_admin_count()<=1)
                throw new Kohana_Exception('Нельзя заблокировать последнего активного администратора');
            $user->status=$status;
            $user->blocked_at=$status==='blocked'?date('Y-m-d H:i:s'):NULL;
            $user->blocked_reason=$status==='blocked'?trim((string)$reason):NULL;
            if ($status==='blocked') $user->session_version=(int)$user->session_version+1;
            $user->save();
            if ($status==='blocked') DB::delete('user_tokens')->where('user_id','=',$user->id)->execute();
            DB::query(NULL,'COMMIT')->execute();
        } catch(Exception $e) { DB::query(NULL,'ROLLBACK')->execute(); throw $e; }
        $this->publish('erp100.user.'.($status==='blocked'?'blocked':'unblocked'),array('user_id'=>(int)$user->id,'reason'=>$reason,'actor_id'=>$actor_id));
        return $user;
    }

    public function revoke_sessions($user_id, $actor_id)
    {
        $user=$this->user($user_id); $user->session_version=(int)$user->session_version+1; $user->save();
        DB::delete('user_tokens')->where('user_id','=',$user->id)->execute();
        $this->publish('erp100.user.sessions_revoked',array('user_id'=>(int)$user->id,'actor_id'=>$actor_id));
        return $user;
    }

    public function set_policy($user_id, $policy, $actor_id)
    {
        $user=$this->user($user_id);
        if ( ! in_array($policy,array('unrestricted','restricted'),TRUE)) throw new Kohana_Exception('Неизвестная политика области доступа');
        if ($policy==='restricted' AND in_array('admin',(array)$user->get_role_names(),TRUE)) throw new Kohana_Exception('Администратор должен иметь доступ без ограничений');
        $user->access_policy=$policy; $user->save();
        $this->publish('erp100.user.access_policy_changed',array('user_id'=>(int)$user->id,'policy'=>$policy,'actor_id'=>$actor_id));
        return $user;
    }

    public function scopes($user_id, $active_only=FALSE)
    {
        $where=$active_only?" AND valid_from<=CURRENT_DATE AND (valid_to IS NULL OR valid_to>=CURRENT_DATE)":'';
        return DB::query(Database::SELECT,"SELECT * FROM erp_100_user_scopes WHERE user_id=:id$where ORDER BY resource_type, resource_id")
            ->param(':id',(int)$user_id)->execute()->as_array();
    }

    public function save_scope($user_id, array $data, $actor_id)
    {
        $this->user($user_id); $type=(string)Arr::get($data,'resource_type'); $resource_id=(int)Arr::get($data,'resource_id');
        if ( ! in_array($type,array('department','warehouse','work_center'),TRUE) OR $resource_id<=0) throw new Kohana_Exception('Некорректная область доступа');
        $this->assert_resource($type,$resource_id);
        $values=array((int)$user_id,$type,$resource_id,!empty($data['include_descendants'])?1:0,
            Arr::get($data,'valid_from')?:$this->database_today(),Arr::get($data,'valid_to')?:NULL,$actor_id?(int)$actor_id:NULL);
        $result=DB::insert('erp_100_user_scopes',array('user_id','resource_type','resource_id','include_descendants','valid_from','valid_to','created_by'))->values($values)->execute();
        $id=(int)$result[0];
        $this->publish('erp100.user.scope_added',array('user_id'=>(int)$user_id,'scope_id'=>$id,'resource_type'=>$type,'resource_id'=>$resource_id,'actor_id'=>$actor_id));
        return $id;
    }

    public function delete_scope($scope_id, $actor_id)
    {
        $row=DB::select()->from('erp_100_user_scopes')->where('id','=',(int)$scope_id)->execute()->current();
        if ( ! $row) throw new Kohana_Exception('Область доступа не найдена');
        DB::delete('erp_100_user_scopes')->where('id','=',(int)$scope_id)->execute();
        $this->publish('erp100.user.scope_removed',array('user_id'=>(int)$row['user_id'],'scope_id'=>(int)$scope_id,'actor_id'=>$actor_id));
    }

    public function resource_options($type)
    {
        $map=array(
            'department'=>array('erp_310_departments','name',"status='active'"),
            'warehouse'=>array('erp_401_warehouses','name',"is_active=1"),
            'work_center'=>array('erp_408_work_centers','name',"is_active=TRUE"),
        );
        if ( ! isset($map[$type])) return array();
        try { return DB::query(Database::SELECT,"SELECT id, name FROM {$map[$type][0]} WHERE {$map[$type][2]} ORDER BY name")->execute()->as_array(); }
        catch(Exception $e) { return array(); }
    }

    /** NULL means unrestricted; an empty array means deny-all. */
    public function allowed_ids($user_id, $resource_type)
    {
        $user=$this->user($user_id);
        if (($user->status ?: 'active')!=='active') return array();
        if (($user->access_policy ?: 'unrestricted')==='unrestricted') return NULL;
        $ids=array();
        foreach($this->scopes($user_id,TRUE) as $row) {
            if ($row['resource_type']!==$resource_type) continue;
            $ids[]=(int)$row['resource_id'];
            if ($resource_type==='department' AND $row['include_descendants']) {
                try {
                    $children=DB::query(Database::SELECT,"WITH RECURSIVE tree AS (SELECT id FROM erp_310_departments WHERE id=:parent UNION ALL SELECT d.id FROM erp_310_departments d JOIN tree t ON d.parent_id=t.id) SELECT id FROM tree")
                        ->param(':parent',(int)$row['resource_id'])->execute()->as_array(NULL,'id');
                    foreach($children as $id) $ids[]=(int)$id;
                } catch(Exception $e) {}
            }
        }
        return array_values(array_unique($ids));
    }

    public function allows($user_id, $resource_type, $resource_id)
    {
        $user=$this->user($user_id);
        if (($user->status ?: 'active')!=='active') return FALSE;
        if (($user->access_policy ?: 'unrestricted')==='unrestricted') return TRUE;
        $resource_id=(int)$resource_id;
        $rows=$this->scopes($user_id,TRUE);
        foreach($rows as $row) {
            if ($row['resource_type']!==$resource_type) continue;
            if ((int)$row['resource_id']===$resource_id) return TRUE;
            if ($resource_type==='department' AND $row['include_descendants'] AND $this->department_descends_from($resource_id,(int)$row['resource_id'])) return TRUE;
        }
        return FALSE;
    }

    protected function active_admin_count()
    {
        return (int)DB::query(Database::SELECT,"SELECT COUNT(DISTINCT u.id) n FROM users u JOIN roles_users ru ON ru.user_id=u.id JOIN roles r ON r.id=ru.role_id WHERE r.name='admin' AND coalesce(u.status,'active')='active'")->execute()->get('n',0);
    }

    protected function assert_resource($type,$id)
    {
        $tables=array('department'=>'erp_310_departments','warehouse'=>'erp_401_warehouses','work_center'=>'erp_408_work_centers');
        try { $exists=DB::select('id')->from($tables[$type])->where('id','=',(int)$id)->limit(1)->execute()->current(); }
        catch(Exception $e) { throw new Kohana_Exception('Модуль-владелец области недоступен'); }
        if ( ! $exists) throw new Kohana_Exception('Объект области доступа не найден');
    }

    protected function department_descends_from($candidate,$parent)
    {
        try {
            $row=DB::query(Database::SELECT,"WITH RECURSIVE tree AS (SELECT id,parent_id FROM erp_310_departments WHERE id=:parent UNION ALL SELECT d.id,d.parent_id FROM erp_310_departments d JOIN tree t ON d.parent_id=t.id) SELECT 1 ok FROM tree WHERE id=:candidate LIMIT 1")
                ->parameters(array(':parent'=>$parent,':candidate'=>$candidate))->execute()->current();
            return (bool)$row;
        } catch(Exception $e) { return FALSE; }
    }

    /** Keep effective-date defaults aligned with PostgreSQL CURRENT_DATE near timezone boundaries. */
    protected function database_today()
    {
        return (string) DB::query(Database::SELECT, 'SELECT CURRENT_DATE AS today')->execute()->get('today');
    }

    protected function publish($name,array $payload) { if(class_exists('Event')) Event::fire($name,$payload); }
}
