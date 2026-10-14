<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp802_Integration extends Controller_Erp802_Main
{
    protected function s(){return Service_Erp802_Integration::instance();}
    protected function actor(){return $this->_current_user?(int)$this->_current_user->id:NULL;}
    protected function allow($acl){if(!$this->can($acl))throw new HTTP_Exception_403('Доступ запрещён');}
    public function action_index()
    {
        $this->allow('erp802.view');$this->set_title('Интеграционные пакеты');$message=NULL;$error=NULL;
        if($this->request->method()===Request::POST){$this->allow('erp802.import');try{if(!Security::check((string)$this->request->post('csrf_token')))throw new Kohana_Exception('Форма устарела, повторите отправку');$file=Arr::get($_FILES,'package');if(!$file OR $file['error']!==UPLOAD_ERR_OK)throw new Kohana_Exception('Выберите CSV или JSON файл');$format=mb_strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));$p=$this->s()->receive_and_process($this->request->post('source_code'),'items',$format,file_get_contents($file['tmp_name']),array('dry_run'=>Erp::bool($this->request->post('dry_run')),'external_batch_id'=>$this->request->post('external_batch_id')),$this->actor());$this->redirect(Route::url('erp802',array('action'=>'view','id'=>$p['id'])));}catch(Throwable $e){$error=$e->getMessage();}}
        $this->template->content=View::factory('erp802/index')->set('packages',$this->s()->packages($this->request->query(),100))->set('sources',$this->s()->sources())->set('error',$error)->set('message',$message)->set('can_import',$this->can('erp802.import'));
    }
    public function action_view()
    {
        $this->allow('erp802.view');$id=(int)$this->request->param('id');if($id<1){$this->redirect(Route::url('erp802'));return;}$error=NULL;if($this->request->method()===Request::POST){$this->allow('erp802.retry');try{if(!Security::check((string)$this->request->post('csrf_token')))throw new Kohana_Exception('Форма устарела');$this->s()->replay($id,$this->actor());}catch(Throwable $e){$error=$e->getMessage();}}
        $p=$this->s()->package($id);$this->set_title('Пакет #'.$id);$this->template->content=View::factory('erp802/view')->set('package',$p)->set('rows',$this->s()->package_rows($id))->set('error',$error)->set('can_retry',$this->can('erp802.retry'));
    }
}
