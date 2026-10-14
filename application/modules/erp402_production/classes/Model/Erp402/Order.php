<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель производственного заказа (erp402_production).
 *
 * Модель описывает данные и правила их проверки; переходы статусов, резерв
 * материалов и выпуск выполняет `Service_Erp402_Orders` — так одна и та же
 * логика работает и из формы, и из API, и из задачи по расписанию.
 *
 * @package    Erp402
 * @category   Models
 */
class Model_Erp402_Order extends ORM
{
    protected $_table_name = 'erp_402_orders';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Статусы заказа (ключи совпадают с CHECK в БД) */
    public static $statuses = array(
        'draft'       => 'Черновик',
        'planned'     => 'Запланирован',
        'released'    => 'Материалы зарезервированы',
        'in_progress' => 'В работе',
        'done'        => 'Завершён',
        'cancelled'   => 'Отменён',
    );

    /** @var array Цветовая маркировка статуса для интерфейса */
    public static $status_classes = array(
        'draft'       => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'planned'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'released'    => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'in_progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'done'        => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled'   => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    );

    /** @var array Приоритеты: 1 — самый высокий */
    public static $priorities = array(
        1 => 'Аварийный',
        2 => 'Очень высокий',
        3 => 'Высокий',
        4 => 'Выше обычного',
        5 => 'Обычный',
        6 => 'Ниже обычного',
        7 => 'Низкий',
        8 => 'Очень низкий',
        9 => 'Фоновый',
    );

    public function rules()
    {
        return array(
            'title' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'number' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
                array(array($this, 'number_unique')),
            ),
            'qty' => array(
                array('not_empty'),
                array('numeric'),
                array(array($this, 'qty_positive')),
            ),
            'status' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$statuses))),
            ),
            'priority' => array(
                array('not_empty'),
                array('digit'),
                array('range', array(':value', 1, 9)),
            ),
        );
    }

    /**
     * Количество должно быть больше нуля.
     *
     * @param   mixed   $value
     * @return  bool
     */
    public function qty_positive($value)
    {
        return (float) $value > 0;
    }

    /**
     * Номер заказа уникален.
     *
     * @param   string  $value
     * @return  bool
     */
    public function number_unique($value)
    {
        $query = ORM::factory('Erp402_Order')->where('number', '=', $value);

        if ($this->loaded())
        {
            $query->and_where('id', '<>', $this->id);
        }

        return (bool) ($query->count_all() === 0);
    }

    public function labels()
    {
        return array(
            'number'              => 'Номер заказа',
            'title'               => 'Изделие',
            'tech_card_id'        => 'Технологическая карта',
            'item_id'             => 'Позиция номенклатуры',
            'uom_code'            => 'Единица измерения',
            'qty'                 => 'План выпуска',
            'qty_done'            => 'Выпущено',
            'status'              => 'Статус',
            'priority'            => 'Приоритет',
            'warehouse_id'        => 'Склад материалов',
            'output_warehouse_id' => 'Склад готовой продукции',
            'customer_id'         => 'Заказчик',
            'contract_id'         => 'Договор',
            'target_date'         => 'Срок изготовления',
            'planned_materials_cost' => 'Материалы, план',
            'planned_labor_cost'  => 'Труд, план',
            'note'                => 'Примечание',
        );
    }

    /**
     * Состав заказа (потребность в материалах).
     *
     * @return ORM
     */
    public function items()
    {
        return ORM::factory('Erp402_OrderItem')
            ->where('order_id', '=', (int) $this->id)
            ->order_by('line_no', 'ASC')
            ->order_by('id', 'ASC');
    }

    /**
     * Выполнение заказа в процентах.
     *
     * @return  float
     */
    public function progress()
    {
        $qty = (float) $this->qty;

        if ($qty <= 0)
        {
            return 0.0;
        }

        return round(min(100, (float) $this->qty_done / $qty * 100), 1);
    }

    /**
     * Можно ли ещё править заказ: завершённый и отменённый — только чтение.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return in_array($this->status, array('draft', 'planned', 'released'), TRUE);
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
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : self::$status_classes['draft'];
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
