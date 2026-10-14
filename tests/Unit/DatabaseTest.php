<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Интеграционные проверки БД. Если подключение недоступно — тесты пропускаются.
 */
class DatabaseTest extends TestCase
{
    protected static function connection()
    {
        try
        {
            $db = \Database::instance();
            $db->connect();
            return $db;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    public function test_connection_is_available()
    {
        $db = self::connection();

        if ($db === NULL)
        {
            $this->markTestSkipped('PostgreSQL недоступна — проверьте .env и docker compose up -d');
        }

        $this->assertNotNull($db);
    }

    public function test_simple_query_works()
    {
        $db = self::connection();

        if ($db === NULL)
        {
            $this->markTestSkipped('PostgreSQL недоступна');
        }

        $result = $db->query(\Database::SELECT, 'SELECT 1 AS one', FALSE);
        $this->assertSame('1', (string) $result->get('one'));
    }

    public function test_core_tables_exist()
    {
        $db = self::connection();

        if ($db === NULL)
        {
            $this->markTestSkipped('PostgreSQL недоступна');
        }

        $missing = [];

        foreach (['users', 'roles', 'roles_users', 'migrations_pg'] as $table)
        {
            $exists = $db->query(\Database::SELECT, "SELECT to_regclass('".$table."') AS r", FALSE)->get('r');

            if ($exists === NULL)
            {
                $missing[] = $table;
            }
        }

        $this->assertSame([], $missing, 'Не накатаны миграции: выполните make migrate');
    }

    public function test_migrations_are_recorded()
    {
        $db = self::connection();

        if ($db === NULL)
        {
            $this->markTestSkipped('PostgreSQL недоступна');
        }

        $count = (int) $db->query(\Database::SELECT, 'SELECT COUNT(*) AS c FROM migrations_pg', FALSE)->get('c');

        $this->assertGreaterThan(0, $count);
    }

    public function test_pg_connection_helper_is_php81_compatible()
    {
        // Регрессия: pg_connect() в PHP 8.1+ возвращает объект, а не resource
        if ( ! method_exists('Database_PostgreSQL', 'is_pg_connection'))
        {
            $this->markTestSkipped('Используется PDO-драйвер PostgreSQL');
        }

        $this->assertTrue(\Database_PostgreSQL::is_pg_connection(NULL) === FALSE);
        $this->assertTrue(\Database_PostgreSQL::is_pg_connection(new \stdClass) === FALSE);
    }
}
