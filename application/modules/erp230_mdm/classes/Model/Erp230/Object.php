<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Мастер-запись изделия (Golden Record). Таблица objects (партиционирована).
 *
 * Помимо CRUD инкапсулирует всю доменную работу с БД (CTE/агрегаты),
 * чтобы контроллеры оставались тонкими:
 *   - display_name()      — отображаемое имя (object_state → фолбэк на свойство name)
 *   - current_properties()— актуальные свойства key => детали
 *   - children()          — прямые дочерние связи (одноуровневый BOM)
 *   - bom_tree()          — разузлование вниз (рекурсивный CTE), плоские рёбра
 *   - where_used()        — обратный обход вверх (рекурсивный CTE)
 *   - state_at()          — срез свойств на момент времени (time-travel)
 *   - dashboard_stats()   — агрегаты для дашборда (статический)
 */
class Model_Erp230_Object extends Model_Erp230_Main {

	protected $_table_name  = 'objects';
	protected $_primary_key = 'id';
	protected $_uuid_pk     = TRUE;

	protected $_created_column = ['column' => 'created_at', 'format' => 'Y-m-d H:i:s'];
	protected $_updated_column = ['column' => 'updated_at', 'format' => 'Y-m-d H:i:s'];

	protected $_table_columns = [
		'id'          => ['type' => 'string'],
		'type_id'     => ['type' => 'string'],
		'org_id'      => ['type' => 'string'],
		'org_path'    => ['type' => 'string'],
		'master_code' => ['type' => 'string'],
		'state'       => ['type' => 'string'],
		'merged_into' => ['type' => 'string'],
		'is_dirty'    => ['type' => 'bool'],
		'created_at'  => ['type' => 'string'],
		'updated_at'  => ['type' => 'string'],
		'deleted_at'  => ['type' => 'string'],
	];

	protected $_belongs_to = [
		'type' => ['model' => 'Erp230_Type', 'foreign_key' => 'type_id'],
	];

	protected $_has_many = [
		'properties' => ['model' => 'Erp230_Property', 'foreign_key' => 'object_id'],
		'links'      => ['model' => 'Erp230_Link',     'foreign_key' => 'parent_id'],
		'baselines'  => ['model' => 'Erp230_Baseline', 'foreign_key' => 'object_id'],
	];

	/**
	 * Матрица допустимых переходов состояний.
	 * Ключ — текущее состояние, значение — список разрешённых целевых.
	 */
	protected static $_state_transitions = [
		'draft'    => ['active', 'archived'],
		'active'   => ['archived', 'merged'],
		'archived' => ['active'],
		'merged'   => [],
	];

	public function rules()
	{
		return [
			'type_id'     => [['not_empty'], ['max_length', [':value', 64]]],
			'org_id'      => [['not_empty'], ['max_length', [':value', 64]]],
			'master_code' => [['not_empty'], ['max_length', [':value', 255]]],
			'state'       => [['in_array', [':value', ['draft','active','archived','merged']]]],
		];
	}

	public function filters()
	{
		return [
			'master_code' => [['trim'], ['strip_tags']],
			'state'       => [['strtolower']],
			'merged_into' => [[function ($v) { return empty($v) ? NULL : $v; }]],
		];
	}

	/** Перед вставкой подставляем ltree-путь организации (нужно для RLS и триггера). */
	protected function before_create()
	{
		if (empty($this->org_path) AND ! empty($this->org_id))
		{
			$org = ORM::factory('Erp230_OrgUnit', $this->org_id);
			if ($org->loaded())
			{
				$this->org_path = (string) $org->path;
			}
		}
	}

	public function labels()
	{
		return [
			'id'          => 'UUID объекта',
			'type_id'     => 'Тип изделия',
			'org_id'      => 'Организация',
			'master_code' => 'Мастер-код',
			'state'       => 'Состояние',
		];
	}

	/**
	 * Контролируемая смена состояния объекта.
	 * Проверяет допустимость перехода по матрице $_state_transitions
	 * и сохраняет изменение. Прямой переход в 'merged' запрещён —
	 * слияние выполняется отдельной операцией (action_merge).
	 *
	 * @param  string $state Целевое состояние
	 * @return $this
	 * @throws Kohana_Exception при недопустимом переходе
	 */
	public function transition_to($state)
	{
		if ( ! $this->loaded())
			throw new Kohana_Exception('Нельзя менять состояние незагруженного объекта');

		$state   = strtolower((string) $state);
		$current = strtolower((string) $this->state);

		$allowed_states = ['draft', 'active', 'archived', 'merged'];
		if ( ! in_array($state, $allowed_states, TRUE))
		{
			throw new Kohana_Exception('Недопустимое состояние ":s"', [':s' => $state]);
		}

		// Прямой перевод в merged извне запрещён — только через слияние.
		if ($state === 'merged')
		{
			throw new Kohana_Exception('Состояние "merged" устанавливается только операцией слияния');
		}

		if ($state === $current)
		{
			// Нет фактического перехода — ничего не делаем.
			return $this;
		}

		$transitions = Arr::get(self::$_state_transitions, $current, []);
		if ( ! in_array($state, $transitions, TRUE))
		{
			throw new Kohana_Exception(
				'Недопустимый переход состояния: ":from" → ":to"',
				[':from' => $current, ':to' => $state]
			);
		}

		$this->state = $state;
		$this->save();

		return $this;
	}

	/**
	 * Валидна ли hash-chain снимков объекта.
	 * Тонкая обёртка над моделью Baseline — чтобы вьюхи/контроллеры
	 * звали $object->is_chain_valid() без знания о Baseline.
	 *
	 * @return bool
	 */
	public function is_chain_valid()
	{
		if ( ! $this->loaded())
			return TRUE; // нет объекта — нечего проверять

		return ORM::factory('Erp230_Baseline')->is_chain_valid($this->id);
	}

	// =======================================================================
	//  ДОМЕННЫЕ ЧТЕНИЯ (перенос SQL из контроллеров)
	// =======================================================================

	/**
	 * Отображаемое имя объекта.
	 * Приоритет: object_state.attributes->>'name' → текущее свойство key='name'.
	 * Один запрос к object_state (без N+1), при пустом результате — фолбэк.
	 *
	 * @return string|NULL
	 */
	public function display_name()
	{
		if ( ! $this->loaded())
			return NULL;

		// 1) object_state.attributes->>'name'
		try
		{
			$name = DB::query(Database::SELECT,
				"SELECT attributes->>'name' AS name
				   FROM object_state
				  WHERE object_id = :oid
				  LIMIT 1")
				->param(':oid', $this->id)
				->execute($this->_db)
				->get('name');

			if ( ! empty($name))
				return (string) $name;
		}
		catch (Exception $e)
		{
			// object_state может отсутствовать в конфигурации — тихо игнорируем.
		}

		// 2) Фолбэк: текущее свойство key='name'.
		try
		{
			$prop = $this->properties
				->where('is_current', '=', TRUE)
				->where('key', '=', 'name')
				->find();

			if ($prop->loaded() AND ! empty($prop->value))
			{
				$decoded = $this->_decode($prop->value);
				if (is_string($decoded) AND $decoded !== '')
					return $decoded;

				return trim((string) $prop->value, '"');
			}
		}
		catch (Exception $e)
		{
			// нет свойств — не критично
		}

		return NULL;
	}

	/**
	 * Актуальные свойства объекта в виде key => детали.
	 *
	 * @return array
	 */
	public function current_properties()
	{
		if ( ! $this->loaded())
			return [];

		$data = [];
		$rows = ORM::factory('Erp230_Property')
			->where('object_id', '=', $this->id)
			->where('is_current', '=', TRUE)
			->find_all();

		foreach ($rows as $p)
		{
			$data[$p->key] = [
				'value'      => $this->_decode($p->value),
				'uom'        => $p->uom_code,
				'source'     => $p->source_id,
				'confidence' => (float) $p->confidence,
				'updated_at' => $p->created_at,
			];
		}

		return $data;
	}

	/**
	 * Прямые дочерние связи (одноуровневый BOM).
	 *
	 * @param  string $type Тип связи (ebom, mbom и т.п.)
	 * @return array
	 */
	public function children($type = 'ebom')
	{
		if ( ! $this->loaded())
			return [];

		$rows = DB::query(Database::SELECT,
			'SELECT l.id, l.child_id, l.qty, l.designator, o.master_code, o.type_id, o.state
			   FROM object_links l
			   JOIN objects o ON o.id = l.child_id
			  WHERE l.parent_id = :id AND l.link_type = :t
			    AND upper(l.valid_period) IS NULL
			  ORDER BY o.master_code')
			->param(':id', $this->id)
			->param(':t', $type)
			->execute($this->_db)
			->as_array();

		$out = [];
		foreach ($rows as $r)
		{
			$out[] = [
				'link_id'     => $r['id'],
				'id'          => $r['child_id'],
				'master_code' => $r['master_code'],
				'type_id'     => $r['type_id'],
				'state'       => $r['state'],
				'qty'         => (float) $r['qty'],
				'designator'  => $r['designator'],
			];
		}

		return $out;
	}

	/**
	 * Разузлование структуры вниз (рекурсивный CTE в глубину).
	 * Возвращает плоский список рёбер (parent_id, child_id, qty, ...).
	 *
	 * @param  string $type  Тип связи
	 * @param  int    $depth Ограничение глубины (1..50)
	 * @return array
	 */
	public function bom_tree($type = 'ebom', $depth = 20)
	{
		if ( ! $this->loaded())
			return [];

		$depth = min(max((int) $depth, 1), 50);

		return DB::query(Database::SELECT,
			'WITH RECURSIVE tree AS (
				SELECT l.parent_id, l.child_id, l.qty, l.designator, 1 AS lvl,
				       ARRAY[l.parent_id, l.child_id] AS path
				  FROM object_links l
				 WHERE l.parent_id = :root AND l.link_type = :t
				   AND upper(l.valid_period) IS NULL
				UNION ALL
				SELECT l.parent_id, l.child_id, l.qty, l.designator, t.lvl + 1,
				       t.path || l.child_id
				  FROM object_links l
				  JOIN tree t ON l.parent_id = t.child_id
				 WHERE l.link_type = :t AND upper(l.valid_period) IS NULL
				   AND t.lvl < :depth
				   AND NOT l.child_id = ANY(t.path)
			)
			SELECT t.parent_id, t.child_id, t.qty, t.designator, t.lvl,
			       o.master_code, o.type_id, o.state
			  FROM tree t
			  JOIN objects o ON o.id = t.child_id
			 ORDER BY t.lvl')
			->param(':root', $this->id)
			->param(':t', $type)
			->param(':depth', $depth)
			->execute($this->_db)
			->as_array();
	}

	/**
	 * Обратный обход структуры вверх (рекурсивный CTE): где используется компонент.
	 *
	 * @param  string $type  Тип связи
	 * @param  int    $depth Ограничение глубины (1..50)
	 * @return array
	 */
	public function where_used($type = 'ebom', $depth = 20)
	{
		if ( ! $this->loaded())
			return [];

		$depth = min(max((int) $depth, 1), 50);

		$rows = DB::query(Database::SELECT,
			'WITH RECURSIVE up AS (
				SELECT l.parent_id, l.child_id, 1 AS lvl, ARRAY[l.child_id, l.parent_id] AS path
				  FROM object_links l
				 WHERE l.child_id = :leaf AND l.link_type = :t
				   AND upper(l.valid_period) IS NULL
				UNION ALL
				SELECT l.parent_id, l.child_id, u.lvl + 1, u.path || l.parent_id
				  FROM object_links l
				  JOIN up u ON l.child_id = u.parent_id
				 WHERE l.link_type = :t AND upper(l.valid_period) IS NULL
				   AND u.lvl < :depth
				   AND NOT l.parent_id = ANY(u.path)
			)
			SELECT DISTINCT u.parent_id, u.lvl, o.master_code, o.type_id, o.state
			  FROM up u JOIN objects o ON o.id = u.parent_id
			 ORDER BY u.lvl')
			->param(':leaf', $this->id)
			->param(':t', $type)
			->param(':depth', $depth)
			->execute($this->_db)
			->as_array();

		$out = [];
		foreach ($rows as $r)
		{
			$out[] = [
				'id'          => $r['parent_id'],
				'master_code' => $r['master_code'],
				'type_id'     => $r['type_id'],
				'state'       => $r['state'],
				'level'       => (int) $r['lvl'],
			];
		}

		return $out;
	}

	/**
	 * Срез свойств объекта на заданный момент времени (time-travel).
	 * При $at = NULL используется серверный now().
	 *
	 * @param  string|NULL $at ISO-8601 timestamptz либо NULL
	 * @return array key => детали
	 */
	public function state_at($at = NULL)
	{
		if ( ! $this->loaded())
			return [];

		$rows = DB::query(Database::SELECT,
			"SELECT DISTINCT ON (key)
			        key, value, uom_code, source_id, confidence
			   FROM object_properties
			  WHERE object_id = :id
			    AND valid_period @> COALESCE(CAST(:at AS timestamptz), now())
			  ORDER BY key, confidence DESC")
			->param(':id', $this->id)
			->param(':at', $at ?: NULL)
			->execute($this->_db)
			->as_array();

		$state = [];
		foreach ($rows as $r)
		{
			$state[$r['key']] = [
				'value'      => $this->_decode($r['value']),
				'uom'        => $r['uom_code'],
				'source'     => $r['source_id'],
				'confidence' => (float) $r['confidence'],
			];
		}

		return $state;
	}

	/**
	 * Агрегаты для дашборда: сводка по objects + количество types/org_units.
	 * Статический — не требует загруженного экземпляра.
	 *
	 * @return array
	 */
	public static function dashboard_stats()
	{
		$db = Database::instance('mdm');

		$objects = DB::query(Database::SELECT,
			"SELECT
			        COUNT(*)                                        AS total,
			        COUNT(*) FILTER (WHERE state = 'draft')         AS draft,
			        COUNT(*) FILTER (WHERE state = 'active')        AS active,
			        COUNT(*) FILTER (WHERE state = 'archived')      AS archived,
			        COUNT(*) FILTER (WHERE state = 'merged')        AS merged,
			        COUNT(*) FILTER (WHERE is_dirty = TRUE)         AS dirty
			   FROM objects
			  WHERE deleted_at IS NULL")
			->execute($db)
			->current();

		$objects = (array) $objects;

		return [
			'objects' => [
				'total'    => (int) Arr::get($objects, 'total', 0),
				'draft'    => (int) Arr::get($objects, 'draft', 0),
				'active'   => (int) Arr::get($objects, 'active', 0),
				'archived' => (int) Arr::get($objects, 'archived', 0),
				'merged'   => (int) Arr::get($objects, 'merged', 0),
				'dirty'    => (int) Arr::get($objects, 'dirty', 0),
			],
			'types'     => self::_count_table($db, 'types'),
			'org_units' => self::_count_table($db, 'org_units'),
		];
	}

	/** Подсчёт строк произвольной таблицы (для агрегатов дашборда). */
	protected static function _count_table($db, $table)
	{
		try
		{
			return (int) DB::query(Database::SELECT,
				'SELECT COUNT(*) AS c FROM '.$db->quote_table($table))
				->execute($db)
				->get('c');
		}
		catch (Exception $e)
		{
			return 0;
		}
	}

	/**
	 * jsonb-значение приходит строкой — пробуем декодировать.
	 *
	 * @param  mixed $value
	 * @return mixed
	 */
	protected function _decode($value)
	{
		if ( ! is_string($value))
			return $value;

		$d = json_decode($value, TRUE);
		return (json_last_error() === JSON_ERROR_NONE) ? $d : $value;
	}

}