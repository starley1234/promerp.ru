<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Главный экран интерактивной документации (erp812_docs).
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Index extends Controller_Erp812_Main
{
    public function action_index()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $modules = $registry->modules();
        $categories = $registry->categories();
        $processes = $registry->business_processes();

        // Подсчет статистики
        $total_routes = 0;
        $total_mcp = 0;
        $total_tables = 0;

        foreach ($modules as $m)
        {
            $total_routes += count($m['routes']);
            $total_mcp += count($m['mcp_tools']);
            $total_tables += count($m['tables']);
        }

        $stats = array(
            'total_modules'   => count($modules),
            'total_routes'    => $total_routes,
            'total_mcp'       => $total_mcp,
            'total_tables'    => $total_tables,
            'total_processes' => count($processes),
        );

        $view = View::factory('erp812/index');
        $view->stats = $stats;
        $view->categories = $categories;
        $view->modules = $modules;
        $view->processes = $processes;
        // Каталог модулей закрыт от гостей, поэтому на главной документации
        // ссылки на него показываем только тем, кто их откроет.
        $view->modules_open = $this->can('erp812.view');

        $this->template->content = $view;
    }
}
