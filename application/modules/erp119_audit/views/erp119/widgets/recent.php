<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Последние действия</h3>
        <a href="/audit/journal" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">Вся лента</a>
    </div>

    <?php if (empty($rows)): ?>
        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Записей пока нет.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($rows as $row): ?>
                <li class="px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900 dark:text-white truncate">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs mr-2 <?php echo Erp119_Ui::action_tone($row['action']); ?>">
                                    <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
                                </span>
                                <?php echo HTML::chars($row['entity_label'] ? $row['entity_label'] : $journal->label_for($row['module'], $row['entity_type'])); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo HTML::chars($row['actor_name'] ? $row['actor_name'] : Erp119_Ui::source_label($row['source'])); ?>
                                · <?php echo HTML::chars(Erp119_Ui::ago($row['occurred_at'])); ?>
                            </p>
                        </div>
                        <a href="/audit/journal/view/<?php echo (int) $row['id']; ?>"
                           class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap">Открыть</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
