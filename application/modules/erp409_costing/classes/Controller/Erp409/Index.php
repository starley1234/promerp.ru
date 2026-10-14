<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp409_Index extends Controller_Erp409_Main
{
 public function action_index(){if(!$this->can('erp409.view'))throw new HTTP_Exception_403('Доступ запрещён');$this->template->content=View::factory('erp409/index')->set('orders',$this->costing()->orders())->set('can_calculate',$this->can('erp409.calculate'));}
 public function action_view(){if(!$this->can('erp409.view'))throw new HTTP_Exception_403('Доступ запрещён');try{$calc=$this->costing()->get($this->request->param('id'));}catch(Exception $e){throw new HTTP_Exception_404('Калькуляция не найдена');}$this->template->content=View::factory('erp409/view')->set('calc',$calc)->set('history',$this->costing()->history($calc['order_id']))->set('can_calculate',$this->can('erp409.calculate'))->set('can_approve',$this->can('erp409.approve'));}
 public function action_calculate(){if(!$this->can('erp409.calculate'))throw new HTTP_Exception_403('Доступ запрещён');if($this->request->method()!==Request::POST)$this->redirect('/costing');$calc=$this->costing()->calculate((int)$this->request->param('id'),$this->actor());$this->redirect('/costing/view/'.$calc['id']);}
 public function action_approve(){if(!$this->can('erp409.approve'))throw new HTTP_Exception_403('Доступ запрещён');if($this->request->method()!==Request::POST)$this->redirect('/costing');$calc=$this->costing()->approve($this->request->param('id'),$this->actor());$this->redirect('/costing/view/'.$calc['id']);}
}
