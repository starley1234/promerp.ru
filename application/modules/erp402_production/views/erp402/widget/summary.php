<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-2 text-blue-600"></i> Производство
        </h3>
        <a href="/production" class="text-xs text-blue-600 hover:text-blue-800">подробнее</a>
    </div>

    <div class="grid grid-cols-3 gap-3 text-center">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">В работе</div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['open']; ?></div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Просрочено</div>
            <div class="text-xl font-semibold <?php echo $stats['overdue'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo (int) $stats['overdue']; ?>
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Выпуск</div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($stats['qty_done'], 0, '.', ' '); ?></div>
        </div>
    </div>

    <?php if ($stats['short_lines'] > 0): ?>
        <div class="mt-3 text-xs text-red-600 dark:text-red-400">
            <i class="fas fa-exclamation-triangle mr-1"></i> строк без остатка: <?php echo (int) $stats['short_lines']; ?>
        </div>
    <?php endif; ?>
</div>
