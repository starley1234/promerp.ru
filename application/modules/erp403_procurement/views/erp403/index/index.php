<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-shopping-cart mr-2 text-blue-600"></i> Закупки
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Заявка → согласование → заказ поставщику → приёмка → приход на склад.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/procurement/low-stock"
               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50">
                <i class="fas fa-exclamation-triangle mr-2 text-amber-500"></i> Дефицит склада
            </a>
            <a href="/procurement/requests/edit"
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i> Новая заявка
            </a>
        </div>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <!-- Плитки состояний -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <a href="/procurement/requests?status=submitted" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Ждут согласования</div>
            <div class="text-2xl font-bold text-amber-600"><?php echo (int) $request_stats['submitted']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">заявок · <?php echo number_format((float) $approved_amount, 0, '.', ' '); ?> ₽ согласовано</div>
        </a>
        <a href="/procurement/requests?status=approved" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Согласованы, не заказаны</div>
            <div class="text-2xl font-bold text-green-600"><?php echo (int) $request_stats['approved']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">можно собрать заказ одной кнопкой</div>
        </a>
        <a href="/procurement/orders?open=1" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Заказы в работе</div>
            <div class="text-2xl font-bold text-blue-600"><?php echo (int) $order_stats['open']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                на <?php echo number_format((float) $order_stats['amount_open'], 0, '.', ' '); ?> ₽
                <?php if ((int) $order_stats['overdue']): ?>
                    · <span class="text-red-600 font-medium">просрочено <?php echo (int) $order_stats['overdue']; ?></span>
                <?php endif; ?>
            </div>
        </a>
        <a href="/procurement/receipts?status=draft" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Приёмки ждут проведения</div>
            <div class="text-2xl font-bold text-indigo-600"><?php echo (int) $receipt_stats['draft']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                за месяц принято на <?php echo number_format((float) $receipt_stats['amount_month'], 0, '.', ' '); ?> ₽
                <?php if ((int) $receipt_stats['quality_blocked']): ?>
                    · <span class="text-red-600 font-medium">брак: <?php echo (int) $receipt_stats['quality_blocked']; ?></span>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Что решить -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-file-alt mr-2 text-amber-500"></i> Заявки к решению</h2>
                <a href="/procurement/requests?status=submitted" class="text-sm text-blue-600 hover:text-blue-800">все</a>
            </div>
            <?php if (empty($submitted) AND empty($approved)): ?>
                <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                    Заявок нет. Позиции, дошедшие до минимума, можно превратить в заявки на странице
                    <a href="/procurement/low-stock" class="text-blue-600 hover:text-blue-800">«Дефицит склада»</a>.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach (array_merge($submitted, $approved) as $request): ?>
                        <li class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <a href="/procurement/requests/view/<?php echo (int) $request->id; ?>" class="font-mono text-blue-600 hover:text-blue-800 text-sm">
                                    <?php echo HTML::chars($request->number); ?>
                                </a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    нужен к <?php echo $request->need_date ? HTML::chars(date('d.m.Y', strtotime($request->need_date))) : '—'; ?>
                                    <?php if ($request->is_overdue()): ?>
                                        <span class="text-red-600 font-medium">· срок прошёл</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs <?php echo $request->status_class(); ?>"><?php echo HTML::chars($request->status_label()); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Поставки -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-truck mr-2 text-blue-500"></i> Поставки на подходе</h2>
                <a href="/procurement/orders?open=1" class="text-sm text-blue-600 hover:text-blue-800">все заказы</a>
            </div>
            <?php if (empty($due_soon) AND empty($overdue)): ?>
                <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                    Открытых заказов нет: либо всё принято, либо заказы ещё не оформлены.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach (array_merge($overdue, $due_soon) as $order): ?>
                        <li class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>" class="font-mono text-blue-600 hover:text-blue-800 text-sm">
                                    <?php echo HTML::chars($order->number); ?>
                                </a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    поставка <?php echo $order->delivery_date ? HTML::chars(date('d.m.Y', strtotime($order->delivery_date))) : '—'; ?>
                                    <?php if ($order->is_overdue()): ?>
                                        <span class="text-red-600 font-medium">· просрочена</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-900 dark:text-white"><?php echo number_format((float) $order->amount, 2, '.', ' '); ?> <?php echo HTML::chars($order->currency); ?></div>
                                <span class="px-2 py-1 rounded text-xs <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Минимум склада -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-boxes mr-2 text-amber-500"></i> Минимум склада</h2>
                <a href="/procurement/low-stock" class="text-sm text-blue-600 hover:text-blue-800">подробно</a>
            </div>
            <?php if (empty($low_stock)): ?>
                <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                    Все позиции выше минимума — закупать нечего.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($low_stock as $row): ?>
                        <?php
                            // Defensive fallback for old/custom stock adapters;
                            // the controller normally supplies normalized rows.
                            $item = (isset($row['item']) AND is_array($row['item'])) ? $row['item'] : array();
                            $item_name = isset($row['name']) ? (string) $row['name']
                                : (isset($item['name']) ? (string) $item['name'] : 'Позиция #'.(int) (isset($row['item_id']) ? $row['item_id'] : 0));
                        ?>
                        <li class="px-5 py-3 flex items-center justify-between text-sm">
                            <span class="text-gray-900 dark:text-white"><?php echo HTML::chars($item_name); ?></span>
                            <span class="text-gray-500 dark:text-gray-400">
                                <?php echo (float) (isset($row['qty']) ? $row['qty'] : 0); ?> из <?php echo (float) (isset($row['min_qty']) ? $row['min_qty'] : 0); ?>
                                <span class="text-xs">(<?php echo HTML::chars(isset($row['warehouse_name']) ? (string) $row['warehouse_name'] : ''); ?>)</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Поставщики -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-industry mr-2 text-gray-500"></i> Поставщики и цены</h2>
                <a href="/procurement/suppliers" class="text-sm text-blue-600 hover:text-blue-800">все</a>
            </div>
            <div class="px-5 py-4 grid grid-cols-3 gap-3 text-center border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo (int) $supplier_stats['suppliers']; ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">поставщиков</div>
                </div>
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo (int) $supplier_stats['preferred']; ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">предпочтительных</div>
                </div>
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo (int) $supplier_stats['priced_items']; ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">позиций с ценами</div>
                </div>
            </div>
            <?php if (empty($savings)): ?>
                <div class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
                    Пока нет позиций с ценами от двух поставщиков — сравнивать нечего.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($savings as $row): ?>
                        <li class="px-5 py-3 flex items-center justify-between text-sm">
                            <span class="text-gray-900 dark:text-white"><?php echo HTML::chars($row['name']); ?></span>
                            <span class="text-gray-500 dark:text-gray-400">
                                <?php echo number_format((float) $row['min_price'], 2, '.', ' '); ?> — <?php echo number_format((float) $row['max_price'], 2, '.', ' '); ?> ₽
                                <span class="text-xs text-green-600">−<?php echo (float) $row['diff_pct']; ?> %</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
