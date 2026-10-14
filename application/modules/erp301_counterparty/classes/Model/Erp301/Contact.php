<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контактное лицо контрагента (erp301_counterparty).
 *
 * @package    Erp301
 * @category   Models
 */
class Model_Erp301_Contact extends ORM
{
    protected $_table_name = 'erp_301_contacts';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'counterparty' => array('model' => 'Erp301_Counterparty', 'foreign_key' => 'counterparty_id'),
    );

    public function rules()
    {
        return array(
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'email' => array(
                array('email', array(':value')),
            ),
            'phone' => array(
                array('max_length', array(':value', 64)),
            ),
        );
    }

    public function labels()
    {
        return array(
            'name'       => 'Ф. И. О.',
            'position'   => 'Должность',
            'phone'      => 'Телефон',
            'email'      => 'E-mail',
            'is_primary' => 'Основной контакт',
            'note'       => 'Заметка',
        );
    }

    /**
     * Инициалы и должность одной строкой.
     *
     * @return string
     */
    public function summary()
    {
        $parts = array_filter(array($this->position, $this->phone, $this->email));

        return $parts ? implode(' · ', $parts) : '—';
    }
}
