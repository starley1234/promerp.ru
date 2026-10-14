<?php defined('SYSPATH') or die('No direct script access.');

/** Shared shell and top navigation for EBOM/MBOM workbench. */
class Controller_Erp231_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Составы EBOM / MBOM';
        $this->_meta['description'] = 'Версионируемые конструкторские и производственные составы изделий.';
        $this->_meta['keywords']    = 'EBOM, MBOM, состав изделия, ревизия, производство';

        $this->set_menu(array(
            array('title'=>'Все составы','href'=>'/product-structures','icon'=>'fas fa-sitemap','acl'=>'erp231.view'),
            array('title'=>'Новый состав','href'=>'/product-structures#new-structure','icon'=>'fas fa-plus-circle','acl'=>'erp231.manage'),
            array('title'=>'Техкарты','href'=>'/tech_cards','icon'=>'fas fa-diagram-project','acl'=>'erp228.view'),
            array('title'=>'Заказы','href'=>'/production/orders','icon'=>'fas fa-gears','acl'=>'erp402_production.view'),
        ));
    }
}
