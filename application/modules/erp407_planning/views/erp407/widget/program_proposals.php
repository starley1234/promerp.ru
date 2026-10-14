<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-tasks mr-1 text-blue-600"></i> Предложения программы
        </h3>
        <a href="/planning/proposals?program_id=<?php echo (int) $program_id; ?>" class="text-xs text-blue-600 hover:text-blue-800">Все</a>
    </div>

    <?php if (empty($proposals)): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Предложений нет.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-xs">
            <?php foreach ($proposals as $status => $group): ?>
                <?php foreach ($group as $proposal): ?>
                    <li class="py-2">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-gray-500 dark:text-gray-400"><?php echo HTML::chars($proposal->number); ?></span>
                            <span class="px-1.5 py-0.5 rounded <?php echo $proposal->status_class(); ?>">
                                <?php echo HTML::chars($proposal->status_label()); ?>
                            </span>
                        </div>
                        <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($proposal->name); ?></div>
                        <div class="text-gray-500 dark:text-gray-400">
                            <?php echo HTML::chars($proposal->kind_label()); ?>
                            <?php echo number_format((float) $proposal->qty, 3, '.', ' '); ?>
                            <?php echo HTML::chars($proposal->uom_code); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
