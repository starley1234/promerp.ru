<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты демонстрационного стенда (модуль erp811_demo).
 *
 * Проверяют обещания модуля, а не отдельные строки:
 *
 *  - стенд живёт в отдельной базе либо схеме основной БД, а очистка сцены
 *    отказывается работать вне точно настроенной пары database/schema;
 *  - сценарий связный: у каждой детали есть материал, маршрут и техкарта-опора,
 *    у каждого шага тура — адрес, что смотреть и вопрос к экрану;
 *  - демо-режим **только на чтение**: не-GET из демо-сессии отвечает 403, а
 *    GET проходит;
 *  - инструменты MCP стенда читающие (нет ни одного изменяющего) — сборка
 *    данных остаётся инфраструктурной операцией;
 *  - маршруты стенда ведут на существующие контроллеры и действия.
 *
 * Тесты не собирают стенд: они читают настройки, сценарий и объявления. База
 * демо-стенда для них не нужна, поэтому они не пропускаются ни на пустой, ни на
 * собранной базе.
 */
class Erp811DemoTest extends TestCase
{
    /** @var \Service_Erp811_Demo */
    protected $demo;

    /** @var \Service_Erp811_Scenario */
    protected $scenario;

    protected function setUp(): void
    {
        $this->demo     = \Service_Erp811_Demo::instance();
        $this->scenario = \Service_Erp811_Scenario::instance();
    }

    // =========================================================================
    //  Изоляция: отдельная база либо отдельная схема
    // =========================================================================

    public function test_stand_uses_its_own_database()
    {
        $name = $this->demo->database_name();

        $this->assertNotEmpty($name, 'У стенда должно быть имя базы');

        $config = $this->demo->database_config();

        $this->assertSame($name, $config['connection']['database'], 'Стенд подключается к своей базе');
        $this->assertNotSame(
            (string) \Kohana::$config->load('database.default.connection.database'),
            $name,
            'База стенда не должна совпадать с рабочей базой'
        );
    }

    public function test_stand_can_use_isolated_schema_in_main_database()
    {
        $config = \Kohana::$config->load('demo');
        $oldStorage = $config->get('storage');
        $oldSchema = $config->get('schema');

        try
        {
            $config->set('storage', 'schema');
            $config->set('schema', 'promerp_demo_test');

            $this->assertTrue($this->demo->uses_shared_database());
            $this->assertSame('promerp_demo_test', $this->demo->schema_name());
            $this->assertSame(
                (string) \Kohana::$config->load('database.default.connection.database'),
                $this->demo->database_name()
            );
            $databaseConfig = $this->demo->database_config();
            $this->assertSame('promerp_demo_test', $databaseConfig['schema']);
            $this->assertSame(array('promerp_demo_test', 'public'), $databaseConfig['search_path']);
        }
        finally
        {
            $config->set('storage', $oldStorage);
            $config->set('schema', $oldSchema);
        }
    }

    public function test_quick_profile_keeps_browser_build_small()
    {
        $profiles = $this->demo->build_profiles();

        $this->assertArrayHasKey('quick', $profiles);
        $this->assertArrayHasKey('full', $profiles);
        $this->assertLessThan($profiles['full']['orders'], $profiles['quick']['orders']);
        $this->assertLessThan($profiles['full']['history_days'], $profiles['quick']['history_days']);
        $this->assertGreaterThanOrEqual(8, $profiles['quick']['orders'], 'Быстрый стенд всё равно покрывает все 8 деталей');
    }

    public function test_demo_profiles_compact_only_persisted_telemetry()
    {
        $profiles = $this->demo->build_profiles();

        $this->assertSame(20, $profiles['quick']['telemetry_every']);
        $this->assertSame(5, $profiles['standard']['telemetry_every']);
        $this->assertSame(1, $profiles['full']['telemetry_every']);

        $seeder = file_get_contents(APPPATH.'modules/erp811_demo/classes/Service/Erp811/Seeder.php');
        $events = file_get_contents(APPPATH.'modules/erp208_events/classes/Service/Erp208/EventBus.php');
        $journal = file_get_contents(APPPATH.'modules/erp119_audit/classes/Service/Erp119/Journal.php');

        $this->assertStringContainsString('sample_writes($telemetry_every)', $seeder);
        $this->assertStringContainsString('sample_persistence($telemetry_every)', $seeder);
        $this->assertStringContainsString('finally', $seeder, 'Полный режим обязан восстанавливаться при сбое сборки');
        $this->assertStringContainsString('$persist_event', $events);
        $this->assertStringContainsString('// 3. Уведомление слушателей', $events);
        $this->assertStringContainsString('sample_writes($every = 1)', $journal);
    }

    public function test_event_sampling_never_skips_business_listeners()
    {
        $reflection = new \ReflectionClass('Service_Erp208_EventBus');
        $bus = $reflection->newInstanceWithoutConstructor();
        $config = new \ReflectionProperty('Service_Erp208_EventBus', '_config');
        $config->setAccessible(TRUE);
        $config->setValue($bus, array('log_published_events' => FALSE, 'logging' => array('enabled' => FALSE)));

        $received = array();
        $bus->sample_persistence(20);
        $bus->listen('demo.test', function ($payload) use (&$received) {
            $received[] = $payload['sequence'];
        });

        for ($i = 1; $i <= 25; $i++)
        {
            $this->assertTrue($bus->publish('demo.test', array('sequence' => $i)));
        }

        $this->assertSame(range(1, 25), $received, 'Прореживание БД не должно прореживать доставку событий');
    }

    public function test_guest_quality_gate_checks_account_and_demo_role()
    {
        $seeder = file_get_contents(APPPATH.'modules/erp811_demo/classes/Service/Erp811/Seeder.php');
        $controller = file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');

        $this->assertStringContainsString('assert_guest_account()', $seeder);
        $this->assertStringContainsString("COUNT(DISTINCT u.id)", $seeder);
        $this->assertStringContainsString("join(array('roles_users', 'ru')", $seeder);
        $this->assertStringContainsString("guest_role", $controller);
        $this->assertStringContainsString("LOWER(TRIM(u.email))", $controller);
        $this->assertStringContainsString('clean_leaked_demo_users()', $controller);
    }

    public function test_browser_build_persists_progress_and_survives_disconnect()
    {
        $controller = file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');

        $this->assertStringContainsString('ignore_user_abort(TRUE)', $controller);
        $this->assertStringContainsString('LOCK_EX | LOCK_NB', $controller);
        $this->assertStringContainsString('action_progress', $controller);
        $this->assertStringContainsString('demo_last_run.log', $controller);
    }

    public function test_postgresql_driver_applies_configured_search_path()
    {
        $driver = file_get_contents(APPPATH.'modules/PostgreSQL/classes/Kohana/Database/PostgreSQL.php');
        $service = file_get_contents(APPPATH.'modules/erp811_demo/classes/Service/Erp811/Demo.php');

        $this->assertStringContainsString('SET search_path TO', $driver);
        $this->assertStringContainsString('ensure_schema_migration_registry', $service);
        $this->assertStringContainsString('.migrations_pg', $service);
        $this->assertStringContainsString("stats['failed']", $service);
        $this->assertStringContainsString('ORM::reset_database_instances()', $service);
    }

    public function test_orm_can_forget_connections_cached_before_demo_switch()
    {
        $cache = new \ReflectionProperty('Kohana_ORM', '_init_cache');
        $columns = new \ReflectionProperty('Kohana_ORM', '_column_cache');
        $cache->setAccessible(TRUE);
        $columns->setAccessible(TRUE);
        $previous = $cache->getValue();
        $previous_columns = $columns->getValue();

        try
        {
            $cache->setValue(NULL, array('user' => array('_db' => new \stdClass())));
            $columns->setValue(NULL, array('user' => array('id' => array('type' => 'int'))));
            \ORM::reset_database_instances();
            $this->assertSame(array(), $cache->getValue(), 'User/Role не должны сохранять соединение основной базы');
            $this->assertSame(array(), $columns->getValue(), 'Новая demo-схема должна перечитать структуру таблиц');
        }
        finally
        {
            $cache->setValue(NULL, $previous);
            $columns->setValue(NULL, $previous_columns);
        }
    }

    /**
     * Очистка сцены обязана отказаться работать не в своём database/schema:
     * иначе `demo --build` по ошибке снесёт учёт завода.
     */
    public function test_clear_stage_refuses_foreign_database()
    {
        $seeder  = \Service_Erp811_Seeder::instance();
        $current = \DB::query(\Database::SELECT, 'SELECT current_database()')->execute()->get('current_database');

        if ($current === $this->demo->database_name())
        {
            $this->markTestSkipped('Тесты идут в базе стенда: проверка «чужой базы» неприменима');
        }

        $this->expectException(\Kohana_Exception::class);

        $seeder->clear_stage();
    }

    // =========================================================================
    //  Сценарий: связность данных
    // =========================================================================

    public function test_scenario_has_all_sections()
    {
        $all = $this->scenario->all();

        foreach (array('company', 'clients', 'suppliers', 'materials', 'parts', 'centers', 'shifts',
                       'routes', 'downtime_reasons', 'defects', 'tour', 'landing') as $key)
        {
            $this->assertArrayHasKey($key, $all, 'В сценарии есть раздел «'.$key.'»');
            $this->assertNotEmpty($all[$key], 'Раздел «'.$key.'» не пустой');
        }
    }

    public function test_every_part_has_material_route_and_batch()
    {
        $materials = array();

        foreach ($this->scenario->materials() as $material)
        {
            $materials[$material['code']] = $material;
        }

        $this->assertNotEmpty($this->scenario->parts(), 'Детали объявлены');

        foreach ($this->scenario->parts() as $part)
        {
            $this->assertArrayHasKey($part['material'], $materials, 'Материал детали '.$part['code'].' есть в справочнике');
            $this->assertGreaterThan(0, (int) $part['batch'], 'Партия детали '.$part['code'].' больше нуля');

            $route = $this->scenario->route_for($materials[$part['material']]['polymer']);

            $this->assertNotEmpty($route, 'У детали '.$part['code'].' есть маршрут');

            $printing = FALSE;

            foreach ($route as $step)
            {
                $this->assertArrayHasKey('op_name', $step);
                $this->assertArrayHasKey('duration_sec', $step);

                if (strpos((string) $step['op_name'], 'Печать') === 0)
                {
                    $printing = TRUE;
                }
            }

            $this->assertTrue($printing, 'В маршруте детали '.$part['code'].' есть операция печати');
        }
    }

    public function test_downtime_reasons_match_shopfloor_dictionary()
    {
        $this->assertNotEmpty($this->scenario->downtime_reasons(), 'Причины простоев объявлены');

        foreach ($this->scenario->downtime_reasons() as $reason)
        {
            $this->assertArrayHasKey(
                $reason['reason'],
                \Model_Erp408_Operation::$downtime_reasons,
                'Причина «'.$reason['reason'].'» есть в справочнике цеха: произвольную причину сервис не примет'
            );
        }
    }

    public function test_tour_steps_are_ready_to_show()
    {
        $this->assertNotEmpty($this->scenario->tour(), 'Тур объявлен');

        foreach ($this->scenario->tour() as $step)
        {
            $this->assertNotEmpty($step['title'], 'У шага тура есть название');
            $this->assertSame('/', substr((string) $step['url'], 0, 1), 'Шаг тура ведёт на адрес системы');
            $this->assertNotEmpty($step['what'], 'У шага тура описано, что смотреть');
        }
    }

    // =========================================================================
    //  Демо-режим: только чтение
    // =========================================================================

    public function test_guard_lets_get_through()
    {
        $this->assertNull($this->guard_with_method('GET'), 'GET в демо-режиме разрешён');
        $this->assertNull($this->guard_with_method('HEAD'), 'HEAD в демо-режиме разрешён');
    }

    public function test_guard_blocks_writes()
    {
        $this->expectException(\HTTP_Exception_403::class);

        $this->guard_with_method('POST');
    }

    /**
     * Вход и выход из демо — свои действия стенда: иначе посетитель не смог бы
     * выйти из режима «только чтение».
     */
    public function test_guard_allows_demo_own_post()
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_URI'] = \Route::get('erp811_demo_exit')->uri(array());

        try
        {
            $this->assertNull($this->guard_with_method('POST'), 'Выход из демо разрешён');
        }
        finally
        {
            $_SERVER = $server;
        }
    }

    /**
     * Переключение роли внутри уже открытого стенда.
     *
     * `logout(TRUE)` уничтожает сессию вместе с идентификатором, а объект
     * сессии остаётся прежним: следующая запись (force_login) падает на
     * `session_regenerate_id()` без активной сессии, и посетитель остаётся без
     * входа — первый же переход по меню отвечал «Доступ ограничен». Поэтому
     * вход обязан сбросить сессию так, чтобы её можно было использовать снова.
     * Проверяем порядок вызовов в обеих точках входа: роль и гость.
     */
    public function test_role_switch_restarts_the_session()
    {
        $source = (string) file_get_contents(APPPATH.'modules/erp811_demo/classes/Service/Erp811/Demo.php');

        foreach (array('login_as_role', 'login_guest') as $method)
        {
            $start = strpos($source, 'function '.$method.'(');
            $this->assertNotSame(FALSE, $start, 'В сервисе стенда нет метода '.$method.'()');

            $next = strpos($source, 'function ', $start + 10);
            $body = substr($source, $start, ($next === FALSE ? strlen($source) : $next) - $start);

            $reset = strpos($body, 'reset_session(');
            $login = strpos($body, 'force_login(');

            $this->assertNotSame(FALSE, $reset, $method.'(): сессия посетителя не сбрасывается');
            $this->assertNotSame(FALSE, $login, $method.'(): нет входа force_login()');
            $this->assertLessThan($login, $reset,
                $method.'(): сброс сессии должен идти до force_login(), иначе вход падает');
        }

        // Сброс обязан не просто уничтожить сессию, а поднять её заново:
        // иначе записывать пользователя некуда.
        $this->assertMatchesRegularExpression(
            '#function reset_session\(\).*?Session::instance\(\)->restart\(\)#s',
            $source,
            'reset_session(): сессия не перезапускается после выхода');
    }

    /**
     * Неудачный вход не оставляет посетителя гостем с cookie стенда.
     *
     * Иначе страницы закрыты ролями, и первый же переход отвечает «Доступ
     * ограничен» вместо демонстрации.
     */
    public function test_failed_entry_rolls_the_stand_back()
    {
        $source = (string) file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');

        $this->assertMatchesRegularExpression(
            '#function _rollback\(\).*?\$this->_demo->leave\(\).*?\$this->?_demo->drop_session\(\)#s',
            $source,
            '_rollback(): демо-режим и сессия стенда должны сниматься вместе');

        $this->assertMatchesRegularExpression(
            '#_rollback\(\);\s*\n\s*\$this->_fail\(#',
            $source,
            'action_enter(): при неудачном входе стенд не откатывается');
    }

    /**
     * «Застрявшая» cookie стенда не превращает страницу входа в 403.
     *
     * Сценарий из жизни: посетитель вошёл в демо, вышел из учётной записи —
     * сессия снята, а cookie стенда осталась. Следующий вход упирался в
     * «демо-стенд работает только на чтение». Теперь POST на /auth/login
     * завершает демо-режим и возвращает на ту же страницу входа — уже в
     * рабочую базу. Проверяются оба адреса: с rewrite и через index.php.
     */
    public function test_login_page_frees_a_stuck_demo_cookie()
    {
        $cookie = (string) $this->demo->config('cookie', 'promerp_demo');
        $server = $_SERVER;

        $active = new \ReflectionProperty('Service_Erp811_Demo', '_active');
        $active->setAccessible(TRUE);
        $previous_active = $active->getValue($this->demo);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        try
        {
            foreach (array('/auth/login', '/index.php/auth/login') as $uri)
            {
                // Собираем «застрявший» демо-режим перед каждым адресом.
                $_SERVER['REQUEST_URI'] = $uri;
                $_COOKIE[$cookie]       = '1';
                $active->setValue($this->demo, TRUE);

                try
                {
                    $this->demo->guard_read_only();

                    throw new \RuntimeException('POST '.$uri.' не снял демо-режим');
                }
                catch (\HTTP_Exception_302 $e)
                {
                    $this->assertStringContainsString('/auth/login', (string) $e->headers('Location'),
                        'из демо-режима вход возвращает на страницу входа, а не на главную');
                }

                // В CLI active() всегда FALSE, поэтому проверяем саму cookie:
                // именно её наличие и создавало «застрявший» демо-режим.
                $this->assertArrayNotHasKey($cookie, $_COOKIE, 'после входа на страницу входа cookie стенда снята: '.$uri);
            }
        }
        finally
        {
            $_SERVER = $server;
            unset($_COOKIE[$cookie]);
            $active->setValue($this->demo, $previous_active);
        }
    }

    /**
     * Выход завершает демо-режим целиком: cookie снимается, а выход из
     * учётной записи вызывает это снятие.
     *
     * Иначе сессия закрыта, а cookie стенда осталась — и следующий вход
     * отвечает 403 «демо-стенд работает только на чтение».
     */
    public function test_logout_finishes_demo_mode()
    {
        $cookie = (string) $this->demo->config('cookie', 'promerp_demo');

        $_COOKIE[$cookie] = '1';

        $this->demo->leave();

        $this->assertArrayNotHasKey($cookie, $_COOKIE, 'после выхода из демо cookie стенда снята');

        // Контроллер выхода из учётной записи обязан звать этот выход: иначе
        // связь между двумя действиями потеряется при следующей правке.
        $source = (string) file_get_contents(APPPATH.'modules/erp100_base/classes/Controller/Erp100/Auth.php');

        $this->assertMatchesRegularExpression('#Service_Erp811_Demo.*?\$demo->leave\(\)#s', $source,
            'Controller_Erp100_Auth::action_logout должен завершать демо-режим');
    }

    /**
     * Роль `demo` (гость стенда) видит контур на чтение и не имеет ни одного
     * изменяющего права — даже если защита HTTP однажды откажет.
     */
    public function test_demo_role_reads_but_never_writes()
    {
        $acl = \Kohana::$config->load('acl')->as_array();

        foreach (array('erp811.view', 'erp801.view', 'erp401_wms.view_stock', 'erp402_production.view',
                       'erp408.view', 'erp405.view', 'erp403.view', 'erp404.view') as $ability)
        {
            $this->assertArrayHasKey($ability, $acl, 'Право '.$ability.' объявлено');
            $this->assertContains('demo', (array) $acl[$ability], 'Гость стенда видит '.$ability);
        }

        foreach ($acl as $ability => $roles)
        {
            $roles = (array) $roles;

            if ( ! in_array('demo', $roles, TRUE))
            {
                continue;
            }

            $action = substr((string) $ability, strrpos((string) $ability, '.') + 1);

            // Маски вида `erpNNN.*` роль demo не получает: под ними и запись.
            $this->assertNotSame('*', $action, 'Роль demo не получает маску модуля '.$ability);
            $this->assertTrue(
                (bool) preg_match('/view|list|read/', $action),
                'У роли demo нет изменяющего права '.$ability
            );
        }
    }

    // =========================================================================
    //  Агентский слой: инструменты только читающие
    // =========================================================================

    public function test_mcp_tools_are_read_only()
    {
        $declaration = \Kohana::find_file('modules/erp811_demo', 'mcp');

        $this->assertTrue($declaration !== FALSE, 'У модуля есть mcp.php');

        $tools = include $declaration;

        $this->assertIsArray($tools);
        $this->assertSame('erp811_demo', $tools['module']);
        $this->assertNotEmpty($tools['tools'], 'Инструменты объявлены');

        foreach ($tools['tools'] as $name => $tool)
        {
            $this->assertFalse($tool['mutating'], 'Инструмент '.$name.' не меняет данные стенда');
            $this->assertNotEmpty($tool['scope'], 'У инструмента '.$name.' объявлено право');
            $this->assertTrue(is_callable($tool['handler']), 'У инструмента '.$name.' есть обработчик');
        }
    }

    public function test_mcp_scope_is_declared_in_acl()
    {
        $acl = \Kohana::$config->load('acl')->as_array();

        $this->assertArrayHasKey('erp811.view', $acl, 'Право erp811.view объявлено в acl.php');
        $this->assertContains('admin', (array) $acl['erp811.view'], 'Администратор видит стенд');
    }

    // =========================================================================
    //  Маршруты и контроллеры
    // =========================================================================

    public function test_routes_point_to_existing_actions()
    {
        // Маршруты задают каталог и контроллер отдельно, поэтому класс
        // собирается из них так же, как это делает роутер: defaults() отдаёт
        // объявленные значения маршрута.
        $expectations = array(
            'erp811_demo_index' => array('Erp811', 'Demo', 'index', '/demo'),
            'erp811_demo_tour'  => array('Erp811', 'Demo', 'tour', '/demo/tour'),
            'erp811_demo_enter' => array('Erp811', 'Demo', 'enter', '/demo/enter'),
            'erp811_demo_exit'  => array('Erp811', 'Demo', 'exit', '/demo/exit'),
            'erp811_demo_api'   => array('Erp811', 'Api', 'status', '/demo/api/status'),
        );

        foreach ($expectations as $name => $expected)
        {
            $route = \Route::get($name);

            $this->assertNotNull($route, 'Маршрут '.$name.' объявлен');

            $defaults = $route->defaults();

            $class = 'Controller_'.(isset($defaults['directory']) ? $defaults['directory'] : '').'_'
                .(isset($defaults['controller']) ? $defaults['controller'] : '');

            $this->assertSame('Controller_'.$expected[0].'_'.$expected[1], $class, $name.' ведёт на нужный контроллер');
            $this->assertTrue(
                method_exists($class, 'action_'.$expected[2]),
                'У '.$class.' есть действие '.$expected[2]
            );
            $this->assertSame($expected[3], '/'.ltrim($route->uri(array()), '/'), $name.' разбирает адрес '.$expected[3]);
        }
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    // =========================================================================
    //  Маршрут осмотра ведёт только на существующие страницы
    // =========================================================================

    public function test_every_tour_step_has_a_working_url()
    {
        // Живой прогон нашёл в маршруте осмотра адрес, которого нет (`/stock`):
        // посетитель нажимал «дальше» и получал 404. Проверка дешёвая — ни
        // HTTP, ни базы, только сопоставление адреса с объявленными роутами.
        $broken = array();

        foreach ($this->scenario->tour() as $step)
        {
            $uri = isset($step['url']) ? (string) $step['url'] : '';

            if ($uri === '')
            {
                $broken[] = 'шаг '.$step['step'].': адрес не задан';
                continue;
            }

            $request = \Request::factory($uri);
            $matched = FALSE;

            foreach (\Route::all() as $route)
            {
                if ($route->matches($request))
                {
                    $matched = TRUE;
                    break;
                }
            }

            if ( ! $matched)
            {
                $broken[] = 'шаг '.$step['step'].' ('.$step['title'].'): '.$uri;
            }
        }

        $this->assertSame(array(), $broken, 'Каждый шаг тура ведёт на существующий маршрут');
        $this->assertGreaterThanOrEqual(8, count($this->scenario->tour()), 'Маршрут осмотра не пустой');
    }

    // =========================================================================
    //  Контур «сотрудники — CRM — составы — QR»: данные сценария связны
    // =========================================================================

    /**
     * Сценарий содержит разделы контура, а модули контура подключены к демо-базе:
     * без этого миграции не применятся и страницы стенда упадут.
     */
    public function test_contour_sections_and_modules_are_declared()
    {
        $all = $this->scenario->all();

        foreach (array('workforce', 'crm', 'structures', 'qr') as $key)
        {
            $this->assertNotEmpty($all[$key], 'В сценарии есть раздел «'.$key.'»');
        }

        $modules = (array) $this->demo->config('modules', array());

        foreach (array('erp310_workforce', 'erp221_crm', 'erp231_product_structure', 'erp199_sqr') as $module)
        {
            $this->assertContains($module, $modules, 'Модуль '.$module.' подключён к демо-базе');
        }

        $this->assertLessThan(
            array_search('erp221_crm', $modules, TRUE),
            array_search('erp301_counterparty', $modules, TRUE),
            'CRM применяется после справочника контрагентов'
        );
        $this->assertLessThan(
            array_search('erp231_product_structure', $modules, TRUE),
            array_search('erp228_tech_cards', $modules, TRUE),
            'Составы применяются после реестра и техкарт'
        );
    }

    /**
     * Сотрудники: руководитель заведён раньше подчинённого, подразделения и
     * должности существуют, табельные номера и учётные записи не повторяются.
     */
    public function test_workforce_scenario_is_consistent()
    {
        $config      = $this->scenario->workforce();
        $departments = array_column($config['departments'], 'code');
        $positions   = array_column($config['positions'], 'code', 'code');

        // Должности миграции модуля используются как есть
        $positions['PROD-MANAGER'] = 'PROD-MANAGER';
        $positions['STOREKEEPER']  = 'STOREKEEPER';

        $seen    = array();
        $users   = array();
        $numbers = array();

        foreach ($config['employees'] as $employee)
        {
            $this->assertNotContains($employee['no'], $numbers, 'Табельный номер '.$employee['no'].' не повторяется');
            $numbers[] = $employee['no'];

            $this->assertContains($employee['dept'], $departments, $employee['no'].': подразделение объявлено');
            $this->assertArrayHasKey($employee['pos'], $positions, $employee['no'].': должность объявлена');

            if ($employee['manager'] !== '')
            {
                $this->assertContains($employee['manager'], $seen, $employee['no'].': руководитель заведён раньше подчинённого');
            }

            if ( ! empty($employee['user']))
            {
                $this->assertNotContains($employee['user'], $users, 'Учётная запись '.$employee['user'].' привязана к одному сотруднику');
                $users[] = $employee['user'];
            }

            $seen[] = $employee['no'];
        }

        foreach ($config['staff_units'] as $unit)
        {
            $this->assertContains($unit['department'], $departments);
            $this->assertArrayHasKey($unit['position'], $positions);
        }
    }

    /**
     * CRM: клиенты и детали существуют, стадии идут по порядку воронки, у
     * отказа есть причина, у задачи — срок в будущем.
     */
    public function test_crm_scenario_is_consistent()
    {
        $config  = $this->scenario->crm();
        $clients = array_column($this->scenario->clients(), 'code');
        $parts   = array_column($this->scenario->parts(), 'code');
        $order   = array('new', 'qualify', 'proposal', 'negotiation');
        $reasons = array('price', 'competitor', 'timing', 'no_budget', 'no_decision', 'no_contact', 'other');
        $stages  = array();

        foreach ($config['deals'] as $deal)
        {
            $this->assertContains($deal['client'], $clients, 'Сделка «'.$deal['title'].'»: клиент из сценария');
            $this->assertTrue(isset($deal['amount']) OR in_array(isset($deal['part']) ? $deal['part'] : '', $parts, TRUE),
                'Сделка «'.$deal['title'].'»: сумма задана явно или считается по детали каталога');

            $path = $deal['path'];
            $last = end($path);

            $this->assertSame('new', $path[0], 'Сделка начинается с новой заявки');

            foreach (array_slice($path, 0, -1) as $index => $stage)
            {
                $this->assertSame($order[$index], $stage, 'Сделка «'.$deal['title'].'»: стадии идут по порядку воронки');
            }

            if ($last === 'lost')
            {
                $this->assertContains(isset($deal['loss']) ? $deal['loss'] : '', $reasons, 'У отказа есть причина');
            }

            $this->assertGreaterThanOrEqual($deal['last_days'], $deal['created_days'], 'Создана не позже последнего события');

            foreach ($deal['acts'] as $act)
            {
                if ($act['status'] === 'pending')
                {
                    $this->assertLessThan(0, $act['days'], 'Задача «'.$act['subject'].'» назначена на будущее');
                }
            }

            $stages[$last] = TRUE;
        }

        foreach (array('won', 'lost', 'negotiation', 'proposal', 'qualify', 'new') as $stage)
        {
            $this->assertArrayHasKey($stage, $stages, 'В воронке есть сделка на стадии «'.$stage.'»');
        }

        foreach (array_keys($config['profiles']) as $code)
        {
            $this->assertContains($code, $clients, 'Профиль относится к клиенту сценария');
        }
    }

    /**
     * Составы и QR ссылаются на материалы, детали и сотрудников сценария.
     */
    public function test_structures_and_qr_scenario_is_consistent()
    {
        $parts     = array_column($this->scenario->parts(), 'code');
        $materials = array_column($this->scenario->materials(), 'code');
        $structure = $this->scenario->structures();
        $qr        = $this->scenario->qr();

        $this->assertContains($structure['consumable'], $materials, 'Расходник состава есть среди материалов');

        foreach (array_keys($structure['scrap']) as $code)
        {
            $this->assertContains($code, $parts);
        }

        foreach (array_keys($structure['revisions']) as $code)
        {
            $this->assertContains($code, $parts);
        }

        foreach ($structure['kit']['lines'] as $line)
        {
            $this->assertContains($line['part'], $parts, 'Комплект состоит из деталей каталога');
        }

        foreach ($qr['items'] as $code)
        {
            $this->assertContains($code, array_merge($parts, $materials), 'Наклейка на позицию каталога');
        }

        $numbers = array_column($this->scenario->workforce()['employees'], 'no');

        foreach ($qr['employees'] as $number)
        {
            $this->assertContains($number, $numbers, 'Бейдж выдан сотруднику сценария');
        }

        $keys = array_column($qr['objects'], 'key');
        $this->assertSame(count($keys), count(array_unique($keys)), 'Ключи свободных объектов уникальны');

        foreach ($qr['objects'] as $object)
        {
            $this->assertContains($object['type'], array('location', 'tool', 'container', 'document'),
                'Свободный объект — локальный тип erp199 (склад как цель не предусмотрен)');
        }
    }

    /**
     * Лендинг собран из общих компонентов, а не из ручной вёрстки таблиц и плиток.
     */
    public function test_landing_uses_standard_components()
    {
        $view = file_get_contents(APPPATH.'modules/erp811_demo/views/erp811/demo.php');

        $this->assertStringContainsString("Component::render('kpi'", $view);
        $this->assertStringContainsString("Component::render('table'", $view);
        $this->assertStringContainsString("Component::begin('card'", $view);
        $this->assertStringNotContainsString('<table', $view, 'Таблица — только через компонент');
        $this->assertStringNotContainsString('indigo', $view, 'Акцентный цвет — как в остальных модулях');
    }

    // =========================================================================
    //  Роли осмотра: витрина на главной и вход под ролью
    // =========================================================================

    /**
     * Список ролей осмотра пригоден для показа: у каждой роли есть код,
     * название, подсказка, иконка и осмысленный адрес старта.
     */
    public function test_roleplay_entries_are_ready_to_show()
    {
        $entries = $this->demo->roleplay();

        $this->assertNotEmpty($entries, 'Стенд должен предлагать хотя бы одну роль осмотра');

        foreach ($entries as $entry)
        {
            foreach (array('role', 'email', 'title', 'hint', 'start', 'icon') as $key)
            {
                $this->assertArrayHasKey($key, $entry, 'У роли осмотра есть поле '.$key);
                $this->assertNotSame('', (string) $entry[$key], 'Поле '.$key.' роли осмотра не пустое');
            }

            $this->assertStringStartsWith('/', $entry['start'], 'Старт роли — внутренний адрес: '.$entry['role']);
        }
    }

    /**
     * Роль ищется по коду, а неизвестный код не даёт войти: иначе адрес из
     * браузера позволил бы выбрать любую учётную запись демо-базы.
     */
    public function test_roleplay_entry_is_found_by_code_only()
    {
        $entries = $this->demo->roleplay();

        $this->assertNotEmpty($entries, 'Список ролей не пуст');

        $first = reset($entries);

        $this->assertSame($first['role'], $this->demo->roleplay_entry($first['role'])['role']);
        $this->assertNull($this->demo->roleplay_entry('root'), 'Произвольная роль не принимается');
        $this->assertFalse($this->demo->login_as_role('root'), 'Войти под неизвестной ролью нельзя');
    }

    /**
     * Каждая роль осмотра объявлена в ACL: роль, которой нет в правах, войдёт
     * в систему и не увидит ни одного раздела — витрина превратится в 403.
     */
    public function test_roleplay_roles_are_declared_in_acl()
    {
        $acl = \Kohana::$config->load('acl')->as_array();

        $known = array();
        foreach ($acl as $roles)
        {
            foreach ((array) $roles as $role)
            {
                $known[$role] = TRUE;
            }
        }

        foreach ($this->demo->roleplay() as $entry)
        {
            $this->assertArrayHasKey(
                $entry['role'],
                $known,
                'Роль осмотра «'.$entry['role'].'» должна быть в application/config/acl.php'
            );
        }
    }

    /**
     * Роль приводит гостя туда, куда ей действительно можно: адрес старта
     * виден в её же меню. Так «войти под ролью» не заканчивается отказом.
     */
    public function test_roleplay_start_pages_are_visible_for_their_roles()
    {
        $demo    = \Kohana::$config->load('demo');
        $by_mail = array();

        foreach ((array) $demo->get('users', array()) as $user)
        {
            $by_mail[mb_strtolower((string) $user['email'])] = (array) $user['roles'];
        }

        $by_mail[mb_strtolower((string) $demo->get('guest_email'))] = array('login', (string) $demo->get('guest_role'));

        foreach ($this->demo->roleplay() as $entry)
        {
            $email = mb_strtolower((string) $entry['email']);

            $this->assertArrayHasKey($email, $by_mail, 'Роль осмотра ссылается на учётную запись стенда: '.$email);

            $hrefs = array();

            foreach (\Ui_Navigation::categories($by_mail[$email]) as $category)
            {
                foreach ($category['items'] as $item)
                {
                    $hrefs[] = $item['href'];
                }
            }

            $this->assertContains(
                $entry['start'],
                $hrefs,
                'Стартовый адрес роли «'.$entry['role'].'» ('.$entry['start'].') должен быть виден ей в меню'
            );
        }
    }

    /**
     * Главная зовёт в демо и предлагает выбор роли, а вход выполняет
     * контроллер стенда — POST-ом, потому что это смена состояния.
     */
    public function test_homepage_invites_guest_and_posts_role_to_stand()
    {
        $welcome    = file_get_contents(APPPATH.'classes/Controller/Welcome.php');
        $view       = file_get_contents(APPPATH.'views/welcome/index.php');
        $controller = file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');

        $this->assertStringContainsString('_demo_invite()', $welcome);
        $this->assertStringContainsString('roleplay()', $welcome);
        $this->assertStringContainsString("->set('demo', \$demo)", $welcome);

        $this->assertStringContainsString('name="role"', $view, 'Витрина предлагает выбор роли');
        $this->assertStringContainsString('action="/demo/enter"', $view, 'Витрина входит в демо POST-ом');
        $this->assertStringContainsString('method="post"', $view);

        $this->assertStringContainsString("post('role')", $controller);
        $this->assertStringContainsString('login_as_role(', $controller);
        $this->assertStringContainsString('roleplay_entry(', $controller);
    }

    /**
     * Лендинг стенда предлагает тот же выбор роли: на /demo приходят из меню и
     * из поиска, минуя главную, и витрины без роли не показывают рабочее место.
     */
    public function test_stand_landing_offers_role_choice()
    {
        $controller = file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');
        $view       = file_get_contents(APPPATH.'modules/erp811_demo/views/erp811/demo.php');

        $this->assertStringContainsString("->set('roleplay', \$this->_demo->roleplay())", $controller);
        $this->assertStringContainsString('name="role"', $view);
        $this->assertStringContainsString('$roleplay', $view);
    }

    /**
     * Роль проверяется по конфигу стенда, а не берётся из формы: иначе гость
     * выбрал бы учётную запись администратора демо-базы.
     */
    public function test_controller_accepts_only_known_roles()
    {
        $entries = $this->demo->roleplay();

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry)
        {
            $this->assertNotNull($this->demo->roleplay_entry($entry['role']));
        }

        $this->assertNull($this->demo->roleplay_entry('admin@demo.promerp.local'), 'Почта не является кодом роли');
    }

    /**
     * Прогнать защиту демо-режима с заданным методом запроса.
     *
     * `active()` в CLI всегда FALSE (cookie не приходит), поэтому для проверки
     * переключаем признак демо-режима напрямую — так тест проверяет именно
     * правило «не-GET → 403», а не наличие cookie в консоли.
     *
     * @param   string  $method
     * @return  mixed
     */
    protected function guard_with_method($method)
    {
        $active = new \ReflectionProperty('Service_Erp811_Demo', '_active');
        $active->setAccessible(TRUE);
        $previous = $active->getValue($this->demo);
        $active->setValue($this->demo, TRUE);

        $server = $_SERVER['REQUEST_METHOD'] ?? NULL;
        $_SERVER['REQUEST_METHOD'] = $method;

        try
        {
            return $this->demo->guard_read_only();
        }
        finally
        {
            $_SERVER['REQUEST_METHOD'] = $server;
            $active->setValue($this->demo, $previous);
        }
    }
}
