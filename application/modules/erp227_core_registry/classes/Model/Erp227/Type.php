<?php defined('SYSPATH') or die('No direct script access.'); 

/**
 * Модель для типов объектов (дискриминаторов) универсального реестра.
 * Определяет структуру атрибутов и конфигурацию интерфейса для различных сущностей ERP.
 *
 * @package    Erp227
 * @category   Model
 * @author     Kohana Programmer
 * 
 * @property int    $id                Идентификатор типа
 * @property string $code              Уникальный строковый код типа (например, 'part', 'employee')
 * @property string $name              Человекопонятное название типа
 * @property string $schema            JSONB схема валидации и описания дополнительных атрибутов
 * @property string $ui_config         JSONB конфигурация отображения в интерфейсе (поля, фильтры)
 * @property string $icon              Название иконки (FontAwesome и т.д.)
 * @property string $created_at        Дата и время создания записи
 * 
 * @property ORM|Model_Erp227_Object[] $objects Коллекция объектов данного типа
 */
class Model_Erp227_Type extends Model_Erp227_Base {

	protected $_table_name = 'erp_227_object_types';

	protected $_has_many = array(
		'objects' => array(
			'model'       => 'Erp227_Object',
			'foreign_key' => 'type_id',
		),
	);

	/**
	 * Правила валидации для полей модели
	 * 
	 * @return array
	 */
	public function rules()
	{
		return array(
            
            'code' => array(
				array('not_empty'),
				array('max_length', array(':value', 50)),
				array(array($this, 'unique'), array('code', ':value')),
			),
			'name' => array(
				array('not_empty'),
				array('max_length', array(':value', 255)),
			),
			'icon' => array(
				 array('max_length', array(':value', 50)),
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
			'id'               => 'ID',
			'code'             => 'Код типа',
			'name'             => 'Наименование',
			'schema'           => 'Схема атрибутов (JSON)',
			'ui_config'        => 'Конфигурация UI (JSON)',
			'icon'             => 'Иконка',
			'created_at'       => 'Дата создания',
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
			'code' => array(
				array('trim'),
				array('strtolower'),
			),
			'name' => array(
				array('trim'),
				array('strip_tags'),
			),
			'icon' => array(
				array('trim'),
			),
			'schema' => array(
				array(array($this, 'default_json')),
			),
			'ui_config' => array(
				array(array($this, 'default_json')),
			),
		);
	}

	/**
	 * Обеспечивает валидный пустой JSON объект для PostgreSQL json/jsonb полей
	 * 
	 * @param  mixed $value
	 * @return string
	 */
	public function default_json($value)
	{
		if (empty($value))
		{
			return '{}';
		}

		return $value;
	}

	/**
	 * Автоматическое заполнение даты создания
	 * 
	 * @var array
	 */
	protected $_created_column = array(
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	);
}