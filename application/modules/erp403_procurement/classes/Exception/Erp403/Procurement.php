<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка закупок (erp403_procurement).
 *
 * `reason()` — короткий машинный код, по которому контроллер показывает
 * понятный текст, JSON API отдаёт `error.reason`, а тесты проверяют поведение,
 * не разбирая сообщения:
 *
 *  - `not_found`          — заявки, заказа, приёмки или строки нет;
 *  - `invalid_transition` — действие недопустимо в текущем состоянии
 *                           (согласовать отклонённую заявку, отправить
 *                           отменённый заказ, принять уже принятое);
 *  - `empty_document`     — в документе нет строк: согласовывать и заказывать
 *                           нечего;
 *  - `dependency`         — недоступен модуль, без которого действие
 *                           невозможно (склад erp401, контрагенты erp301);
 *  - `quality_blocked`    — позиция приёмки не прошла входной контроль:
 *                           приход на склад запрещён (erp405);
 *  - `duplicate_supplier` — у поставщика уже есть такой профиль;
 *  - `already_posted`     — документ проведён: правки запрещены;
 *  - `supplier_required`  — заказ без поставщика создать нельзя.
 *
 * @package    Erp403
 * @category   Exceptions
 */
class Exception_Erp403_Procurement extends Kohana_Exception
{
    /** @var string */
    protected $_reason = 'procurement_error';

    /**
     * @param   string          $message
     * @param   array|null      $variables
     * @param   string          $reason
     * @param   int             $code
     * @param   Exception|null  $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'procurement_error', $code = 0, ?Exception $previous = NULL)
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
