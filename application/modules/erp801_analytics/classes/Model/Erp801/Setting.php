<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель настройки витрины (erp801_analytics).
 *
 * Порог живёт строкой, а разбор и проверку диапазона делает сервис настроек:
 * так новая настройка не требует миграции. Здесь — только хранение и
 * читаемое представление строки.
 *
 * @package    Erp801
 * @category   Models
 */
class Model_Erp801_Setting extends ORM
{
    protected $_table_name = 'erp_801_settings';

    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /**
     * @return  array
     */
    public function as_row()
    {
        return array(
            'id'          => (int) $this->id,
            'code'        => (string) $this->code,
            'value'       => (string) $this->value,
            'updated_at'  => (string) $this->updated_at,
            'updated_by'  => $this->updated_by === NULL ? NULL : (int) $this->updated_by,
        );
    }
}
