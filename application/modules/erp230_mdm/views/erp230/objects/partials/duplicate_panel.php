<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Панель отображения найденных дубликатов при создании/редактировании объекта.
 * 
 * @var array $candidates Список кандидатов из DuplicateGuard::check()
 */

if (empty($candidates)) {
    return;
}
?>

<div id="duplicate-panel" class="mb-6 overflow-hidden rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/10">
    <div class="flex items-center gap-3 border-b border-amber-200 px-4 py-3 dark:border-amber-900/50">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
            <i class="fas fa-copy"></i>
        </div>
        <div>
            <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                Обнаружены похожие объекты (<?php echo count($candidates); ?>)
            </h3>
            <p class="text-xs text-amber-700 dark:text-amber-400">
                Пожалуйста, проверьте список ниже перед созданием новой записи, чтобы избежать дублирования данных.
            </p>
        </div>
    </div>

    <div class="max-h-64 overflow-y-auto">
        <table class="min-w-full divide-y divide-amber-200 dark:divide-amber-900/30">
                    <caption class="sr-only">Таблица данных</caption>
            <thead class="bg-amber-100/50 dark:bg-amber-900/20">
                <tr>
                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-amber-800 dark:text-amber-300">Мастер-код</th>
                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-amber-800 dark:text-amber-300">Наименование</th>
                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-amber-800 dark:text-amber-300">Совпадение</th>
                    <th scope="col" class="px-4 py-2 text-right text-xs font-semibold text-amber-800 dark:text-amber-300">Действие</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-amber-200 dark:divide-amber-900/30">
                <?php foreach ($candidates as $item): ?>
                    <tr class="hover:bg-amber-100/30 dark:hover:bg-amber-900/20 transition-colors">
                        <td class="whitespace-nowrap px-4 py-2 text-sm font-mono font-medium text-amber-900 dark:text-amber-200">
                            <?php echo HTML::chars($item['master_code']); ?>
                        </td>
                        <td class="px-4 py-2 text-sm text-amber-800 dark:text-amber-300">
                            <?php echo HTML::chars(isset($item['name']) ? $item['name'] : '—'); ?>
                        </td>
                        <td class="px-4 py-2 text-xs text-amber-700 dark:text-amber-400">
                            <span class="inline-flex items-center rounded bg-amber-200/50 px-1.5 py-0.5 dark:bg-amber-800/40">
                                <?php echo HTML::chars(isset($item['match_reason']) ? $item['match_reason'] : 'похожие атрибуты'); ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                            <a href="<?php echo URL::site('erp230_objects/view/' . $item['id']); ?>" 
                               target="_blank"
                               class="inline-flex items-center font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                Просмотр <i class="fas fa-external-link-alt ml-1.5 text-[10px]"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="bg-amber-100/30 px-4 py-2 dark:bg-amber-900/20">
        <p class="text-[11px] italic text-amber-600 dark:text-amber-500">
            * Если вы уверены, что это не дубликат, вы можете продолжить сохранение.
        </p>
    </div>
</div>