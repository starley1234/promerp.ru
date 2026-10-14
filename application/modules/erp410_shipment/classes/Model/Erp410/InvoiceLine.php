<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Позиция счёта (erp410_shipment).
 *
 * Строка счёта повторяет отгруженное или заказанное: наименование, количество,
 * цена и суммы. Ссылки на позицию заказа и отгрузки — мягкие: счёт должен
 * читаться и после того, как документ-основание убрали из работы.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_InvoiceLine extends ORM
{
    protected $_table_name = 'erp_410_invoice_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'invoice' => array('model' => 'Erp410_Invoice', 'foreign_key' => 'invoice_id'),
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
}
