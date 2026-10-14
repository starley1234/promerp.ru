<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель склада (erp401_wms).
 *
 * @package    Erp401
 * @category   Models
 */
class Model_Erp401_Warehouse extends ORM
{
    protected $_table_name = 'erp_401_warehouses';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Типы складов, допустимые в CHECK-ограничении БД */
    public static $types = array(
        'main'       => 'Основной',
        'production' => 'Производственный',
        'transit'    => 'Транзитный',
        'quarantine' => 'Карантин',
        'scrap'      => 'Списание',
    );

    public function rules()
    {
        return array(
            'code' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
                array(array($this, 'code_unique')),
            ),
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'type' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$types))),
            ),
        );
    }

    /**
     * Проверка уникальности кода склада.
     *
     * @param  string $value
     * @return bool
     */
    public function code_unique($value)
    {
        $query = ORM::factory('Erp401_Warehouse')->where('code', '=', $value);

        if ($this->loaded())
        {
            $query->and_where('id', '<>', $this->id);
        }

        return (bool) ($query->count_all() === 0);
    }

    public function labels()
    {
        return array(
            'code'        => 'Код',
            'name'        => 'Наименование',
            'type'        => 'Тип склада',
            'object_id'   => 'Объект реестра',
            'is_active'   => 'Активен',
            'address'     => 'Адрес',
            'description' => 'Описание',
            'created_at'  => 'Создан',
            'updated_at'  => 'Изменён',
        );
    }

    /**
     * Человекочитаемое название типа склада.
     *
     * @return string
     */
    public function type_label()
    {
        return isset(self::$types[$this->type]) ? self::$types[$this->type] : $this->type;
    }

    /**
     * Суммарная стоимость-величина остатков на складе (количество позиций и единиц).
     *
     * @return array {positions:int, qty:float}
     */
    public function totals()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'positions'),
                array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty')
            )
            ->from('erp_401_balances')
            ->where('warehouse_id', '=', $this->id)
            ->execute()
            ->current();

        return array(
            'positions' => (int) $row['positions'],
            'qty'       => (float) $row['qty'],
        );
    }
}
