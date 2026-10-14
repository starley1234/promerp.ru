<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовая ORM-модель модуля MDM (ERP230).
 * Инкапсулирует особенности PostgreSQL: UUID, партиционирование, отсутствие
 * рабочего lastInsertId() и list_columns() в PDO-драйвере.
 */
abstract class Model_Erp230_Main extends ORM {

	protected $_db_group = 'mdm';

	/** У моделей с uuid-PK ставим TRUE — id сгенерируется автоматически. */
	protected $_uuid_pk = FALSE;

	/** Колонки, которые надо вернуть из INSERT ... RETURNING (считает триггер/БД). */
	protected $_returning = [];

	/** Генерация UUID на стороне БД (безопасно для партиций). */
	protected function _uuid()
	{
		return DB::query(Database::SELECT, 'SELECT uuid_generate_v4() AS id')
			->execute($this->_db)
			->get('id');
	}

	/** Хуки для потомков. */
	protected function before_create() {}
	protected function after_create()  {}
	protected function before_update() {}
	protected function after_update()  {}

	/**
	 * Единая точка входа для сохранения.
	 * UPDATE идёт через _update_pg(), INSERT — через _create_pg().
	 */
	public function save(?Validation $validation = NULL)
	{
		if ($this->_loaded)
		{
			$this->before_update();
			$this->check($validation);
			$this->_update_pg();
			$this->after_update();

			return $this;
		}

		if ($this->_uuid_pk AND empty($this->_object[$this->_primary_key]))
		{
			$this->{$this->_primary_key} = $this->_uuid();
		}

		$this->before_create();
		$this->check($validation);          // бросит ORM_Validation_Exception при ошибке
		$this->_create_pg();
		$this->after_create();

		return $this;
	}

	/** create() всегда проксируем в save() — вся вставка в одном месте. */
	public function create(?Validation $validation = NULL)
	{
		return $this->save($validation);
	}

	/** update() проксируем в save() — обновление в одном месте. */
	public function update(?Validation $validation = NULL)
	{
		return $this->save($validation);
	}

	/**
	 * Подстановка created/updated колонок в $_object при вставке.
	 */
	protected function _touch_timestamps($is_create)
	{
		if ($is_create AND is_array($this->_created_column))
		{
			$c = $this->_created_column['column'];
			if (empty($this->_object[$c]))
				$this->{$c} = date($this->_created_column['format']);
		}
		if (is_array($this->_updated_column))
		{
			$c = $this->_updated_column['column'];
			$this->{$c} = date($this->_updated_column['format']);
		}
	}

	/**
	 * Ручная вставка строки без обращения к lastInsertId().
	 * При наличии $_returning данные забираются через RETURNING (тип SELECT),
	 * иначе — обычный INSERT (тип UPDATE, драйвер не дёргает lastInsertId).
	 */
	protected function _create_pg()
	{
		$db = $this->_db;

		$this->_touch_timestamps(TRUE);

		$data = [];
		foreach ($this->_changed as $column)
		{
			$data[$column] = $this->run_filter($column, $this->_object[$column]);
		}

		if (empty($data))
			throw new Kohana_Exception('Нет данных для вставки в :t', [':t' => $this->_table_name]);

		$cols = $phs = $params = [];
		$i = 0;
		foreach ($data as $col => $val)
		{
			$cols[]      = $db->quote_column($col);
			$ph          = ':v'.$i;
			$phs[]       = $ph;
			$params[$ph] = $val;
			$i++;
		}

		$sql = 'INSERT INTO '.$db->quote_table($this->_table_name)
			.' ('.implode(', ', $cols).') VALUES ('.implode(', ', $phs).')';

		if ( ! empty($this->_returning))
		{
			$ret = [];
			foreach ($this->_returning as $rc)
			{
				$ret[] = $db->quote_column($rc);
			}
			$sql .= ' RETURNING '.implode(', ', $ret);

			// Тип SELECT => драйвер вернёт строки и НЕ дёрнет lastInsertId()
			$row = DB::query(Database::SELECT, $sql)
				->parameters($params)
				->execute($db)
				->current();

			if ($row)
			{
				foreach ($this->_returning as $rc)
				{
					$this->_object[$rc] = Arr::get((array) $row, $rc);
				}
			}
		}
		else
		{
			// Тип UPDATE => rowCount, без lastInsertId()
			DB::query(Database::UPDATE, $sql)
				->parameters($params)
				->execute($db);
		}

		$this->_saved             = TRUE;
		$this->_loaded            = TRUE;
		$this->_valid             = TRUE;
		$this->_changed           = [];
		$this->_original_values   = $this->_object;
		$this->_primary_key_value = $this->_object[$this->_primary_key];

		return $this;
	}

	/**
	 * Ручное обновление строки. Пишет только изменённые поля,
	 * WHERE по первичному ключу (+ партиционирование через сам PK).
	 */
	protected function _update_pg()
	{
		$db = $this->_db;

		$this->_touch_timestamps(FALSE);

		$data = [];
		foreach ($this->_changed as $column)
		{
			$data[$column] = $this->run_filter($column, $this->_object[$column]);
		}

		// Нечего обновлять — просто фиксируем состояние.
		if (empty($data))
		{
			$this->_saved           = TRUE;
			$this->_original_values = $this->_object;
			return $this;
		}

		$sets = $params = [];
		$i = 0;
		foreach ($data as $col => $val)
		{
			$ph          = ':v'.$i;
			$sets[]      = $db->quote_column($col).' = '.$ph;
			$params[$ph] = $val;
			$i++;
		}

		$params[':pk'] = $this->_primary_key_value;

		$sql = 'UPDATE '.$db->quote_table($this->_table_name)
			.' SET '.implode(', ', $sets)
			.' WHERE '.$db->quote_column($this->_primary_key).' = :pk';

		DB::query(Database::UPDATE, $sql)
			->parameters($params)
			->execute($db);

		$this->_saved           = TRUE;
		$this->_valid           = TRUE;
		$this->_changed         = [];
		$this->_original_values = $this->_object;

		return $this;
	}

	/** Утилита: приведение postgres boolean ('t'/'f') к bool. */
	protected function _pg_bool($v)
	{
		return $v === TRUE OR $v === 't' OR $v === '1' OR $v === 1;
	}

	/**
	 * Чтение колонки с приведением boolean.
	 *
	 * PostgreSQL отдаёт boolean строками `t`/`f`, а `'f'` в PHP — непустая
	 * строка, то есть «истина». Без приведения `! empty($obj->is_dirty)` и
	 * `if ($obj->is_dirty)` показывают грязными все объекты подряд, а `(bool)`
	 * в JSON-ответах всегда `true`. Приводим на границе модели — как в
	 * erp227 (Model_Erp227_Base::get), чтобы потребители получали bool.
	 *
	 * @param   string  $column
	 * @return  mixed
	 */
	public function get($column)
	{
		$value = parent::get($column);

		if ($value === 't' OR $value === 'f')
		{
			return ($value === 't');
		}

		return $value;
	}
}