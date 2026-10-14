<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Исключение модуля аналитики (erp801_analytics).
 *
 * Причина (`reason`) машинно-читаема и стабильна: `invalid_argument` — неверный
 * фильтр или период, `not_found` — нет такого снимка, `source_missing` — витрина
 * не может быть посчитана, потому что источник не подключён (например, модуль
 * цеха выключен), `validation` — порог вне допустимого диапазона.
 *
 * Отдельно про `source_missing`: аналитика не имеет права падать от того, что
 * сосед выключен. Витрина честно говорит «источник недоступен» и остаётся
 * рабочей для тех блоков, чьи источники на месте.
 *
 * @package    Erp801
 * @category   Exceptions
 */
class Exception_Erp801_Analytics extends Kohana_Exception
{
    /** @var string */
    protected $_reason;

    /**
     * @param   string  $message
     * @param   array   $variables
     * @param   string  $reason
     */
    public function __construct($message = 'Ошибка аналитики', array $variables = array(), $reason = 'error')
    {
        $this->_reason = (string) $reason;

        parent::__construct($message, $variables);
    }

    /**
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
