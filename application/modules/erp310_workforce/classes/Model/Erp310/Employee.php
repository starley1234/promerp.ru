<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp310_Employee extends ORM
{
    protected $_table_name = 'erp_310_employees';
    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');
    protected $_belongs_to = array('user' => array('model' => 'User', 'foreign_key' => 'user_id'));
    protected $_has_many = array('assignments' => array('model' => 'Erp310_Assignment', 'foreign_key' => 'employee_id'));

    public static $statuses = array('active' => 'Работает', 'leave' => 'Отсутствует', 'dismissed' => 'Уволен');

    public function rules()
    {
        return array(
            'personnel_no' => array(array('not_empty'), array('max_length', array(':value', 32)), array(array($this, 'unique_number'))),
            'last_name' => array(array('not_empty'), array('max_length', array(':value', 128))),
            'first_name' => array(array('not_empty'), array('max_length', array(':value', 128))),
            'email' => array(array('email', array(':value'))),
            'status' => array(array('in_array', array(':value', array_keys(self::$statuses)))),
        );
    }

    public function unique_number($value)
    {
        $query = ORM::factory('Erp310_Employee')->where(DB::expr('upper(personnel_no)'), '=', mb_strtoupper(trim($value), 'UTF-8'));
        if ($this->loaded()) $query->where('id', '<>', $this->id);
        return $query->count_all() === 0;
    }

    public function full_name()
    {
        return trim($this->last_name.' '.$this->first_name.' '.$this->middle_name);
    }

    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }
}
