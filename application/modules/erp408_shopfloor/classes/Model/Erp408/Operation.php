<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Операция производственного заказа в цехе (erp408_shopfloor).
 *
 * Одна строка — одно рабочее задание: что делать (`name`), на каком центре
 * (`work_center_id`), в какую смену (`shift_id`) и какой день (`planned_day`),
 * сколько запланировано (`qty_planned`) и сколько сделано (`qty_ok`).
 *
 * Модель описывает данные и подписи; переходы («начать», «приостановить»,
 * «завершить»), отметки в журнале и события выполняет
 * `Service_Erp408_Operations` — одна и та же логика работает и из карточки, и
 * из терминала, и из API, и из задачи по расписанию.
 *
 * @package    Erp408
 * @category   Models
 */
class Model_Erp408_Operation extends ORM
{
    protected $_table_name = 'erp_408_operations';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'work_center' => array('model' => 'Erp408_WorkCenter', 'foreign_key' => 'work_center_id'),
        'shift'       => array('model' => 'Erp408_Shift', 'foreign_key' => 'shift_id'),
    );

    protected $_has_many = array(
        'worklogs' => array('model' => 'Erp408_Worklog', 'foreign_key' => 'operation_id'),
    );

    /** @var array Статусы операции (ключи совпадают с CHECK в БД) */
    public static $statuses = array(
        'planned'     => 'К выполнению',
        'in_progress' => 'В работе',
        'paused'      => 'Приостановлена',
        'done'        => 'Выполнена',
        'cancelled'   => 'Отменена',
    );

    /** @var array Цветовая маркировка статуса для интерфейса */
    public static $status_classes = array(
        'planned'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'in_progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'paused'      => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        'done'        => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled'   => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    );

    /** @var array Причины простоев: код => подпись */
    public static $downtime_reasons = array(
        'no_material' => 'Нет материала',
        'equipment'   => 'Поломка оборудования',
        'setup'       => 'Переналадка',
        'no_tool'     => 'Нет инструмента',
        'no_staff'    => 'Нет исполнителя',
        'quality'     => 'Разбор брака',
        'power'       => 'Нет энергии (свет, воздух)',
        'other'       => 'Прочее',
    );

    /** @var array Откуда взялась операция */
    public static $sources = array(
        'tech_card' => 'Из техкарты',
        'manual'    => 'Добавлена вручную',
        'order'     => 'Из заказа (без техкарты)',
    );

    public function rules()
    {
        return array(
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'op_no' => array(
                array('not_empty'),
                array('digit'),
            ),
            'qty_planned' => array(
                array('numeric'),
            ),
            'status' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$statuses))),
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
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : self::$status_classes['planned'];
    }

    /**
     * Операция ещё в работе (по ней есть что делать в терминале).
     *
     * @return  bool
     */
    public function is_open()
    {
        return in_array($this->status, array('planned', 'in_progress', 'paused'), TRUE);
    }

    /**
     * Операция назначена на рабочий центр и день.
     *
     * @return  bool
     */
    public function is_assigned()
    {
        return (bool) $this->work_center_id AND (bool) $this->planned_day;
    }

    /**
     * Сколько осталось сделать.
     *
     * @return  float
     */
    public function remaining()
    {
        return max(0, (float) $this->qty_planned - (float) $this->qty_ok);
    }

    /**
     * Выполнение в процентах (для прогресс-бара).
     *
     * @return  int
     */
    public function progress()
    {
        $planned = (float) $this->qty_planned;

        if ($planned <= 0)
        {
            return $this->status === 'done' ? 100 : 0;
        }

        return (int) min(100, round((float) $this->qty_ok / $planned * 100));
    }

    /**
     * Длительность по отметкам: сколько секунд операция была в работе.
     *
     * @return  int
     */
    public function worked_seconds()
    {
        return (int) $this->worked_sec;
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
