<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
        <i class="fas fa-clipboard-list mr-2 text-blue-600"></i> Производственные заказы на изделие
    </h3>

    <?php if (empty($orders)): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">Заказов нет.</p>
    <?php else: ?>
        <ul class="text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <?php foreach ($orders as $row): ?>
                <li class="py-2 flex items-center justify-between">
                    <div>
                        <a href="/production/orders/view/<?php echo (int) $row['id']; ?>" class="font-mono text-blue-600 hover:text-blue-800">
                            <?php echo HTML::chars($row['number']); ?>
                        </a>
                        <span class="text-gray-900 dark:text-white ml-2"><?php echo HTML::chars($row['title']); ?></span>
                    </div>
                    <span class="px-2 py-1 rounded text-xs <?php echo Model_Erp402_Order::$status_classes[$row['status']]; ?>">
                        <?php echo HTML::chars(isset($statuses[$row['status']]) ? $statuses[$row['status']] : $row['status']); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total > count($orders)): ?>
            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Всего заказов: <?php echo (int) $total; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
