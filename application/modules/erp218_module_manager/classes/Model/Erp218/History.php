<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель истории изменений версий модулей (Changelog).
 * Предназначена для фиксации жизненного цикла программных компонентов ERP.
 *
 * @property int        $id              Идентификатор записи
 * @property int        $module_id       ID модуля (связь с Model_Erp218_Module)
 * @property string     $version         Версия в формате SemVer (например, 1.2.0)
 * @property string     $change_log      Описание внесенных изменений
 * @property string     $release_date    Дата официального релиза
 * @property int        $created_by      ID пользователя, создавшего запись
 * @property string     $created_at      Дата и время создания
 * @property string     $updated_at      Дата и время последнего обновления
 * @property int        $is_deleted      Флаг мягкого удаления (0/1)
 *
 * @property Model_Erp218_Module $module Связанный модуль
 */
class Model_Erp218_History extends ORM {

	protected $_table_name = 'erp_218_version_history';

	protected $_belongs_to = [
		'module' => [
			'model'       => 'Erp218_Module',
			'foreign_key' => 'module_id',
		],
	];

	protected $_created_column = [
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	];

	protected $_updated_column = [
		'column' => 'updated_at',
		'format' => 'Y-m-d H:i:s',
	];

	/**
	 * Правила валидации данных
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'module_id' => [
				['not_empty'],
				['digit'],
			],
			'version' => [
				['not_empty'],
				['max_length', [':value', 50]],
			],
			'change_log' => [
				['not_empty'],
			],
			'release_date' => [
				['date'],
			],
			'created_by' => [
				['not_empty'],
				['digit'],
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
			'id'           => 'ID записи',
			'module_id'    => 'Модуль',
			'version'      => 'Версия (SemVer)',
			'change_log'   => 'Список изменений',
			'release_date' => 'Дата релиза',
			'created_by'   => 'Автор записи',
			'created_at'   => 'Дата создания',
			'updated_at'   => 'Дата обновления',
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
			'version' => [
				['trim'],
				['strip_tags'],
			],
			'change_log' => [
				['trim'],
			],
		];
	}
}