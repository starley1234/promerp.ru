<?php defined('SYSPATH') or die('No direct script access.');

/** JSON boundary for EBOM/MBOM workbenches and integrations. */
class Controller_Erp231_Api extends Controller_Erp100_Base
{
    public $auto_render=FALSE;
    protected function service(){return Service_Erp231_ProductStructure::instance();}
    protected function body(){ $v=json_decode((string)$this->request->body(),TRUE); return is_array($v)?$v:(array)$this->request->post(); }
    protected function require_method($method){if($this->request->method()!==$method)throw (new HTTP_Exception_405('Метод не поддерживается'))->allowed(array($method));}
    protected function require_acl($acl){if(!$this->can($acl))throw new HTTP_Exception_403('Доступ запрещён');}
    protected function actor(){return $this->_current_user?(int)$this->_current_user->id:NULL;}
    protected function json($data,$status=200){$this->response->status($status)->headers('Content-Type','application/json; charset=utf-8')->body(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    protected function respond($callback){try{$this->json(array('status'=>'ok','data'=>$callback()));}catch(HTTP_Exception $e){throw $e;}catch(Exception $e){$this->json(array('status'=>'error','message'=>$e->getMessage()),422);}}

    public function action_search(){ $this->respond(function(){ $this->require_acl('erp231.view'); return $this->service()->search($this->request->query(),min(200,max(1,(int)($this->request->query('limit')?:50))),max(0,(int)($this->request->query('offset')?:0))); }); }
    public function action_reference(){ $this->require_acl('erp231.view');$q=trim((string)$this->request->query('q'));$rows=DB::query(Database::SELECT,"SELECT id,code,name FROM erp_227_objects WHERE is_latest=true AND is_deleted=false AND (:q='' OR code ILIKE '%'||:q||'%' OR name ILIKE '%'||:q||'%') ORDER BY name LIMIT 30")->param(':q',$q)->execute()->as_array();$items=array();foreach($rows as $r)$items[]=array('id'=>(int)$r['id'],'label'=>$r['code'].' · '.$r['name'],'context'=>'Номенклатура');$this->json(array('items'=>$items)); }
    public function action_get(){ $this->respond(function(){ $this->require_acl('erp231.view'); return $this->service()->get((int)$this->request->param('id')); }); }
    public function action_validate(){ $this->respond(function(){ $this->require_acl('erp231.view'); return $this->service()->validate((int)$this->request->param('id')); }); }
    public function action_create(){ $this->respond(function(){ $this->require_method(Request::POST);$this->require_acl('erp231.manage');return $this->service()->create($this->body(),$this->actor()); }); }
    public function action_add_line(){ $this->respond(function(){ $this->require_method(Request::POST);$this->require_acl('erp231.manage');$d=$this->body();$id=(int)Arr::get($d,'structure_id',$this->request->param('id'));return array('line_id'=>$this->service()->add_line($id,$d,$this->actor())); }); }
    public function action_review(){ $this->respond(function(){ $this->require_method(Request::POST);$this->require_acl('erp231.manage');return $this->service()->submit_review((int)$this->request->param('id'),$this->actor()); }); }
    public function action_copy_mbom(){ $this->respond(function(){ $this->require_method(Request::POST);$this->require_acl('erp231.manage');return $this->service()->copy_ebom_to_mbom((int)$this->request->param('id'),$this->body(),$this->actor()); }); }
    public function action_approve(){ $this->respond(function(){ $this->require_method(Request::POST);$this->require_acl('erp231.approve');return $this->service()->approve((int)$this->request->param('id'),$this->actor()); }); }
}
