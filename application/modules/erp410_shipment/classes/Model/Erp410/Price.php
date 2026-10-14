<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Цена продажи (erp410_shipment).
 *
 * Две вещи в одной таблице: базовая цена позиции (`customer_id` пуст) и
 * договорная цена клиента. Сервис продаж всегда берёт самую свежую подходящую
 * запись: сначала цену клиента, потом базовую. Так прайс не приходится
 * дублировать по каждому покупателю, а история цен остаётся честной.
 *
 * @package    Erp410
 * @category   Models
 */
class Model_Erp410_Price extends ORM
{
    protected $_table_name = 'erp_410_prices';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Источники цены */
    public static $sources = array(
        'manual'   => 'Прайс',
        'order'    => 'Заказ',
        'shipment' => 'Отгрузка',
        'invoice'  => 'Счёт',
        'import'   => 'Импорт',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'item_id' => array(
                array('not_empty'),
                array('numeric'),
            ),
            'price' => array(
                array('numeric'),
            ),
        );
    }

    /**
     * Источник цены словарём интерфейса.
     *
     * @return  string  info у договорной, neutral у базовой, ok у цены из документа
     */
    public function source_state()
    {
        if ($this->is_contract())
        {
            return 'info';
        }

        return ($this->customer_id === NULL OR $this->customer_id === '') ? 'neutral' : 'ok';
    }

    /**
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * Цена с учётом скидки записи.
     *
     * @return  float
     */
    public function net_price()
    {
        return round((float) $this->price * (1 - (float) $this->discount / 100), 6);
    }

    /**
     * Цена договорная (для конкретного клиента) или базовая.
     *
     * @return  bool
     */
    public function is_contract()
    {
        return ! empty($this->customer_id);
    }
}
