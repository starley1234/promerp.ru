<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp230_OrgUnit extends Model_Erp230_Main {

	protected $_table_name  = 'org_units';
	protected $_primary_key = 'id';

	/** path считает триггер trg_org_path — забираем через RETURNING. */
	protected $_returning = ['path'];

	protected $_table_columns = [
		'id'        => ['type' => 'string'],
		'parent_id' => ['type' => 'string'],
		'name'      => ['type' => 'string'],
		'path'      => ['type' => 'string'],
	];
}