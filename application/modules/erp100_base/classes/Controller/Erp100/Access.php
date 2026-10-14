<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp100_Access extends Controller_Base
{
    public function before()
    {
        parent::before();
        if ( ! $this->can('erp100.users')) throw new HTTP_Exception_403('Доступ запрещён');
        $this->template->title='Управление доступом';
    }

    public function action_index()
    {
        $rows=DB::query(Database::SELECT,"SELECT u.id,u.username,u.email,u.status,u.access_policy,u.last_login,
            string_agg(r.name, ', ' ORDER BY r.name) roles
            FROM users u LEFT JOIN roles_users ru ON ru.user_id=u.id LEFT JOIN roles r ON r.id=ru.role_id
            GROUP BY u.id ORDER BY u.username")->execute()->as_array();
        $this->template->content=View::factory('erp100/access/index')->set('rows',$rows);
    }

    public function action_view()
    {
        $service=Service_Erp100_Access::instance(); $id=(int)$this->request->param('id');
        try { $account=$service->present($id); }
        catch(Exception $e) { throw new HTTP_Exception_404('Учётная запись не найдена'); }
        $this->template->content=View::factory('erp100/access/view')
            ->set('account',$account)->set('all_roles',ORM::factory('Role')->order_by('name')->find_all())
            ->set('departments',$service->resource_options('department'))->set('warehouses',$service->resource_options('warehouse'))
            ->set('work_centers',$service->resource_options('work_center'));
    }

    public function action_roles() { $this->post(); $this->run(function($s,$id,$actor){ $s->set_roles($id,(array)$this->request->post('roles'),$actor); },'Роли сохранены'); }
    public function action_status() { $this->post(); $this->run(function($s,$id,$actor){ $s->set_status($id,$this->request->post('status'),$this->request->post('reason'),$actor); },'Статус сохранён'); }
    public function action_policy() { $this->post(); $this->run(function($s,$id,$actor){ $s->set_policy($id,$this->request->post('access_policy'),$actor); },'Политика области сохранена'); }
    public function action_revoke() { $this->post(); $this->run(function($s,$id,$actor){ $s->revoke_sessions($id,$actor); },'Все сеансы отозваны'); }
    public function action_scope_add() { $this->post(); $this->run(function($s,$id,$actor){ $s->save_scope($id,$this->request->post(),$actor); },'Область добавлена'); }
    public function action_scope_delete() { $this->post(); $user=(int)$this->request->post('user_id'); try { Service_Erp100_Access::instance()->delete_scope((int)$this->request->param('id'),$this->actor()); Session::instance()->set('access_flash',array('success','Область удалена')); } catch(Exception $e) { Session::instance()->set('access_flash',array('error',$e->getMessage())); } $this->redirect('/access/users/view/'.$user); }

    protected function post() { if($this->request->method()!==Request::POST) throw (new HTTP_Exception_405('Используйте POST'))->allowed(array('POST')); }
    protected function actor() { return $this->_current_user?(int)$this->_current_user->id:NULL; }
    protected function run($callback,$message)
    {
        $id=(int)$this->request->param('id');
        try { $callback(Service_Erp100_Access::instance(),$id,$this->actor()); Session::instance()->set('access_flash',array('success',$message)); }
        catch(Exception $e) { Session::instance()->set('access_flash',array('error',$e->getMessage())); }
        $this->redirect('/access/users/view/'.$id);
    }
}
