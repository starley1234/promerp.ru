<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель резерва номенклатуры (erp401_wms).
 *
 * Резерв «замораживает» часть свободного остатка под документ-потребитель
 * (заказ клиента, производственное задание). Жизненный цикл:
 * active -> consumed (списано расходом) | active -> released (резерв снят).
 *
 * @package    Erp401
 * @category   Models
 */
class Model_Erp401_Reservation extends ORM
{
    protected $_table_name = 'erp_401_reservations';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'warehouse' => array(
            'model'       => 'Erp401_Warehouse',
            'foreign_key' => 'warehouse_id',
        ),
    );

    /** @var array Статусы резерва (совпадают с CHECK-ограничением в БД) */
    public static $statuses = array(
        'active'   => 'Активен',
        'released' => 'Снят',
        'consumed' => 'Списан',
    );

    public function rules()
    {
        return array(
            'warehouse_id' => array(array('not_empty'), array('digit')),
            'item_id'      => array(array('not_empty'), array('digit')),
            'qty'          => array(array('not_empty'), array('numeric')),
            'status'       => array(array('not_empty'), array('in_array', array(':value', array_keys(self::$statuses)))),
        );
    }

    public function labels()
    {
        return array(
            'warehouse_id' => 'Склад',
            'item_id'      => 'Номенклатура',
            'qty'          => 'Количество',
            'status'       => 'Статус',
            'ref_type'     => 'Документ-потребитель',
            'ref_id'       => 'Номер документа',
            'expires_at'   => 'Действует до',
            'created_at'   => 'Создан',
            'released_at'  => 'Закрыт',
        );
    }

    /**
     * Человекочитаемый статус резерва.
     *
     * @return string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }

    /**
     * Резерв просрочен?
     *
     * @return bool
     */
    public function is_expired()
    {
        if ($this->status !== 'active' OR empty($this->expires_at))
        {
            return FALSE;
        }

        return strtotime($this->expires_at) < time();
    }
}
