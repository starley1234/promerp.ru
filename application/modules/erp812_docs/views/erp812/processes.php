<div class="space-y-6">
    <?php echo Component::render('page_header', array(
        'crumbs' => array(array('Документация', '/docs'), array('Сквозные бизнес-процессы', NULL)),
        'title'  => 'Бизнес-процессы предприятия',
        'lead'   => 'Как заказ, закупка и материал проходят через модули системы — от потребности до результата.',
        'meta'   => 'Всего процессов: '.count($processes),
    )); ?>


    <!-- Main 2-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Sidebar Navigation of Processes -->
        <div class="lg:col-span-1 space-y-2 sticky top-4">
            <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg font-semibold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Каталог процессов
            </div>
            <nav class="space-y-1">
                <?php foreach ($processes as $p_id => $p):
                    $is_active = ($p_id === $current_id);
                ?>
                <a href="/docs/processes/<?= $p_id ?>"
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-sm font-medium transition-all <?= $is_active
                        ? 'bg-blue-600 text-white shadow-md'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60 border border-gray-200 dark:border-gray-700/60' ?>">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm <?= $is_active ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' ?>">
                        <i class="<?= $p['icon'] ?>"></i>
                    </span>
                    <div class="truncate flex-1">
                        <div class="font-semibold truncate"><?= htmlspecialchars($p['title']) ?></div>
                        <div class="text-[11px] <?= $is_active ? 'text-blue-100' : 'text-gray-400' ?>"><?= count($p['steps']) ?> этапов</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Process Detail Content -->
        <div class="lg:col-span-3 space-y-6">
            <?php if ($current_process): ?>
                <!-- Process Header Card -->
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <span class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl">
                                <i class="<?= $current_process['icon'] ?>"></i>
                            </span>
                            <div>
                                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($current_process['title']) ?>
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Идентификатор: <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-mono"><?= $current_process['id'] ?></code>
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed mb-6">
                        <?= htmlspecialchars($current_process['description']) ?>
                    </p>

                    <!-- Involved Modules & Roles Badges -->
                    <div class="flex flex-wrap items-center gap-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Модули:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($current_process['modules'] as $mod_name): ?>
                                    <a href="/docs/modules/<?= $mod_name ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-semibold hover:bg-blue-100 transition-colors">
                                        <i class="fas fa-cube text-[10px]"></i>
                                        <?= htmlspecialchars($mod_name) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interactive Mermaid Diagram Section -->
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-project-diagram text-blue-500"></i>
                            Интерактивная схема процесса (Mermaid Flowchart)
                        </h3>
                        <button data-action="copy-target" data-target="#mermaid-raw-code"
                                class="px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition-colors">
                            <i class="fas fa-copy mr-1"></i> Скопировать код
                        </button>
                    </div>

                    <div class="p-6 rounded-lg bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700/80 overflow-x-auto min-h-[220px] flex items-center justify-center">
                        <div class="mermaid w-full text-center">
<?= $current_process['mermaid'] ?>
                        </div>
                    </div>

                    <pre id="mermaid-raw-code" class="hidden"><?= htmlspecialchars($current_process['mermaid']) ?></pre>
                </div>

                <!-- Step-by-Step Breakdown -->
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-6">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-list-ol text-emerald-500"></i>
                        Пошаговый регламент выполнения процесса
                    </h3>

                    <div class="space-y-4 relative before:absolute before:inset-0 before:left-4 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-700">
                        <?php foreach ($current_process['steps'] as $idx => $step): ?>
                        <div class="relative flex items-start gap-4 pl-2">
                            <!-- Step Number Badge -->
                            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0 shadow-md ring-4 ring-white dark:ring-gray-800 z-10">
                                <?= $step['step'] ?>
                            </div>

                            <!-- Step Content Card -->
                            <div class="flex-1 p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700/60">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white text-sm">
                                        <?= htmlspecialchars($step['title']) ?>
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <?php if ( ! empty($step['role'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-[11px] font-medium">
                                                <i class="fas fa-user-tag text-[9px]"></i> <?= htmlspecialchars($step['role']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( ! empty($step['module'])): ?>
                                            <a href="/docs/modules/<?= $step['module'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-semibold hover:underline">
                                                <i class="fas fa-cube text-[9px]"></i> <?= htmlspecialchars($step['module']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                    <?= htmlspecialchars($step['desc']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="p-8 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    Выберите бизнес-процесс из списка слева.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
