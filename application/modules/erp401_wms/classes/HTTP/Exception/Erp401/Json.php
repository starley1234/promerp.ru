<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка JSON API складского учёта (erp401_wms).
 *
 * Kohana перехватывает HTTP_Exception в Request_Client_Internal и превращает
 * её в ответ через get_response(), поэтому API просто выбрасывает этот класс,
 * а вызывающая сторона всегда получает {"success": false, "error": {...}}.
 *
 * @package    Erp401
 * @category   Exceptions
 */
class HTTP_Exception_Erp401_Json extends HTTP_Exception
{
    /** @var int HTTP-статус ответа */
    protected $_status = 400;

    /** @var string Машинный код причины */
    protected $_reason = 'error';

    /**
     * @param string $message Текст для вызывающей стороны
     * @param string $reason  Машинный код (insufficient_stock, invalid_qty, ...)
     * @param int    $status  HTTP-статус
     */
    public function __construct($message, $reason = 'error', $status = 400)
    {
        parent::__construct($message);

        $this->_reason = (string) $reason;
        $this->_status = (int) $status;

        // Базовый класс выводит код из имени класса (…_404 → 404). У нас в имени
        // нет кода, поэтому задаём его явно — иначе исключение выглядит как «0»
        // и внешние обработчики (логи, тесты, клиенты) видят его как 500.
        $this->code = (int) $status;
    }

    /**
     * @return Response
     */
    public function get_response()
    {
        return Response::factory()
            ->status($this->_status)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(array(
                'success' => FALSE,
                'error'   => array(
                    'message' => $this->getMessage(),
                    'reason'  => $this->_reason,
                ),
            ), JSON_UNESCAPED_UNICODE));
    }
}
