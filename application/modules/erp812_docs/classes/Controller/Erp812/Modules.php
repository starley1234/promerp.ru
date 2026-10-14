<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Каталог и детальный просмотр модулей PromERP.
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Modules extends Controller_Erp812_Main
{
    /**
     * Каталог модулей закрыт от гостей.
     *
     * В нём перечислены таблицы, маршруты, права доступа и связи между
     * модулями: это внутренняя схема системы, а не содержание сайта. Гостю
     * такие ссылки всё равно не откроются, поэтому держим раздел внутри
     * контура — как и всю остальную документацию для авторизованных.
     *
     * @return  void
     * @throws  HTTP_Exception_403
     */
    public function before()
    {
        parent::before();

        if ( ! $this->can('erp812.view'))
        {
            throw new HTTP_Exception_403(
                'Каталог модулей доступен авторизованным пользователям: войдите в систему.'
            );
        }
    }

    public function action_index()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $modules = $registry->modules();
        $categories = $registry->categories();
        $current_category = $this->request->query('category');

        if ($current_category && isset($categories[$current_category]))
        {
            $modules = array_filter($modules, function($m) use ($current_category) {
                return $m['category'] === $current_category;
            });
        }

        $view = View::factory('erp812/modules/index');
        $view->modules = $modules;
        $view->categories = $categories;
        $view->current_category = $current_category;

        $this->template->content = $view;
    }

    public function action_view()
    {
        $name = $this->request->param('id');
        $registry = Service_Erp812_DocRegistry::instance();
        $module = $registry->module($name);

        if ( ! $module)
        {
            throw new HTTP_Exception_404('Модуль :name не найден', array(':name' => $name));
        }

        $this->_meta['title'] = $module['title'] . ' — Документация модуля';

        // Рендеринг README через Service_Erp812_MarkdownRenderer
        // README начинается с `#`, а страница уже даёт свой h1 из
        // `page_header`: без сдвига на странице выходило два заголовка
        // первого уровня.
        $rendered_readme = Service_Erp812_MarkdownRenderer::render($module['readme_raw'], 1);

        // Построение интерактивной Mermaid-диаграммы архитектуры модуля
        $mermaid_diagram = $this->_generate_module_diagram($module);

        $view = View::factory('erp812/modules/view');
        $view->module = $module;
        $view->rendered_readme = $rendered_readme;
        $view->mermaid_diagram = $mermaid_diagram;

        $this->template->content = $view;
    }

    /**
     * Генерация схемы зависимостей и архитектуры модуля в формате Mermaid
     */
    protected function _generate_module_diagram(array $module)
    {
        $safe_mod_id = 'M_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $module['name']);
        
        // Очистка названия: удаляем любые спецсимволы, которые могут сломать синтаксис Mermaid
        $safe_title = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', ' ', $module['title']);
        $safe_title = preg_replace('/\s+/', ' ', trim($safe_title));
        if (empty($safe_title))
        {
            $safe_title = $module['name'];
        }

        $lines = array();
        $lines[] = "graph TD";
        $lines[] = "    subgraph {$safe_mod_id} [\"{$safe_title}\"]";

        $has_routes = ! empty($module['routes']);
        $has_ctrls  = ! empty($module['controllers']);
        $has_mcp    = ! empty($module['mcp_tools']);
        $has_tables = ! empty($module['tables']);

        if ($has_routes)
        {
            $lines[] = "        subgraph SG_Routes [\"HTTP Маршруты\"]";
            $i = 0;
            foreach (array_slice($module['routes'], 0, 4) as $r)
            {
                $i++;
                $r_name = preg_replace('/[^\p{L}\p{N}\s\-_\.\/]/u', '', (string)$r['name']);
                $lines[] = "            R{$i}[\"{$r_name}\"]";
            }
            if (count($module['routes']) > 4)
            {
                $rem = count($module['routes']) - 4;
                $lines[] = "            R_more[\"... еще {$rem} маршрутов\"]";
            }
            $lines[] = "        end";
        }

        if ($has_ctrls)
        {
            $lines[] = "        subgraph SG_Controllers [\"Контроллеры\"]";
            $c_i = 0;
            foreach (array_slice($module['controllers'], 0, 4) as $c)
            {
                $c_i++;
                $c_name = is_array($c) ? $c['name'] : (string)$c;
                $c_name = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $c_name);
                $lines[] = "            C{$c_i}[\"{$c_name}\"]";
            }
            if (count($module['controllers']) > 4)
            {
                $rem = count($module['controllers']) - 4;
                $lines[] = "            C_more[\"... еще {$rem} контроллеров\"]";
            }
            $lines[] = "        end";
        }

        if ($has_mcp)
        {
            $lines[] = "        subgraph SG_MCP [\"MCP Tools (AI)\"]";
            $m_i = 0;
            foreach (array_slice($module['mcp_tools'], 0, 5) as $tool)
            {
                $m_i++;
                $t_name = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', (string)$tool['name']);
                $lines[] = "            T{$m_i}[\"{$t_name}\"]";
            }
            if (count($module['mcp_tools']) > 5)
            {
                $rem = count($module['mcp_tools']) - 5;
                $lines[] = "            T_more[\"... еще {$rem} инструментов\"]";
            }
            $lines[] = "        end";
        }

        if ($has_tables)
        {
            $lines[] = "        subgraph SG_Tables [\"PostgreSQL Таблицы\"]";
            $t_i = 0;
            foreach (array_slice($module['tables'], 0, 5) as $tbl)
            {
                $t_i++;
                $tbl_name = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', (string)$tbl);
                $lines[] = "            TB{$t_i}[(\"{$tbl_name}\")]";
            }
            if (count($module['tables']) > 5)
            {
                $rem = count($module['tables']) - 5;
                $lines[] = "            TB_more[(\"... еще {$rem} таблиц\")]";
            }
            $lines[] = "        end";
        }

        $lines[] = "    end";

        // Связи между подграфами
        if ($has_routes && $has_ctrls)
        {
            $lines[] = "    SG_Routes --> SG_Controllers";
        }
        if ($has_ctrls && $has_tables)
        {
            $lines[] = "    SG_Controllers --> SG_Tables";
        }
        if ($has_mcp && $has_tables)
        {
            $lines[] = "    SG_MCP --> SG_Tables";
        }

        return implode("\n", $lines);
    }
}
