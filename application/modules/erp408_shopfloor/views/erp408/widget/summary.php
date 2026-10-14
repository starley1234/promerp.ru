<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-1 text-blue-600"></i> Цех · <?php echo HTML::chars($day); ?>
        </h3>
        <a href="/shopfloor" class="text-sm text-blue-600 hover:text-blue-800">Открыть</a>
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Операций на день</div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $board['totals']['operations']; ?></div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Плановых часов</div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($board['totals']['hours'], 1, '.', ' '); ?></div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">За 7 дней: годных</div>
            <div class="text-xl font-semibold text-green-700 dark:text-green-400"><?php echo number_format(isset($stats['qty_ok']) ? $stats['qty_ok'] : 0, 0, '.', ' '); ?></div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Брак</div>
            <div class="text-xl font-semibold <?php echo ( ! empty($stats['qty_scrap'])) ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo number_format(isset($stats['qty_scrap']) ? $stats['qty_scrap'] : 0, 0, '.', ' '); ?>
            </div>
        </div>
    </div>
</div>
