<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Проверка окружения: версия PHP, расширения, наличие конфигов.
 */
class EnvironmentTest extends TestCase
{
    public function test_php_version_is_supported()
    {
        // Минимум для текущего ядра — PHP 8.1, целевая версия проекта — 8.4.
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.1', '>='),
            'Требуется PHP 8.1 или выше, установлен '.PHP_VERSION
        );
    }

    public function test_pgsql_extension_is_loaded()
    {
        $this->assertTrue(
            extension_loaded('pgsql') OR extension_loaded('pdo_pgsql'),
            'Для работы с PostgreSQL нужно расширение pgsql или pdo_pgsql'
        );
    }

    public function test_core_extensions_are_loaded()
    {
        foreach (['mbstring', 'json', 'session', 'ctype'] as $ext)
        {
            $this->assertTrue(extension_loaded($ext), 'Расширение '.$ext.' не загружено');
        }
    }

    public function test_kohana_is_booted()
    {
        $this->assertTrue(class_exists('Kohana'), 'Фреймворк не загружен');

        // Ядро проекта — Konine (форк Kohana/Koseven), ветка PHP84X.
        $this->assertTrue(
            version_compare(\Kohana::VERSION, '3.4', '>='),
            'Ожидается Konine 3.4+, загружено '.\Kohana::VERSION
        );
        $this->assertSame('PHP84X', \Kohana::CODENAME, 'Ожидается ветка Konine PHP84X');
    }

    public function test_required_config_files_exist()
    {
        $files = [
            'database',
            'cookie',
            'auth',
            'site_config',
            'erp111_migrations_postgresql',
            'url',
        ];

        foreach ($files as $name)
        {
            // Konine ищет конфиг по каскаду: application/config, затем config модулей
            $found = \Kohana::find_file('config', $name);

            $this->assertTrue(
                (bool) $found,
                'Не найден config/'.$name.'.php (ни в application/config, ни в модулях) — выполните make setup'
            );

            $this->assertNotEmpty(
                \Kohana::$config->load($name)->as_array(),
                'Конфиг '.$name.' пуст'
            );
        }
    }

    public function test_config_templates_are_committed()
    {
        $this->assertTrue(is_dir(APPPATH.'config.dist'), 'Каталог application/config.dist должен быть в репозитории');

        $templates = glob(APPPATH.'config.dist/*.php');
        $this->assertGreaterThan(5, count($templates));
    }

    public function test_cookie_salt_is_not_default()
    {
        $salt = \Kohana::$config->load('cookie')->get('salt');

        $this->assertNotEmpty($salt, 'cookie.salt пуст');
        $this->assertNotSame('change-me-32-chars-minimum-length!!', $salt,
            'Замените cookie.salt (сгенерируйте: php -r "echo bin2hex(random_bytes(32));")');
    }

    public function test_system_config_files_are_present()
    {
        // system/config содержит inflector.php, mimes.php и др.
        // Ранее каталоги config/ попадали в .gitignore, из-за чего ядро ломалось.
        $this->assertTrue(is_file(SYSPATH.'config/inflector.php'), 'system/config/inflector.php отсутствует');
        $this->assertTrue(is_file(SYSPATH.'config/mimes.php'), 'system/config/mimes.php отсутствует');
    }

    public function test_writable_directories_exist()
    {
        foreach (['cache', 'logs', 'sessions'] as $dir)
        {
            $this->assertTrue(is_dir(APPPATH.$dir), 'Каталог application/'.$dir.' отсутствует');
        }
    }
}
