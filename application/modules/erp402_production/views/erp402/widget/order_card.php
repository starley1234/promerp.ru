<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-center justify-between mb-2">
        <a href="/production/orders/view/<?php echo (int) $order->id; ?>" class="font-mono text-sm text-blue-600 hover:text-blue-800">
            <?php echo HTML::chars($order->number); ?>
        </a>
        <span class="px-2 py-1 rounded text-xs <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
    </div>

    <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($order->title); ?></div>

    <div class="mt-3 w-full h-2 bg-gray-200 dark:bg-gray-700 rounded">
        <div class="h-2 rounded bg-blue-600" data-progress="<?php echo (float) $progress; ?>"></div>
    </div>

    <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span><?php echo number_format((float) $order->qty_done, 0, '.', ' '); ?> / <?php echo number_format((float) $order->qty, 0, '.', ' '); ?> <?php echo HTML::chars($order->uom_code); ?></span>
        <?php if ($order->target_date): ?><span>срок <?php echo HTML::chars($order->target_date); ?></span><?php endif; ?>
    </div>

    <?php if ($summary !== NULL AND ($summary['short'] > 0 OR $summary['unmapped'] > 0)): ?>
        <div class="mt-2 text-xs text-red-600 dark:text-red-400">
            не хватает материалов по <?php echo (int) $summary['short']; ?> стр.,
            без номенклатуры — <?php echo (int) $summary['unmapped']; ?>
        </div>
    <?php endif; ?>
</div>
