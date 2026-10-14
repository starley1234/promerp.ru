<?php

// -- Environment setup --------------------------------------------------------

// Load the core Kohana class
require SYSPATH.'classes/Kohana/Core'.EXT;

if (is_file(APPPATH.'classes/Kohana'.EXT))
{
	// Application extends the core
	require APPPATH.'classes/Kohana'.EXT;
}
else
{
	// Load empty core extension
	require SYSPATH.'classes/Kohana'.EXT;
}

// Подключение автозагрузчика Composer.
// В репозитории vendor/ отсутствует (composer install выполняется при развёртывании),
// поэтому проверяем наличие файла — иначе приложение обязано работать на ядре фреймворка.
if (is_file(dirname(__DIR__) . '/vendor/autoload.php'))
{
	require dirname(__DIR__) . '/vendor/autoload.php';
}

/**
 * Set the default time zone.
 *
 * @link http://kohanaframework.org/guide/using.configuration
 * @link http://www.php.net/manual/timezones
 */
date_default_timezone_set('Europe/Moscow');

/**
 * Set the default locale.
 *
 * @link http://kohanaframework.org/guide/using.configuration
 * @link http://www.php.net/manual/function.setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Enable the Kohana auto-loader.
 *
 * @link http://kohanaframework.org/guide/using.autoloading
 * @link http://www.php.net/manual/function.spl-autoload-register
 */
spl_autoload_register(['Kohana', 'auto_load']);

/**
 * Optionally, you can enable a compatibility auto-loader for use with
 * older modules that have not been updated for PSR-0.
 *
 * It is recommended to not enable this unless absolutely necessary.
 */
//spl_autoload_register(array('Kohana', 'auto_load_lowercase'));

/**
 * Enable the Kohana auto-loader for unserialization.
 *
 * @link http://www.php.net/manual/function.spl-autoload-call
 * @link http://www.php.net/manual/var.configuration#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');

/**
 * Enable composer autoload libraries
 */
if (is_file(dirname(APPPATH).'/vendor/autoload.php'))
{
	require dirname(APPPATH).'/vendor/autoload.php';
}

/**
 * Set the mb_substitute_character to "none"
 *
 * @link http://www.php.net/manual/function.mb-substitute-character.php
 */
mb_substitute_character('none');

// -- Configuration and initialization -----------------------------------------

/**
 * Set the default language
 */
I18n::lang('ru-ru');

if (isset($_SERVER['SERVER_PROTOCOL']))
{
	// Replace the default protocol.
	HTTP::$protocol = $_SERVER['SERVER_PROTOCOL'];
}

/**
 * Set Kohana::$environment if a 'KOHANA_ENV' environment variable has been supplied.
 *
 * Note: If you supply an invalid environment name, a PHP warning will be thrown
 * saying "Couldn't find constant Kohana::<INVALID_ENV_NAME>"
 */
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}

/**
 * Initialize Kohana, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   NULL
 * - string   index_file  name of your index file, usually "index.php", if set to FALSE uses clean URLS     index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - integer  cache_life  lifetime, in seconds, of items cached              60
 * - boolean  errors      enable or disable error handling                   TRUE
 * - boolean  profile     enable or disable internal profiling               TRUE
 * - boolean  caching     enable or disable internal caching                 FALSE
 * - boolean  expose      set the X-Powered-By header                        FALSE
 */
Kohana::init([
	'base_url'   => '/',
	'index_file' => FALSE, // Убираем index.php из генерации ссылок
	'profile' => FALSE,
	'caching' => FALSE,
	
	
]);


/**
 * Attach the file write to logging. Multiple writers are supported.
 */
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
Kohana::$config->attach(new Config_File);

Cookie::$salt = Kohana::$config->load('cookie')->get('salt');


ini_set('session.save_path', APPPATH.'sessions');
// Session::$default = 'database';


/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
Kohana::modules([
	 'encrypt'    => MODPATH.'encrypt',    // Encryption supprt
	 'auth'       => MODPATH.'auth',       // Basic authentication
	 'cache'      => MODPATH.'cache',      // Caching with multiple backends
	 
	 'cookie_consent' => APPPATH.'modules/cookie_consent',
	 
	 'email' => APPPATH.'modules/email',
	 'llm' => APPPATH.'modules/llm',
	 'erp100_base' => APPPATH.'modules/erp100_base',
	 
	 'PostgreSQL' => APPPATH.'modules/PostgreSQL',
	 
	 'erp111_migrations_postgresql' => APPPATH.'modules/erp111_migrations_postgresql',
	 'erp208_events' => APPPATH.'modules/erp208_events',

	 // Шлюз обмена: журнал пакетов, idempotency, mapping внешних ID и безопасный импорт.
	 'erp802_integration' => APPPATH.'modules/erp802_integration',

	 // Журнал действий: кто, когда и что изменил (читает события шины)
	 'erp119_audit' => APPPATH.'modules/erp119_audit',
	 'erp217_notification_center' => APPPATH.'modules/erp217_notification_center',
	 'erp212_file_storage' => APPPATH.'modules/erp212_file_storage',
	 'erp218_module_manager' => APPPATH.'modules/erp218_module_manager',
	 
	 'erp118_links' => APPPATH.'modules/erp118_links',
	 
	 'erp227_core_registry' => APPPATH.'modules/erp227_core_registry',

	 // Технологические карты: исходник изделия -> техпроцесс -> операции
	 'erp228_tech_cards' => APPPATH.'modules/erp228_tech_cards',

	 // Версионируемые конструкторские и производственные составы EBOM/MBOM.
	 'erp231_product_structure' => APPPATH.'modules/erp231_product_structure',

	 // Опциональный MDM-контур: golden record внешних PLM/ERP/AI-источников.
	 // Пока изолирован от оперативной номенклатуры erp227 и имеет статус
	 // optional/quarantine; новые модули от него не зависят.
	 'erp230_mdm' => APPPATH.'modules/erp230_mdm',

	 // Контрагенты (Master Data): поставщики, покупатели, реквизиты, договоры
	 'erp301_counterparty' => APPPATH.'modules/erp301_counterparty',

	 // Продажи (CRM): воронка и сделки, лента активностей и напоминания,
	 // профиль продаж клиента поверх контрагента erp301. Своего справочника
	 // клиентов модуль не ведёт — данные о клиенте и контактах остаются у
	 // владельца (erp301), CRM хранит только то, чего у него нет: стадии,
	 // сделки, ленту, ИИ-разбор и базу знаний — см. README модуля.
	 'erp221_crm' => APPPATH.'modules/erp221_crm',

	 // Сотрудники и организационная структура: человек, учётная запись,
	 // подразделение, должность и история назначений.
	 'erp310_workforce' => APPPATH.'modules/erp310_workforce',

	 // Складской учёт (WMS): склады, остатки, движения, резервы (см. README модуля)
	 'erp401_wms' => APPPATH.'modules/erp401_wms',

	 // Lot/serial identity и genealogy; количества по-прежнему принадлежат WMS.
	 'erp412_traceability' => APPPATH.'modules/erp412_traceability',

	 // Производство: заказы, состав (BOM), резерв материалов, выпуск продукции.
	 // Опирается на erp228 (техкарты), erp401 (склад), erp227 (номенклатура).
	 'erp402_production' => APPPATH.'modules/erp402_production',

	 // Цех: рабочие центры, смены, календарь, операции заказов и отметки
	 // исполнителей (терминал). Опирается на erp402 (заказы), erp228 (операции
	 // техкарт), erp401 (материалы) — см. README модуля.
	 'erp408_shopfloor' => APPPATH.'modules/erp408_shopfloor',

	 // QR-маркировка: наклейка связывает запись в системе с предметом в цехе.
	 // Данные о предметах модуль не дублирует — читает у владельцев (erp227
	 // реестр, erp412 партии, erp402 заказы, erp310 сотрудники, erp401 остатки),
	 // а сам ведёт реестр наклеек, свободные объекты (то, для чего владельца
	 // нет), полевые сценарии и инвентаризации. Тонкий клиент (приложение,
	 // WebView) ходит только через /qr/api — см. README модуля.
	 'erp199_sqr' => APPPATH.'modules/erp199_sqr',

	 // Объяснимая плановая/фактическая себестоимость производственных заказов.
	 'erp409_costing' => APPPATH.'modules/erp409_costing',

	 // Планирование потребностей (MRP-минимум): производственная программа,
	 // расчёт потребности по техкартам с учётом остатков и незавершёнки,
	 // дефициты и даты заказа, предложения для закупки и производства.
	 // Опирается на erp402 (заказы), erp401 (склад), erp228 (техкарты),
	 // erp227 (номенклатура) — см. README модуля.
	 'erp407_planning' => APPPATH.'modules/erp407_planning',

	 // Закупки: заявка на потребность (руками, по минимуму склада или из
	 // предложения планирования erp407), согласование, заказ поставщику,
	 // приёмка с входным контролем и приход на склад. Опирается на erp301
	 // (контрагенты-поставщики), erp401 (склад и минимумы), erp227
	 // (номенклатура), erp119/erp208 (журнал и события) — см. README модуля.
	 'erp403_procurement' => APPPATH.'modules/erp403_procurement',

	 // Продажи (erp410_shipment): заказ клиента, подтверждение с проверкой
	 // кредитного лимита, резерв товара на складе, сборка и проведение
	 // отгрузки (расход в erp401), счёт и оплата. Покупатели — контрагенты
	 // erp301 с ролью «покупатель», товар — реестр erp227, склад — erp401.
	 // См. README модуля.
	 'erp410_shipment' => APPPATH.'modules/erp410_shipment',

	 // MCP-сервер: доступ LLM-агентов к сервисам модулей (инструменты, ключи,
	 // журнал вызовов, подтверждения). Зависимостей нет — модуль лишь читает
	 // объявления `mcp.php` тех модулей, что подключены (см. README модуля).
	 // Документы и печать (erp404_documents): шаблоны печатных форм, выпуск
	 // документов со снимком и версией шаблона, журнал печати. Своих справочников
	 // не заводит: данные берёт у владельцев (закупки erp403, производство
	 // erp402, качество erp405, цех erp408), файлы — из хранилища erp212.
	 'erp404_documents' => APPPATH.'modules/erp404_documents',

	 // Качество (erp405_quality): контроль партий (входной, операционный,
	 // итоговый), несоответствия с причиной и виновником, карантин, решения по
	 // браку (переделать / вернуть / утиль / как есть) и статистика. Опирается
	 // на склад erp401, приёмки erp403, заказы erp402 и операции цеха erp408.
	 'erp405_quality' => APPPATH.'modules/erp405_quality',

	 // Аналитика и витрины (erp801_analytics): сводка KPI, оборачиваемость и
	 // покрытие запасов с ABC/XYZ, OEE и Парето простоев, Парето брака, просрочка
	 // закупок, снимки отчётов и пороги «плохо/хорошо». Своих предметных данных
	 // не ведёт: агрегаты считают владельцы (erp401, erp408, erp405, erp403), а
	 // модуль их сводит, сравнивает и выгружает в CSV — см. README модуля.
	 'erp801_analytics' => APPPATH.'modules/erp801_analytics',

	 // Демонстрационный стенд (erp811_demo): вымышленное производство
	 // «контрактная 3D-печать» в отдельной базе + режим гостя. Нужен, чтобы
	 // показать работающую систему целиком там, где своего учёта ещё нет:
	 // один вход без регистрации, изменения выключены, боевые данные недостижимы.
	 'erp811_demo' => APPPATH.'modules/erp811_demo',

	 // Интерактивная документация: сквозные бизнес-процессы, архитектурные
	 // диаграммы Mermaid, каталог модулей, поиск и инструменты MCP.
	 'erp812_docs' => APPPATH.'modules/erp812_docs',

	 'erp804_mcp' => APPPATH.'modules/erp804_mcp',

	 
	// 'codebench'  => MODPATH.'codebench',  // Benchmarking tool
	
	 'minion'     => MODPATH.'minion',     // CLI Tasks
	
	 'database'   => MODPATH.'database',   // Database access
	// 'image'      => MODPATH.'image',      // Image manipulation
	 
	 'orm'        => MODPATH.'orm',        // Object Relationship Mapping
	 'pagination' => MODPATH.'pagination', // Pagination
	// 'unittest'   => MODPATH.'unittest',   // Unit testing
	// 'userguide'  => MODPATH.'userguide',  // User guide and API documentation
	]);
/**
 * Демонстрационный стенд (erp811_demo) включается здесь, а не в контроллере:
 * решение «работать с демо-базой» принимается по cookie до того, как стартует
 * сессия и первый запрос к базе. Иначе гость успел бы открыть объекты рабочей
 * базы и только потом переключиться.
 *
 * Режим «только чтение» ставится там же: демо-сессия не принимает изменений,
 * чтобы стенд оставался одинаковым для следующего посетителя. Проверка нужна
 * раньше контроллеров — иначе POST успел бы дойти до сервисов модулей.
 */
if (PHP_SAPI !== 'cli' AND class_exists('Service_Erp811_Demo'))
{
	 try
	 {
	 	 Service_Erp811_Demo::instance()->boot();
	 	 Service_Erp811_Demo::instance()->guard_read_only();
	 }
	 catch (HTTP_Exception_Expected $e)
	 {
	 	 // Служебный редирект стенда — например, вход в систему с оставшейся
	 	 // cookie стенда. У такого исключения уже собран готовый ответ с
	 	 // заголовком Location, но общий обработчик исключений собирает новый
	 	 // Response и заголовок теряет: браузер получал 302 без адреса и
	 	 // оставался на странице входа с ошибкой «неверный пароль» (проверка
	 	 // шла по демо-базе). Отдаём ответ исключения так же, как это делает
	 	 // контур запроса (Kohana_Request_Client_Internal).
	 	 echo $e->get_response()->send_headers(TRUE)->body();

	 	 exit;
	 }
	 catch (HTTP_Exception_403 $e)
	 {
	 	 // Отказ «только чтение» рисует свою страницу — отдаём как есть.
	 	 throw $e;
	 }
	 catch (Exception $e)
	 {
	 	 // Стенд — вспомогательный контур: если он сломан, сайт обязан работать.
	 	 Kohana::$log->add(Log::ERROR, 'erp811: демо-режим не включён — :error', array(':error' => $e->getMessage()));
	 }
}

	
	

	
	

/**
 * Cookie Salt
 * @see  http://kohanaframework.org/3.3/guide/kohana/cookies
 *
 * If you have not defined a cookie salt in your Cookie class then
 * uncomment the line below and define a preferrably long salt.
 */
// Cookie::$salt = NULL;
/**
 * Cookie HttpOnly directive
 * If set to true, disallows cookies to be accessed from JavaScript
 * @see https://en.wikipedia.org/wiki/Session_hijacking
 */
// Cookie::$httponly = TRUE;
/**
 * If website runs on secure protocol HTTPS, allows cookies only to be transmitted
 * via HTTPS.
 * Warning: HSTS must also be enabled in .htaccess, otherwise first request
 * to http://www.example.com will still reveal this cookie
 */
// Cookie::$secure = isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] == 'on' ? TRUE : FALSE;

/**
 * Set the routes. Each route must have a minimum of a name, a URI and a set of
 * defaults for the URI.
 */

require_once APPPATH.'routes.php'; 
 
