<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="/erp227_types" class="text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400">
                            Типы объектов
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                <?php echo $type->loaded() ? 'Редактирование' : 'Создание'; ?>
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <?php echo $type->loaded() ? HTML::chars($type->name) : 'Новый тип объекта'; ?>
            </h1>
        </div>
    </div>

    <form action="" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Основные настройки -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 card-shadow">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i> Основная информация
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Наименование</label>
                            <input type="text" name="name" value="<?php echo HTML::chars($type->name); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Системный код (code)</label>
                            <input type="text" name="code" value="<?php echo HTML::chars($type->code); ?>" required
                                   <?php echo $type->loaded() ? 'readonly' : ''; ?>
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none font-mono <?php echo $type->loaded() ? 'opacity-60' : ''; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Иконка (FontAwesome)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="<?php echo $type->icon ? HTML::chars($type->icon) : 'fas fa-tag'; ?>" id="icon-preview"></i>
                                </span>
                                <input type="text" name="icon" id="icon-input" value="<?php echo HTML::chars($type->icon); ?>" 
                                       placeholder="fas fa-cube"
                                       class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 card-shadow">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center justify-between">
                        <span class="flex items-center"><i class="fas fa-database mr-2 text-purple-500"></i> Схема атрибутов (JSON)</span>
                        <span class="text-xs font-normal text-gray-400">Определяет динамические поля</span>
                    </h2>
                    <textarea name="attribute_schema" rows="12" 
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-blue-600 dark:text-blue-400 font-mono text-sm focus:ring-2 focus:ring-blue-500 outline-none"><?php echo HTML::chars($type->schema); ?></textarea>
                </div>
            </div>

            <!-- Боковая панель -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 card-shadow">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-desktop mr-2 text-green-500"></i> UI Конфигурация
                    </h2>
                    <textarea name="ui_config" rows="8" 
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 font-mono text-xs focus:ring-2 focus:ring-blue-500 outline-none"><?php echo HTML::chars($type->ui_config); ?></textarea>
                    <p class="mt-2 text-xs text-gray-500">Настройки отображения в списках и карточках.</p>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 border border-blue-100 dark:border-blue-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> Сохранить изменения
                    </button>
                    <a href="/erp227_types" class="w-full mt-3 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Отмена
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.getElementById('icon-input').addEventListener('input', function(e) {
        const preview = document.getElementById('icon-preview');
        preview.className = e.target.value || 'fas fa-tag';
    });
</script>