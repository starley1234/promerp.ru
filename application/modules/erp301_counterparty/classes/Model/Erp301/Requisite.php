<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реквизиты контрагента: банковский счёт, юридический или почтовый адрес
 * (erp301_counterparty).
 *
 * @package    Erp301
 * @category   Models
 */
class Model_Erp301_Requisite extends ORM
{
    protected $_table_name = 'erp_301_requisites';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'counterparty' => array('model' => 'Erp301_Counterparty', 'foreign_key' => 'counterparty_id'),
    );

    /** @var array Виды реквизитов */
    public static $kinds = array(
        'bank'   => 'Банковский счёт',
        'legal'  => 'Юридический адрес',
        'postal' => 'Почтовый адрес',
    );

    public function rules()
    {
        return array(
            'kind' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$kinds))),
            ),
            'account' => array(
                array(array($this, 'account_format'), array(':value')),
            ),
            'bik' => array(
                array(array($this, 'bik_format'), array(':value')),
            ),
        );
    }

    /**
     * Расчётный счёт: 20 цифр (только для банковских реквизитов).
     *
     * @param  string $value
     * @return bool
     */
    public function account_format($value)
    {
        if ($this->kind !== 'bank' OR $value === NULL OR $value === '')
        {
            return TRUE;
        }

        return (bool) preg_match('/^\d{20}$/', (string) $value);
    }

    /**
     * БИК: 9 цифр.
     *
     * @param  string $value
     * @return bool
     */
    public function bik_format($value)
    {
        if ($value === NULL OR $value === '')
        {
            return TRUE;
        }

        return (bool) preg_match('/^\d{9}$/', (string) $value);
    }

    public function labels()
    {
        return array(
            'kind'         => 'Вид реквизитов',
            'title'        => 'Название',
            'bank_name'    => 'Банк',
            'bik'          => 'БИК',
            'account'      => 'Расчётный счёт',
            'corr_account' => 'Корр. счёт',
            'currency'     => 'Валюта',
            'address'      => 'Адрес',
            'is_primary'   => 'Основные',
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
     * Реквизиты одной строкой для печати.
     *
     * @return string
     */
    public function summary()
    {
        if ($this->kind === 'bank')
        {
            return trim(implode(', ', array_filter(array(
                $this->bank_name,
                $this->bik ? 'БИК '.$this->bik : NULL,
                $this->account ? 'счёт '.$this->account : NULL,
            ))));
        }

        return (string) $this->address;
    }
}
