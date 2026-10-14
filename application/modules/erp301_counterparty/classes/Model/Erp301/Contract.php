<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Договор с контрагентом (erp301_counterparty).
 *
 * Скан договора хранится в erp212_file_storage (поле file_id), модуль файлы не дублирует.
 *
 * @package    Erp301
 * @category   Models
 */
class Model_Erp301_Contract extends ORM
{
    protected $_table_name = 'erp_301_contracts';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'counterparty' => array('model' => 'Erp301_Counterparty', 'foreign_key' => 'counterparty_id'),
    );

    /** @var array Виды договоров */
    public static $kinds = array(
        'supply'  => 'Поставка',
        'service' => 'Услуги',
        'work'    => 'Работы',
        'lease'   => 'Аренда',
        'other'   => 'Прочее',
    );

    /** @var array Статусы договора */
    public static $statuses = array(
        'draft'      => 'Проект',
        'active'     => 'Действует',
        'suspended'  => 'Приостановлен',
        'expired'    => 'Истёк',
        'terminated' => 'Расторгнут',
    );

    public function rules()
    {
        return array(
            'number' => array(
                array('not_empty'),
                array('max_length', array(':value', 64)),
                array(array($this, 'number_unique')),
            ),
            'kind' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$kinds))),
            ),
            'status' => array(
                array('in_array', array(':value', array_keys(self::$statuses))),
            ),
            'amount' => array(
                array('numeric'),
            ),
            'valid_to' => array(
                array(array($this, 'valid_period'), array(':value')),
            ),
        );
    }

    /**
     * Номер договора уникален внутри контрагента.
     *
     * @param  string $value
     * @return bool
     */
    public function number_unique($value)
    {
        $query = ORM::factory('Erp301_Contract')
            ->where('counterparty_id', '=', $this->counterparty_id)
            ->where('number', '=', $value)
            ->where('is_deleted', '=', 0);

        if ($this->loaded())
        {
            $query->where('id', '<>', $this->id);
        }

        return (int) $query->count_all() === 0;
    }

    /**
     * Дата окончания не раньше даты начала.
     *
     * @param  string $value
     * @return bool
     */
    public function valid_period($value)
    {
        if ($value === NULL OR $value === '' OR empty($this->valid_from))
        {
            return TRUE;
        }

        return strtotime($value) >= strtotime($this->valid_from);
    }

    public function labels()
    {
        return array(
            'number'        => 'Номер',
            'title'         => 'Предмет договора',
            'kind'          => 'Вид',
            'signed_at'     => 'Дата подписания',
            'valid_from'    => 'Действует с',
            'valid_to'      => 'Действует по',
            'amount'        => 'Сумма',
            'currency'      => 'Валюта',
            'payment_terms' => 'Условия оплаты',
            'status'        => 'Статус',
            'file_id'       => 'Скан договора',
            'note'          => 'Заметка',
        );
    }

    /**
     * @return string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : $this->kind;
    }

    /**
     * @return string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }

    /**
     * Договор истекает в ближайшие N дней.
     *
     * @param  int $days
     * @return bool
     */
    public function expires_soon($days = 30)
    {
        if ( ! $this->valid_to OR $this->status !== 'active')
        {
            return FALSE;
        }

        $left = strtotime($this->valid_to) - strtotime(date('Y-m-d'));

        return $left >= 0 AND $left <= $days * 86400;
    }

    /**
     * Сумма с валютой: «1 250 000,00 RUB».
     *
     * @return string
     */
    public function amount_label()
    {
        if ($this->amount === NULL OR $this->amount === '')
        {
            return '—';
        }

        return number_format((float) $this->amount, 2, ',', ' ').' '.$this->currency;
    }
}
