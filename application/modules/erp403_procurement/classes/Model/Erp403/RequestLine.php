<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Строка заявки на закупку (erp403_procurement): что именно нужно купить.
 *
 * В строке хранится потребность и «прикидка» цены (`price` — оценка
 * закупщика или последняя известная цена поставщика), чтобы до согласования
 * видеть сумму. Фактическая цена появляется в заказе поставщику.
 *
 * Состояния строки: `open` — открыта, `ordered` — попала в заказ,
 * `received` — принята на склад, `cancelled` — отменена.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_RequestLine extends ORM
{
    protected $_table_name = 'erp_403_request_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'request' => array('model' => 'Erp403_Request', 'foreign_key' => 'request_id'),
    );

    /** @var array Состояния строки */
    public static $statuses = array(
        'open'      => 'Открыта',
        'ordered'   => 'В заказе',
        'received'  => 'Принята',
        'cancelled' => 'Отменена',
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
     * Подпись состояния строки.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * Сумма по строке (оценка).
     *
     * @return  float
     */
    public function amount()
    {
        return round((float) $this->qty * (float) $this->price, 2);
    }

    /**
     * Строка ещё открыта: её можно править и заказывать.
     *
     * @return  bool
     */
    public function is_open()
    {
        return ((string) $this->status === 'open');
    }

    /**
     * Срок потребности по строке (со своим сроком или со сроком заявки).
     *
     * @return  string|null
     */
    public function due_date()
    {
        if ($this->need_date)
        {
            return (string) $this->need_date;
        }

        return $this->request->loaded() ? (string) $this->request->need_date : NULL;
    }
}
