<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Остановка действия API регистра (erp227_core_registry).
 *
 * Ответ об ошибке уже лежит в `$this->response`: исключение нужно лишь для
 * того, чтобы прервать выполнение действия. Так делают API-контроллеры ядра
 * (`_json_halt()`), и это принципиально отличается от прежнего `exit`:
 * вызывающий процесс (дымовой прогон, тест, MCP-мост, внутренний вызов)
 * продолжает работу и получает нормальный ответ.
 *
 * @package    Erp227
 * @category   Exceptions
 */
class Exception_Erp227_Api_Response extends Exception {

    /** @var int Код ответа HTTP */
    protected $_status_code;

    public function __construct($message, $code = 400, ?Exception $previous = NULL)
    {
        $this->_status_code = (int) $code;

        parent::__construct((string) $message, 0, $previous);
    }

    /**
     * @return int
     */
    public function status_code()
    {
        return $this->_status_code;
    }
}
