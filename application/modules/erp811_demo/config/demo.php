<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Настройки демонстрационного стенда (erp811_demo).
 *
 * Демо-стенд наполнен вымышленным производством и изолирован отдельной базой
 * либо отдельной схемой основной БД. Посетитель входит «гостем», изменения
 * запрещены, срок жизни сессии — пока открыт браузер. В schema-mode рабочая
 * схема `public` не входит в search_path, а сброс удаляет только demo-схему.
 *
 * Значения можно переопределить в `application/config/demo.php`.
 */

return array(

    // Выключенный модуль не отдаёт ни страницу /demo, ни гостевой вход:
    // на боевом стенде демо обычно не нужно.
    'enabled'          => TRUE,

    // Хранилище стенда:
    // - database (по умолчанию) — отдельная физическая БД;
    // - schema — отдельная схема в основной БД: удобно для dev/глубоких тестов,
    //   не создаёт новую БД и при этом никогда не смешивает строки с public.
    'storage'          => getenv('DEMO_STORAGE') ?: 'database',

    // Shared schema безопасна как рабочий инструмент разработки. В production
    // требуется отдельное явное подтверждение переменной окружения.
    'shared_database_allowed' => Kohana::$environment !== Kohana::PRODUCTION
        OR in_array(strtolower((string) getenv('DEMO_ALLOW_SHARED_PRODUCTION')), array('1', 'true', 'yes'), TRUE),

    // Параметры двух режимов. В schema-mode DB_DEMO_NAME игнорируется.
    'database'         => getenv('DB_DEMO_NAME') ?: 'promerp_demo',
    'schema'           => getenv('DB_DEMO_SCHEMA') ?: 'promerp_demo',

    // Cookie-переключатель демо-режима. Cookie, а не сессия: решение о базе
    // принимается в bootstrap, до старта сессии, иначе первый же запрос успел
    // бы уйти в рабочую базу.
    'cookie'           => 'promerp_demo',
    'cookie_lifetime'  => 0,

    // Гостевая учётная запись в демо-базе. Пароль известен всем — это и есть
    // вход в демонстрацию.
    'guest_email'      => 'guest@demo.promerp.local',
    'guest_password'   => 'demo',
    'guest_username'   => 'Гость демо-стенда',
    'guest_role'       => 'demo',

    // Роли, под которыми посетитель может осмотреть стенд.
    //
    // Гость (`demo`) видит витрины, но не рабочие места: разделам нужна роль,
    // а она у гостя одна — «смотреть». Чтобы показать смысл системы — «что
    // делать этому человеку прямо сейчас», — посетитель выбирает роль ещё на
    // главной странице и попадает сразу в подходящий раздел. Учётки те же, что
    // в `users` ниже; пароль не спрашивается, вход выполняется принудительно,
    // запись всё равно запрещена `guard_read_only()`.
    //
    // `title` — как роль назвать посетителю, `hint` — что он увидит,
    // `start` — куда его привести, чтобы первый экран был осмысленным.
    //
    // Адрес старта должен открываться на стенде: модуль, которого нет в списке
    // `modules` ниже (например, себестоимость erp409), отдаст ошибку —
    // демонстрационный стенд собран не из всех модулей системы.
    'roleplay'         => array(
        array(
            'role'  => 'admin',
            'email' => 'admin@demo.promerp.local',
            'title' => 'Администратор',
            'hint'  => 'Вся система: справочники, доступ, журнал действий',
            'start' => '/analytics',
            'icon'  => 'fa-user-shield',
        ),
        array(
            'role'  => 'manager',
            'email' => 'manager@demo.promerp.local',
            'title' => 'Руководитель снабжения',
            'hint'  => 'Заявки, заказы поставщикам и просроченные сроки',
            'start' => '/procurement',
            'icon'  => 'fa-truck-ramp-box',
        ),
        array(
            'role'  => 'worker',
            'email' => 'master@demo.promerp.local',
            'title' => 'Мастер участка',
            'hint'  => 'Качество партий и документы по операциям',
            'start' => '/quality',
            'icon'  => 'fa-clipboard-check',
        ),
        array(
            'role'  => 'accountant',
            'email' => 'quality@demo.promerp.local',
            'title' => 'Экономист',
            'hint'  => 'Склад, остатки, резервы и движения — деньги в запасах',
            'start' => '/warehouse/balances',
            'icon'  => 'fa-boxes-stacked',
        ),
        array(
            'role'  => 'demo',
            'email' => 'guest@demo.promerp.local',
            'title' => 'Просто посмотреть',
            'hint'  => 'Витрины, отчёты и справочники без привязки к роли',
            'start' => '/analytics',
            'icon'  => 'fa-eye',
        ),
    ),

    // Учётные записи, которые создаются в демо-базе, чтобы демонстрация
    // выглядела живой (кто-то снял снимок, кто-то принял партию).
    'users'            => array(
        array('email' => 'admin@demo.promerp.local',   'username' => 'admin',       'password' => 'demo', 'roles' => array('login', 'admin')),
        array('email' => 'manager@demo.promerp.local', 'username' => 'Смирнова А.', 'password' => 'demo', 'roles' => array('login', 'manager')),
        array('email' => 'master@demo.promerp.local',  'username' => 'Гареев Р.',   'password' => 'demo', 'roles' => array('login', 'worker')),
        array('email' => 'quality@demo.promerp.local', 'username' => 'Литвинова О.','password' => 'demo', 'roles' => array('login', 'accountant')),
    ),

    // Сценарий: какой мир имитируем. Сейчас один — контрактная 3D-печать.
    'scenario'         => 'print3d',

    // Модули, миграции которых нужны демо-базе. Порядок = порядок применения.
    // Модуль MDM (erp230_mdm) в демонстрацию не входит: у него своя база.
    'modules'          => array(
        'erp100_base',
        'erp118_links',
        'erp119_audit',
        'erp208_events',
        'erp212_file_storage',
        'erp217_notification_center',
        'erp310_workforce',
        'erp227_core_registry',
        'erp228_tech_cards',
        'erp231_product_structure',
        'erp301_counterparty',
        'erp221_crm',
        'erp401_wms',
        'erp402_production',
        'erp403_procurement',
        'erp404_documents',
        'erp405_quality',
        'erp407_planning',
        'erp408_shopfloor',
        'erp199_sqr',
        'erp801_analytics',
        'erp804_mcp',
        'erp811_demo',
    ),

    // Профиль объёма. quick предназначен для сборки из браузера на shared
    // hosting; standard — обычная демонстрация; full — нагрузочные исследования.
    'build_profile'    => getenv('DEMO_BUILD_PROFILE') ?: 'quick',
    'build_profiles'   => array(
        // telemetry_every прореживает только хранимые события и аудит генератора;
        // слушатели EventBus всё равно получают каждое событие.
        'quick'    => array('label' => 'Быстрый', 'history_days' => 30,  'orders' => 8,  'telemetry_every' => 20),
        'standard' => array('label' => 'Обычный', 'history_days' => 60,  'orders' => 16, 'telemetry_every' => 5),
        'full'     => array('label' => 'Полный',  'history_days' => 120, 'orders' => 30, 'telemetry_every' => 1),
    ),

    // Явные значения оставлены для обратной совместимости и пользовательских
    // сценариев. Во время UI-сборки их переопределяет выбранный профиль.
    'history_days'     => (int) (getenv('DEMO_HISTORY_DAYS') ?: 30),
    'orders'           => (int) (getenv('DEMO_ORDERS') ?: 8),

);
