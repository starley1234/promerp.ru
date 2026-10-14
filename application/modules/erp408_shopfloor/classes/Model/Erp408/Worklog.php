<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Отметка исполнителя в журнале цеха (erp408_shopfloor).
 *
 * Журнал пополняется только добавлением: старт, пауза, возобновление,
 * выработка, окончание, начало и конец простоя. По нему считается время
 * работы и простоя операции, и видно, кто и когда нажимал кнопку — в
 * терминальном режиме это основное доказательство факта.
 *
 * Записи не правятся и не удаляются: ошибочную отметку исправляют новой
 * (например, «выработка» с отрицательным количеством при разборе ошибки), а
 * служебные правки делает `Service_Erp408_Operations` под своим сервисом.
 *
 * @package    Erp408
 * @category   Models
 */
class Model_Erp408_Worklog extends ORM
{
    protected $_table_name = 'erp_408_worklogs';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'operation' => array('model' => 'Erp408_Operation', 'foreign_key' => 'operation_id'),
    );

    /** @var array Виды отметок (ключи совпадают с CHECK в БД) */
    public static $kinds = array(
        'start'          => 'Начало работы',
        'pause'          => 'Пауза',
        'resume'         => 'Возобновление',
        'output'         => 'Выработка',
        'finish'         => 'Окончание',
        'downtime_start' => 'Начало простоя',
        'downtime_end'   => 'Конец простоя',
    );

    public function rules()
    {
        return array(
            'kind' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$kinds))),
            ),
        );
    }

    /**
     * @return  string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : (string) $this->kind;
    }

    /**
     * Причина простоя словами (для отчётов и интерфейса).
     *
     * @return  string
     */
    public function reason_label()
    {
        if ( ! $this->downtime_reason)
        {
            return '';
        }

        $reasons = Model_Erp408_Operation::$downtime_reasons;

        return isset($reasons[$this->downtime_reason]) ? $reasons[$this->downtime_reason] : (string) $this->downtime_reason;
    }

    /**
     * Подпись отметки для списка: «Выработка: 12 шт (брак 1)».
     *
     * @return  string
     */
    public function label()
    {
        $parts = array($this->kind_label());

        if ($this->qty_ok !== NULL AND (float) $this->qty_ok != 0)
        {
            $parts[] = 'годных '.rtrim(rtrim(number_format((float) $this->qty_ok, 3, '.', ''), '0'), '.');
        }

        if ($this->qty_scrap !== NULL AND (float) $this->qty_scrap != 0)
        {
            $parts[] = 'брак '.rtrim(rtrim(number_format((float) $this->qty_scrap, 3, '.', ''), '0'), '.');
        }

        if ($this->downtime_reason)
        {
            $parts[] = $this->reason_label();
        }

        if ($this->seconds)
        {
            $parts[] = (int) $this->seconds.' с';
        }

        return implode(' · ', $parts);
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
