<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказ клиента (erp410_shipment).
 *
 * Заказ — это «что и когда покупатель просит». Путь заказа:
 *
 *   draft ──confirm──► confirmed ──reserve──► reserved ──pick──► picking
 *        ──ship──► shipped ──(счёт)──► closed
 *
 * Отменён может быть до отгрузки. Правки разрешены, пока заказ не начали
 * собирать: после резерва позиций менять количество нельзя — иначе склад
 * зарезервировал одно, а уехало другое.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_Order extends ORM
{
    protected $_table_name = 'erp_410_orders';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines'     => array('model' => 'Erp410_OrderLine', 'foreign_key' => 'order_id'),
        'shipments' => array('model' => 'Erp410_Shipment', 'foreign_key' => 'order_id'),
        'invoices'  => array('model' => 'Erp410_Invoice', 'foreign_key' => 'order_id'),
    );

    /** @var array Состояния заказа: ключи совпадают с CHECK в БД */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'confirmed' => 'Подтверждён',
        'reserved'  => 'Зарезервирован',
        'picking'   => 'Сборка',
        'shipped'   => 'Отгружен',
        'closed'    => 'Закрыт',
        'cancelled' => 'Отменён',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'reserved'  => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'picking'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'shipped'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'closed'    => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    );

    /** @var array Источники заказа */
    public static $sources = array(
        'manual' => 'Вручную',
        'erp221' => 'Сделка CRM',
        'import' => 'Импорт',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'number' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
            ),
            'status' => array(
                array('not_empty'),
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
            'draft'        => 'neutral',
            'confirmed'    => 'info',
            'reserved'     => 'info',
            'picking'      => 'warn',
            'shipped'      => 'ok',
            'closed'       => 'ok',
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
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * Правки разрешены, пока заказ не начали собирать.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return in_array((string) $this->status, array('draft', 'confirmed'), TRUE);
    }

    /**
     * Заказ ждёт решения: черновик или подтверждённый, но не начатый.
     *
     * @return  bool
     */
    public function is_open()
    {
        return in_array((string) $this->status, array('draft', 'confirmed', 'reserved', 'picking'), TRUE);
    }

    /**
     * Работа по заказу закончена.
     *
     * @return  bool
     */
    public function is_closed()
    {
        return in_array((string) $this->status, array('shipped', 'closed', 'cancelled'), TRUE);
    }

    /**
     * Заказ можно отгружать: подтверждён или уже собирается.
     *
     * @return  bool
     */
    public function can_ship()
    {
        return in_array((string) $this->status, array('confirmed', 'reserved', 'picking'), TRUE);
    }

    /**
     * Сколько дней осталось до отгрузки (отрицательное — срок прошёл).
     *
     * @return  int|null
     */
    public function days_left()
    {
        if ( ! $this->ship_date)
        {
            return NULL;
        }

        return (int) floor((strtotime((string) $this->ship_date.' 00:00:00') - strtotime(date('Y-m-d').' 00:00:00')) / 86400);
    }

    /**
     * Срок отгрузки прошёл, а заказ ещё не уехал.
     *
     * @return  bool
     */
    public function is_overdue()
    {
        $days = $this->days_left();

        return ($days !== NULL AND $days < 0 AND $this->is_open());
    }

    /**
     * Новая запись получает uuid до вставки: он нужен тут же — в журнале
     * действий (erp119), событиях и ответе JSON API.
     *
     * @param   Validation|null $validation
     * @return  ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if ( ! $this->loaded() AND (string) $this->uuid === '')
        {
            $this->uuid = Erp::uuid();
        }

        return parent::save($validation);
    }
}
