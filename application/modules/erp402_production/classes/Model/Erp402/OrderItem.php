<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель строки состава производственного заказа (erp402_production).
 *
 * Норма расхода хранится на единицу изделия (`qty_per_unit`), потребность на
 * весь заказ — в `qty_total`. Так смена плана выпуска не «съедает» исходные
 * нормы техкарты: пересчёт делает сервис при планировании.
 *
 * @package    Erp402
 * @category   Models
 */
class Model_Erp402_OrderItem extends ORM
{
    protected $_table_name = 'erp_402_order_items';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Статусы строки (ключи совпадают с CHECK в БД) */
    public static $statuses = array(
        'planned'  => 'Запланирован',
        'reserved' => 'Зарезервирован',
        'issued'   => 'Списан в производство',
        'short'    => 'Не хватает на складе',
    );

    /** @var array Цветовая маркировка статуса */
    public static $status_classes = array(
        'planned'  => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'reserved' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'issued'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'short'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    );

    public function rules()
    {
        return array(
            'order_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'qty_per_unit' => array(
                array('not_empty'),
                array('numeric'),
            ),
            'line_no' => array(
                array('not_empty'),
                array('digit'),
            ),
        );
    }

    public function labels()
    {
        return array(
            'line_no'      => '№ строки',
            'item_id'      => 'Номенклатура',
            'name'         => 'Материал',
            'uom_code'     => 'Ед. изм.',
            'qty_per_unit' => 'Норма на изделие',
            'qty_total'    => 'Потребность',
            'qty_issued'   => 'Списано',
            'warehouse_id' => 'Склад',
            'status'       => 'Статус',
        );
    }

    /**
     * Заказ строки.
     *
     * @return Model_Erp402_Order|null
     */
    public function order()
    {
        $order = ORM::factory('Erp402_Order', (int) $this->order_id);

        return $order->loaded() ? $order : NULL;
    }

    /**
     * Осталось списать по строке.
     *
     * @return  float
     */
    public function remaining()
    {
        return round(max(0, (float) $this->qty_total - (float) $this->qty_issued), 6);
    }

    /**
     * Метка статуса для интерфейса.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }

    /**
     * CSS-класс бейджа статуса.
     *
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : self::$status_classes['planned'];
    }
}
