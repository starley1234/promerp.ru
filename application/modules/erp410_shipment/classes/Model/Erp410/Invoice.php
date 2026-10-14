<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Счёт на оплату (erp410_shipment).
 *
 * Счёт — это основание для денег: он выставляется по отгрузке (или по заказу
 * целиком) и живёт своей жизнью до оплаты. Состояния: `draft` — черновик,
 * `issued` — выставлен клиенту, `paid` — оплачен, `cancelled` — отменён.
 *
 * Оплата отмечается суммой (`paid_amount`), а не флагом: клиент платит
 * частями, и «оплачен» — это когда сумма дошла до итога счёта.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_Invoice extends ORM
{
    protected $_table_name = 'erp_410_invoices';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines' => array('model' => 'Erp410_InvoiceLine', 'foreign_key' => 'invoice_id'),
    );

    protected $_belongs_to = array(
        'order'    => array('model' => 'Erp410_Order', 'foreign_key' => 'order_id'),
        'shipment' => array('model' => 'Erp410_Shipment', 'foreign_key' => 'shipment_id'),
    );

    /** @var array Состояния счёта */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'issued'    => 'Выставлен',
        'paid'      => 'Оплачен',
        'cancelled' => 'Отменён',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'issued'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'paid'      => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
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
            'issued'       => 'warn',
            'paid'         => 'ok',
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
     * Сколько осталось получить.
     *
     * @return  float
     */
    public function outstanding()
    {
        return round((float) $this->amount - (float) $this->paid_amount, 2);
    }

    /**
     * Выставленный счёт можно оплачивать, черновик — нет.
     *
     * @return  bool
     */
    public function is_payable()
    {
        return ((string) $this->status === 'issued');
    }

    /**
     * Счёт просрочен: срок оплаты прошёл, а денег нет.
     *
     * @return  bool
     */
    public function is_overdue()
    {
        if ( ! $this->due_date OR (string) $this->status !== 'issued')
        {
            return FALSE;
        }

        return (strtotime((string) $this->due_date.' 23:59:59') < time());
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
