<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Строка заказа поставщику (erp403_procurement).
 *
 * Хранит заказанное количество, согласованную цену и то, сколько уже принято
 * (`qty_received`). Разница — то, что ещё ждём; по ней строится график
 * поставок и приёмка.
 *
 * Состояния строки: `open` — ждём поставку, `partial` — принята частично,
 * `received` — принята полностью, `cancelled` — снята с заказа.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_OrderLine extends ORM
{
    protected $_table_name = 'erp_403_order_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'order' => array('model' => 'Erp403_Order', 'foreign_key' => 'order_id'),
    );

    /** @var array Состояния строки */
    public static $statuses = array(
        'open'      => 'Ждём поставку',
        'partial'   => 'Принята частично',
        'received'  => 'Принята',
        'cancelled' => 'Снята',
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
     * Сколько ещё не принято.
     *
     * @return  float
     */
    public function remaining()
    {
        return round(max(0, (float) $this->qty - (float) $this->qty_received), 6);
    }

    /**
     * Строка закрыта: принята полностью или снята.
     *
     * @return  bool
     */
    public function is_done()
    {
        return in_array((string) $this->status, array('received', 'cancelled'), TRUE);
    }

    /**
     * Сумма строки по согласованной цене.
     *
     * @return  float
     */
    public function amount()
    {
        return round((float) $this->qty * (float) $this->price, 2);
    }

    /**
     * Сумма того, что уже принято.
     *
     * @return  float
     */
    public function amount_received()
    {
        return round((float) $this->qty_received * (float) $this->price, 2);
    }

    /**
     * Сколько процентов строки принято (для прогресс-бара).
     *
     * @return  int
     */
    public function progress()
    {
        $qty = (float) $this->qty;

        if ($qty <= 0)
        {
            return 0;
        }

        return (int) min(100, round((float) $this->qty_received / $qty * 100));
    }
}
