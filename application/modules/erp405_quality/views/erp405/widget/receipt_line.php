<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="border rounded-lg p-3 text-sm">
    <div class="flex items-center justify-between mb-2">
        <span class="font-medium text-gray-900 dark:text-white">
            <i class="fas fa-clipboard-check mr-1 text-green-600"></i> Контроль качества
        </span>
        <?php if ($line_id): ?>
            <a href="/quality/inspections" class="text-xs text-blue-600 hover:text-blue-800">все контроли</a>
        <?php endif; ?>
    </div>

    <?php if ( ! $line_id OR count($inspections) === 0): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Контроль по этой позиции не заведён. Входной контроль создаётся из приёмки:
            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">POST /quality/api/inspection_from_receipt</code> с <code>line_id</code>.
        </p>
    <?php else: ?>
        <ul class="space-y-1">
            <?php foreach ($inspections as $inspection): ?>
                <li class="flex items-center justify-between text-xs">
                    <a href="/quality/inspections/view/<?php echo (int) $inspection->id; ?>" class="text-blue-600 hover:text-blue-800">
                        <?php echo HTML::chars($inspection->number); ?>
                    </a>
                    <span class="px-2 py-0.5 rounded <?php echo $inspection->status_class(); ?>"><?php echo HTML::chars($inspection->status_label()); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
