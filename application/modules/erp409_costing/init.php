<?php defined('SYSPATH') or die('No direct script access.');
Route::set('erp409_costing_api','costing/api/<action>(/<id>)',array('id'=>'[^/]+'))->defaults(array('directory'=>'Erp409','controller'=>'Api','action'=>'orders'));
Route::set('erp409_costing_calculate','costing/calculate/<id>',array('id'=>'\d+'))->defaults(array('directory'=>'Erp409','controller'=>'Index','action'=>'calculate'));
Route::set('erp409_costing_approve','costing/approve/<id>',array('id'=>'\d+'))->defaults(array('directory'=>'Erp409','controller'=>'Index','action'=>'approve'));
Route::set('erp409_costing_view','costing/view/<id>',array('id'=>'[^/]+'))->defaults(array('directory'=>'Erp409','controller'=>'Index','action'=>'view'));
Route::set('erp409_costing','costing')->defaults(array('directory'=>'Erp409','controller'=>'Index','action'=>'index'));
