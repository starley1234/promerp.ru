<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель подписок пользователей на уведомления (Центр уведомлений ERP217).
 * 
 * @property int        $id             Идентификатор подписки
 * @property int        $user_id        ID пользователя из таблицы users
 * @property string     $event_type     Тип системного события (код)
 * @property string     $channel        Канал получения (email, telegram, max, push)
 * @property string     $allowed_roles  Список ролей, которым разрешена данная подписка
 * @property int        $is_enabled     Флаг активности подписки (0/1)
 * @property int        $created_by     ID создателя записи
 * @property string     $created_at     Дата создания
 * @property string     $updated_at     Дата последнего обновления
 * @property int        $is_deleted     Флаг мягкого удаления (0/1)
 * 
 * @property Model_User $user           Связанная модель пользователя
 * @property Model_User $creator        Связанная модель создателя записи
 * 
 * @package    ERP217
 * @category   Model
 * @author     Kohana Programmer
 */
class Model_Erp217_Subscription extends ORM {

	protected $_table_name = 'erp_217_subscriptions';

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
			'event_type' => array(
				array('not_empty'),
				array('max_length', array(':value', 100)),
			),
			'channel' => array(
				array('not_empty'),
				array('max_length', array(':value', 50)),
				array('in_array', array(':value', array('email', 'telegram', 'max', 'push'))),
			),
			'allowed_roles' => array(
				array('max_length', array(':value', 255)),
			),
			'is_enabled' => array(
				array('in_array', array(':value', array(0, 1))),
			),
			'created_by' => array(
				array('not_empty'),
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
			'user_id'       => 'Пользователь',
			'event_type'    => 'Тип события',
			'channel'       => 'Канал уведомления',
			'allowed_roles' => 'Разрешенные роли',
			'is_enabled'    => 'Активность',
			'created_by'    => 'Автор записи',
			'created_at'    => 'Дата создания',
			'updated_at'    => 'Дата обновления',
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
			'event_type' => array(
				array('trim'),
				array('strip_tags'),
			),
			'channel' => array(
				array('trim'),
				array('strtolower'),
			),
			'allowed_roles' => array(
				array('trim'),
				array('strip_tags'),
			),
		);
	}
}