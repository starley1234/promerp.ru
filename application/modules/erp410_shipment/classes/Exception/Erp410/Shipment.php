<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка бизнес-правил продажи (erp410_shipment).
 *
 * Сообщение — для человека (показывается на странице), `reason` — для машины
 * (JSON API, MCP): `not_found`, `invalid_transition`, `empty_document`,
 * `insufficient_stock`, `credit_limit`, `already_posted`, `duplicate_invoice`,
 * `customer_required`, `warehouse_required`, `item_required`, `invalid_qty`,
 * `invalid_price`, `dependency`.
 *
 * @package    Erp410
 * @category   Exceptions
 */
class Exception_Erp410_Shipment extends Kohana_Exception
{
    /** @var string Машинно-читаемая причина отказа */
    protected $_reason;

    /**
     * @param   string          $message
     * @param   array|null      $variables
     * @param   string|null     $reason
     * @param   int             $code
     * @param   Exception|null  $previous
     */
    public function __construct($message = '', ?array $variables = NULL, $reason = NULL, $code = 0, ?Exception $previous = NULL)
    {
        $this->_reason = $reason ? (string) $reason : 'error';

        parent::__construct($message, $variables, $code, $previous);
    }

    /**
     * Машинно-читаемая причина отказа.
     *
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
