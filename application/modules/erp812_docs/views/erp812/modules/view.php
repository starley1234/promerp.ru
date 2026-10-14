<div class="space-y-6">
    <?php echo Component::render('page_header', array(
        'crumbs'       => array(array('Документация', '/docs'), array('Каталог модулей', '/docs/modules'), array($module['title'], NULL)),
        'eyebrow'      => $module['name'],
        'title'        => $module['title'],
        'actions_html' => '<a href="/docs/modules" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><i class="fas fa-arrow-left" aria-hidden="true"></i> Все модули</a>',
    )); ?>

    <!-- Quick Meta Chips -->
    <div class="flex flex-wrap gap-3">
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs">
            <span class="text-gray-400">Категория:</span>
            <span class="font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($module['category']) ?></span>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs">
            <span class="text-gray-400">База данных:</span>
            <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400"><?= htmlspecialchars($module['db_group']) ?> (PostgreSQL)</span>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs">
            <span class="text-gray-400">Маршрутов:</span>
            <span class="font-semibold text-gray-800 dark:text-gray-200"><?= count($module['routes']) ?></span>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs">
            <span class="text-gray-400">Таблиц:</span>
            <span class="font-semibold text-gray-800 dark:text-gray-200"><?= count($module['tables']) ?></span>
        </div>
        <?php if ( ! empty($module['mcp_tools'])): ?>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-xs text-blue-700 dark:text-blue-300">
            <i class="fas fa-robot text-blue-500"></i>
            <span class="font-semibold"><?= count($module['mcp_tools']) ?> MCP Tools</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Architecture Diagram -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-sitemap text-blue-500"></i>
            Архитектурная схема модуля (Mermaid)
        </h3>
        <div class="p-6 rounded-lg bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700/80 overflow-x-auto min-h-[180px] flex items-center justify-center">
            <div class="mermaid w-full text-center">
<?= $mermaid_diagram ?>
            </div>
        </div>
    </div>

    <!-- Documentation & README Content -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-book-open text-blue-500"></i>
            Описание и спецификация (README)
        </h3>
        <div class="prose dark:prose-invert max-w-none text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            <?= $rendered_readme ?>
        </div>
    </div>

    <!-- 2 Column Technical Specification: Routes & Database -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Routes & Endpoints -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-route text-amber-500"></i>
                    Маршруты и HTTP эндпоинты (<?= count($module['routes']) ?>)
                </h3>
            </div>

            <?php if ( ! empty($module['routes'])): ?>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <?php foreach ($module['routes'] as $r): ?>
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs">
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">
                        <?= htmlspecialchars($r['name']) ?>
                    </div>
                    <div class="font-mono text-[11px] text-blue-600 dark:text-blue-400 break-all mb-1">
                        <?= htmlspecialchars($r['uri']) ?>
                    </div>
                    <?php if ( ! empty($r['controller'])): ?>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                        Контроллер: <span class="font-mono text-gray-700 dark:text-gray-300"><?= htmlspecialchars($r['controller']) ?></span>
                        <?php if ( ! empty($r['action'])): ?>
                            &rarr; <span class="font-mono text-gray-700 dark:text-gray-300"><?= htmlspecialchars($r['action']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-900/30 rounded-lg">
                Маршруты не зарегистрированы в init.php
            </div>
            <?php endif; ?>
        </div>

        <!-- Database Schema Tables -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-database text-rose-500"></i>
                    Схема базы данных (<?= count($module['tables']) ?> таблиц)
                </h3>
            </div>

            <?php if ( ! empty($module['tables'])): ?>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <?php foreach ($module['tables'] as $tbl): ?>
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-table text-gray-400"></i>
                        <span class="font-mono font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($tbl) ?></span>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-mono text-[10px]">
                        PostgreSQL
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-900/30 rounded-lg">
                Модуль не объявляет собственных таблиц или использует справочники MDM.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MCP Tools Section (if any) -->
    <?php if ( ! empty($module['mcp_tools'])): ?>
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-800 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-robot text-blue-500"></i>
            Инструменты Model Context Protocol (AI Tools)
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($module['mcp_tools'] as $tool): ?>
            <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 text-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-mono font-bold text-blue-700 dark:text-blue-300 text-sm">
                        <?= htmlspecialchars($tool['name']) ?>
                    </span>
                    <?php if ( ! empty($tool['mutating'])): ?>
                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[10px] font-bold">
                            MUTATING
                        </span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-[10px] font-bold">
                            READONLY
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    <?= htmlspecialchars($tool['description'] ?? 'Без описания') ?>
                </p>
                <?php if ( ! empty($tool['parameters'])): ?>
                <div class="pt-2 border-t border-blue-100 dark:border-blue-800">
                    <span class="font-semibold text-gray-500 dark:text-gray-400">Параметры:</span>
                    <pre class="mt-1 p-2 rounded bg-gray-900 text-emerald-400 font-mono text-[11px] overflow-x-auto"><?= json_encode($tool['parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Controllers & ACL Capabilities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Controllers -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-3">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-code text-blue-500"></i>
                Контроллеры модуля (<?= count($module['controllers']) ?>)
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($module['controllers'] as $c): 
                    $c_name = is_array($c) ? $c['name'] : $c;
                ?>
                    <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-mono text-xs font-medium">
                        <?= htmlspecialchars($c_name) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ACL Rights -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-3">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-shield-halved text-emerald-500"></i>
                Права доступа ACL (<?= count($module['acl']) ?>)
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php if ( ! empty($module['acl'])): ?>
                    <?php foreach ($module['acl'] as $perm => $roles): ?>
                        <div class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900 text-xs">
                            <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300"><?= htmlspecialchars($perm) ?></span>
                            <?php if (is_array($roles)): ?>
                                <span class="text-[10px] text-gray-400 ml-1">(<?= implode(', ', $roles) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-xs text-gray-400">Специфические права ACL не заданы</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
