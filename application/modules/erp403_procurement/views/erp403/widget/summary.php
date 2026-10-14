<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-sm">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-shopping-cart mr-2 text-blue-600"></i> Закупки
        </h3>
        <a href="/procurement" class="text-xs text-blue-600 hover:text-blue-800">подробнее</a>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-3">
        <a href="/procurement/requests?status=submitted" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold text-gray-900 dark:text-white"><?php echo (int) $request_stats['submitted']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">ждут решения</div>
        </a>
        <a href="/procurement/orders" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold text-gray-900 dark:text-white"><?php echo (int) $order_stats['open']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">заказов в работе</div>
        </a>
        <a href="/procurement/receipts?status=draft" class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-center hover:bg-gray-100 dark:hover:bg-gray-600">
            <div class="text-lg font-bold text-gray-900 dark:text-white"><?php echo (int) $receipt_stats['draft']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">приёмок к проведению</div>
        </a>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
        <div>Заказано, но не пришло: <span class="text-gray-900 dark:text-white"><?php echo number_format((float) $order_stats['amount_open'], 0, '.', ' '); ?> ₽</span></div>
        <div>
            Просрочено: заявок <span class="<?php echo (int) $request_stats['overdue'] ? 'text-red-600 font-medium' : 'text-gray-900 dark:text-white'; ?>"><?php echo (int) $request_stats['overdue']; ?></span>,
            заказов <span class="<?php echo (int) $order_stats['overdue'] ? 'text-red-600 font-medium' : 'text-gray-900 dark:text-white'; ?>"><?php echo (int) $order_stats['overdue']; ?></span>
        </div>
        <?php if ((int) $receipt_stats['quality_blocked']): ?>
            <div class="text-red-600 font-medium">
                Заблокировано входным контролем: <?php echo (int) $receipt_stats['quality_blocked']; ?> поз.
            </div>
        <?php endif; ?>
    </div>

    <?php if ( ! empty($submitted)): ?>
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Ждут согласования</div>
            <ul class="space-y-1">
                <?php foreach ($submitted as $request): ?>
                    <li class="flex items-center justify-between text-xs">
                        <a href="/procurement/requests/view/<?php echo (int) $request->id; ?>" class="font-mono text-blue-600 hover:text-blue-800">
                            <?php echo HTML::chars($request->number); ?>
                        </a>
                        <span class="<?php echo $request->is_overdue() ? 'text-red-600' : 'text-gray-500 dark:text-gray-400'; ?>">
                            <?php echo $request->need_date ? HTML::chars(date('d.m.Y', strtotime($request->need_date))) : '—'; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ( ! empty($due_soon)): ?>
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Поставки на неделю</div>
            <ul class="space-y-1">
                <?php foreach ($due_soon as $order): ?>
                    <li class="flex items-center justify-between text-xs">
                        <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>" class="font-mono text-blue-600 hover:text-blue-800">
                            <?php echo HTML::chars($order->number); ?>
                        </a>
                        <span class="<?php echo $order->is_overdue() ? 'text-red-600' : 'text-gray-500 dark:text-gray-400'; ?>">
                            <?php echo $order->delivery_date ? HTML::chars(date('d.m.Y', strtotime($order->delivery_date))) : '—'; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
