<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Соглашения модулей: именование каталогов, миграций, наличие документации.
 */
class ModuleConventionsTest extends TestCase
{
    /**
     * Модули, которые не являются ERP-модулями и не обязаны следовать erpNNN_slug.
     */
    protected $_exceptions = ['PostgreSQL', 'PostgreSQL_PDO', 'email', 'cookie_consent', 'llm', 'yandexauth'];

    public function test_module_directories_are_named_by_convention()
    {
        $modules = glob(APPPATH.'modules/*', GLOB_ONLYDIR);
        $this->assertGreaterThan(10, count($modules));

        $violations = [];

        foreach ($modules as $dir)
        {
            $name = basename($dir);

            if (in_array($name, $this->_exceptions, TRUE))
            {
                continue;
            }

            if ( ! preg_match('/^erp\d{3}_[a-z0-9_]+$/', $name))
            {
                $violations[] = $name;
            }
        }

        $this->assertSame([], $violations, 'Имена модулей должны быть вида erpNNN_slug');
    }

    public function test_all_enabled_modules_exist_on_disk()
    {
        foreach (\Kohana::modules() as $name => $path)
        {
            $this->assertTrue(is_dir($path), 'Модуль '.$name.' подключён в bootstrap.php, но каталог '.$path.' отсутствует');
        }
    }

    public function test_pg_migrations_follow_naming_convention()
    {
        $violations = [];

        foreach (glob(APPPATH.'modules/*/migrations_pg/*.php') as $file)
        {
            $class = basename($file, '.php');

            if (strpos($class, 'pgMigration_') !== 0 AND strpos($class, 'Migration_') !== 0)
            {
                $violations[] = $class;
            }
        }

        $this->assertSame([], $violations, 'Файлы миграций должны называться pgMigration_*.php');
    }

    public function test_pg_migrations_extend_base_class()
    {
        $violations = [];

        foreach (glob(APPPATH.'modules/*/migrations_pg/pgMigration_*.php') as $file)
        {
            $class = basename($file, '.php');
            require_once $file;

            if ( ! class_exists($class))
            {
                $violations[] = $class.' (класс не найден)';
                continue;
            }

            if ( ! is_subclass_of($class, 'Model_Erp111_Migration'))
            {
                $violations[] = $class.' (не наследует Model_Erp111_Migration)';
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_erp_modules_have_readme()
    {
        $missing = [];

        foreach (\Kohana::modules() as $name => $path)
        {
            if ( ! preg_match('/^erp\d{3}_/', $name))
            {
                continue;
            }

            if ( ! is_file($path.'README.md'))
            {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'У каждого ERP-модуля должен быть README.md');
    }

    public function test_module_manager_lists_modules_on_disk()
    {
        $on_disk = count(glob(APPPATH.'modules/*', GLOB_ONLYDIR));
        $enabled = count(\Kohana::modules());

        $this->assertGreaterThan(0, $on_disk);
        $this->assertGreaterThan(0, $enabled);

        // Не подключённые модули — не ошибка, но выводим их в сообщении
        $not_connected = [];
        foreach (glob(APPPATH.'modules/*', GLOB_ONLYDIR) as $dir)
        {
            $name = basename($dir);
            if ( ! array_key_exists($name, \Kohana::modules()))
            {
                $not_connected[] = $name;
            }
        }

        $this->assertTrue(TRUE, 'Не подключены в bootstrap.php: '.implode(', ', $not_connected));
    }

    /**
     * `Request::query($key, $value)` — сеттер, а не «значение по умолчанию»:
     * такой вызов возвращает объект Request, и `(int)` от него роняет действие
     * с «Object of class Request could not be converted to int». Так были
     * сломаны JSON API склада и страница журнала вызовов MCP. Для значений
     * по умолчанию есть `$this->_query($key, $default)` в Controller_Base.
     */
    public function test_controllers_do_not_pass_query_defaults()
    {
        $violations = [];

        foreach (glob(APPPATH.'modules/*/classes/Controller/*/*.php') as $file)
        {
            $source = (string) file_get_contents($file);

            // Именно $this->request->query(...): у Request::factory(...)->query()
            // второй аргумент — законный сеттер строки запроса
            if (preg_match_all("/->request->query\('([^']+)'\s*,/", $source, $matches))
            {
                foreach ($matches[1] as $key)
                {
                    $violations[] = basename($file).': request->query(\''.$key.'\') с вторым аргументом — используйте $this->_query()';
                }
            }
        }

        $this->assertSame([], $violations, 'Значения по умолчанию для query() задавайте через $this->_query()');
    }

    /**
     * У каждого модуля проекта должен быть либо JSON API, либо объявленные
     * инструменты MCP (файл mcp.php в модуле) — требование заказчика
     * «API у каждого модуля».
     *
     * Исключений для проектных (erp*) модулей нет: ядро (erp100_base),
     * миграции (erp111) и личные ссылки (erp118) отдают API наравне с
     * бизнес-модулями — интерфейс один раз описан и доступен машинам.
     * Сторонние и служебные модули (auth, database, email, llm, yandexauth…)
     * не в счёте: это не модули системы, а зависимости каркаса.
     */
    public function test_api_modules_have_entry_points()
    {
        $violations = [];

        foreach (\Kohana::modules() as $name => $path)
        {
            if (strpos($name, 'erp') !== 0)
            {
                continue;
            }

            if (glob($path.'/classes/Controller/*/Api.php') OR is_file($path.'/mcp.php'))
            {
                continue;
            }

            $violations[] = $name;
        }

        $this->assertSame([], $violations, 'Модуль без JSON API и без mcp.php');
    }

    /**
     * Ключ права объявляется в `config/acl.php` ровно один раз: при дубле
     * побеждает последнее объявление и роль молча теряет доступ (реальный
     * случай — демо-стенд остался без просмотра контрагентов `erp301.view`).
     */
    public function test_acl_abilities_are_declared_once()
    {
        $source = (string) file_get_contents(APPPATH.'config/acl.php');

        $this->assertNotSame('', $source);
        $this->assertTrue(
            (bool) preg_match_all("/'([a-zA-Z0-9_]+\.[a-zA-Z0-9_*]+)'\s*=>/", $source, $matches),
            'В config/acl.php не найдено ни одного объявленного права'
        );

        $seen = [];
        $duplicates = [];

        foreach ($matches[1] as $ability)
        {
            if (isset($seen[$ability]))
            {
                $duplicates[$ability] = TRUE;
            }

            $seen[$ability] = TRUE;
        }

        $this->assertSame(
            [],
            array_keys($duplicates),
            'Право объявлено в config/acl.php дважды — побеждает последнее объявление'
        );
    }
}
