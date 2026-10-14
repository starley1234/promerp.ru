<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Наполнение демонстрационного стенда (erp811_demo).
 *
 * Стенд собирается **через сервисы модулей**, а не прямыми вставками. Это
 * дороже по коду, но даёт то, ради чего демонстрация существует: данные
 * проходят те же правила, что в бою — резервы и движения склада, версии
 * техкарт, переходы статусов заказа, карантин брака, согласование заявки,
 * приёмка с входным контролем. Если правило сломано, стенд это покажет
 * (сборка честно пишет, что не получилось), а не спрячет за красивым слепком.
 *
 * Прямые вставки остаются только там, где сервис не умеет сказать «задним
 * числом»: исторические даты движений (приход филамента в прошлом месяце) —
 * иначе витрины аналитики показали бы пустоту.
 *
 * Детерминированность: генератор случайных чисел инициализируется одним seed,
 * поэтому один и тот же сценарий даёт один и тот же стенд — демонстрации можно
 * повторять, а тесты — сверять. Даты считаются от «сегодня», поэтому стенд
 * всегда выглядит живым.
 *
 * @package    Erp811
 * @category   Services
 */
class Service_Erp811_Seeder
{
    /** @var Service_Erp811_Seeder|null */
    protected static $_instance;

    /** @var Service_Erp811_Demo */
    protected $_demo;

    /** @var Service_Erp811_Scenario */
    protected $_scenario;

    /** @var callable|null Как сообщать о ходе сборки */
    protected $_logger;

    /** @var array Что собрано: счётчики по слоям */
    protected $_counts = array();

    /** @var array Что не получилось: слой, объект, причина */
    protected $_problems = array();

    /** @var array Номенклатура: код позиции сценария => id объекта реестра */
    protected $_items = array();

    /** @var array Контрагенты: код => id */
    protected $_parties = array();

    /** @var array Склады: код => id */
    protected $_warehouses = array();

    /** @var array Центры: код => id */
    protected $_centers = array();

    /** @var array Техкарты: код детали => id карты */
    protected $_cards = array();

    /** @var array Заказы: id заказа => данные сценария */
    protected $_orders = array();

    /** @var array Сколько материала пришло за историю: код => количество */
    protected $_supply = array();

    /** @var array Контроль качества по заказу: id заказа => id контроля */
    protected $_inspections = array();

    /** @var array|null План заказов на историю (считается один раз) */
    protected $_plan = NULL;

    /** @var int|null Сборщик: пользователь-администратор демо-базы */
    protected $_admin_id;

    /** @var int */
    protected $_seed = 20260916;

    /**
     * @return  Service_Erp811_Seeder
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct()
    {
        $this->_demo     = Service_Erp811_Demo::instance();
        $this->_scenario = Service_Erp811_Scenario::instance();
    }

    // =========================================================================
    //  План и сборка
    // =========================================================================

    /**
     * Таблицы, которые очищаются перед сборкой: «сцена» стенда.
     *
     * Миграции модулей приносят вместе со схемой свои демонстрационные данные
     * (электронные компоненты, тестовые заказы) — они нужны модулям для
     * самопроверки, но на стенде про 3D-печать это мусор из другой истории.
     * Поэтому сцена собирается с нуля: сначала очистка, затем сценарий.
     *
     * Список ведётся явно (`TRUNCATE` одним запросом, поэтому внешние ключи и
     * последовательности не мешают). Пользователи, роли, типы объектов реестра,
     * шаблоны печатных форм, пороги и журнал сборок не трогаются: это не данные
     * сценария, а оснастка стенда.
     *
     * @return  array
     */
    protected function stage_tables()
    {
        return array(
            // склад
            'erp_401_balances', 'erp_401_movements', 'erp_401_warehouses',
            // производство
            'erp_402_order_items', 'erp_402_orders',
            // закупки
            'erp_403_receipt_lines', 'erp_403_receipts',
            'erp_403_order_lines', 'erp_403_orders',
            'erp_403_request_lines', 'erp_403_requests',
            'erp_403_prices', 'erp_403_suppliers',
            // качество
            'erp_405_decisions', 'erp_405_defects', 'erp_405_inspections', 'erp_405_defect_codes',
            // планирование
            'erp_407_requirements', 'erp_407_proposals', 'erp_407_program_lines', 'erp_407_programs',
            // цех
            'erp_408_calendar', 'erp_408_worklogs', 'erp_408_operations',
            'erp_408_shifts', 'erp_408_work_centers',
            // документы (шаблоны остаются — они часть оснастки)
            'erp_404_prints', 'erp_404_documents',
            // справочники
            'erp_301_contacts', 'erp_301_contracts', 'erp_301_counterparties', 'erp_301_requisites',
            'erp_228_tech_cards', 'erp_228_tech_documents', 'erp_228_tech_operations',
            'erp_227_objects', 'erp_227_object_links', 'erp_227_sequences',
            // сотрудники: только данные сценария. Подразделения и должности —
            // справочник миграции модуля, он дополняется по коду и не чистится
            'erp_310_assignments', 'erp_310_staff_units', 'erp_310_employees',
            // CRM: сделки и клиентские карточки. Стадии и причины отказов —
            // справочники миграции, `default_stage()` без них бросает исключение
            'erp_221_ai_analysis', 'erp_221_messages', 'erp_221_activities_history', 'erp_221_activities',
            'erp_221_deal_contacts', 'erp_221_deals', 'erp_221_contact_profiles', 'erp_221_client_profiles',
            'erp_221_knowledge_base',
            // составы изделий
            'erp_231_mappings', 'erp_231_changes', 'erp_231_lines', 'erp_231_structures',
            // QR: объекты, наклейки, журнал. Типы и сценарии терминала остаются
            'erp_199_inventory_items', 'erp_199_inventories', 'erp_199_media', 'erp_199_activity',
            'erp_199_tokens', 'erp_199_objects_history', 'erp_199_objects',
            // журналы и агентский слой
            'erp_119_audit_log', 'erp_208_events',
            'erp_804_mcp_calls', 'erp_804_mcp_keys',
        );
    }

    /**
     * Есть ли такая таблица в текущей базе.
     *
     * @param   string  $table
     * @return  bool
     */
    protected function table_exists($table)
    {
        $schema = $this->_demo->uses_shared_database() ? $this->_demo->schema_name() : 'public';

        return (bool) DB::query(Database::SELECT, 'SELECT to_regclass(:table)')
            ->param(':table', $schema.'.'.$table)
            ->execute()
            ->get('to_regclass');
    }

    /**
     * Очистить сцену перед сборкой.
     *
     * Защита: чистка допустима только в базе стенда. Если по какой-то причине
     * приложение смотрит в рабочую базу, сборка останавливается — потерять
     * учёт из-за команды демонстрации нельзя.
     *
     * @return  int     сколько таблиц очищено
     */
    public function clear_stage()
    {
        $location = DB::query(Database::SELECT, 'SELECT current_database() AS database, current_schema() AS schema')
            ->execute()->current();
        $expected_schema = $this->_demo->uses_shared_database() ? $this->_demo->schema_name() : 'public';

        if ($location['database'] !== $this->_demo->database_name() OR $location['schema'] !== $expected_schema)
        {
            throw new Kohana_Exception(
                'Отказ от очистки: текущее хранилище «:current_db/:current_schema», а стенд живёт в «:demo_db/:demo_schema». '
                .'Сборка не имеет права очищать рабочую схему.',
                array(
                    ':current_db' => $location['database'], ':current_schema' => $location['schema'],
                    ':demo_db' => $this->_demo->database_name(), ':demo_schema' => $expected_schema,
                )
            );
        }

        $tables = array();

        foreach ($this->stage_tables() as $table)
        {
            if ($this->table_exists($table))
            {
                $tables[] = '"'.$table.'"';
            }
        }

        if ($tables)
        {
            DB::query(NULL, 'TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE')->execute();
        }

        return count($tables);
    }

    /**
     * Что будет создано — без записи в базу. Нужно для `demo:plan` и для того,
     * чтобы человек понимал, что именно развернёт команда.
     *
     * @return  array
     */
    public function plan()
    {
        $scenario = $this->_scenario;

        return array(
            'База'         => $this->_demo->database_name(),
            'Сценарий'     => $scenario->headline().' ('.$scenario->code().')',
            'Компания'     => isset($scenario->company()['name']) ? $scenario->company()['name'] : '—',
            'Глубина'      => $scenario->history_days().' дней истории',
            'Клиенты'      => count($scenario->clients()),
            'Поставщики'   => count($scenario->suppliers()),
            'Материалы'    => count($scenario->materials()),
            'Детали'       => count($scenario->parts()),
            'Техкарты'     => count($scenario->parts()),
            'Рабочие центры' => count($scenario->centers()),
            'Смены'        => count($scenario->shifts()),
            'Сотрудники'   => count((array) Arr::get($scenario->workforce(), 'employees', array())),
            'Сделки CRM'   => count((array) Arr::get($scenario->crm(), 'deals', array())),
            'QR-объекты'   => count((array) Arr::get($scenario->qr(), 'objects', array())),
            'Заказы'       => $scenario->order_count(),
            'Дефекты'      => count($scenario->defects()),
            'Простои'      => count($scenario->downtime_reasons()),
        );
    }

    /**
     * Собрать стенд: справочники → склад → цех → производство → качество →
     * планирование → закупки → документы → аналитика.
     *
     * Каждый слой изолирован: сбой одного не мешает остальным, а причина
     * попадает в отчёт. Так демонстрация собирается даже на неполной базе —
     * и всегда видно, что именно не доехало.
     *
     * @param   array           $options
     * @param   callable|null   $logger
     * @return  array           counts, problems, layers
     */
    public function build(array $options = array(), $logger = NULL)
    {
        $this->_logger   = $logger;
        $this->_counts   = array();
        $this->_problems = array();

        if (isset($options['seed']))
        {
            $this->_seed = (int) $options['seed'];
        }

        mt_srand($this->_seed);

        // Сцена собирается с нуля: демонстрационные данные из миграций модулей
        // (электроника, тестовые заказы) к 3D-печати не относятся.
        $this->layer('clear', 'Подготовка сцены (очистка демо-базы)');

        $layers = array(
            'users'          => 'Учётные записи стенда',
            'workforce'      => 'Сотрудники и оргструктура',
            'registry'       => 'Номенклатура: типы объектов',
            'parties'        => 'Клиенты и поставщики',
            'crm'            => 'CRM: профили клиентов, сделки и контакты',
            'items'          => 'Номенклатура: материалы и детали',
            'warehouses'     => 'Склады',
            'centers'        => 'Рабочие центры и смены',
            'stock'          => 'Запасы и история движений',
            'tech_cards'     => 'Технологические карты',
            'product_structure' => 'Составы изделий (EBOM и MBOM)',
            'orders'         => 'Производственные заказы',
            'qr'             => 'QR-метки, сканирования и инвентаризации',
            'replenishment'  => 'Пробные образцы и расход вне заказов',
            'operations'     => 'Операции цеха и простои',
            'quality'        => 'Контроль качества и брак',
            'shipments'      => 'Отгрузка готовой продукции клиентам',
            'planning'       => 'Программа выпуска и потребность',
            'procurement'    => 'Закупка материалов и приёмка',
            'documents'      => 'Печатные формы и паспорта',
            'analytics'      => 'Пороги и снимки витрин',
        );

        $profiles = $this->_demo->build_profiles();
        $profile_name = (string) $this->_demo->config(
            'active_build_profile',
            $this->_demo->config('build_profile', 'quick')
        );
        $profile = isset($profiles[$profile_name]) ? (array) $profiles[$profile_name] : array();
        $telemetry_every = max(1, (int) (isset($profile['telemetry_every']) ? $profile['telemetry_every'] : 1));
        $journal = Service_Erp119_Journal::instance();
        $events = Service_Erp208_EventBus::instance();

        $journal->sample_writes($telemetry_every);
        $events->sample_persistence($telemetry_every);
        $this->log('Телеметрия: сохраняется каждая '.$telemetry_every.'-я запись; доставка событий полная');

        try
        {
            foreach ($layers as $method => $label)
            {
                $this->layer($method, $label);
            }
        }
        finally
        {
            // Singleton-сервисы живут до конца запроса. Обязательно вернуть
            // полный режим, даже если сборка прервётся исключением/ошибкой.
            $journal->sample_writes(1);
            $events->sample_persistence(1);
        }

        $this->log('Стенд собран: слоёв '.count($layers).', замечаний '.count($this->_problems), 'ok');

        return array(
            'counts'   => $this->_counts,
            'problems' => $this->_problems,
            'layers'   => $layers,
        );
    }

    /**
     * Выполнить слой сборки, не давая его сбою остановить остальные.
     *
     * @param   string  $method
     * @param   string  $label
     * @return  void
     */
    protected function layer($method, $label)
    {
        $started = microtime(TRUE);

        try
        {
            $this->$method();
        }
        catch (ORM_Validation_Exception $e)
        {
            // «Failed to validate array» ничего не объясняет: разворачиваем поля.
            $messages = array();

            foreach ($e->errors('') as $field => $errors)
            {
                $messages[] = $field.': '.implode(', ', (array) $errors);
            }

            $this->fail($label, $messages ? implode('; ', $messages) : $e->getMessage());
        }
        catch (Exception $e)
        {
            $this->fail($label, get_class($e).': '.$e->getMessage().' @ '.$this->origin($e));
        }
        catch (Error $e)
        {
            $this->fail($label, get_class($e).': '.$e->getMessage().' @ '.$this->origin($e));
        }

        $this->log(sprintf('%-38s %s', $label, $this->took($started)), 'info');
    }

    // =========================================================================
    //  Слои: учётные записи и номенклатура
    // =========================================================================

    /**
     * Слой «очистка»: привести демо-базу к пустой сцене.
     *
     * @return  void
     */
    protected function clear()
    {
        $this->count('truncated_tables', $this->clear_stage());
    }

    /**
     * Пользователи стенда: администратор, менеджер, мастер, контролёр и гость.
     *
     * Пароли демонстрационные и одинаковые — стенд публичный, секретов в нём нет.
     */
    protected function users()
    {
        $users = $this->_demo->config('users', array());

        $users[] = $this->guest_user_data();

        foreach ($users as $data)
        {
            $this->ensure_user($data);
        }

        $this->assert_guest_account();
        $this->count('users', count($users));
        $this->_admin_id = $this->user_id();
    }

    /** @return array Канонические реквизиты гостя из общей конфигурации. */
    protected function guest_user_data()
    {
        return array(
            'email'    => (string) $this->_demo->config('guest_email', 'guest@demo.promerp.local'),
            'username' => (string) $this->_demo->config('guest_username', 'Гость'),
            'password' => (string) $this->_demo->config('guest_password', 'demo'),
            'roles'    => array('login', (string) $this->_demo->config('guest_role', 'demo')),
        );
    }

    /**
     * Проверить результат слоя сразу, а не объявлять успех по числу входных
     * настроек. Проверяется также роль demo: без неё аккаунт существует, но
     * войти в безопасное рабочее место не сможет.
     */
    protected function assert_guest_account()
    {
        $guest = $this->guest_user_data();
        $row = DB::select(array(DB::expr('COUNT(DISTINCT u.id)'), 'cnt'))
            ->from(array('users', 'u'))
            ->join(array('roles_users', 'ru'), 'INNER')->on('ru.user_id', '=', 'u.id')
            ->join(array('roles', 'r'), 'INNER')->on('r.id', '=', 'ru.role_id')
            ->where(DB::expr('LOWER(TRIM(u.email))'), '=', strtolower(trim($guest['email'])))
            ->where('r.name', '=', (string) $this->_demo->config('guest_role', 'demo'))
            ->execute()->get('cnt');

        if ((int) $row !== 1)
        {
            throw new UnexpectedValueException(
                'Гостевой аккаунт '.$guest['email'].' не создан или не получил роль demo'
            );
        }
    }

    /**
     * Создать пользователя с ролями (или обновить пароль существующего).
     *
     * @param   array   $data
     * @return  int|null
     */
    protected function ensure_user(array $data)
    {
        $user = ORM::factory('User')->where('email', '=', $data['email'])->find();

        if ( ! $user->loaded())
        {
            $user = ORM::factory('User');
        }

        $user->values(array(
            'email'            => $data['email'],
            'username'         => $data['username'],
            'password'         => $data['password'],
            'email_confirmed'  => 1,
            'confirmation_token' => NULL,
        ), array('email', 'username', 'password', 'email_confirmed', 'confirmation_token'));

        $user->save();

        foreach ((array) $data['roles'] as $role_name)
        {
            $role = ORM::factory('Role', array('name' => $role_name));

            if ( ! $role->loaded())
            {
                $role = ORM::factory('Role');
                $role->name        = $role_name;
                $role->description = 'Роль демонстрационного стенда';
                $role->save();
            }

            if ( ! $user->has('roles', $role))
            {
                $user->add('roles', $role);
            }
        }

        return (int) $user->id;
    }

    /**
     * Типы объектов реестра: детали и материалы.
     *
     * Реестр erp227 — общий источник номенклатуры: склад, производство, качество
     * и аналитика берут позиции оттуда, поэтому демонстрация начинается с типов.
     */
    protected function registry()
    {
        $registry = Service_Erp227_Registry::instance();

        $types = array(
            'part' => array(
                'name'   => 'Готовая деталь',
                'icon'   => 'fas fa-cube',
                'schema' => array('fields' => array(
                    array('name' => 'client', 'type' => 'string', 'label' => 'Клиент'),
                    array('name' => 'material', 'type' => 'string', 'label' => 'Материал'),
                    array('name' => 'weight_g', 'type' => 'number', 'label' => 'Масса, г'),
                    array('name' => 'layer_mm', 'type' => 'number', 'label' => 'Слой, мм'),
                    array('name' => 'infill', 'type' => 'number', 'label' => 'Заполнение, %'),
                    array('name' => 'tolerance', 'type' => 'string', 'label' => 'Допуск'),
                )),
            ),
            'material' => array(
                'name'   => 'Материал и расходники',
                'icon'   => 'fas fa-spool',
                'schema' => array('fields' => array(
                    array('name' => 'polymer', 'type' => 'string', 'label' => 'Полимер'),
                    array('name' => 'color', 'type' => 'string', 'label' => 'Цвет'),
                    array('name' => 'diameter_mm', 'type' => 'number', 'label' => 'Диаметр, мм'),
                    array('name' => 'spool_kg', 'type' => 'number', 'label' => 'Катушка, кг'),
                    array('name' => 'price', 'type' => 'number', 'label' => 'Цена, ₽'),
                )),
            ),
        );

        $created = 0;

        foreach ($types as $code => $data)
        {
            $exists = ORM::factory('Erp227_Type')->where('code', '=', $code)->find();

            if ($exists->loaded())
            {
                continue;
            }

            $registry->create_type(array(
                'code'      => $code,
                'name'      => $data['name'],
                'icon'      => $data['icon'],
                'schema'    => $data['schema'],
                'ui_config' => array(),
            ));

            $created++;
        }

        $this->count('registry_types', $created);
    }

    /**
     * Клиенты и поставщики — из описания сценария, через сервис контрагентов.
     */
    protected function parties()
    {
        $counterparties = Service_Erp301_Counterparties::instance();

        foreach ($this->_scenario->clients() as $client)
        {
            $party = $counterparties->create(array(
                'code'  => $client['code'],
                'name'  => $client['name'],
                'kind'  => $this->kind_of($client['kind']),
                'roles' => array('client'),
                'inn'   => $client['inn'],
                'city'  => $client['city'],
                'phone' => '+7 (843) '.$this->rand(200, 799).'-'.str_pad($this->rand(10, 99), 2, '0', STR_PAD_LEFT).'-'.str_pad($this->rand(10, 99), 2, '0', STR_PAD_LEFT),
                'email' => 'zakaz@'.strtolower($client['code']).'.example',
                'rating' => $client['rating'],
                'note'  => $client['about'],
                'status' => 'active',
            ), $this->user_id('manager'));

            $this->_parties[$client['code']] = (int) $party->id;
        }

        foreach ($this->_scenario->suppliers() as $supplier)
        {
            $party = $counterparties->create(array(
                'code'  => $supplier['code'],
                'name'  => $supplier['name'],
                'kind'  => $this->kind_of($supplier['kind']),
                'roles' => array('supplier'),
                'inn'   => $supplier['inn'],
                'city'  => $supplier['city'],
                'phone' => '+7 (495) '.$this->rand(200, 799).'-'.str_pad($this->rand(10, 99), 2, '0', STR_PAD_LEFT).'-'.str_pad($this->rand(10, 99), 2, '0', STR_PAD_LEFT),
                'email' => 'sale@'.strtolower($supplier['code']).'.example',
                'rating' => $supplier['rating'],
                'note'  => 'Срок поставки '.$supplier['lead_days'].' дней. '.$supplier['profile']['payment_terms'].', '.$supplier['profile']['delivery'],
                'status' => 'active',
            ), $this->user_id('manager'));

            $this->_parties[$supplier['code']] = (int) $party->id;
        }

        $this->count('counterparties', count($this->_parties));
    }

    /**
     * Номенклатура: материалы (катушки и расходники) и детали.
     */
    protected function items()
    {
        $registry = Service_Erp227_Registry::instance();

        foreach ($this->_scenario->materials() as $material)
        {
            $item = $registry->create_object(array(
                'type'       => 'material',
                'code'       => $material['code'],
                'name'       => $material['name'],
                'kind'       => 'material',
                'attributes' => array(
                    'polymer'     => $material['polymer'],
                    'color'       => $material['color'],
                    'diameter_mm' => $material['diameter_mm'],
                    'spool_kg'    => $material['spool_kg'],
                    'price'       => $material['price'],
                    'min_qty'     => $material['min_qty'],
                    'supplier'    => $material['supplier'],
                    'nozzle_c'    => isset($material['attrs']['nozzle_c']) ? $material['attrs']['nozzle_c'] : NULL,
                    'bed_c'       => isset($material['attrs']['bed_c']) ? $material['attrs']['bed_c'] : NULL,
                ),
                'metadata'   => array('scenario' => 'print3d', 'role' => 'материал'),
            ));

            $this->_items[$material['code']] = (int) $item->id;
        }

        foreach ($this->_scenario->parts() as $part)
        {
            $item = $registry->create_object(array(
                'type'       => 'part',
                'code'       => $part['code'],
                'name'       => $part['name'],
                'kind'       => 'product',
                'attributes' => array(
                    'client'      => $part['client'],
                    'material'    => $part['material'],
                    'weight_g'    => $part['weight_g'],
                    'layer_mm'    => $part['layer_mm'],
                    'infill'      => $part['infill'],
                    'print_hours' => $part['print_hours'],
                    'batch'       => $part['batch'],
                    'price'       => $part['price'],
                    'tolerance'   => $part['tolerance'],
                ),
                'metadata'   => array('scenario' => 'print3d', 'role' => 'деталь', 'note' => $part['note']),
            ));

            $this->_items[$part['code']] = (int) $item->id;
        }

        $this->count('items', count($this->_items));
    }

    // =========================================================================
    //  Слои: склад, цех, техпроцесс
    // =========================================================================

    /**
     * Склады: материалы, готовая продукция, карантин, брак.
     */
    protected function warehouses()
    {
        $stock = Service_Erp401_Stock::instance();

        $list = array(
            array('code' => 'WH-MAT', 'name' => 'Склад филамента и расходников', 'type' => 'main',
                  'description' => 'Катушки, фотополимер, сопла. Отсюда материал уходит в печать.'),
            array('code' => 'WH-FIN', 'name' => 'Склад готовой продукции', 'type' => 'production',
                  'description' => 'Готовые детали до отгрузки клиенту, в том числе принятые ОТК.'),
            array('code' => 'WH-QAR', 'name' => 'Карантин ОТК', 'type' => 'quarantine',
                  'description' => 'Партии с дефектами: решение ещё не принято, смешивать с годным нельзя.'),
            array('code' => 'WH-SCR', 'name' => 'Брак и утиль', 'type' => 'scrap',
                  'description' => 'Детали, признанные неисправимым браком.'),
        );

        foreach ($list as $data)
        {
            $warehouse = ORM::factory('Erp401_Warehouse')->where('code', '=', $data['code'])->find();

            if ( ! $warehouse->loaded())
            {
                $warehouse = $stock->create_warehouse($data);
            }

            $this->_warehouses[$data['code']] = (int) $warehouse->id;
        }

        $this->count('warehouses', count($this->_warehouses));
    }

    /**
     * Рабочие центры, смены и календарь на два месяца вперёд.
     */
    protected function centers()
    {
        $centers = Service_Erp408_WorkCenters::instance();

        foreach ($this->_scenario->centers() as $center)
        {
            $exists = ORM::factory('Erp408_WorkCenter')->where('code', '=', $center['code'])->find();

            if ( ! $exists->loaded())
            {
                $exists = $centers->create(array(
                    'code'             => $center['code'],
                    'name'             => $center['name'],
                    'kind'             => $center['kind'],
                    'workshop'         => $center['workshop'],
                    'capacity_per_hour' => $center['capacity_per_hour'],
                    'cost_per_hour'    => $center['cost_per_hour'],
                    'is_active'        => TRUE,
                    'note'             => $center['note'],
                ), $this->user_id());
            }

            $this->_centers[$center['code']] = (int) $this->key($exists, 'id');
        }

        $shifts_service = Service_Erp408_Shifts::instance();
        $shift_ids      = array();

        foreach ($this->_scenario->shifts() as $shift)
        {
            $exists = ORM::factory('Erp408_Shift')->where('code', '=', $shift['code'])->find();

            if ( ! $exists->loaded())
            {
                $exists = $shifts_service->create(array(
                    'code'           => $shift['code'],
                    'name'           => $shift['name'],
                    'start_time'     => $shift['start_time'],
                    'end_time'       => $shift['end_time'],
                    'duration_hours' => $shift['duration_hours'],
                    'is_active'      => TRUE,
                    'sort_order'     => $shift['sort_order'],
                    'note'           => $shift['note'],
                ), $this->user_id());
            }

            $shift_ids[] = (int) $this->key($exists, 'id');
        }

        $shifts_service->generate(
            date('Y-m-d', strtotime('-'.$this->_scenario->history_days().' days')),
            date('Y-m-d', strtotime('+30 days')),
            array_values($this->_centers),
            $shift_ids
        );

        $this->count('centers', count($this->_centers));
        $this->count('shifts', count($shift_ids));
    }

    /**
     * Запасы: история прихода филамента и расходников по дням.
     *
     * Даты движений — исторические: без этого витрины аналитики (оборачиваемость,
     * покрытие, ABC/XYZ) показали бы пустоту. Поэтому здесь `performed_at`
     * задаётся явно.
     */
    protected function stock()
    {
        $stock = Service_Erp401_Stock::instance();
        $mat   = $this->_warehouses['WH-MAT'];
        $short = $this->_scenario->short_materials();
        $days  = max(2, (int) $this->_scenario->history_days());
        $moves = 0;
        $kg    = 0.0;

        // Закупка считается по плану выпуска, а не «на глаз»: сколько материала
        // реально уйдёт со склада по закрытым и запущенным заказам, столько и
        // закупаем — плюс страховой остаток. План заказов строится один раз
        // (`order_plan()`) и тот же самый попадает в слой `orders`, поэтому
        // склад и производство считают одно и то же.
        $demand = $this->material_demand(array('done', 'running'));

        foreach ($this->_scenario->materials() as $material)
        {
            $item_id = $this->item($material['code']);
            $needed  = isset($demand[$material['code']]) ? (float) $demand[$material['code']] : 0.0;
            $min     = (float) $material['min_qty'];

            if (in_array($material['code'], $short, TRUE))
            {
                // «Просаженная» позиция: закупка чуть меньше расхода — материал
                // заканчивается, и стенд показывает дефицит, заявку и просрочку
                // поставки, а не идеальную витрину.
                $incoming = round($needed * 1.03, 2);
                $note     = 'Закупка под программу выпуска: '.$material['polymer'];
            }
            elseif ($needed > 0)
            {
                // Закупка = расход по программе + страховой запас. Запас считаем
                // от расхода (примерно три недели), а не от минимума позиции:
                // иначе медленные материалы показывали бы покрытие «на годы».
                $buffer   = max($min * 0.4, $needed * 0.16);
                $incoming = round($needed * 1.05 + $buffer, 2);
                $note     = 'Закупка под программу выпуска: '.$material['polymer'];
            }
            else
            {
                // Позиция без расхода — расходники: держим страховой минимум.
                $incoming = round($min * 0.4, 2);
                $note     = 'Закупка расходников: '.$material['polymer'];
            }

            if ($incoming <= 0)
            {
                continue;
            }

            // История прихода: несколько поставок за период — так в движениях
            // склада видно, что он живёт, а не «один приход в начале времён».
            $deliveries = $needed > 0 ? 3 : 1;
            $each       = round($incoming / $deliveries, 2);

            for ($i = 0; $i < $deliveries; $i++)
            {
                $qty = ($i === $deliveries - 1) ? round($incoming - $each * $i, 2) : $each;

                if ($qty <= 0)
                {
                    continue;
                }

                $days_ago = (int) max(2, round($days * (1 - ($i + 1) / ($deliveries + 1))));

                $stock->receive($mat, $item_id, $qty, array(
                    'doc_type'     => 'purchase',
                    'note'         => $note.' (поставщик '.$material['supplier'].')',
                    'performed_at' => $this->day($days_ago, '09:'.str_pad($this->rand(0, 59), 2, '0', STR_PAD_LEFT).':00'),
                ));

                $moves++;
                $kg += $qty;
            }

            $this->_supply[$material['code']] = $incoming;

            // Неснижаемый минимум живёт в строке склада (erp_401_balances.min_qty):
            // по нему склад и витрины понимают, что позицию пора дозаказывать.
            $balance = $stock->balance($mat, $item_id);

            if ($balance->loaded() AND (float) $balance->min_qty !== $min)
            {
                $balance->min_qty = $min;
                $balance->save();
            }
        }

        $this->count('stock_receipts', $moves);
        $this->count('stock_qty', round($kg, 1));

        $this->log('Закупка под программу выпуска: '.implode(', ', array_map(function ($code, $kg) {
            return $code.' '.round($kg, 2).' кг';
        }, array_keys($demand), $demand))
            .' · всего закуплено '.round($kg, 1).' кг · просажены: '.implode(', ', $short), 'debug');
    }

    /**
     * Образцы для согласования — единственный расход материала вне заказа.
     *
     * Слой идёт после производства, поэтому знает, сколько материала реально
     * списано, и доводит остаток до страхового запаса (сценарий задаёт его в
     * днях расхода). Так покрытие запасов на стенде — рабочая цифра, а не
     * «запас на годы». По позициям из `short_materials` остаток не восполняем:
     * именно они показывают дефицит и заявку на закупку.
     */
    protected function replenishment()
    {
        $stock  = Service_Erp401_Stock::instance();
        $mat    = $this->_warehouses['WH-MAT'];
        $short  = $this->_scenario->short_materials();
        $issued = array();
        $moves  = 0;
        $kg     = 0.0;

        foreach ($this->_scenario->materials() as $material)
        {
            $issued[$material['code']] = (float) DB::select(array(DB::expr('COALESCE(SUM(qty), 0)'), 'issued'))
                ->from('erp_401_movements')
                ->where('item_id', '=', (int) $this->item($material['code']))
                ->where('type', '=', 'issue')
                ->execute()
                ->get('issued', 0);
        }

        // Образцы для согласования: печатня печатает пробные детали бесплатно,
        // и это единственный расход материала вне заказа. Берём позиции с
        // наибольшим расходом, но не «просаженные»: у тех остаток и так под
        // минимальным, а дефицит на стенде должен быть понятным — одна позиция.
        arsort($issued);

        $taken = 0;

        foreach ($issued as $code => $used)
        {
            if ($taken >= 2 OR $used <= 0 OR in_array($code, $short, TRUE))
            {
                continue;
            }

            $item_id = $this->item($code);

            $balance = (float) DB::select('qty')
                ->from('erp_401_balances')
                ->where('warehouse_id', '=', (int) $mat)
                ->where('item_id', '=', (int) $item_id)
                ->execute()
                ->get('qty', 0);

            $qty = round(min(0.5, max(0.1, $balance * 0.05)), 2);

            try
            {
                $stock->issue($mat, $item_id, $qty, array(
                    'doc_type'     => 'write_off',
                    'note'         => 'Пробные образцы для согласования с клиентом',
                    'performed_at' => $this->day($this->rand(3, 25), '14:'.str_pad($this->rand(0, 59), 2, '0', STR_PAD_LEFT).':00'),
                ));

                $moves++;
                $kg += $qty;
                $taken++;
            }
            catch (Exception $e)
            {
                $this->fail('образцы по '.$code, $e->getMessage());
            }
        }

        $this->count('samples_write_off', $moves);
        $this->count('samples_qty', round($kg, 2));

        $this->log('Пробные образцы: '.$moves.' списаний, '.round($kg, 2).' кг · просажены: '.implode(', ', $short), 'debug');
    }

    protected function tech_cards()
    {
        $cards    = Service_Erp228_TechCards::instance();
        $materials = array();

        foreach ($this->_scenario->materials() as $material)
        {
            $materials[$material['code']] = $material;
        }

        foreach ($this->_scenario->parts() as $part)
        {
            $material = $materials[$part['material']];
            $route    = $this->_scenario->route_for($material['polymer']);

            $card = $cards->create(array(
                'title'           => 'Техкарта: '.$part['name'],
                'target_type'     => 'parametric',
                'target_id'       => $this->item($part['code']),
                'source_code'     => 'Каталог деталей «Печатня 3D»: '.$part['code'],
                'production_type' => 'additive',
                'input_params'    => array(
                    'part'        => $part['code'],
                    'material'    => $material['name'],
                    'layer_mm'    => $part['layer_mm'],
                    'infill'      => $part['infill'],
                    'tolerance'   => $part['tolerance'],
                ),
                'status'          => 'ready',
            ), $this->user_id());

            // Расход материала на деталь: граммы → килограммы (так считает склад).
            $kg_per_unit = round($part['weight_g'] / 1000, 4);

            $operations = array();

            // Цех планирует время операции как «норма на единицу × количество»
            // (Erp408_Operations::plan_order), поэтому в техкарте норма — на одну
            // деталь: часы печати партии делим на типовую партию, а подготовку,
            // постобработку и контроль — на то же количество.
            $batch = max(1, (int) $part['batch']);

            foreach ($route as $step)
            {
                $duration = $step['duration_sec'];

                if ($duration === 0)
                {
                    // Печать: часы на типовую партию → секунды на деталь.
                    // Размер заказа может быть меньше или больше типового, но
                    // норма на деталь одна: 54 с на клипсу, 28 мин на кронштейн.
                    $duration = (int) round($part['print_hours'] * 3600 / $batch);
                }
                elseif ($duration < 3600)
                {
                    // Разовая операция на партию (подготовка, постобработка,
                    // контроль): на деталь приходится её доля.
                    $duration = max(1, (int) round($duration / $batch));
                }
                else
                {
                    // Операция длиннее часа задана на партию целиком (например
                    // сушка фотополимера): делим на типовую партию, иначе цех
                    // умножал бы её на каждую деталь и план раздувался бы.
                    $duration = max(1, (int) round($duration / max(1, (int) $part['batch'])));
                }

                $operations[] = array(
                    'op_name'        => $step['op_name'],
                    'op_description' => $step['op_name'].' — деталь «'.$part['name'].'»',
                    'duration_sec'   => $duration,
                    'cost_amount'    => $step['cost_amount'],
                    'params'         => array_merge((array) $step['params'], array('center' => $step['op_name'] === 'Печать партии' ? 'printer' : 'post')),
                );
            }

            $cards->save_document($card->id, array(
                'content_markdown' => $this->card_markdown($part, $material, $kg_per_unit),
                'structured_data'  => array(
                    'product'   => array('name' => $part['name'], 'code' => $part['code']),
                    'materials' => array(
                        array(
                            'name' => $material['name'],
                            'qty'  => $kg_per_unit,
                            'uom'  => 'кг',
                        ),
                    ),
                    'operations' => array_map(function ($step) {
                        return array('name' => $step['op_name'], 'duration_sec' => $step['duration_sec']);
                    }, $operations),
                    'batch_hours' => $part['print_hours'],
                ),
                'llm_model'   => 'demo-seed',
                'tokens_total' => 0,
                'gen_time_ms' => 0,
            ), $operations, $this->user_id());

            $this->_cards[$part['code']] = (int) $card->id;
        }

        $this->count('tech_cards', count($this->_cards));
    }

    /**
     * Текст техкарты: печатается в карточке, поэтому пишем по-человечески.
     */
    protected function card_markdown(array $part, array $material, $kg_per_unit)
    {
        $lines = array();
        $lines[] = '## '.$part['name'];
        $lines[] = '';
        $lines[] = '- Код детали: '.$part['code'].' (клиент '.$part['client'].')';
        $lines[] = '- Материал: '.$material['name'];
        $lines[] = '- Слой: '.$part['layer_mm'].' мм, заполнение '.$part['infill'].' %, допуск '.$part['tolerance'];
        $lines[] = '- Расчёт печати: '.$part['print_hours'].' ч на партию '.$part['batch'].' шт. ('
            .number_format($kg_per_unit * 1000, 1, ',', ' ').' г на деталь)';
        $lines[] = '- Расход материала: '.number_format($kg_per_unit, 4, ',', ' ').' кг на деталь';
        $lines[] = '';
        $lines[] = 'Маршрут: подготовка модели → печать → постобработка → контроль размеров.';

        return implode("\n", $lines);
    }

    // =========================================================================
    //  Слои: производство и цех
    // =========================================================================

    /**
     * Производственные заказы в разных состояниях: закрытые, в работе,
     * запланированные, черновик и один отменённый.
     */
    /**
     * План заказов на историю: считается один раз и используется дважды —
     * складом (по нему считается приход материалов) и производством.
     *
     * Так синтетика остаётся связной: материал закуплен под тот выпуск,
     * который стенд потом показывает.
     *
     * @return  array
     */
    protected function order_plan()
    {
        if ($this->_plan !== NULL)
        {
            return $this->_plan;
        }

        $parts = $this->_scenario->parts();
        $count = $this->_scenario->order_count();

        $plan = array();

        for ($i = 0; $i < $count; $i++)
        {
            $part = $parts[$i % count($parts)];

            // Раскладываем состояния так, чтобы на стенде было всё: и «в работе»,
            // и «готово», и «ждёт старта» — иначе экран заказов выглядит мёртвым.
            // Но закрытых заказов должно быть большинство: витрины считают
            // покрытие запасов и выполнение плана по факту выпуска, и стенд
            // с сотней незакрытых заказов показывал бы провал плана вместо
            // работающего участка.
            // В быстром профиле всего 8 заказов, поэтому процентные границы
            // поглощали planned/cancelled. Последние три слота закреплены явно:
            // один в работе, один запланирован, один отменён; остальные готовы.
            if ($i < max(1, $count - 3))
            {
                $state = 'done';
            }
            elseif ($i === $count - 3)
            {
                $state = 'running';
            }
            elseif ($i === $count - 2)
            {
                $state = 'planned';
            }
            else
            {
                $state = 'cancelled';
            }

            $plan[] = array('part' => $part, 'state' => $state, 'index' => $i);
        }

        // От старых заказов к новым: так история выглядит правдоподобно,
        // а «в работе» оказываются последние.
        $plan = array_reverse($plan);

        $ready = array();

        foreach ($plan as $position => $row)
        {
            $part  = $row['part'];
            $state = $row['state'];

            $batch = max(10, (int) round($part['batch'] * (0.4 + mt_rand(0, 60) / 100)));

            $days_ago = ($state === 'done' || $state === 'cancelled')
                ? $this->rand(10, $this->_scenario->history_days() - 15)
                : ($state === 'running' ? $this->rand(2, 8) : -$this->rand(3, 20));

            $ready[] = array(
                'part'     => $part,
                'state'    => $state,
                'batch'    => $batch,
                'days_ago' => $days_ago,
                'index'    => $row['index'],
            );
        }

        return $this->_plan = $ready;
    }

    /**
     * Списание материала по закрытым заказам: код материала => килограммы.
     *
     * Материал списывается при завершении заказа (в запущенных заказах он лишь
     * зарезервирован), поэтому стартовый запас склада считается по закрытым
     * заказам — сервис производства не пропустит списание без остатка.
     *
     * @return  array
     */
    protected function material_demand($states = array('done'))
    {
        $demand = array();

        foreach ($this->order_plan() as $row)
        {
            if ( ! in_array($row['state'], (array) $states, TRUE))
            {
                continue;
            }

            $code = $row['part']['material'];
            $kg   = $row['batch'] * $row['part']['weight_g'] / 1000;

            $demand[$code] = isset($demand[$code]) ? $demand[$code] + $kg : $kg;
        }

        return $demand;
    }

    protected function orders()
    {
        $orders = Service_Erp402_Orders::instance();

        foreach ($this->order_plan() as $position => $row)
        {
            $part  = $row['part'];

            if ( ! isset($this->_cards[$part['code']]))
            {
                $this->fail('заказ по детали '.$part['code'], 'нет техкарты — пропущен');
                continue;
            }
            $state = $row['state'];

            $batch = $row['batch'];

            $days_ago = $row['days_ago'];

            $target = date('Y-m-d', strtotime(($days_ago > 0 ? '-' : '+').abs($days_ago).' days'));

            $order = $orders->create(array(
                'title'               => $part['name'],
                'item_id'             => $this->item($part['code']),
                'tech_card_id'        => $this->_cards[$part['code']],
                'qty'                 => $batch,
                'uom_code'            => 'pcs',
                'priority'            => $state === 'running' ? 1 : $this->rand(2, 6),
                'customer_id'         => $this->_parties[$part['client']],
                'warehouse_id'        => $this->_warehouses['WH-MAT'],
                'output_warehouse_id' => $this->_warehouses['WH-FIN'],
                'target_date'         => $target,
                'note'                => $part['note'].' Партия '.$batch.' шт.',
            ), $this->user_id('manager'));

            $order->created_at = $this->day($days_ago > 0 ? $days_ago : 3, '09:'.$this->padValue($this->rand(0, 59)).':00');
            $order->save();

            $this->fill_order($order, $part);

            $this->advance_order($order, $state);

            $this->_orders[(int) $order->id] = array('part' => $part, 'state' => $state, 'batch' => $batch, 'days_ago' => $days_ago);
        }

        $this->count('orders', count($this->_orders));
    }

    /**
     * Состав заказа из техкарты; если сопоставление не сработало — строка
     * добавляется напрямую (демонстрация не должна остаться без состава).
     */
    protected function fill_order($order, array $part)
    {
        $orders = Service_Erp402_Orders::instance();

        try
        {
            $result = $orders->fill_from_tech_card($order->id, $this->_cards[$part['code']], $this->user_id());

            if ((int) $result['added'] === 0)
            {
                throw new Exception_Erp402_Production('состав из техкарты пуст');
            }
        }
        catch (Exception $e)
        {
            $material = $this->material_of($part);

            $orders->add_item($order->id, array(
                'item_id'      => $this->item($material['code']),
                'name'         => $material['name'],
                'uom_code'     => 'кг',
                'qty_per_unit' => round($part['weight_g'] / 1000, 4),
                'warehouse_id' => $this->_warehouses['WH-MAT'],
                'note'         => 'Норма расхода из техкарты ('.$e->getMessage().')',
            ), $this->user_id());
        }
    }

    /**
     * Провести заказ по маршруту статусов.
     */
    protected function advance_order($order, $state)
    {
        $orders = Service_Erp402_Orders::instance();
        $flow   = array();

        if ($state === 'draft')
        {
            return;
        }

        $flow[] = 'plan';

        if (in_array($state, array('running', 'done'), TRUE))
        {
            $flow[] = 'release';
            $flow[] = 'start';
        }

        if ($state === 'done')
        {
            $flow[] = 'complete';
        }

        foreach ($flow as $action)
        {
            try
            {
                if ($action === 'plan')
                {
                    $orders->plan($order->id, $this->user_id());
                }
                elseif ($action === 'release')
                {
                    $orders->release($order->id, $this->user_id());
                }
                elseif ($action === 'start')
                {
                    $orders->start($order->id, $this->user_id());
                }
                elseif ($action === 'complete')
                {
                    $orders->complete($order->id, (float) $order->qty, $this->user_id());
                }
            }
            catch (Exception $e)
            {
                $this->fail('заказ '.$order->number.': '.$action, $e->getMessage());
            }
        }

        if ($state === 'cancelled')
        {
            try
            {
                $orders->plan($order->id, $this->user_id());
                $orders->cancel($order->id, 'Клиент отказался от партии: деталь переделали по новой модели', $this->user_id());
            }
            catch (Exception $e)
            {
                $this->fail('заказ '.$order->number.': отмена', $e->getMessage());
            }
        }
    }

    /**
     * Операции цеха: маршрут по заказу, факт печати, простои и брак.
     */
    protected function operations()
    {
        $shopfloor  = Service_Erp408_Operations::instance();
        $orders     = Service_Erp402_Orders::instance();
        $reasons    = $this->_scenario->downtime_reasons();
        $operations = 0;
        $downtimes  = 0;

        foreach ($this->_orders as $order_id => $data)
        {
            if (in_array($data['state'], array('draft', 'cancelled'), TRUE))
            {
                continue;
            }

            $order = $orders->get($order_id);

            if ( ! $order)
            {
                continue;
            }

            try
            {
                $shopfloor->plan_order($order, $this->user_id());
            }
            catch (Exception $e)
            {
                $this->fail('операции заказа '.$order->number, $e->getMessage());
                continue;
            }

            $rows = $this->order_operations($order_id);

            foreach ($rows as $index => $operation)
            {
                $operations++;

                $center_code = $this->center_for($data['part'], $index);

                try
                {
                    $shopfloor->assign($operation['id'], $this->_centers[$center_code], NULL, date('Y-m-d', strtotime('-'.$data['days_ago'].' days')), $this->user_id());
                }
                catch (Exception $e)
                {
                    // У центра может не быть смены в этот день — не критично для стенда.
                }

                $last = ($index === count($rows) - 1);

                if ($data['state'] === 'done' OR ($data['state'] === 'running' AND ! $last))
                {
                    // Простой отмечают по открытой операции, поэтому вставляем
                    // его между стартом и выпуском — как это делает мастер.
                    // Заказ «в работе» проходит маршрут до последней операции:
                    // на экране цеха видно, где именно он стоит.
                    $downtimes += $this->complete_operation($shopfloor, $operation, $data, $index, $reasons);
                }
                elseif ($data['state'] === 'running' AND $last)
                {
                    $this->start_operation($shopfloor, $operation, TRUE);
                    $downtimes += $this->maybe_downtime($shopfloor, $operation, $reasons, FALSE);
                }
            }
        }

        $this->count('operations', $operations);
        $this->count('downtimes', $downtimes);
    }

    /**
     * @param   int $order_id
     * @return  array
     */
    protected function order_operations($order_id)
    {
        return DB::select('id', 'name', 'op_no', 'status')
            ->from('erp_408_operations')
            ->where('order_id', '=', (int) $order_id)
            ->order_by('op_no', 'ASC')
            ->execute()
            ->as_array();
    }

    /**
     * Операция выполнена в прошлом: старт, выпуск, финиш с исторической датой.
     */
    protected function complete_operation($shopfloor, array $operation, array $data, $index, array $reasons)
    {
        $downtimes = 0;

        try
        {
            $shopfloor->start($operation['id'], $this->user_id());

            $downtimes += $this->maybe_downtime($shopfloor, $operation, $reasons, TRUE);

            // Брак печати: у части партий — с причиной из справочника причин простоев.
            $scrap = 0;

            // Брак печати: печатня держит его в районе целевых 8 % партий.
            if ($index === 0 AND $this->rand(1, 100) <= 12)
            {
                $scrap = max(1, (int) round($data['batch'] * $this->rand(1, 3) / 100));
            }

            $qty_ok = max(1, (int) $data['batch'] - $scrap);

            $shopfloor->output($operation['id'], $qty_ok, $scrap, $this->user_id(),
                $scrap > 0 ? 'Брак: '.$reasons[0]['label'] : NULL);

            $shopfloor->finish($operation['id'], $this->user_id());

            // Финиш сервиса ставит «сейчас», поэтому историческую дату и
            // учтённое время записываем после него — уже как факт.
            $this->shift_operation($operation['id'], $data['days_ago'], $index);
        }
        catch (Exception $e)
        {
            $this->fail('операция #'.$operation['id'].' ('.$operation['name'].')', $e->getMessage());
        }

        return $downtimes;
    }

    /**
     * Операция в работе: старт без финиша.
     */
    protected function start_operation($shopfloor, array $operation, $historical = TRUE)
    {
        try
        {
            $shopfloor->start($operation['id'], $this->user_id());

            if ($historical)
            {
                $this->shift_operation($operation['id'], 1, 0);
            }
        }
        catch (Exception $e)
        {
            $this->fail('старт операции #'.$operation['id'], $e->getMessage());
        }
    }

    /**
     * Простой: часть операций останавливалась из-за сопла, филамента или калибровки.
     */
    protected function maybe_downtime($shopfloor, array $operation, array $reasons, $closed)
    {
        if ($this->rand(1, 100) > 55)
        {
            return 0;
        }

        $reason = $reasons[array_rand($reasons)];

        try
        {
            $shopfloor->downtime_start($operation['id'], $this->user_id(), $reason['reason'], $reason['label']);

            if ($closed)
            {
                $shopfloor->downtime_stop($operation['id'], $this->user_id(), 'Устранено: '.$reason['label']);

                // Сервис считает секунды простоя от «сейчас», а простой был в
                // истории: длительность проставляем напрямую. Парето простоев
                // строится по журналу работ (downtime_end.seconds), доступность
                // OEE — по операции.
                $seconds = $this->rand(10, 40) * 60;

                DB::update('erp_408_operations')
                    ->set(array('downtime_sec' => DB::expr('downtime_sec + '.(int) $seconds)))
                    ->where('id', '=', (int) $operation['id'])
                    ->execute();

                DB::update('erp_408_worklogs')
                    ->set(array('seconds' => (int) $seconds))
                    ->where('operation_id', '=', (int) $operation['id'])
                    ->where('kind', '=', 'downtime_end')
                    ->execute();
            }

            return 1;
        }
        catch (Exception $e)
        {
            return 0;
        }
    }

    /**
     * Сдвинуть операцию в прошлое: start/finish сервис ставит «сейчас», а стенду
     * нужна история. Правится только время — статусы и факт остаются от сервиса.
     */
    protected function shift_operation($operation_id, $days_ago, $index)
    {
        $row = DB::select('planned_hours', 'downtime_sec')
            ->from('erp_408_operations')
            ->where('id', '=', (int) $operation_id)
            ->execute()
            ->current();

        if ( ! $row)
        {
            return;
        }

        $planned_sec = (float) $row['planned_hours'] * 3600;

        // Работа занимает больше нормы (темп ~90 % от нормативного), часть
        // времени — простой: так OEE показывает живые 65–75 %, а не «идеальные»
        // сто процентов.
        $worked   = (int) round($planned_sec * 1.1);
        $downtime = max((int) $row['downtime_sec'], (int) round($worked * 0.08));

        $started = strtotime('-'.$days_ago.' days +'.(7 + $index * 2).' hours');

        DB::update('erp_408_operations')
            ->set(array(
                'actual_start' => date('Y-m-d H:i:s', $started),
                'actual_end'   => date('Y-m-d H:i:s', $started + $worked + $downtime),
                'worked_sec'   => $worked,
                'downtime_sec' => $downtime,
            ))
            ->where('id', '=', (int) $operation_id)
            ->execute();
    }

    // =========================================================================
    //  Слои: качество, планирование, закупки
    // =========================================================================

    /**
     * Качество: справочник дефектов, входной контроль филамента и контроль
     * готовых партий, карантин и решения по браку.
     */
    protected function quality()
    {
        $codes        = Service_Erp405_DefectCodes::instance();
        $inspections  = Service_Erp405_Inspections::instance();
        $code_ids     = array();
        $inspection_n = 0;

        foreach ($this->_scenario->defects() as $defect)
        {
            $exists = ORM::factory('Erp405_DefectCode')->where('code', '=', $defect['code'])->find();

            if ( ! $exists->loaded())
            {
                $exists = $codes->create(array(
                    'code'     => $defect['code'],
                    'name'     => $defect['name'],
                    'category' => $defect['category'],
                    'note'     => $defect['note'],
                    'is_active' => 1,
                ), $this->user_id());
            }

            $code_ids[$defect['code']] = (int) $exists->id;
        }

        $this->count('defect_codes', count($code_ids));

        // Входной контроль филамента: каждая вторая приёмка с замечанием.
        foreach ($this->_scenario->materials() as $material)
        {
            if ($this->rand(1, 100) > 35)
            {
                continue;
            }

            try
            {
                $inspection = $inspections->register(array(
                    'kind'         => 'incoming',
                    'source'       => 'manual',
                    'item_id'      => $this->item($material['code']),
                    'qty'          => $this->rand(2, 6),
                    'warehouse_id' => $this->_warehouses['WH-MAT'],
                    'supplier_id'  => $this->_parties[$material['supplier']],
                    'note'         => 'Входной контроль катушки: диаметр, влажность, геометрия намотки',
                ), $this->user_id('quality'));

                $inspection_n++;

                if ($this->rand(1, 100) <= 25)
                {
                    // С браком партию нельзя признать годной «по-тихому»: сначала
                    // решение — здесь «использовать как есть» после сушки.
                    $inspections->add_defect($inspection->id, array(
                        'defect_code_id' => $code_ids['DEF-STRING'],
                        'qty'            => $this->rand(1, 2),
                        'cause'          => 'supplier',
                        'note'           => 'Катушка пришла влажной: печать «сопливит», материал отправлен на сушку',
                    ), $this->user_id('quality'));

                    $inspections->decide($inspection->id, 'use_as_is',
                        'Катушку просушили 6 часов при 55 °C, материал допущен в печать', $this->user_id('quality'));
                }
                else
                {
                    $inspections->mark_passed($inspection->id, $this->user_id('quality'));
                }
            }
            catch (Exception $e)
            {
                $this->fail('входной контроль '.$material['code'], $e->getMessage());
            }
        }

        // Контроль готовых партий: годные проходят, часть уходит в карантин.
        foreach ($this->_orders as $order_id => $data)
        {
            if ($data['state'] !== 'done')
            {
                continue;
            }

            $part   = $data['part'];
            $first  = $this->order_operations($order_id);
            $op_id  = $first ? (int) $first[0]['id'] : NULL;
            $scrap  = $this->rand(0, 100) <= 12 ? $this->rand(1, max(1, (int) round($data['batch'] * 0.03))) : 0;
            $good   = max(1, (int) $data['batch'] - $scrap);

            try
            {
                $inspection = $inspections->register(array(
                    'kind'         => 'final',
                    'source'       => 'erp402_order',
                    'item_id'      => $this->item($part['code']),
                    'item_name'    => $part['name'],
                    'qty'          => (int) $data['batch'],
                    'warehouse_id' => $this->_warehouses['WH-FIN'],
                    'order_id'     => (int) $order_id,
                    'operation_id' => $op_id,
                    'note'         => 'Контроль партии после печати и постобработки: '.$part['tolerance'],
                ), $this->user_id('quality'));

                $inspection_n++;
                $this->_inspections[(int) $order_id] = (int) $inspection->id;

                if ($scrap > 0)
                {
                    $defect = $this->_scenario->defects()[array_rand($this->_scenario->defects())];

                    $inspections->add_defect($inspection->id, array(
                        'defect_code_id' => $code_ids[$defect['code']],
                        'qty'            => $scrap,
                        'cause'          => $defect['category'] === 'material' ? 'equipment' : 'production',
                        'note'           => $defect['note'],
                    ), $this->user_id('quality'));

                    // Партия идёт в карантин: часть деталей можно доработать, часть — нет.
                    $inspections->quarantine($inspection->id, $this->_warehouses['WH-QAR'], $scrap, $this->user_id('quality'));

                    if ($this->rand(1, 100) <= 50)
                    {
                        $inspections->decide($inspection->id, 'rework', 'Снять поддержки и отшлифовать: деталь годная после доработки', $this->user_id('quality'));
                    }
                    else
                    {
                        $inspections->decide($inspection->id, 'scrap', 'Неисправимо: размер вне допуска', $this->user_id('quality'), array('scrap' => TRUE));
                    }
                }
                else
                {
                    $inspections->mark_passed($inspection->id, $this->user_id('quality'));
                }

                $this->count('good_qty', $good);
            }
            catch (Exception $e)
            {
                $this->fail('контроль партии заказа #'.$order_id, $e->getMessage());
            }
        }

        $this->count('inspections', $inspection_n);
    }

    /**
     * Планирование: программа выпуска на текущий месяц и расчёт потребности.
     */
    /**
     * Отгрузка готовой продукции: печатня работает под заказ клиента, поэтому
     * часть выпущенных партий со склада уехала, а часть ждёт отгрузки.
     *
     * Без этого слоя склад готовой продукции выглядел бы мёртвым: всё, что
     * напечатали, лежало бы и не двигалось — а витрина честно показывала бы
     * «мёртвый запас». Здесь же видно и оборот готовой продукции.
     */
    protected function shipments()
    {
        $stock = Service_Erp401_Stock::instance();
        $fin   = $this->_warehouses['WH-FIN'];
        $moves = 0;
        $qty   = 0;
        $money = 0.0;

        foreach ($this->_scenario->parts() as $index => $part)
        {
            // Каждая третья партия ещё не отгружена — ждёт отправки на складе.
            if ($index % 3 === 2)
            {
                continue;
            }

            $item_id = $this->item($part['code']);

            $balance = (float) DB::select('qty')
                ->from('erp_401_balances')
                ->where('warehouse_id', '=', (int) $fin)
                ->where('item_id', '=', (int) $item_id)
                ->execute()
                ->get('qty', 0);

            if ($balance <= 0)
            {
                continue;
            }

            // Уезжает 40–70 % партии: остаток на складе тоже должен быть виден.
            $out = (float) floor($balance * (0.4 + $this->rand(0, 3) * 0.1));

            if ($out <= 0)
            {
                continue;
            }

            try
            {
                $stock->issue($fin, $item_id, $out, array(
                    'doc_type'     => 'shipment',
                    'note'         => 'Отгрузка клиенту '.$this->_scenario->party_name($part['client']).' по заказу '.$part['code'],
                    'performed_at' => $this->day($this->rand(2, 30), '16:'.str_pad($this->rand(0, 59), 2, '0', STR_PAD_LEFT).':00'),
                ));

                $moves++;
                $qty += (int) $out;
                $money += $out * (float) $part['price'];
            }
            catch (Exception $e)
            {
                $this->fail('отгрузка '.$part['code'], $e->getMessage());
            }
        }

        $this->count('shipments', $moves);
        $this->count('shipped_qty', (int) $qty);
        $this->count('shipped_value', (int) round($money));

        $this->log('Отгрузка готовой продукции: '.$moves.' документов, '.(int) $qty.' шт, '
            .number_format($money, 0, ',', ' ').' ₽ (сумма по прайсу сценария)', 'debug');
    }

    protected function planning()
    {
        $programs = Service_Erp407_Programs::instance();
        $mrp      = Service_Erp407_Mrp::instance();

        $month = date('Y-m-01');

        $program = $programs->create(array(
            'title'       => 'Программа выпуска на '.date('m.Y'),
            'period_from' => $month,
            'period_to'   => date('Y-m-t', strtotime($month)),
            'status'      => 'draft',
            'note'        => 'План печатного участка: партии клиентов на текущий месяц',
        ), $this->user_id('manager'));

        foreach ($this->_orders as $order_id => $data)
        {
            if (in_array($data['state'], array('cancelled'), TRUE))
            {
                continue;
            }

            try
            {
                $programs->add_line($program->id, array(
                    'order_id'   => (int) $order_id,
                    'item_id'    => $this->item($data['part']['code']),
                    'name'       => $data['part']['name'],
                    'qty'        => (float) $data['batch'],
                    'due_date'   => date('Y-m-d', strtotime('+'.$this->rand(2, 20).' days')),
                    'priority'   => 3,
                ), $this->user_id('manager'));
            }
            catch (Exception $e)
            {
                $this->fail('строка программы: '.$data['part']['code'], $e->getMessage());
            }
        }

        $programs->approve($program, $this->user_id('manager'));

        try
        {
            $result = $mrp->calculate($program, array('user_id' => $this->user_id('manager')));

            $this->count('requirements', isset($result['rows']) ? count($result['rows']) : 0);

            // Дефицит без предложений обрывает демонстрационный planning →
            // procurement flow. Создаём открытые решения плана через штатный
            // сервис; экспорт в закупку остаётся отдельным бизнес-действием.
            $proposal_result = Service_Erp407_Proposals::instance()->create_from_requirements(
                $program,
                array(),
                $this->user_id('manager')
            );
            $this->count('proposals', (int) $proposal_result['created']);
        }
        catch (Exception $e)
        {
            $this->fail('расчёт потребности', $e->getMessage());
        }

        $this->count('programs', 1);
    }

    /**
     * Закупки: профили поставщиков, история цен, заявка, заказ и приёмка.
     */
    protected function procurement()
    {
        $suppliers = Service_Erp403_Suppliers::instance();
        $requests  = Service_Erp403_Requests::instance();
        $orders    = Service_Erp403_Orders::instance();
        $receipts  = Service_Erp403_Receipts::instance();

        // Профили и история цен: по ним закупка потом сравнивает предложения.
        foreach ($this->_scenario->suppliers() as $supplier)
        {
            $supplier_id = $this->_parties[$supplier['code']];

            try
            {
                $suppliers->save_profile($supplier_id, array(
                    'payment_terms' => $supplier['profile']['payment_terms'],
                    'delivery'      => $supplier['profile']['delivery'],
                    'min_order'     => $supplier['profile']['min_order'],
                    'lead_days'     => $supplier['lead_days'],
                    'note'          => 'Демонстрационный профиль: '.$supplier['city'],
                ), $this->user_id('manager'));
            }
            catch (Exception $e)
            {
                $this->fail('профиль поставщика '.$supplier['code'], $e->getMessage());
            }

            foreach ($this->_scenario->materials() as $material)
            {
                if ($material['supplier'] !== $supplier['code'])
                {
                    continue;
                }

                foreach (array(3, 1) as $months_ago)
                {
                    try
                    {
                        $suppliers->record_price(array(
                            'supplier_id' => $supplier_id,
                            'item_id'     => $this->item($material['code']),
                            'price'       => round($material['price'] * (1 - $months_ago * 0.03), 2),
                            'qty'         => $material['min_qty'] * 2,
                            'uom_code'    => 'шт',
                            'currency'    => 'RUB',
                            'price_date'  => date('Y-m-d', strtotime('-'.$months_ago.' months')),
                            'note'        => 'Закупка '.$material['polymer'].' ('.$material['color'].')',
                        ), $this->user_id('manager'));
                    }
                    catch (Exception $e)
                    {
                        $this->fail('история цен '.$supplier['code'].'/'.$material['code'], $e->getMessage());
                    }
                }
            }
        }

        // Заявка на материалы, у которых остаток ниже минимума: это те позиции,
        // по которым склад просит закупку. Название берём из номенклатуры —
        // строка остатка его не содержит.
        $stock   = Service_Erp401_Stock::instance();
        $low     = $stock->low_stock(20);
        $lines   = array();

        foreach ($low as $row)
        {
            $item = $stock->item((int) $row['item_id']);

            if ( ! $item)
            {
                continue;
            }

            $lines[] = array(
                'item_id'   => (int) $row['item_id'],
                'name'      => (string) $item['name'],
                'qty'       => max(2, (int) ceil(((float) $row['min_qty']) * 2)),
                'uom_code'  => 'шт',
                'price'     => 0,
                'need_date' => date('Y-m-d', strtotime('+10 days')),
                'note'      => 'Остаток ниже минимума: '.round((float) $row['qty'], 2).' при минимуме '.round((float) $row['min_qty'], 2),
            );
        }

        if ( ! $lines)
        {
            // Резервный путь: заявка на ходовой материал, чтобы стенд не остался без закупки.
            $material = $this->_scenario->materials()[0];

            $lines[] = array(
                'item_id'   => $this->item($material['code']),
                'name'      => $material['name'],
                'qty'       => 12,
                'uom_code'  => 'шт',
                'price'     => $material['price'],
                'need_date' => date('Y-m-d', strtotime('+10 days')),
                'note'      => 'Плановая закупка ходового материала',
            );
        }

        $request = $requests->create(array(
            'priority'     => 2,
            'source'       => 'manual',
            'warehouse_id' => $this->_warehouses['WH-MAT'],
            'need_date'    => date('Y-m-d', strtotime('+14 days')),
            'note'         => 'Закупка материалов по расчёту потребности',
            'lines'        => $lines,
        ), $this->user_id('manager'));

        try
        {
            $requests->submit($request, $this->user_id('manager'));
            $requests->approve($request, $this->user_id());
        }
        catch (Exception $e)
        {
            $this->fail('согласование заявки '.$request->number, $e->getMessage());
        }

        $this->count('requests', 1);

        // Заказ поставщику по заявке.
        $order_lines = array();

        foreach ($requests->lines($request->id) as $line)
        {
            $item_id = (int) $this->key($line, 'item_id');
            $price   = (float) $this->key($line, 'price');

            $order_lines[] = array(
                'item_id'  => $item_id,
                'name'     => (string) $this->key($line, 'name'),
                'qty'      => (float) $this->key($line, 'qty'),
                'uom_code' => (string) $this->key($line, 'uom_code'),
                'price'    => $price > 0 ? $price : $this->price_of($item_id),
                'status'   => 'open',
            );
        }

        $supplier_code = $this->_scenario->materials()[0]['supplier'];

        // Заказ по заявке: поставщик задерживает поставку — так на витрине
        // закупок видно просрочку, а не только «всё хорошо».
        $order = $orders->create(array(
            'supplier_id'   => $this->_parties[$supplier_code],
            'request_id'    => (int) $request->id,
            'warehouse_id'  => $this->_warehouses['WH-MAT'],
            'lines'         => $order_lines,
            'delivery_date' => date('Y-m-d', strtotime('-3 days')),
            'payment_terms' => 'отсрочка 14 дней',
            'note'          => 'Заказ на материалы для программы выпуска (поставщик просит отсрочку)',
        ), $this->user_id('manager'));

        try
        {
            $orders->send($order, $this->user_id('manager'));
            $orders->confirm($order, $this->user_id());
        }
        catch (Exception $e)
        {
            $this->fail('отправка заказа '.$order->number, $e->getMessage());
        }

        $this->count('purchase_orders', 1);
        $this->count('purchase_orders_overdue', 1);

        // Ещё один заказ в пути — на этот раз в срок.
        $second = $orders->create(array(
            'supplier_id'   => $this->_parties[$this->_scenario->suppliers()[1]['code']],
            'warehouse_id'  => $this->_warehouses['WH-MAT'],
            'lines'         => array(
                array(
                    'item_id'  => $this->item($this->_scenario->materials()[1]['code']),
                    'name'     => $this->_scenario->materials()[1]['name'],
                    'qty'      => 6,
                    'uom_code' => 'шт',
                    'price'    => $this->_scenario->materials()[1]['price'],
                    'status'   => 'open',
                ),
            ),
            'delivery_date' => date('Y-m-d', strtotime('+7 days')),
            'note'         => 'Пополнение PETG: расход вырос из-за форм для печенья',
        ), $this->user_id('manager'));

        $orders->send($second, $this->user_id('manager'));

        $this->count('purchase_orders_open', 1);

        // Ещё две закупки в работе: расходники и смола. По ним просрочки нет —
        // в работе у поставщика остаётся несколько заказов, как в жизни.
        foreach (array(
            array('code' => 'CON-NOZ-04', 'qty' => 20, 'days' => 5,  'note' => 'Сменные сопла 0,4 и 0,6 мм на квартал'),
            array('code' => 'MAT-RES-GRY', 'qty' => 4, 'days' => 12, 'note' => 'Смола для SLA: растёт доля точных деталей'),
        ) as $extra)
        {
            try
            {
                $material = NULL;

                foreach ($this->_scenario->materials() as $row)
                {
                    if ($row['code'] === $extra['code'])
                    {
                        $material = $row;
                    }
                }

                $extra_order = $orders->create(array(
                    'supplier_id'   => $this->_parties[$material['supplier']],
                    'warehouse_id'  => $this->_warehouses['WH-MAT'],
                    'lines'         => array(
                        array(
                            'item_id'  => $this->item($material['code']),
                            'name'     => $material['name'],
                            'qty'      => $extra['qty'],
                            'uom_code' => 'шт',
                            'price'    => $material['price'],
                            'status'   => 'open',
                        ),
                    ),
                    'delivery_date' => date('Y-m-d', strtotime('+'.$extra['days'].' days')),
                    'note'          => $extra['note'],
                ), $this->user_id('manager'));

                $orders->send($extra_order, $this->user_id('manager'));
            }
            catch (Exception $e)
            {
                $this->fail('закупка '.$extra['code'], $e->getMessage());
            }
        }

        $this->count('purchase_orders_transit', 3);

        // Закрытая закупка истории: заказ принят на склад с входным контролем
        // по строкам. Материалы не из «просаженных» позиций, иначе дефицит на
        // стенде закроется сам собой.
        try
        {
            $received_order = $orders->create(array(
                'supplier_id'   => $this->_parties[$supplier_code],
                'warehouse_id'  => $this->_warehouses['WH-MAT'],
                'lines'         => array(
                    array('item_id' => $this->item('MAT-PLA-BLK'), 'name' => 'Пластик PLA, чёрный, 1,75 мм, 1 кг',
                          'qty' => 6, 'uom_code' => 'шт', 'price' => 1850, 'status' => 'open'),
                    array('item_id' => $this->item('MAT-ABS-BLK'), 'name' => 'Пластик ABS, чёрный, 1,75 мм, 1 кг',
                          'qty' => 4, 'uom_code' => 'шт', 'price' => 2400, 'status' => 'open'),
                ),
                'delivery_date' => date('Y-m-d', strtotime('-12 days')),
                'note'          => 'Плановая закупка ходовых материалов',
            ), $this->user_id('manager'));

            $orders->send($received_order, $this->user_id('manager'));
            $orders->confirm($received_order, $this->user_id());

            $receipt = $receipts->create(array(
                'order_id'     => (int) $received_order->id,
                'warehouse_id' => $this->_warehouses['WH-MAT'],
                'received_at'  => date('Y-m-d', strtotime('-'.$this->rand(8, 11).' days')),
                'note'         => 'Приёмка по заказу '.$received_order->number,
            ), $this->user_id('manager'));

            // Строки приёмки сервис копирует из заказа сам; добавляем только
            // если приёмка создана без заказа — иначе позиции удваиваются и на
            // склад приходит вдвое больше материала.
            if ( ! $receipts->lines($receipt->id))
            {
                foreach ($orders->lines($received_order->id) as $line)
                {
                    $receipts->add_line($receipt->id, array(
                        'item_id'  => (int) $this->key($line, 'item_id'),
                        'name'     => (string) $this->key($line, 'name'),
                        'qty'      => (float) $this->key($line, 'qty'),
                        'price'    => (float) $this->key($line, 'price'),
                        'uom_code' => (string) $this->key($line, 'uom_code'),
                    ));
                }
            }

            $receipt_n = 0;

            // Входной контроль по строкам: одна катушка приходит с замечанием,
            // по ней решение «использовать как есть» после сушки.
            foreach ($receipts->lines($receipt->id) as $line)
            {
                $receipt_n++;

                $ok = ($receipt_n > 1) OR ($this->rand(1, 100) > 40);

                $receipts->set_quality((int) $this->key($line, 'id'), $ok ? 'passed' : 'pending',
                    $ok ? 'Катушки целые, вакуумная упаковка не нарушена' : 'Одна катушка с повреждённой упаковкой: ждёт решения по браку');
            }

            $receipts->post($receipt, $this->user_id('manager'));

            $this->count('receipts', 1);
        }
        catch (Exception $e)
        {
            $this->fail('закрытая закупка с приёмкой', $e->getMessage());
        }
    }

    /**
     * Документы: паспорта качества на закрытые партии и заказ поставщику.
     */
    protected function documents()
    {
        if ( ! class_exists('Service_Erp404_Documents'))
        {
            return;
        }

        $documents = Service_Erp404_Documents::instance();
        $printed   = 0;

        foreach ($this->_orders as $order_id => $data)
        {
            if ($data['state'] !== 'done')
            {
                continue;
            }

            try
            {
                // Паспорт печатаем по контролю партии (erp405): в нём есть
                // результат, решение по браку и список дефектов — именно это
                // шаблон паспорта и показывает.
                $ref_type = isset($this->_inspections[$order_id]) ? 'erp405_inspection' : 'erp402_order';
                $ref_id   = isset($this->_inspections[$order_id]) ? $this->_inspections[$order_id] : (int) $order_id;

                $document = $documents->create(array(
                    'template_code' => 'QUALITY-PASSPORT',
                    'ref_type'      => $ref_type,
                    'ref_id'        => (int) $ref_id,
                    'title'         => 'Паспорт качества: '.$data['part']['name'].' (партия '.$data['batch'].' шт.)',
                    'note'          => 'Контроль размеров по допуску '.$data['part']['tolerance'],
                ), $this->user_id('quality'));

                $documents->issue($document->id, $this->user_id('quality'));
                $documents->print_document($document->id, $this->user_id('quality'), $this->rand(1, 2), 'Печать на стенд');
                $printed++;
            }
            catch (Exception $e)
            {
                $this->fail('паспорт качества по заказу #'.$order_id, $e->getMessage());
            }
        }

        $this->count('documents', $printed);
    }

    /**
     * Аналитика: пороги «как в жизни» и снимки витрин.
     *
     * Снимки нужны, чтобы на стенде работала витрина сравнения «было/стало»:
     * без них первый же экран снимков пуст.
     */
    protected function analytics()
    {
        $settings  = Service_Erp801_Settings::instance();
        $snapshots = Service_Erp801_Snapshots::instance();

        $settings->save(array(
            'cover_target_days'    => 21,
            'dead_stock_days'      => 60,
            'abc_a_share'          => 75,
            'abc_b_share'          => 92,
            'xyz_x_cv'             => 30,
            'xyz_y_cv'             => 60,
            'oee_target'           => 60,
            'defect_share_target'  => 8,
            'overdue_target'       => 12,
        ), $this->user_id());

        $this->count('settings', 9);

        $snapshots_n = 0;

        $plan = array(
            array('code' => 'stock',      'title' => 'Запасы: оборачиваемость и покрытие', 'days' => 14),
            array('code' => 'production', 'title' => 'Производство: OEE и простои',        'days' => 7),
            array('code' => 'quality',    'title' => 'Качество: брак и причины',           'days' => 21),
        );

        foreach ($plan as $row)
        {
            try
            {
                $snapshots->save(
                    $row['code'],
                    array(
                        'from' => date('Y-m-d', strtotime('-'.$row['days'].' days')),
                        'to'   => date('Y-m-d'),
                    ),
                    $this->user_id('manager'),
                    $row['title'].' — снимок на '.date('d.m.Y'),
                    'Ежемесячный снимок витрины для сравнения «было/стало»',
                    date('Y-m-d', strtotime('-3 days'))
                );

                $snapshots_n++;
            }
            catch (Exception $e)
            {
                $this->fail('снимок витрины '.$row['code'], $e->getMessage());
            }
        }

        $this->count('snapshots', $snapshots_n);
    }

    // =========================================================================
    //  Помощники
    // =========================================================================

    /**
     * Где именно упало: файл и строка (для отчёта сборки).
     *
     * @param   Throwable   $e
     * @return  string
     */
    protected function origin($e)
    {
        return str_replace(APPPATH, '', $e->getFile()).':'.$e->getLine();
    }

    /**
     * Привести вид контрагента к справочнику модели (legal | individual | state | foreign).
     *
     * @param   string  $kind
     * @return  string
     */
    protected function kind_of($kind)
    {
        $map = array(
            'company'      => 'legal',
            'entrepreneur' => 'individual',
            'state'        => 'state',
            'foreign'      => 'foreign',
        );

        return isset($map[$kind]) ? $map[$kind] : 'legal';
    }

    /**
     * Идентификатор из ответа сервиса: сервисы цеха возвращают массивы,
     * а не ORM-модели, поэтому читаем id одинаково в обоих случаях.
     *
     * @param   mixed   $row
     * @param   string  $field
     * @return  mixed
     */
    protected function key($row, $field)
    {
        if (is_array($row))
        {
            return isset($row[$field]) ? $row[$field] : NULL;
        }

        if (is_object($row))
        {
            return isset($row->$field) ? $row->$field : NULL;
        }

        return NULL;
    }

    /**
     * Идентификатор позиции номенклатуры по коду сценария.
     *
     * @param   string  $code
     * @return  int
     */
    protected function item($code)
    {
        if (isset($this->_items[$code]))
        {
            return $this->_items[$code];
        }

        $types = array('material', 'part');

        foreach ($types as $type)
        {
            $object = ORM::factory('Erp227_Object')
                ->where('code', '=', $code)
                ->find();

            if ($object->loaded())
            {
                $this->_items[$code] = (int) $object->id;

                return (int) $object->id;
            }
        }

        throw new Kohana_Exception('В демо-базе нет позиции :code', array(':code' => $code));
    }

    /**
     * Материал детали из сценария.
     *
     * @param   array   $part
     * @return  array
     */
    protected function material_of(array $part)
    {
        foreach ($this->_scenario->materials() as $material)
        {
            if ($material['code'] === $part['material'])
            {
                return $material;
            }
        }

        throw new Kohana_Exception('В сценарии нет материала :code', array(':code' => $part['material']));
    }

    /**
     * Какому центру достаётся операция: печать — принтеру, остальное — участку.
     *
     * @param   array   $part
     * @param   int     $index
     * @return  string  код центра
     */
    protected function center_for(array $part, $index)
    {
        $printers = array('WC-PRNT-01', 'WC-PRNT-02', 'WC-PRNT-03', 'WC-PRNT-04', 'WC-RESIN-05');

        if ($index === 0)
        {
            return $printers[array_rand($printers)];
        }

        if ($index === 1)
        {
            return 'WC-POST-06';
        }

        return 'WC-QC-07';
    }

    /**
     * Цена позиции из сценария (для строк заказа поставщику).
     *
     * @param   int $item_id
     * @return  float
     */
    protected function price_of($item_id)
    {
        foreach ($this->_scenario->materials() as $material)
        {
            if ( ! isset($this->_items[$material['code']]))
            {
                continue;
            }

            if ((int) $this->_items[$material['code']] === (int) $item_id)
            {
                return (float) $material['price'];
            }
        }

        return 0.0;
    }

    // =========================================================================
    //  Слои: сотрудники, CRM, составы изделий, QR
    //
    //  Логика этих слоёв живёт в `Service_Erp811_Contour`: они опираются на
    //  результат производственной цепочки, но не продолжают её. Здесь — только
    //  передача контекста и разбор результата в счётчики и замечания сборки.
    // =========================================================================

    /**
     * Сотрудники, подразделения, должности и штатное расписание (erp310).
     */
    protected function workforce()
    {
        $this->absorb(Service_Erp811_Contour::instance()->workforce($this->contour_context()), 'Сотрудники и оргструктура');
    }

    /**
     * Профили клиентов, контакты, сделки и база знаний (erp221).
     */
    protected function crm()
    {
        $this->absorb(Service_Erp811_Contour::instance()->crm($this->contour_context()), 'CRM');
    }

    /**
     * Составы изделий: EBOM, MBOM, ревизии и комплект (erp231).
     */
    protected function product_structure()
    {
        $this->absorb(Service_Erp811_Contour::instance()->product_structure($this->contour_context()), 'Составы изделий');
    }

    /**
     * QR-наклейки, сканирования и инвентаризации (erp199).
     */
    protected function qr()
    {
        $this->absorb(Service_Erp811_Contour::instance()->qr($this->contour_context()), 'QR-метки');
    }

    /**
     * То, что слоям контура нужно знать о уже собранном стенде.
     *
     * @return  array
     */
    protected function contour_context()
    {
        return array(
            'actor'      => $this->_admin_id ?: $this->user_id('admin'),
            'users'      => array(
                'admin'   => $this->user_id('admin'),
                'manager' => $this->user_id('manager'),
                'worker'  => $this->user_id('worker'),
                'quality' => $this->user_id('quality'),
            ),
            'parties'    => $this->_parties,
            'items'      => $this->_items,
            'cards'      => $this->_cards,
            'orders'     => array_keys($this->_orders),
            'warehouses' => $this->_warehouses,
        );
    }

    /**
     * Принять результат слоя контура: счётчики — в итоги сборки, замечания — в отчёт.
     *
     * @param   array   $result     counts, problems
     * @param   string  $label
     * @return  void
     */
    protected function absorb(array $result, $label)
    {
        foreach ($result['counts'] as $key => $value)
        {
            $this->count($key, $value);
        }

        foreach ($result['problems'] as $message)
        {
            $this->fail($label, $message);
        }
    }

    /**
     * Идентификатор пользователя по роли.
     *
     * @param   string  $key
     * @return  int|null
     */
    protected function user_id($key = 'admin')
    {
        static $cache = array();

        if (isset($cache[$key]))
        {
            return $cache[$key];
        }

        $email = array(
            'admin'    => 'admin@demo.promerp.local',
            'manager'  => 'manager@demo.promerp.local',
            'worker'   => 'master@demo.promerp.local',
            'quality'  => 'quality@demo.promerp.local',
        );

        if ($key === 'guest')
        {
            $email['guest'] = (string) $this->_demo->config('guest_email');
        }

        $address = isset($email[$key]) ? $email[$key] : $email['admin'];

        $user = ORM::factory('User')->where('email', '=', $address)->find();

        $cache[$key] = $user->loaded() ? (int) $user->id : NULL;

        return $cache[$key];
    }

    /**
     * Дата со сдвигом в прошлое.
     *
     * @param   int     $days_ago
     * @param   string  $time
     * @return  string
     */
    protected function day($days_ago, $time = '10:00:00')
    {
        return date('Y-m-d', strtotime('-'.(int) $days_ago.' days')).' '.$time;
    }

    /**
     * Детерминированный «случайный» порядок: стенд повторяем.
     *
     * @param   int $min
     * @param   int $max
     * @return  int
     */
    protected function rand($min, $max)
    {
        return mt_rand((int) $min, (int) $max);
    }

    /**
     * @param   int $value
     * @return  string
     */
    protected function padValue($value)
    {
        return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param   string  $key
     * @param   int     $value
     * @return  void
     */
    protected function count($key, $value)
    {
        // Килограммы и деньги стенд считает с точностью до сотых, штуки —
        // целыми: (int) превращал «0,91 кг образцов» в ноль.
        $value = is_float($value) ? round($value, 2) : (int) $value;

        $this->_counts[$key] = (isset($this->_counts[$key]) ? $this->_counts[$key] : 0) + $value;
    }

    /**
     * @param   string  $layer
     * @param   string  $message
     * @return  void
     */
    protected function fail($layer, $message)
    {
        $this->_problems[] = array('layer' => $layer, 'message' => $message);

        $this->log('  ! '.$layer.': '.$message, 'warn');
    }

    /**
     * @param   string  $message
     * @param   string  $level
     * @return  void
     */
    protected function log($message, $level = 'info')
    {
        if ($this->_logger !== NULL)
        {
            call_user_func($this->_logger, $message, $level);
        }
    }

    /**
     * @param   float   $started
     * @return  string
     */
    protected function took($started)
    {
        return sprintf('%.2f с', microtime(TRUE) - $started);
    }
}
