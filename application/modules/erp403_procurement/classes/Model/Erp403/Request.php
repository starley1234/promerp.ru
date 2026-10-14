<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Заявка на закупку (erp403_procurement).
 *
 * Заявка — это «что нужно купить». Её заводят руками, из минимума склада
 * (`Service_Erp403_Requests::auto_from_low_stock()`) или из предложения
 * планирования (erp407 передаёт закупку этим путём). Дальше заявку
 * согласовывают и превращают в заказ поставщику.
 *
 * Состояния: `draft` — наполняется, `submitted` — ждёт согласования,
 * `approved` — согласована (можно заказывать), `rejected` — отклонена,
 * `ordered` — попала в заказ поставщику, `cancelled` — отменена.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_Request extends ORM
{
    protected $_table_name = 'erp_403_requests';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines' => array('model' => 'Erp403_RequestLine', 'foreign_key' => 'request_id'),
    );

    /** @var array Состояния заявки: ключи совпадают с CHECK в БД */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'submitted' => 'На согласовании',
        'approved'  => 'Согласована',
        'rejected'  => 'Отклонена',
        'ordered'   => 'В заказе',
        'cancelled' => 'Отменена',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'approved'  => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'rejected'  => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'ordered'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    );

    /** @var array Откуда взялась заявка */
    public static $sources = array(
        'manual' => 'Заведена вручную',
        'auto'   => 'Автоматически: минимум склада',
        'erp407' => 'Из расчёта планирования',
        'import' => 'Загружена из файла',
    );

    /** @var array Приоритеты заявки */
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
            'number' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
            ),
            'need_date' => array(
                array('date'),
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
     * Подпись источника заявки.
     *
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * Подпись приоритета.
     *
     * @return  string
     */
    public function priority_label()
    {
        $priority = (int) $this->priority;

        return isset(self::$priorities[$priority]) ? self::$priorities[$priority] : 'Обычный';
    }

    /**
     * Правки разрешены у черновика и заявки, ждущей согласования.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return in_array((string) $this->status, array('draft', 'submitted'), TRUE);
    }

    /**
     * Заявку можно согласовать (или отклонить).
     *
     * @return  bool
     */
    public function can_decide()
    {
        return in_array((string) $this->status, array('draft', 'submitted'), TRUE);
    }

    /**
     * Заявку можно превратить в заказ.
     *
     * @return  bool
     */
    public function can_order()
    {
        return ((string) $this->status === 'approved');
    }

    /**
     * Сколько дней осталось до срока потребности (отрицательное — срок прошёл).
     *
     * @return  int|null
     */
    public function days_left()
    {
        if ( ! $this->need_date)
        {
            return NULL;
        }

        return (int) floor((strtotime((string) $this->need_date.' 00:00:00') - strtotime(date('Y-m-d').' 00:00:00')) / 86400);
    }

    /**
     * Срок потребности уже прошёл.
     *
     * @return  bool
     */
    public function is_overdue()
    {
        $days = $this->days_left();

        return ($days !== NULL AND $days < 0);
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
