<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST API модуля MDM / Digital Twin (ERP230).
 *
 * Разделы:
 *   - Objects       : CRUD мастер-записей через ObjectFactory, lifecycle, merge
 *   - Properties    : атрибуты с Trust Score, история, пакетное обновление
 *   - Xref          : кросс-референсы внешних систем
 *   - Consolidate   : приём данных из внешних источников
 *   - Twin          : телеметрия, паспорт двойника
 *   - Search        : текстовый и семантический поиск
 *   - Dictionaries  : справочники (org_units, sources, uom)
 *
 * @package Erp230
 * @version 4.0.1
 */
class Controller_Erp230_Api extends Controller_Erp230_Main {

	const DEFAULT_SOURCE = 'manual';

	// =======================================================================
	//  Инфраструктура
	// =======================================================================

	protected function _db()
	{
		return Database::instance('mdm');
	}

	public function before()
	{
		parent::before();

		$action   = $this->request->action();
		$resolved = $this->_resolve_rest_action(
			$action,
			strtoupper($this->request->method()),
			$this->request->param('id') !== NULL
		);

		if ($resolved !== $action)
			$this->request->action($resolved);
	}

	protected function _resolve_rest_action($action, $method, $has_id)
	{
		switch ($action)
		{
			case 'object':
			case 'objects':
				if ($method === 'POST')                       return 'create_object';
				if ($method === 'PATCH' OR $method === 'PUT') return 'update_object';
				if ($method === 'DELETE')                     return 'delete_object';
				return $has_id ? 'object' : 'objects';

			case 'properties':
				return ($method === 'POST') ? 'set_properties' : 'properties';
		}

		return $action;
	}

	protected function _handle($fn)
	{
		try
		{
			$this->_json_response($fn());
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->_json_response([
				'status' => 'error',
				'code'   => 'validation_failed',
				'errors' => $e->errors('models'),
			], 422);
		}
		catch (Kohana_Exception $e)
		{
			$code = $e->getCode();
			$data = ['status' => 'error', 'message' => $e->getMessage()];
			
			// Обработка специфичных кодов ошибок для ObjectFactory (409 Conflict)
			if ($code === 409)
			{
				$data['code'] = $e->getMessage(); // duplicate_master_code, duplicate_identity и т.д.
			}

			$this->_json_response($data, ($code >= 400 AND $code < 600) ? $code : 400);
		}
		catch (Exception $e)
		{
			$this->_json_response([
				'status' => 'error', 
				'message' => $e->getMessage()
			], 500);
		}
	}

	protected function _require($permission)
	{
		return TRUE;
	}

	protected function _invalidate_dashboard()
	{
		try { Cache::instance()->delete('erp230_dashboard_stats'); } catch (Exception $e) {}
	}

	protected function _object($id)
	{
		$obj = ORM::factory('Erp230_Object', $id);
		if ( ! $obj->loaded())
			throw new Kohana_Exception('Object :id not found', [':id' => $id], 404);

		return $obj;
	}

	protected function _input()
	{
		$data = json_decode($this->request->body(), TRUE);
		if (json_last_error() !== JSON_ERROR_NONE AND $this->request->body() !== '')
			throw new Kohana_Exception('Malformed JSON body', NULL, 400);

		return (array) $data;
	}

	protected function _serialize_object(ORM $o, $with_name = FALSE)
	{
		$data = [
			'id'          => $o->id,
			'type_id'     => $o->type_id,
			'org_id'      => $o->org_id,
			'org_path'    => $o->org_path,
			'master_code' => $o->master_code,
			'state'       => $o->state,
			'merged_into' => $o->merged_into,
			'created_at'  => $o->created_at,
			'updated_at'  => $o->updated_at,
		];

		if ($with_name) $data['display_name'] = $o->display_name();

		return $data;
	}

	protected function _decode($value)
	{
		if ( ! is_string($value)) return $value;
		$d = json_decode($value, TRUE);
		return (json_last_error() === JSON_ERROR_NONE) ? $d : $value;
	}

	// =======================================================================
	//  OBJECTS — мастер-записи (Golden Record)
	// =======================================================================

	public function action_objects()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$limit  = min(max((int) $this->request->query('limit') ?: 25, 1), 200);
			$offset = max((int) $this->request->query('offset'), 0);

			// Список реестра считает сервис GoldenRecord: та же выборка, что и в UI,
			// с учётом иерархии типов и денормализованного состояния.
			$registry = Service_Erp230_GoldenRecord::instance()->get_registry_list([
				'type_id' => $this->request->query('type'),
				'org_id'  => $this->request->query('org'),
				'state'   => $this->request->query('state'),
				'search'  => $this->request->query('q'),
				'limit'   => $limit,
				'page'    => (int) floor($offset / $limit) + 1,
			]);

			$data = [];

			foreach ($registry['items'] as $item)
			{
				$data[] = $this->_serialize_object($item);
			}

			return [
				'status' => 'ok',
				'total'  => (int) $registry['total'],
				'limit'  => $limit,
				'offset' => $offset,
				'data'   => $data,
			];
		});
	}

	public function action_object()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$obj  = $this->_object($this->request->param('id'));
			$with = array_filter(explode(',', (string) $this->request->query('with')));
			$out  = ['status' => 'ok', 'object' => $this->_serialize_object($obj, TRUE)];

			if (in_array('properties', $with))
				$out['properties'] = $obj->current_properties();

			return $out;
		});
	}

	public function action_create_object()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.create');

			$in = $this->_input();
			
			// Использование ObjectFactory для централизованного создания и проверки дублей
			$obj = Service_Erp230_ObjectFactory::instance()->create($in, [
				'source_id'      => Arr::get($in, 'source_id', self::DEFAULT_SOURCE),
				'remote_id'      => Arr::get($in, 'remote_id'),
				'allow_existing' => FALSE,
			]);

			$this->_invalidate_dashboard();

			return [
				'status' => 'ok', 
				'object' => $this->_serialize_object($obj, TRUE)
			];
		});
	}

	public function action_update_object()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$obj = $this->_object($this->request->param('id'));
			$in  = $this->_input();

			// Проверка уникальности кода при смене, если код передан
			if (isset($in['master_code']) AND $in['master_code'] !== $obj->master_code)
			{
				Service_Erp230_DuplicateGuard::instance()->assert_unique_code(
					$in['master_code'], 
					Arr::get($in, 'type_id', $obj->type_id), 
					Arr::get($in, 'org_id', $obj->org_id), 
					$obj->id
				);
			}

			foreach (['master_code', 'org_id', 'type_id'] as $f)
			{
				if (array_key_exists($f, $in))
					$obj->$f = $in[$f];
			}
			$obj->save();

			if (array_key_exists('state', $in) AND $in['state'] !== $obj->state)
			{
				$obj->transition_to($in['state']);
			}

			$this->_invalidate_dashboard();

			return ['status' => 'ok', 'object' => $this->_serialize_object($obj)];
		});
	}

	public function action_delete_object()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.delete');

			$obj = $this->_object($this->request->param('id'));
			$obj->deleted_at = date('Y-m-d H:i:s');
			$obj->state      = 'archived';
			$obj->save();

			$this->_invalidate_dashboard();

			return ['status' => 'ok', 'id' => $obj->id, 'deleted' => TRUE];
		});
	}

	public function action_state()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$obj  = $this->_object($this->request->param('id'));
			$next = Arr::get($this->_input(), 'state');

			$obj->transition_to($next);

			$this->_invalidate_dashboard();

			return ['status' => 'ok', 'id' => $obj->id, 'state' => $obj->state];
		});
	}

	public function action_merge()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.merge');

			$loser  = $this->_object($this->request->param('id'));
			$winner = $this->_object(Arr::get($this->_input(), 'into'));

			if ($loser->id === $winner->id)
				throw new Kohana_Exception('Cannot merge object into itself', NULL, 400);

			$db = $this->_db();
			$db->begin();
			try
			{
				DB::query(Database::UPDATE,
					'UPDATE object_xref SET object_id = :w WHERE object_id = :l')
					->param(':w', $winner->id)->param(':l', $loser->id)->execute($db);

				$props = ORM::factory('Erp230_Property')
					->where('object_id', '=', $loser->id)
					->where('is_current', '=', TRUE)
					->find_all();

				$prop = ORM::factory('Erp230_Property');
				foreach ($props as $p)
				{
					$prop->upsert_safe($winner->id, $p->key, $this->_decode($p->value), $p->source_id, $p->uom_code);
				}

				$loser->merged_into = $winner->id;
				$loser->state       = 'merged';
				$loser->save();

				$db->commit();
			}
			catch (Exception $e)
			{
				$db->rollback();
				throw $e;
			}

			$this->_invalidate_dashboard();

			return ['status' => 'ok', 'merged' => $loser->id, 'into' => $winner->id];
		});
	}

	// =======================================================================
	//  PROPERTIES — атрибуты (Trust Score + time-travel)
	// =======================================================================

	public function action_properties()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');
			$obj = $this->_object($this->request->param('id'));

			return [
				'status'      => 'ok',
				'object_id'   => $obj->id,
				'data'        => $obj->current_properties(),
			];
		});
	}

	public function action_set_properties()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$obj    = $this->_object($this->request->param('id'));
			$in     = $this->_input();
			$source = Arr::get($in, 'source_id');
			$items  = Arr::get($in, 'items', []);

			if ( ! $source OR empty($items))
				throw new Kohana_Exception('Required: source_id, items{}', NULL, 400);

			// Использование пакетного обновления через GoldenRecord
			$result = Service_Erp230_GoldenRecord::instance()->update_attributes_batch(
				$obj->id, 
				$items, 
				$source
			);

			return [
				'status'    => 'ok', 
				'object_id' => $obj->id, 
				'summary'   => $result
			];
		});
	}

	public function action_attribute_history()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$key = $this->request->query('key');
			if ( ! $key) throw new Kohana_Exception('Parameter "key" is required', NULL, 400);

			$obj   = $this->_object($this->request->param('id'));
			$limit = min(max((int) $this->request->query('limit') ?: 100, 1), 500);

			$data = [];
			$rows = ORM::factory('Erp230_Property')
				->where('object_id', '=', $obj->id)
				->where('key', '=', $key)
				->order_by('created_at', 'DESC')
				->limit($limit)
				->find_all();

			foreach ($rows as $h)
			{
				$data[] = [
					'value'      => $this->_decode($h->value),
					'source'     => $h->source_id,
					'is_current' => ($h->is_current === 't' OR $h->is_current === TRUE),
					'period'     => $h->valid_period,
					'created_at' => $h->created_at,
				];
			}

			return ['status' => 'ok', 'object_id' => $obj->id, 'key' => $key, 'data' => $data];
		});
	}

	// =======================================================================
	//  XREF — кросс-референсы внешних систем
	// =======================================================================

	public function action_resolve()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$source = $this->request->query('source');
			$remote = $this->request->query('remote');
			if ( ! $source OR ! $remote)
				throw new Kohana_Exception('Required: source, remote', NULL, 400);

			$row = DB::query(Database::SELECT,
				'SELECT object_id FROM object_xref WHERE source_id = :s AND remote_id = :r')
				->param(':s', $source)->param(':r', $remote)
				->execute($this->_db())->current();

			if ( ! $row) throw new Kohana_Exception('Xref not found', NULL, 404);

			return [
				'status'    => 'ok',
				'object'    => $this->_serialize_object($this->_object($row['object_id']), TRUE),
			];
		});
	}

	public function action_xref()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');
			$obj = $this->_object($this->request->param('id'));

			$rows = DB::query(Database::SELECT,
				'SELECT source_id, remote_id FROM object_xref WHERE object_id = :id ORDER BY source_id')
				->param(':id', $obj->id)->execute($this->_db())->as_array();

			return ['status' => 'ok', 'object_id' => $obj->id, 'xref' => $rows];
		});
	}

	// =======================================================================
	//  CONSOLIDATE — приём данных
	// =======================================================================

	public function action_consolidate()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$in  = $this->_input();
			$sid = Arr::get($in, 'source_id');
			$rid = Arr::get($in, 'remote_id');
			$pl  = Arr::get($in, 'payload');

			if ( ! $sid OR ! $rid OR ! $pl)
				throw new Kohana_Exception('Required: source_id, remote_id, payload', NULL, 400);

			// Сервис консолидации теперь использует ObjectFactory внутри
			$obj = Service_Erp230_Consolidation::instance()->consolidate($sid, $rid, $pl);

			$this->_invalidate_dashboard();

			return ['status' => 'ok', 'object' => $this->_serialize_object($obj, TRUE)];
		});
	}

	// =======================================================================
	//  DIGITAL TWIN — телеметрия, паспорт
	// =======================================================================

	public function action_telemetry()
	{
		$this->_handle(function () {
			$this->_require('twin.telemetry.ingest');

			$obj      = $this->_object($this->request->param('id'));
			$in       = $this->_input();
			$source   = Arr::get($in, 'source_id', 'sensor');
			$readings = Arr::get($in, 'readings', []);

			$result = Service_Erp230_GoldenRecord::instance()->update_attributes_batch(
				$obj->id, 
				$readings, 
				$source
			);

			return ['status' => 'ok', 'object_id' => $obj->id, 'results' => $result];
		});
	}

	/**
	 * Срез состояния объекта на момент времени (time-travel).
	 *
	 *   GET /mdm/twin/<id>/state?at=2026-01-31T12:00:00Z
	 */
	public function action_state_at()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$obj = $this->_object($this->request->param('id'));

			return [
				'status'  => 'ok',
				'id'      => $obj->id,
				'at'      => $this->request->query('at'),
				'state'   => $obj->state_at($this->request->query('at') ?: NULL),
			];
		});
	}

	public function action_twin()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');
			$obj = $this->_object($this->request->param('id'));

			return [
				'status'      => 'ok',
				'object'      => $this->_serialize_object($obj, TRUE),
				'properties'  => $obj->current_properties(),
			];
		});
	}

	// =======================================================================
	//  SEARCH
	// =======================================================================

	public function action_search()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$q     = trim((string) $this->request->query('q'));
			$type  = $this->request->query('type') ?: 'text';
			$limit = min(max((int) $this->request->query('limit') ?: 10, 1), 100);

			if ($q === '') return ['status' => 'ok', 'data' => []];

			$data = ($type === 'semantic')
				? $this->_semantic_search($q, $limit)
				: $this->_text_search($q, $limit);

			return ['status' => 'ok', 'type' => $type, 'data' => $data];
		});
	}

	protected function _text_search($q, $limit)
	{
		$out = [];
		$rows = ORM::factory('Erp230_Object')
			->where('master_code', 'ILIKE', '%'.$q.'%')
			->where('deleted_at', 'IS', NULL)
			->limit($limit)
			->find_all();

		foreach ($rows as $o)
		{
			$out[] = ['id' => $o->id, 'master_code' => $o->master_code, 'type_id' => $o->type_id];
		}
		return $out;
	}

	protected function _semantic_search($q, $limit)
	{
		if ( ! class_exists('Llm')) throw new Kohana_Exception('Semantic search unavailable', NULL, 503);
		$vector = Llm::get_embedding($q);
		
		$out = [];
		foreach (ORM::factory('Erp230_Search')->semantic_search(json_encode($vector), $limit) as $row)
		{
			$obj = ORM::factory('Erp230_Object', $row['object_id']);
			if ($obj->loaded())
				$out[] = ['id' => $obj->id, 'master_code' => $obj->master_code, 'distance' => (float) $row['distance']];
		}
		return $out;
	}

	// =======================================================================
	//  DICTIONARIES
	// =======================================================================

	public function action_dictionary()
	{
		$this->_handle(function () {
			$this->_require('mdm.dictionary.view');

			$map = [
				'org_units' => 'Erp230_OrgUnit',
				'sources'   => 'Erp230_Source',
				'uom'       => 'Erp230_Uom',
			];

			$name = $this->request->param('id');
			if ( ! isset($map[$name])) throw new Kohana_Exception('Unknown dictionary', NULL, 404);

			$data = [];
			foreach (ORM::factory($map[$name])->find_all() as $row)
			{
				$data[] = $row->as_array();
			}

			return ['status' => 'ok', 'dictionary' => $name, 'data' => $data];
		});
	}
}