<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Предложение плана (erp407_planning).
 *
 * Расчёт говорит «не хватает 40 штук к 20-му числу»; предложение — это
 * решение, что с этим делать: закупить (`purchase`) или выпустить самому
 * (`produce`). Дальше у предложения своя жизнь:
 *
 *   new       — предложено, но никто не решил;
 *   approved  — плановик утвердил (правки закрыты);
 *   exported  — передано в исполнение: заявка закупки (erp403) или заказ
 *               производства (erp402); `target_ref` хранит ссылку на приёмник;
 *   done      — исполнено, потребность закрыта;
 *   cancelled — отклонено.
 *
 * @package    Erp407
 * @category   Models
 */
class Model_Erp407_Proposal extends ORM
{
    protected $_table_name = 'erp_407_proposals';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'requirement' => array('model' => 'Erp407_Requirement', 'foreign_key' => 'requirement_id'),
        'program'     => array('model' => 'Erp407_Program', 'foreign_key' => 'program_id'),
    );

    /** @var array Состояния предложения (ключи совпадают с CHECK в БД) */
    public static $statuses = array(
        'new'       => 'Предложено',
        'approved'  => 'Утверждено',
        'exported'  => 'Передано в исполнение',
        'cancelled' => 'Отклонено',
        'done'      => 'Исполнено',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'new'       => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'approved'  => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'exported'  => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'done'      => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    );

    /** @var array Виды предложений */
    public static $kinds = array(
        'purchase' => 'Закупить',
        'produce'  => 'Выпустить',
    );

    /** @var array Приоритеты: чем меньше число, тем раньше заказ */
    public static $priorities = array(
        1 => 'Срочно',
        2 => 'Высокий',
        3 => 'Обычный',
        4 => 'Низкий',
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
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : (string) $this->kind;
    }

    /**
     * @return  string
     */
    public function priority_label()
    {
        return isset(self::$priorities[(int) $this->priority]) ? self::$priorities[(int) $this->priority] : 'Обычный';
    }

    /**
     * Утверждённое и переданное в исполнение предложение больше не правится.
     *
     * @return  bool
     */
    public function is_locked()
    {
        return in_array($this->status, array('approved', 'exported', 'done'), TRUE);
    }

    /**
     * Сдвиг срока: сколько дней осталось до даты заказа.
     *
     * @return  int|null  отрицательное — уже поздно
     */
    public function days_to_order()
    {
        if ( ! $this->order_date)
        {
            return NULL;
        }

        return (int) floor((strtotime((string) $this->order_date.' 00:00:00') - strtotime(date('Y-m-d').' 00:00:00')) / 86400);
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
