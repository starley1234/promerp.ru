<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Интерактивный каталог инструментов MCP (Model Context Protocol) для AI-агентов.
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Mcp extends Controller_Erp812_Main
{
    public function action_index()
    {
        $this->_meta['title'] = 'Каталог инструментов MCP (Model Context Protocol)';

        $registry = Service_Erp812_DocRegistry::instance();
        $modules = $registry->modules();

        $all_tools = array();
        foreach ($modules as $m_name => $mod)
        {
            if ( ! empty($mod['mcp_tools']))
            {
                foreach ($mod['mcp_tools'] as $t)
                {
                    $t['module_name'] = $m_name;
                    $t['module_title'] = $mod['title'];
                    $t['category'] = $mod['category'];
                    $all_tools[] = $t;
                }
            }
        }

        $view = View::factory('erp812/mcp');
        $view->tools = $all_tools;
        $view->modules_with_tools = array_filter($modules, function($m) {
            return ! empty($m['mcp_tools']);
        });

        $this->template->content = $view;
    }
}
