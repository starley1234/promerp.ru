<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фабрика создания мастер-объектов (Object Factory).
 *
 * Единая точка входа для создания объектов из UI, API и коннекторов.
 * Инкапсулирует канонический write-path:
 *   normalize master_code → DuplicateGuard (xref, code, identity)
 *   → create draft → xref → properties (batch) → event.
 *
 * Объект ВСЕГДА создаётся в состоянии draft. Перевод в active выполняется
 * отдельно через Model_Erp230_Object::transition_to() с валидацией схемы.
 *
 * @package Erp230
 * @version 1.0.0
 */
class Service_Erp230_ObjectFactory {

	const DEFAULT_SOURCE = 'manual';

	/**
	 * @var Service_Erp230_ObjectFactory
	 */
	protected static $_instance;

	/**
	 * Singleton instance
	 *
	 * @return Service_Erp230_ObjectFactory
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
	 * Создание (или возврат существующего) мастер-объекта.
	 *
	 * @param  array $payload  type_id, org_id, master_code?, master_code_mode?, properties?, source_id?, remote_id?
	 * @param  array $options  source_id, remote_id, allow_existing, actor_id, skip_properties, identity_mode
	 * @return Model_Erp230_Object
	 * @throws Kohana_Exception|Validation_Exception|ORM_Validation_Exception
	 */
	public function create(array $payload, array $options = [])
	{
		// 1. Валидация входных данных.
		$validation = Validation::factory($payload)
			->rule('type_id', 'not_empty')
			->rule('org_id', 'max_length', [':value', 64]);

		if ( ! $validation->check())
		{
			throw new Validation_Exception($validation);
		}

		$type_id = Arr::get($payload, 'type_id');
		$org_id  = Arr::get($payload, 'org_id');

		$properties = Arr::get($payload, 'properties', []);
		if ( ! is_array($properties))
		{
			throw new Kohana_Exception('Payload "properties" must be an array');
		}

		// 2. Источник и remote_id.
		$source_id = Arr::get($options, 'source_id', Arr::get($payload, 'source_id', self::DEFAULT_SOURCE));
		$remote_id = Arr::get($options, 'remote_id', Arr::get($payload, 'remote_id'));

		$allow_existing = (bool) Arr::get($options, 'allow_existing', FALSE);
		$actor_id       = Arr::get($options, 'actor_id');
		$skip_props     = (bool) Arr::get($options, 'skip_properties', FALSE);

		$identity_mode  = Arr::get($options, 'identity_mode', 'block');

		$guard = Service_Erp230_DuplicateGuard::instance();
		$db    = Database::instance('mdm');
		$db->begin();

		try
		{
			$is_new = FALSE;

			// 3. Поиск по кросс-референсу (source_id + remote_id).
			$object = NULL;
			if ( ! empty($source_id) AND ! empty($remote_id))
			{
				$found = $guard->find_by_xref($source_id, $remote_id);
				if ($found !== NULL AND $found->loaded())
				{
					$found = $this->_resolve_merged($found);

					if ($allow_existing)
					{
						$object = $found;
					}
					else
					{
						$db->rollback();
						$this->_conflict('duplicate_xref', [$this->_candidate($found)]);
					}
				}
			}

			// 4. Нормализация master_code / авто-генерация.
			$master_code_mode = Arr::get($payload, 'master_code_mode', 'manual');
			$master_code      = Arr::get($payload, 'master_code');

			$generator = Service_Erp230_CodeGenerator::instance();
			$policy    = $generator->policy_for_type($type_id);

			$master_code = $generator->normalize($master_code, $policy);

			if ($object === NULL)
			{
				$auto = ($master_code_mode === 'auto')
					OR ($master_code === '' AND Arr::get($policy, 'mode') === 'auto');

				if ($auto)
				{
					$master_code = $generator->allocate($type_id, $org_id, $policy);
				}

				if ($master_code === '' OR $master_code === NULL)
				{
					$db->rollback();
					throw new Kohana_Exception('master_code is required (policy mode: :m)', [
						':m' => Arr::get($policy, 'mode', 'manual'),
					]);
				}

				// 5. Проверка уникальности кода.
				$existing = $guard->find_by_code($master_code, $type_id, $org_id);
				if ($existing !== NULL AND $existing->loaded())
				{
					$existing = $this->_resolve_merged($existing);

					if ($allow_existing)
					{
						$object = $existing;
					}
					else
					{
						$db->rollback();
						$this->_conflict('duplicate_master_code', [$this->_candidate($existing)]);
					}
				}
			}

			// 6. Проверка identity_keys (по свойствам).
			if ($object === NULL AND $identity_mode !== 'skip' AND ! empty($properties))
			{
				$candidates = $guard->find_by_identity_keys($type_id, $org_id, $this->_flatten_properties($properties));

				if ( ! empty($candidates))
				{
					if ($identity_mode === 'block')
					{
						$db->rollback();
						$this->_conflict('duplicate_identity', $candidates);
					}
					// warn: не блокируем создание, кандидаты вернутся вызывающему через event/log.
				}
			}

			// 7. Создание draft-объекта (state форсируется).
			if ($object === NULL)
			{
				$object = ORM::factory('Erp230_Object')
					->values([
						'type_id'     => $type_id,
						'org_id'      => $org_id,
						'master_code' => $master_code,
						'state'       => 'draft',
					])
					->create();

				$is_new = TRUE;

				// 8. Кросс-референс (Xref read-only для ORM — пишем DB::query).
				$object_id = $this->_object_id($object);

				if ( ! empty($source_id) AND ! empty($remote_id))
				{
					DB::query(Database::INSERT, '
						INSERT INTO object_xref (source_id, remote_id, object_id)
						VALUES (:source_id, :remote_id, :object_id)
						ON CONFLICT (source_id, remote_id) DO NOTHING
					')
						->param(':source_id', $source_id)
						->param(':remote_id', $remote_id)
						->param(':object_id', $object_id)
						->execute($db);
				}
			}

			$object_id = $this->_object_id($object);

			// 9. Свойства (batch без собственной транзакции).
			if ( ! $skip_props AND ! empty($properties))
			{
				$props = $this->_normalize_properties($properties);

				Service_Erp230_GoldenRecord::instance()
					->update_attributes_batch($object_id, $props, $source_id, [
						'use_transaction' => FALSE,
					]);
			}

			$db->commit();

			// 10. Событие о создании нового объекта.
			if ($is_new AND class_exists('Event'))
			{
				Event::fire('erp230.object.created', [
					'object_id' => $object_id,
					'source_id' => $source_id,
					'actor_id'  => $actor_id,
				]);
			}


			return $object;
		}
		catch (Exception $e)
		{
			// Транзакция уже откачена в местах конфликта; на всякий случай.
			try { $db->rollback(); } catch (Exception $ignore) {}
			throw $e;
		}
	}

	/**
	 * Поиск/создание объекта для консолидации.
	 * Только xref → code → create draft + xref. Свойства не пишет.
	 *
	 * @param  string $source_id
	 * @param  string $remote_id
	 * @param  array  $payload  type_id, org_id, master_code
	 * @return Model_Erp230_Object
	 */
	public function find_or_create_for_consolidate($source_id, $remote_id, array $payload)
	{
		return $this->create($payload, [
			'source_id'       => $source_id,
			'remote_id'       => $remote_id,
			'allow_existing'  => TRUE,
			'skip_properties' => TRUE,
			'identity_mode'   => 'skip',
		]);
	}

	/**
	 * Разрешение merged-редиректа: если объект в состоянии merged — вернуть цель.
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
	 * Нормализация ID (некоторые драйверы возвращают массив).
	 *
	 * @param  Model_Erp230_Object $object
	 * @return string
	 */
	protected function _object_id(Model_Erp230_Object $object)
	{
		return is_array($object->id) ? Arr::get($object->id, 'id') : $object->id;
	}

	/**
	 * Приведение properties к списку [{key, value, uom}].
	 * Принимает как map key => ['value'=>,'uom'=>], так и list.
	 *
	 * @param  array $properties
	 * @return array
	 */
	protected function _normalize_properties(array $properties)
	{
		$out = [];

		foreach ($properties as $key => $attr)
		{
			// Формат списка: [{key, value, uom}].
			if (is_int($key) AND is_array($attr) AND isset($attr['key']))
			{
				$out[] = [
					'key'   => $attr['key'],
					'value' => Arr::get($attr, 'value'),
					'uom'   => Arr::get($attr, 'uom'),
				];
				continue;
			}

			// Формат map: key => ['value'=>, 'uom'=>] или key => scalar.
			if (is_array($attr))
			{
				$out[] = [
					'key'   => $key,
					'value' => Arr::get($attr, 'value'),
					'uom'   => Arr::get($attr, 'uom'),
				];
			}
			else
			{
				$out[] = [
					'key'   => $key,
					'value' => $attr,
					'uom'   => NULL,
				];
			}
		}

		return $out;
	}

	/**
	 * Плоский map key => value для проверки identity_keys.
	 *
	 * @param  array $properties
	 * @return array
	 */
	protected function _flatten_properties(array $properties)
	{
		$flat = [];

		foreach ($this->_normalize_properties($properties) as $p)
		{
			$flat[$p['key']] = $p['value'];
		}

		return $flat;
	}

	/**
	 * Формирование кандидата для ответа 409.
	 *
	 * @param  Model_Erp230_Object $object
	 * @return array
	 */
	protected function _candidate(Model_Erp230_Object $object)
	{
		return [
			'id'          => $this->_object_id($object),
			'master_code' => $object->master_code,
			'name'        => $object->display_name(),
			'state'       => $object->state,
		];
	}

	/**
	 * Бросок конфликта дубля с машиночитаемым кодом и кандидатами.
	 *
	 * @param  string $code
	 * @param  array  $candidates
	 * @throws Kohana_Exception
	 */
	protected function _conflict($code, array $candidates)
	{
		$e = new Kohana_Exception('Duplicate detected: :code', [':code' => $code]);
		$e->duplicate_code = $code;
		$e->duplicate_candidates = $candidates;
		throw $e;
	}
}