<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Department extends Controller_Erp310_Base
{
    public function action_index()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        $this->template->content = View::factory('erp310/department/index')
            ->set('departments', $service->departments(FALSE))->set('kinds', Model_Erp310_Department::$kinds)
            ->set('messages', $this->flashes());
    }

    public function action_save()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/departments');
        try
        {
            Service_Erp310_Workforce::instance()->save_department($this->request->post(), $this->user_id());
            $this->flash('success', 'Подразделение сохранено');
        }
        catch (Exception $e)
        {
            $this->flash('error', $e instanceof ORM_Validation_Exception ? 'Проверьте код и название подразделения' : $e->getMessage());
        }
        $this->redirect('/workforce/departments');
    }
}
