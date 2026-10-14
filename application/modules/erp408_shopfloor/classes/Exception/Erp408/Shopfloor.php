<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка цеха (erp408_shopfloor).
 *
 * Причина (`reason()`) — короткий машинный код: контроллеры показывают по нему
 * понятный текст, API отдаёт в конверте `error.reason`, тесты проверяют
 * поведение, не разбирая сообщения.
 *
 *  - `invalid_transition`  — операцию нельзя перевести в это состояние
 *                            (например, завершить отменённую);
 *  - `not_assigned`        — операция без рабочего центра или смены;
 *  - `no_work_center`      — рабочий центр не найден или выключен;
 *  - `order_not_found`     — производственный заказ не найден;
 *  - `no_route`            — у заказа нет ни операций техкарты, ни состава;
 *  - `qty_invalid`         — выработка или брак не сходятся с планом;
 *  - `nothing_to_do`       — операция уже завершена, отметка не нужна;
 *  - `not_working_day`     — попытка запланировать работу в нерабочий день.
 *
 * @package    Erp408
 * @category   Exceptions
 */
class Exception_Erp408_Shopfloor extends Kohana_Exception
{
    /** @var string */
    protected $_reason = 'shopfloor_error';

    /**
     * @param   string      $message
     * @param   array|null  $variables
     * @param   string      $reason
     * @param   int         $code
     * @param   Exception|null $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'shopfloor_error', $code = 0, ?Exception $previous = NULL)
    {
        $this->_reason = (string) $reason;

        parent::__construct($message, is_array($variables) ? $variables : array(), $code, $previous);
    }

    /**
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
