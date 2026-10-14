<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Erp310WorkforceTest extends TestCase
{
    protected $service;
    protected $employee_ids = array();
    protected $department_ids = array();
    protected $actor_id;
    protected $today;

    protected function setUp(): void
    {
        try { \DB::select('id')->from('erp_310_employees')->limit(1)->execute(); }
        catch (\Exception $e) { $this->markTestSkipped('Выполните миграции erp310'); return; }
        $this->service = \Service_Erp310_Workforce::instance();
        $this->today = (string) \DB::query(\Database::SELECT, 'SELECT CURRENT_DATE AS today')->execute()->get('today');
        $key = substr(md5((string) microtime(TRUE)), 0, 10);
        $insert = \DB::insert('users', array('username','email','password','email_confirmed'))
            ->values(array('workforce_'.$key, 'workforce_'.$key.'@example.test', 'hash', 1))->execute();
        $this->actor_id = (int) $insert[0];
    }

    protected function tearDown(): void
    {
        if ( ! $this->service) return;
        try {
            if ($this->department_ids) {
                \DB::delete('erp_310_staff_units')->where('department_id', 'IN', $this->department_ids)->execute();
            }
            if ($this->employee_ids) {
                \DB::delete('erp_310_assignments')->where('employee_id', 'IN', $this->employee_ids)->execute();
                \DB::delete('erp_310_employees')->where('id', 'IN', $this->employee_ids)->execute();
            }
            if ($this->department_ids) {
                \DB::delete('erp_310_positions')->where('department_id', 'IN', $this->department_ids)->execute();
                \DB::delete('erp_310_departments')->where('id', 'IN', $this->department_ids)->execute();
            }
            if ($this->actor_id) {
                \DB::delete('roles_users')->where('user_id', '=', $this->actor_id)->execute();
                \DB::delete('users')->where('id', '=', $this->actor_id)->execute();
            }
        } catch (\Exception $e) {}
    }

    protected function department($suffix = 'A')
    {
        $row = $this->service->save_department(array(
            'code' => 'TEST-'.$suffix.'-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'name' => 'Тестовое подразделение '.$suffix,
            'kind' => 'section',
        ), $this->actor_id);
        $this->department_ids[] = (int) $row->id;
        return $row;
    }

    protected function employee($department_id, array $extra = array())
    {
        $seq = count($this->employee_ids) + 1;
        $row = $this->service->save_employee(array_merge(array(
            'personnel_no' => 'TEST-EMP-'.substr(md5(microtime(TRUE).$seq), 0, 8),
            'last_name' => 'Тестов', 'first_name' => 'Сотрудник'.$seq,
            'status' => 'active', 'department_id' => $department_id,
            'assignment_from' => $this->today, 'rate' => 1,
        ), $extra), $this->actor_id);
        $this->employee_ids[] = (int) $row->id;
        return $row;
    }

    public function test_department_is_created_and_normalizes_code()
    {
        $row = $this->service->save_department(array('code'=>' test-lab ','name'=>'Лаборатория','kind'=>'department'), $this->actor_id);
        $this->department_ids[] = (int) $row->id;
        $this->assertSame('TEST-LAB', $row->code);
        $this->assertSame('Лаборатория', $row->name);
    }

    public function test_employee_and_current_assignment_are_created_together()
    {
        $department = $this->department();
        $employee = $this->employee($department->id);
        $card = $this->service->get($employee->id);
        $this->assertSame((int) $department->id, (int) $card['department_id']);
        $this->assertSame('Тестов Сотрудник1', trim($card['last_name'].' '.$card['first_name']));
    }

    public function test_new_assignment_closes_previous_one()
    {
        $first = $this->department('FIRST');
        $second = $this->department('SECOND');
        $employee = $this->employee($first->id);
        $this->service->save_employee(array(
            'id'=>$employee->id, 'personnel_no'=>$employee->personnel_no,
            'last_name'=>$employee->last_name, 'first_name'=>$employee->first_name,
            'status'=>'active', 'department_id'=>$second->id, 'assignment_from'=>$this->today, 'rate'=>1,
        ), $this->actor_id);
        $current = \DB::select()->from('erp_310_assignments')->where('employee_id','=',$employee->id)->where('valid_to','IS',NULL)->execute()->as_array();
        $this->assertCount(1, $current);
        $this->assertSame((int) $second->id, (int) $current[0]['department_id']);
        $this->assertSame(2, (int) \DB::select(array(\DB::expr('COUNT(*)'),'n'))->from('erp_310_assignments')->where('employee_id','=',$employee->id)->execute()->get('n'));
    }

    public function test_search_filters_department()
    {
        $first = $this->department('ONE');
        $second = $this->department('TWO');
        $this->employee($first->id);
        $this->employee($second->id);
        $result = $this->service->search(array('department_id'=>$first->id, 'term'=>'Тестов'), 25, 0);
        $this->assertSame(1, $result['total']);
        $this->assertSame((int) $first->id, (int) $result['rows'][0]['department_id']);
    }

    public function test_search_applies_effective_department_scope()
    {
        $first=$this->department('SCOPE-ONE'); $second=$this->department('SCOPE-TWO');
        $allowed=$this->employee($first->id); $this->employee($second->id);
        $result=$this->service->search(array('department_ids'=>array((int)$first->id)),25,0);
        $this->assertSame(1,$result['total']);
        $this->assertSame((int)$allowed->id,(int)$result['rows'][0]['id']);
        $this->assertSame(0,$this->service->search(array('department_ids'=>array()),25,0)['total']);
    }

    public function test_position_from_another_department_is_rejected()
    {
        $first = $this->department('POSITION-A');
        $second = $this->department('POSITION-B');
        $position = \ORM::factory('Erp310_Position')->values(array(
            'department_id' => $first->id, 'code' => 'TEST-POS-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'name' => 'Тестовая должность', 'category' => 'specialist', 'status' => 'active',
        ))->create();
        $this->expectException(\Kohana_Exception::class);
        $this->employee($second->id, array('position_id' => $position->id));
    }

    public function test_position_can_be_created_and_archived_when_unused()
    {
        $department = $this->department('POS-ARCHIVE');
        $position = $this->service->save_position(array(
            'code' => 'TEST-POS-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'name' => 'Инженер-испытатель', 'category' => 'specialist',
            'department_id' => $department->id,
        ), $this->actor_id);
        $this->assertSame('active', $position->status);
        $archived = $this->service->archive_position($position->id, $this->actor_id);
        $this->assertSame('archived', $archived->status);
    }

    public function test_used_position_cannot_be_archived()
    {
        $department = $this->department('POS-USED');
        $position = $this->service->save_position(array(
            'code' => 'TEST-POS-'.substr(md5((string) microtime(TRUE)), 0, 6),
            'name' => 'Мастер тестового участка', 'category' => 'manager',
            'department_id' => $department->id,
        ), $this->actor_id);
        $this->employee($department->id, array('position_id' => $position->id));
        $this->expectException(\Kohana_Exception::class);
        $this->service->archive_position($position->id, $this->actor_id);
    }

    public function test_dismissal_closes_assignment_and_is_idempotent()
    {
        $department = $this->department('DISMISS');
        $employee = $this->employee($department->id, array('hired_at' => '2026-01-10'));
        $this->service->dismiss_employee($employee->id, $this->today, $this->actor_id);
        $this->service->dismiss_employee($employee->id, $this->today, $this->actor_id);
        $card = $this->service->get($employee->id);
        $history = $this->service->assignment_history($employee->id);
        $this->assertSame('dismissed', $card['status']);
        $this->assertSame($this->today, $card['dismissed_at']);
        $this->assertCount(1, $history);
        $this->assertSame($this->today, $history[0]['valid_to']);
    }

    public function test_dismissal_before_hiring_is_rejected()
    {
        $department = $this->department('DATES');
        $employee = $this->employee($department->id, array('hired_at' => '2026-09-10'));
        $this->expectException(\Kohana_Exception::class);
        $this->service->dismiss_employee($employee->id, '2026-09-09', $this->actor_id);
    }

    public function test_duplicate_personnel_number_is_rejected()
    {
        $department = $this->department();
        $first = $this->employee($department->id);
        $this->expectException(\ORM_Validation_Exception::class);
        $second = $this->employee($department->id, array('personnel_no'=>$first->personnel_no));
        $this->employee_ids[] = (int) $second->id;
    }

    public function test_stats_report_unlinked_employee()
    {
        $department = $this->department();
        $before = $this->service->stats();
        $this->employee($department->id, array('user_id'=>NULL));
        $after = $this->service->stats();
        $this->assertSame($before['unlinked'] + 1, $after['unlinked']);
    }

    public function test_staffing_calculates_vacant_fte_from_assignments()
    {
        $department = $this->department('STAFF');
        $position = $this->service->save_position(array(
            'department_id'=>$department->id, 'code'=>'TEST-STAFF-'.substr(md5(microtime(TRUE)),0,6),
            'name'=>'Штатный испытатель', 'category'=>'specialist',
        ), $this->actor_id);
        $this->service->save_staff_unit(array(
            'department_id'=>$department->id, 'position_id'=>$position->id,
            'planned_fte'=>2, 'valid_from'=>$this->today,
        ), $this->actor_id);
        $this->employee($department->id, array('position_id'=>$position->id, 'rate'=>0.5));
        $rows = $this->service->staffing_rows(TRUE);
        $match = array_values(array_filter($rows, function($row) use($department,$position) {
            return (int)$row['department_id']===(int)$department->id AND (int)$row['position_id']===(int)$position->id;
        }));
        $this->assertCount(1, $match);
        $this->assertSame(0.5, (float)$match[0]['occupied_fte']);
        $this->assertSame(1.5, (float)$match[0]['vacant_fte']);
    }

    public function test_staffing_rejects_position_of_another_department()
    {
        $first=$this->department('STAFF-A'); $second=$this->department('STAFF-B');
        $position=$this->service->save_position(array('department_id'=>$first->id,'code'=>'TEST-ST-X-'.substr(md5(microtime(TRUE)),0,6),'name'=>'Чужая должность'),$this->actor_id);
        $this->expectException(\Kohana_Exception::class);
        $this->service->save_staff_unit(array('department_id'=>$second->id,'position_id'=>$position->id,'planned_fte'=>1),$this->actor_id);
    }

    public function test_employee_reference_returns_labels_not_bare_ids()
    {
        $department = $this->department('REFERENCE');
        $employee = $this->employee($department->id);
        $items = $this->service->references('employee', $employee->personnel_no, 15);
        $this->assertSame((int) $employee->id, (int) $items[0]['id']);
        $this->assertStringContainsString('Тестов', $items[0]['label']);
        $this->assertStringContainsString($employee->personnel_no, $items[0]['context']);
    }

    public function test_employee_cannot_manage_self()
    {
        $department = $this->department('SELF-MANAGER');
        $employee = $this->employee($department->id);
        $this->expectException(\Kohana_Exception::class);
        $this->service->save_employee(array(
            'id'=>$employee->id, 'personnel_no'=>$employee->personnel_no,
            'last_name'=>$employee->last_name, 'first_name'=>$employee->first_name,
            'status'=>'active', 'department_id'=>$department->id, 'manager_id'=>$employee->id,
        ), $this->actor_id);
    }

    public function test_mcp_exposes_workforce_tools()
    {
        $tools = \Service_Erp804_Registry::instance()->tools();
        foreach (array('workforce.employees.search','workforce.employee.get','workforce.structure.list',
            'workforce.positions.list','workforce.staffing.list','workforce.staffing.save',
            'workforce.employee.save','workforce.employee.dismiss') as $name)
        {
            $this->assertArrayHasKey($name, $tools);
        }
        $this->assertFalse($tools['workforce.employees.search']['mutating']);
        $this->assertTrue($tools['workforce.employee.dismiss']['approval']);
    }

    public function test_mcp_read_handlers_reach_service()
    {
        $tools = \Service_Erp804_Registry::instance()->tools();
        $department = $this->department('MCP');
        $employee = $this->employee($department->id);
        $result = $tools['workforce.employee.get']['handler'](array('id'=>$employee->id));
        $this->assertSame((int)$employee->id, (int)$result['employee']['id']);
        $list = $tools['workforce.employees.search']['handler'](array('term'=>$employee->personnel_no));
        $this->assertSame(1, $list['total']);
    }

    public function test_routes_are_registered()
    {
        $this->assertSame('workforce', \Route::get('erp310')->uri());
        $this->assertSame('workforce/employees', \Route::get('erp310_employees')->uri(array()));
        $this->assertSame('workforce/staffing', \Route::get('erp310_staffing')->uri(array()));
    }
}
