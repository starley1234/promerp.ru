<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html>
<html lang="ru" class="scroll-smooth" data-ui="<?php echo HTML::chars(Ui_Mode::current()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    /*
     * Адрес страницы для поисковых роботов собираем из паспорта инстанса
     * (`site_url`), а не из текущего Host: у одной установки бывает несколько
     * доменов, и канонический должен быть один — иначе выдача дробится.
     */
    $site_config = Kohana::$config->load('site_config');
    $site_url    = rtrim((string) $site_config->get('site_url'), '/');
    $page_path   = '/'.ltrim((string) Request::current()->uri(), '/');
    $canonical   = $site_url.($page_path === '/' ? '/' : $page_path);
    $page_title  = isset($title) && $title ? $title : 'PromERP — Управление предприятием';
    $page_desc   = isset($meta['description']) ? $meta['description'] : 'PromERP — модульная ERP-система для производственного предприятия';
    ?>

    <title><?php echo HTML::chars($page_title); ?></title>
    <link rel="canonical" href="<?php echo HTML::chars($canonical); ?>">
    <meta name="description" content="<?php echo HTML::chars($page_desc); ?>">
    <meta name="keywords" content="<?php echo HTML::chars(isset($meta['keywords']) ? $meta['keywords'] : 'ERP, производство, склад, качество, управление предприятием'); ?>">
    <meta name="author" content="<?php echo HTML::chars(isset($meta['author']) ? $meta['author'] : 'PromERP'); ?>">
    <?php if ( ! empty($meta['robots'])): ?>
    <meta name="robots" content="<?php echo HTML::chars($meta['robots']); ?>">
    <?php endif; ?>

    <meta property="og:site_name" content="<?php echo HTML::chars((string) $site_config->get('site_name')); ?>">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo HTML::chars($canonical); ?>">
    <meta property="og:title" content="<?php echo HTML::chars($page_title); ?>">
    <meta property="og:description" content="<?php echo HTML::chars($page_desc); ?>">
    <?php if (isset($meta['og_image']) && $meta['og_image']): ?><meta property="og:image" content="<?php echo HTML::chars($meta['og_image']); ?>"><?php endif; ?>

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo HTML::chars($page_title); ?>">
    <meta name="twitter:description" content="<?php echo HTML::chars($page_desc); ?>">
    <?php if (isset($meta['og_image']) && $meta['og_image']): ?><meta name="twitter:image" content="<?php echo HTML::chars($meta['og_image']); ?>"><?php endif; ?>

    <?php
    /*
     * Структурированные данные (schema.org). Страница передаёт их готовым
     * массивом через `set_json_ld()`: поисковику важно понимать, что это
     * программный продукт и где у него ответы на вопросы, а не просто текст.
     */
    if (isset($json_ld) && is_array($json_ld) && $json_ld): ?>
    <script type="application/ld+json"><?php echo json_encode($json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif; ?>

    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">

    <!-- Локальные ассеты без внешних CDN -->
    <script src="<?php echo HTML::chars(Assets::url('theme_init')); ?>"></script>
    <link rel="stylesheet" href="<?php echo HTML::chars(Assets::url('css')); ?>">
    <link rel="stylesheet" href="<?php echo HTML::chars(Assets::url('simple')); ?>">
    <link rel="stylesheet" href="<?php echo HTML::chars(Assets::url('fontawesome')); ?>">
    <script src="<?php echo HTML::chars(Assets::url('jquery')); ?>"></script>
    <script src="<?php echo HTML::chars(Assets::url('template_js')); ?>"></script>

    <?php if (isset($styles) && is_array($styles)): ?>
        <?php foreach ($styles as $style): ?>
            <?php if (isset($style['file'])): ?>
                <?php echo HTML::style($style['file']); ?>
            <?php elseif (isset($style['code'])): ?>
                <style><?php echo $style['code']; ?></style>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <?php if (isset($script['file'])): ?>
                <?php echo HTML::script($script['file'], array('defer' => $script['defer'] ?? false)); ?>
            <?php elseif (isset($script['code'])): ?>
                <script><?php echo $script['code']; ?></script>
            <?php elseif (isset($script['src'])): ?>
                <script src="<?php echo HTML::chars($script['src']); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    // Подключение сторонних JS-библиотек страницы ($javascript_libraries)
    // убрано вместе с представлением main_lib: его нет в проекте, и вызов
    // молча гасился try/catch. Библиотеки подключаются через $this->_scripts.
    ?>

    <?php
    // Демо-режим: посетитель должен видеть, что он на стенде, и уметь выйти.
    // Состояние спрашиваем у владельца режима (erp811), а не у cookie напрямую.
    // Считаем до <body>: от этого зависят его атрибуты.
    $is_demo = FALSE;
    $demo_notice = 'Демо-режим: изменения отключены, чтобы стенд остался прежним для следующего посетителя.';

    if (array_key_exists('erp811_demo', Kohana::modules()) AND class_exists('Service_Erp811_Demo'))
    {
        try { $is_demo = Service_Erp811_Demo::instance()->active(); }
        catch (Exception $e) { $is_demo = FALSE; }
    }
    ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen flex flex-col font-sans transition-colors duration-200" data-notifications="<?php echo (array_key_exists('erp217_notification_center', Kohana::modules()) && isset($current_user_roles) && in_array('login', $current_user_roles)) ? '1' : '0'; ?>" data-demo="<?php echo $is_demo ? '1' : '0'; ?>" data-demo-notice="<?php echo HTML::chars($demo_notice); ?>">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:shadow-lg focus:outline-none">Перейти к содержимому</a>

    <?php
    $current_uri = '/' . trim(Request::current()->uri(), '/');
    $is_logged_in = isset($current_user) && $current_user;
    $is_admin = isset($current_user_roles) && in_array('admin', $current_user_roles);

    // One role-aware catalogue feeds desktop, mobile and Ctrl+K.
    // Гостю права не дают ни одного пункта, поэтому у него — своё меню из
    // открытых страниц: иначе боковая панель остаётся пустым столбцом.
    $nav_categories = $is_logged_in
        ? Ui_Navigation::categories(isset($current_user_roles) ? $current_user_roles : array(), Ui_Mode::current())
        : Ui_Navigation::guest_categories();
    ?>

    <!-- 1. Мобильное боковое меню (Drawer Overlay, только на мобильных экранах) -->
    <div id="mobile-sidebar-drawer" class="fixed inset-0 z-50 md:hidden hidden" role="dialog" aria-modal="true" aria-label="Мобильная навигация">
        <!-- Backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm" aria-hidden="true"></div>

        <!-- Выдвижная панель -->
        <div id="mobile-sidebar-panel" class="fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col shadow-2xl transform -translate-x-full transition-transform duration-200 ease-in-out">
            <!-- Шапка мобильного меню -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 dark:border-gray-700/60 shrink-0">
                <a href="/" class="flex items-center gap-3 text-blue-600 dark:text-blue-400 font-bold text-lg">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-500/20">
                        <i class="fas fa-cubes-stacked text-base" aria-hidden="true"></i>
                    </span>
                    <span class="font-bold text-gray-900 dark:text-white truncate">
                        <?php echo HTML::chars(isset($site_name) && $site_name ? $site_name : 'PromERP'); ?>
                    </span>
                </a>
                <button id="sidebar-close-mobile" type="button" aria-label="Закрыть меню" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-times text-lg" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Список категорий на мобильных -->
            <div class="flex-1 overflow-y-auto no-scrollbar p-3 space-y-4 text-sm font-medium">
                <?php foreach ($nav_categories as $cat_key => $category): ?>
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            <?php echo HTML::chars($category['title']); ?>
                        </div>
                        <div class="space-y-0.5">
                            <?php foreach ($category['items'] as $item): ?>
                                <?php
                                $is_active = FALSE;
                                if (!empty($item['exact'])) {
                                    $is_active = ($current_uri === $item['href']);
                                } else {
                                    $is_active = ($current_uri === $item['prefix'] || strpos($current_uri, $item['prefix'].'/') === 0);
                                }
                                ?>
                                <a href="<?php echo HTML::chars($item['href']); ?>" 
                                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all <?php echo $is_active ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:text-gray-900 dark:hover:text-white'; ?>">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg <?php echo $is_active ? 'bg-blue-600 text-white' : 'text-gray-400'; ?>">
                                        <i class="<?php echo HTML::chars($item['icon']); ?> text-xs" aria-hidden="true"></i>
                                    </span>
                                    <span class="truncate"><?php echo HTML::chars($item['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Подвал мобильного меню -->
            <div class="px-3 pt-3 border-t border-gray-100 dark:border-gray-700/60 shrink-0 bg-gray-50/50 dark:bg-gray-800/50">
                <?php echo Component::render('mode_switch'); ?>
            </div>
            <div class="p-3 shrink-0 bg-gray-50/50 dark:bg-gray-800/50 flex items-center justify-between">
                <?php if ($is_logged_in): ?>
                    <a href="/auth/profile" class="flex items-center gap-2.5 min-w-0">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white font-bold text-xs uppercase">
                            <?php echo HTML::chars(mb_substr($current_user->username ?? 'U', 0, 1, 'UTF-8')); ?>
                        </span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-white truncate max-w-[120px]">
                            <?php echo HTML::chars($current_user->username); ?>
                        </span>
                    </a>
                    <div class="flex items-center gap-1">
                        <button type="button" aria-label="Переключить тему" class="theme-toggle-btn p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-lg transition-colors">
                            <i class="theme-icon-sun fas fa-sun text-amber-400 text-base" aria-hidden="true"></i>
                            <i class="theme-icon-moon fas fa-moon text-slate-600 dark:text-slate-300 text-base" aria-hidden="true"></i>
                        </button>
                        <a href="/auth/logout" title="Выйти" class="p-2 text-gray-400 hover:text-rose-600 rounded-lg transition-colors">
                            <i class="fas fa-arrow-right-from-bracket text-sm" aria-hidden="true"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/auth/login" class="flex items-center justify-center gap-2 w-full py-2 px-3 rounded-xl bg-blue-600 text-white text-xs font-semibold">
                        Войти в систему
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Общий контейнер всего интерфейса -->
    <div class="min-h-screen flex flex-row w-full bg-gray-50 dark:bg-gray-900">
        <!-- 2. Десктопное левое боковое меню (Sidebar в обычном потоке Flexbox) -->
        <aside id="main-sidebar" class="hidden md:flex md:flex-col md:sticky md:top-0 md:h-screen shrink-0 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700/80 sidebar-transition z-30 select-none" aria-label="Основная навигация">
            
            <!-- Шапка десктопного сайдбара -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 dark:border-gray-700/60 shrink-0">
                <a href="/" class="sidebar-brand-wrapper flex items-center gap-3 overflow-hidden text-blue-600 dark:text-blue-400 font-bold text-lg tracking-tight group">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-500/20 group-hover:scale-105 transition-transform">
                        <i class="fas fa-cubes-stacked text-base" aria-hidden="true"></i>
                    </span>
                    <span class="sidebar-brand-name truncate font-bold text-gray-900 dark:text-white transition-opacity duration-200">
                        <?php echo HTML::chars(isset($site_name) && $site_name ? $site_name : 'PromERP'); ?>
                    </span>
                </a>
                
                <?php
                /*
                 * Кнопка сворачивания — на границе сайдбара, а не в его шапке:
                 * так место в шапке остаётся названию системы, а сама кнопка
                 * одинаково работает и в развёрнутом, и в свёрнутом виде.
                 */
                ?>
                <button id="sidebar-toggle-desktop" type="button" aria-label="Свернуть меню" aria-expanded="true"
                        title="Свернуть меню"
                        class="sidebar-collapse-toggle">
                    <i id="sidebar-toggle-icon" class="fas fa-chevron-left text-[10px] transition-transform duration-200" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Список категорий сайдбара -->
            <div class="flex-1 overflow-y-auto no-scrollbar p-3 space-y-5 text-sm font-medium">
                <?php foreach ($nav_categories as $cat_key => $category): ?>
                    <div>
                        <div class="sidebar-group-header px-3 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            <?php echo HTML::chars($category['title']); ?>
                        </div>
                        <div class="space-y-0.5">
                            <?php foreach ($category['items'] as $item): ?>
                                <?php
                                $is_active = FALSE;
                                if (!empty($item['exact'])) {
                                    $is_active = ($current_uri === $item['href']);
                                } else {
                                    $is_active = ($current_uri === $item['prefix'] || strpos($current_uri, $item['prefix'].'/') === 0);
                                }
                                ?>
                                <?php
                                /*
                                 * `data-nav-title` — подпись для всплывающей
                                 * подсказки свёрнутого меню: `title` браузер
                                 * показывает с задержкой и своим шрифтом, а в
                                 * узкой колонке иконок без подписи не понять,
                                 * куда ведёт пункт.
                                 */
                                ?>
                                <a href="<?php echo HTML::chars($item['href']); ?>"
                                   title="<?php echo HTML::chars($item['title']); ?>"
                                   aria-label="<?php echo HTML::chars($item['title']); ?>"
                                   data-nav-title="<?php echo HTML::chars($item['title']); ?>"
                                   class="sidebar-nav-item flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all group <?php echo $is_active ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-semibold shadow-sm shadow-blue-500/10' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:text-gray-900 dark:hover:text-white'; ?>">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors <?php echo $is_active ? 'bg-blue-600 text-white dark:bg-blue-500' : 'text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400'; ?>">
                                        <i class="<?php echo HTML::chars($item['icon']); ?> text-xs" aria-hidden="true"></i>
                                    </span>
                                    <span class="sidebar-text truncate"><?php echo HTML::chars($item['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Подвал десктопного сайдбара -->
            <div class="p-3 border-t border-gray-100 dark:border-gray-700/60 shrink-0 bg-gray-50/50 dark:bg-gray-800/50">
                <?php echo Component::render('mode_switch', array('class' => 'sidebar-user-details mb-3')); ?>
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <a href="/auth/profile" class="flex items-center gap-3 min-w-0 flex-1 group" title="Профиль пользователя">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs uppercase shadow-sm">
                                <?php echo HTML::chars(mb_substr($current_user->username ?? 'U', 0, 1, 'UTF-8')); ?>
                            </span>
                            <div class="sidebar-user-details min-w-0 flex-1">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    <?php echo HTML::chars($current_user->username); ?>
                                </p>
                                <p class="text-[11px] text-gray-400 truncate">
                                    <?php echo $is_admin ? 'Администратор' : 'Сотрудник'; ?>
                                </p>
                            </div>
                        </a>
                        <a href="/auth/logout" title="Выйти из системы" aria-label="Выйти" class="sidebar-user-details p-2 text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                            <i class="fas fa-arrow-right-from-bracket text-xs" aria-hidden="true"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/auth/login" class="flex items-center justify-center gap-2 w-full py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow transition-colors">
                        <i class="fas fa-right-to-bracket text-xs" aria-hidden="true"></i>
                        <span class="sidebar-text">Войти в систему</span>
                    </a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- 3. Основная область приложения (справа от десктопного сайдбара) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-gray-50 dark:bg-gray-900">
            <!-- Верхняя панель (Topbar Header) -->
            <header class="sticky top-0 z-20 h-16 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border-b border-gray-200 dark:border-gray-700/80 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0">
                
                <!-- Левая часть топбара: Кнопка мобильного меню + Название + Вкладки подраздела -->
                <div class="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
                    <button id="mobile-sidebar-toggle" type="button" aria-label="Открыть боковое меню" aria-expanded="false" class="md:hidden p-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition-colors shrink-0">
                        <i class="fas fa-bars text-lg" aria-hidden="true"></i>
                    </button>

                    <?php
                    /*
                     * Название системы в топбаре — для узкого экрана: десктопный
                     * сайдбар скрыт, и без него шапка остаётся пустой полосой с
                     * одной кнопкой меню, по которой непонятно, куда человек
                     * попал. На широком экране название уже есть в сайдбаре.
                     */
                    ?>
                    <a href="/" class="md:hidden flex items-center gap-2 shrink-0 text-blue-600 dark:text-blue-400">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                            <i class="fas fa-cubes-stacked text-xs" aria-hidden="true"></i>
                        </span>
                        <span class="truncate text-sm font-bold text-gray-900 dark:text-white">
                            <?php echo HTML::chars(isset($site_name) && $site_name ? $site_name : 'PromERP'); ?>
                        </span>
                    </a>

                    <!-- Подразделы / Вкладки текущего модуля -->
                    <?php
                    /*
                     * Для гостя меню разделов уже есть в постоянной боковой
                     * панели. Не дублируем его в топбаре: справа остаются
                     * глобальные действия (тема и вход), слева — мобильное
                     * меню и название системы.
                     */
                    ?>
                    <?php if (isset($menu_items) && is_array($menu_items) && !empty($menu_items)): ?>
                        <nav class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar py-1" aria-label="Вкладки подраздела">
                            <?php foreach ($menu_items as $subitem): ?>
                                <?php
                                $sub_href = !empty($subitem['href']) ? $subitem['href'] : (!empty($subitem['url']) ? $subitem['url'] : '#');
                                $sub_active = !empty($subitem['active']);
                                ?>
                                <a href="<?php echo HTML::chars($sub_href); ?>" 
                                   class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 <?php echo $sub_active ? 'bg-blue-600 text-white font-semibold shadow-sm shadow-blue-500/20' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:text-gray-900 dark:hover:text-white'; ?>"
                                   <?php echo $sub_active ? 'aria-current="page"' : ''; ?>>
                                    <?php if (!empty($subitem['icon'])): ?>
                                        <i class="<?php echo HTML::chars($subitem['icon']); ?> text-xs" aria-hidden="true"></i>
                                    <?php endif; ?>
                                    <span><?php echo HTML::chars($subitem['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </div>

                <!-- Правая часть топбара: Поиск Ctrl+K, Закладки, Уведомления, Тема, Профиль -->
                <div class="flex items-center space-x-2 sm:space-x-3 shrink-0 ml-3">
                    <?php if ($is_demo): ?>
                        <!--
                            Индикатор демо-режима.

                            Раньше это была одна кнопка-колба, открывавшая меню с
                            выходом: посетители жали на неё и не понимали, почему
                            ничего не происходит, — выход прятался за вторым
                            кликом. Теперь рядом два отдельных элемента: метка
                            (объясняет режим, открывает справку) и кнопка выхода
                            со значком двери — выход в один клик.
                        -->
                        <div class="relative flex items-center" data-demo-indicator>
                            <button type="button" id="demo-mode-button" aria-haspopup="true" aria-expanded="false"
                                    title="Вы в демонстрационном режиме. Нажмите, чтобы узнать подробнее"
                                    class="inline-flex items-center gap-1.5 rounded-l-xl border border-r-0 border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">
                                <i class="fas fa-flask text-[11px]" aria-hidden="true"></i>
                                <span class="hidden sm:inline">Демо-режим</span>
                                <span class="sr-only">Вы в демонстрационном режиме. Подробнее о режиме</span>
                            </button>

                            <!-- Выход одним нажатием: POST, потому что это смена состояния. -->
                            <form method="post" action="/demo/exit" class="contents">
                                <button type="submit" title="Выйти из демо-режима"
                                        class="inline-flex items-center gap-1.5 rounded-r-xl border border-amber-300 bg-amber-100 px-2.5 py-1.5 text-xs font-semibold text-amber-900 transition-colors hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-200 dark:hover:bg-amber-500/30">
                                    <i class="fas fa-right-from-bracket text-[11px]" aria-hidden="true"></i>
                                    <span class="sr-only">Выйти из демо-режима</span>
                                </button>
                            </form>

                            <div id="demo-mode-menu" role="dialog" aria-labelledby="demo-mode-title"
                                 class="hidden absolute right-0 top-full mt-2 w-72 rounded-2xl border border-gray-200 bg-white p-4 shadow-xl z-50 dark:border-gray-700 dark:bg-gray-800">
                                <p id="demo-mode-title" class="text-sm font-bold text-gray-900 dark:text-white">
                                    Демонстрационный режим
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Данные вымышленные и лежат в отдельной базе. Изменения отключены:
                                    стенд остаётся одинаковым для следующего посетителя.
                                </p>
                                <form method="post" action="/demo/exit" class="mt-3">
                                    <button type="submit"
                                            class="w-full rounded-xl bg-amber-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        <i class="fas fa-right-from-bracket mr-1" aria-hidden="true"></i>
                                        Выйти из демо-режима
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                        <button id="command-palette-button" type="button" aria-label="Открыть поиск и команды" aria-keyshortcuts="Control+K" class="hidden sm:inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 px-3 py-1.5 text-xs text-gray-500 hover:border-blue-400 hover:text-blue-600 dark:text-gray-400 dark:hover:border-blue-500 transition-all shadow-sm">
                            <i class="fas fa-magnifying-glass text-xs" aria-hidden="true"></i>
                            <span>Поиск</span>
                            <kbd class="rounded border border-gray-300 bg-white px-1.5 py-0.5 text-[10px] font-mono dark:border-gray-600 dark:bg-gray-800 text-gray-400">Ctrl K</kbd>
                        </button>

                        <?php if (array_key_exists('erp118_links', Kohana::modules())): ?>
                            <span data-lazy-fragment="/erp118_widget/bookmark_button" aria-label="Закладка страницы"></span>
                        <?php endif; ?>

                        <?php if (array_key_exists('erp217_notification_center', Kohana::modules())): ?>
                            <a href="/erp217_index?unread=1" class="relative p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700/60 rounded-xl transition-colors" title="Уведомления" aria-label="Уведомления">
                                <i class="fas fa-bell text-base" aria-hidden="true"></i>
                                <span id="notification-badge" class="hidden absolute top-1.5 right-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full" aria-label="Непрочитанные уведомления">0</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php
                    /*
                     * Признак связи. В цеху сеть пропадает чаще, чем на офисном
                     * этаже, и непонятно, почему действие «не сработало». Пока
                     * связь есть, элемент скрыт и места не занимает: в топбаре
                     * не должно быть постоянного «всё хорошо».
                     *
                     * Скрытие — через обёртку без display-утилиты. Сам атрибут
                     * `hidden` задаёт `display: none` правилом из базового слоя
                     * Tailwind, а утилита `.inline-flex` стоит в слое утилит —
                     * позже и с той же специфичностью. На одном элементе они
                     * спорят, и побеждает утилита: индикатор был бы виден
                     * всегда. Проверку держит `bin/frontend-audit.py`.
                     */
                    ?>
                    <span id="connection-status" role="status" aria-live="polite" hidden>
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300">
                            <i class="fas fa-plug-circle-xmark text-[11px]" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Нет связи</span>
                            <span class="sr-only">Связь с сервером потеряна: действия не дойдут до системы</span>
                        </span>
                    </span>

                    <!-- Переключатель темы (Dark/Light) -->
                    <button type="button" aria-label="Переключить тему" title="Переключить тему" class="theme-toggle-btn p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700/60 rounded-xl transition-colors">
                        <i class="theme-icon-sun fas fa-sun text-amber-400 text-base" aria-hidden="true"></i>
                        <i class="theme-icon-moon fas fa-moon text-slate-600 dark:text-slate-300 text-base" aria-hidden="true"></i>
                    </button>

                    <!-- Меню профиля -->
                    <?php if ($is_logged_in): ?>
                        <div class="relative">
                            <button id="userDropdownButton" type="button" aria-haspopup="true" aria-expanded="false" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white font-bold text-xs uppercase shadow-sm">
                                    <?php echo HTML::chars(mb_substr($current_user->username ?? 'U', 0, 1, 'UTF-8')); ?>
                                </span>
                                <i id="dropdownArrow" class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200 hidden sm:inline" aria-hidden="true"></i>
                            </button>

                            <div id="userDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 py-1.5 z-50 opacity-0 scale-95 transition-all duration-150 transform">
                                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700/80">
                                    <p class="text-xs font-semibold text-gray-900 dark:text-white truncate"><?php echo HTML::chars($current_user->username); ?></p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate"><?php echo HTML::chars($current_user->email); ?></p>
                                </div>
                                <div class="p-1 space-y-0.5">
                                    <a href="/auth/profile" class="flex items-center px-3 py-2 text-xs font-medium rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <i class="fas fa-user-circle mr-2.5 text-gray-400 text-sm" aria-hidden="true"></i> Мой профиль
                                    </a>
                                    <?php if ($is_admin): ?>
                                        <a href="/admin_index" class="flex items-center px-3 py-2 text-xs font-medium rounded-xl hover:bg-amber-50 dark:hover:bg-amber-900/20 text-amber-600 dark:text-amber-400 transition-colors">
                                            <i class="fas fa-shield-halved mr-2.5 text-sm" aria-hidden="true"></i> Администрирование
                                        </a>
                                        <a href="/migrate_pg" class="flex items-center px-3 py-2 text-xs font-medium rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 transition-colors">
                                            <i class="fas fa-database mr-2.5 text-sm" aria-hidden="true"></i> Миграции БД
                                        </a>
                                    <?php endif; ?>
                                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                    <a href="/auth/logout" class="flex items-center px-3 py-2 text-xs font-medium rounded-xl text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                        <i class="fas fa-arrow-right-from-bracket mr-2.5 text-sm" aria-hidden="true"></i> Выйти
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login" class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-colors">
                            Вход
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Хлебные крошки (Breadcrumbs) -->
            <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
                <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-1">
                    <nav class="flex overflow-x-auto no-scrollbar whitespace-nowrap text-xs text-gray-500 dark:text-gray-400" aria-label="Навигационная цепочка">
                        <ol class="flex items-center gap-2">
                            <?php foreach ($breadcrumbs as $key => $crumb): ?>
                                <li class="flex items-center gap-2">
                                    <?php if ($key > 0): ?>
                                        <i class="fas fa-chevron-right text-[9px] text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                                    <?php endif; ?>
                                    <?php if (isset($crumb['active']) && $crumb['active']): ?>
                                        <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo HTML::chars($crumb['text']); ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo HTML::chars($crumb['href']); ?>" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors"><?php echo HTML::chars($crumb['text']); ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
            <?php endif; ?>

            <!-- Основной заголовок страницы (H1) -->
            <?php if (isset($h1) && $h1): ?>
                <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight"><?php echo HTML::chars($h1); ?></h1>
                </div>
            <?php endif; ?>

            <!-- Основной контент страницы -->
            <main id="main-content" tabindex="-1" class="flex-1 min-w-0 w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 py-6 outline-none">
                <?php echo $content; ?>
            </main>

            <?php
            // Публичные страницы. Гостю их негде найти: в меню вход в систему,
            // а условия и политика нужны до регистрации. Подвал только у
            // неавторизованных — внутри системы он бы мешал работе.
            if ( ! $is_logged_in): ?>
                <footer class="mt-auto border-t border-gray-200 dark:border-gray-700/80 px-4 sm:px-6 lg:px-8 py-6">
                    <div class="mx-auto max-w-[1920px] space-y-3 text-xs text-gray-500 dark:text-gray-400">
                        <nav class="flex flex-wrap items-center gap-x-5 gap-y-2"
                             aria-label="Разделы сайта">
                            <a href="/about" class="hover:text-blue-600 dark:hover:text-blue-400">О сервисе</a>
                            <a href="/docs" class="hover:text-blue-600 dark:hover:text-blue-400">Документация</a>
                            <a href="/demo" class="hover:text-blue-600 dark:hover:text-blue-400">Демо-стенд</a>
                            <a href="/contact" class="hover:text-blue-600 dark:hover:text-blue-400">Обратная связь</a>
                        </nav>

                        <div class="flex flex-wrap items-center justify-between gap-x-5 gap-y-2 border-t border-gray-100 pt-3 dark:border-gray-700/60">
                            <p>
                                <?php echo HTML::chars(isset($site_name) && $site_name ? $site_name : 'PromERP'); ?>
                                — система управления производственным предприятием
                            </p>

                            <nav class="flex flex-wrap items-center gap-x-5 gap-y-2" aria-label="Правовые страницы">
                                <a href="/terms" class="hover:text-blue-600 dark:hover:text-blue-400">Условия использования</a>
                                <a href="/privacy" class="hover:text-blue-600 dark:hover:text-blue-400">Политика конфиденциальности</a>
                            </nav>
                        </div>
                    </div>
                </footer>
            <?php endif; ?>

            <?php
            // Cookie Consent
            if (array_key_exists('cookie_consent', Kohana::modules())) {
                try {
                    echo Controller_CookieConsent::get_popup_html();
                } catch (Exception $e) {}
            }
            ?>
        </div>
    </div>

    <!-- Палитра команд / Быстрый поиск (Ctrl+K) -->
    <?php if ($is_logged_in): ?>
        <?php
        $palette_items = Ui_Navigation::palette(isset($current_user_roles) ? $current_user_roles : array(), Ui_Mode::current());
        ?>
        <div id="command-palette" class="fixed inset-0 z-[90] hidden" role="dialog" aria-modal="true" aria-labelledby="command-palette-title">
            <div id="command-palette-backdrop" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <div class="relative mx-auto mt-[10vh] w-[min(92vw,640px)] overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 px-4">
                    <i class="fas fa-magnifying-glass text-gray-400 text-sm" aria-hidden="true"></i>
                    <label for="command-palette-input" id="command-palette-title" class="sr-only">Поиск по разделам и командам</label>
                    <input id="command-palette-input" type="search" autocomplete="off" placeholder="Найти раздел, модуль или операцию…" class="w-full border-0 bg-transparent py-3.5 text-sm text-gray-900 dark:text-white outline-none placeholder:text-gray-400">
                    <kbd class="hidden sm:inline-block rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2 py-0.5 text-[10px] font-mono text-gray-400">Esc</kbd>
                </div>
                <div id="command-palette-results" class="max-h-[55vh] overflow-y-auto p-2 space-y-1" role="listbox">
                    <?php foreach ($palette_items as $palette_item): ?>
                        <a href="<?php echo HTML::chars($palette_item['href']); ?>" role="option" class="command-palette-item flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 dark:text-gray-200 dark:hover:bg-blue-900/40 dark:hover:text-blue-300 transition-colors" data-search="<?php echo HTML::chars(mb_strtolower($palette_item['title'], 'UTF-8')); ?>">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300">
                                <i class="<?php echo HTML::chars($palette_item['icon']); ?> text-xs" aria-hidden="true"></i>
                            </span>
                            <span class="flex-1 font-medium"><?php echo HTML::chars($palette_item['title']); ?></span>
                            <i class="fas fa-arrow-right text-[10px] text-gray-400" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-100 dark:border-gray-700 px-4 py-2 flex items-center justify-between text-[11px] text-gray-400">
                    <span>Навигация по разделам</span>
                    <span>Нажмите <kbd class="font-mono">Enter</kbd> для перехода</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="notification-container" aria-live="polite" aria-atomic="true" role="status"></div>
    <?php // Подсказка свёрнутого меню: один элемент на все пункты, место считает app.js. ?>
    <div id="sidebar-tooltip" role="tooltip" aria-hidden="true"></div>
    <script src="<?php echo HTML::chars(Assets::url('app_js')); ?>" defer></script>
    <script src="<?php echo HTML::chars(Assets::url('smart_reference_js')); ?>" defer></script>
</body>
</html>
