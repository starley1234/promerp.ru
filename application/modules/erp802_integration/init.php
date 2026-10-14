<?php defined('SYSPATH') or die('No direct script access.');
Route::set('erp802_api','integration/api/<action>(/<id>)',array('id'=>'\d+'))->defaults(array('directory'=>'Erp802','controller'=>'Api','action'=>'packages'));
Route::set('erp802','integration(/<action>(/<id>))',array('id'=>'\d+'))->defaults(array('directory'=>'Erp802','controller'=>'Integration','action'=>'index'));
