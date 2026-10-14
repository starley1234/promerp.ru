<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Staffing extends Controller_Erp310_Base
{
    public function action_index()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        $this->template->content = View::factory('erp310/staffing/index')
            ->set('rows', $service->staffing_rows(FALSE))->set('departments', $service->departments())
            ->set('positions', $service->positions())->set('messages', $this->flashes())
            ->set('can_manage', $this->can('erp310.manage'));
    }

    public function action_save()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/staffing');
        try
        {
            Service_Erp310_Workforce::instance()->save_staff_unit($this->request->post(), $this->user_id());
            $this->flash('success', 'Штатное расписание сохранено');
        }
        catch (Exception $e) { $this->flash('error', $e->getMessage()); }
        $this->redirect('/workforce/staffing');
    }

    public function action_archive()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/staffing');
        try
        {
            Service_Erp310_Workforce::instance()->archive_staff_unit((int) $this->request->param('id'), $this->user_id());
            $this->flash('success', 'Строка штатного расписания закрыта');
        }
        catch (Exception $e) { $this->flash('error', $e->getMessage()); }
        $this->redirect('/workforce/staffing');
    }
}
