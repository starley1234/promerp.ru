<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Условия клиента (erp410_shipment).
 *
 * Профиль продажи поверх контрагента erp301: отсрочка, кредитный лимит,
 * скидка и адрес доставки. Своих контрагентов модуль не заводит — покупатели
 * живут в справочнике erp301 с ролью «покупатель», а здесь лежат только
 * торговые условия.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_Customer extends ORM
{
    protected $_table_name = 'erp_410_customers';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'customer_id' => array(
                array('not_empty'),
                array('numeric'),
            ),
        );
    }

    /**
     * @return  bool
     */
    public function is_active()
    {
        return ((int) $this->is_active === 1);
    }

    /**
     * Отсрочка в днях (0 — предоплата).
     *
     * @return  int
     */
    public function credit_days()
    {
        return max(0, (int) $this->credit_days);
    }

    /**
     * Скидка клиента, %.
     *
     * @return  float
     */
    public function discount()
    {
        return round((float) $this->discount, 2);
    }

    /**
     * Условие оплаты человеческим языком.
     *
     * @return  string
     */
    public function payment_label()
    {
        if ((string) $this->payment_terms !== '')
        {
            return (string) $this->payment_terms;
        }

        return $this->credit_days() > 0
            ? 'Отсрочка '.$this->credit_days().' дней'
            : 'Предоплата';
    }
}
