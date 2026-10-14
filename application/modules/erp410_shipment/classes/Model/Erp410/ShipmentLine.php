<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Позиция отгрузки (erp410_shipment).
 *
 * Строка отгрузки — снимок того, что уехало: наименование, количество, цена и
 * ссылка на позицию заказа (`order_line_id`). Снимок, а не ссылка, потому что
 * заказ потом меняется (скидки, переименования), а накладная должна читаться
 * так же, как в день отгрузки.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_ShipmentLine extends ORM
{
    protected $_table_name = 'erp_410_shipment_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'shipment' => array('model' => 'Erp410_Shipment', 'foreign_key' => 'shipment_id'),
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'qty' => array(
                array('numeric'),
            ),
        );
    }

    /**
     * Сумма строки.
     *
     * @return  float
     */
    public function amount()
    {
        return round((float) $this->qty * (float) $this->price, 2);
    }

    /**
     * Есть ли позиция номенклатуры: без неё склад не примет расход.
     *
     * @return  bool
     */
    public function has_item()
    {
        return ! empty($this->item_id);
    }
}
