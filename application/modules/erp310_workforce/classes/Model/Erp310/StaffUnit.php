<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp310_StaffUnit extends ORM
{
    protected $_table_name = 'erp_310_staff_units';

    public function rules()
    {
        return array(
            'department_id' => array(array('not_empty'), array('digit')),
            'position_id' => array(array('not_empty'), array('digit')),
            'planned_fte' => array(array('not_empty'), array('numeric')),
            'valid_from' => array(array('not_empty'), array('date')),
            'status' => array(array('in_array', array(':value', array('active','archived')))),
        );
    }
}
