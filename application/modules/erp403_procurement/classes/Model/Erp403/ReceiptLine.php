<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Строка приёмки (erp403_procurement).
 *
 * Строка знает, из какой строки заказа пришла (`order_line_id`) — это
 * позволяет обновлять принятое количество в заказе. Если поставщик привёз
 * больше/меньше, в заказе остаётся план, а разница видна как «принято».
 *
 * `quality_status` — зацепка входного контроля (erp405_quality): пока контроль
 * не проведён, статус `pending`; провал контроля (`failed`) блокирует приход
 * на склад при проведении приёмки.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_ReceiptLine extends ORM
{
    protected $_table_name = 'erp_403_receipt_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'receipt'    => array('model' => 'Erp403_Receipt', 'foreign_key' => 'receipt_id'),
        'order_line' => array('model' => 'Erp403_OrderLine', 'foreign_key' => 'order_line_id'),
    );

    /** @var array Состояния входного контроля */
    public static $quality_statuses = array(
        'pending' => 'Ждёт контроля',
        'passed'  => 'Контроль пройден',
        'failed'  => 'Брак — приход запрещён',
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
     * Подпись состояния контроля.
     *
     * @return  string
     */
    public function quality_label()
    {
        return isset(self::$quality_statuses[$this->quality_status]) ? self::$quality_statuses[$this->quality_status] : (string) $this->quality_status;
    }

    /**
     * Позиция заблокирована контролем качества.
     *
     * @return  bool
     */
    public function is_blocked()
    {
        return ((string) $this->quality_status === 'failed');
    }

    /**
     * Контроль ещё не проведён.
     *
     * @return  bool
     */
    public function is_pending()
    {
        return ((string) $this->quality_status === 'pending');
    }

    /**
     * Сумма строки приёмки.
     *
     * @return  float
     */
    public function amount()
    {
        return round((float) $this->qty * (float) $this->price, 2);
    }
}
