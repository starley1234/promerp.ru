<?php
/**
 * Загрузочный файл тестов: поднимает Kohana в окружении TESTING.
 *
 * Используется и PHPUnit (phpunit.xml.dist), и простым раннером tests/run.php
 * (на случай, если composer/phpunit недоступен).
 */

$_SERVER['KOHANA_ENV'] = 'TESTING';

define('EXT', '.php');

// Та же политика, что у точки входа (public_html/index.php): по умолчанию
// депрекейшены отключены, с STRICT_DEPRECATIONS=1 — включены и становятся
// ошибками. Иначе тесты проверяют не то же поведение, что видит пользователь.
$strict = getenv('STRICT_DEPRECATIONS');
error_reporting(($strict === '1' OR $strict === 'true') ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED));

$base = dirname(__DIR__);

// Тесты читают те же переменные окружения, что bin/console и bin/serve (.env).
$env_file = $base.'/.env';

if (is_file($env_file))
{
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
    {
        $line = trim($line);

        if ($line === '' OR $line[0] === '#' OR strpos($line, '=') === FALSE)
        {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Снимаем парные кавычки
        if (strlen($value) > 1 AND ($value[0] === '"' OR $value[0] === "'") AND substr($value, -1) === $value[0])
        {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === FALSE)
        {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }
    }
}

$application = $base.'/application';
$modules     = $base.'/modules';
$system      = $base.'/system';

define('DOCROOT', $base.'/public_html/');
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('MODPATH', realpath($modules).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);

// Точка входа не используется, но Kohana ожидает ряд $_SERVER-ключей
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

if ( ! defined('KOHANA_START_TIME'))
{
    define('KOHANA_START_TIME', microtime(TRUE));
}

if ( ! defined('KOHANA_START_MEMORY'))
{
    define('KOHANA_START_MEMORY', memory_get_usage());
}

require APPPATH.'bootstrap'.EXT;

// Тесты не должны зависеть от состояния сессии авторизованного пользователя
if (class_exists('Auth'))
{
    try
    {
        Auth::instance()->logout(TRUE);
    }
    catch (Exception $e)
    {
        // ignore
    }
}
