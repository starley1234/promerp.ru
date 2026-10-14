<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Архитектурный гид и структурные схемы PromERP.
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Architecture extends Controller_Erp812_Main
{
    public function action_index()
    {
        $this->_meta['title'] = 'Системная архитектура PromERP';

        $registry = Service_Erp812_DocRegistry::instance();
        $categories = $registry->categories();
        $modules = $registry->modules();

        $view = View::factory('erp812/architecture');
        $view->categories = $categories;
        $view->modules = $modules;

        $this->template->content = $view;
    }
}
