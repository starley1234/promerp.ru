<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Исключение производства (erp402_production).
 *
 * Выбрасывается сервисами модуля при нарушении бизнес-правил: недопустимый
 * переход статуса, пустой состав, нехватка материалов, попытка изменить
 * завершённый заказ. Текст исключения безопасен для показа пользователю.
 *
 * @package    Erp402
 * @category   Exceptions
 */
class Exception_Erp402_Production extends Kohana_Exception
{
    /** @var string Машинный код причины */
    protected $_reason = 'error';

    /**
     * @param   string  $message    Текст для пользователя
     * @param   array   $variables  Подстановки для Kohana_Exception
     * @param   string  $reason     Машинный код (invalid_transition, empty_bom, ...)
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'error')
    {
        $this->_reason = $reason;

        parent::__construct($message, $variables);
    }

    /**
     * Машинный код причины.
     *
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
