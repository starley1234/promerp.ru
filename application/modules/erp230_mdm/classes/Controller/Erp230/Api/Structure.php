<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST API управления структурой изделий (BOM / Digital Twin).
 * 
 * Методы перенесены из Controller_Erp230_Api для декомпозиции логики.
 * Обеспечивает:
 *   - Прямое разузлование (BOM)
 *   - Рекурсивное построение дерева (CTE)
 *   - Обратный обход (Where-used)
 *   - Управление связями (Link CRUD)
 *
 * @package Erp230
 * @version 1.0.0
 */
class Controller_Erp230_Api_Structure extends Controller_Erp230_Api {

	/**
	 * GET /erp230_api/bom/<id>?type=ebom
	 * Прямые дочерние элементы.
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	public function action_bom()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');
			$obj  = $this->_object($this->request->param('id'));
			$type = $this->request->query('type') ?: 'ebom';

			return [
				'status'    => 'ok',
				'parent_id' => $obj->id,
				'type'      => $type,
				'children'  => $obj->children($type),
			];
		});
	}

	/**
	 * GET /erp230_api/bom_tree/<id>?type=ebom&depth=10
	 * Полная разузлованная структура (рекурсивный обход в глубину).
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	public function action_bom_tree()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$obj   = $this->_object($this->request->param('id'));
			$type  = $this->request->query('type') ?: 'ebom';
			$depth = min(max((int) $this->request->query('depth') ?: 20, 1), 50);

			$rows = $obj->bom_tree($type, $depth);

			return [
				'status'    => 'ok',
				'root'      => $obj->id,
				'type'      => $type,
				'nodes'     => count($rows),
				'tree'      => $this->_nest($rows, $obj->id),
			];
		});
	}

	/**
	 * GET /erp230_api/where_used/<id>?type=ebom&depth=10
	 * Обратный обход: в какие изделия входит компонент.
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	public function action_where_used()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$obj   = $this->_object($this->request->param('id'));
			$type  = $this->request->query('type') ?: 'ebom';
			$depth = min(max((int) $this->request->query('depth') ?: 20, 1), 50);

			return [
				'status'    => 'ok',
				'component' => $obj->id,
				'used_in'   => $obj->where_used($type, $depth),
			];
		});
	}

	/**
	 * POST /erp230_api/link  {parent_id, child_id, link_type?, qty?, designator?}
	 * Создание связи (модель отсекает циклы).
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	public function action_create_link()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$in   = $this->_input();
			$link = ORM::factory('Erp230_Link');
			$link->values([
				'parent_id'  => Arr::get($in, 'parent_id'),
				'child_id'   => Arr::get($in, 'child_id'),
				'link_type'  => Arr::get($in, 'link_type', 'ebom'),
				'qty'        => Arr::get($in, 'qty', 1),
				'designator' => Arr::get($in, 'designator'),
			])->save();

			return ['status' => 'ok', 'link_id' => $link->id];
		});
	}

	/**
	 * DELETE /erp230_api/link/<id>
	 * Логическое закрытие связи (проставляется верхняя граница периода).
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	public function action_delete_link()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$id = $this->request->param('id');
			$n  = DB::query(Database::UPDATE,
				"UPDATE object_links
				    SET valid_period = tstzrange(lower(valid_period), now(), '[)')
				  WHERE id = :id AND upper(valid_period) IS NULL")
				->param(':id', $id)->execute($this->_db());

			if ($n === 0)
				throw new Kohana_Exception('Active link not found', NULL, 404);

			return ['status' => 'ok', 'link_id' => $id, 'closed' => TRUE];
		});
	}

	/** 
	 * Собирает плоский список рёбер в дерево по parent_id. 
	 * (Метод перенесён из Controller_Erp230_Api)
	 */
	protected function _nest(array $rows, $root)
	{
		$byParent = [];
		foreach ($rows as $r)
		{
			$byParent[$r['parent_id']][] = $r;
		}

		$build = function ($parent) use (&$build, $byParent) {
			$children = [];
			if (empty($byParent[$parent])) return $children;

			foreach ($byParent[$parent] as $r)
			{
				$children[] = [
					'id'          => $r['child_id'],
					'master_code' => $r['master_code'],
					'type_id'     => $r['type_id'],
					'state'       => $r['state'],
					'qty'         => (float) $r['qty'],
					'designator'  => $r['designator'],
					'children'    => $build($r['child_id']),
				];
			}
			return $children;
		};

		return $build($root);
	}
}