<?php defined('SYSPATH') or die('No direct script access.');

/**
 * История цен поставщиков (erp403_procurement).
 *
 * Каждая запись — это «сколько стоила позиция у поставщика такого-то числа».
 * Записи появляются из заказов (согласованная цена) и из приёмок (фактическая
 * цена в накладной), поэтому выбор поставщика опирается на историю, а не на
 * память закупщика. История — единственный источник для отчёта «динамика цен»
 * и для подсказки цены в новой заявке.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_Price extends ORM
{
    protected $_table_name = 'erp_403_prices';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Источники записи истории цен */
    public static $sources = array(
        'order'   => 'Заказ поставщику',
        'receipt' => 'Приёмка',
        'manual'  => 'Внесено вручную',
    );

    /** @var array Валюты, с которыми работает модуль (справочник — erp230) */
    public static $currencies = array(
        'RUB' => '₽',
        'USD' => '$',
        'EUR' => '€',
        'CNY' => '¥',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'price' => array(
                array('numeric'),
            ),
        );
    }

    /**
     * Подпись источника записи.
     *
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * Символ валюты для интерфейса.
     *
     * @return  string
     */
    public function currency_sign()
    {
        return isset(self::$currencies[$this->currency]) ? self::$currencies[$this->currency] : (string) $this->currency;
    }

    /**
     * Цена за базовую единицу: если пришло 100 шт за 5 000, то цена за штуку 50.
     *
     * @return  float
     */
    public function unit_price()
    {
        $qty = (float) $this->qty;

        if ($qty <= 0)
        {
            return (float) $this->price;
        }

        return round((float) $this->price / $qty, 6);
    }
}
