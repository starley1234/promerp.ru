<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Атрибут объекта (EAV, time-travel). Таблица object_properties (партиционирована).
 * Запись строго через хранимую функцию upsert_property (Trust Score).
 */
class Model_Erp230_Property extends Model_Erp230_Main {

	protected $_table_name  = 'object_properties';
	protected $_primary_key = 'id';

	protected $_created_column = ['column' => 'created_at', 'format' => 'Y-m-d H:i:s'];

	protected $_table_columns = [
		'id'           => ['type' => 'int'],
		'object_id'    => ['type' => 'string'],
		'org_path'     => ['type' => 'string'],
		'key'          => ['type' => 'string'],
		'value'        => ['type' => 'string'],
		'uom_code'     => ['type' => 'string'],
		'source_id'    => ['type' => 'string'],
		'actor_id'     => ['type' => 'string'],
		'confidence'   => ['type' => 'float'],
		'valid_period' => ['type' => 'string'],
		'is_current'   => ['type' => 'bool'],
		'created_at'   => ['type' => 'string'],
	];

	protected $_belongs_to = [
		'object' => ['model' => 'Erp230_Object', 'foreign_key' => 'object_id'],
		'source' => ['model' => 'Erp230_Source', 'foreign_key' => 'source_id'],
	];

	/**
	 * Безопасный upsert с проверкой Trust Score на стороне БД.
	 *
	 * @return string  'ok' | 'rejected_low_trust'
	 */
	public function upsert_safe($object_id, $key, $value, $source_id, $uom = NULL, $actor_id = NULL)
	{
		return DB::query(Database::SELECT,
			'SELECT upsert_property(:oid, :key, CAST(:val AS jsonb), :src, :uom, :actor) AS status')
			->param(':oid', $object_id)
			->param(':key', $key)
			->param(':val', json_encode($value, JSON_UNESCAPED_UNICODE))
			->param(':src', $source_id)
			->param(':uom', $uom)
			->param(':actor', $actor_id)
			->execute($this->_db)
			->get('status');
	}
}