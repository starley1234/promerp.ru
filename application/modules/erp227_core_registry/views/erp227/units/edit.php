<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/erp227_units" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white inline-flex items-center text-sm font-medium">
                        <i class="fas fa-balance-scale mr-2"></i> Справочник единиц
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-medium">
                            <?php echo $unit->loaded() ? 'Редактирование: ' . HTML::chars($unit->unit) : 'Новая единица'; ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <?php echo $unit->loaded() ? 'Редактирование единицы измерения' : 'Создание единицы измерения'; ?>
        </h1>
    </div>

    <div class="max-w-2xl bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <form action="" method="POST" class="p-6 space-y-6">
            
            <!-- Код единицы (ID) -->
            <div>
                <label for="unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Код единицы (ID) <span class="text-red-500">*</span>
                </label>
                <input type="text" name="unit" id="unit" 
                       value="<?php echo HTML::chars($unit->unit); ?>" 
                       <?php echo $unit->loaded() ? 'readonly' : 'required'; ?>
                       class="block w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 <?php echo $unit->loaded() ? 'opacity-60 cursor-not-allowed' : ''; ?>"
                       placeholder="например: pcs, kg, m">
                <p class="mt-1 text-xs text-gray-500">Системный идентификатор. После создания изменить нельзя.</p>
            </div>

            <!-- Наименование -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Наименование <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="title" 
                       value="<?php echo HTML::chars($unit->name); ?>" 
                       required
                       class="block w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                       placeholder="например: Штука, Килограмм">
            </div>

            <!-- Флаг дробности -->
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input type="hidden" name="is_decimal" value="0">
                    <input id="is_decimal" name="is_decimal" type="checkbox" value="1" 
                           <?php echo $unit->is_decimal ? 'checked' : ''; ?>
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div class="ml-3 text-sm">
                    <label for="is_decimal" class="font-medium text-gray-700 dark:text-gray-300">Разрешить дробные</label>
                    <p class="text-gray-500 dark:text-gray-400">Разрешить ввод дробных количеств при использовании этой единицы (например, 1.5 кг).</p>
                </div>
            </div>

            <!-- Кнопки -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a href="/erp227_units" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-700 dark:text-white dark:border-gray-600 dark:hover:bg-gray-600 dark:focus:ring-gray-700">
                    Отмена
                </a>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-800">
                    <i class="fas fa-save mr-2"></i> Сохранить
                </button>
            </div>
        </form>
    </div>
</div>