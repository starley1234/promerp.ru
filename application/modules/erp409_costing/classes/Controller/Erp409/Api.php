<?php defined('SYSPATH') or die('No direct script access.');
class Controller_Erp409_Api extends Controller_Erp409_Main
{
 public function before(){parent::before();$this->auto_render=FALSE;$this->response->headers('Content-Type','application/json; charset=utf-8');}
 protected function json($data,$status=200){$this->response->status($status);$this->response->body(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
 public function action_orders(){if(!$this->can('erp409.view'))return $this->json(array('error'=>'Доступ запрещён'),403);return $this->json(array('items'=>$this->costing()->orders()));}
 public function action_view(){if(!$this->can('erp409.view'))return $this->json(array('error'=>'Доступ запрещён'),403);try{return $this->json(array('data'=>$this->costing()->get($this->request->param('id'))));}catch(Exception $e){return $this->json(array('error'=>$e->getMessage()),404);}}
}
