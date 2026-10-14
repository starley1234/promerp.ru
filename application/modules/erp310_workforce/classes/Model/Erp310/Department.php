<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp310_Department extends ORM
{
    protected $_table_name = 'erp_310_departments';
    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'parent' => array('model' => 'Erp310_Department', 'foreign_key' => 'parent_id'),
    );
    protected $_has_many = array(
        'children' => array('model' => 'Erp310_Department', 'foreign_key' => 'parent_id'),
        'assignments' => array('model' => 'Erp310_Assignment', 'foreign_key' => 'department_id'),
    );

    public static $kinds = array(
        'company' => 'Организация', 'branch' => 'Филиал', 'department' => 'Подразделение',
        'workshop' => 'Цех', 'section' => 'Участок', 'team' => 'Бригада',
    );

    public function rules()
    {
        return array(
            'code' => array(array('not_empty'), array('max_length', array(':value', 32)), array(array($this, 'unique_code'))),
            'name' => array(array('not_empty'), array('max_length', array(':value', 255))),
            'kind' => array(array('in_array', array(':value', array_keys(self::$kinds)))),
            'status' => array(array('in_array', array(':value', array('active', 'archived')))),
        );
    }

    public function unique_code($value)
    {
        $query = ORM::factory('Erp310_Department')->where(DB::expr('upper(code)'), '=', mb_strtoupper(trim($value), 'UTF-8'));
        if ($this->loaded()) $query->where('id', '<>', $this->id);
        return $query->count_all() === 0;
    }

    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : $this->kind;
    }
}
