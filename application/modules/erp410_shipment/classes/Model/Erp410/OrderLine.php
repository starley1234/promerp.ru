<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Позиция заказа клиента (erp410_shipment).
 *
 * В позиции живёт вся арифметика продажи: сколько просят (`qty`), сколько
 * зарезервировано на складе (`qty_reserved`) и сколько уже уехало
 * (`qty_shipped`). Остаток к отгрузке — `qty − qty_shipped`: он и попадает в
 * отгрузку при сборке.
 *
 * Скидка хранится у позиции процентом: цена прайса одна, а условия у клиента
 * свои, и менять её из-за каждой сделки нельзя.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_OrderLine extends ORM
{
    protected $_table_name = 'erp_410_order_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'order' => array('model' => 'Erp410_Order', 'foreign_key' => 'order_id'),
    );

    /** @var array Состояния позиции */
    public static $statuses = array(
        'open'      => 'Открыта',
        'reserved'  => 'Зарезервирована',
        'shipped'   => 'Отгружена',
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
     * Состояние документа словарём интерфейса (components/badge, components/kpi).
     *
     * @return  string  ok|warn|bad|info|neutral
     */
    public function status_state()
    {
        $states = array(
            'open'         => 'neutral',
            'reserved'     => 'info',
            'shipped'      => 'ok',
            'cancelled'    => 'bad',
        );

        return isset($states[$this->status]) ? $states[$this->status] : 'neutral';
    }

    /**
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * Сумма позиции: количество × цена × (1 − скидка).
     *
     * @return  float
     */
    public function amount()
    {
        return round((float) $this->qty * (float) $this->price * (1 - (float) $this->discount / 100), 2);
    }

    /**
     * Сколько ещё осталось отгрузить.
     *
     * @return  float
     */
    public function remaining()
    {
        return round((float) $this->qty - (float) $this->qty_shipped, 6);
    }

    /**
     * Доля отгрузки, 0–1.
     *
     * @return  float
     */
    public function progress()
    {
        $qty = (float) $this->qty;

        if ($qty <= 0)
        {
            return 1.0;
        }

        return min(1.0, round((float) $this->qty_shipped / $qty, 4));
    }

    /**
     * Позиция закрыта: отгружена полностью.
     *
     * @return  bool
     */
    public function is_complete()
    {
        return ((float) $this->qty_shipped >= (float) $this->qty);
    }

    /**
     * Позиция ждёт отгрузки.
     *
     * @return  bool
     */
    public function is_open()
    {
        return ((string) $this->status !== 'cancelled' AND $this->remaining() > 0);
    }

    /**
     * Цена позиции с учётом скидки (для документов и прайса).
     *
     * @return  float
     */
    public function net_price()
    {
        return round((float) $this->price * (1 - (float) $this->discount / 100), 6);
    }
}
