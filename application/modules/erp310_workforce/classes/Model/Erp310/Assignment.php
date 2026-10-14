<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp310_Assignment extends ORM
{
    protected $_table_name = 'erp_310_assignments';
    protected $_belongs_to = array(
        'employee' => array('model' => 'Erp310_Employee', 'foreign_key' => 'employee_id'),
        'department' => array('model' => 'Erp310_Department', 'foreign_key' => 'department_id'),
        'position' => array('model' => 'Erp310_Position', 'foreign_key' => 'position_id'),
        'manager' => array('model' => 'Erp310_Employee', 'foreign_key' => 'manager_id'),
    );
}
