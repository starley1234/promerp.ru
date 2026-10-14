<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-globe-americas mr-3 text-blue-500"></i>
                Общие файлы
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Список файлов с публичным доступом для всех пользователей системы
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Всего файлов: <span class="font-semibold text-gray-900 dark:text-white"><?php echo $total; ?></span>
            </span>
        </div>
    </div>

    <?php if (count($files) > 0): ?>
        <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Имя файла</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Размер</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Дата загрузки</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($files as $file): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                            <i class="fas fa-file-alt text-lg"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo HTML::chars($file->filename); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-500 font-mono">
                                                ID: <?php echo $file->id; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        <?php echo HTML::chars($file->extension ?: 'bin'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo Text::bytes($file->file_size); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo date('d.m.Y H:i', strtotime($file->created_at)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'file', 'id' => $file->id]); ?>" 
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                                           title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo Route::url('default', ['controller' => 'erp212_download', 'action' => 'file', 'id' => $file->id]); ?>" 
                                           class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 p-2 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors"
                                           title="Скачать">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total > 20): ?>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Страница <?php echo $page; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50">Назад</a>
                            <?php endif; ?>
                            <?php if ($total > $page * 20): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50">Вперед</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-folder-open text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Публичные файлы отсутствуют</h3>
            <p class="mt-1 text-gray-500 dark:text-gray-400">В данный момент нет файлов, доступных для общего просмотра.</p>
        </div>
    <?php endif; ?>
</div>