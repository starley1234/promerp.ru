<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель единицы измерения.
 * Работает с таблицей erp_227_units.
 *
 * @package    Erp227
 * @category   Model
 * @author     Portal
 */
class Model_Erp227_Unit extends Model_Erp227_Base {

    protected $_table_name = 'erp_227_units';
    protected $_primary_key = 'unit';

    // ВРУЧНУЮ прописываем колонки. 
    // Теперь ORM вообще не будет опрашивать базу о структуре.
    protected $_table_columns = [
        'unit' => [
            'type' => 'string',
            'column_name' => 'unit',
            'column_default' => NULL,
            'data_type' => 'character varying',
            'is_nullable' => FALSE,
            'character_maximum_length' => 10,
        ],
        'name' => [
            'type' => 'string',
            'column_name' => 'name',
            'column_default' => NULL,
            'data_type' => 'character varying',
            'is_nullable' => FALSE,
            'character_maximum_length' => 100,
        ],
        'is_decimal' => [
            'type' => 'boolean',
            'column_name' => 'is_decimal',
            'column_default' => 'true',
            'data_type' => 'boolean',
            'is_nullable' => TRUE,
        ],
    ];

    // Связи
    protected $_has_many = array(
        'links' => array(
            'model'       => 'Erp227_Object_Link',
            'foreign_key' => 'unit',
        ),
    );
    
    public function values(array $values, ?array $expected = NULL)
    {
        // Если в данных есть первичный ключ и мы создаем новую запись
        if (isset($values[$this->_primary_key]) AND ! $this->loaded())
        {
            $this->__set($this->_primary_key, $values[$this->_primary_key]);
        }

        return parent::values($values, $expected);
    }
    
    public function create(?Validation $validation = NULL)
    {
        // Помечаем первичный ключ как "измененный", 
        // чтобы ORM включила его в INSERT запрос
        $this->_changed[$this->_primary_key] = $this->_primary_key;

        return parent::create($validation);
    }

    /**
     * Правила валидации
     * @return array
     */
    public function rules()
    {
        return array(
            
            'unit' => array(
                array('not_empty'),
                array('max_length', array(':value', 10)),
            ),
            
            
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 100)),
            ),
        );
    }

    /**
     * Фильтры данных
     * @return array
     */
    public function filters()
    {
        return array(
            'unit' => array(
                array('trim'),
            ),
            'name' => array(
                array('trim'),
            ),
            'is_decimal' => array(
                array(array($this, 'filter_boolean')),
            ),
        );
    }

    /**
     * Приведение к булеву типу для БД PostgreSQL
     * @param mixed $value
     * @return bool
     */
    public function filter_boolean($value)
    {
        // Исправлено: возвращаем реальный boolean тип. 
        // В PHP '0' (строка) при приведении к bool дает true, если не проверять явно.
        if ($value === '0' OR $value === 0 OR $value === 'false' OR $value === FALSE OR empty($value))
        {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Метки полей
     * @return array
     */
    public function labels()
    {
        return array(
            'unit'       => 'Код (ID)',
            'name'       => 'Наименование',
            'is_decimal' => 'Дробное значение',
        );
    }
}