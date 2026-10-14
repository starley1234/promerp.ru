<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для работы с Baselines (снимками состояния) и аудитом целостности.
 * 
 * Перенесена логика из Api.php для обеспечения неизменяемости данных 
 * и проверки цепочек хешей (hash-chain).
 *
 * @package Erp230
 */
class Controller_Erp230_Api_Baselines extends Controller_Erp230_Api {

	/**
	 * POST /erp230_api_baselines/snapshot  {object_id, code?}
	 * Создание неизменяемого снимка (звено hash-chain).
	 * Канонический путь — Service_Erp230_Consolidation::create_snapshot().
	 */
	public function action_snapshot()
	{
		$this->_handle(function () {
			$this->_require('mdm.baseline.create');

			$in  = $this->_input();
			$oid = Arr::get($in, 'object_id');
			if ( ! $oid)
				throw new Kohana_Exception('Required: object_id', NULL, 400);

			$this->_object($oid); // валидируем существование
			$code = Arr::get($in, 'code', 'V'.date('YmdHis'));

			$b = Service_Erp230_Consolidation::instance()
				->create_snapshot($oid, $this->_app_user, $code);

			return [
				'status'      => 'ok',
				'baseline_id' => $b->id,
				'code'        => $code,
				'hash'        => $b->snapshot_hash,
				'prev_hash'   => $b->prev_hash,
			];
		});
	}

	/**
	 * GET /erp230_api_baselines/list/<id>
	 * Перечень снимков объекта.
	 */
	public function action_list()
	{
		$this->_handle(function () {
			$this->_require('mdm.baseline.view');
			$obj = $this->_object($this->request->param('id'));

			$rows = ORM::factory('Erp230_Baseline')
				->where('object_id', '=', $obj->id)
				->order_by('seq', 'ASC')
				->find_all();

			$data = [];
			foreach ($rows as $b)
			{
				$data[] = [
					'id'         => $b->id,
					'seq'        => (int) $b->seq,
					'code'       => $b->code,
					'hash'       => $b->snapshot_hash,
					'prev_hash'  => $b->prev_hash,
					'actor_id'   => $b->actor_id,
					'created_at' => $b->created_at,
				];
			}

			return ['status' => 'ok', 'object_id' => $obj->id, 'baselines' => $data];
		});
	}

	/**
	 * GET /erp230_api_baselines/verify/<id>
	 * Аудит целостности hash-chain для конкретного объекта.
	 */
	public function action_verify()
	{
		$this->_handle(function () {
			$this->_require('mdm.audit.integrity');

			$obj   = $this->_object($this->request->param('id'));
			$model = ORM::factory('Erp230_Baseline');

			return [
				'status'   => 'ok',
				'is_valid' => $model->is_chain_valid($obj->id),
				'history'  => $model->verify_chain($obj->id),
			];
		});
	}

	/**
	 * GET /erp230_api_baselines/verify_chain/<id>
	 * Проверка целостности данных через Service_Erp230_GoldenRecord.
	 */
	public function action_verify_chain()
	{
		$this->_handle(function () {
			$this->_require('mdm.audit.integrity');

			$id = $this->request->param('id');
			$this->_object($id); // валидация существования

			$report = Service_Erp230_GoldenRecord::instance()->verify_integrity($id);

			return [
				'status' => 'ok',
				'report' => $report
			];
		});
	}

	/**
	 * GET /erp230_api_baselines/details/<baseline_id>
	 * Получить содержимое конкретного снимка (JSON-срез данных).
	 */
	public function action_details()
	{
		$this->_handle(function () {
			$this->_require('mdm.baseline.view');

			$id = $this->request->param('id');
			$baseline = ORM::factory('Erp230_Baseline', $id);

			if ( ! $baseline->loaded())
				throw new Kohana_Exception('Baseline not found', NULL, 404);

			// колонка в БД/ORM — snapshot (не snapshot_data)
			$snap = $baseline->snapshot;
			if ($snap === NULL OR $snap === '')
				$snap = isset($baseline->snapshot_data) ? $baseline->snapshot_data : NULL;

			return [
				'status'   => 'ok',
				'baseline' => [
					'id'            => $baseline->id,
					'object_id'     => $baseline->object_id,
					'code'          => $baseline->code,
					'snapshot_data' => $this->_decode($snap),
					'hash'          => $baseline->snapshot_hash,
					'created_at'    => $baseline->created_at,
				],
			];
		});
	}
}