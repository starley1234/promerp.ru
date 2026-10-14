<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Остановка JSON-ответа в продажах (erp410_shipment).
 *
 * Konine не прерывает запрос после `before()` и не выходит из действия после
 * отправки ответа. Поэтому ошибка в JSON API обязана бросать исключение: его
 * ловит `Controller_Erp410_Main` и превращает в корректный JSON с нужным кодом.
 *
 * @package    Erp410
 * @category   Exceptions
 */
class HTTP_Exception_Erp410_Json extends HTTP_Exception
{
    /** @var string */
    protected $_reason;

    /**
     * @param   int             $code
     * @param   string          $message
     * @param   string|null     $reason
     */
    public function __construct($code, $message, $reason = NULL)
    {
        $this->_reason = $reason ? (string) $reason : 'error';

        parent::__construct($code, $message);
    }

    /**
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
