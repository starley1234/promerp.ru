<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реестр уникальных кодов объектов. Таблица object_codes.
 * Используется для обеспечения уникальности комбинации master_code + type_id + org_id
 * и быстрого поиска UUID объекта по бизнес-ключам.
 * Синхронизируется триггерами таблицы objects.
 */
class Model_Erp230_Code extends Model_Erp230_Main {

	protected $_table_name  = 'object_codes';
	
	/**
	 * Первичный ключ составной (master_code, type_id, org_id).
	 * В Kohana ORM штатно поддерживается только одиночный PK,
	 * поэтому для операций CRUD через ORM этот класс используется ограниченно,
	 * в основном для поиска (find_all) и валидации.
	 */
	protected $_primary_key = 'master_code';

	protected $_table_columns = [
		'master_code' => ['type' => 'string'],
		'type_id'     => ['type' => 'string'],
		'org_id'      => ['type' => 'string'],
		'object_id'   => ['type' => 'string'], // UUID
	];

	protected $_belongs_to = [
		'object' => ['model' => 'Erp230_Object',  'foreign_key' => 'object_id'],
		'type'   => ['model' => 'Erp230_Type',    'foreign_key' => 'type_id'],
		'org'    => ['model' => 'Erp230_OrgUnit', 'foreign_key' => 'org_id'],
	];

	public function rules()
	{
		return [
			'master_code' => [['not_empty'], ['max_length', [':value', 255]]],
			'type_id'     => [['not_empty'], ['max_length', [':value', 64]]],
			'org_id'      => [['not_empty'], ['max_length', [':value', 64]]],
			'object_id'   => [['not_empty']],
		];
	}

	/**
	 * Поиск UUID объекта по бизнес-ключам.
	 *
	 * @param  string $master_code
	 * @param  string $type_id
	 * @param  string $org_id
	 * @return string|NULL UUID объекта
	 */
	public static function get_object_id($master_code, $type_id, $org_id)
	{
		$res = DB::select('object_id')
			->from('object_codes')
			->where('master_code', '=', $master_code)
			->where('type_id', '=', $type_id)
			->where('org_id', '=', $org_id)
			->execute(Database::instance('mdm'))
			->current();

		return $res ? $res['object_id'] : NULL;
	}

	/**
	 * Проверка существования кода (регистронезависимая, согласно индексу uk_object_codes_upper).
	 *
	 * @param  string $master_code
	 * @param  string $type_id
	 * @param  string $org_id
	 * @return bool
	 */
	public static function code_exists($master_code, $type_id, $org_id)
	{
		$count = DB::select([DB::expr('COUNT(*)'), 'total'])
			->from('object_codes')
			->where(DB::expr('UPPER(master_code)'), '=', strtoupper($master_code))
			->where('type_id', '=', $type_id)
			->where('org_id', '=', $org_id)
			->execute(Database::instance('mdm'))
			->get('total');

		return (int) $count > 0;
	}
}