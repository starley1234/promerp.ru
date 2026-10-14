<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Производственная программа (erp407_planning).
 *
 * Программа — это ответ на вопрос «что и когда выпустить на горизонте».
 * Строки (`erp_407_program_lines`) держат изделие и срок, а расчёт
 * потребности (`Service_Erp407_Mrp`) превращает программу в список дефицитов
 * с датами заказа.
 *
 * Состояния: `draft` — наполняется, `approved` — план утверждён и по нему
 * считают потребность, `closed` — период закрыт, правки запрещены.
 *
 * @package    Erp407
 * @category   Models
 */
class Model_Erp407_Program extends ORM
{
    protected $_table_name = 'erp_407_programs';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines'        => array('model' => 'Erp407_ProgramLine', 'foreign_key' => 'program_id'),
        'requirements' => array('model' => 'Erp407_Requirement', 'foreign_key' => 'program_id'),
    );

    /** @var array Состояния программы: ключи совпадают с CHECK в БД */
    public static $statuses = array(
        'draft'    => 'Черновик',
        'approved' => 'Утверждена',
        'closed'   => 'Закрыта',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'    => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'closed'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    );

    /** @var array Источник строк программы */
    public static $sources = array(
        'manual' => 'Заполнена вручную',
        'orders' => 'Собрана из заказов производства',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'title' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'period_from' => array(
                array('not_empty'),
            ),
            'period_to' => array(
                array('not_empty'),
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
     * Подпись источника строк.
     *
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * Сколько дней осталось до конца периода (отрицательное — период прошёл).
     *
     * @return  int|null
     */
    public function days_left()
    {
        if ( ! $this->period_to)
        {
            return NULL;
        }

        return (int) floor((strtotime((string) $this->period_to.' 00:00:00') - strtotime(date('Y-m-d').' 00:00:00')) / 86400);
    }

    /**
     * Правки запрещены у закрытой программы.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return ($this->status !== 'closed');
    }

    /**
     * Расчёт потребности возможен, когда в программе есть строки.
     *
     * @return  bool
     */
    public function can_calculate()
    {
        return ($this->status !== 'closed');
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
