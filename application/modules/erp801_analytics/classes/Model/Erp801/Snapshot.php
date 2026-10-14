<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель снимка витрины аналитики (erp801_analytics).
 *
 * Снимок — это отчёт на память: код витрины, фильтры и готовый результат.
 * Он не пересчитывается и не «догоняет» источник: если через месяц соседний
 * модуль перепроведёт документ, снимок останется таким, каким его видели на
 * планёрке. Именно поэтому payload хранится строкой JSON, а не ссылкой на
 * запрос: снимок должен читаться, даже когда источник недоступен.
 *
 * @package    Erp801
 * @category   Models
 */
class Model_Erp801_Snapshot extends ORM
{
    protected $_table_name = 'erp_801_snapshots';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Витрины, которые можно снять */
    public static $codes = array(
        'summary'    => 'Сводка (все блоки)',
        'stock'      => 'Запасы: оборачиваемость и покрытие',
        'abc'        => 'Запасы: ABC/XYZ',
        'production' => 'Производство: OEE и простои',
        'quality'    => 'Качество: брак и причины',
        'purchases'  => 'Закупки: заказы и просрочка',
    );

    /**
     * Название витрины по коду.
     *
     * @param   string  $code
     * @return  string
     */
    public static function code_label($code)
    {
        return isset(self::$codes[$code]) ? self::$codes[$code] : (string) $code;
    }

    /**
     * Расшифрованный payload снимка.
     *
     * @return  array
     */
    public function payload()
    {
        if ($this->_object['payload'] === NULL OR $this->_object['payload'] === '')
        {
            return array();
        }

        if (is_array($this->_object['payload']))
        {
            return $this->_object['payload'];
        }

        $decoded = json_decode((string) $this->_object['payload'], TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Фильтры, с которыми снимали витрину.
     *
     * @return  array
     */
    public function filters()
    {
        if ($this->_object['filters'] === NULL OR $this->_object['filters'] === '')
        {
            return array();
        }

        if (is_array($this->_object['filters']))
        {
            return $this->_object['filters'];
        }

        $decoded = json_decode((string) $this->_object['filters'], TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Представление снимка для API и списков.
     *
     * @param   bool    $with_payload
     * @return  array
     */
    public function as_row($with_payload = FALSE)
    {
        $row = array(
            'id'         => (int) $this->id,
            'uuid'       => (string) $this->uuid,
            'code'       => (string) $this->code,
            'code_label' => self::code_label((string) $this->code),
            'day'        => (string) $this->day,
            'title'      => (string) $this->title,
            'filters'    => $this->filters(),
            'note'       => (string) $this->note,
            'created_at' => (string) $this->created_at,
            'created_by' => $this->created_by === NULL ? NULL : (int) $this->created_by,
        );

        if ($with_payload)
        {
            $row['payload'] = $this->payload();
        }

        return $row;
    }
}
