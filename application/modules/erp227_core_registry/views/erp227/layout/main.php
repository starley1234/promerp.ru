<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html>
<html lang="ru" class="scroll-smooth">
<head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo HTML::chars(isset($title) ? $title : 'Универсальный реестр объектов'); ?></title>
    <meta name="description" content="<?php echo HTML::chars(isset($meta['description']) ? $meta['description'] : 'Core Registry — центральный модуль ERP для хранения и иерархии объектов'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'dark') document.documentElement.classList.add('dark');
        })();

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { primary: '#3b82f6', dark: '#0f172a' }
                }
            }
        }
    </script>
    
    <style>
        .sidebar-transition { transition: all 0.3s ease-in-out; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        .theme-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
        .theme-toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #3b82f6; }
        input:checked + .slider:before { transform: translateX(24px); }
        
        #notification-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 1000; display: flex; flex-direction: column-reverse; gap: 0.5rem; }
        .notification { display: flex; align-items: center; padding: 0.75rem 1rem; border-radius: 0.5rem; opacity: 0; transform: translateY(20px); transition: all 0.3s; min-width: 280px; }
        .notification.show { opacity: 1; transform: translateY(0); }
    </style>

    <?php if (isset($styles) && is_array($styles)): ?>
        <?php foreach ($styles as $style): ?>
            <?php if (isset($style['file'])): ?>
                <link rel="stylesheet" href="<?php echo HTML::chars($style['file']); ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen flex flex-col transition-colors duration-200">
    
    <!-- Header -->
    <header class="sticky top-0 z-40 w-full bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border-b border-gray-200 dark:border-gray-700">
        <div class="container-fluid mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <a href="/" class="text-xl font-bold flex items-center text-blue-600 dark:text-blue-400 mr-8">
                    <?php echo isset($site_logo) ? $site_logo : '<i class="fas fa-database mr-2"></i>'; ?>
                    <span class="text-gray-900 dark:text-white hidden sm:inline"><?php echo HTML::chars(isset($site_name) ? $site_name : 'Registry'); ?></span>
                </a>
            </div>

            <div class="flex items-center space-x-4">
                <label class="theme-toggle">
                    <input type="checkbox" id="theme-toggle">
                    <span class="slider"></span>
                </label>
                
                <?php if (isset($current_user)): ?>
                    <div class="flex items-center space-x-3 border-l border-gray-200 dark:border-gray-700 pl-4">
                        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold">
                            <?php echo HTML::chars(strtoupper(substr($current_user->username, 0, 1))); ?>
                        </div>
                        <span class="hidden lg:block text-sm font-medium"><?php echo HTML::chars($current_user->username); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Navigation -->
        <aside class="w-64 hidden md:flex flex-col bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 sidebar-transition">
            <div class="p-4 space-y-1 overflow-y-auto">
                <p class="text-xs font-semibold text-gray-400 uppercase px-3 mb-2 tracking-wider">Реестр</p>
                
                <a href="/erp227_registry" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-list w-5 mr-3 text-blue-500"></i> Все объекты
                </a>
                <a href="/erp227_registry/tree" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-sitemap w-5 mr-3 text-green-500"></i> Иерархия (LTree)
                </a>
                <a href="/erp227_registry/types" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-tags w-5 mr-3 text-purple-500"></i> Типы и схемы
                </a>
                
                <div class="my-4 border-t border-gray-100 dark:border-gray-700"></div>
                
                <p class="text-xs font-semibold text-gray-400 uppercase px-3 mb-2 tracking-wider">Инструменты</p>
                <a href="/erp227_registry/search" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-search w-5 mr-3 text-amber-500"></i> AI Поиск
                </a>
                <a href="/erp227_registry/units" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-ruler w-5 mr-3 text-cyan-500"></i> Ед. измерения
                </a>
                <a href="/erp227_registry/audit" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-history w-5 mr-3 text-red-500"></i> Аудит событий
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto relative">
            <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
            <nav class="px-6 py-3 bg-white dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700" aria-label="Breadcrumb">
                <ol class="flex items-center text-xs space-x-2">
                    <?php foreach ($breadcrumbs as $key => $crumb): ?>
                        <?php if ($key > 0): ?><li><i class="fas fa-chevron-right text-gray-400"></i></li><?php endif; ?>
                        <li>
                            <a href="<?php echo HTML::chars($crumb['href']); ?>" class="<?php echo (isset($crumb['active']) && $crumb['active']) ? 'text-gray-500' : 'text-blue-600 hover:underline'; ?>">
                                <?php echo HTML::chars($crumb['text']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="p-6">
                <?php if (isset($h1)): ?>
                    <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white"><?php echo HTML::chars($h1); ?></h1>
                <?php endif; ?>

                <?php echo $content; ?>
            </div>
        </main>
    </div>

    <div id="notification-container"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            const html = document.documentElement;
            
            if (html.classList.contains('dark')) themeToggle.checked = true;

            themeToggle?.addEventListener('change', (e) => {
                if (e.target.checked) {
                    html.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            });
        });
    </script>

    <?php if (isset($scripts) && is_array($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <?php if (isset($script['file'])): ?>
                <script src="<?php echo HTML::chars($script['file']); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>