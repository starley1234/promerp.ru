<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp310_Position extends ORM
{
    protected $_table_name = 'erp_310_positions';
    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');
    protected $_belongs_to = array('department' => array('model' => 'Erp310_Department', 'foreign_key' => 'department_id'));

    public static $categories = array(
        'director' => 'Руководитель организации', 'manager' => 'Руководитель',
        'specialist' => 'Специалист', 'worker' => 'Рабочий', 'support' => 'Обеспечение',
    );

    public function rules()
    {
        return array(
            'code' => array(array('not_empty'), array('max_length', array(':value', 32)), array(array($this, 'unique_code'))),
            'name' => array(array('not_empty'), array('max_length', array(':value', 255))),
            'category' => array(array('in_array', array(':value', array_keys(self::$categories)))),
        );
    }

    public function unique_code($value)
    {
        $query = ORM::factory('Erp310_Position')
            ->where(DB::expr('upper(code)'), '=', mb_strtoupper(trim($value), 'UTF-8'));
        if ($this->loaded()) $query->where('id', '<>', $this->id);
        return $query->count_all() === 0;
    }

    public function category_label()
    {
        return isset(self::$categories[$this->category]) ? self::$categories[$this->category] : $this->category;
    }
}
