<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Позиций</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo (int) $positions; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Единиц на складах</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($qty, 0, '.', ' '); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Зарезервировано</p>
        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo number_format($reserved, 0, '.', ' '); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Ниже минимума</p>
        <p class="text-2xl font-bold <?php echo ($low_count > 0) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'; ?>">
            <?php echo (int) $low_count; ?>
        </p>
    </div>
</div>
