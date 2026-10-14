<div class="space-y-6">
    <?php echo Component::render('page_header', array(
        'crumbs' => array(array('Документация', '/docs'), array('Каталог модулей', NULL)),
        'title'  => 'Каталог модулей',
        'lead'   => 'Доменные и служебные модули системы: назначение, таблицы, маршруты, права и инструменты AI.',
        'meta'   => 'Модулей: '.count($modules),
    )); ?>

    <!-- Category Tabs + filter -->
    <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <a href="/docs/modules" 
           class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= empty($current_category) ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700' ?>">
            Все категории
        </a>
        <?php foreach ($categories as $cat_k => $cat_v): 
            $is_cat_active = ($current_category === $cat_k);
        ?>
        <a href="/docs/modules?category=<?= $cat_k ?>" 
           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $is_cat_active ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700' ?>">
            <i class="<?= $cat_v['icon'] ?> text-[10px]"></i>
            <?= htmlspecialchars($cat_v['title']) ?>
        </a>
        <?php endforeach; ?>
    </div>

        <!-- Filter / Search in Catalog -->
        <div class="relative min-w-[260px]">
            <i class="fas fa-search absolute left-3.5 top-3 text-gray-400 text-sm" aria-hidden="true"></i>
            <input type="text"
                   id="module-filter-input"
                   aria-label="Фильтр по названию или коду"
                   placeholder="Фильтр по названию или коду..."
                   class="w-full pl-10 pr-4 py-2 text-sm rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <!-- Modules Grid -->
    <div id="modules-cards-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($modules as $m): ?>
        <div class="module-card flex flex-col justify-between p-5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500 transition-all"
             data-title="<?= strtolower(htmlspecialchars($m['title'])) ?>"
             data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>"
             data-desc="<?= strtolower(htmlspecialchars($m['description'])) ?>">
            <div>
                <!-- Card Header -->
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg shrink-0">
                            <i class="<?= $m['icon'] ?>"></i>
                        </span>
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-white text-base leading-tight">
                                <a href="/docs/modules/<?= $m['name'] ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    <?= htmlspecialchars($m['title']) ?>
                                </a>
                            </h3>
                            <span class="text-xs font-mono text-gray-400"><?= htmlspecialchars($m['name']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-3 mb-4 leading-relaxed">
                    <?= htmlspecialchars($m['description']) ?>
                </p>

                <!-- Technical Badges -->
                <div class="grid grid-cols-3 gap-2 py-3 px-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 mb-4 text-center">
                    <div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white"><?= count($m['routes']) ?></div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-300">Маршрутов</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white"><?= count($m['tables']) ?></div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-300">Таблиц БД</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold <?= ! empty($m['mcp_tools']) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white' ?>">
                            <?= count($m['mcp_tools']) ?>
                        </div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-300">MCP Tools</div>
                    </div>
                </div>
            </div>

            <!-- Card Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700/60">
                <span class="text-[11px] text-gray-600 dark:text-gray-300">
                    <i class="fas fa-folder text-gray-400 mr-1"></i>
                    <?= htmlspecialchars($categories[$m['category']]['title'] ?? $m['category']) ?>
                </span>
                <a href="/docs/modules/<?= $m['name'] ?>" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                    <span>Подробнее</span>
                    <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('module-filter-input');
    var cards = document.querySelectorAll('.module-card');

    if (!input) return;

    input.addEventListener('input', function() {
        var query = input.value.toLowerCase().trim();
        cards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var name = card.getAttribute('data-name') || '';
            var desc = card.getAttribute('data-desc') || '';
            if (!query || title.includes(query) || name.includes(query) || desc.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>
