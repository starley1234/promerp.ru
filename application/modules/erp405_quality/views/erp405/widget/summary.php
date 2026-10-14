<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-sm">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-clipboard-check mr-2 text-green-600"></i> Качество
        </h3>
        <a href="/quality" class="text-xs text-blue-600 hover:text-blue-800">подробнее</a>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-3">
        <a href="/quality/inspections?result=failed" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold <?php echo ((int) $summary['failed'] > 0) ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>"><?php echo (int) $summary['failed']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">партий с браком</div>
        </a>
        <a href="/quality/inspections?pending=1" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold text-amber-600"><?php echo (int) $summary['pending']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">ждут решения</div>
        </a>
        <a href="/quality/quarantine" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold text-blue-600"><?php echo (float) $summary['quarantine_qty']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">на карантине</div>
        </a>
    </div>

    <?php if (count($pending) > 0): ?>
        <ul class="space-y-1">
            <?php foreach ($pending as $inspection): ?>
            <li class="flex items-center justify-between text-xs">
                <a href="/quality/inspections/view/<?php echo (int) $inspection->id; ?>" class="text-blue-600 hover:text-blue-800 truncate">
                    <?php echo HTML::chars($inspection->number); ?>
                </a>
                <span class="text-red-600 whitespace-nowrap"><?php echo (float) $inspection->defect_qty; ?> из <?php echo (float) $inspection->qty; ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Очередь решений пуста.</p>
    <?php endif; ?>
</div>
