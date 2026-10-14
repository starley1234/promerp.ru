<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель подписок пользователей на конкретные сущности (Центр уведомлений ERP217).
 * 
 * @property int        $id           Идентификатор подписки
 * @property int        $user_id      ID пользователя из таблицы users
 * @property string     $entity_type  Тип сущности (например, deal, task, order)
 * @property int        $entity_id    ID конкретной записи сущности
 * @property string     $created_at   Дата создания
 * @property string     $updated_at   Дата последнего обновления
 * @property int        $created_by   ID создателя записи
 * @property int        $is_deleted   Флаг мягкого удаления (0/1)
 * 
 * @property Model_User $user         Связанная модель пользователя
 * @property Model_User $creator      Связанная модель создателя записи
 * 
 * @package    ERP217
 * @category   Model
 * @author     Kohana Programmer
 */
class Model_Erp217_EntitySubscription extends ORM {

	protected $_table_name = 'erp_217_entity_subscriptions';

	protected $_belongs_to = array(
		'user' => array(
			'model'       => 'User',
			'foreign_key' => 'user_id',
		),
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
			'user_id' => array(
				array('not_empty'),
				array('digit'),
			),
			'entity_type' => array(
				array('not_empty'),
				array('max_length', array(':value', 50)),
			),
			'entity_id' => array(
				array('not_empty'),
				array('digit'),
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
			'id'          => 'ID',
			'user_id'     => 'Пользователь',
			'entity_type' => 'Тип сущности',
			'entity_id'   => 'ID сущности',
			'created_at'  => 'Дата создания',
			'updated_at'  => 'Дата обновления',
			'created_by'  => 'Автор записи',
			'is_deleted'  => 'Удалено',
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
			'entity_type' => array(
				array('trim'),
				array('strtolower'),
			),
		);
	}
}