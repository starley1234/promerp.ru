<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Связь в графе (EBOM/MBOM). Таблица object_links.
 * Циклические зависимости отсекаются здесь, т.к. в БД нет соответствующего триггера.
 */
class Model_Erp230_Link extends Model_Erp230_Main {

	protected $_table_name  = 'object_links';
	protected $_primary_key = 'id';
	protected $_uuid_pk     = TRUE;

	protected $_table_columns = [
		'id'           => ['type' => 'string'],
		'parent_id'    => ['type' => 'string'],
		'child_id'     => ['type' => 'string'],
		'link_type'    => ['type' => 'string'],
		'qty'          => ['type' => 'float'],
		'designator'   => ['type' => 'string'],
		'valid_period' => ['type' => 'string'],
	];

	/**
	 * Валидация связи. Возвращает bool (НЕ бросает исключение),
	 * поэтому её можно вызывать напрямую для проверки цикла.
	 */
	public function check(?Validation $extra_validation = NULL)
	{
		$this->_validation = Validation::factory($this->as_array())
			->rule('parent_id', 'not_empty')
			->rule('child_id',  'not_empty')
			->rule('link_type', 'not_empty');

		$passed = $this->_validation->check();

		if ( ! empty($this->parent_id) AND ! empty($this->child_id))
		{
			if ($this->parent_id == $this->child_id)
			{
				$this->_validation->error('child_id', 'self_reference');
				$passed = FALSE;
			}
			elseif ($this->_creates_cycle($this->parent_id, $this->child_id))
			{
				$this->_validation->error('child_id', 'circular_dependency');
				$passed = FALSE;
			}
		}

		$this->_valid = $passed;
		return $passed;
	}

	public function validation()
	{
		if ( ! $this->_validation instanceof Validation)
		{
			$this->check();
		}
		return $this->_validation;
	}

	/**
	 * Проверяет, создаст ли ребро parent->child цикл.
	 * Цикл возникает, если parent уже достижим из child по существующим рёбрам.
	 */
	protected function _creates_cycle($parent_id, $child_id)
	{
		$sql = 'WITH RECURSIVE reachable(node) AS (
					SELECT child_id FROM object_links
					WHERE parent_id = :child AND upper(valid_period) IS NULL
				UNION
					SELECT l.child_id FROM object_links l
					JOIN reachable r ON l.parent_id = r.node
					WHERE upper(l.valid_period) IS NULL
				)
				SELECT 1 AS hit FROM reachable WHERE node = :parent LIMIT 1';

		$res = DB::query(Database::SELECT, $sql)
			->param(':child',  $child_id)
			->param(':parent', $parent_id)
			->execute($this->_db);

		return count($res) > 0;
	}

	/** При сохранении цикл недопустим — тут уже бросаем исключение. */
	public function save(?Validation $validation = NULL)
	{
		if ($this->_loaded)
			return parent::save($validation);

		if (empty($this->_object['id']))
			$this->id = $this->_uuid();

		if ( ! $this->check())
			throw new ORM_Validation_Exception($this->object_name(), $this->_validation);

		return $this->_create_pg();
	}
}