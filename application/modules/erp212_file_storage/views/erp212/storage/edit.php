<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-4xl">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-edit mr-3 text-blue-500"></i>
                Редактирование файла
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Изменение метаданных и параметров доступа для файла #<?php echo $file->id; ?>
            </p>
        </div>
        <a href="<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'file', 'id' => $file->id]); ?>" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i> К информации
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form action="" method="POST" class="p-6 space-y-6">
            
            <?php if (!empty($errors)): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo HTML::chars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Имя файла -->
            <div>
                <label for="filename" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Имя файла
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-file-signature text-gray-400"></i>
                    </div>
                    <input type="text" name="filename" id="filename" 
                           value="<?php echo HTML::chars($file->filename); ?>"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors"
                           required>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Отображаемое имя файла в системе.</p>
            </div>

            <!-- Префикс модуля -->
            <div>
                <label for="module_prefix" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Префикс модуля
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-tag text-gray-400"></i>
                    </div>
                    <input type="text" name="module_prefix" id="module_prefix" 
                           value="<?php echo HTML::chars($file->module_prefix); ?>"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors">
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Используется для группировки файлов по модулям системы.</p>
            </div>

            <!-- Общий доступ -->
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input id="is_public" name="is_public" type="checkbox" value="1" 
                           <?php echo $file->is_public ? 'checked' : ''; ?>
                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900">
                </div>
                <div class="ml-3 text-sm">
                    <label for="is_public" class="font-medium text-gray-700 dark:text-gray-300">Общий файл</label>
                    <p class="text-gray-500 dark:text-gray-400">Если включено, файл будет виден всем пользователям в разделе «Общие файлы».</p>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-4">
                <button type="reset" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                    Сбросить изменения
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-save mr-2"></i> Сохранить изменения
                </button>
            </div>
        </form>
    </div>

    <!-- Информационная панель -->
    <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 rounded-xl p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Техническая информация</h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Физический путь и MIME-тип файла изменить нельзя.</li>
                        <li>Для изменения прав доступа конкретных пользователей используйте вкладку «Права доступа» в карточке файла.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>