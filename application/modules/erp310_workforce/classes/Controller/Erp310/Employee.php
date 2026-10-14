<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Employee extends Controller_Erp310_Base
{
    const PAGE_SIZE = 25;

    public function action_index()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $filters = array(
            'term' => trim((string) $this->request->query('term')),
            'status' => $this->request->query('status'),
            'department_id' => (int) $this->request->query('department_id'),
        );
        $page = max(1, (int) $this->request->query('page'));
        $allowed = Service_Erp100_Access::instance()->allowed_ids($this->user_id(), 'department');
        if ($allowed !== NULL) $filters['department_ids'] = $allowed;
        $service = Service_Erp310_Workforce::instance();
        $result = $service->search($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $this->template->content = View::factory('erp310/employee/index')
            ->set('rows', $result['rows'])->set('total', $result['total'])->set('filters', $filters)
            ->set('page', $page)->set('limit', self::PAGE_SIZE)->set('departments', $service->departments())
            ->set('statuses', Model_Erp310_Employee::$statuses)->set('messages', $this->flashes());
    }

    public function action_view()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        try
        {
            $employee = $service->get((int) $this->request->param('id'));
        }
        catch (Kohana_Exception $e)
        {
            throw new HTTP_Exception_404('Сотрудник не найден');
        }
        if ( ! $employee['department_id'] OR ! $this->in_scope('department', (int)$employee['department_id']))
            throw new HTTP_Exception_403('Сотрудник вне вашей области доступа');
        $this->template->content = View::factory('erp310/employee/view')
            ->set('employee', $employee)
            ->set('history', $service->assignment_history($employee['id']))
            ->set('statuses', Model_Erp310_Employee::$statuses)
            ->set('messages', $this->flashes());
    }

    public function action_edit()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        $id = (int) $this->request->param('id');
        $employee = $id ? $service->get($id) : array('id' => NULL, 'status' => 'active', 'rate' => 1, 'assignment_from' => date('Y-m-d'));
        $this->render_form($employee, array());
    }

    public function action_save()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/employees');
        $data = $this->request->post();
        try
        {
            $employee = Service_Erp310_Workforce::instance()->save_employee($data, $this->user_id());
            $this->flash('success', 'Карточка сотрудника сохранена');
        }
        catch (ORM_Validation_Exception $e)
        {
            $this->render_form($data, $e->errors(''));
            return;
        }
        catch (Exception $e)
        {
            $this->render_form($data, array('form' => $e->getMessage()));
            return;
        }

        $this->redirect('/workforce/employees?term='.urlencode($employee->personnel_no));
    }

    public function action_dismiss()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/employees');
        $id = (int) $this->request->param('id');
        try
        {
            Service_Erp310_Workforce::instance()->dismiss_employee($id, $this->request->post('dismissed_at'), $this->user_id());
            $this->flash('success', 'Сотрудник уволен, действующее назначение закрыто');
        }
        catch (Exception $e)
        {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/workforce/employees/view/'.$id);
    }

    protected function render_form(array $employee, array $errors)
    {
        $service = Service_Erp310_Workforce::instance();
        if ((int) Arr::get($employee, 'user_id') AND ! Arr::get($employee, 'username'))
        {
            $user = ORM::factory('User', (int) $employee['user_id']);
            if ($user->loaded()) { $employee['username'] = $user->username; $employee['user_email'] = $user->email; }
        }
        if ((int) Arr::get($employee, 'manager_id') AND ! Arr::get($employee, 'manager_name'))
        {
            try
            {
                $manager = $service->get((int) $employee['manager_id']);
                $employee['manager_name'] = trim($manager['last_name'].' '.$manager['first_name'].' '.$manager['middle_name']);
            }
            catch (Exception $e) {}
        }
        $this->template->content = View::factory('erp310/employee/form')
            ->set('employee', $employee)->set('errors', $errors)->set('departments', $service->departments())
            ->set('positions', $service->positions())
            ->set('statuses', Model_Erp310_Employee::$statuses);
    }
}
