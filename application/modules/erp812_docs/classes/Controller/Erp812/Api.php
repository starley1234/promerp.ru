<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST API контроллер для документации PromERP.
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Api extends Controller
{
    public function before()
    {
        parent::before();
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    public function action_search()
    {
        $q = $this->request->query('q');
        if ( ! $q || mb_strlen(trim($q)) < 2)
        {
            $this->response->body(json_encode(array(
                'status'  => 'success',
                'query'   => (string)$q,
                'results' => array(),
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return;
        }

        $registry = Service_Erp812_DocRegistry::instance();
        $results = $registry->search($q);

        // Каталог модулей закрыт от гостей: в поиске не выдаём ссылки на то,
        // что гость всё равно не откроет (иначе подсказка ведёт на 403).
        if ( ! Auth::instance()->logged_in('login'))
        {
            $results = array_values(array_filter($results, function ($item) {
                return ! array_key_exists('type', (array) $item) OR $item['type'] !== 'module';
            }));
        }

        $this->response->body(json_encode(array(
            'status'  => 'success',
            'query'   => $q,
            'count'   => count($results),
            'results' => $results,
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function action_modules()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $modules = $registry->modules();

        $this->response->body(json_encode(array(
            'status'  => 'success',
            'count'   => count($modules),
            'modules' => array_values($modules),
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function action_module()
    {
        $id = $this->request->query('id') ?: $this->request->param('id');
        $registry = Service_Erp812_DocRegistry::instance();
        $module = $registry->module($id);

        if ( ! $module)
        {
            $this->response->status(404)->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Module not found: ' . $id,
            ), JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->response->body(json_encode(array(
            'status' => 'success',
            'module' => $module,
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function action_processes()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $processes = $registry->business_processes();

        $this->response->body(json_encode(array(
            'status'    => 'success',
            'count'     => count($processes),
            'processes' => array_values($processes),
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function action_mcp()
    {
        $registry = Service_Erp812_DocRegistry::instance();
        $modules = $registry->modules();

        $all_tools = array();
        foreach ($modules as $m_name => $mod)
        {
            if ( ! empty($mod['mcp_tools']))
            {
                foreach ($mod['mcp_tools'] as $t)
                {
                    $t['module'] = $m_name;
                    $all_tools[] = $t;
                }
            }
        }

        $this->response->body(json_encode(array(
            'status' => 'success',
            'count'  => count($all_tools),
            'tools'  => $all_tools,
        ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
