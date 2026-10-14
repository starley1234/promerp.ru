<?php defined('SYSPATH') or die('No direct script access.');
Route::set('erp412_api','traceability/api/<action>(/<id>)',array('id'=>'[0-9a-fA-F-]+'))->defaults(array('directory'=>'Erp412','controller'=>'Api','action'=>'lots'));
Route::set('erp412','traceability(/<action>(/<id>))',array('id'=>'[0-9a-fA-F-]+'))->defaults(array('directory'=>'Erp412','controller'=>'Traceability','action'=>'index'));
// Business listeners are seeded in migration 003, so they survive EventBus
// recreation and worker/HTTP process boundaries without duplicate callbacks.
