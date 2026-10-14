<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Position extends Controller_Erp310_Base
{
    public function action_index()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        $this->template->content = View::factory('erp310/position/index')
            ->set('positions', $service->position_rows())
            ->set('departments', $service->departments())
            ->set('categories', Model_Erp310_Position::$categories)
            ->set('messages', $this->flashes());
    }

    public function action_save()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/positions');
        try
        {
            Service_Erp310_Workforce::instance()->save_position($this->request->post(), $this->user_id());
            $this->flash('success', 'Должность сохранена');
        }
        catch (Exception $e)
        {
            $this->flash('error', $e instanceof ORM_Validation_Exception ? 'Проверьте код, название и категорию должности' : $e->getMessage());
        }
        $this->redirect('/workforce/positions');
    }

    public function action_archive()
    {
        if ( ! $this->can('erp310.manage')) throw new HTTP_Exception_403('Доступ запрещён');
        if ($this->request->method() !== Request::POST) $this->redirect('/workforce/positions');
        try
        {
            Service_Erp310_Workforce::instance()->archive_position((int) $this->request->param('id'), $this->user_id());
            $this->flash('success', 'Должность перенесена в архив');
        }
        catch (Exception $e)
        {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/workforce/positions');
    }
}
