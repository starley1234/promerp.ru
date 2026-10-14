<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заказ поставщику (erp403_procurement).
 *
 * Заказ — это «что и у кого куплено». Он собирается из согласованных заявок
 * (по одному поставщику) или заводится вручную, отправляется поставщику,
 * подтверждается им, принимается на склад частями и закрывается.
 *
 * Состояния: `draft` — черновик, `sent` — отправлен поставщику,
 * `confirmed` — подтверждён поставщиком, `partially_received` — принят
 * частично, `received` — принят полностью, `cancelled` — отменён.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_Order extends ORM
{
    protected $_table_name = 'erp_403_orders';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines' => array('model' => 'Erp403_OrderLine', 'foreign_key' => 'order_id'),
    );

    /** @var array Состояния заказа: ключи совпадают с CHECK в БД */
    public static $statuses = array(
        'draft'              => 'Черновик',
        'sent'               => 'Отправлен поставщику',
        'confirmed'          => 'Подтверждён',
        'partially_received' => 'Принят частично',
        'received'           => 'Принят полностью',
        'cancelled'          => 'Отменён',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'              => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'sent'               => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'confirmed'          => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'partially_received' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'received'           => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled'          => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
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
     * Подпись состояния.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * CSS-классы состояния для интерфейса.
     *
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * Правки разрешены, пока заказ не отправлен.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return ((string) $this->status === 'draft');
    }

    /**
     * Заказ принят полностью или отменён — работа закрыта.
     *
     * @return  bool
     */
    public function is_closed()
    {
        return in_array((string) $this->status, array('received', 'cancelled'), TRUE);
    }

    /**
     * Сколько дней осталось до срока поставки (отрицательное — срок прошёл).
     *
     * @return  int|null
     */
    public function days_left()
    {
        if ( ! $this->delivery_date)
        {
            return NULL;
        }

        return (int) floor((strtotime((string) $this->delivery_date.' 00:00:00') - strtotime(date('Y-m-d').' 00:00:00')) / 86400);
    }

    /**
     * Поставка просрочена (и заказ ещё не принят).
     *
     * @return  bool
     */
    public function is_overdue()
    {
        $days = $this->days_left();

        return ($days !== NULL AND $days < 0 AND (string) $this->status !== 'received' AND (string) $this->status !== 'cancelled');
    }

    /**
     * Новая запись получает uuid до вставки.
     *
     * Колонку `uuid` заполняет и база (`gen_random_uuid()`), но выданное
     * значение модель не знает до перечитывания строки — а оно нужно тут же:
     * в журнале действий (`erp119`), событиях и ответе JSON API. Пустой uuid в
     * этих местах означает «ссылку на объект потеряли»: запись журнала
     * оказывается ни к чему не привязанной. Поэтому идентификатор выдаётся
     * помощником `Erp::uuid()` до сохранения, а серверный default остаётся
     * подстраховкой для вставок в обход ORM.
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
