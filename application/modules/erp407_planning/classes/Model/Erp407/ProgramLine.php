<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Строка производственной программы (erp407_planning).
 *
 * Одна строка — «столько изделий к такому сроку». Ссылки на номенклатуру
 * (`item_id`) и техкарту (`tech_card_id`) мягкие: строка может существовать и
 * без них (завод часто планирует «по названию»), но тогда расчёт потребности
 * по составу невозможен — модуль честно скажет об этом в предупреждениях.
 *
 * @package    Erp407
 * @category   Models
 */
class Model_Erp407_ProgramLine extends ORM
{
    protected $_table_name = 'erp_407_program_lines';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'program' => array('model' => 'Erp407_Program', 'foreign_key' => 'program_id'),
    );

    /** @var array Состояния строки */
    public static $statuses = array(
        'planned'   => 'В плане',
        'produced'  => 'Выпущено',
        'cancelled' => 'Снята с плана',
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
     * Подпись состояния строки.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * Возможность считать состав: нужны номенклатура и техкарта.
     *
     * @return  bool
     */
    public function is_expandable()
    {
        return ((int) $this->item_id > 0 AND (int) $this->tech_card_id > 0);
    }
}
