<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Отгрузка (erp410_shipment).
 *
 * Отгрузка — это «что, чем и откуда уехало». Собирается из строк заказа (то,
 * что ещё не уехало) или создаётся вручную. Пока отгрузка черновик, её строки
 * правятся; проведение (`posted`) списывает товар со склада через erp401 —
 * после этого отгрузка не меняется, а обратное движение оформляется возвратом.
 *
 * Состояния: `draft` — черновик, `ready` — собран и готов к отправке,
 * `shipped` — проведён (товар уехал), `cancelled` — отменён.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_Shipment extends ORM
{
    protected $_table_name = 'erp_410_shipments';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines' => array('model' => 'Erp410_ShipmentLine', 'foreign_key' => 'shipment_id'),
    );

    protected $_belongs_to = array(
        'order' => array('model' => 'Erp410_Order', 'foreign_key' => 'order_id'),
    );

    /** @var array Состояния отгрузки */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'ready'     => 'Готова к отправке',
        'shipped'   => 'Отгружена',
        'cancelled' => 'Отменена',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'ready'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'shipped'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
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
            'ready'        => 'info',
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
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * Отгрузка проведена: товар уехал.
     *
     * @return  bool
     */
    public function is_posted()
    {
        return ((string) $this->status === 'shipped');
    }

    /**
     * Отгрузку можно править, пока она не проведена.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return in_array((string) $this->status, array('draft', 'ready'), TRUE);
    }

    /**
     * Строку можно собрать: черновик или готовый к отправке черновик.
     *
     * @return  bool
     */
    public function can_build()
    {
        return $this->is_editable();
    }

    /**
     * Новая запись получает uuid до вставки (см. Model_Erp410_Order::save()).
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
