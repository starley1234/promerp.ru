<div class="space-y-6">
    <?php echo Component::render('page_header', array(
        'crumbs' => array(array('Документация', '/docs'), array('Архитектура PromERP', NULL)),
        'title'  => 'Архитектура PromERP',
        'lead'   => 'Слои системы, изоляция модулей и баз данных, устройство модуля и границы ответственности.',
    )); ?>


    <!-- High-Level Architecture Diagram Card -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                1. Общая многоуровневая архитектура (Layered Architecture)
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Взаимодействие клиентов, AI-агентов, контроллеров HMVC, сервисов и изолированных баз данных PostgreSQL.
            </p>
        </div>

        <div class="p-6 rounded-lg bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700/80 overflow-x-auto min-h-[300px] flex items-center justify-center">
            <div class="mermaid w-full text-center">
graph TD
    subgraph Clients [Клиентский уровень]
        Browser["💻 Web Browser (Tailwind UI / Alpine.js)"]
        Mobile["📱 Mobile PWA / Tablets"]
        AIAgents["🤖 AI Agents (Model Context Protocol)"]
    end

    subgraph Entry [Точка входа & Безопасность]
        Nginx["🛡️ Nginx Web Server / SSL"]
        Bootstrap["⚡ PHP 8.4 Bootstrap & Router"]
        ACL["🔐 RBAC / ACL Authorization Guard"]
    end

    subgraph HMVC [Модульное ядро PromERP HMVC]
        subgraph CoreModules [Системные модули]
            Auth["Пользователи & Auth"]
            MDM["MDM Нормативно-справочная инфо"]
            Docs["Интерактивная документация"]
        end

        subgraph BusinessModules [Бизнес-домены]
            Sales["Заказы & Продажи"]
            Purchasing["Закупки & Снабжение"]
            Mfg["Производство & Техкарты"]
            Warehouse["Складской учет (WMS)"]
            Fin["Финансы & Казначейство"]
            HR["Кадры & Табель"]
        end
    end

    subgraph DataLayer [Уровень хранения данных PostgreSQL]
        DB_Core[("🗄️ PostgreSQL (promerp_core)")]
        DB_MDM[("🗄️ PostgreSQL (promerp_mdm)")]
    end

    Browser --> Nginx
    Mobile --> Nginx
    AIAgents --> Nginx
    Nginx --> Bootstrap
    Bootstrap --> ACL
    ACL --> CoreModules
    ACL --> BusinessModules
    CoreModules --> DB_Core
    CoreModules --> DB_MDM
    BusinessModules --> DB_Core
    BusinessModules --> DB_MDM
            </div>
        </div>
    </div>

    <!-- 2 Column: Database Segregation & Module Structure -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Database Segregation Diagram -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-database text-rose-500"></i>
                2. Разделение баз данных и пулов подключений
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                PromERP использует независимые конфигурационные группы подключения к БД (default, mdm). Это гарантирует строгую изоляцию нормативно-справочной информации от операционных транзакций.
            </p>

            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700/80 overflow-x-auto min-h-[180px] flex items-center justify-center">
                <div class="mermaid w-full text-center">
graph TD
    App["PromERP Backend"]
    
    subgraph ConfigGroups [Конфигурация Database]
        DefaultGroup["Group: default"]
        MdmGroup["Group: mdm"]
    end

    subgraph PGInstances [PostgreSQL Базы данных]
        DBCore[("promerp_core<br/>(Заказы, Склад, Кадры, Производство)")]
        DBMDM[("promerp_mdm<br/>(ЕИ, Контрагенты, Номенклатура, Справочники)")]
    end

    App --> DefaultGroup --> DBCore
    App --> MdmGroup --> DBMDM
                </div>
            </div>
        </div>

        <!-- HMVC Architecture Principles -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-cubes text-blue-500"></i>
                3. Принципы HMVC изоляции модулей
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                Каждый модуль PromERP расположен в отдельной директории <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-mono text-blue-600 dark:text-blue-400">application/modules/[module_name]</code> и содержит:
            </p>

            <div class="space-y-2.5 text-xs">
                <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <i class="fas fa-file-code text-blue-500 mt-0.5"></i>
                    <div>
                        <strong class="text-gray-900 dark:text-white">init.php</strong> — регистрация маршрутов модуля и первичная инициализация.
                    </div>
                </div>
                <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <i class="fas fa-folder text-blue-500 mt-0.5"></i>
                    <div>
                        <strong class="text-gray-900 dark:text-white">classes/</strong> — Контроллеры, Сервисы и Модели данных с каскадным автозагрузчиком Kohana.
                    </div>
                </div>
                <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <i class="fas fa-database text-rose-500 mt-0.5"></i>
                    <div>
                        <strong class="text-gray-900 dark:text-white">migrations_pg/</strong> — изолированные SQL-миграции модуля с отслеживанием версий.
                    </div>
                </div>
                <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <i class="fas fa-robot text-blue-500 mt-0.5"></i>
                    <div>
                        <strong class="text-gray-900 dark:text-white">mcp.php</strong> — декларация AI-инструментов Model Context Protocol.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Factory Offline-First Resiliency Card -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-network-wired text-emerald-500"></i>
            4. Стандарты безопасности и автономности (100% Offline-First)
        </h3>
        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
            Система спроектирована для работы в изолированных промышленных контурах предприятий (ЗПО/АСУ ТП без доступа в открытый Интернет):
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs">
                <div class="font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-1.5">
                    <i class="fas fa-server text-blue-500"></i> Полная автономность
                </div>
                <p class="text-gray-500 dark:text-gray-400 leading-relaxed">
                    Все фронтенд-библиотеки (Mermaid, Alpine, FontAwesome, Tailwind) хранятся локально в <code class="text-gray-700 dark:text-gray-300 font-mono">public_html/assets/</code> без внешних CDN-запросов.
                </p>
            </div>

            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs">
                <div class="font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-1.5">
                    <i class="fas fa-shield-halved text-emerald-500"></i> Строгий RBAC/ACL
                </div>
                <p class="text-gray-500 dark:text-gray-400 leading-relaxed">
                    Двухуровневая авторизация: проверка ролей на уровне контроллеров и гранулярная валидация прав на уровне доменных сервисов.
                </p>
            </div>

            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs">
                <div class="font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-1.5">
                    <i class="fas fa-microchip text-blue-500"></i> AI & MCP Ready
                </div>
                <p class="text-gray-500 dark:text-gray-400 leading-relaxed">
                    Поддержка протокола Model Context Protocol позволяет подключать локальные и облачные LLM для автоматизации рутинных операций.
                </p>
            </div>
        </div>
    </div>
</div>
