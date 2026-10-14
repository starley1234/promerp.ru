<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка качества (erp405_quality).
 *
 * `reason()` — короткий машинный код: контроллер показывает по нему понятный
 * текст, JSON API отдаёт `error.reason`, тесты проверяют поведение, не разбирая
 * сообщения:
 *
 *  - `not_found`          — контроля, дефекта или кода дефекта нет;
 *  - `invalid_transition` — действие недопустимо в текущем состоянии (решение
 *                           принято повторно, закрытый контроль правят);
 *  - `empty_document`     — у контроля нет дефектов: итог «брак» ставить не на
 *                           чем;
 *  - `dependency`         — недоступен модуль, без которого действие
 *                           невозможно (склад erp401, приёмки erp403);
 *  - `warehouse_required` — нет склада нужного типа (`quarantine`, `scrap`):
 *                           изолировать партию или списать брак некуда;
 *  - `qty_exceeded`       — дефектов больше, чем в партии, или из карантина
 *                           просят больше, чем там лежит;
 *  - `not_quarantined`    — партия не на карантине: выпускать нечего;
 *  - `duplicate_code`     — код дефекта с таким обозначением уже есть;
 *  - `in_use`             — код дефекта уже встречался: удалять нельзя, только
 *                           выключать.
 *
 * @package    Erp405
 * @category   Exceptions
 */
class Exception_Erp405_Quality extends Kohana_Exception
{
    /** @var string */
    protected $_reason = 'quality_error';

    /**
     * @param   string          $message
     * @param   array|null      $variables
     * @param   string          $reason
     * @param   int             $code
     * @param   Exception|null  $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'quality_error', $code = 0, ?Exception $previous = NULL)
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
