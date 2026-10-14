<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ключи доступа агентов (erp804_mcp).
 *
 * Ключ — это учётная запись не человека, а программы: LLM-агента, коннектора
 * MCP, внешнего воркера. У ключа есть:
 *
 *  - права (`scopes`) в тех же именах, что права ACL (`erp402_production.manage`);
 *  - признак «только чтение» — такому ключу недоступны изменяющие инструменты;
 *  - срок жизни и признак «включён» — ключ можно отозвать, не удаляя историю
 *    вызовов;
 *  - счётчик вызовов и время последнего использования — видно, кто работает.
 *
 * В базе хранится только SHA-256 от токена: по дампу базы войти нельзя.
 * Сам токен показывается один раз, при выдаче.
 *
 * @package    Erp804
 * @category   Services
 */
class Service_Erp804_Keys
{
    const TABLE = 'erp_804_mcp_keys';

    /** @var string Префикс токена — по нему видно, что это ключ MCP */
    const PREFIX = 'mcp_';

    /** @var Service_Erp804_Keys|null */
    protected static $_instance;

    /** @var array|null Последняя выданная пара (токен доступен один раз) */
    protected $_last_token;

    /**
     * @return  Service_Erp804_Keys
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
    //  Выдача и проверка
    // =========================================================================

    /**
     * Выдать ключ.
     *
     * @param   string  $name
     * @param   array   $scopes     права в именах ACL, можно `erp402_production.*`
     * @param   array   $data       client, actor_id, read_only, expires_at, note, created_by
     * @return  array               token (показывается один раз), key (строка из БД)
     */
    public function issue($name, array $scopes, array $data = array())
    {
        $name = trim((string) $name);

        if ($name === '')
        {
            throw new Exception_Erp804_Mcp('Укажите имя ключа', NULL, 'invalid_arguments');
        }

        if ( ! $scopes)
        {
            throw new Exception_Erp804_Mcp('Укажите хотя бы одно право (scope) для ключа', NULL, 'invalid_arguments');
        }

        $token = self::PREFIX.bin2hex(random_bytes(20));

        // Конструктор запросов Konine возвращает [вставленный id, строк], а не id
        $result = DB::insert(self::TABLE, array(
                'name', 'token_hash', 'token_prefix', 'client', 'actor_id', 'scopes',
                'read_only', 'expires_at', 'note', 'created_by', 'created_at',
            ))
            ->values(array(
                $name,
                $this->hash($token),
                substr($token, 0, 12),
                isset($data['client']) ? (string) $data['client'] : NULL,
                ! empty($data['actor_id']) ? (int) $data['actor_id'] : NULL,
                $this->to_pg_array($scopes),
                ! empty($data['read_only']),
                ! empty($data['expires_at']) ? (string) $data['expires_at'] : NULL,
                isset($data['note']) ? (string) $data['note'] : NULL,
                ! empty($data['created_by']) ? (int) $data['created_by'] : NULL,
                date('c'),
            ))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        $this->_last_token = $token;

        return array(
            'token' => $token,
            'key'   => $this->find($id),
        );
    }

    /**
     * Токен, выданный последним вызовом issue() — показывается один раз.
     *
     * @return  string|null
     */
    public function last_token()
    {
        return $this->_last_token;
    }

    /**
     * Проверить токен и отметить использование.
     *
     * @param   string  $token
     * @return  array|null
     * @throws  Exception_Erp804_Mcp
     */
    public function authenticate($token)
    {
        $token = trim((string) $token);

        if ($token === '')
        {
            throw new Exception_Erp804_Mcp('Не передан ключ доступа (Authorization: Bearer …)', NULL, 'unauthorized');
        }

        $key = DB::select()
            ->from(self::TABLE)
            ->where('token_hash', '=', $this->hash($token))
            ->limit(1)
            ->execute()
            ->current();

        if ( ! $key)
        {
            throw new Exception_Erp804_Mcp('Ключ не найден', NULL, 'unauthorized');
        }

        $key['enabled']   = Service_Erp804_Cast::boolean($key['enabled']);
        $key['read_only'] = Service_Erp804_Cast::boolean($key['read_only']);

        if ( ! $key['enabled'])
        {
            throw new Exception_Erp804_Mcp('Ключ отозван', NULL, 'unauthorized');
        }

        if ($key['expires_at'] AND strtotime($key['expires_at']) < time())
        {
            throw new Exception_Erp804_Mcp('Срок действия ключа истёк', NULL, 'unauthorized');
        }

        DB::update(self::TABLE)
            ->set(array(
                'last_used_at' => DB::expr('now()'),
                'calls_count'  => DB::expr('calls_count + 1'),
            ))
            ->where('id', '=', (int) $key['id'])
            ->execute();

        $key['scopes'] = $this->scopes($key);

        return $key;
    }

    // =========================================================================
    //  Права
    // =========================================================================

    /**
     * Права ключа массивом.
     *
     * PostgreSQL отдаёт `TEXT[]` строкой вида `{a,b}` — разбираем.
     *
     * @param   array   $key
     * @return  array
     */
    public function scopes(array $key)
    {
        $scopes = isset($key['scopes']) ? $key['scopes'] : array();

        if (is_array($scopes))
        {
            return array_values(array_filter($scopes, 'strlen'));
        }

        $scopes = trim((string) $scopes, '{}');

        if ($scopes === '')
        {
            return array();
        }

        return array_values(array_filter(str_getcsv($scopes, ',', '"', '\\'), 'strlen'));
    }

    /**
     * Разрешено ли ключу право.
     *
     * Поддерживаются точные права (`erp402_production.manage`), префиксные
     * (`erp402_production.*`) и «всё» (`*`).
     *
     * @param   array   $key
     * @param   string  $right
     * @return  bool
     */
    public function allows(array $key, $right)
    {
        $right = (string) $right;

        if ($right === '')
        {
            return TRUE;
        }

        foreach ($this->scopes($key) as $scope)
        {
            if ($scope === '*' OR $scope === $right)
            {
                return TRUE;
            }

            if (substr($scope, -2) === '.*' AND strpos($right, substr($scope, 0, -1)) === 0)
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    // =========================================================================
    //  Чтение и управление
    // =========================================================================

    /**
     * Найти ключ по идентификатору, uuid или хешу токена.
     *
     * @param   int|string  $id_or_uuid
     * @return  array|null
     */
    public function find($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        $query = DB::select()->from(self::TABLE)->limit(1);

        if (strlen((string) $id_or_uuid) === 64)
        {
            // Полный токен ключа ищем по хешу: в базе лежит только он.
            $query->where('token_hash', '=', (string) $id_or_uuid);
        }
        else
        {
            $ref = Erp::id_or_uuid($id_or_uuid);

            if ($ref === NULL)
            {
                return NULL;
            }

            $query->where($ref[0], '=', $ref[1]);
        }

        $key = $query->execute()->current();

        if ( ! $key)
        {
            return NULL;
        }

        return $this->prepare($key);
    }

    /**
     * Список ключей.
     *
     * @param   array   $filters    enabled, client, term
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   rows, total, limit, offset
     */
    public function search(array $filters = array(), $limit = 20, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $build = function ($query) use ($filters) {
            if (isset($filters['enabled']) AND $filters['enabled'] !== '')
            {
                $query->where('enabled', '=', (bool) $filters['enabled']);
            }

            if ( ! empty($filters['client']))
            {
                $query->where('client', '=', (string) $filters['client']);
            }

            if ( ! empty($filters['term']))
            {
                $query->where('name', 'ILIKE', '%'.$filters['term'].'%');
            }

            return $query;
        };

        $total = (int) $build(DB::select(array(DB::expr('COUNT(*)'), 'total'))->from(self::TABLE))
            ->execute()
            ->get('total', 0);

        $rows = $build(DB::select()->from(self::TABLE))
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->execute()
            ->as_array();

        foreach ($rows as $index => $row)
        {
            unset($row['token_hash']);
            $rows[$index] = $this->prepare($row);
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
     * Изменить ключ: права, запрет изменений, срок, включённость.
     *
     * @param   int|string  $id
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  array
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $key = $this->find($id);

        if ( ! $key)
        {
            throw new Exception_Erp804_Mcp('Ключ не найден', NULL, 'invalid_arguments');
        }

        $set = array();

        if (array_key_exists('scopes', $data) AND is_array($data['scopes']))
        {
            $set['scopes'] = $this->to_pg_array($data['scopes']);
        }

        if (array_key_exists('read_only', $data))
        {
            $set['read_only'] = (bool) $data['read_only'];
        }

        if (array_key_exists('enabled', $data))
        {
            $set['enabled'] = (bool) $data['enabled'];
        }

        if (array_key_exists('expires_at', $data))
        {
            $set['expires_at'] = $data['expires_at'] ? (string) $data['expires_at'] : NULL;
        }

        if (array_key_exists('note', $data))
        {
            $set['note'] = $data['note'] !== NULL ? (string) $data['note'] : NULL;
        }

        if ( ! $set)
        {
            throw new Exception_Erp804_Mcp('Нечего изменять', NULL, 'invalid_arguments');
        }

        $set['updated_at'] = date('c');

        DB::update(self::TABLE)->set($set)->where('id', '=', (int) $key['id'])->execute();

        $updated = $this->find($key['id']);

        $this->journal('update', $updated, $user_id, array(
            'fields' => array_keys($set),
        ));

        return $updated;
    }

    /**
     * Отозвать ключ (мягко: запись и история вызовов остаются).
     *
     * @param   int|string  $id
     * @param   int|null    $user_id
     * @return  array
     */
    public function revoke($id, $user_id = NULL)
    {
        return $this->update($id, array('enabled' => FALSE), $user_id);
    }

    /**
     * Удалить ключ вместе со ссылками в вызовах (key_id станет NULL).
     *
     * @param   int|string  $id
     * @return  bool
     */
    public function delete($id)
    {
        $key = $this->find($id);

        if ( ! $key)
        {
            return FALSE;
        }

        DB::delete(self::TABLE)->where('id', '=', (int) $key['id'])->execute();

        return TRUE;
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @param   string  $token
     * @return  string
     */
    public function hash($token)
    {
        return hash('sha256', (string) $token);
    }

    /**
     * @param   array   $scopes
     * @return  string  литерал для TEXT[]
     */
    public function to_pg_array(array $scopes)
    {
        $clean = array();

        foreach ($scopes as $scope)
        {
            $scope = trim((string) $scope);

            if ($scope !== '' AND preg_match('/^[A-Za-z0-9_.*:-]+$/', $scope))
            {
                $clean[] = $scope;
            }
        }

        return '{'.implode(',', array_unique($clean)).'}';
    }

    /**
     * Привести строку ключа к видам, удобным PHP: права массивом, булевы
     * поля — настоящими bool (PostgreSQL отдаёт `t`/`f` строками).
     *
     * @param   array   $key
     * @return  array
     */
    public function prepare(array $key)
    {
        $key['scopes']    = $this->scopes($key);
        $key['enabled']   = Service_Erp804_Cast::boolean(isset($key['enabled']) ? $key['enabled'] : FALSE);
        $key['read_only'] = Service_Erp804_Cast::boolean(isset($key['read_only']) ? $key['read_only'] : FALSE);

        return $key;
    }

    /**
     * Запись об изменении ключа — в журнал действий (erp119).
     *
     * @param   string      $action
     * @param   array       $key
     * @param   int|null    $user_id
     * @param   array       $extra
     * @return  void
     */
    protected function journal($action, array $key, $user_id = NULL, array $extra = array())
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            $before = array();
            $after  = array(
                'name'   => $key['name'],
                'scopes' => $this->scopes($key),
            ) + $extra;

            Service_Erp119_Journal::instance()->change(
                'mcp_key',
                isset($key['uuid']) ? $key['uuid'] : (string) $key['id'],
                $action,
                $before,
                $after,
                array(
                    'module'      => 'erp804',
                    'entity_label'=> 'Ключ агента '.$key['name'],
                    'user_id'     => $user_id,
                    'severity'    => 'warning',
                )
            );
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp804: журнал ключа недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
