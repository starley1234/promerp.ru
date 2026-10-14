<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис консолидации мастер-данных (MDM).
 * Обеспечивает прием данных из внешних систем, сопоставление через кросс-референсы
 * и формирование "Золотой записи" на основе баллов доверия.
 *
 * @package Erp230
 * @version 1.2.3
 */
class Service_Erp230_Consolidation {

	/**
	 * @var Service_Erp230_Consolidation
	 */
	protected static $_instance;

	/**
	 * Singleton instance
	 *
	 * @return Service_Erp230_Consolidation
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
	 * Основной метод интеграции: прием данных по объекту из внешней системы.
	 * Использует ObjectFactory для обеспечения целостности и предотвращения дублей.
	 * Идемпотентен по паре (source_id, remote_id) через xref.
	 *
	 * @param string $source_id ID источника (plm, erp, etc.)
	 * @param string $remote_id ID объекта во внешней системе
	 * @param array  $payload   Данные объекта (type_id, master_code, org_id, properties)
	 * @return Model_Erp230_Object
	 * @throws Exception|Kohana_Exception
	 */
	public function consolidate($source_id, $remote_id, array $payload)
	{
		// Нормализация: допускаем плоский контракт (source_id/remote_id внутри payload)
		if ( ! isset($payload['properties']) AND ! isset($payload['type_id']))
		{
			// payload уже «плоский» с верхнего уровня — оставляем как есть
		}

		// 1. Валидация входных данных
		$validation = Validation::factory($payload)
			->rule('type_id', 'not_empty')
			->rule('org_id', 'not_empty')
			->rule('master_code', 'not_empty')
			->rule('properties', 'not_empty')
			->rule('properties', 'is_array');

		if ( ! $validation->check())
		{
			throw new Kohana_Exception('Validation failed: :errors', [
				':errors' => json_encode($validation->errors('validation'))
			]);
		}

		$source_id = (string) $source_id;
		$remote_id = (string) $remote_id;

		$db = Database::instance('mdm');
		$db->begin();

		try
		{
			// 2. Поиск или создание объекта через фабрику (Xref -> Code -> Draft)
			// Повторный вызов с тем же source_id+remote_id возвращает тот же object (xref).
			$factory = Service_Erp230_ObjectFactory::instance();

			$is_new = FALSE;
			if (method_exists($factory, 'find_or_create_for_consolidate'))
			{
				$object = $factory->find_or_create_for_consolidate($source_id, $remote_id, $payload);
				if (is_array($object))
				{
					$is_new = (bool) Arr::get($object, 'is_new', FALSE);
					$object = Arr::get($object, 'object', $object);
				}
				elseif (isset($object->is_new_flag))
				{
					$is_new = (bool) $object->is_new_flag;
				}
			}
			else
			{
				// Fallback: xref → load, иначе create + xref
				$object = $this->_find_by_xref($source_id, $remote_id);
				if ($object === NULL OR ! $object->loaded())
				{
					if (method_exists($factory, 'create'))
					{
						$object = $factory->create($payload, FALSE);
					}
					else
					{
						$object = ORM::factory('Erp230_Object');
						$object->values([
							'type_id'     => $payload['type_id'],
							'org_id'      => $payload['org_id'],
							'master_code' => $payload['master_code'],
						])->create();
					}
					$this->_ensure_xref($source_id, $remote_id, $object->id);
					$is_new = TRUE;
				}
			}

			$object_id = $object->id;
			if (is_array($object_id))
			{
				$object_id = Arr::get($object_id, 'id');
			}
			$object_id = (string) $object_id;

			// 3. Обновление атрибутов через GoldenRecord (Trust Score Logic)
			$result = ['ok' => 0, 'is_new' => $is_new];
			if (class_exists('Service_Erp230_GoldenRecord'))
			{
				$result = Service_Erp230_GoldenRecord::instance()->update_attributes_batch(
					$object_id,
					$payload['properties'],
					$source_id,
					['use_transaction' => FALSE]
				);
				if ( ! is_array($result))
				{
					$result = ['ok' => (int) $result, 'is_new' => $is_new];
				}
				if ( ! isset($result['is_new']))
				{
					$result['is_new'] = $is_new;
				}
			}

			// 4. Если были изменения — обновляем Digital Twin и поисковый индекс
			if ((int) Arr::get($result, 'ok', 0) > 0 OR $is_new)
			{
				DB::query(Database::SELECT, 'SELECT refresh_object_state(:id)')
					->param(':id', $object_id)
					->execute($db);

				if (class_exists('Model_Erp230_Search'))
				{
					ORM::factory('Erp230_Search')->mark_for_reindex($object_id);
				}
			}

			$db->commit();

			// Генерируем системное событие
			if (class_exists('Event'))
			{
				Event::fire('erp230.object.consolidated', [
					'object_id' => $object_id,
					'source_id' => $source_id,
					'remote_id' => $remote_id,
					'is_new'    => (bool) Arr::get($result, 'is_new', $is_new),
				]);
			}

			// Перечитываем объект, чтобы вернуть актуальное состояние
			$fresh = ORM::factory('Erp230_Object', $object_id);
			return $fresh->loaded() ? $fresh : $object;
		}
		catch (Exception $e)
		{
			// Исправление ошибки Call to undefined method Database_PostgreSQL::in_transaction()
			// В Kohana 3.3 Database_PostgreSQL не имеет метода in_transaction().
			// Используем блок try-catch для отката, если транзакция активна.
			try
			{
				$db->rollback();
			}
			catch (Exception $rollback_exception)
			{
				// Игнорируем ошибки отката, если транзакция не была начата
			}
			throw $e;
		}
	}

	/**
	 * Создание неизменяемого снимка (Baseline) для объекта.
	 * Каноническая реализация: prev_hash / seq / snapshot_hash не подставляются вручную —
	 * их формирует триггер БД tg_baseline_chain (Hash-chain) и ORM забирает через RETURNING.
	 *
	 * @param string $object_id UUID объекта
	 * @param string $actor_id  ID пользователя (или объект с ->id)
	 * @param string $code      Код версии (например, "V1.0")
	 * @return Model_Erp230_Baseline
	 * @throws Exception|Kohana_Exception
	 */
	public function create_snapshot($object_id, $actor_id, $code)
	{
		$object_id = (string) $object_id;

		// actor_id может прийти объектом пользователя из контроллера
		if (is_object($actor_id))
		{
			$actor_id = isset($actor_id->id) ? (string) $actor_id->id : (string) $actor_id;
		}
		else
		{
			$actor_id = (string) $actor_id;
		}

		if ($code === NULL OR $code === '')
		{
			$code = 'V'.date('YmdHis');
		}

		$db = Database::instance('mdm');
		$db->begin();

		try
		{
			$object = ORM::factory('Erp230_Object', $object_id);
			if ( ! $object->loaded())
				throw new Kohana_Exception('Object not found', NULL, 404);

			// Гарантируем актуальность Digital Twin перед снимком
			DB::query(Database::SELECT, 'SELECT refresh_object_state(:id)')
				->param(':id', $object_id)
				->execute($db);

			// Текущее состояние из object_state
			$state = DB::select()
				->from('object_state')
				->where('object_id', '=', $object_id)
				->execute($db)
				->current();

			$snapshot_data = '{}';
			if ($state)
			{
				$attrs = Arr::get($state, 'attributes', NULL);
				if ($attrs === NULL)
				{
					// иногда twin хранит data/state
					$attrs = Arr::get($state, 'data', Arr::get($state, 'state', '{}'));
				}
				if (is_array($attrs) OR is_object($attrs))
				{
					$snapshot_data = json_encode($attrs, JSON_UNESCAPED_UNICODE);
				}
				elseif (is_string($attrs) AND $attrs !== '')
				{
					// уже JSON-строка
					$snapshot_data = $attrs;
				}
			}

			// prev_hash, seq, snapshot_hash — триггер tg_baseline_chain
			$baseline = ORM::factory('Erp230_Baseline');

			$values = [
				'object_id' => $object_id,
				'code'      => (string) $code,
				'snapshot'  => $snapshot_data,
				'actor_id'  => $actor_id,
			];

			// org_path — если есть у объекта / в схеме baselines
			if (isset($object->org_path) AND $object->org_path !== NULL AND $object->org_path !== '')
			{
				$values['org_path'] = $object->org_path;
			}
			elseif (isset($object->org_id) AND $object->org_id !== NULL AND $object->org_id !== '')
			{
				$values['org_path'] = $object->org_id;
			}

			$baseline->values($values)->create();

			// Сбрасываем флаг dirty
			$table = $object->table_name();
			$test = DB::update($table)
				->set(['is_dirty' => FALSE])
				->where('id', '=', $object_id)
				->execute($db);
            
			$db->commit();

			// Перечитываем, чтобы гарантированно отдать snapshot_hash/prev_hash/seq
			if ($baseline->loaded() AND $baseline->id)
			{
				$reloaded = ORM::factory('Erp230_Baseline', $baseline->id);
				if ($reloaded->loaded())
				{
					$baseline = $reloaded;
				}
			}

			if (class_exists('Event'))
			{
				Event::fire('erp230.baseline.created', [
					'baseline_id' => $baseline->id,
					'object_id'   => $object_id,
					'code'        => $code,
				]);
			}

			return $baseline;
		}
		catch (Exception $e)
		{
			try
			{
				$db->rollback();
			}
			catch (Exception $rollback_exception) {}
			throw $e;
		}
	}

	/**
	 * Поиск объекта по кросс-референсу (source_id + remote_id).
	 *
	 * @param string $source_id
	 * @param string $remote_id
	 * @return Model_Erp230_Object|NULL
	 */
	protected function _find_by_xref($source_id, $remote_id)
	{
		$db = Database::instance('mdm');
		$row = DB::select('object_id')
			->from('object_xref')
			->where('source_id', '=', (string)$source_id)
			->where('remote_id', '=', (string)$remote_id)
			->execute($db)
			->current();

		if ($row AND ! empty($row['object_id']))
		{
			// Приведение к строке, так как в $row может быть объект или ресурс
			$oid = (string) $row['object_id'];
			$obj = ORM::factory('Erp230_Object', $oid);
			return $obj->loaded() ? $obj : NULL;
		}
		return NULL;
	}

	/**
	 * Создаёт xref, если ещё нет (для идемпотентности consolidate).
	 *
	 * @param string $source_id
	 * @param string $remote_id
	 * @param string $object_id
	 * @return void
	 */
	protected function _ensure_xref($source_id, $remote_id, $object_id)
	{
		$object_id = (string) $object_id;

		if (class_exists('Model_Erp230_Xref'))
		{
			$xref = ORM::factory('Erp230_Xref')
				->where('source_id', '=', $source_id)
				->where('remote_id', '=', $remote_id)
				->find();

			if ( ! $xref->loaded())
			{
				$xref = ORM::factory('Erp230_Xref');
				$xref->values([
					'source_id' => $source_id,
					'remote_id' => $remote_id,
					'object_id' => $object_id,
				])->create();
			}
			return;
		}

		try
		{
			$db = Database::instance('mdm');
			$existing = DB::select('object_id')
				->from('object_xref')
				->where('source_id', '=', $source_id)
				->where('remote_id', '=', $remote_id)
				->limit(1)
				->execute($db)
				->current();

			if ( ! $existing)
			{
				DB::insert('object_xref', ['source_id', 'remote_id', 'object_id'])
					->values([$source_id, $remote_id, $object_id])
					->execute($db);
			}
		}
		catch (Exception $e)
		{
			// схема xref может отличаться — фабрика/триггер закроют сами
		}
	}
}