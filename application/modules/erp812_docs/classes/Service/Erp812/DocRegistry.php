<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Service_Erp812_DocRegistry — реестр автоматического сбора, анализа и структурирования
 * документации по всем модулям и сквозным бизнес-процессам PromERP.
 *
 * @package    Erp812
 * @category   Services
 */
class Service_Erp812_DocRegistry
{
    /**
     * @var Service_Erp812_DocRegistry
     */
    protected static $_instance;

    /**
     * @var array Кэш списка модулей
     */
    protected $_modules_cache;

    /**
     * @return Service_Erp812_DocRegistry
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Мета-информация о категориях модулей.
     *
     * @return array
     */
    public function categories()
    {
        return array(
            'production' => array(
                'id'          => 'production',
                'title'       => 'Производство и цех',
                'description' => 'Управление производственными заказами, технологическими картами, цеховыми сменами, MRP-планированием и контролем брака.',
                'icon'        => 'fas fa-industry',
                'color'       => 'blue',
            ),
            'supply' => array(
                'id'          => 'supply',
                'title'       => 'Снабжение и продажи',
                'description' => 'Управление цепочками поставок, закупками, договорами с контрагентами и выпуском первичных документов.',
                'icon'        => 'fas fa-truck-ramp-box',
                'color'       => 'amber',
            ),
            'analytics' => array(
                'id'          => 'analytics',
                'title'       => 'Аналитика, AI и MCP',
                'description' => 'Аналитические витрины OEE, KPI, ABC/XYZ анализ, агентский MCP-шлюз и интерактивный демонстрационный стенд.',
                'icon'        => 'fas fa-chart-line',
                'color'       => 'emerald',
            ),
            'core' => array(
                'id'          => 'core',
                'title'       => 'Ядро и инфраструктура',
                'description' => 'Базовые системные сервисы: ACL, авторизация, журнал аудита, шина событий, файловое хранилище, MDM и миграции PostgreSQL.',
                'icon'        => 'fas fa-cubes',
                'color'       => 'indigo',
            ),
        );
    }

    /**
     * Получение полного каталога модулей с автоматическим анализом исходных файлов.
     *
     * @return array
     */
    public function modules()
    {
        if ($this->_modules_cache !== NULL)
        {
            return $this->_modules_cache;
        }

        $all_modules = Kohana::modules();
        $acl_config = (array) Kohana::$config->load('acl');
        $result = array();

        foreach ($all_modules as $name => $path)
        {
            if (strpos($name, 'erp') !== 0 && $name !== 'llm')
            {
                continue;
            }

            $info = $this->_inspect_module($name, $path, $acl_config);
            $result[$name] = $info;
        }

        // Сортировка по ID модуля (erp100, erp111, erp208...)
        uasort($result, function ($a, $b) {
            return strnatcmp($a['id_code'], $b['id_code']);
        });

        $this->_modules_cache = $result;
        return $result;
    }

    /**
     * Получение информации об одном модуле.
     *
     * @param   string  $module_name
     * @return  array|null
     */
    public function module($module_name)
    {
        $modules = $this->modules();
        return isset($modules[$module_name]) ? $modules[$module_name] : NULL;
    }

    /**
     * Анализ каталога модуля: парсинг README.md, поиск маршрутов, MCP, таблиц и прав.
     *
     * @param   string  $name
     * @param   string  $path
     * @param   array   $acl_config
     * @return  array
     */
    protected function _inspect_module($name, $path, array $acl_config)
    {
        $id_code = preg_match('/^erp(\d+)/', $name, $m) ? (int) $m[1] : 999;
        $readme_path = rtrim($path, '/') . '/README.md';
        $readme_raw = is_file($readme_path) ? (string) file_get_contents($readme_path) : '';

        // Извлечение заголовка из README (# Заголовок)
        $title = $name;
        $description = '';
        if (preg_match('/^#\s+(.+)$/m', $readme_raw, $m))
        {
            $title = trim($m[1]);
        }

        // Извлечение первого абзаца описания
        if (preg_match('/^#\s+.+\n+([^#\n].+)$/m', $readme_raw, $m))
        {
            $description = trim($m[1]);
        }

        // Определение категории
        $category = 'core';
        if (in_array($name, array('erp402_production', 'erp228_tech_cards', 'erp408_shopfloor', 'erp407_planning', 'erp401_wms', 'erp405_quality'), TRUE))
        {
            $category = 'production';
        }
        elseif (in_array($name, array('erp403_procurement', 'erp301_counterparty', 'erp404_documents', 'erp221_crm'), TRUE))
        {
            $category = 'supply';
        }
        elseif (in_array($name, array('erp801_analytics', 'erp804_mcp', 'erp811_demo', 'erp812_docs', 'llm'), TRUE))
        {
            $category = 'analytics';
        }

        // Поиск маршрутов из init.php
        $routes = $this->_scan_routes(rtrim($path, '/') . '/init.php');

        // Поиск MCP-инструментов из mcp.php
        $mcp_tools = $this->_scan_mcp_tools(rtrim($path, '/') . '/mcp.php');

        // Поиск миграций и таблиц из migrations_pg/
        $tables = $this->_scan_tables(rtrim($path, '/') . '/migrations_pg');

        // Поиск прав доступа в config/acl.php
        $permissions = $this->_find_permissions($name, $acl_config);

        // Поиск контроллеров
        $controllers = $this->_scan_controllers(rtrim($path, '/') . '/classes/Controller');

        // Иконка модуля
        $icons = array(
            'erp402_production'          => 'fas fa-industry',
            'erp228_tech_cards'          => 'fas fa-diagram-project',
            'erp408_shopfloor'           => 'fas fa-stopwatch',
            'erp407_planning'            => 'fas fa-calendar-days',
            'erp401_wms'                 => 'fas fa-boxes-stacked',
            'erp405_quality'             => 'fas fa-clipboard-check',
            'erp403_procurement'         => 'fas fa-truck-ramp-box',
            'erp301_counterparty'        => 'fas fa-handshake',
            'erp404_documents'           => 'fas fa-file-lines',
            'erp801_analytics'           => 'fas fa-chart-line',
            'erp804_mcp'                 => 'fas fa-robot',
            'erp811_demo'                => 'fas fa-play',
            'erp812_docs'                => 'fas fa-book-open',
            'erp119_audit'               => 'fas fa-clock-rotate-left',
            'erp212_file_storage'        => 'fas fa-folder-tree',
            'erp218_module_manager'      => 'fas fa-cubes',
            'erp230_mdm'                 => 'fas fa-database',
            'erp208_events'              => 'fas fa-bolt',
            'erp217_notification_center' => 'fas fa-bell',
            'erp118_links'               => 'fas fa-bookmark',
            'erp111_migrations_postgresql' => 'fas fa-database',
            'erp100_base'                => 'fas fa-shield-halved',
            'erp227_core_registry'       => 'fas fa-rectangle-list',
        );
        $icon = isset($icons[$name]) ? $icons[$name] : 'fas fa-cube';

        return array(
            'name'         => $name,
            'id_code'      => $id_code,
            'title'        => $title,
            'description'  => $description,
            'category'     => $category,
            'icon'         => $icon,
            'db_group'     => ($name === 'erp230_mdm' ? 'mdm' : 'default'),
            'path'         => $path,
            'readme_raw'   => $readme_raw,
            'routes'       => $routes,
            'mcp_tools'    => $mcp_tools,
            'tables'       => $tables,
            'acl'          => $permissions,
            'permissions'  => $permissions,
            'controllers'  => $controllers,
            'has_api'      => !empty($mcp_tools) || !empty(glob(rtrim($path, '/') . '/classes/Controller/*/Api.php')),
        );
    }

    /**
     * Поиск маршрутов в init.php.
     */
    protected function _scan_routes($init_file)
    {
        if (!is_file($init_file))
        {
            return array();
        }

        $content = (string) file_get_contents($init_file);
        $routes = array();

        if (preg_match_all("/Route::set\(\s*'([^']+)'\s*,\s*'([^']+)'\)/", $content, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $routes[] = array(
                    'name' => $match[1],
                    'uri'  => $match[2],
                );
            }
        }

        return $routes;
    }

    /**
     * Поиск MCP инструментов в mcp.php.
     */
    protected function _scan_mcp_tools($mcp_file)
    {
        if (!is_file($mcp_file))
        {
            return array();
        }

        try
        {
            $data = include $mcp_file;
            if (is_array($data) && isset($data['tools']) && is_array($data['tools']))
            {
                $tools = array();
                foreach ($data['tools'] as $tool_name => $tool_def)
                {
                    $tools[] = array(
                        'name'        => $tool_name,
                        'title'       => isset($tool_def['title']) ? $tool_def['title'] : $tool_name,
                        'description' => isset($tool_def['description']) ? $tool_def['description'] : '',
                        'scope'       => isset($tool_def['scope']) ? $tool_def['scope'] : '',
                        'mutating'    => !empty($tool_def['mutating']),
                        'approval'    => !empty($tool_def['approval']),
                        'schema'      => isset($tool_def['schema']) ? $tool_def['schema'] : array(),
                    );
                }
                return $tools;
            }
        }
        catch (Exception $e) {}

        return array();
    }

    /**
     * Поиск создаваемых таблиц в миграциях.
     */
    protected function _scan_tables($migrations_dir)
    {
        if (!is_dir($migrations_dir))
        {
            return array();
        }

        $tables = array();
        foreach (glob($migrations_dir . '/*.php') as $file)
        {
            $content = (string) file_get_contents($file);
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z0-9_\."]+)/i', $content, $matches))
            {
                foreach ($matches[1] as $tbl)
                {
                    $clean_tbl = trim(str_replace(array('"', "'"), '', $tbl));
                    if (!in_array($clean_tbl, $tables, TRUE))
                    {
                        $tables[] = $clean_tbl;
                    }
                }
            }
        }

        return $tables;
    }

    /**
     * Поиск прав модуля в ACL.
     */
    protected function _find_permissions($module_name, array $acl_config)
    {
        $perms = array();
        $prefix = preg_replace('/^([a-z0-9]+)_.+$/', '$1', $module_name);

        foreach ($acl_config as $perm => $roles)
        {
            if (strpos($perm, $prefix) === 0 || strpos($perm, $module_name) === 0)
            {
                $perms[$perm] = (array) $roles;
            }
        }

        return $perms;
    }

    /**
     * Поиск контроллеров.
     */
    protected function _scan_controllers($controller_dir)
    {
        if (!is_dir($controller_dir))
        {
            return array();
        }

        $controllers = array();
        foreach (glob($controller_dir . '/*/*.php') as $file)
        {
            $class_name = basename($file, '.php');
            $rel_path = basename(dirname($file)) . '/' . $class_name;
            $controllers[] = array(
                'name' => $class_name,
                'path' => $rel_path,
            );
        }

        return $controllers;
    }

    /**
     * Список сквозных интерактивных бизнес-процессов с Mermaid-диаграммами.
     *
     * @return array
     */
    public function business_processes()
    {
        return array(
            'p2p' => array(
                'id'          => 'p2p',
                'title'       => 'Закупки и снабжение (Procure-to-Pay)',
                'icon'        => 'fas fa-truck-ramp-box',
                'badge'       => 'P2P Flow',
                'description' => 'Сквозной процесс обеспечения сырьем: от возникновения потребности цеха и планирования MRP до заказа поставщику, входного контроля ОТК и оприходования на склад.',
                'modules'     => array('erp407_planning', 'erp403_procurement', 'erp301_counterparty', 'erp405_quality', 'erp401_wms'),
                'mermaid'     => "graph TD
    Need[\"📝 1. Потребность цеха / дефицит MRP\"] --> Req[\"📋 2. Заявка на закупку (erp403)\"]
    Req --> Appr{\"⚖️ 3. Согласование заявки\"}
    Appr -- Отклонено --> Reject[\"❌ Отклонено\"]
    Appr -- Утверждено --> PO[\"📜 4. Заказ поставщику (erp403)\"]
    PO --> Delivery[\"🚚 5. Доставка и накладная\"]
    Delivery --> Receipt[\"📦 6. Приемка ТМЦ\"]
    Receipt --> QC{\"🔍 7. Входной контроль (erp405)\"}
    QC -- Брак --> Scrap[\"❌ Акт брака / Карантин\"]
    QC -- Годно --> Stock[\"✅ Оприходование на склад (erp401)\"]",
                'steps' => array(
                    array('step' => 1, 'role' => 'Плановик / Мастер', 'module' => 'erp407_planning', 'title' => 'Выявление потребности', 'desc' => 'Автоматический расчет дефицита сырья на основе производственной программы или ручная заявка.'),
                    array('step' => 2, 'role' => 'Менеджер закупки', 'module' => 'erp403_procurement', 'title' => 'Оформление заявки', 'desc' => 'Создание заявки на закупку с указанием номенклатуры, количества и планового срока поставки.'),
                    array('step' => 3, 'role' => 'Руководитель', 'module' => 'erp403_procurement', 'title' => 'Согласование и выбор контрагента', 'desc' => 'Проверка лимитов бюджета, выбор поставщика из реестра erp301 и согласование условий оплаты.'),
                    array('step' => 4, 'role' => 'Менеджер закупки', 'module' => 'erp403_procurement', 'title' => 'Заказ поставщику', 'desc' => 'Формирование печатной формы заказа (erp404) и отправка спецификации контрагенту.'),
                    array('step' => 5, 'role' => 'Кладовщик / ОТК', 'module' => 'erp405_quality', 'title' => 'Приемка и входной контроль', 'desc' => 'Сверка фактически прибывшего количества с накладной и проведение входного контроля качества.'),
                    array('step' => 6, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Оприходование на склад', 'desc' => 'Проведение приходного складского ордера и увеличение остатка свободных запасов.'),
                ),
            ),

            'o2c' => array(
                'id'          => 'o2c',
                'title'       => 'Продажи и отгрузка (Order-to-Cash)',
                'icon'        => 'fas fa-briefcase',
                'badge'       => 'O2C Flow',
                'description' => 'Жизненный цикл клиентского заказа: от регистрации лида и коммерческого предложения до резервирования продукции на складе, выпуска и отгрузки клиенту.',
                'modules'     => array('erp301_counterparty', 'erp401_wms', 'erp404_documents', 'erp801_analytics'),
                'mermaid'     => "graph TD
    Client[\"👤 Клиент: Запрос / Заказ\"] --> Quote[\"📑 1. КП и расчет цен\"]
    Quote --> Contract[\"📜 2. Договор с клиентом (erp301)\"]
    Contract --> CheckStock{\"📦 3. Проверка остатка ГП\"}
    CheckStock -- В наличии --> Reserve[\"🔒 Резерв на складе (erp401)\"]
    CheckStock -- Нет на складе --> Prod[\"⚙️ Заказ на производство (erp402)\"]
    Prod --> Reserve
    Reserve --> PrintDoc[\"📄 4. Комплект УПД и ТОРГ-12 (erp404)\"]
    PrintDoc --> Ship[\"🚚 5. Отгрузка со склада\"]
    Ship --> Close[\"💰 6. Закрытие сделки и аналитика (erp801)\"]",
                'steps' => array(
                    array('step' => 1, 'role' => 'Менеджер по продажам', 'module' => 'erp301_counterparty', 'title' => 'Регистрация заказа и клиента', 'desc' => 'Создание карточки контрагента, проверка ИНН и фиксация договоренностей.'),
                    array('step' => 2, 'role' => 'Менеджер / Кладовщик', 'module' => 'erp401_wms', 'title' => 'Проверка складских остатков', 'desc' => 'Проверка доступности готовой продукции на складе или формирование заказа в цех.'),
                    array('step' => 3, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Резервирование продукции', 'desc' => 'Установка складского резерва под конкретный номер заказа клиента.'),
                    array('step' => 4, 'role' => 'Бухгалтер / Оператор', 'module' => 'erp404_documents', 'title' => 'Формирование отгрузочных документов', 'desc' => 'Генерация УПД, транспортных накладных и паспортов качества изделий.'),
                    array('step' => 5, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Отгрузка и списание', 'desc' => 'Проведение расходной накладной и списание со склада готовой продукции.'),
                ),
            ),

            'production' => array(
                'id'          => 'production',
                'title'       => 'Производственный цикл изделия',
                'icon'        => 'fas fa-industry',
                'badge'       => 'Shopfloor Flow',
                'description' => 'Управление выпуском продукции в цехе: выбор техкарты, списание материалов, назначение на рабочие центры и учет фактической выработки.',
                'modules'     => array('erp228_tech_cards', 'erp402_production', 'erp408_shopfloor', 'erp405_quality', 'erp401_wms'),
                'mermaid'     => "graph TD
    Order[\"📋 1. Заказ на выпуск (erp402)\"] --> TechCard[\"📐 2. Технологическая карта (erp228)\"]
    TechCard --> BOM[\"📦 3. Резерв сырья на складе (erp401)\"]
    BOM --> Dispatch[\"🗓️ 4. План-график смены (erp408)\"]
    Dispatch --> Terminal[\"💻 5. Терминал рабочего места (erp408)\"]
    Terminal --> QC{\"🔍 6. Контроль ОТК (erp405)\"}
    QC -- Годно --> Complete[\"✅ Выпуск ГП на склад\"]
    QC -- Брак --> Rework[\"⚠️ Акт брака / Доработка\"]",
                'steps' => array(
                    array('step' => 1, 'role' => 'Мастер / Диспетчер', 'module' => 'erp402_production', 'title' => 'Создание производственного заказа', 'desc' => 'Утверждение номенклатуры, планового объема выпуска и целевой даты готовности.'),
                    array('step' => 2, 'role' => 'Технолог', 'module' => 'erp228_tech_cards', 'title' => 'Привязка технологической карты', 'desc' => 'Заполнение спецификации сырья (BOM) и маршрута технологических операций.'),
                    array('step' => 3, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Выдача материалов в производство', 'desc' => 'Перемещение необходимых компонентов со склада материалов в цеховой склад.'),
                    array('step' => 4, 'role' => 'Оператор станка', 'module' => 'erp408_shopfloor', 'title' => 'Фиксация операций на терминале', 'desc' => 'Отметка начала работы, учет времени выполнения, регистрация простоев и штучной выработки.'),
                    array('step' => 5, 'role' => 'Инспектор ОТК', 'module' => 'erp405_quality', 'title' => 'Выходной контроль качества', 'desc' => 'Проверка партии готовой продукции на соответствие чертежам и ГОСТ/ТУ.'),
                    array('step' => 6, 'role' => 'Мастер цеха', 'module' => 'erp402_production', 'title' => 'Завершение заказа и выпуск', 'desc' => 'Оформление акта выпуска продукции и списание материалов по фактическим нормам.'),
                ),
            ),

            'wms_flow' => array(
                'id'          => 'wms_flow',
                'title'       => 'Складская логистика и управление остатками',
                'icon'        => 'fas fa-boxes-stacked',
                'badge'       => 'WMS Flow',
                'description' => 'Управление складскими операциями: топология складов, приход, перемещения, резервирование, списание и инвентаризация остатков.',
                'modules'     => array('erp401_wms', 'erp227_core_registry', 'erp119_audit'),
                'mermaid'     => "graph LR
    Inflow[\"📥 Поступление\"] --> Receive[\"Приходный ордер\"]
    Receive --> Balance[(\"Баланс остатков\")]
    Balance --> Transfer[\"Перемещение между складами\"]
    Balance --> Reserve[\"Резерв под заказы\"]
    Reserve --> Issue[\"Расход / Списание в цех\"]
    Balance --> Audit[\"Инвентаризация & Сверка\"]",
                'steps' => array(
                    array('step' => 1, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Приход ТМЦ', 'desc' => 'Регистрация поступления товаров и материалов на выбранный склад хранения.'),
                    array('step' => 2, 'role' => 'Кладовщик', 'module' => 'erp401_wms', 'title' => 'Внутрискладское перемещение', 'desc' => 'Передача ТМЦ между складами (сырье, полуфабрикаты, готовая продукция, брак).'),
                    array('step' => 3, 'role' => 'Система / Менеджер', 'module' => 'erp401_wms', 'title' => 'Управление резервами', 'desc' => 'Автоматическое резервирование запасов с контролем доступного остатка.'),
                    array('step' => 4, 'role' => 'Инвентаризационная комиссия', 'module' => 'erp401_wms', 'title' => 'Корректировка и инвентаризация', 'desc' => 'Сверка учетного и фактического остатка с фиксацией в журнале аудита erp119.'),
                ),
            ),

            'maintenance' => array(
                'id'          => 'maintenance',
                'title'       => 'Техническое обслуживание и простои оборудования (ТОиР)',
                'icon'        => 'fas fa-screwdriver-wrench',
                'badge'       => 'Maintenance Flow',
                'description' => 'Регистрация поломок, простоев и регламентного технического обслуживания оборудования цеха.',
                'modules'     => array('erp408_shopfloor', 'erp801_analytics'),
                'mermaid'     => "graph TD
    WorkCenter[\"🏭 Рабочий центр\"] --> Incident{\"⚠️ Фиксация инцидента\"}
    Incident --> Downtime[\"⏱️ Учет времени простоя (erp408)\"]
    Downtime --> Reason[\"🏷️ Классификация причины простоя\"]
    Reason --> Maintenance[\"🛠️ Проведение ремонта\"]
    Maintenance --> Ready[\"✅ Возобновление работы\"]
    Downtime --> OEE[\"📊 Расчет OEE и Парето потерь (erp801)\"]",
                'steps' => array(
                    array('step' => 1, 'role' => 'Оператор / Наладчик', 'module' => 'erp408_shopfloor', 'title' => 'Фиксация остановки оборудования', 'desc' => 'Нажатие кнопки простоя на терминале рабочего места с выбором причины.'),
                    array('step' => 2, 'role' => 'Служба главного механика', 'module' => 'erp408_shopfloor', 'title' => 'Устранение неисправности', 'desc' => 'Проведение восстановительных или наладочных работ.'),
                    array('step' => 3, 'role' => 'Оператор', 'module' => 'erp408_shopfloor', 'title' => 'Закрытие простоя', 'desc' => 'Возобновление технологической операции и автоматический учет длительности.'),
                    array('step' => 4, 'role' => 'Аналитик / Главный инженер', 'module' => 'erp801_analytics', 'title' => 'Анализ OEE и Парето', 'desc' => 'Оценка общей эффективности оборудования и ключевых факторов потерь доступности.'),
                ),
            ),
        );
    }

    /**
     * Поиск по всей документации, модулям и MCP-инструментам.
     *
     * @param   string  $query
     * @return  array
     */
    public function search($query)
    {
        $query = trim(mb_strtolower($query, 'UTF-8'));
        if ($query === '')
        {
            return array();
        }

        $results = array();
        $modules = $this->modules();

        // 1. Поиск по модулям
        foreach ($modules as $mod)
        {
            $score = 0;
            $match_text = '';

            if (mb_strpos(mb_strtolower($mod['title'], 'UTF-8'), $query) !== FALSE)
            {
                $score += 10;
                $match_text = $mod['title'];
            }
            elseif (mb_strpos(mb_strtolower($mod['description'], 'UTF-8'), $query) !== FALSE)
            {
                $score += 5;
                $match_text = $mod['description'];
            }
            elseif (mb_strpos(mb_strtolower($mod['name'], 'UTF-8'), $query) !== FALSE)
            {
                $score += 8;
                $match_text = $mod['name'];
            }
            elseif (mb_strpos(mb_strtolower($mod['readme_raw'], 'UTF-8'), $query) !== FALSE)
            {
                $score += 2;
                $pos = mb_strpos(mb_strtolower($mod['readme_raw'], 'UTF-8'), $query);
                $start = max(0, $pos - 40);
                $match_text = '…' . mb_substr($mod['readme_raw'], $start, 120, 'UTF-8') . '…';
            }

            if ($score > 0)
            {
                $results[] = array(
                    'type'     => 'module',
                    'title'    => $mod['title'],
                    'subtitle' => 'Модуль ' . $mod['name'],
                    'url'      => '/docs/modules/' . $mod['name'],
                    'badge'    => $mod['category'],
                    'snippet'  => $match_text,
                    'score'    => $score,
                );
            }

            // Поиск по MCP-инструментам модуля
            foreach ($mod['mcp_tools'] as $tool)
            {
                $t_score = 0;
                if (mb_strpos(mb_strtolower($tool['name'], 'UTF-8'), $query) !== FALSE) $t_score += 10;
                if (mb_strpos(mb_strtolower($tool['title'], 'UTF-8'), $query) !== FALSE) $t_score += 8;
                if (mb_strpos(mb_strtolower($tool['description'], 'UTF-8'), $query) !== FALSE) $t_score += 5;

                if ($t_score > 0)
                {
                    $results[] = array(
                        'type'     => 'mcp_tool',
                        'title'    => $tool['title'],
                        'subtitle' => 'Инструмент MCP: ' . $tool['name'] . ' (' . $mod['name'] . ')',
                        'url'      => '/docs/mcp#' . $tool['name'],
                        'badge'    => 'MCP Tool',
                        'snippet'  => $tool['description'],
                        'score'    => $t_score,
                    );
                }
            }
        }

        // 2. Поиск по бизнес-процессам
        foreach ($this->business_processes() as $proc)
        {
            $p_score = 0;
            if (mb_strpos(mb_strtolower($proc['title'], 'UTF-8'), $query) !== FALSE) $p_score += 10;
            if (mb_strpos(mb_strtolower($proc['description'], 'UTF-8'), $query) !== FALSE) $p_score += 5;

            if ($p_score > 0)
            {
                $results[] = array(
                    'type'     => 'process',
                    'title'    => $proc['title'],
                    'subtitle' => 'Бизнес-процесс предприятия',
                    'url'      => '/docs/processes#' . $proc['id'],
                    'badge'    => $proc['badge'],
                    'snippet'  => $proc['description'],
                    'score'    => $p_score,
                );
            }
        }

        // Сортировка по релевантности
        usort($results, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $results;
    }
}
