<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-start justify-between gap-2 mb-3">
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">
                Операция <?php echo (int) $operation['op_no']; ?> · <?php echo HTML::chars($operation['name']); ?>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                <?php echo HTML::chars($operation['order_number']); ?>
                <?php if ($operation['work_center_label']): ?> · <?php echo HTML::chars($operation['work_center_label']); ?><?php endif; ?>
                · <?php echo HTML::chars((string) $operation['planned_day']); ?>
            </p>
        </div>
        <span class="px-2 py-1 rounded-full text-xs whitespace-nowrap <?php echo $operation['status_class']; ?>">
            <?php echo HTML::chars($operation['status_label']); ?>
        </span>
    </div>

    <div class="flex items-center justify-between text-sm mb-1">
        <span class="text-gray-500 dark:text-gray-400">Годных</span>
        <span class="text-gray-900 dark:text-white">
            <strong><?php echo number_format($operation['qty_ok'], 0, '.', ' '); ?></strong> из <?php echo number_format($operation['qty_planned'], 0, '.', ' '); ?>
            <?php if ($operation['qty_scrap'] > 0): ?>
                <span class="text-red-600">(брак <?php echo number_format($operation['qty_scrap'], 0, '.', ' '); ?>)</span>
            <?php endif; ?>
        </span>
    </div>

    <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full">
        <div class="h-2 rounded-full <?php echo $operation['progress'] >= 100 ? 'bg-green-500' : 'bg-blue-500'; ?>"
             data-progress="<?php echo min(100, (int) $operation['progress']); ?>"></div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
        Работа <?php echo number_format($operation['worked_hours'], 2, '.', ' '); ?> ч
        <?php if ($operation['downtime_hours'] > 0): ?> · простой <?php echo number_format($operation['downtime_hours'], 2, '.', ' '); ?> ч<?php endif; ?>
        <?php if ($operation['is_downtime']): ?> · <span class="text-orange-600">простой идёт сейчас</span><?php endif; ?>
    </div>

    <?php if ( ! empty($logs)): ?>
        <ul class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 space-y-1 text-xs text-gray-500 dark:text-gray-400">
            <?php foreach (array_slice($logs, 0, 3) as $log): ?>
                <li>
                    <span class="text-gray-700 dark:text-gray-200"><?php echo HTML::chars($log['kind_label']); ?></span>
                    · <?php echo HTML::chars(substr((string) $log['at'], 11, 5)); ?>
                    <?php if ($log['qty_ok']): ?> · годных <?php echo number_format($log['qty_ok'], 0, '.', ' '); ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <a href="/shopfloor/operations/view/<?php echo (int) $operation['id']; ?>" class="inline-block mt-3 text-sm text-blue-600 hover:text-blue-800">
        Открыть операцию
    </a>
</div>
