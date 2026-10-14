<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-sm">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-2 text-blue-600"></i> Поставщики
            <?php if ($item_name): ?>
                <span class="text-gray-500 dark:text-gray-400 font-normal">— <?php echo HTML::chars($item_name); ?></span>
            <?php endif; ?>
        </h3>
        <?php if ($item_id): ?>
            <a href="/procurement/suppliers/compare?item_id=<?php echo (int) $item_id; ?>" class="text-xs text-blue-600 hover:text-blue-800">сравнить</a>
        <?php endif; ?>
    </div>

    <?php if ( ! $item_id): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Укажите номенклатуру — виджет покажет, у кого эта позиция дешевле и быстрее.</p>
    <?php elseif (empty($rows)): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Цен по позиции ещё нет. История наполняется отправленными заказами и проведёнными приёмками.
        </p>
    <?php else: ?>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php $rank = 0; ?>
            <?php foreach ($rows as $row): ?>
                <?php $rank++; ?>
                <li class="py-2 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <a href="/procurement/suppliers/view/<?php echo (int) $row['supplier_id']; ?>" class="text-blue-600 hover:text-blue-800 truncate block">
                            <?php echo HTML::chars($row['name']); ?>
                        </a>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            срок <?php echo (int) $row['lead_time']; ?> дн. · сделок <?php echo (int) $row['deals']; ?>
                            <?php if ($rank === 1): ?>
                                · <span class="text-green-600 dark:text-green-400">рекомендуем</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-gray-900 dark:text-white font-medium">
                            <?php echo number_format((float) $row['price'], 2, '.', ' '); ?> <?php echo HTML::chars($row['currency']); ?>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo (float) $row['score']; ?> б.</div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( ! empty($history)): ?>
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Последние цены</div>
                <ul class="space-y-1">
                    <?php foreach ($history as $price): ?>
                        <li class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-300">
                                <?php echo HTML::chars(date('d.m.Y', strtotime($price['dated_at']))); ?>
                                · <?php echo HTML::chars($price['supplier_name']); ?>
                            </span>
                            <span class="text-gray-900 dark:text-white"><?php echo number_format((float) $price['price'], 2, '.', ' '); ?> ₽</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
