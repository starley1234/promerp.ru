<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель настроек пользователя.
 * 
 * ВНИМАНИЕ: Для исправления ошибки "Undefined array key 'type'" в Kohana_Database_MySQLi::list_columns
 * при использовании JSON полей, необходимо переопределить метод list_columns.
 * Ошибка возникает из-за того, что драйвер MySQLi в Kohana не знает о типе JSON и не заполняет ключ 'type'.
 */
class Model_Erp100_User_Setting extends ORM {

	protected $_table_name = 'users_settings';
	protected $_primary_key = 'user_id';

	protected $_belongs_to = array(
		'user' => array(
			'model'       => 'User',
			'foreign_key' => 'user_id',
		),
	);

	/**
	 * Переопределение для исправления ошибки драйвера БД при работе с JSON полями.
	 * Мы вызываем прямой запрос к БД, чтобы обойти некорректный парсинг в MySQLi.php
	 */
	public function list_columns()
	{
		// Получаем список колонок напрямую через запрос, чтобы избежать ошибки в драйвере
		$columns = $this->_db->query(Database::SELECT, 'SHOW COLUMNS FROM '.$this->_db->quote_table($this->_table_name), TRUE)->as_array();
		
		$result = array();
		$count = 0;
		foreach ($columns as $row)
		{
			$column = array();
			
			$type = $row['Type'];
			if ($open = strpos($type, '('))
			{
				$type = substr($type, 0, $open);
			}

			$result[$row['Field']] = array(
				'column_name'       => $row['Field'],
				'column_default'    => $row['Default'],
				'data_type'         => $type,
				'is_nullable'       => ($row['Null'] == 'YES'),
				'ordinal_position'  => ++$count,
				'type'              => ($type === 'json') ? 'string' : $type, // Принудительно ставим string для json
			);
		}

		return $result;
	}

	/**
	 * Автоматическая десериализация JSON при чтении
	 */
	public function __get($column)
	{
		$value = parent::__get($column);

		if ($column === 'settings_json' AND ! empty($value) AND is_string($value))
		{
			return json_decode($value, TRUE);
		}

		return $value;
	}

	/**
	 * Автоматическая сериализация в JSON при записи
	 */
	public function __set($column, $value)
	{
		if ($column === 'settings_json' AND (is_array($value) OR is_object($value)))
		{
			$value = json_encode($value, JSON_UNESCAPED_UNICODE);
		}

		parent::__set($column, $value);
	}

	/**
	 * Правила валидации
	 */
	public function rules()
	{
		return array(
			'user_id' => array(
				array('not_empty'),
				array('digit'),
			),
		);
	}
}