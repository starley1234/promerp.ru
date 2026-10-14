<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка MCP-слоя (erp804_mcp).
 *
 * Причина (`reason()`) — короткий код для агента и для SQL-фильтров:
 *
 *  - `unauthorized`     — токена нет или он неверный/истёк;
 *  - `forbidden`        — у ключа нет нужного права (scope);
 *  - `read_only`        — ключ только для чтения, а инструмент изменяющий;
 *  - `unknown_tool`     — инструмент не объявлен ни одним модулем;
 *  - `invalid_arguments`— аргументы не прошли проверку по схеме;
 *  - `approval_required`— инструмент ждёт подтверждения человека;
 *  - `failed`           — инструмент вернул ошибку бизнес-логики;
 *  - `disabled`         — модуль или инструмент выключен.
 *
 * @package    Erp804
 * @category   Exceptions
 */
class Exception_Erp804_Mcp extends Kohana_Exception
{
    /**
     * @var string
     */
    protected $_reason = 'failed';

    /**
     * @param   string  $message
     * @param   array   $variables
     * @param   string  $reason
     * @param   int     $code
     * @param   Exception|NULL $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'failed', $code = 0, ?Exception $previous = NULL)
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

    /**
     * Код JSON-RPC для ошибки.
     *
     * @return  int
     */
    public function rpc_code()
    {
        switch ($this->_reason)
        {
            case 'unauthorized':
                return -32001;
            case 'forbidden':
            case 'read_only':
                return -32003;
            case 'unknown_tool':
                return -32601;
            case 'invalid_arguments':
                return -32602;
            case 'approval_required':
                return -32010;
            default:
                return -32603;
        }
    }
}
