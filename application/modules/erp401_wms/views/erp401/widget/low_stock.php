<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i> Остатки ниже минимума
        </h3>
        <a href="/warehouse/balances?low_only=1" class="text-xs text-blue-600 hover:underline">все</a>
    </div>

    <?php if (empty($rows)): ?>
        <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Все позиции выше минимума.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php foreach ($rows as $row): ?>
                <li class="px-4 py-2 flex items-center justify-between text-sm">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($row['item']['code']); ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($row['warehouse_name']); ?></div>
                    </div>
                    <div class="text-right">
                        <span class="font-semibold text-red-600 dark:text-red-400"><?php echo number_format((float) $row['qty'], 0, '.', ' '); ?></span>
                        <span class="text-xs text-gray-500">/ <?php echo number_format((float) $row['min_qty'], 0, '.', ' '); ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
