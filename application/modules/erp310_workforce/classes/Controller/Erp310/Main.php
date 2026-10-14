<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Main extends Controller_Erp310_Base
{
    public function action_index()
    {
        if ( ! $this->can('erp310.view')) throw new HTTP_Exception_403('Доступ запрещён');
        $service = Service_Erp310_Workforce::instance();
        $this->template->content = View::factory('erp310/main/index')
            ->set('stats', $service->stats())
            ->set('recent', $service->search(array('status' => 'active'), 8, 0)['rows'])
            ->set('messages', $this->flashes());
    }
}
