<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Заголовок и навигация -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="<?php echo URL::site('erp212_storage'); ?>" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 transition-colors">
                            <i class="fas fa-hdd mr-2"></i>Хранилище
                        </a>
                    </li>
                    <li class="inline-flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2 text-xs"></i>
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Журнал доступа</span>
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Журнал доступа к файлам</h1>
        </div>

        <div class="flex gap-2">
            <a href="<?php echo URL::site('erp212_storage'); ?>" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i>Назад в хранилище
            </a>
        </div>
    </div>

    <!-- Статистика логов -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Всего записей</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($total, 0, '.', ' '); ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Страница</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $current_page; ?> / <?php echo $total_pages; ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Записей на страницу</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $per_page; ?></div>
        </div>
    </div>

    <!-- Таблица логов -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Дата и время</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Пользователь</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действие</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Файл / Объект</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP-адрес</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-history text-4xl mb-3 block opacity-20"></i>
                                Записи в журнале отсутствуют
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/20 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                                    <?php echo date('d.m.Y H:i:s', strtotime($log->created_at)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-7 w-7 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 mr-2 text-xs font-bold">
                                            <?php echo strtoupper(substr($log->user_name ?? 'S', 0, 1)); ?>
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo HTML::chars($log->user_name ?? 'Система'); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                        $action_class = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                        if (strpos($log->action, 'upload') !== false) $action_class = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
                                        if (strpos($log->action, 'delete') !== false) $action_class = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                                        if (strpos($log->action, 'download') !== false) $action_class = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
                                    ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $action_class; ?>">
                                        <?php echo HTML::chars($log->action); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white truncate max-w-xs" title="<?php echo HTML::chars($log->details); ?>">
                                        <?php echo HTML::chars($log->details); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                                    <?php echo HTML::chars($log->ip_address); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Показано <span class="font-medium text-gray-900 dark:text-white"><?php echo count($logs); ?></span> из <span class="font-medium text-gray-900 dark:text-white"><?php echo $total; ?></span> записей
                </div>
                <div class="flex gap-2">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors shadow-sm">
                            <i class="fas fa-chevron-left mr-2"></i>Предыдущая
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors shadow-sm">
                            Следующая <i class="fas fa-chevron-right ml-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>