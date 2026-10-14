<?php defined('SYSPATH') or die('No direct script access.');

/**
 * `bin/console mcp:serve` — MCP-сервер по stdio.
 *
 * Транспорт для локальных клиентов (Claude Desktop, Cursor, Continue и др.):
 * клиент запускает процесс и общается с ним строками JSON-RPC через
 * стандартный ввод/вывод. В отличие от HTTP-транспорта, здесь нет заголовков,
 * поэтому ключ передаётся аргументом `--key=` (или берётся из переменной
 * окружения `MCP_KEY`).
 *
 *   bin/console mcp:serve --key=mcp_…
 *   bin/console mcp:serve --info          # показать инструменты и выйти
 *
 * В stdout уходит только JSON — отладочные сообщения пишутся в stderr.
 *
 * @package    Erp804
 * @category   Tasks
 */
class Task_Mcp_Serve extends Minion_Task
{
    protected $_options = array(
        'key'  => NULL,
        'info' => FALSE,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $server = Service_Erp804_Server::instance();

        if ($this->_flag($options, 'info'))
        {
            fwrite(STDOUT, json_encode($server->info(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL);

            return;
        }

        $token = $this->_value($options, 'key', getenv('MCP_KEY'));

        if ( ! $token)
        {
            fwrite(STDERR, 'Укажите ключ агента: --key=<токен> или переменную окружения MCP_KEY'.PHP_EOL);

            return;
        }

        $key = NULL;

        try
        {
            $key = $server->authenticate($token);

            fwrite(STDERR, 'Ключ «'.$key['name'].'» принят: '.count(Service_Erp804_Registry::instance()->for_key($key)).' инструментов'.PHP_EOL);
        }
        catch (Exception_Erp804_Mcp $e)
        {
            fwrite(STDERR, 'Ключ не принят: '.$e->getMessage().PHP_EOL);

            return;
        }

        while (($line = fgets(STDIN)) !== FALSE)
        {
            $line = trim($line);

            if ($line === '')
            {
                continue;
            }

            $message = json_decode($line, TRUE);

            if ( ! is_array($message))
            {
                fwrite(STDOUT, json_encode(
                    Service_Erp804_Server::error(NULL, -32700, 'Не разобрал строку как JSON'),
                    JSON_UNESCAPED_UNICODE
                ).PHP_EOL);

                continue;
            }

            $answer = $server->handle_payload($message, $key);

            if ($answer !== NULL)
            {
                fwrite(STDOUT, json_encode($answer, JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
        }
    }
}
