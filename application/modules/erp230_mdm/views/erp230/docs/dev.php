<div>
    <div class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Руководство программиста MDM</h1>
        <p class="text-lg text-gray-600 dark:text-gray-400">Архитектура, стандарты и принципы работы модуля ERP230.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Навигация -->
        <div class="lg:col-span-1">
            <nav class="sticky top-6 space-y-1">
                <a href="#arch" class="block px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-md">1. Архитектурный паттерн</a>
                <a href="#db" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">2. Слой данных (PostgreSQL)</a>
                <a href="#logic" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">3. Бизнес-логика</a>
                <a href="#security" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">4. Безопасность и RLS</a>
                <a href="#api" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">5. Работа с API</a>
            </nav>
        </div>

        <!-- Контент -->
        <div class="lg:col-span-3 space-y-12">
            
            <!-- Секция 1 -->
            <section id="arch">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">1</span>
                    Архитектурный паттерн
                </h2>
                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                    <p>Модуль построен на принципе <strong>«Тонкие контроллеры — Толстые модели»</strong>. Контроллеры (<code>Controller_Erp230_Api</code>, <code>Index</code>) отвечают только за валидацию HTTP-ввода и формат ответа. Вся доменная логика, включая сложные SQL-запросы (CTE, рекурсия), инкапсулирована в моделях.</p>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 my-4">
                        <p class="text-sm text-yellow-700 dark:text-yellow-300"><strong>Важно:</strong> Прямые вызовы <code>DB::query</code> в контроллерах запрещены. Используйте методы моделей или сервисы.</p>
                    </div>
                </div>
            </section>

            <!-- Секция 2 -->
            <section id="db">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">2</span>
                    Слой данных (PostgreSQL)
                </h2>
                <div class="space-y-4">
                    <p class="text-gray-600 dark:text-gray-400">Мы используем возможности PostgreSQL 14+ для обеспечения производительности и гибкости:</p>
                    <ul class="list-disc pl-6 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li><strong>EAV + Time-Travel:</strong> Таблица <code>object_properties</code> хранит историю изменений через <code>tstzrange</code>.</li>
                        <li><strong>Партиционирование:</strong> Основные таблицы (objects, properties, baselines) секционированы по годам (поле <code>created_at</code>).</li>
                        <li><strong>Иерархии:</strong> Типы и оргструктура реализованы через расширение <code>ltree</code> для быстрого поиска по дереву.</li>
                        <li><strong>Целостность:</strong> Хеширование снимков (Baselines) реализовано на уровне триггеров БД (<code>tg_baseline_chain</code>).</li>
                    </ul>
                </div>
            </section>

            <!-- Секция 3 -->
            <section id="logic">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">3</span>
                    Бизнес-логика
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <h4 class="font-bold mb-2 text-blue-600">Trust Score</h4>
                        <p class="text-xs text-gray-500">Логика выбора эталонного значения (Golden Record) находится в <code>Model_Erp230_Property::upsert_safe()</code>. Значение от источника с низким уровнем доверия не перезапишет данные от надежного источника.</p>
                    </div>
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <h4 class="font-bold mb-2 text-blue-600">State Machine</h4>
                        <p class="text-xs text-gray-500">Переходы состояний объекта (draft -> active -> archived) управляются методом <code>Model_Erp230_Object::transition_to()</code>. Прямая смена поля <code>state</code> через ORM не рекомендуется.</p>
                    </div>
                </div>
            </section>

            <!-- Секция 4 -->
            <section id="security">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">4</span>
                    Безопасность и RLS
                </h2>
                <div class="bg-gray-900 rounded-lg p-6 text-gray-300 font-mono text-xs">
                    <p class="text-gray-500 mb-2">// Установка контекста безопасности в Controller_Erp230_Main::before()</p>
                    <p>DB::query(NULL, "SET adhoc.current_user_id = :id")->param(':id', $user_id)->execute();</p>
                    <p>DB::query(NULL, "SET adhoc.role = :role")->param(':role', $role)->execute();</p>
                    <p class="mt-4 text-gray-500">// После этого БД сама фильтрует строки (Row Level Security)</p>
                    <p>SELECT * FROM objects; // Вернет только те, к которым есть доступ по org_path</p>
                </div>
            </section>

            <!-- Секция 5 -->
            <section id="api">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">5</span>
                    Работа с API
                </h2>
                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-400 text-sm">
                    <p>Все API-методы возвращают JSON. Для обработки ошибок используйте метод <code>_handle()</code> в контроллере, который автоматически ловит исключения и формирует корректный HTTP-статус.</p>
                    <pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded">GET /erp230_api/object/&lt;uuid&gt;?with=properties,structure</pre>
                </div>
            </section>
            
            <!-- Секция 6 -->
            <section id="test">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">5</span>
                    !!! Тест. Будут созданы "мусорные объекты"
                </h2>
                <a href="/erp230_test" class="block px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-md">Тест (создание объектов)</a>
                <a href="/erp230_ApiTest" class="block px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-md">Тест API (создание объектов, другие функции)</a>
            </section>
            
            

        </div>
    </div>
</div>