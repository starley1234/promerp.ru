<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp230_Dictionary extends Model_Erp230_Main {

	protected $_table_name  = 'types';
	protected $_primary_key = 'id';

	protected $_table_columns = [
		'id'        => ['type' => 'string'],
		'parent_id' => ['type' => 'string'],
		'name'      => ['type' => 'string'],
		'path'      => ['type' => 'string'],
	];

	public function set_table_name($name)
	{
		$this->_table_name = $name;
		return $this;
	}
}