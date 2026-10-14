<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель складского движения (erp401_wms).
 *
 * Журнал_append-only: сторно делается отдельной записью (status='void'
 * или встречным движением), строки не редактируются и не удаляются.
 *
 * @package    Erp401
 * @category   Models
 */
class Model_Erp401_Movement extends ORM
{
    protected $_table_name = 'erp_401_movements';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'warehouse' => array(
            'model'       => 'Erp401_Warehouse',
            'foreign_key' => 'warehouse_id',
        ),
        'warehouse_to' => array(
            'model'       => 'Erp401_Warehouse',
            'foreign_key' => 'warehouse_to_id',
        ),
    );

    /** @var array Типы движений (совпадают с CHECK-ограничением в БД) */
    public static $types = array(
        'receipt'  => 'Приход',
        'issue'    => 'Расход',
        'transfer' => 'Перемещение',
        'adjust'   => 'Корректировка',
    );

    /** @var array Статусы движений */
    public static $statuses = array(
        'draft'  => 'Черновик',
        'posted' => 'Проведено',
        'void'   => 'Сторнировано',
    );

    public function rules()
    {
        return array(
            'type'         => array(array('not_empty'), array('in_array', array(':value', array_keys(self::$types)))),
            'status'       => array(array('not_empty'), array('in_array', array(':value', array_keys(self::$statuses)))),
            'warehouse_id' => array(array('not_empty'), array('digit')),
            'item_id'      => array(array('not_empty'), array('digit')),
            'qty'          => array(array('not_empty'), array('numeric')),
        );
    }

    public function labels()
    {
        return array(
            'type'           => 'Тип движения',
            'item_id'        => 'Номенклатура',
            'uom_code'       => 'Единица измерения',
            'warehouse_id'   => 'Склад-источник',
            'warehouse_to_id'=> 'Склад-получатель',
            'qty'            => 'Количество',
            'balance_before' => 'Остаток до',
            'balance_after'  => 'Остаток после',
            'status'         => 'Статус',
            'doc_type'       => 'Документ-основание',
            'doc_id'         => 'Номер документа',
            'note'           => 'Комментарий',
            'performed_at'   => 'Дата операции',
            'created_at'     => 'Создано',
        );
    }

    /**
     * Человекочитаемый тип движения.
     *
     * @return string
     */
    public function type_label()
    {
        return isset(self::$types[$this->type]) ? self::$types[$this->type] : $this->type;
    }

    /**
     * Человекочитаемый статус движения.
     *
     * @return string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }

    /**
     * Знак движения для отображения: приход — «+», расход — «−».
     *
     * @return string
     */
    public function signed_qty()
    {
        $qty = number_format((float) $this->qty, 3, '.', ' ');

        switch ($this->type)
        {
            case 'receipt':
                return '+'.$qty;
            case 'issue':
                return '&minus;'.$qty;
            default:
                return $qty;
        }
    }
}
