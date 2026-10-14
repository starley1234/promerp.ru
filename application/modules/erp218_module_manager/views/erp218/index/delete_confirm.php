<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Подтверждение мягкого удаления модуля (erp218).
 *
 * Ожидает: $module (Model_Erp218_Module).
 * См. Controller_Erp218_Index::action_delete().
 */
?>
<div class="mx-auto max-w-xl">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-800 p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 shrink-0 rounded-lg bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-600 dark:text-red-400">
                <i class="fas fa-trash text-xl"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    Удалить модуль <?php echo HTML::chars($module->display_name); ?>?
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Модуль <span class="font-mono"><?php echo HTML::chars($module->system_name); ?></span>
                    (версия <?php echo HTML::chars($module->version); ?>) будет помечен как удалённый.
                    Запись можно восстановить из списка удалённых.
                </p>

                <form method="post" action="<?php echo URL::site('erp218_index/delete/'.$module->id); ?>" class="mt-5 flex justify-end gap-3">
                    <?php echo Form::hidden('csrf_token', Security::token()); ?>
                    <a href="<?php echo URL::site('erp218_index/detail/'.$module->id); ?>"
                       class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300">
                        Отмена
                    </a>
                    <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium">
                        Удалить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
