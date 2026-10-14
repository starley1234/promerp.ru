<?php defined('SYSPATH') or die('No direct script access.');

abstract class Model_Erp111_Migration {

    /**
     * @var string Группа БД. Мы используем protected с подчеркиванием, 
     * как это принято в Kohana/Koseven.
     */
    protected $_db_group;

    public function __construct()
    {
        // Если в дочернем классе группа не определена (равна NULL)
        if ($this->_db_group === NULL)
        {
            $this->_db_group = Kohana::$config->load('erp111_migrations_postgresql.db_group');
        }
        
        // Если и в конфиге пусто — дефолт
        if (empty($this->_db_group))
        {
            $this->_db_group = Database::$default;
        }
    }

    /**
     * Группа подключения, в которой работает миграция.
     *
     * @return string
     */
    public function db_group()
    {
        return $this->_db_group;
    }

    abstract public function up();
    abstract public function down();

    /**
     * Универсальный метод для выполнения запросов.
     * Мы передаем $type по умолчанию как Database::UPDATE для DDL/DML запросов.
     */
    protected function run_query($sql, $type = Database::UPDATE)
    {
        return DB::query($type, $sql)->execute($this->_db_group);
    }

    // Вспомогательный метод для SELECT, если понадобится
    protected function select_query($sql)
    {
        return DB::query(Database::SELECT, $sql)->execute($this->_db_group);
    }
	
    /**
     * Создает таблицу в базе данных.
     * Адаптировано под PostgreSQL (убраны специфичные для MySQL обратные кавычки и ENGINE)
     */
    protected function create_table($table_name, array $columns, array $options = array())
    {
        $sql_columns = array();
        $primary_keys = array();

        foreach ($columns as $column_name => $column_def)
        {
            $column_name_quoted = '"' . $column_name . '"';
            $type = strtoupper($column_def['type']);
            
            // Обработка автоинкремента для PostgreSQL
            if (isset($column_def['auto_increment']) && $column_def['auto_increment'])
            {
                $type = ($type === 'BIGINT') ? 'BIGSERIAL' : 'SERIAL';
            }

            $sql = $column_name_quoted . " " . $type;

            if (isset($column_def['length']) && !isset($column_def['auto_increment']))
            {
                $sql .= "(" . $column_def['length'] . ")";
            }

            if (isset($column_def['null']) && $column_def['null'] === FALSE)
            {
                $sql .= " NOT NULL";
            }

            if (isset($column_def['default']))
            {
                if ($column_def['default'] instanceof Database_Expression)
                {
                    $sql .= " DEFAULT " . $column_def['default']->__toString();
                }
                else
                {
                    $sql .= " DEFAULT " . DB::quote($column_def['default']);
                }
            }

            $sql_columns[] = $sql;

            if (isset($column_def['primary_key']) && $column_def['primary_key'])
            {
                $primary_keys[] = $column_name_quoted;
            }
        }

        if (!empty($primary_keys))
        {
            $sql_columns[] = "PRIMARY KEY (" . implode(', ', $primary_keys) . ")";
        }

        $sql = 'CREATE TABLE "' . $table_name . '" (' . implode(", ", $sql_columns) . ');';

        $this->run_query($sql);
    }

    /**
     * Удаляет таблицу из базы данных.
     */
    protected function drop_table($table_name)
    {
        $this->run_query('DROP TABLE IF EXISTS "' . $table_name . '" CASCADE');
    }

    /**
     * Создать обычные индексы, если их ещё нет (идемпотентно).
     *
     * Внешние ключи PostgreSQL не индексирует сам: без индекса по колонке
     * `warehouse_id` каждое соединение со складом и каждое удаление склада
     * читают таблицу целиком. Здесь это объявляется одной строкой на индекс.
     *
     * @param   array  $indexes  таблица => список определений: 'item_id' или 'order_id, status'
     * @return  void
     * @throws  Kohana_Exception  если имя таблицы или колонки выглядит небезопасно
     */
    protected function add_indexes(array $indexes)
    {
        foreach ($indexes as $table => $definitions)
        {
            foreach ((array) $definitions as $definition)
            {
                $columns = array_map('trim', explode(',', (string) $definition));

                $this->assert_identifier($table);

                foreach ($columns as $column)
                {
                    $this->assert_identifier($column);
                }

                $this->run_query(
                    'CREATE INDEX IF NOT EXISTS '.$this->index_name($table, $columns, 'idx')
                    .' ON '.$table.' ('.implode(', ', $columns).')'
                );
            }
        }
    }

    /**
     * Триграммные индексы для поиска «содержит» (`LIKE '%текст%'`).
     *
     * Обычный индекс по `search_text` работает только для поиска «начинается с»,
     * а списки ищут по любой части строки — то есть читают таблицу целиком.
     * GIN по триграммам (расширение `pg_trgm`) ускоряет и `LIKE`, и `ILIKE`.
     *
     * Расширение входит в contrib. Если его нет или нет прав его создать, миграция
     * не падает: поиск остаётся рабочим, просто без ускорения — об этом пишется
     * предупреждение в журнал.
     *
     * @param   array  $columns  таблица => колонка (или список колонок)
     * @return  boolean  TRUE, если индексы созданы
     */
    protected function add_trigram_indexes(array $columns)
    {
        if ( ! $this->ensure_extension('pg_trgm'))
        {
            return FALSE;
        }

        foreach ($columns as $table => $list)
        {
            $this->assert_identifier($table);

            foreach ((array) $list as $column)
            {
                $this->assert_identifier($column);

                $this->run_query(
                    'CREATE INDEX IF NOT EXISTS '.$this->index_name($table, array($column), 'trgm')
                    .' ON '.$table.' USING gin ('.$column.' gin_trgm_ops)'
                );
            }
        }

        return TRUE;
    }

    /**
     * Удалить индексы, созданные add_indexes() и add_trigram_indexes() (для down()).
     *
     * @param   array  $indexes  те же определения, что передавались в add_indexes()
     * @param   array  $trigram  те же определения, что передавались в add_trigram_indexes()
     * @return  void
     */
    protected function drop_added_indexes(array $indexes, array $trigram = array())
    {
        foreach ($indexes as $table => $definitions)
        {
            foreach ((array) $definitions as $definition)
            {
                $columns = array_map('trim', explode(',', (string) $definition));
                $this->run_query('DROP INDEX IF EXISTS '.$this->index_name($table, $columns, 'idx'));
            }
        }

        foreach ($trigram as $table => $list)
        {
            foreach ((array) $list as $column)
            {
                $this->run_query('DROP INDEX IF EXISTS '.$this->index_name($table, array($column), 'trgm'));
            }
        }
    }

    /**
     * Включить расширение PostgreSQL, не роняя миграцию, если это невозможно.
     *
     * @param   string  $name  имя расширения (pg_trgm)
     * @return  boolean
     */
    protected function ensure_extension($name)
    {
        $this->assert_identifier($name);

        try
        {
            $installed = $this->select_query("SELECT 1 AS ok FROM pg_extension WHERE extname = '".$name."'")->current();

            if ($installed)
            {
                return TRUE;
            }

            $available = $this->select_query("SELECT 1 AS ok FROM pg_available_extensions WHERE name = '".$name."'")->current();

            if ( ! $available)
            {
                throw new Kohana_Exception('расширение не установлено на сервере PostgreSQL');
            }

            $this->run_query('CREATE EXTENSION IF NOT EXISTS '.$name);

            return TRUE;
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'Миграция '.get_class($this).': расширение :name недоступно (:reason), индексы поиска пропущены', array(
                ':name'   => $name,
                ':reason' => $e->getMessage(),
            ));

            return FALSE;
        }
    }

    /**
     * Имя индекса не длиннее 63 символов (предел PostgreSQL).
     *
     * @param   string  $table
     * @param   array   $columns
     * @param   string  $suffix
     * @return  string
     */
    protected function index_name($table, array $columns, $suffix)
    {
        $name = $table.'_'.implode('_', $columns).'_'.$suffix;

        if (strlen($name) > 63)
        {
            $name = substr($name, 0, 54).'_'.substr(sha1($name), 0, 8);
        }

        return $name;
    }

    /**
     * Имена таблиц и колонок попадают в SQL как есть: только латиница, цифры и «_».
     *
     * @param   string  $identifier
     * @return  void
     * @throws  Kohana_Exception
     */
    protected function assert_identifier($identifier)
    {
        if ( ! preg_match('/^[a-z_][a-z0-9_]*$/', (string) $identifier))
        {
            throw new Kohana_Exception('Недопустимое имя в определении индекса: :name', array(':name' => (string) $identifier));
        }
    }
}
