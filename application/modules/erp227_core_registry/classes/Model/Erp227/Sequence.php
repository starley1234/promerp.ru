<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для управления последовательностями (нумераторами) типов объектов.
 * Позволяет генерировать уникальные коды с префиксами для различных сущностей.
 *
 * @package    Erp227
 * @category   Model
 * @author     Kohana Programmer
 * 
 * @property int    $type_id     Идентификатор типа объекта (PK, FK)
 * @property string $prefix      Префикс для генерации кода (например, 'INV-')
 * @property int    $current_val Текущее значение счетчика
 * @property int    $padding     Количество символов для дополнения нулями (например, 6 -> 000001)
 * 
 * @property Model_Erp227_Type $type Тип объекта, к которому относится последовательность
 */
class Model_Erp227_Sequence extends Model_Erp227_Base {

	protected $_table_name = 'erp_227_sequences';

	protected $_primary_key = 'type_id';

	protected $_belongs_to = array(
		'type' => array(
			'model'       => 'Erp227_Type',
			'foreign_key' => 'type_id',
		),
	);

	/**
	 * Правила валидации
	 * 
	 * @return array
	 */
	public function rules()
	{
		return array(
			'type_id' => array(
				array('not_empty'),
				array('numeric'),
			),
			'prefix' => array(
				array('not_empty'),
				array('max_length', array(':value', 10)),
			),
			'current_val' => array(
				array('digit'),
			),
			'padding' => array(
				array('digit'),
				array('range', array(':value', 0, 20)),
			),
		);
	}

	/**
	 * Метки полей
	 * 
	 * @return array
	 */
	public function labels()
	{
		return array(
			'type_id'     => 'ID типа',
			'prefix'      => 'Префикс',
			'current_val' => 'Текущее значение',
			'padding'     => 'Длина номера (padding)',
		);
	}

	/**
	 * Генерирует следующий код для указанного типа объекта.
	 * Инкрементирует счетчик в БД атомарно.
	 * 
	 * @param  int|Model_Erp227_Type $type
	 * @return string
	 */
	public static function next_code($type)
	{
		$type_id = ($type instanceof Model_Erp227_Type) ? $type->id : (int) $type;
		$db = Database::instance();

		// 1. Пытаемся получить и заблокировать строку
		$result = DB::query(Database::SELECT, 'SELECT * FROM erp_227_sequences WHERE type_id = :type_id FOR UPDATE')
			->parameters(array(':type_id' => $type_id))
			->execute()
			->current();

		if ( ! $result)
		{
			// 2. Если настройки нет, создаем её с уникальным префиксом для этого типа
			// Используем OBJ-{ID}- для исключения дублей между типами
			$prefix = 'OBJ-' . $type_id . '-';
			$padding = 6;
			$initial_val = 1;

			try {
				DB::insert('erp_227_sequences', array('type_id', 'prefix', 'current_val', 'padding'))
					->values(array($type_id, $prefix, $initial_val, $padding))
					->execute();
				
				return $prefix . str_pad($initial_val, $padding, '0', STR_PAD_LEFT);
			} catch (Exception $e) {
				// Если кто-то успел вставить раньше, пробуем прочитать снова
				return self::next_code($type_id);
			}
		}

		// 3. Инкрементируем существующий счетчик
		$new_val = (int) $result['current_val'] + 1;

		DB::update('erp_227_sequences')
			->set(array('current_val' => $new_val))
			->where('type_id', '=', $type_id)
			->execute();

		return $result['prefix'] . str_pad($new_val, (int) $result['padding'], '0', STR_PAD_LEFT);
	}
}