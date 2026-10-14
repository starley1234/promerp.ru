<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель остатка номенклатуры на складе (erp401_wms).
 *
 * Одна строка на пару (склад, объект реестра erp227).
 * Изменять остатки напрямую через модель запрещено — только через
 * Service_Erp401_Stock, который ведёт журнал движений и держит транзакцию.
 *
 * @package    Erp401
 * @category   Models
 */
class Model_Erp401_Balance extends ORM
{
    protected $_table_name = 'erp_401_balances';

    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'warehouse' => array(
            'model'       => 'Erp401_Warehouse',
            'foreign_key' => 'warehouse_id',
        ),
    );

    public function rules()
    {
        return array(
            'warehouse_id' => array(array('not_empty'), array('digit')),
            'item_id'      => array(array('not_empty'), array('digit')),
            'qty'          => array(array('numeric')),
            'reserved'     => array(array('numeric')),
            'min_qty'      => array(array('numeric')),
        );
    }

    public function labels()
    {
        return array(
            'warehouse_id' => 'Склад',
            'item_id'      => 'Номенклатура',
            'uom_code'     => 'Единица измерения',
            'qty'          => 'Остаток',
            'reserved'     => 'Зарезервировано',
            'min_qty'      => 'Минимум',
            'updated_at'   => 'Обновлён',
        );
    }

    /**
     * Свободный остаток (физический минус зарезервированный).
     *
     * @return float
     */
    public function available()
    {
        return round((float) $this->qty - (float) $this->reserved, 6);
    }

    /**
     * Признак падения остатка до минимума.
     *
     * @return bool
     */
    public function is_low()
    {
        return ((float) $this->min_qty > 0) AND ((float) $this->qty <= (float) $this->min_qty);
    }
}
