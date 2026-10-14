<?php defined('SYSPATH') or die('No direct script access.');

/**
 * HTTP-транспорт MCP-сервера (erp804_mcp).
 *
 * Одна точка входа — `/mcp`:
 *
 *   POST /mcp   — JSON-RPC 2.0 (одиночное сообщение или пакет), ответ JSON;
 *   GET  /mcp   — сведения о сервере (без ключа) для проверки подключения;
 *   OPTIONS /mcp — предварительный запрос CORS.
 *
 * Авторизация: ключ агента в заголовке `Authorization: Bearer <токен>`,
 * `X-Mcp-Key` или параметром `?key=` (последнее — для отладки curl).
 * Сессия пользователя не требуется: агент работает не от имени человека,
 * но его ключ имеет свой набор прав и виден в журнале вызовов.
 *
 * Спецификация MCP допускает ответ обычным JSON (без SSE) — так и сделано:
 * ответ приходит одним документом, потоковые уведомления не используются.
 *
 * @package    Erp804
 * @category   Controllers
 */
class Controller_Erp804_Mcp extends Controller
{
    /**
     * @var bool Ответ — JSON, шаблон не нужен
     */
    public $auto_render = FALSE;

    /**
     * @var bool Агент авторизуется ключом, а не сессией
     */
    protected $auth_required = FALSE;

    /**
     * POST /mcp — обработка сообщений JSON-RPC.
     */
    public function action_index()
    {
        $server = Service_Erp804_Server::instance();
        $mode   = $this->_server_mode();
        $method = $this->request->method();

        if ($method === HTTP_Request::OPTIONS)
        {
            $this->response
                ->status(204)
                ->headers('Access-Control-Allow-Origin', '*')
                ->headers('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Mcp-Key')
                ->headers('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');

            return;
        }

        $token = $this->_token();

        if ($method === HTTP_Request::GET AND ! $token)
        {
            $info = $server->info();
            $this->_output(array(
                'jsonrpc' => '2.0',
                'result'  => $info + array(
                    'mode' => $mode,
                    'endpoint' => $this->_endpoint_path(),
                    'advertised_tools' => $mode === 'discovery' ? 2 : $info['tools'],
                    'hint' => $mode === 'discovery'
                        ? 'POST '.$this->_endpoint_path().': сначала system.tool.search, затем готовый system.tool.call из результата.'
                        : 'POST /mcp с JSON-RPC и заголовком Authorization: Bearer <ключ агента>. Ключи выдаются в /mcp/keys.',
                ),
            ));

            return;
        }

        try
        {
            // Ключ всегда проверяется в основной базе. После этого специализированный
            // endpoint может переключить только выполнение tools на demo-схему.
            $key = $this->_effective_key($server->authenticate($token));
            $prepared = FALSE;

            try
            {
                $this->_prepare_tool_runtime();
                $prepared = TRUE;
                $payload = json_decode($this->_body(), TRUE);
                $answer  = $server->handle_payload($payload, $key, $mode);

                if ($answer === NULL)
                {
                    $this->response
                        ->status(202)
                        ->headers('Content-Type', 'application/json; charset=utf-8')
                        ->body('');

                    return;
                }

                $this->_output($answer);
            }
            finally
            {
                if ($prepared) $this->_restore_tool_runtime();
            }
        }
        catch (Exception_Erp804_Mcp $e)
        {
            $this->_output(
                Service_Erp804_Server::error(NULL, $e->rpc_code(), $e->getMessage(), array('reason' => $e->reason())),
                ($e->reason() === 'unauthorized') ? 401 : 400
            );
        }
        catch (Throwable $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp804: сбой транспорта — :error', array(':error' => $e->getMessage()));

            $this->_output(
                Service_Erp804_Server::error(NULL, -32603, 'Внутренняя ошибка сервера', array('reason' => 'internal')),
                500
            );
        }
    }

    /** @return string full или discovery; переопределяется компактным endpoint. */
    protected function _server_mode()
    {
        return 'full';
    }

    /** @return string */
    protected function _endpoint_path()
    {
        return '/mcp';
    }

    /** @return array Эффективные ограничения конкретного endpoint. */
    protected function _effective_key(array $key)
    {
        return $key;
    }

    /** @return void Подготовить соединение для выполнения tools. */
    protected function _prepare_tool_runtime() {}

    /** @return void Вернуть исходное соединение после tools. */
    protected function _restore_tool_runtime() {}

    /**
     * Ключ агента из заголовков или строки запроса.
     *
     * @return  string
     */
    protected function _token()
    {
        $header = (string) $this->request->headers('Authorization');

        if ($header !== '' AND stripos($header, 'bearer ') === 0)
        {
            return trim(substr($header, 7));
        }

        foreach (array('X-Mcp-Key', 'X-Api-Key') as $name)
        {
            $value = (string) $this->request->headers($name);

            if ($value !== '')
            {
                return trim($value);
            }
        }

        return trim((string) $this->request->query('key'));
    }

    /**
     * Тело запроса.
     *
     * @return  string
     */
    protected function _body()
    {
        $body = (string) $this->request->body();

        if (trim($body) === '')
        {
            $body = (string) @file_get_contents('php://input');
        }

        return $body;
    }

    /**
     * Ответ JSON-RPC.
     *
     * @param   array   $data
     * @param   int     $code
     * @return  void
     */
    protected function _output(array $data, $code = 200)
    {
        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->headers('Access-Control-Allow-Origin', '*')
            ->headers('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Mcp-Key')
            ->headers('Cache-Control', 'no-store')
            ->body(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
