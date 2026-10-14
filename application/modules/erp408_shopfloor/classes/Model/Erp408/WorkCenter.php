<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Рабочий центр или участок (erp408_shopfloor).
 *
 * Центр — это место, куда мастер назначает операцию: станок, пост, участок.
 * Производительность (`capacity_per_hour`) и стоимость нормо-часа
 * (`cost_per_hour`) нужны плановой себестоимости: без них `erp409` считает
 * только материалы.
 *
 * @package    Erp408
 * @category   Models
 */
class Model_Erp408_WorkCenter extends ORM
{
    protected $_table_name = 'erp_408_work_centers';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'operations' => array('model' => 'Erp408_Operation', 'foreign_key' => 'work_center_id'),
    );

    /** @var array Виды: участок объединяет несколько рабочих мест */
    public static $kinds = array(
        'center' => 'Рабочий центр',
        'area'   => 'Участок',
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
            'kind' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$kinds))),
            ),
            'capacity_per_hour' => array(
                array('numeric'),
            ),
            'cost_per_hour' => array(
                array('numeric'),
            ),
        );
    }

    /**
     * Подпись целиком: код и название — так центр видно в списках и терминале.
     *
     * @return  string
     */
    public function label()
    {
        return trim($this->code.' '.$this->name);
    }

    /**
     * @return  string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : (string) $this->kind;
    }

    /**
     * Плановые часы центра на день с учётом календаря и смен.
     *
     * @param   string      $day    ГГГГ-ММ-ДД
     * @return  float
     */
    public function planned_hours($day)
    {
        if ( ! class_exists('Service_Erp408_Shifts'))
        {
            return 0.0;
        }

        return Service_Erp408_Shifts::instance()->hours_for($this->id, $day);
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
