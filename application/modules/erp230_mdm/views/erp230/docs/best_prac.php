<div>
    <div class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Рекомендации: MDM</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Работа с данными -->
        <div class="space-y-6">
            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center">
                    <i class="fas fa-database text-blue-500 mr-2"></i> Работа с Golden Record
                </h3>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Всегда используйте <code>Model_Erp230_Property::upsert_safe()</code> для записи атрибутов. Это гарантирует проверку Trust Score и корректное закрытие периодов <code>tstzrange</code>.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Для получения отображаемого имени объекта вызывайте <code>$object->display_name()</code>. Метод оптимизирован и использует денормализованные данные из <code>object_state</code>.</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center">
                    <i class="fas fa-project-diagram text-purple-500 mr-2"></i> Структуры и BOM
                </h3>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Для построения деревьев любой вложенности используйте <code>bom_tree()</code>. Метод реализует рекурсивный CTE на стороне БД, что в десятки раз быстрее обхода в PHP.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-amber-500 mt-1 mr-2"></i>
                            <span>При создании связей (Link) всегда проверяйте отсутствие циклов. Модель делает это автоматически в <code>check_cycles()</code>.</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Безопасность и Производительность -->
        <div class="space-y-6">
            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center">
                    <i class="fas fa-shield-alt text-red-500 mr-2"></i> Безопасность (RLS)
                </h3>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Система использует Row Level Security. Это означает, что выборка <code>SELECT * FROM objects</code> автоматически вернет только те записи, которые разрешены текущему пользователю по его <code>org_path</code>.
                    </p>
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded text-xs font-mono text-blue-700 dark:text-blue-300">
                        // Никогда не отключайте RLS в коде.<br>
                        // Контекст устанавливается в Main::before()
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i> Производительность
                </h3>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span><strong>Партиционирование:</strong> При выполнении сырых SQL-запросов всегда включайте <code>created_at</code> в условия WHERE, чтобы планировщик БД мог исключить ненужные секции (Partition Pruning).</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span><strong>JSONB:</strong> Используйте оператор <code>@></code> для поиска по атрибутам в <code>object_state</code>, так как на это поле наложен GIN-индекс.</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <!-- Интеграция и API -->
    <div class="mt-12">
        <div class="bg-slate-900 rounded-2xl p-8 text-white">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h3 class="text-2xl font-bold">Интеграция через REST API</h3>
                <span class="text-xs font-mono bg-slate-800 px-3 py-1 rounded border border-slate-700 text-slate-400">Base URL: /mdm/api/</span>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="space-y-4">
                    <h4 class="text-blue-400 font-semibold flex items-center">
                        <i class="fas fa-exchange-alt mr-2"></i> Консолидация (Xref)
                    </h4>
                    <p class="text-sm text-slate-400">
                        Используйте <code>Service_Erp230_Consolidation::consolidate()</code> для синхронизации с внешними системами (PLM/ERP).
                    </p>
                    <ul class="text-xs space-y-2 text-slate-300">
                        <li class="flex items-center"><i class="fas fa-link mr-2 text-slate-500"></i> GET /resolve — поиск по внешнему ID</li>
                        <li class="flex items-center"><i class="fas fa-fingerprint mr-2 text-slate-500"></i> POST /object/merge — слияние дублей</li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <h4 class="text-blue-400 font-semibold flex items-center">
                        <i class="fas fa-history mr-2"></i> Цифровой двойник
                    </h4>
                    <p class="text-sm text-slate-400">
                        Доступ к истории изменений и состоянию на момент времени (Time-travel).
                    </p>
                    <ul class="text-xs space-y-2 text-slate-300">
                        <li class="flex items-center"><i class="fas fa-clock mr-2 text-slate-500"></i> GET /twin/{id}/state?at={ts}</li>
                        <li class="flex items-center"><i class="fas fa-sensor mr-2 text-slate-500"></i> POST /telemetry/{id} — данные датчиков</li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <h4 class="text-blue-400 font-semibold flex items-center">
                        <i class="fas fa-file-signature mr-2"></i> Baselines & Audit
                    </h4>
                    <p class="text-sm text-slate-400">
                        Фиксация неизменяемых снимков состояния с проверкой целостности через Hash-chain.
                    </p>
                    <ul class="text-xs space-y-2 text-slate-300">
                        <li class="flex items-center"><i class="fas fa-camera mr-2 text-slate-500"></i> POST /snapshot — создать снимок</li>
                        <li class="flex items-center"><i class="fas fa-shield-check mr-2 text-slate-500"></i> GET /verify_integrity/{id}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-800">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
                        <div class="text-blue-400 font-bold mb-1">Step 1: Resolve</div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500">Сопоставление Xref</div>
                    </div>
                    <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
                        <div class="text-blue-400 font-bold mb-1">Step 2: Trust</div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500">Валидация Trust Score</div>
                    </div>
                    <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
                        <div class="text-blue-400 font-bold mb-1">Step 3: Golden Record</div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500">Обновление мастер-данных</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>