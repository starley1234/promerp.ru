<?php defined('SYSPATH') OR die('No direct script access.');

abstract class Kohana_Database_PostgreSQL extends Database
{
    protected $_version;
    private static $_dsn_store = array();
    protected static $_pk_cache = array(); // Кэш имен ключей для таблиц

    public function __construct($name, $config)
    {
        parent::__construct($name, $config);

        if (empty($this->_config['connection']['info']))
        {
            $info = '';
            extract($this->_config['connection']);
            if ( ! empty($hostname)) $info .= "host='$hostname'";
            if ( ! empty($port))     $info .= " port='$port'";
            if ( ! empty($username)) $info .= " user='$username'";
            if ( ! empty($password)) $info .= " password='$password'";
            if ( ! empty($database)) $info .= " dbname='$database'";
            $dsn = $info;
        }
        else
        {
            $dsn = $this->_config['connection']['info'];
        }

        self::$_dsn_store[$this->_instance] = $dsn;
        $this->_config['connection'] = array(
            'info'       => '[REDACTED]',
            'persistent' => ! empty($this->_config['connection']['persistent']),
        );
    }

    public function connect()
    {
        if ($this->_connection) return;
        $dsn = self::$_dsn_store[$this->_instance];
        try {
            $this->_connection = empty($this->_config['connection']['persistent'])
                ? pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW)
                : pg_pconnect($dsn, PGSQL_CONNECT_FORCE_NEW);
        } catch (ErrorException $e) {
            throw new Database_Exception('Database connection failed', NULL, 0);
        }
        if ( ! $this->_connection)
            throw new Database_Exception('Unable to connect to PostgreSQL ":name"', array(':name' => $this->_instance));

        // `schema` is not only introspection metadata: every unqualified query
        // must actually use it. This also allows isolated demo/test schemas in
        // the same physical database without leaking into `public`.
        $schemas = isset($this->_config['search_path'])
            ? (array) $this->_config['search_path']
            : array(isset($this->_config['schema']) ? (string) $this->_config['schema'] : 'public');
        $quoted_schemas = array();
        foreach ($schemas as $schema)
        {
            if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema))
            {
                throw new Database_Exception('Invalid PostgreSQL schema for connection ":name"', array(':name' => $this->_instance));
            }
            $quoted_schemas[] = '"'.str_replace('"', '""', $schema).'"';
        }
        if ( ! @pg_query($this->_connection, 'SET search_path TO '.implode(', ', $quoted_schemas)))
        {
            throw new Database_Exception('Unable to select PostgreSQL schema for connection ":name"', array(':name' => $this->_instance));
        }
    }

    public function disconnect()
    {
        $status = FALSE;
        if ($this->_connection) {
            if ($status = pg_close($this->_connection)) {
                $this->_connection = NULL;
            }
        }
        return $status;
    }

    protected function _command($sql)
    {
        $this->_connection OR $this->connect();
        $result = pg_query($this->_connection, $sql);
        return (pg_result_status($result) === PGSQL_COMMAND_OK);
    }

    public function begin($mode = NULL) { return $this->_command("BEGIN $mode"); }
    public function commit() { return $this->_command('COMMIT'); }
    public function rollback() { return $this->_command('ROLLBACK'); }

    public function query($type, $sql, $as_object = FALSE, ?array $params = NULL)
    {
        $this->_connection OR $this->connect();
        $format = (Kohana::$environment === Kohana::PRODUCTION) ? ':error' : ':error [ :query ]';

        if (Kohana::$profiling) $benchmark = Profiler::start("Database ({$this->_instance})", $sql);

        // 1. Умное определение RETURNING
        if ($type === Database::INSERT && stripos($sql, 'RETURNING') === FALSE)
        {
            if (preg_match('/INSERT\s+INTO\s+(["\w\._-]+)/i', $sql, $matches))
            {
                $table = trim($matches[1], '"');
                
                // Сначала ищем в статическом кэше (уже определенные ключи)
                if ( ! isset(self::$_pk_cache[$table])) 
                {
                    $this->list_columns($table); // Это наполнит self::$_pk_cache[$table]
                }

                $pk = self::$_pk_cache[$table] ?? $this->_config['primary_key'] ?? NULL;

                if ($pk) 
                {
                    $sql .= ' RETURNING ' . $this->quote_identifier($pk);
                }
            }
        }

        $result = @pg_query($this->_connection, $sql);

        if ( ! $result) {
            if (isset($benchmark)) Profiler::delete($benchmark);
            throw new Database_Exception($format, array(':error' => pg_last_error($this->_connection), ':query' => $sql));
        }

        $status = pg_result_status($result);
        $rows = ($status === PGSQL_TUPLES_OK) ? pg_num_rows($result) : pg_affected_rows($result);

        if (isset($benchmark)) Profiler::stop($benchmark);
        $this->last_query = $sql;

        if ($type === Database::SELECT)
            return new Database_PostgreSQL_Result($result, $sql, $as_object, $params, $rows);

        if ($type === Database::INSERT) {
            // Если был RETURNING, берем ID из первой строки результата
            $insert_id = ($status === PGSQL_TUPLES_OK && $rows > 0) ? pg_fetch_result($result, 0, 0) : NULL;
            return array($insert_id, $rows);
        }

        return $rows;
    }

    public function list_columns($table, $like = NULL, $add_prefix = TRUE)
    {
        $this->_connection OR $this->connect();
        $raw_table = $add_prefix ? ($this->table_prefix().$table) : $table;
        
        // Более мощный запрос: ищет первичный ключ через системные индексы (100% точность)
        $sql = "SELECT c.column_name, c.column_default, c.is_nullable, c.data_type,
            (SELECT count(*) FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)			 
			 WHERE i.indrelid = '".pg_escape_string($this->_connection, $raw_table)."'::regclass
			 
             AND i.indisprimary AND a.attname = c.column_name) as is_primary
            FROM information_schema.columns c
            WHERE c.table_schema = ".$this->quote($this->schema())."
            AND c.table_name = ".$this->quote($raw_table)."
            ORDER BY c.ordinal_position";

        $result = array();
        // Используем pg_query напрямую здесь, чтобы не триггерить рекурсию в query()
        $res = pg_query($this->_connection, $sql);
        
        while ($row = pg_fetch_assoc($res))
        {
            if ($row['is_primary'] > 0) 
            {
                self::$_pk_cache[$table] = $row['column_name'];
            }
            
            $result[$row['column_name']] = array(
                'column_name'    => $row['column_name'],
                'primary'        => ($row['is_primary'] > 0),
                'type'           => $this->_parse_type($row['data_type']),
                'column_default' => $row['column_default'],
                'is_nullable'    => ($row['is_nullable'] === 'YES'),
            );
        }
        return $result;
    }

    protected function _parse_type($type)
    {
        if (strpos($type, 'int') !== FALSE) return 'int';
        if (strpos($type, 'bool') !== FALSE) return 'bool';
        if (strpos($type, 'float') !== FALSE || strpos($type, 'numeric') !== FALSE) return 'float';
        return 'string';
    }

    public function list_tables($like = NULL)
    {
        $this->_connection OR $this->connect();
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = ".$this->quote($this->schema());
        $res = pg_query($this->_connection, $sql);
        $result = array();
        while ($row = pg_fetch_assoc($res)) $result[] = $row['table_name'];
        return $result;
    }

    public function escape($value)
    {
        $this->_connection OR $this->connect();
        return "'".pg_escape_string($this->_connection, $value)."'";
    }

    public function schema() { return $this->_config['schema'] ?? 'public'; }
    public function set_charset($charset) { pg_set_client_encoding($this->_connection, $charset); }
}