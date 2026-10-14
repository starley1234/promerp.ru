<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель типов событий (Центр уведомлений ERP217).
 * 
 * @property int        $id             Идентификатор типа события
 * @property string     $alias          Уникальный строковый код события
 * @property string     $name           Название события
 * @property string     $description    Описание события
 * @property string     $allowed_roles  Список ролей через запятую, которым разрешена подписка
 * @property string     $created_at     Дата создания
 * @property string     $updated_at     Дата последнего обновления
 * @property int        $created_by     ID создателя записи
 * @property int        $is_deleted     Флаг мягкого удаления (0/1)
 * 
 * @property Model_User $creator        Связанная модель создателя записи
 * 
 * @package    ERP217
 * @category   Model
 * @author     Kohana Programmer
 */
class Model_Erp217_EventType extends ORM {

	protected $_table_name = 'erp_217_event_types';

	protected $_belongs_to = array(
		'creator' => array(
			'model'       => 'User',
			'foreign_key' => 'created_by',
		),
	);

	protected $_created_column = array(
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	);

	protected $_updated_column = array(
		'column' => 'updated_at',
		'format' => 'Y-m-d H:i:s',
	);

	/**
	 * Правила валидации данных
	 * 
	 * @return array
	 */
	public function rules()
	{
		return array(
			'alias' => array(
				array('not_empty'),
				array('max_length', array(':value', 100)),
			),
			'name' => array(
				array('not_empty'),
				array('max_length', array(':value', 255)),
			),
			'allowed_roles' => array(
				array('max_length', array(':value', 255)),
			),
			'created_by' => array(
				array('digit'),
			),
			'is_deleted' => array(
				array('in_array', array(':value', array(0, 1))),
			),
		);
	}

	/**
	 * Человекопонятные метки полей
	 * 
	 * @return array
	 */
	public function labels()
	{
		return array(
			'id'            => 'ID',
			'alias'         => 'Алиас',
			'name'          => 'Название',
			'description'   => 'Описание',
			'allowed_roles' => 'Разрешенные роли',
			'created_at'    => 'Дата создания',
			'updated_at'    => 'Дата обновления',
			'created_by'    => 'Автор записи',
			'is_deleted'    => 'Удалено',
		);
	}

	/**
	 * Фильтры очистки данных
	 * 
	 * @return array
	 */
	public function filters()
	{
		return array(
			'alias' => array(
				array('trim'),
			),
			'name' => array(
				array('trim'),
			),
			'description' => array(
				array('trim'),
			),
			'allowed_roles' => array(
				array('trim'),
			),
		);
	}
}