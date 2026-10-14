<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Исключение складского учёта (erp401_wms).
 *
 * Выбрасывается сервисом Service_Erp401_Stock при нарушении бизнес-правил
 * (недостаток остатка, неверное количество, склад не найден и т.п.).
 * Отличается от системных ошибок тем, что текст безопасен для показа
 * пользователю в форме.
 *
 * @package    Erp401
 * @category   Exceptions
 */
class Exception_Erp401_Stock extends Kohana_Exception
{
    /**
     * Код, удобный для обработки вызывающей стороной.
     *
     * @var string
     */
    protected $_reason = 'error';

    /**
     * @param string          $message  Текст для пользователя
     * @param array|null      $variables Подстановки для Kohana_Exception
     * @param string          $reason   Машинный код причины
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'error')
    {
        $this->_reason = $reason;

        parent::__construct($message, $variables);
    }

    /**
     * Машинный код причины (insufficient_stock, invalid_qty, ...).
     *
     * @return string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
