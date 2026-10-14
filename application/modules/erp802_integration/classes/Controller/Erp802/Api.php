<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp802_Api extends Controller_Erp100_Base
{
    public $auto_render=FALSE;
    protected function s(){return Service_Erp802_Integration::instance();}
    protected function body(){$v=json_decode((string)$this->request->body(),TRUE);return is_array($v)?$v:(array)$this->request->post();}
    protected function actor(){return $this->_current_user?(int)$this->_current_user->id:NULL;}
    protected function acl($v){if(!$this->can($v))throw new HTTP_Exception_403('Доступ запрещён');}
    protected function method($v){if($this->request->method()!==$v)throw (new HTTP_Exception_405)->allowed(array($v));}
    protected function out($fn){try{$this->response->headers('Content-Type','application/json; charset=utf-8')->body(json_encode(array('status'=>'ok','data'=>$fn()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}catch(HTTP_Exception $e){throw $e;}catch(Throwable $e){$this->response->status(422)->body(json_encode(array('status'=>'error','message'=>$e->getMessage()),JSON_UNESCAPED_UNICODE));}}
    public function action_sources(){$this->out(function(){$this->acl('erp802.view');return $this->s()->sources();});}
    public function action_packages(){$this->out(function(){$this->acl('erp802.view');return $this->s()->packages($this->request->query(),$this->request->query('limit')?:50);});}
    public function action_package(){$this->out(function(){$this->acl('erp802.view');$id=(int)$this->request->param('id');return array('package'=>$this->s()->package($id),'rows'=>$this->s()->package_rows($id));});}
    public function action_import(){$this->out(function(){$this->method(Request::POST);$this->acl('erp802.import');$d=$this->body();return $this->s()->receive_and_process(Arr::get($d,'source_code'),Arr::get($d,'entity_kind','items'),Arr::get($d,'format','json'),(string)Arr::get($d,'content'),array('dry_run'=>Erp::bool(Arr::get($d,'dry_run',FALSE)),'external_batch_id'=>Arr::get($d,'external_batch_id')),$this->actor());});}
    public function action_retry(){$this->out(function(){$this->method(Request::POST);$this->acl('erp802.retry');return $this->s()->replay((int)$this->request->param('id'),$this->actor());});}
}
