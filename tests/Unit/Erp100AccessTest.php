<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class Erp100AccessTest extends TestCase
{
    protected $service; protected $users=array(); protected $departments=array(); protected $actor_id;
    protected function setUp(): void
    {
        try { \DB::select('access_policy')->from('users')->limit(1)->execute(); }
        catch(\Exception $e) { $this->markTestSkipped('Выполните миграции access governance'); return; }
        $this->service=\Service_Erp100_Access::instance();
        $this->actor_id=$this->user();
    }
    protected function tearDown(): void
    {
        try {
            if($this->users) \DB::delete('erp_100_user_scopes')->where('user_id','IN',$this->users)->execute();
            if($this->departments) \DB::delete('erp_310_departments')->where('id','IN',$this->departments)->execute();
            if($this->users) {
                \DB::delete('roles_users')->where('user_id','IN',$this->users)->execute();
                \DB::delete('users')->where('id','IN',$this->users)->execute();
            }
        } catch(\Exception $e) {}
    }
    protected function user()
    {
        $key=substr(md5(microtime(TRUE).count($this->users)),0,10);
        $r=\DB::insert('users',array('username','email','password','email_confirmed'))->values(array('access_'.$key,'access_'.$key.'@example.test','hash',1))->execute();
        $id=(int)$r[0]; $this->users[]=$id;
        $login=\ORM::factory('Role',array('name'=>'login'));
        \DB::insert('roles_users',array('user_id','role_id'))->values(array($id,$login->id))->execute();
        return $id;
    }
    protected function department($parent=NULL)
    {
        $r=\Service_Erp310_Workforce::instance()->save_department(array('code'=>'ACL-'.substr(md5(microtime(TRUE).count($this->departments)),0,7),'name'=>'Область теста','kind'=>'department','parent_id'=>$parent),$this->actor_id);
        $this->departments[]=(int)$r->id; return (int)$r->id;
    }

    public function test_unrestricted_keeps_previous_access_behavior()
    {
        $id=$this->user();
        $this->assertTrue($this->service->allows($id,'department',999999));
        $this->assertNull($this->service->allowed_ids($id,'department'));
    }
    public function test_restricted_without_scopes_is_deny_all()
    {
        $id=$this->user(); $this->service->set_policy($id,'restricted',$this->actor_id);
        $this->assertFalse($this->service->allows($id,'department',1));
        $this->assertSame(array(),$this->service->allowed_ids($id,'department'));
    }
    public function test_department_scope_can_include_descendants()
    {
        $id=$this->user(); $parent=$this->department(); $child=$this->department($parent);
        $this->service->set_policy($id,'restricted',$this->actor_id);
        $this->service->save_scope($id,array('resource_type'=>'department','resource_id'=>$parent,'include_descendants'=>1),$this->actor_id);
        $this->assertTrue($this->service->allows($id,'department',$parent));
        $this->assertTrue($this->service->allows($id,'department',$child));
        $this->assertContains($child,$this->service->allowed_ids($id,'department'));
    }
    public function test_restricted_scope_denies_other_department()
    {
        $id=$this->user(); $allowed=$this->department(); $other=$this->department();
        $this->service->set_policy($id,'restricted',$this->actor_id);
        $this->service->save_scope($id,array('resource_type'=>'department','resource_id'=>$allowed),$this->actor_id);
        $this->assertTrue($this->service->allows($id,'department',$allowed));
        $this->assertFalse($this->service->allows($id,'department',$other));
    }
    public function test_blocking_revokes_session_epoch()
    {
        $id=$this->user(); $before=$this->service->user($id)->session_version;
        $user=$this->service->set_status($id,'blocked','Тест',$this->actor_id);
        $this->assertSame('blocked',$user->status);
        $this->assertSame((int)$before+1,(int)$user->session_version);
        $this->assertFalse($this->service->allows($id,'department',1));
    }
    public function test_last_active_admin_cannot_be_blocked()
    {
        $admin=\ORM::factory('User')->where('username','=','admin')->find();
        $this->expectException(\Kohana_Exception::class);
        $this->service->set_status($admin->id,'blocked','Нельзя',999);
    }
    public function test_unknown_role_is_rejected()
    {
        $id=$this->user(); $this->expectException(\Kohana_Exception::class);
        $this->service->set_roles($id,array('login','does-not-exist'),$this->actor_id);
    }
    public function test_mcp_access_tools_require_approval_for_changes()
    {
        $tools=\Service_Erp804_Registry::instance()->tools();
        foreach(array('access.user.get','access.roles.set','access.status.set','access.sessions.revoke','access.policy.set','access.scope.add') as $name) $this->assertArrayHasKey($name,$tools);
        $this->assertFalse($tools['access.user.get']['mutating']);
        $this->assertTrue($tools['access.roles.set']['approval']);
        $this->assertTrue($tools['access.status.set']['destructive']);
    }
}
