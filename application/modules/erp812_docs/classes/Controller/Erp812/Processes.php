<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер сквозных бизнес-процессов PromERP с Mermaid-диаграммами.
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Processes extends Controller_Erp812_Main
{
    public function action_index()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $processes = $registry->business_processes();
        $selected_id = $this->request->param('id');

        if ( ! $selected_id && ! empty($processes))
        {
            $selected_id = array_key_first($processes);
        }

        $current_process = isset($processes[$selected_id]) ? $processes[$selected_id] : reset($processes);

        $view = View::factory('erp812/processes');
        $view->processes = $processes;
        $view->current_id = $selected_id;
        $view->current_process = $current_process;

        $this->template->content = $view;
    }
}
