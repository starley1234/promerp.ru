<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Журнал вызовов инструментов (erp804_mcp).
 *
 * Каждый вызов `tools/call` попадает в `erp_804_mcp_calls` — это ответ на
 * вопрос «что делал агент»: инструмент, аргументы, результат, длительность,
 * ключ и клиент. Изменяющие вызовы дополнительно уходят в журнал действий
 * `erp119` (`entity_type = mcp_call`), чтобы попадать в общую историю.
 *
 * Инструменты, помеченные `approval`, не выполняются сразу: вызов остаётся
 * в статусе `awaiting_approval` и ждёт решения человека в интерфейсе
 * `/mcp` (право `erp804_mcp.approve`). Только после одобрения сервер
 * выполняет обработчик и записывает результат.
 *
 * @package    Erp804
 * @category   Services
 */
class Service_Erp804_Calls
{
    const TABLE = 'erp_804_mcp_calls';

    /** @var Service_Erp804_Calls|null */
    protected static $_instance;

    /**
     * @return  Service_Erp804_Calls
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Начать вызов: создать запись в журнале вызовов.
     *
     * @param   string  $tool
     * @param   array   $key     ключ агента (или NULL для CLI/отладки)
     * @param   array   $args
     * @param   array   $meta    mutating, idempotency_key, ip
     * @return  array            строка вызова
     */
    public function start($tool, ?array $key = NULL, array $args = array(), array $meta = array())
    {
        $idempotency = ! empty($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : NULL;

        if ($idempotency)
        {
            $existing = DB::select()
                ->from(self::TABLE)
                ->where('idempotency_key', '=', $idempotency)
                ->limit(1)
                ->execute()
                ->current();

            if ($existing)
            {
                return $this->decode($existing);
            }
        }

        // Конструктор запросов Konine возвращает [вставленный id, строк], а не id
        $result = DB::insert(self::TABLE, array(
                'key_id', 'key_name', 'tool_name', 'arguments', 'status', 'mutating',
                'idempotency_key', 'ip', 'created_at',
            ))
            ->values(array(
                ($key AND ! empty($key['id'])) ? (int) $key['id'] : NULL,
                ($key AND isset($key['name'])) ? (string) $key['name'] : NULL,
                (string) $tool,
                json_encode($args, JSON_UNESCAPED_UNICODE),
                'pending',
                ! empty($meta['mutating']),
                $idempotency,
                isset($meta['ip']) ? (string) $meta['ip'] : NULL,
                date('c'),
            ))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        return $this->find($id);
    }

    /**
     * Завершить вызов успехом или ошибкой.
     *
     * @param   int         $call_id
     * @param   array|null  $result
     * @param   string|null $error
     * @param   string|null $reason
     * @param   int|null    $duration_ms
     * @return  array
     */
    public function finish($call_id, ?array $result = NULL, $error = NULL, $reason = NULL, $duration_ms = NULL)
    {
        DB::update(self::TABLE)
            ->set(array(
                'status'      => $error === NULL ? 'ok' : 'error',
                'result'      => $result !== NULL ? json_encode($result, JSON_UNESCAPED_UNICODE) : NULL,
                'error'       => $error,
                'reason'      => $reason,
                'duration_ms' => $duration_ms !== NULL ? (int) $duration_ms : NULL,
                'finished_at' => date('c'),
            ))
            ->where('id', '=', (int) $call_id)
            ->execute();

        return $this->find($call_id);
    }

    /**
     * Инструмент требует подтверждения человека.
     *
     * @param   int     $call_id
     * @param   string  $message
     * @return  array
     */
    public function await_approval($call_id, $message = NULL)
    {
        DB::update(self::TABLE)
            ->set(array(
                'status' => 'awaiting_approval',
                'error'  => $message,
            ))
            ->where('id', '=', (int) $call_id)
            ->execute();

        return $this->find($call_id);
    }

    /**
     * Одобрить и выполнить вызов.
     *
     * @param   int         $call_id
     * @param   int|null    $user_id
     * @return  array       обновлённая строка вызова
     * @throws  Exception_Erp804_Mcp
     */
    public function approve($call_id, $user_id = NULL)
    {
        $call = $this->find($call_id);

        if ( ! $call)
        {
            throw new Exception_Erp804_Mcp('Вызов не найден', NULL, 'invalid_arguments');
        }

        if ($call['status'] !== 'awaiting_approval')
        {
            throw new Exception_Erp804_Mcp(
                'Одобрять можно только вызовы, ожидающие подтверждения (сейчас — :status)',
                array(':status' => $call['status']),
                'invalid_arguments'
            );
        }

        DB::update(self::TABLE)
            ->set(array('status' => 'approved', 'approved_by' => $user_id ? (int) $user_id : NULL, 'approved_at' => date('c')))
            ->where('id', '=', (int) $call['id'])
            ->execute();

        $call = $this->find($call['id']);

        $started = microtime(TRUE);

        try
        {
            $data = Service_Erp804_Server::instance()->execute(
                $call['tool_name'],
                is_array($call['arguments']) ? $call['arguments'] : json_decode((string) $call['arguments'], TRUE),
                $call
            );

            return $this->finish($call['id'], $data, NULL, NULL, (int) round((microtime(TRUE) - $started) * 1000));
        }
        catch (Exception_Erp804_Mcp $e)
        {
            return $this->finish($call['id'], NULL, $e->getMessage(), $e->reason(), (int) round((microtime(TRUE) - $started) * 1000));
        }
        catch (Exception $e)
        {
            return $this->finish($call['id'], NULL, $e->getMessage(), 'failed', (int) round((microtime(TRUE) - $started) * 1000));
        }
    }

    /**
     * Отклонить вызов.
     *
     * @param   int         $call_id
     * @param   int|null    $user_id
     * @param   string|null $reason
     * @return  array
     */
    public function reject($call_id, $user_id = NULL, $reason = NULL)
    {
        $call = $this->find($call_id);

        if ( ! $call)
        {
            throw new Exception_Erp804_Mcp('Вызов не найден', NULL, 'invalid_arguments');
        }

        if ($call['status'] !== 'awaiting_approval')
        {
            throw new Exception_Erp804_Mcp(
                'Отклонить можно только вызов, ожидающий подтверждения (сейчас — :status)',
                array(':status' => $call['status']),
                'invalid_arguments'
            );
        }

        DB::update(self::TABLE)
            ->set(array(
                'status'           => 'rejected',
                'approved_by'      => $user_id ? (int) $user_id : NULL,
                'approved_at'      => date('c'),
                'rejection_reason' => $reason !== NULL ? (string) $reason : NULL,
                'finished_at'      => date('c'),
            ))
            ->where('id', '=', (int) $call['id'])
            ->execute();

        return $this->find($call['id']);
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * @param   int|string  $id_or_uuid
     * @return  array|null
     */
    public function find($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $query = DB::select()->from(self::TABLE)->limit(1);
        $query->where($ref[0], '=', $ref[1]);

        $call = $query->execute()->current();

        return $this->decode($call);
    }

    /**
     * Список вызовов.
     *
     * @param   array   $filters    tool_name, status, key_id, idempotency_key, term, mutating
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset, pages
     */
    public function search(array $filters = array(), $limit = 20, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $build = function ($query) use ($filters) {
            if ( ! empty($filters['tool_name']))
            {
                $query->where('tool_name', '=', (string) $filters['tool_name']);
            }

            if ( ! empty($filters['status']))
            {
                $query->where('status', '=', (string) $filters['status']);
            }

            if ( ! empty($filters['key_id']))
            {
                $query->where('key_id', '=', (int) $filters['key_id']);
            }

            if ( ! empty($filters['idempotency_key']))
            {
                $query->where('idempotency_key', '=', (string) $filters['idempotency_key']);
            }

            if (isset($filters['mutating']) AND $filters['mutating'] !== '')
            {
                $query->where('mutating', '=', (bool) $filters['mutating']);
            }

            if ( ! empty($filters['term']))
            {
                $query->where('tool_name', 'ILIKE', '%'.$filters['term'].'%');
            }

            return $query;
        };

        $total = (int) $build(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from(self::TABLE))
            ->execute()
            ->get('total', 0);

        $rows = $build(DB::select()->from(self::TABLE))
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->decode($row);
        }

        return array(
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'pages'  => (int) ceil($total / $limit),
        );
    }

    /**
     * Вызовы, ожидающие подтверждения человека.
     *
     * @param   int     $limit
     * @return  array
     */
    public function pending($limit = 50)
    {
        $rows = DB::select()
            ->from(self::TABLE)
            ->where('status', '=', 'awaiting_approval')
            ->order_by('created_at', 'ASC')
            ->limit($limit)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            $rows[$index] = $this->decode($row);
        }

        return $rows;
    }

    /**
     * Сводка вызовов за период.
     *
     * @param   int     $days
     * @return  array
     */
    public function stats($days = 7)
    {
        $days = max(1, (int) $days);

        $totals = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'ok')"), 'ok'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'error')"), 'error'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'awaiting_approval')"), 'awaiting_approval'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'approved')"), 'approved'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'rejected')"), 'rejected'),
                array(DB::expr("COUNT(*) FILTER (WHERE mutating)"), 'mutating'),
                array(DB::expr('COALESCE(MAX(created_at), now())'), 'last_at')
            )
            ->from(self::TABLE)
            ->where('created_at', '>=', DB::expr("now() - interval '".$days." days'"))
            ->execute()
            ->current();

        $by_tool = DB::select(
                'tool_name',
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'error')"), 'errors')
            )
            ->from(self::TABLE)
            ->where('created_at', '>=', DB::expr("now() - interval '".$days." days'"))
            ->group_by('tool_name')
            ->order_by(DB::expr('COUNT(*)'), 'DESC')
            ->limit(10)
            ->execute()
            ->as_array();

        $by_day = DB::select(
                array(DB::expr("to_char(created_at, 'YYYY-MM-DD')"), 'day'),
                array(DB::expr('COUNT(*)'), 'total')
            )
            ->from(self::TABLE)
            ->where('created_at', '>=', DB::expr("now() - interval '".$days." days'"))
            ->group_by(DB::expr("to_char(created_at, 'YYYY-MM-DD')"))
            ->order_by('day', 'ASC')
            ->execute()
            ->as_array();

        $by_key = DB::select(
                'key_id',
                'key_name',
                array(DB::expr('COUNT(*)'), 'total')
            )
            ->from(self::TABLE)
            ->where('created_at', '>=', DB::expr("now() - interval '".$days." days'"))
            ->group_by('key_id')
            ->group_by('key_name')
            ->order_by(DB::expr('COUNT(*)'), 'DESC')
            ->limit(10)
            ->execute()
            ->as_array();

        return array(
            'days'    => $days,
            'total'   => (int) $totals['total'],
            'ok'      => (int) $totals['ok'],
            'error'   => (int) $totals['error'],
            'awaiting_approval' => (int) $totals['awaiting_approval'],
            'approved'=> (int) $totals['approved'],
            'rejected'=> (int) $totals['rejected'],
            'mutating'=> (int) $totals['mutating'],
            'last_at' => $totals['last_at'],
            'by_tool' => $by_tool,
            'by_day'  => $by_day,
            'by_key'  => $by_key,
        );
    }

    /**
     * Запись об изменяющем вызове — в журнал действий (erp119).
     *
     * @param   array   $call
     * @param   string  $action
     * @param   int|null $user_id
     * @return  void
     */
    public function journal(array $call, $action = 'execute', $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change(
                'mcp_call',
                $call['uuid'],
                $action,
                array(),
                array(
                    'tool'   => $call['tool_name'],
                    'key'    => $call['key_name'],
                    'status' => $call['status'],
                    'result' => is_array($call['result']) ? $call['result'] : NULL,
                ),
                array(
                    'module'       => 'erp804',
                    'entity_label' => 'MCP: '.$call['tool_name'],
                    'user_id'      => $user_id,
                    'severity'     => 'warning',
                )
            );
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp804: журнал вызова недоступен — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * Привести JSONB-поля к массивам.
     *
     * @param   array|false|null    $row
     * @return  array|null
     */
    protected function decode($row)
    {
        if ( ! $row)
        {
            return NULL;
        }

        foreach (array('arguments', 'result') as $field)
        {
            if (isset($row[$field]) AND is_string($row[$field]))
            {
                $decoded = json_decode($row[$field], TRUE);
                $row[$field] = is_array($decoded) ? $decoded : NULL;
            }
        }

        $row['mutating'] = Service_Erp804_Cast::boolean(isset($row['mutating']) ? $row['mutating'] : FALSE);

        return $row;
    }
}
