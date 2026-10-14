<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-sm">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-box-open mr-2 text-blue-600"></i> Карантин
        </h3>
        <a href="/quality/quarantine" class="text-xs text-blue-600 hover:text-blue-800">подробнее</a>
    </div>

    <?php if (count($rows) === 0): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Изолированных партий нет.</p>
    <?php else: ?>
        <ul class="space-y-1">
            <?php foreach ($rows as $row): ?>
                <li class="flex items-center justify-between text-xs">
                    <a href="/quality/inspections/view/<?php echo (int) $row['id']; ?>" class="text-blue-600 hover:text-blue-800 truncate">
                        <?php echo HTML::chars($row['item_name']); ?>
                    </a>
                    <span class="text-gray-700 dark:text-gray-200 whitespace-nowrap"><?php echo (float) $row['quarantine_qty']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
