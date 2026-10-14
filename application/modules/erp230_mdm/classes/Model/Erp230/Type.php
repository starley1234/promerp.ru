<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp230_Type extends Model_Erp230_Main {

	protected $_table_name  = 'types'; 
	protected $_primary_key = 'id';

	protected $_table_columns = [
		'id'           => ['type' => 'string'],
		'parent_id'    => ['type' => 'string'],
		'display_name' => ['type' => 'string'],
		'path'         => ['type' => 'string'],
		'schema'       => ['type' => 'string'],
	];

	protected $_belongs_to = [
		'parent' => [
			'model'       => 'Erp230_Type',
			'foreign_key' => 'parent_id',
		],
	];

	protected $_has_many = [
		'children' => [
			'model'       => 'Erp230_Type',
			'foreign_key' => 'parent_id',
		],
		'objects' => [
			'model'       => 'Erp230_Object',
			'foreign_key' => 'type_id',
		],
	];

	/**
	 * Схема типа как PHP-массив (jsonb → array).
	 *
	 * @return array
	 */
	public function schema_array()
	{
		$schema = $this->schema;

		if ($schema === NULL OR $schema === '')
		{
			return [];
		}

		if (is_array($schema))
		{
			return $schema;
		}

		if (is_object($schema))
		{
			$decoded = json_decode(json_encode($schema), TRUE);
			return is_array($decoded) ? $decoded : [];
		}

		$decoded = json_decode((string) $schema, TRUE);

		return (json_last_error() === JSON_ERROR_NONE AND is_array($decoded))
			? $decoded
			: [];
	}

	/**
	 * Запись схемы из массива/объекта/JSON-строки.
	 *
	 * @param  mixed $schema
	 * @return $this
	 */
	public function set_schema($schema)
	{
		if (is_array($schema) OR is_object($schema))
		{
			$this->schema = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		elseif ($schema === NULL OR $schema === '')
		{
			$this->schema = '{}';
		}
		else
		{
			$decoded = json_decode((string) $schema, TRUE);
			$this->schema = (json_last_error() === JSON_ERROR_NONE)
				? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: '{}';
		}

		return $this;
	}

	/**
	 * Список полей схемы (properties), если схема в формате JSON Schema-like.
	 *
	 * @return array
	 */
	public function schema_properties()
	{
		$schema = $this->schema_array();

		if (isset($schema['properties']) AND is_array($schema['properties']))
		{
			return $schema['properties'];
		}

		return $schema;
	}

	/**
	 * Обязательные ключи атрибутов из схемы.
	 *
	 * @return array
	 */
	public function required_keys()
	{
		$schema = $this->schema_array();

		if (isset($schema['required']) AND is_array($schema['required']))
		{
			return array_values($schema['required']);
		}

		return [];
	}

	public function filters()
	{
		return [
			TRUE => [
				['trim'],
			],
			'id' => [
				['mb_strtolower'],
			],
			'display_name' => [
				['strip_tags'],
			],
			// ИСПРАВЛЕНИЕ: Если parent_id пустая строка, превращаем в NULL для корректного Foreign Key в БД
			'parent_id' => [
				[function($value) {
					return (string) $value === '' ? NULL : $value;
				}]
			],
		];
	}

	public function rules()
	{
		return [
			'id' => [
				['not_empty'],
				['regex', [':value', '/^[a-z0-9_]+$/']],
				[[$this, 'unique'], ['id', ':value']],
			],
			'display_name' => [
				['not_empty'],
				['max_length', [':value', 255]],
			],
			'parent_id' => [
				[[$this, 'parent_exists']],
			],
		];
	}

	/**
	 * @param  mixed $value
	 * @return bool
	 */
	public function parent_exists($value)
	{
		if ($value === NULL OR $value === '')
		{
			return TRUE;
		}

		if ($this->loaded() AND (string) $value === (string) $this->id)
		{
			return FALSE;
		}

		$parent = ORM::factory('Erp230_Type', $value);

		return $parent->loaded();
	}

	/**
	 * Уникальность первичного ключа (text id).
	 *
	 * @param  string $field
	 * @param  mixed  $value
	 * @return bool
	 */
	public function unique($field, $value)
	{
		$model = ORM::factory($this->object_name())
			->where($field, '=', $value);

		if ($this->loaded())
		{
			$model->where($this->_primary_key, '!=', $this->pk());
		}

		return ! $model->find()->loaded();
	}

	public function __toString()
	{
		return (string) ($this->display_name ?: $this->id);
	}
}