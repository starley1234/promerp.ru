<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Страж дубликатов (Duplicate Guard).
 *
 * Централизует все проверки на дубли в едином write-path создания объектов:
 *   - find_by_xref()          — поиск по кросс-референсу (source_id + remote_id);
 *   - find_by_code()          — поиск по нормализованному master_code (в рамках type + org);
 *   - assert_unique_code()    — гарантия уникальности кода (с exclude для edit);
 *   - find_by_identity_keys() — поиск по бизнес-ключам идентичности (identity_keys из схемы типа);
 *   - suggest_similar()       — мягкие похожие кандидаты (fulltext / semantic) для UI;
 *   - check()                 — агрегированная проверка (status ok|warn|block + candidates).
 *
 * Используется Service_Erp230_ObjectFactory до создания draft-объекта.
 *
 * identity_keys читаются из JSON-схемы типа (types.schema → x-mdm.identity_keys):
 *   { "x-mdm": { "identity_keys": ["gtin", "manufacturer_part_number"] } }
 *
 * Единый lookup order (F.3 manual_for_file):
 *   1. xref (source_id + remote_id)
 *   2. master_code + type_id + org_id (живые, не deleted)
 *   3. identity_keys (если настроены)
 *   4. create draft (вне Guard — в Factory)
 *   5. soft similar — только UI warn
 *
 * @package Erp230
 * @version 1.1.0
 */
class Service_Erp230_DuplicateGuard {

	/**
	 * @var Service_Erp230_DuplicateGuard
	 */
	protected static $_instance;

	/**
	 * Singleton instance
	 *
	 * @return Service_Erp230_DuplicateGuard
	 */
	public static function instance()
	{
		if (self::$_instance === NULL)
		{
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function __construct() {}

	/**
	 * Поиск мастер-объекта по кросс-референсу (source_id + remote_id).
	 * Xref имеет составной PK и read-only для ORM, поэтому читаем через DB::select.
	 *
	 * @param  string $source_id
	 * @param  string $remote_id
	 * @return Model_Erp230_Object|NULL
	 */
	public function find_by_xref($source_id, $remote_id)
	{
		if (empty($source_id) OR empty($remote_id))
		{
			return NULL;
		}

		$row = DB::select('object_id')
			->from('object_xref')
			->where('source_id', '=', $source_id)
			->where('remote_id', '=', $remote_id)
			->execute(Database::instance('mdm'))
			->current();

		if ( ! $row OR empty($row['object_id']))
		{
			return NULL;
		}

		$object = ORM::factory('Erp230_Object', $row['object_id']);

		return $object->loaded() ? $object : NULL;
	}

	/**
	 * Поиск объекта по нормализованному master_code в рамках type + org.
	 * Регистронезависимо (согласно индексу uk_object_codes_upper).
	 * Игнорирует мягко удалённые объекты (deleted_at IS NULL).
	 *
	 * @param  string $master_code  Уже нормализованный код
	 * @param  string $type_id
	 * @param  string $org_id
	 * @return Model_Erp230_Object|NULL
	 */
	public function find_by_code($master_code, $type_id, $org_id)
	{
		if (empty($master_code) OR empty($type_id) OR empty($org_id))
		{
			return NULL;
		}

		$object = ORM::factory('Erp230_Object')
			->where(DB::expr('UPPER(master_code)'), '=', strtoupper($master_code))
			->where('type_id', '=', $type_id)
			->where('org_id', '=', $org_id)
			->where('deleted_at', 'IS', NULL)
			->find();

		return $object->loaded() ? $object : NULL;
	}

	/**
	 * Гарантия уникальности master_code в рамках type + org.
	 *
	 * Опирается на Model_Erp230_Code::code_exists (реестр object_codes, регистронезависимо),
	 * с поддержкой исключения текущего объекта при редактировании.
	 * При обнаружении дубля бросает исключение с кодом duplicate_master_code.
	 *
	 * @param  string      $master_code
	 * @param  string      $type_id
	 * @param  string      $org_id
	 * @param  string|NULL $exclude_object_id  Исключить объект (для edit)
	 * @return void
	 * @throws Kohana_Exception  при обнаружении дубля
	 */
	public function assert_unique_code($master_code, $type_id, $org_id, $exclude_object_id = NULL)
	{
		if (empty($master_code) OR empty($type_id) OR empty($org_id))
		{
			return;
		}

		if ($this->code_exists($master_code, $type_id, $org_id, $exclude_object_id))
		{
			throw new Kohana_Exception(
				'duplicate_master_code: :code (type=:type, org=:org)',
				[
					':code' => $master_code,
					':type' => $type_id,
					':org'  => $org_id,
				]
			);
		}
	}

	/**
	 * Проверка существования кода (regisтронезависимо) с опциональным exclude.
	 *
	 * Использует Model_Erp230_Code::code_exists, если доступен; иначе — прямой запрос
	 * по object_codes / objects.
	 *
	 * @param  string      $master_code
	 * @param  string      $type_id
	 * @param  string      $org_id
	 * @param  string|NULL $exclude_object_id
	 * @return bool
	 */
	public function code_exists($master_code, $type_id, $org_id, $exclude_object_id = NULL)
	{
		if (empty($master_code) OR empty($type_id) OR empty($org_id))
		{
			return FALSE;
		}

		if (class_exists('Model_Erp230_Code')
			AND method_exists('Model_Erp230_Code', 'code_exists'))
		{
			return (bool) Model_Erp230_Code::code_exists($master_code, $type_id, $org_id, $exclude_object_id);
		}

		// Fallback: прямой запрос по objects.
		$query = ORM::factory('Erp230_Object')
			->where(DB::expr('UPPER(master_code)'), '=', strtoupper($master_code))
			->where('type_id', '=', $type_id)
			->where('org_id', '=', $org_id)
			->where('deleted_at', 'IS', NULL);

		if ( ! empty($exclude_object_id))
		{
			$query->where('id', '!=', $exclude_object_id);
		}

		return $query->count_all() > 0;
	}

	/**
	 * Поиск кандидатов-дубликатов по бизнес-ключам идентичности (identity_keys).
	 *
	 * identity_keys берутся из схемы типа (x-mdm.identity_keys). Для каждого
	 * ключа значение сопоставляется с текущими свойствами (is_current = TRUE).
	 * Кандидатом считается объект, у которого совпадают ВСЕ заданные ключи.
	 *
	 * @param  string $type_id
	 * @param  string $org_id
	 * @param  array  $properties  Плоский map key => value
	 * @return array               Список кандидатов [{id, master_code, name, state, matched_keys}]
	 */
	public function find_by_identity_keys($type_id, $org_id, array $properties)
	{
		$keys = $this->identity_keys_for_type($type_id);

		if (empty($keys) OR empty($properties))
		{
			return [];
		}

		// Оставляем только те identity-ключи, для которых пришли значения.
		$match = [];
		foreach ($keys as $key)
		{
			if (array_key_exists($key, $properties)
				AND $properties[$key] !== NULL
				AND $properties[$key] !== '')
			{
				$match[$key] = $properties[$key];
			}
		}

		if (empty($match))
		{
			return [];
		}

		$db = Database::instance('mdm');

		// Для каждого identity-ключа ищем множество object_id с совпадающим
		// текущим значением, затем пересекаем множества (совпасть должны ВСЕ ключи).
		$object_ids = NULL;

		foreach ($match as $key => $value)
		{
			$json = json_encode($value, JSON_UNESCAPED_UNICODE);

			$rows = DB::select('op.object_id')
				->from(['object_properties', 'op'])
				->join(['objects', 'o'], 'INNER')
					->on('o.id', '=', 'op.object_id')
				->where('op.key', '=', $key)
				->where('op.is_current', '=', TRUE)
				->where('o.type_id', '=', $type_id)
				->where('o.org_id', '=', $org_id)
				->where('o.deleted_at', 'IS', NULL)
				->where('o.state', '!=', 'merged')
				->where(DB::expr('op.value::text'), '=', $json)
				->execute($db)
				->as_array(NULL, 'object_id');

			$ids = array_values(array_unique($rows));

			if (empty($ids))
			{
				// Хотя бы по одному ключу нет совпадений — пересечение пусто.
				return [];
			}

			$object_ids = ($object_ids === NULL)
				? $ids
				: array_values(array_intersect($object_ids, $ids));

			if (empty($object_ids))
			{
				return [];
			}
		}

		if (empty($object_ids))
		{
			return [];
		}

		return $this->_build_candidates($object_ids, array_keys($match));
	}

	/**
	 * Мягкий поиск похожих объектов (fulltext / semantic) для UI-предупреждений.
	 *
	 * Никогда не блокирует создание — только возвращает кандидатов для показа.
	 * Делегирует в Model_Erp230_Search::suggest_for_guard, если он реализован.
	 *
	 * @param  string      $q
	 * @param  string|NULL $type_id
	 * @param  string|NULL $org_id
	 * @param  int         $limit
	 * @return array  Список кандидатов [{id, master_code, name, ...}]
	 */
	public function suggest_similar($q, $type_id = NULL, $org_id = NULL, $limit = 10)
	{
		$q = trim((string) $q);

		if ($q === '')
		{
			return [];
		}

		try
		{
			if (class_exists('Model_Erp230_Search'))
			{
				$search = ORM::factory('Erp230_Search');

				if (method_exists($search, 'suggest_for_guard'))
				{
					$rows = $search->suggest_for_guard($q, $type_id, $org_id, $limit);

					return is_array($rows) ? $rows : [];
				}
			}
		}
		catch (Exception $e)
		{
			// Similar — не критичный путь: молча возвращаем пусто.
			return [];
		}

		return [];
	}

	/**
	 * Агрегированная проверка дублей для UI/API.
	 *
	 * Порядок (F.3):
	 *   1. xref              → block (duplicate_xref)
	 *   2. master_code       → block (duplicate_master_code)
	 *   3. identity_keys     → warn/block (duplicate_identity) в зависимости от identity_mode
	 *   4. similar (q)       → warn (только кандидаты)
	 *
	 * @param  array $context  master_code, type_id, org_id, properties,
	 *                         source_id, remote_id, exclude_object_id, q, identity_mode
	 * @return array {
	 *   status: 'ok'|'warn'|'block',
	 *   reason: 'duplicate_master_code'|'duplicate_xref'|'duplicate_identity'|NULL,
	 *   candidates: [ {id, master_code, name, match|matched_keys}, ... ]
	 * }
	 */
	public function check(array $context)
	{
		$type_id           = Arr::get($context, 'type_id');
		$org_id            = Arr::get($context, 'org_id');
		$master_code       = Arr::get($context, 'master_code');
		$source_id         = Arr::get($context, 'source_id');
		$remote_id         = Arr::get($context, 'remote_id');
		$exclude_object_id = Arr::get($context, 'exclude_object_id');
		$properties        = Arr::get($context, 'properties', []);
		$q                 = Arr::get($context, 'q');
		$identity_mode     = Arr::get($context, 'identity_mode', 'block');

		if ( ! is_array($properties))
		{
			$properties = [];
		}

		$result = [
			'status'     => 'ok',
			'reason'     => NULL,
			'candidates' => [],
		];

		// 1. Xref.
		if ( ! empty($source_id) AND ! empty($remote_id))
		{
			$by_xref = $this->find_by_xref($source_id, $remote_id);

			if ($by_xref !== NULL)
			{
				$resolved = $this->_resolve_merged($by_xref);

				$result['status']     = 'block';
				$result['reason']     = 'duplicate_xref';
				$result['candidates'] = [$this->_candidate_from_object($resolved)];

				return $result;
			}
		}

		// 2. master_code.
		if ( ! empty($master_code) AND ! empty($type_id) AND ! empty($org_id))
		{
			if ($this->code_exists($master_code, $type_id, $org_id, $exclude_object_id))
			{
				$by_code = $this->find_by_code($master_code, $type_id, $org_id);

				$candidates = [];
				if ($by_code !== NULL)
				{
					$resolved = $this->_resolve_merged($by_code);

					// Исключаем self при edit.
					$rid = $this->_object_id($resolved);
					if (empty($exclude_object_id) OR $rid !== $exclude_object_id)
					{
						$candidates[] = $this->_candidate_from_object($resolved);
					}
				}

				$result['status']     = 'block';
				$result['reason']     = 'duplicate_master_code';
				$result['candidates'] = $candidates;

				return $result;
			}
		}

		// 3. identity_keys.
		if ( ! empty($type_id) AND ! empty($org_id) AND ! empty($properties))
		{
			$identity = $this->find_by_identity_keys($type_id, $org_id, $properties);

			// Исключаем self при edit.
			if ( ! empty($exclude_object_id))
			{
				$identity = array_values(array_filter(
					$identity,
					function ($c) use ($exclude_object_id) {
						return Arr::get($c, 'id') !== $exclude_object_id;
					}
				));
			}

			if ( ! empty($identity))
			{
				if ($identity_mode === 'skip')
				{
					// Игнорируем identity полностью.
				}
				elseif ($identity_mode === 'warn')
				{
					$result['status']     = 'warn';
					$result['reason']     = 'duplicate_identity';
					$result['candidates'] = $identity;

					return $result;
				}
				else // block
				{
					$result['status']     = 'block';
					$result['reason']     = 'duplicate_identity';
					$result['candidates'] = $identity;

					return $result;
				}
			}
		}

		// 4. similar (soft warn only).
		if ( ! empty($q))
		{
			$similar = $this->suggest_similar($q, $type_id, $org_id);

			if ( ! empty($exclude_object_id))
			{
				$similar = array_values(array_filter(
					$similar,
					function ($c) use ($exclude_object_id) {
						return Arr::get($c, 'id') !== $exclude_object_id;
					}
				));
			}

			if ( ! empty($similar))
			{
				$result['status']     = 'warn';
				$result['reason']     = NULL;
				$result['candidates'] = $similar;

				return $result;
			}
		}

		return $result;
	}

	/**
	 * Получить identity_keys для типа из схемы (x-mdm.identity_keys).
	 *
	 * @param  string $type_id
	 * @return array  Список ключей (может быть пустым)
	 */
	public function identity_keys_for_type($type_id)
	{
		if (empty($type_id))
		{
			return [];
		}

		try
		{
			$type = ORM::factory('Erp230_Type', $type_id);

			if ( ! $type->loaded())
			{
				return [];
			}

			// Предпочитаем хелпер модели, если реализован.
			if (method_exists($type, 'identity_keys'))
			{
				$keys = $type->identity_keys();
			}
			else
			{
				$schema = $this->_schema_array($type);
				$x_mdm  = Arr::get($schema, 'x-mdm', []);
				$keys   = Arr::get($x_mdm, 'identity_keys', []);
			}

			if ( ! is_array($keys))
			{
				return [];
			}

			// Только непустые строковые ключи.
			$out = [];
			foreach ($keys as $k)
			{
				if (is_string($k) AND $k !== '')
				{
					$out[] = $k;
				}
			}

			return array_values(array_unique($out));
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	/**
	 * Построение списка кандидатов из набора object_id.
	 *
	 * @param  array $object_ids
	 * @param  array $matched_keys
	 * @return array
	 */
	protected function _build_candidates(array $object_ids, array $matched_keys)
	{
		$candidates = [];

		$objects = ORM::factory('Erp230_Object')
			->where('id', 'IN', $object_ids)
			->where('deleted_at', 'IS', NULL)
			->find_all();

		foreach ($objects as $object)
		{
			if ( ! $object->loaded())
			{
				continue;
			}

			// Пропускаем merged-редиректы — резолвим в цель.
			$resolved = $this->_resolve_merged($object);

			$candidate = $this->_candidate_from_object($resolved);
			$candidate['matched_keys'] = $matched_keys;

			$candidates[] = $candidate;
		}

		return $candidates;
	}

	/**
	 * Собрать структуру кандидата из объекта.
	 *
	 * @param  Model_Erp230_Object $object
	 * @return array
	 */
	protected function _candidate_from_object(Model_Erp230_Object $object)
	{
		return [
			'id'          => $this->_object_id($object),
			'master_code' => $object->master_code,
			'name'        => $object->display_name(),
			'state'       => $object->state,
		];
	}

	/**
	 * Нормализованное получение id объекта (учёт составного PK).
	 *
	 * @param  Model_Erp230_Object $object
	 * @return mixed
	 */
	protected function _object_id(Model_Erp230_Object $object)
	{
		return is_array($object->id) ? Arr::get($object->id, 'id') : $object->id;
	}

	/**
	 * Разрешение merged-редиректа: если объект merged — вернуть целевой.
	 *
	 * @param  Model_Erp230_Object $object
	 * @return Model_Erp230_Object
	 */
	protected function _resolve_merged(Model_Erp230_Object $object)
	{
		if ($object->state === 'merged' AND ! empty($object->merged_into))
		{
			$target = ORM::factory('Erp230_Object', $object->merged_into);
			if ($target->loaded())
			{
				return $target;
			}
		}

		return $object;
	}

	/**
	 * Извлечение schema типа как массива.
	 * Поддерживает метод schema_array() модели, если он реализован,
	 * иначе декодирует поле schema самостоятельно.
	 *
	 * @param  Model_Erp230_Type $type
	 * @return array
	 */
	protected function _schema_array(Model_Erp230_Type $type)
	{
		if (method_exists($type, 'schema_array'))
		{
			$arr = $type->schema_array();
			return is_array($arr) ? $arr : [];
		}

		$schema = $type->schema;

		if (is_array($schema))
		{
			return $schema;
		}

		if (is_string($schema) AND $schema !== '')
		{
			$decoded = json_decode($schema, TRUE);
			return (json_last_error() === JSON_ERROR_NONE AND is_array($decoded)) ? $decoded : [];
		}

		return [];
	}
}