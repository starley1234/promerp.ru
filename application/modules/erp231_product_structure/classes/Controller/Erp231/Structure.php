<?php defined('SYSPATH') or die('No direct script access.');
/** Human workbench for the same EBOM/MBOM service used by API and MCP. */
class Controller_Erp231_Structure extends Controller_Erp231_Main
{
 protected function s(){return Service_Erp231_ProductStructure::instance();}protected function actor(){return $this->_current_user?(int)$this->_current_user->id:NULL;}protected function allow($a){if(!$this->can($a))throw new HTTP_Exception_403('Доступ запрещён');}protected function csrf(){if(!Security::check((string)$this->request->post('csrf_token')))throw new Kohana_Exception('Форма устарела, повторите действие');}
 public function action_index(){
  $this->allow('erp231.view');$this->set_title('Составы EBOM / MBOM');$error=NULL;if($this->request->method()===Request::POST){$this->allow('erp231.manage');$created=NULL;try{$this->csrf();$created=$this->s()->create($this->request->post(),$this->actor());}catch(Throwable $e){$error=$e->getMessage();}
   // Переход — вне try: redirect() в Kohana бросает исключение, и перехват Throwable принял бы его за ошибку формы.
   if($created!==NULL){$this->redirect(Route::url('erp231',array('action'=>'view','id'=>$created['id'])));}}
  $result=$this->s()->search($this->request->query(),100);$this->template->content=View::factory('erp231/index')->set('rows',$result['rows'])->set('total',$result['total'])->set('error',$error)->set('can_manage',$this->can('erp231.manage'));
 }
 public function action_view(){
  $this->allow('erp231.view');$id=(int)$this->request->param('id');if($id<1){$this->redirect(Route::url('erp231'));return;}$error=NULL;
  if($this->request->method()===Request::POST){try{$this->csrf();$action=(string)$this->request->post('form_action');if($action==='add_line'){$this->allow('erp231.manage');$this->s()->add_line($id,$this->request->post(),$this->actor());}elseif($action==='review'){$this->allow('erp231.manage');$this->s()->submit_review($id,$this->actor());}elseif($action==='approve'){$this->allow('erp231.approve');$this->s()->approve($id,$this->actor());}else throw new Kohana_Exception('Неизвестное действие');}catch(Throwable $e){$error=$e->getMessage();}}
  $row=$this->s()->get($id);$check=$this->s()->validate($id);$this->set_title($row['kind'].' '.$row['revision'].' · '.$row['product_name']);$this->template->content=View::factory('erp231/view')->set('structure',$row)->set('check',$check)->set('error',$error)->set('can_manage',$this->can('erp231.manage'))->set('can_approve',$this->can('erp231.approve'));
 }
}
