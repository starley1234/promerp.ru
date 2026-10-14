<?php
/**
 * Главная документации: поиск, показатели, разделы, пример процесса и категории модулей.
 *
 * Оформление — как у остальных страниц сервиса: шапка `page_header`, показатели
 * `kpi`, панели `card`, плитки одного вида. Градиентный баннер и разноцветные
 * иконки разделов убраны: акцент один — синий.
 */
$tile = 'block rounded-lg border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 p-4';

$sections = array(
    array('/docs/processes', 'fas fa-diagram-project', 'Сквозные бизнес-процессы',
        'Диаграммы по шагам: закупки (P2P), продажи (O2C), производство и складская логистика.', 'Изучить процессы'),
    array('/docs/mcp', 'fas fa-robot', 'Инструменты MCP для AI',
        'Спецификация Model Context Protocol: инструменты чтения, расчёта и подготовки черновиков.', 'Каталог MCP'),
    array('/docs/architecture', 'fas fa-sitemap', 'Архитектура',
        'Слои системы, изоляция модулей и баз данных, устройство модуля.', 'Открыть схему'),
);

/*
 * Каталог модулей — внутренний раздел (таблицы, маршруты, права доступа),
 * он закрыт от гостей. Тому, кто его не откроет, показываем не ссылку, а
 * понятное объяснение: иначе половина плиток ведёт на «доступ запрещён».
 */
if ( ! empty($modules_open))
{
    $sections[] = array('/docs/modules', 'fas fa-cubes', 'Каталог модулей системы',
        'Реестр модулей с таблицами, контроллерами, маршрутами, правами доступа и связями.', 'Смотреть модули');
}
?>
<div class="space-y-6">

    <?php echo Component::render('page_header', array(
        'title' => 'Документация',
        'lead'  => 'Сквозные бизнес-процессы, схемы данных, архитектурные слои, каталог модулей и инструменты MCP для AI-агентов.',
    )); ?>

    <div class="relative max-w-xl">
        <div class="relative flex items-center">
            <i class="fas fa-search absolute left-4 text-gray-400" aria-hidden="true"></i>
            <input type="text"
                   id="doc-search-input"
                   aria-label="Поиск по документации"
                   placeholder="Поиск по процессам, модулям, таблицам и инструментам MCP"
                   class="w-full pl-10 pr-10 py-2.5 text-sm rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   autocomplete="off">
            <span id="doc-search-spinner" class="hidden absolute right-4 text-gray-400">
                <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
            </span>
        </div>
        <?php // Результаты поиска: список под полем ввода ?>
        <div id="doc-search-results" class="hidden absolute left-0 right-0 top-full mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto z-50 divide-y divide-gray-100 dark:divide-gray-700/50"></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <?php echo Component::render('kpi', array('label' => 'Модули', 'value' => $stats['total_modules'], 'unit' => 'шт.', 'hint' => 'Изолированных доменов', 'url' => empty($modules_open) ? NULL : '/docs/modules')); ?>
        <?php echo Component::render('kpi', array('label' => 'Процессы', 'value' => $stats['total_processes'], 'unit' => 'шт.', 'hint' => 'Сквозных схем',         'url' => '/docs/processes')); ?>
        <?php echo Component::render('kpi', array('label' => 'Маршруты', 'value' => $stats['total_routes'],    'unit' => 'шт.', 'hint' => 'HTTP-адресов')); ?>
        <?php echo Component::render('kpi', array('label' => 'MCP',      'value' => $stats['total_mcp'],       'unit' => 'шт.', 'hint' => 'Инструментов для AI',   'url' => '/docs/mcp')); ?>
        <?php echo Component::render('kpi', array('label' => 'Таблицы',  'value' => $stats['total_tables'],    'unit' => 'шт.', 'hint' => 'В схемах PostgreSQL')); ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php foreach ($sections as $section): ?>
            <a href="<?php echo HTML::chars($section[0]); ?>" class="<?php echo $tile; ?> hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                    <i class="<?php echo HTML::chars($section[1]); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                    <?php echo HTML::chars($section[2]); ?>
                </span>
                <span class="block text-sm text-gray-600 dark:text-gray-300 mt-2"><?php echo HTML::chars($section[3]); ?></span>
                <span class="block text-sm text-blue-600 dark:text-blue-400 mt-3">
                    <?php echo HTML::chars($section[4]); ?> <i class="fas fa-arrow-right ml-1 text-xs" aria-hidden="true"></i>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    Component::begin('card', array(
        'title'        => 'Пример: закупки и снабжение (P2P)',
        'icon'         => 'fas fa-diagram-project',
        'subtitle'     => 'От потребности цеха до прихода на склад и оплаты.',
        'id'           => 'docs-p2p',
        'actions_html' => '<a href="/docs/processes/p2p" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Подробный разбор <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i></a>',
    ));
    ?>
        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <div class="mermaid text-center">
graph LR
    Need["1. Потребность цеха"] --> Requisition["2. Заявка на закупку"]
    Requisition --> RFQ["3. Выбор поставщика"]
    RFQ --> PO["4. Заказ поставщику"]
    PO --> Shipment["5. Отгрузка и доставка"]
    Shipment --> WMS["6. Приёмка на склад"]
    WMS --> QC{"7. Контроль качества"}
    QC -- Принято --> Stock["Оприходование на склад"]
    QC -- Брак --> Return["Акт рекламации"]
    Stock --> Payment["8. Оплата счёта"]
            </div>
        </div>
    <?php Component::end(); ?>

    <section aria-labelledby="docs-categories">
        <div class="flex items-center justify-between mb-3">
            <h2 id="docs-categories" class="font-semibold text-gray-900 dark:text-white">Категории модулей</h2>
            <?php if ( ! empty($modules_open)): ?>
                <a href="/docs/modules" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    Все модули (<?php echo count($modules); ?>) <i class="fas fa-arrow-right ml-1 text-xs" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($categories as $cat_key => $cat): ?>
                <?php $cat_modules = array_filter($modules, function ($m) use ($cat_key) { return $m['category'] === $cat_key; }); ?>
                <div class="<?php echo $tile; ?> flex flex-col justify-between">
                    <div>
                        <p class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                            <i class="<?php echo HTML::chars($cat['icon']); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                            <?php echo HTML::chars($cat['title']); ?>
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">· <?php echo count($cat_modules); ?></span>
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-2"><?php echo HTML::chars($cat['description']); ?></p>
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <?php foreach (array_slice($cat_modules, 0, 4) as $m): ?>
                                <?php if (empty($modules_open)): ?>
                                    <span class="inline-block px-2.5 py-1 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"><?php echo HTML::chars($m['title']); ?></span>
                                <?php else: ?>
                                    <a href="/docs/modules/<?php echo HTML::chars($m['name']); ?>" class="inline-block px-2.5 py-1 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900/60 dark:hover:text-blue-300">
                                        <?php echo HTML::chars($m['title']); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (count($cat_modules) > 4): ?>
                                <span class="inline-block px-2 py-1 text-xs rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">+<?php echo count($cat_modules) - 4; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ( ! empty($modules_open)): ?>
                        <a href="/docs/modules?category=<?php echo HTML::chars($cat_key); ?>" class="mt-3 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            Открыть категорию <i class="fas fa-chevron-right text-[10px]" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('doc-search-input');
    var searchResults = document.getElementById('doc-search-results');
    var spinner = document.getElementById('doc-search-spinner');
    var debounceTimer = null;

    if (!searchInput || !searchResults) return;

    searchInput.addEventListener('input', function() {
        var query = searchInput.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            if (spinner) spinner.classList.add('hidden');
            return;
        }

        if (spinner) spinner.classList.remove('hidden');

        debounceTimer = setTimeout(function() {
            fetch('/erp812_api/search?q=' + encodeURIComponent(query))
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (spinner) spinner.classList.add('hidden');
                    if (!data.results || data.results.length === 0) {
                        searchResults.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">Ничего не найдено по запросу "' + query + '"</div>';
                        searchResults.classList.remove('hidden');
                        return;
                    }

                    var html = '';
                    data.results.forEach(function(item) {
                        var iconClass = 'fa-cubes';
                        var badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300';
                        var typeLabel = 'Модуль';

                        if (item.type === 'process') {
                            iconClass = 'fa-diagram-project';
                            badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300';
                            typeLabel = 'Процесс';
                        } else if (item.type === 'mcp_tool') {
                            iconClass = 'fa-robot';
                            badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300';
                            typeLabel = 'MCP Tool';
                        } else if (item.type === 'table') {
                            iconClass = 'fa-database';
                            badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300';
                            typeLabel = 'Таблица';
                        }

                        html += '<a href="' + item.url + '" class="block p-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">' +
                            '<div class="flex items-center justify-between mb-1">' +
                                '<div class="flex items-center gap-2">' +
                                    '<i class="fas ' + iconClass + ' text-gray-400 group-hover:text-blue-500"></i>' +
                                    '<span class="font-semibold text-sm text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">' + item.title + '</span>' +
                                '</div>' +
                                '<span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ' + badgeClass + '">' + typeLabel + '</span>' +
                            '</div>' +
                            '<p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1">' + item.snippet + '</p>' +
                        '</a>';
                    });

                    searchResults.innerHTML = html;
                    searchResults.classList.remove('hidden');
                })
                .catch(function() {
                    if (spinner) spinner.classList.add('hidden');
                });
        }, 200);
    });

    // Закрытие выпадающего списка при клике вне него
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
});
</script>
