<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">API Documentation</h1>
        <p class="text-lg text-gray-600 dark:text-gray-400">Спецификация методов взаимодействия с MDM системой.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <nav class="sticky top-6 space-y-1">
                <a href="#overview" class="block px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-md">Обзор</a>
                <a href="#objects" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Objects (Мастер-записи)</a>
                <a href="#properties" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Properties (Атрибуты)</a>
                <a href="#xref" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Xref (Кросс-референсы)</a>
                <a href="#structure" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Structure (BOM)</a>
                <a href="#twin" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Twin & Telemetry</a>
                <a href="#audit" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Baselines & Audit</a>
                <a href="#search" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">Search & Dictionaries</a>
            </nav>
        </div>

        <!-- Content -->
        <div class="lg:col-span-3 space-y-12">
            
            <!-- Overview -->
            <section id="overview">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">1</span>
                    Обзор
                </h2>
                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                    <p>Базовый URL для всех запросов:</p>
                    <code class="block p-3 bg-gray-100 dark:bg-gray-800 rounded text-blue-600 dark:text-blue-400 font-mono">/mdm/api/</code>
                    <p class="mt-4 text-sm">Все ответы возвращаются в формате JSON. Для аутентификации используется текущая сессия пользователя с учетом RLS прав доступа.</p>
                </div>
            </section>

            <!-- Objects -->
            <section id="objects">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">2</span>
                    Objects (Мастер-записи)
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/objects</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p class="mb-2">Список объектов с фильтрами и пагинацией.</p>
                            <p class="text-xs font-mono">Параметры: type, org, state, q</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/object/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Детальная информация об объекте. Поддерживает параметр <code>with=properties,structure</code>.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/object</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Создание новой мастер-записи.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded">PATCH</span>
                            <code class="text-sm font-bold">/object/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Обновление полей (master_code, org_id, type_id).</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded">DELETE</span>
                            <code class="text-sm font-bold">/object/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Мягкое удаление (архивация) объекта.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/object/&lt;id&gt;/state</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Смена жизненного цикла через <code>transition_to</code>.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/object/&lt;id&gt;/merge</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Слияние дублей (перенос Xref, свойств и связей в выжившую запись).</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Properties -->
            <section id="properties">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">3</span>
                    Properties (Атрибуты и Time-travel)
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/properties/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Актуальный набор свойств (Golden Record).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/properties/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Пакетная запись свойств с использованием Trust Score.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/attribute_history/&lt;id&gt;?key=&lt;key&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>История изменений конкретного атрибута.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Xref -->
            <section id="xref">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">4</span>
                    Xref (Кросс-референсы)
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/resolve?source=&lt;src&gt;&amp;remote=&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Поиск мастер-записи по внешнему ID из конкретной системы-источника.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/xref/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Список всех внешних привязок (кросс-ссылок) объекта.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Structure -->
            <section id="structure">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">5</span>
                    Structure (BOM / Связи)
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/bom/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Прямые дочерние связи объекта.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/bom_tree/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Полное дерево структуры (рекурсивный обход, CTE).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/where_used/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Обратный обход (где используется компонент).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/link</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Создание связи между объектами.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded">DELETE</span>
                            <code class="text-sm font-bold">/link/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Закрытие связи (установка верхней границы периода валидности).</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Twin -->
            <section id="twin">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">6</span>
                    Twin & Telemetry
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/telemetry/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Прием данных с датчиков (записываются как свойства).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/twin/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Паспорт двойника (данные + состояние + структура + аудит).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/twin/&lt;id&gt;/state?at=&lt;ts&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Срез состояния объекта на любой момент времени.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Baselines & Audit -->
            <section id="audit">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">7</span>
                    Baselines & Audit (Снимки и Целостность)
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">POST</span>
                            <code class="text-sm font-bold">/snapshot</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Создание неизменяемого снимка состояния (hash-chain).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/baselines/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Список всех снимков объекта.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/verify_integrity/&lt;id&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Проверка целостности цепочки хешей снимков.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Search & Dictionaries -->
            <section id="search">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">8</span>
                    Search & Dictionaries
                </h2>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/search?q=&amp;type=text|semantic</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Поиск (текстовый или векторный семантический).</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">GET</span>
                            <code class="text-sm font-bold">/dictionary/&lt;name&gt;</code>
                        </div>
                        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
                            <p>Получение справочников (<code>types</code>, <code>org_units</code>, <code>sources</code>, <code>uom</code>).</p>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </div>
</div>

<style>
    section { scroll-margin-top: 2rem; }
    code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>