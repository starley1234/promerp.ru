<?php defined('SYSPATH') or die('No direct script access.');

Route::set('erp231_api', 'product-structures/api/<action>(/<id>)', array('id'=>'\d+'))
    ->defaults(array('directory'=>'Erp231','controller'=>'Api','action'=>'search'));
Route::set('erp231', 'product-structures(/<action>(/<id>))', array('id'=>'\d+'))
    ->defaults(array('directory'=>'Erp231','controller'=>'Structure','action'=>'index'));
