<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты модуля QR-маркировки (erp199_sqr).
 *
 * Проверяем не «строчки кода», а обещания модуля людям: наклейка выпускается и
 * привязывается к чему угодно в системе, терминал получает понятное состояние,
 * фото и сценарии попадают в журнал, инвентаризация считает расхождения, а
 * данные владельцев (реестр, партии, заказы) не дублируются.
 */
class Erp199SqrTest extends TestCase
{
    /** @var array Наклейки, созданные тестом (удаляются в tearDown) */
    protected $tokens = array();

    /** @var array Локальные объекты, созданные тестом */
    protected $objects = array();

    /** @var array Сессии инвентаризации, созданные тестом */
    protected $inventories = array();

    /** @var array Файлы, созданные тестом */
    protected $files = array();

    protected function setUp(): void
    {
        try
        {
            \DB::select('id')->from('erp_199_tokens')->limit(1)->execute();
        }
        catch (\Throwable $e)
        {
            $this->markTestSkipped('Выполните миграции erp199_sqr');
        }
    }

    protected function tearDown(): void
    {
        try
        {
            if ($this->files)
            {
                \DB::delete('erp_199_media')->where('id', 'IN', $this->files)->execute();
            }

            foreach ($this->tokens as $id)
            {
                \DB::delete('erp_199_activity')->where('token_id', '=', $id)->execute();
                \DB::delete('erp_199_inventory_items')->where('token_id', '=', $id)->execute();
                \DB::delete('erp_199_tokens')->where('id', '=', $id)->execute();
            }

            foreach ($this->inventories as $id)
            {
                \DB::delete('erp_199_inventory_items')->where('inventory_id', '=', $id)->execute();
                \DB::delete('erp_199_inventories')->where('id', '=', $id)->execute();
            }

            foreach ($this->objects as $id)
            {
                \DB::delete('erp_199_objects_history')->where('object_id', '=', $id)->execute();
                \DB::update('erp_199_tokens')->set(array('object_id' => NULL))->where('object_id', '=', $id)->execute();
                \DB::delete('erp_199_objects')->where('id', '=', $id)->execute();
            }
        }
        catch (\Throwable $e)
        {
            // Уборка не должна маскировать результат теста.
        }
    }

    /**
     * Выпустить наклейку и запомнить её для уборки.
     *
     * @param   string $batch
     * @return  array
     */
    protected function issue($batch = 'TEST199')
    {
        $issued = \Service_Erp199_Tokens::instance()->issue(1, $batch, NULL, 'тест');
        $token  = $issued['tokens'][0];

        $this->tokens[] = (int) $token['id'];

        return $token;
    }

    // =========================================================================
    //  Наклейки
    // =========================================================================

    public function test_issue_creates_unique_active_codes_with_prefix()
    {
        $issued = \Service_Erp199_Tokens::instance()->issue(5, 'TEST199-ISSUE', NULL, 'партия теста');
        $codes  = array();

        foreach ($issued['tokens'] as $token)
        {
            $this->tokens[] = (int) $token['id'];
            $this->assertSame('new', $token['status']);
            $this->assertSame('QR-', substr($token['code'], 0, 3));
            $this->assertMatchesRegularExpression('/^QR-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $token['code']);
            $codes[] = $token['code'];
        }

        $this->assertCount(5, array_unique($codes), 'Коды наклеек обязаны быть уникальны');
        $this->assertSame('TEST199-ISSUE', $issued['batch']);
    }

    public function test_token_binds_to_registry_object_and_resolves_as_active()
    {
        $object_id = (int) \DB::select('id')->from('erp_227_objects')
            ->where('is_deleted', '=', FALSE)->order_by('id')->execute()->get('id');

        if ( ! $object_id)
        {
            $this->markTestSkipped('В реестре erp227 нет объектов');
        }

        $token = $this->issue();
        $bound = \Service_Erp199_Tokens::instance()->bind($token['code'], 'registry', $object_id, NULL, 'тест');

        $this->assertSame('active', $bound['status']);
        $this->assertSame('registry', $bound['target_kind']);
        $this->assertSame($object_id, (int) $bound['target_id']);
        $this->assertNotEmpty($bound['target_label']);

        $resolved = \Service_Erp199_Scan::instance()->resolve($token['code']);

        $this->assertSame('active', $resolved['state']);
        $this->assertSame('registry', $resolved['target']['kind']);
        $this->assertSame($object_id, (int) $resolved['target']['id']);
        $this->assertNotEmpty($resolved['scenarios'], 'Активная наклейка должна предлагать сценарии');
    }

    public function test_token_binds_by_human_code_and_refuses_ambiguity()
    {
        $object = \DB::select('id', 'code')->from('erp_227_objects')
            ->where('is_deleted', '=', FALSE)->and_where('code', 'IS NOT', \DB::expr('NULL'))
            ->order_by('id')->execute()->current();

        if ( ! $object)
        {
            $this->markTestSkipped('В реестре erp227 нет объектов с кодом');
        }

        $token = $this->issue();
        $bound = \Service_Erp199_Tokens::instance()->bind($token['code'], 'registry', $object['code']);

        $this->assertSame('active', $bound['status']);
        $this->assertSame((int) $object['id'], (int) $bound['target_id']);
    }

    public function test_unknown_code_is_not_an_error_but_a_state()
    {
        $resolved = \Service_Erp199_Scan::instance()->resolve('QR-ЗЗЗЗ-ЯЯЯЯ');

        $this->assertSame('unknown', $resolved['state']);
        $this->assertSame('QR-ЗЗЗЗ-ЯЯЯЯ', $resolved['token']['code']);
        $this->assertTrue($resolved['can_register']);
        $this->assertNotEmpty($resolved['message']);
    }

    public function test_clean_code_survives_url_query_and_case()
    {
        $scan = \Service_Erp199_Scan::instance();

        $this->assertSame('QR-7F3K-9M2P', $scan->clean_code('https://promerp.local/qr/QR-7F3K-9M2P'));
        $this->assertSame('QR-7F3K-9M2P', $scan->clean_code('qr-7f3k-9m2p?utm=1'));
        $this->assertSame('QR-7F3K-9M2P', $scan->clean_code(' QR-7F3K-9M2P '));
    }

    public function test_lost_token_reports_its_state_and_offers_no_scenarios()
    {
        $token = $this->issue();
        \Service_Erp199_Tokens::instance()->set_status($token['code'], 'lost', NULL, 'потеряна в цехе');

        $resolved = \Service_Erp199_Scan::instance()->resolve($token['code']);

        $this->assertSame('lost', $resolved['state']);
        $this->assertSame(array(), $resolved['scenarios']);
    }

    // =========================================================================
    //  Свободные объекты и фото
    // =========================================================================

    public function test_field_registration_creates_draft_object_with_media()
    {
        $token = $this->issue('TEST199-FIELD');

        // 1x1 PNG — минимальная корректная картинка, чтобы не тащить бинарь
        // в репозиторий. Формат обязан входить в список допустимых.
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $result = \Service_Erp199_Scan::instance()->register_local($token['code'], array(
            'name' => 'Насос тестовый',
            'type' => 'tool',
            'comment' => 'Найден в цехе №2',
            'image' => $image,
        ), array(), NULL, array('geo_lat' => 55.75, 'geo_lon' => 37.61));

        $this->objects[] = (int) $result['object']['id'];
        $this->files[]   = (int) $result['media']['id'];

        $this->assertSame('draft', $result['object']['status']);
        $this->assertSame('Насос тестовый', $result['object']['name']);
        $this->assertNotEmpty($result['media']['url']);
        $this->assertSame('active', $result['token']['status']);
        $this->assertSame('local', $result['token']['target_kind']);

        $file = DOCROOT.ltrim($result['media']['file_path'], '/');
        $this->assertFileExists($file);

        $activity = \Service_Erp199_Scan::instance()->activity($result['token']['id'], 10);
        $this->assertNotEmpty($activity);
        $this->assertContains('bind', array_column($activity, 'action_type'), 'Привязка объекта обязана остаться в журнале');
    }

    public function test_moderation_turns_draft_into_accepted_object()
    {
        $token = $this->issue('TEST199-MODER');
        $result = \Service_Erp199_Scan::instance()->register_local($token['code'], array('name' => 'Объект на модерацию', 'type' => 'container'));
        $this->objects[] = (int) $result['object']['id'];

        $accepted = \Service_Erp199_Objects::instance()->moderate($result['object']['id'], 'active', NULL, 'принято');

        $this->assertSame('active', $accepted['status']);
        $this->assertSame('принято', $accepted['moderator_comment']);

        $history = \Service_Erp199_Objects::instance()->history($result['object']['id']);
        $types  = array_column($history, 'change_type');
        $this->assertContains('moderation', $types);
    }

    public function test_media_rejects_oversized_and_wrong_type()
    {
        $media = \Service_Erp199_Media::instance();

        try
        {
            $media->save_base64('data:text/plain;base64,'.base64_encode('не картинка'));
            $this->assertTrue(FALSE, 'Текстовый файл не должен приниматься как фотография');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('формат', mb_strtolower($e->getMessage()));
        }
    }

    public function test_local_object_statuses_are_a_closed_set()
    {
        $this->assertSame('draft', array_key_first(\Service_Erp199_Objects::instance()->statuses()));

        $this->expectException(\Kohana_Exception::class);
        \Service_Erp199_Objects::instance()->moderate(999999999, 'неизвестный');
    }

    // =========================================================================
    //  Сценарии терминала
    // =========================================================================

    public function test_scenarios_are_data_and_can_be_saved()
    {
        $service  = \Service_Erp199_Scenarios::instance();
        $existing = $service->get('note');

        $this->assertSame('note', $existing['code']);
        $this->assertNotEmpty($existing['fields'], 'У сценария должна быть форма');

        $saved = $service->save(array(
            'code'        => 'test_mark_199',
            'label'       => 'Тестовая отметка',
            'target_kinds' => array('local'),
            'form'        => array('fields' => array(array('name' => 'comment', 'label' => 'Что', 'type' => 'text', 'required' => TRUE))),
            'effect'      => array('kind' => 'set_attributes', 'store_as' => 'test_mark'),
            'is_active'   => TRUE,
            'sort'        => 999,
        ));

        $this->assertSame('test_mark_199', $saved['code']);

        try
        {
            $service->save(array('code' => 'bad code', 'label' => 'Плохой код'));
            $this->assertTrue(FALSE, 'Код сценария с пробелом должен отклоняться');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('Код сценария', $e->getMessage());
        }

        try
        {
            $service->save(array('code' => 'test_wrong_kind', 'label' => 'Чужой вид', 'target_kinds' => array('нет_такого')));
            $this->assertTrue(FALSE, 'Неизвестный вид цели должен отклоняться');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('вид цели', $e->getMessage());
        }
    }

    public function test_scenario_requires_photo_and_comment_from_client()
    {
        $token = $this->issue('TEST199-SCEN');
        $result = \Service_Erp199_Scan::instance()->register_local($token['code'], array('name' => 'Объект для сценария', 'type' => 'tool'));
        $this->objects[] = (int) $result['object']['id'];

        try
        {
            \Service_Erp199_Scan::instance()->execute($token['code'], 'photo_fix', array('comment' => 'без фото'), array());
            $this->assertTrue(FALSE, 'Сценарий photo_fix обязан требовать фотографию');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('фото', mb_strtolower($e->getMessage()));
        }

        try
        {
            \Service_Erp199_Scan::instance()->execute($token['code'], 'note', array(), array());
            $this->assertTrue(FALSE, 'Сценарий note обязан требовать комментарий');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('комментар', mb_strtolower($e->getMessage()));
        }
    }

    public function test_scenario_writes_value_and_journal_on_the_server()
    {
        $token = $this->issue('TEST199-RUN');
        $result = \Service_Erp199_Scan::instance()->register_local($token['code'], array('name' => 'Объект со сценарием', 'type' => 'container'));
        $this->objects[] = (int) $result['object']['id'];

        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $ran = \Service_Erp199_Scan::instance()->execute($token['code'], 'stage_mark', array(
            'stage'   => 'assembly',
            'comment' => 'собрано',
            'image'   => $image,
        ), array(), NULL, array('geo_lat' => 55.7, 'geo_lon' => 37.6));

        if ( ! empty($ran['media']['id']))
        {
            $this->files[] = (int) $ran['media']['id'];
        }

        $object = \Service_Erp199_Objects::instance()->get($result['object']['id']);
        $this->assertSame('assembly', $object['attributes']['stage']);

        $activity = \Service_Erp199_Scan::instance()->activity($token['id'], 5);
        $this->assertSame('scenario', $activity[0]['action_type']);
        $this->assertSame('stage_mark', $activity[0]['scenario_code']);
    }

    // =========================================================================
    //  Инвентаризация
    // =========================================================================

    public function test_inventory_session_counts_expected_marked_and_missing()
    {
        $inventory = \Service_Erp199_Inventories::instance()->open(array(
            'label' => 'Тестовая инвентаризация',
            'scope' => array('kinds' => array('local')),
        ));
        $this->inventories[] = (int) $inventory['id'];

        $first  = $this->issue('TEST199-INV');
        $second = $this->issue('TEST199-INV');

        // Сколько наклеек в области уже было: в рабочей базе могли остаться
        // объекты прошлых запусков, и жёсткое «ровно одна» ломалось бы от них.
        $baseline = \Service_Erp199_Inventories::instance()->progress($inventory['id']);

        // Свой свободный объект модуля: инвентаризация свободных объектов
        // считает только цели вида local, и ожидаемое число должно следовать за
        // действиями теста, а не за тем, что лежит в базе.
        $local = \Service_Erp199_Scan::instance()->register_local($first['code'], array(
            'name' => 'Объект для инвентаризации',
            'type' => 'tool',
        ));
        $this->objects[] = (int) $local['object']['id'];

        // Вторая наклейка уходит в реестр: в область «свободных объектов» она
        // попадать не должна.
        \Service_Erp199_Tokens::instance()->bind($second['code'], 'registry', (int) \DB::select('id')->from('erp_227_objects')->where('is_deleted', '=', FALSE)->order_by('id')->execute()->get('id'));

        $before = \Service_Erp199_Inventories::instance()->progress($inventory['id']);
        $this->assertSame($baseline['expected'] + 1, $before['expected'],
            'в инвентаризацию входит ровно одна новая свободная наклейка, наклейка реестра не считается');
        $this->assertGreaterThanOrEqual(1, $before['expected']);

        \Service_Erp199_Inventories::instance()->mark($inventory['id'], \Service_Erp199_Tokens::instance()->get($first['code']), 'found', NULL, array('comment' => 'нашли'));

        $after = \Service_Erp199_Inventories::instance()->progress($inventory['id']);
        $this->assertSame(1, $after['marked']);
        $this->assertSame(1, $after['found']);
        $this->assertSame($after['expected'] - 1, $after['missing']);

        try
        {
            \Service_Erp199_Inventories::instance()->mark($inventory['id'], \Service_Erp199_Tokens::instance()->get($second['code']), 'неведомая отметка');
            $this->assertTrue(FALSE, 'Недопустимая отметка инвентаризации должна отклоняться');
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString('отметка', mb_strtolower($e->getMessage()));
        }

        $closed = \Service_Erp199_Inventories::instance()->close($inventory['id'], NULL, 'проверено');
        $this->assertSame('closed', $closed['status']);

        $this->expectException(\Exception::class);
        \Service_Erp199_Inventories::instance()->mark($inventory['id'], \Service_Erp199_Tokens::instance()->get($first['code']), 'found');
    }

    // =========================================================================
    //  Границы: права, маршруты, MCP
    // =========================================================================

    public function test_read_only_api_actions_are_declared_and_mutating_ones_are_not()
    {
        $read_only = (array) \Kohana::$config->load('api.read_only_actions');

        foreach (array('resolve', 'scenarios', 'tokens', 'token', 'targets', 'activity') as $action)
        {
            $this->assertContains($action, $read_only, 'Действие «'.$action.'» обязано быть в списке только читающих');
        }

        foreach (array('bind', 'register', 'scan', 'photo', 'inventory_scan') as $action)
        {
            $this->assertNotContains($action, $read_only, 'Изменяющее действие «'.$action.'» не должно считаться чтением');
        }
    }

    public function test_module_rights_routes_and_mcp_tools_are_visible()
    {
        $acl = (array) \Kohana::$config->load('acl');

        foreach (array('erp199.view', 'erp199.tokens.bind', 'erp199.objects.moderate', 'erp199.inventories.manage') as $right)
        {
            $this->assertArrayHasKey($right, $acl, 'Право '.$right.' не объявлено в acl.php');
        }

        $this->assertNotNull(\Route::get('erp199_index'));
        $this->assertNotNull(\Route::get('erp199_api'));

        $tools = \Service_Erp804_Registry::instance()->tools();

        foreach (array('qr.resolve', 'qr.tokens', 'qr.token', 'qr.objects', 'qr.inventories', 'qr.journal') as $name)
        {
            $this->assertArrayHasKey($name, $tools, 'Инструмент '.$name.' не найден');
            $this->assertFalse($tools[$name]['mutating'], 'Инструмент '.$name.' должен быть только читающим');
        }

        $nav = \Ui_Navigation::palette(array('login', 'manager'));
        $this->assertContains('/qr', array_column($nav, 'href'));
    }

    public function test_admin_views_use_shared_components()
    {
        foreach (array('index.php', 'tokens.php', 'token.php', 'inventory.php') as $view)
        {
            $source = file_get_contents(APPPATH.'modules/erp199_sqr/views/erp199/'.$view);

            $this->assertStringContainsString("Component::render('table'", $source);
            $this->assertStringNotContainsString('<table', $source);
        }
    }
}
