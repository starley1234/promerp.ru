<?php

/**
 * The directory in which your application specific resources are located.
 * The application directory must contain the bootstrap.php file.
 *
 * @link http://kohanaframework.org/guide/about.install#application
 */
$application = 'application';

/**
 * The directory in which your modules are located.
 *
 * @link http://kohanaframework.org/guide/about.install#modules
 */
$modules = 'modules';

/**
 * The directory in which the Kohana resources are located. The system
 * directory must contain the classes/kohana.php file.
 *
 * @link http://kohanaframework.org/guide/about.install#system
 */
$system = 'system';

/**
 * The default extension of resource files. If you change this, all resources
 * must be renamed to use the new extension.
 *
 * @link http://kohanaframework.org/guide/about.install#ext
 */
define('EXT', '.php');

/**
 * Set the PHP error reporting level. If you set this in php.ini, you remove this.
 * @link http://www.php.net/manual/errorfunc.configuration#ini.error-reporting
 *
 * When developing your application, it is highly recommended to enable notices
 * and warnings. Enable them by using: E_ALL
 *
 * In a production environment, it is safe to ignore notices. Disable them by
 * using: E_ALL & ~E_NOTICE
 *
 * When using a legacy application, it is recommended to disable deprecated
 * notices. Disable with: E_ALL & ~E_DEPRECATED
 */
/**
 * Переменные окружения из `.env` — до всего остального.
 *
 * Раньше `.env` читали только обёртки CLI (`bin/console`, `bin/serve`,
 * `bin/test`), а веб-точка входа полагалась на настройки сервера. На хосте с
 * php-fpm (или там, где `.htaccess` не применился) настройки не доходили до
 * PHP, и приложение падало на «A valid cookie salt is required». Теперь файл
 * читает само приложение, поэтому способ доставки настроек перестал быть
 * частью развёртывания. Значения окружения сервера при этом приоритетнее.
 */
$root = realpath(__DIR__.'/..').DIRECTORY_SEPARATOR;

if (is_file($root.'application/env.php'))
{
	require $root.'application/env.php';

	promerp_env_load($root);
}

/**
 * Режим депрекейшенов.
 *
 * Kohana/Konine превращает любое сообщение, попадающее под error_reporting(),
 * в ErrorException. На PHP 8.4 устаревший код (mb_strlen(NULL), strtotime(NULL)
 * и т.п.) иначе валит страницы в 500.
 *
 *   STRICT_DEPRECATIONS=1  — строгий режим (E_ALL): депрекейшены становятся ошибками,
 *                            нужен, чтобы вычищать код под новые версии PHP;
 *   по умолчанию           — мягкий режим: депрекейшены пишутся в лог, но не рвут запрос.
 */
$strict = getenv('STRICT_DEPRECATIONS');
error_reporting(($strict === '1' OR $strict === 'true') ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED));

/**
 * End of standard configuration! Changing any of the code below should only be
 * attempted by those with a working knowledge of Kohana internals.
 *
 * @link http://kohanaframework.org/guide/using.configuration
 */

// Корень проекта (на уровень выше public_html) определён выше, там же, где
// читается .env: он нужен и для настроек, и для путей.

// Set the full path to the docroot — каталог, доступный из веба (public_html).
// Именно от него модули строят пути к uploads/img/storage.
define('DOCROOT', realpath(__DIR__).DIRECTORY_SEPARATOR);

// Пути к приложению, модулям и ядру считаем от корня проекта:
// так index.php одинаково работает и из CLI, и из php-fpm (cwd = public_html).
if (is_dir($root.$application))
	$application = $root.$application;

if (is_dir($root.$modules))
	$modules = $root.$modules;

if (is_dir($root.$system))
	$system = $root.$system;

// Define the absolute paths for configured directories
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('MODPATH', realpath($modules).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);

// Clean up the configuration vars
unset($application, $modules, $system);

if (file_exists('install'.EXT))
{
	// Load the installation check
	return include 'install'.EXT;
}

/**
 * Define the start time of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_TIME'))
{
	define('KOHANA_START_TIME', microtime(TRUE));
}

/**
 * Define the memory usage at the start of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_MEMORY'))
{
	define('KOHANA_START_MEMORY', memory_get_usage());
}

// Bootstrap the application
require APPPATH.'bootstrap'.EXT;

if (PHP_SAPI == 'cli') // Try and load minion
{
	class_exists('Minion_Task') OR die('Please enable the Minion module for CLI support.');
	set_exception_handler(['Minion_Exception', 'handler']);

	Minion_Task::factory(Minion_CLI::options())->execute();
}
else
{
	/**
	 * Execute the main request. A source of the URI can be passed, eg: $_SERVER['PATH_INFO'].
	 * If no source is specified, the URI will be automatically detected.
	 */
	echo Request::factory(TRUE, [], FALSE)
		->execute()
		->send_headers(TRUE)
		->body();
}
