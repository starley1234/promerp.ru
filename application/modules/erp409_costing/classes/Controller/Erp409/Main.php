<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp409_Main extends Controller_Base
{
 public function before(){parent::before();$this->_meta['title']='Себестоимость';$this->_meta['description']='Объяснимая плановая и фактическая себестоимость производственных заказов.';$this->set_menu(array(array('title'=>'Калькуляции','href'=>'/costing','icon'=>'fas fa-calculator','acl'=>'erp409.view')));}
 protected function costing(){return Service_Erp409_Costing::instance();}
 protected function actor(){try{$u=Auth::instance()->get_user();return $u&&$u->loaded()?(int)$u->id:NULL;}catch(Exception $e){return NULL;}}
}
