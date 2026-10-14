<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($card->title); ?></span>
        <a href="/tech_cards/view/<?php echo (int) $card->id; ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Открыть</a>
    </div>

    <?php if ( ! $document): ?>
        <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Техпроцесс не сформирован.</p>
    <?php else: ?>
        <table class="w-full text-left text-sm">
                    <caption class="sr-only">Таблица данных</caption>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($operations as $i => $operation): ?>
                    <tr>
                        <td class="px-4 py-2 text-gray-400 w-8"><?php echo $i + 1; ?></td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($operation->op_name); ?></td>
                        <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            <?php echo Model_Erp228_TechDocument::human_duration($operation->duration_sec); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
            Итого: <?php echo Model_Erp228_TechDocument::human_duration($document->duration_total()); ?> ·
            <?php echo number_format($document->cost_total(), 2, ',', ' '); ?> ₽
        </div>
    <?php endif; ?>
</div>
