<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Валидатор JSON-схемы типа для мастер-объектов (Schema Validator).
 *
 * Лёгкая реализация проверки свойств объекта против схемы типа (types.schema).
 * Не является полноценным движком JSON Schema draft-07 — покрывает минимально
 * необходимый набор правил, достаточный для публикации объекта (draft → active):
 *
 *   - required[]                    — обязательные ключи
 *   - properties[key].type          — string|number|integer|boolean|enum
 *   - properties[key].enum          — допустимые значения
 *   - properties[key].minLength     — минимальная длина строки
 *   - properties[key].maxLength     — максимальная длина строки (доп.)
 *   - properties[key].minimum       — минимум для чисел (доп.)
 *   - properties[key].maximum       — максимум для чисел (доп.)
 *   - формат ключа                  — ^[a-z][a-z0-9_.]*$
 *
 * Вызывается из Model_Erp230_Object::transition_to('active') и опционально
 * из ObjectFactory (soft-warn).
 *
 * @package Erp230
 * @version 1.0.0
 */
class Service_Erp230_SchemaValidator {

	const KEY_PATTERN = '/^[a-z][a-z0-9_.]*$/';

	/**
	 * @var Service_Erp230_SchemaValidator
	 */
	protected static $_instance;

	/**
	 * Singleton instance
	 *
	 * @return Service_Erp230_SchemaValidator
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
	 * Валидация набора свойств против схемы типа.
	 *
	 * @param  array        $properties  key => value (уже скаляры)
	 * @param  array|string $schema      types.schema (array или JSON-строка)
	 * @return array ['ok' => bool, 'errors' => [key => message]]
	 */
	public function validate(array $properties, $schema)
	{
		$schema = $this->_schema_to_array($schema);
		$errors = [];

		if (empty($schema))
		{
			// Нет схемы — считаем валидным (проверка отключена для типа).
			return ['ok' => TRUE, 'errors' => []];
		}

		$props_schema = Arr::get($schema, 'properties', []);
		if ( ! is_array($props_schema))
		{
			$props_schema = [];
		}

		// 1. Проверка обязательных ключей.
		$required = Arr::get($schema, 'required', []);
		if (is_array($required))
		{
			foreach ($required as $key)
			{
				if ( ! $this->_has_value($properties, $key))
				{
					$errors[$key] = 'Обязательное поле не заполнено';
				}
			}
		}

		// 2. Проверка каждого предоставленного свойства.
		foreach ($props_schema as $key => $rules)
		{
			if ( ! is_array($rules))
			{
				continue;
			}

			// Значение отсутствует и поле не обязательное — пропускаем.
			if ( ! $this->_has_value($properties, $key))
			{
				continue;
			}

			// Уже помечено как ошибка required — не дублируем.
			if (isset($errors[$key]))
			{
				continue;
			}

			$value = Arr::get($properties, $key);
			$error = $this->_validate_value($value, $rules);

			if ($error !== NULL)
			{
				$errors[$key] = $error;
			}
		}

		return [
			'ok'     => empty($errors),
			'errors' => $errors,
		];
	}

	/**
	 * Валидация свойств объекта: тянет схему через type_id и текущие свойства.
	 *
	 * @param  string $object_id
	 * @return array ['ok' => bool, 'errors' => [key => message]]
	 * @throws Kohana_Exception
	 */
	public function validate_object($object_id)
	{
		$object = ORM::factory('Erp230_Object', $object_id);

		if ( ! $object->loaded())
		{
			throw new Kohana_Exception('Object not found: :id', [':id' => $object_id]);
		}

		$type = ORM::factory('Erp230_Type', $object->type_id);

		if ( ! $type->loaded())
		{
			// Нет типа — нет схемы для проверки.
			return ['ok' => TRUE, 'errors' => []];
		}

		$schema = method_exists($type, 'schema_array')
			? $type->schema_array()
			: $this->_schema_to_array($type->schema);

		$properties = $this->_current_properties($object);

		return $this->validate($properties, $schema);
	}

	/**
	 * Проверка формата имени ключа свойства.
	 *
	 * @param  string $key
	 * @return bool
	 */
	public function validate_key_format($key)
	{
		return (bool) preg_match(self::KEY_PATTERN, (string) $key);
	}

	/**
	 * Валидация одного значения против набора правил свойства.
	 *
	 * @param  mixed $value
	 * @param  array $rules
	 * @return string|null  Сообщение об ошибке или NULL, если валидно.
	 */
	protected function _validate_value($value, array $rules)
	{
		// enum имеет приоритет над type.
		if (isset($rules['enum']) AND is_array($rules['enum']))
		{
			if ( ! in_array($value, $rules['enum'], FALSE))
			{
				return 'Недопустимое значение (ожидается одно из: '
					.implode(', ', array_map('strval', $rules['enum'])).')';
			}
			// enum-значение прошло — остальные type-проверки не критичны.
		}

		$type = Arr::get($rules, 'type');

		if ($type !== NULL)
		{
			$type_error = $this->_validate_type($value, $type);
			if ($type_error !== NULL)
			{
				return $type_error;
			}
		}

		// Ограничения строк.
		if ($this->_is_stringy($type))
		{
			$len = mb_strlen((string) $value);

			if (isset($rules['minLength']) AND $len < (int) $rules['minLength'])
			{
				return 'Минимальная длина: '.(int) $rules['minLength'];
			}

			if (isset($rules['maxLength']) AND $len > (int) $rules['maxLength'])
			{
				return 'Максимальная длина: '.(int) $rules['maxLength'];
			}
		}

		// Ограничения чисел.
		if ($type === 'number' OR $type === 'integer')
		{
			$num = $value + 0;

			if (isset($rules['minimum']) AND $num < $rules['minimum'])
			{
				return 'Минимальное значение: '.$rules['minimum'];
			}

			if (isset($rules['maximum']) AND $num > $rules['maximum'])
			{
				return 'Максимальное значение: '.$rules['maximum'];
			}
		}

		return NULL;
	}

	/**
	 * Проверка соответствия значения типу.
	 *
	 * @param  mixed  $value
	 * @param  string $type
	 * @return string|null
	 */
	protected function _validate_type($value, $type)
	{
		switch ($type)
		{
			case 'string':
			case 'text':
				if ( ! is_scalar($value))
				{
					return 'Ожидается строка';
				}
				break;

			case 'number':
				if ( ! is_numeric($value))
				{
					return 'Ожидается число';
				}
				break;

			case 'integer':
				if ( ! is_numeric($value) OR (string)(int) $value !== (string) $value)
				{
					// Разрешаем целочисленные значения, представленные числом.
					if ( ! is_numeric($value) OR floor($value + 0) != ($value + 0))
					{
						return 'Ожидается целое число';
					}
				}
				break;

			case 'boolean':
				if ( ! is_bool($value)
					AND ! in_array($value, [0, 1, '0', '1', 'true', 'false', TRUE, FALSE], TRUE))
				{
					return 'Ожидается логическое значение';
				}
				break;

			case 'enum':
				// enum без списка значений — валидируется отдельно в _validate_value.
				break;

			default:
				// Неизвестный тип — не блокируем.
				break;
		}

		return NULL;
	}

	/**
	 * Является ли тип строковым (для проверки длины).
	 *
	 * @param  string|null $type
	 * @return bool
	 */
	protected function _is_stringy($type)
	{
		return ($type === 'string' OR $type === 'text' OR $type === NULL OR $type === 'enum');
	}

	/**
	 * Проверка наличия непустого значения по ключу.
	 *
	 * @param  array  $properties
	 * @param  string $key
	 * @return bool
	 */
	protected function _has_value(array $properties, $key)
	{
		if ( ! array_key_exists($key, $properties))
		{
			return FALSE;
		}

		$value = $properties[$key];

		if ($value === NULL)
		{
			return FALSE;
		}

		if (is_string($value) AND trim($value) === '')
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Извлечение текущих свойств объекта в формате key => value.
	 *
	 * @param  Model_Erp230_Object $object
	 * @return array
	 */
	protected function _current_properties(Model_Erp230_Object $object)
	{
		$flat = [];

		if (method_exists($object, 'current_properties'))
		{
			$props = $object->current_properties();

			if (is_array($props))
			{
				foreach ($props as $key => $entry)
				{
					// Формат map: key => ['value'=>..., ...] или key => scalar.
					if (is_array($entry) AND array_key_exists('value', $entry))
					{
						$flat[$key] = $entry['value'];
					}
					elseif (is_int($key) AND is_array($entry) AND isset($entry['key']))
					{
						// Формат списка: [{key, value, ...}].
						$flat[$entry['key']] = Arr::get($entry, 'value');
					}
					else
					{
						$flat[$key] = $entry;
					}
				}
			}
		}

		return $flat;
	}

	/**
	 * Приведение схемы к массиву (принимает array или JSON-строку).
	 *
	 * @param  array|string $schema
	 * @return array
	 */
	protected function _schema_to_array($schema)
	{
		if (is_array($schema))
		{
			return $schema;
		}

		if (is_string($schema) AND $schema !== '')
		{
			$decoded = json_decode($schema, TRUE);

			if (json_last_error() === JSON_ERROR_NONE AND is_array($decoded))
			{
				return $decoded;
			}
		}

		return [];
	}
}