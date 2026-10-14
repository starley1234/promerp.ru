<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp412_Traceability extends Controller_Erp412_Main
{
 protected function s(){return Service_Erp412_Traceability::instance();}protected function actor(){return $this->_current_user?(int)$this->_current_user->id:NULL;}protected function allow($a){if(!$this->can($a))throw new HTTP_Exception_403('Доступ запрещён');}protected function csrf(){if(!Security::check((string)$this->request->post('csrf_token')))throw new Kohana_Exception('Форма устарела, повторите действие');}
 public function action_index(){
  $this->allow('erp412.view');$this->set_title('Прослеживаемость партий');$error=NULL;if($this->request->method()===Request::POST){$this->allow('erp412.manage');try{$this->csrf();$lot=$this->s()->create_lot($this->request->post(),$this->actor());$this->redirect(Route::url('erp412',array('action'=>'view','id'=>$lot['uuid'])));}catch(Throwable $e){$error=$e->getMessage();}}
  $this->template->content=View::factory('erp412/index')->set('lots',$this->s()->search($this->request->query(),100))->set('error',$error)->set('can_manage',$this->can('erp412.manage'));
 }
 public function action_label(){
  $this->allow('erp412.view');$id=$this->request->param('id');if(!$id)throw new HTTP_Exception_404('Партия не найдена');$lot=$this->s()->get($id);$this->auto_render=FALSE;$this->response->body(View::factory('erp412/label')->set('lot',$lot)->set('scan_url',Route::url('erp412',array('action'=>'view','id'=>$lot['uuid']),TRUE)));
 }
 public function action_view(){
  $this->allow('erp412.view');$id=$this->request->param('id');if(!$id){$this->redirect(Route::url('erp412'));return;}$error=NULL;if($this->request->method()===Request::POST){try{$this->csrf();$action=(string)($this->request->post('form_action')?:'status');if($action==='production'){$this->allow('erp412.manage');$this->s()->record_production($this->request->post('input_lot_id'),$id,$this->request->post('order_id'),$this->request->post('qty'),$this->actor());}else{$status=(string)$this->request->post('status');$this->allow($status==='recalled'?'erp412.recall':'erp412.manage');$this->s()->transition($id,$status,$this->request->post('reason'),$this->actor());}}catch(Throwable $e){$error=$e->getMessage();}}
  $lot=$this->s()->get($id);$this->set_title('Партия '.$lot['lot_number']);$this->template->content=View::factory('erp412/view')->set('lot',$lot)->set('graph',$this->s()->genealogy($lot['id']))->set('affected',$this->s()->affected_outputs($lot['id']))->set('error',$error)->set('can_manage',$this->can('erp412.manage'))->set('can_recall',$this->can('erp412.recall'));
 }
}
