<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-server mr-3 text-indigo-500"></i>
                Управление хранилищами (Backends)
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Конфигурация драйверов и физических мест хранения файлов
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <button class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Добавить бэкенд
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Название и ID</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Драйвер</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Статус</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Конфигурация</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($backends as $backend): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400">
                                            <i class="fas <?php 
                                                echo $backend->driver === 's3' ? 'fa-cloud' : ($backend->driver === 'ftp' ? 'fa-network-wired' : 'fa-hdd'); 
                                            ?> text-lg"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo HTML::chars($backend->name); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-500 font-mono">
                                                ID: <?php echo $backend->id; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase">
                                        <?php echo HTML::chars($backend->driver); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($backend->is_active): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            <span class="w-2 h-2 mr-1.5 bg-green-500 rounded-full"></span>
                                            Активен
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            <span class="w-2 h-2 mr-1.5 bg-red-500 rounded-full"></span>
                                            Отключен
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate font-mono">
                                        <?php 
                                            $config = $backend->get_config_array();
                                            if ($backend->driver === 'local') {
                                                echo 'Path: ' . HTML::chars($config['root_path'] ?? 'default');
                                            } elseif ($backend->driver === 's3') {
                                                echo 'Bucket: ' . HTML::chars($config['bucket'] ?? '-');
                                            } elseif ($backend->driver === 'ftp') {
                                                echo 'Host: ' . HTML::chars($config['host'] ?? '-');
                                            }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 p-2 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors" title="Проверить соединение">
                                            <i class="fas fa-plug"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Справка по драйверам</h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Local:</strong> Файлы хранятся на сервере приложения. Требуются права на запись в целевую директорию.</li>
                        <li><strong>S3:</strong> Совместимые хранилища (AWS, Minio, Selectel). Требуются Key, Secret и Bucket.</li>
                        <li><strong>FTP:</strong> Удаленные серверы. Рекомендуется использовать только в изолированных сетях.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>