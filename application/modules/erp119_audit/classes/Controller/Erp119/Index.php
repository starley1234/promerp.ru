<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сводка по журналу действий (erp119_audit).
 *
 * @package    Erp119
 * @category   Controllers
 */
class Controller_Erp119_Index extends Controller_Erp119_Main
{
    /**
     * Дашборд: объёмы, важность, активность по дням, топы, последние записи.
     */
    public function action_index()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $query = Service_Erp119_Query::instance();
        $days  = (int) Kohana::$config->load('erp119_audit')->get('default_period_days', 30);

        $this->set_title('Журнал действий');
        $this->set_description('Кто, когда и что изменил в системе');

        $this->template->content = View::factory('erp119/index/index')
            ->set('stats', $query->stats($days))
            ->set('activity', $query->activity(14))
            ->set('recent', $query->search(array(), (int) Kohana::$config->load('erp119_audit')->get('recent_limit', 8), 0)['rows'])
            ->set('journal', Service_Erp119_Journal::instance())
            ->set('messages', $this->_flash_messages());
    }
}
