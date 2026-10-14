<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для работы с типами данных (метаданными) MDM.
 * 
 * Перенесены методы из Api.php для работы со справочником типов,
 * добавлены методы для получения JSON-схем валидации.
 */
class Controller_Erp230_Api_Type extends Controller_Erp230_Api {

	/**
	 * GET /erp230_api_type/list
	 * Получить иерархический список всех типов.
	 */
	public function action_list()
	{
		$this->_handle(function () {
			$this->_require('mdm.dictionary.view');

			$types = ORM::factory('Erp230_Type')
				->order_by('path', 'ASC')
				->find_all()
				->as_array();

			$data = [];
			foreach ($types as $t)
			{
				$data[] = [
					'id'           => $t->id,
					'parent_id'    => $t->parent_id,
					'display_name' => $t->display_name,
					'path'         => $t->path,
					'has_schema'   => ! empty(json_decode($t->schema, TRUE))
				];
			}

			return [
				'status' => 'ok',
				'count'  => count($data),
				'data'   => $data
			];
		});
	}

	/**
	 * GET /erp230_api_type/schema/<id>
	 * Получить схему валидации для конкретного типа.
	 * Используется фронтендом для генерации форм добавления элементов.
	 */
	public function action_schema()
	{
		$this->_handle(function () {
			$this->_require('mdm.dictionary.view');

			$id = $this->request->param('id');
			$type = ORM::factory('Erp230_Type', $id);

			if ( ! $type->loaded())
				throw new Kohana_Exception('Type :id not found', [':id' => $id], 404);

			return [
				'status'  => 'ok',
				'type_id' => $type->id,
				'schema'  => json_decode($type->schema, TRUE) ?: new stdClass()
			];
		});
	}

	/**
	 * POST /erp230_api_type/create
	 * Регистрация нового типа данных.
	 */
	public function action_create()
	{
		$this->_handle(function () {
			$this->_require('mdm.dictionary.edit');

			$in = $this->_input();
			
			$type = ORM::factory('Erp230_Type');
			$type->id           = Arr::get($in, 'id');
			$type->parent_id    = Arr::get($in, 'parent_id');
			$type->display_name = Arr::get($in, 'display_name');
			$type->schema       = json_encode(Arr::get($in, 'schema', []));
			$type->save();

			return [
				'status' => 'ok',
				'id'     => $type->id
			];
		});
	}

	/**
	 * PATCH /erp230_api_type/update/<id>
	 * Обновление метаданных типа (напр. схемы).
	 */
	public function action_update()
	{
		$this->_handle(function () {
			$this->_require('mdm.dictionary.edit');

			$id = $this->request->param('id');
			$type = ORM::factory('Erp230_Type', $id);

			if ( ! $type->loaded())
				throw new Kohana_Exception('Type not found', NULL, 404);

			$in = $this->_input();
			if (isset($in['display_name'])) $type->display_name = $in['display_name'];
			if (isset($in['schema']))       $type->schema = json_encode($in['schema']);
			
			$type->save();

			return ['status' => 'ok'];
		});
	}
}