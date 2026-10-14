<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-sm">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-truck-loading mr-2 text-blue-600"></i> Приёмка заказа
        </h3>
        <?php if ($order): ?>
            <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                <?php echo HTML::chars($order->number); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ( ! $order OR ! $progress): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Заказ не найден или по нему ещё нет позиций.</p>
    <?php else: ?>
        <div class="flex items-center gap-3 mb-2">
            <div class="flex-1">
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" data-progress="<?php echo (int) $progress['progress']; ?>"></div>
                </div>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                <?php echo (float) $progress['qty_received']; ?> / <?php echo (float) $progress['qty']; ?> (<?php echo (int) $progress['progress']; ?> %)
            </div>
        </div>

        <div class="flex items-center justify-between text-xs">
            <span class="px-2 py-1 rounded <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
            <span class="<?php echo $order->is_overdue() ? 'text-red-600 font-medium' : 'text-gray-500 dark:text-gray-400'; ?>">
                поставка <?php echo $order->delivery_date ? HTML::chars(date('d.m.Y', strtotime($order->delivery_date))) : '—'; ?>
            </span>
        </div>

        <?php if ( ! empty($receipts)): ?>
            <ul class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-1">
                <?php foreach ($receipts as $receipt): ?>
                    <li class="flex items-center justify-between text-xs">
                        <a href="/procurement/receipts/view/<?php echo (int) $receipt->id; ?>" class="font-mono text-blue-600 hover:text-blue-800">
                            <?php echo HTML::chars($receipt->number); ?>
                        </a>
                        <span class="text-gray-500 dark:text-gray-400">
                            <?php echo HTML::chars($receipt->status_label()); ?>
                            <?php echo $receipt->received_at ? ' · '.HTML::chars(date('d.m.Y', strtotime($receipt->received_at))) : ''; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>
