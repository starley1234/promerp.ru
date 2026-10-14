<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Справочник источников данных. Таблица sources.
 * Тест использует логические имена display_name/trust_score — маппим на name/trust.
 */
class Model_Erp230_Source extends Model_Erp230_Main {

	protected $_table_name  = 'sources';
	protected $_primary_key = 'id';

	protected $_table_columns = [
		'id'    => ['type' => 'string'],
		'name'  => ['type' => 'string'],
		'kind'  => ['type' => 'string'],
		'trust' => ['type' => 'int'],
	];

	/** Алиасы: логическое имя => реальная колонка. */
	protected static $_aliases = [
		'display_name' => 'name',
		'trust_score'  => 'trust',
	];

	public function __set($column, $value)
	{
		if (isset(self::$_aliases[$column]))
		{
			$column = self::$_aliases[$column];
		}
		return parent::__set($column, $value);
	}

	public function __get($column)
	{
		if (isset(self::$_aliases[$column]))
		{
			$column = self::$_aliases[$column];
		}
		return parent::__get($column);
	}

	/** kind в БД NOT NULL, а тест его не задаёт — проставляем дефолт. */
	protected function before_create()
	{
		if ($this->kind === NULL OR $this->kind === '')
		{
			$this->kind = 'external';
		}
	}
}