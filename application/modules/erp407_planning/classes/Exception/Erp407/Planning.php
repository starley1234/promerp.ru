<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка планирования (erp407_planning).
 *
 * `reason()` — короткий машинный код, по которому контроллер показывает
 * понятный текст, API отдаёт конверт `error.reason`, а тесты проверяют
 * поведение, не разбирая сообщения:
 *
 *  - `not_found`         — программы, строки или предложения нет;
 *  - `invalid_transition`— действие недопустимо в текущем состоянии
 *                          (например, расчёт по закрытой программе);
 *  - `no_demand`         — в программе нет строк: считать нечего;
 *  - `dependency`        — недоступен модуль, без которого расчёт невозможен
 *                          (склад erp401 или техкарты erp228);
 *  - `proposal_locked`   — предложение уже утверждено или передано: правки
 *                          и повторная передача запрещены;
 *  - `export_failed`     — передача в исполнение не удалась (нет модуля-приёмника).
 *
 * @package    Erp407
 * @category   Exceptions
 */
class Exception_Erp407_Planning extends Kohana_Exception
{
    /** @var string */
    protected $_reason = 'planning_error';

    /**
     * @param   string      $message
     * @param   array|null  $variables
     * @param   string      $reason
     * @param   int         $code
     * @param   Exception|null $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'planning_error', $code = 0, ?Exception $previous = NULL)
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
