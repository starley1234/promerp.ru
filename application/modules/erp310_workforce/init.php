<?php defined('SYSPATH') or die('No direct script access.');

/** Сотрудники и организационная структура (erp310_workforce). */
Route::set('erp310_api', 'workforce/api/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Api'));
Route::set('erp310_employees', 'workforce/employees(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Employee', 'action' => 'index'));
Route::set('erp310_departments', 'workforce/departments(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Department', 'action' => 'index'));
Route::set('erp310_positions', 'workforce/positions(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Position', 'action' => 'index'));
Route::set('erp310_staffing', 'workforce/staffing(/<action>(/<id>))', array('id' => '\d+'))
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Staffing', 'action' => 'index'));
Route::set('erp310', 'workforce')
    ->defaults(array('directory' => 'Erp310', 'controller' => 'Main', 'action' => 'index'));
