<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для работы с журналом событий и телеметрии (Audit Log).
 * Обеспечивает хранение истории изменений объектов и метрик цифрового двойника.
 * Таблица erp_227_events является секционированной по полю created_at.
 *
 * @property integer    $id           Идентификатор события
 * @property integer    $object_id    ID объекта из реестра (erp_227_objects)
 * @property string     $event_type   Тип события (created, updated, status_change, sensor_data и т.д.)
 * @property integer    $actor_id     ID пользователя или системы, инициировавшей событие
 * @property string     $payload      JSON-данные события (изменения, контекст)
 * @property float      $metric_value Числовое значение (для телеметрии и датчиков)
 * @property string     $created_at   Дата и время события
 *
 * @property Model_Erp227_Object $object Связанный объект реестра
 */
class Model_Erp227_Event extends Model_Erp227_Base {

	protected $_table_name = 'erp_227_events';

	protected $_belongs_to = [
		'object' => [
			'model'       => 'Erp227_Object',
			'foreign_key' => 'object_id',
		],
	];

	// Автоматическое заполнение даты создания
	protected $_created_column = [
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	];

	/**
	 * Правила валидации
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'object_id' => [
				['not_empty'],
				['digit'],
			],
			'event_type' => [
				['not_empty'],
				['max_length', [':value', 50]],
			],
			'actor_id' => [
				['digit'],
			],
			'metric_value' => [
				['numeric'],
			],
		];
	}

	/**
	 * Человекопонятные метки полей
	 *
	 * @return array
	 */
	public function labels()
	{
		return [
			'id'           => 'ID события',
			'object_id'    => 'Объект',
			'event_type'   => 'Тип события',
			'actor_id'     => 'Инициатор',
			'payload'      => 'Данные события',
			'metric_value' => 'Значение метрики',
			'created_at'   => 'Дата и время',
		];
	}

	/**
	 * Фильтры очистки данных
	 *
	 * @return array
	 */
	public function filters()
	{
		return [
			'event_type' => [
				['trim'],
				['strip_tags'],
			],
		];
	}

	/**
	 * Переопределение метода сохранения.
	 * Поскольку таблица секционирована, в некоторых конфигурациях PostgreSQL 
	 * возврат ID через RETURNING может иметь особенности, но стандартный ORM Kohana 
	 * обычно справляется, если секции созданы корректно.
	 * 
	 * @param Validation $validation
	 * @return ORM
	 */
	public function save(?Validation $validation = NULL)
	{
		// Если payload передан как массив, кодируем в JSON
		if (is_array($this->payload))
		{
			$this->payload = json_encode($this->payload, JSON_UNESCAPED_UNICODE);
		}

		return parent::save($validation);
	}
}