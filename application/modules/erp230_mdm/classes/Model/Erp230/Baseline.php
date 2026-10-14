<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Неизменяемый снимок (Baseline) с hash-chain. Таблица baselines (партиционирована).
 */
class Model_Erp230_Baseline extends Model_Erp230_Main {

	protected $_table_name  = 'baselines';
	protected $_primary_key = 'id';
	protected $_uuid_pk     = TRUE;

	protected $_created_column = ['column' => 'created_at', 'format' => 'Y-m-d H:i:s'];

	/** snapshot_hash / prev_hash / seq вычисляет триггер tg_baseline_chain — забираем через RETURNING. */
	protected $_returning = ['snapshot_hash', 'prev_hash', 'seq'];

	protected $_table_columns = [
		'id'             => ['type' => 'string'],
		'seq'            => ['type' => 'int'],
		'object_id'      => ['type' => 'string'],
		'org_path'       => ['type' => 'string'],
		'code'           => ['type' => 'string'],
		'snapshot'       => ['type' => 'string'],
		'snapshot_hash'  => ['type' => 'string'],
		'prev_hash'      => ['type' => 'string'],
		'compliance_ref' => ['type' => 'string'],
		'signature'      => ['type' => 'string'],
		'signed_hash'    => ['type' => 'string'],
		'actor_id'       => ['type' => 'string'],
		'created_at'     => ['type' => 'string'],
	];

	protected $_belongs_to = [
		'object' => ['model' => 'Erp230_Object', 'foreign_key' => 'object_id'],
	];

	/** jsonb-поля можно передавать массивами — сериализуем перед вставкой. */
	protected function before_create()
	{
		if (is_array($this->_object['snapshot']))
			$this->snapshot = json_encode($this->_object['snapshot'], JSON_UNESCAPED_UNICODE);

		if ( ! empty($this->_object['compliance_ref']) AND is_array($this->_object['compliance_ref']))
			$this->compliance_ref = json_encode($this->_object['compliance_ref']);
	}

	public function is_chain_valid($object_id)
	{
		$v = DB::query(Database::SELECT, 'SELECT is_chain_valid(:id) AS ok')
			->param(':id', $object_id)
			->execute($this->_db)
			->get('ok');

		return $this->_pg_bool($v);
	}

	public function verify_chain($object_id)
	{
		return DB::query(Database::SELECT, 'SELECT * FROM verify_baseline_chain(:id)')
			->param(':id', $object_id)
			->execute($this->_db)
			->as_array();
	}
}