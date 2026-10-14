<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            История изменений
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(<?php echo (int) $total; ?>)</span>
        </h3>
        <a href="/audit/journal/entity?entity_type=<?php echo urlencode($entity_type); ?>&entity_id=<?php echo urlencode($entity_id); ?>"
           class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">Полная история</a>
    </div>

    <?php if (empty($rows)): ?>
        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Изменений не было.</p>
    <?php else: ?>
        <ol class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($rows as $row): ?>
                <li class="px-4 py-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::action_tone($row['action']); ?>">
                            <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900 dark:text-white">
                                <?php echo HTML::chars($row['message'] ? $row['message'] : Erp119_Ui::action_label($row['action'])); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo HTML::chars($row['actor_name'] ? $row['actor_name'] : Erp119_Ui::source_label($row['source'])); ?>
                                · <?php echo date('d.m.Y H:i', strtotime($row['occurred_at'])); ?>
                            </p>
                            <?php if ( ! empty($row['changes'])): ?>
                                <ul class="mt-2 space-y-1">
                                    <?php foreach ($row['changes'] as $change): ?>
                                        <li class="text-xs text-gray-600 dark:text-gray-300">
                                            <span class="font-mono"><?php echo HTML::chars($change['field']); ?></span>:
                                            <span class="text-red-600 dark:text-red-300"><?php echo HTML::chars(Erp119_Ui::value($change['old'], 60)); ?></span>
                                            →
                                            <span class="text-green-700 dark:text-green-300"><?php echo HTML::chars(Erp119_Ui::value($change['new'], 60)); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
