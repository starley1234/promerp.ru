<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Добавление записи в историю версий модуля (erp218).
 *
 * Ожидает: $module (Model_Erp218_Module).
 * См. Controller_Erp218_Index::action_add_version_history().
 */
?>
<div class="mx-auto max-w-2xl">
    <div class="mb-5">
        <a href="<?php echo URL::site('erp218_index/detail/'.$module->id); ?>" class="text-sm text-blue-600 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> К модулю
        </a>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-1">
            Новая версия модуля <?php echo HTML::chars($module->display_name); ?>
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Текущая версия: <span class="font-mono"><?php echo HTML::chars($module->version); ?></span>
        </p>
    </div>

    <form method="post" class="space-y-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <?php echo Form::hidden('csrf_token', Security::token()); ?>

        <div>
            <label for="version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Версия <span class="text-red-500">*</span>
            </label>
            <input type="text" name="version" id="version" required maxlength="50" placeholder="1.2.0"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2 font-mono">
        </div>

        <div>
            <label for="release_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Дата релиза
            </label>
            <input type="date" name="release_date" id="release_date" value="<?php echo date('Y-m-d'); ?>"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2">
        </div>

        <div>
            <label for="change_log" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Список изменений <span class="text-red-500">*</span>
            </label>
            <textarea name="change_log" id="change_log" rows="6" required
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2"
                      placeholder="— что добавлено&#10;— что исправлено"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?php echo URL::site('erp218_index/detail/'.$module->id); ?>"
               class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300">Отмена</a>
            <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium">
                Добавить в историю
            </button>
        </div>
    </form>
</div>
