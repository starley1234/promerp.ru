<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html>
<html lang="ru" class="scroll-smooth">
<head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo HTML::chars(isset($title) ? $title : 'Module Manager | Реестр систем'); ?></title>
    <meta name="description" content="<?php echo HTML::chars(isset($meta['description']) ? $meta['description'] : 'Централизованный учет и управление метаданными модулей Kohana'); ?>">
    <meta name="author" content="<?php echo HTML::chars(isset($meta['author']) ? $meta['author'] : 'erp218_developer'); ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    
    <!-- CSS Frameworks -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    
    <!-- Core Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        dark: '#0f172a',
                        status_alpha: '#f59e0b',
                        status_beta: '#3b82f6',
                        status_stable: '#10b981',
                        status_archived: '#6b7280'
                    }
                }
            }
        }
    </script>
    
    <style>
        .sidebar-transition { transition: all 0.3s ease-in-out; }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .dark .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        
        .theme-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
        .theme-toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #3b82f6; }
        input:checked + .slider:before { transform: translateX(24px); }
        
        #notification-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 1000; display: flex; flex-direction: column-reverse; gap: 0.5rem; }
        .notification { display: flex; align-items: center; padding: 0.75rem 1rem; border-radius: 0.5rem; opacity: 0; transform: translateY(20px); transition: all 0.3s; min-width: 280px; }
        .notification.show { opacity: 1; transform: translateY(0); }
        
        .status-badge { padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
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
        <div class="container mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <a href="/erp218_index" class="text-xl font-bold flex items-center text-blue-600 dark:text-blue-400">
                    <i class="fas fa-cubes-stacked mr-2"></i>
                    <span class="text-gray-900 dark:text-white hidden sm:inline">Module Manager</span>
                </a>
            </div>

            <div class="flex items-center space-x-4">
                <!-- Theme Toggle -->
                <label class="theme-toggle">
                    <input type="checkbox" id="theme-toggle">
                    <span class="slider"></span>
                </label>

                <?php if (isset($current_user)): ?>
                <div class="flex items-center space-x-3 border-l pl-4 border-gray-200 dark:border-gray-700">
                    <span class="text-sm font-medium hidden md:block"><?php echo HTML::chars($current_user->username); ?></span>
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white">
                        <?php echo strtoupper(substr($current_user->username, 0, 1)); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex-grow container mx-auto px-4 py-6">
        
        <!-- Breadcrumbs -->
        <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
        <nav class="flex mb-4 text-sm" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li><a href="/" class="text-gray-400 hover:text-blue-600"><i class="fas fa-home"></i></a></li>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <li class="flex items-center space-x-2">
                        <i class="fas fa-chevron-right text-gray-400 text-[10px]"></i>
                        <a href="<?php echo HTML::chars($crumb['href']); ?>" class="text-gray-500 hover:text-blue-600 <?php echo (!empty($crumb['active']) ? 'font-bold text-blue-500' : ''); ?>">
                            <?php echo HTML::chars($crumb['text']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>

        <!-- Page Title -->
        <?php if (isset($h1)): ?>
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white"><?php echo HTML::chars($h1); ?></h1>
        <?php endif; ?>

        <!-- Module Content -->
        <main class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <?php echo $content; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer class="mt-auto py-6 border-t border-gray-200 dark:border-gray-700">
        <div class="container mx-auto px-4 text-center text-sm text-gray-500">
            &copy; <?php echo date('Y'); ?> ERP System | Module Registry v1.0
        </div>
    </footer>

    <!-- Notifications -->
    <div id="notification-container"></div>

    <!-- Scripts -->
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

            // Global Notification Function
            window.showNotification = function(message, type = 'success') {
                const container = document.getElementById('notification-container');
                const toast = document.createElement('div');
                const bgClass = type === 'success' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200';
                
                toast.className = `notification show border ${bgClass} shadow-md p-4 mb-2`;
                toast.innerHTML = `<span>${message}</span>`;
                
                container.appendChild(toast);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            };
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