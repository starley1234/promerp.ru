<?php defined('SYSPATH') OR die('No direct script access.');

class Database_PostgreSQL extends Database_PDO {

    protected $_identifier = '"';
	
	// Кэш структур таблиц в рамках выполнения одного скрипта
    protected static $_table_metadata = [];

    /**
     * Основной метод выполнения запросов.
     * Перехватывает INSERT и обрабатывает его вручную, чтобы избежать вызова lastInsertId()
     */
    public function query($type, $sql, $as_object = FALSE, ?array $params = NULL)
    {
        if ($type === Database::INSERT && stripos($sql, 'RETURNING') === FALSE)
        {
            if (preg_match('/INSERT\s+INTO\s+(["\w\._-]+)/i', $sql, $matches))
            {
                $table = trim($matches[1], '"');
                
                // Проверяем, знаем ли мы уже первичный ключ этой таблицы
                if ( ! isset(self::$_table_metadata[$table]))
                {
                    $columns = $this->list_columns($table);
                    foreach ($columns as $name => $info)
                    {
                        if (!empty($info['primary']))
                        {
                            self::$_table_metadata[$table] = $name;
                            break;
                        }
                    }
                }

                // Если нашли PK — добавляем RETURNING
                if (isset(self::$_table_metadata[$table]))
                {
                    $sql .= ' RETURNING ' . $this->quote_column(self::$_table_metadata[$table]);
                }
            }

            // Прямое выполнение через PDO (быстрее, чем через обертку, т.к. меньше вложенности)
            $result = $this->_connection->query($sql);
            $row = $result->fetch(PDO::FETCH_ASSOC);
            return [$row ? reset($row) : NULL, $result->rowCount()];
        }

        return parent::query($type, $sql, $as_object, $params);
    }

    /**
     * Стандартный метод вставки (используется Query Builder-ом)
     */
    public function insert($table, array $columns, array $values)
    {
        $sql = 'INSERT INTO '.$this->quote_table($table).' ('.implode(', ', array_map([$this, 'quote_column'], $columns)).') VALUES ('.implode(', ', $values).')';
        
        // Передаем выполнение в наш "умный" query
        return $this->query(Database::INSERT, $sql);
    }

    /**
     * Список колонок (вызывается родителем и ORM)
     */
    public function list_columns($table, $like = NULL, $add_prefix = TRUE)
    {
        if ($add_prefix === TRUE)
        {
            $table = $this->_config['table_prefix'].$table;
        }

        $sql = "SELECT column_name, column_default, is_nullable, data_type, 
                (SELECT count(*) FROM information_schema.key_column_usage 
                 WHERE table_name = c.table_name AND column_name = c.column_name 
                 AND constraint_name LIKE '%_pkey') as is_primary
                FROM information_schema.columns c
                WHERE table_name = ".$this->quote($table)."
                AND table_schema = 'public' 
                ORDER BY ordinal_position";

        $result = [];
        // ВАЖНО: вызываем родительский query как SELECT, чтобы не зациклиться
        $rows = parent::query(Database::SELECT, $sql, FALSE);
        
        foreach ($rows as $row)
        {
            $result[$row['column_name']] = [
                'column_name'    => $row['column_name'],
                'primary'        => ($row['is_primary'] > 0),
                'type'           => $this->_parse_type($row['data_type']),
                'column_default' => $row['column_default'],
                'is_nullable'    => ($row['is_nullable'] === 'YES'),
            ];
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
}