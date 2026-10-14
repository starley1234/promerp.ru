<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Base extends Controller_Base
{
    public function before()
    {
        parent::before();
        $this->_meta['title'] = 'Сотрудники и оргструктура';
        $this->_meta['description'] = 'Сотрудники, подразделения, должности и назначения.';
        $this->set_menu(array(
            array('title' => 'Сводка', 'href' => '/workforce', 'icon' => 'fas fa-users', 'acl' => 'erp310.view'),
            array('title' => 'Сотрудники', 'href' => '/workforce/employees', 'icon' => 'fas fa-address-card', 'acl' => 'erp310.view'),
            array('title' => 'Оргструктура', 'href' => '/workforce/departments', 'icon' => 'fas fa-sitemap', 'acl' => 'erp310.view'),
            array('title' => 'Должности', 'href' => '/workforce/positions', 'icon' => 'fas fa-briefcase', 'acl' => 'erp310.view'),
            array('title' => 'Штат и вакансии', 'href' => '/workforce/staffing', 'icon' => 'fas fa-chart-pie', 'acl' => 'erp310.view'),
            array('title' => 'Добавить сотрудника', 'href' => '/workforce/employees/edit', 'icon' => 'fas fa-user-plus', 'acl' => 'erp310.manage'),
        ));
    }

    protected function user_id()
    {
        $user = Auth::instance()->get_user();
        return $user ? (int) $user->id : NULL;
    }

    protected function flash($type, $message)
    {
        Session::instance()->set('erp310_flash', array(array('type' => $type, 'message' => $message)));
    }

    protected function flashes()
    {
        $rows = Session::instance()->get('erp310_flash', array());
        Session::instance()->delete('erp310_flash');
        return $rows;
    }
}
