<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для управления универсальными связями объектов (BOM, локации, назначения).
 * Обеспечивает связь между сущностями реестра с учетом количественных показателей.
 *
 * @property int        $id           Идентификатор связи
 * @property int        $source_id    ID исходного объекта (родитель/контейнер)
 * @property int        $target_id    ID целевого объекта (компонент/исполнитель)
 * @property string     $link_type    Тип связи (например: bom, location, assignment)
 * @property float      $quantity     Количество/Норма
 * @property float      $qty          Алиас для quantity
 * @property string     $unit         Единица измерения (ссылка на erp_227_units)
 * @property int        $sort_order   Порядок сортировки
 * @property string     $data         Дополнительные данные связи (JSONB)
 * @property string     $created_at   Дата создания
 * @property int        $created_by   ID пользователя-создателя
 * @property bool       $is_deleted   Флаг мягкого удаления
 *
 * @property Model_Erp227_Object $source    Исходный объект
 * @property Model_Erp227_Object $target    Целевой объект
 * @property Model_Erp227_Unit   $unit_ref  Объект единицы измерения
 */
class Model_Erp227_Link extends Model_Erp227_Base {

	protected $_table_name = 'erp_227_object_links';

	protected $_belongs_to = array(
		'source' => array(
			'model'       => 'Erp227_Object',
			'foreign_key' => 'source_id',
		),
		'target' => array(
			'model'       => 'Erp227_Object',
			'foreign_key' => 'target_id',
		),
		'unit_ref' => array(
			'model'       => 'Erp227_Unit',
			'foreign_key' => 'unit',
			'primary_key' => 'unit',
		),
	);

	protected $_created_column = array(
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	);

	/**
	 * Переопределяем метод get для поддержки алиаса qty
	 */
	public function get($column)
	{
		if ($column === 'qty')
		{
			return parent::get('quantity');
		}

		return parent::get($column);
	}

	/**
	 * Переопределяем метод set для корректного приведения типов PostgreSQL boolean
	 * и поддержки алиаса qty
	 */
	public function set($column, $value)
	{
		if ($column === 'qty')
		{
			$column = 'quantity';
		}

		if ($column === 'is_deleted')
		{
			$value = (bool) $value;
		}

		return parent::set($column, $value);
	}

	/**
	 * Scope: Исключить удаленные записи
	 * 
	 * @return $this
	 */
	public function active()
	{
		return $this->where($this->_object_name . '.is_deleted', '=', FALSE);
	}

	/**
	 * Scope: Связи конкретного объекта (как входящие, так и исходящие)
	 * 
	 * @param int $object_id
	 * @return $this
	 */
	public function for_object($object_id)
	{
		return $this->where_open()
				->where('source_id', '=', $object_id)
				->or_where('target_id', '=', $object_id)
			->where_close();
	}

	/**
	 * Правила валидации данных
	 *
	 * @return array
	 */
	public function rules()
	{
		return array(
			'source_id' => array(
				array('not_empty'),
				array('digit'),
			),
			'target_id' => array(
				array('not_empty'),
				array('digit'),
				array(array($this, 'is_not_self_link'), array(':validation', ':field')),
			),
			'link_type' => array(
				array('not_empty'),
				array('max_length', array(':value', 30)),
			),
			'quantity' => array(
				array('numeric'),
				array(array($this, 'validate_unit_decimal'), array(':validation', ':field')),
			),
			'unit' => array(
				array('max_length', array(':value', 10)),
			),
			'sort_order' => array(
				array('digit'),
			),
		);
	}

	/**
	 * Метки полей (русский язык)
	 *
	 * @return array
	 */
	public function labels()
	{
		return array(
			'source_id'  => 'Исходный объект',
			'target_id'  => 'Целевой объект',
			'link_type'  => 'Тип связи',
			'quantity'   => 'Количество',
			'unit'       => 'Ед. изм.',
			'sort_order' => 'Порядок',
			'data'       => 'Доп. данные',
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
			'link_type' => array(
				array('trim'),
				array('strtolower'),
			),
			'unit' => array(
				array('trim'),
				array(array($this, 'filter_empty_unit')),
			),
		);
	}

	/**
	 * Преобразование пустой строки в NULL для корректной работы Foreign Key в БД
	 */
	public function filter_empty_unit($value)
	{
		return (string) $value === '' ? NULL : $value;
	}

	/**
	 * Валидация: объект не может быть связан сам с собой
	 */
	public function is_not_self_link(Validation $validation, $field)
	{
		if ($this->source_id == $this->target_id)
		{
			$validation->error($field, 'self_link_not_allowed');
		}
	}

	/**
	 * Валидация: проверка дробности количества в зависимости от единицы измерения
	 */
	public function validate_unit_decimal(Validation $validation, $field)
	{
		if ( ! empty($this->unit))
		{
			$unit = ORM::factory('Erp227_Unit', $this->unit);
			if ($unit->loaded() AND ! $unit->is_decimal)
			{
				if (floor($this->quantity) != $this->quantity)
				{
					$validation->error($field, 'decimal_not_allowed_for_unit');
				}
			}
		}
	}
}