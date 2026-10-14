<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Шаблон смены (erp408_shopfloor).
 *
 * Смена — это «окно» работы, а не расписание конкретного дня: первая, вторая,
 * ночная. Конкретные рабочие дни живут в календаре (`erp_408_calendar`),
 * потому что в цехе бывает и сокращённый день, и ремонт, и сверхурочные.
 *
 * @package    Erp408
 * @category   Models
 */
class Model_Erp408_Shift extends ORM
{
    protected $_table_name = 'erp_408_shifts';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'operations' => array('model' => 'Erp408_Operation', 'foreign_key' => 'shift_id'),
        'calendar'   => array('model' => 'Erp408_Shift', 'foreign_key' => 'shift_id'),
    );

    public function rules()
    {
        return array(
            'code' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
            ),
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 128)),
            ),
            'start_time' => array(
                array('not_empty'),
            ),
            'end_time' => array(
                array('not_empty'),
            ),
            'duration_hours' => array(
                array('not_empty'),
                array('numeric'),
                array('range', array(':value', 0.5, 24)),
            ),
        );
    }

    /**
     * @return  string  «08:00–16:00 (8 ч)»
     */
    public function label()
    {
        return substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5)
            .' ('.rtrim(rtrim(number_format((float) $this->duration_hours, 2, '.', ''), '0'), '.').' ч)';
    }

    /**
     * Смена, в которую попадает момент времени (по началу смены).
     *
     * Ночная смена переходит через полночь: 00:00–08:00 считается своим днём,
     * а 16:00–00:00 — днём начала.
     *
     * @param   string  $time   ЧЧ:ММ
     * @return  Model_Erp408_Shift|null
     */
    public static function for_time($time)
    {
        $time = substr((string) $time, 0, 5);

        $row = DB::select()
            ->from('erp_408_shifts')
            ->where('is_active', '=', TRUE)
            ->where('start_time', '<=', $time)
            ->order_by('start_time', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        if ($row)
        {
            return ORM::factory('Erp408_Shift')->values($row, array_keys($row));
        }

        // До первой смены дня — значит, работает ночная смена прошлых суток
        $row = DB::select()
            ->from('erp_408_shifts')
            ->where('is_active', '=', TRUE)
            ->order_by('start_time', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        return $row ? ORM::factory('Erp408_Shift')->values($row, array_keys($row)) : NULL;
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
