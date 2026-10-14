<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-truck mr-2 text-indigo-600"></i> Закупки: сроки и просрочка
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Сколько заказов висит, что просрочено и что подходит к сроку — деньги, которые ещё не превратились в материалы.
            </p>
        </div>
        <?php if ($can_export): ?>
            <a href="/analytics/export/purchases?<?php echo http_build_query(array_filter($filters, function ($value) { return $value !== NULL AND $value !== ''; })); ?>"
               class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ($panel === NULL): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 rounded-md px-4 py-3 text-sm">
            Витрина не посчитана: источник данных недоступен.
            <?php foreach ($sources as $source): ?>
                <?php if ( ! $source['ok']): ?>
                    <div class="text-xs mt-1">Не подключён: <?php echo HTML::chars($source['title']); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php $state = isset($states[$panel['state']]) ? $states[$panel['state']] : $states['unknown']; ?>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <?php
            $stats = $panel['stats'];
            $cards = array(
                array('Всего заказов', isset($stats['total']) ? (int) $stats['total'] : 0, 'text-gray-900 dark:text-white'),
                array('Открытых', $panel['open'], 'text-blue-600'),
                array('Просрочено', $panel['overdue'], 'text-red-600'),
                array('Принято', isset($stats['received']) ? (int) $stats['received'] : 0, 'text-green-600'),
                array('Отменено', isset($stats['cancelled']) ? (int) $stats['cancelled'] : 0, 'text-gray-500'),
            );
            ?>
            <?php foreach ($cards as $card): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo HTML::chars($card[0]); ?></div>
                    <div class="text-xl font-semibold <?php echo $card[2]; ?>"><?php echo HTML::chars((string) $card[1]); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex items-center justify-between">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Доля просроченных среди открытых</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                    <?php echo HTML::chars((string) $panel['overdue_share']); ?><span class="text-sm text-gray-400"> %</span>
                </div>
                <div class="text-xs text-gray-400">
                    цель: не больше <?php echo HTML::chars((string) $panel['target']); ?> % ·
                    открытая сумма <?php echo number_format((float) (isset($stats['amount_open']) ? $stats['amount_open'] : 0), 2, '.', ' '); ?>
                </div>
            </div>
            <span class="px-3 py-1 rounded text-sm <?php echo $state['class']; ?>"><?php echo HTML::chars($state['label']); ?></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-hourglass-end mr-1 text-red-500"></i> Просроченные
                </div>
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="text-xs text-gray-400">
                        <tr>
                            <th scope="col" class="text-left px-4 py-2">Заказ</th>
                            <th scope="col" class="text-left px-4 py-2">Поставщик</th>
                            <th scope="col" class="text-right px-4 py-2">Дата поставки</th>
                            <th scope="col" class="text-right px-4 py-2">Просрочка</th>
                            <th scope="col" class="text-right px-4 py-2">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                        <?php if ( ! $panel['overdue_rows']): ?>
                            <tr><td colspan="5" class="px-4 py-4 text-gray-500 dark:text-gray-400">Просроченных заказов нет.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($panel['overdue_rows'] as $row): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="<?php echo HTML::chars($row['url']); ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline"><?php echo HTML::chars($row['number']); ?></a>
                                    <div class="text-xs text-gray-400"><?php echo HTML::chars($row['status_label']); ?></div>
                                </td>
                                <td class="px-4 py-2"><?php echo HTML::chars($row['supplier_name']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['delivery_date']); ?></td>
                                <td class="px-4 py-2 text-right text-red-600 font-semibold"><?php echo (int) $row['days_late']; ?> дн.</td>
                                <td class="px-4 py-2 text-right"><?php echo number_format((float) $row['amount'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-clock mr-1 text-amber-500"></i> Срок в ближайшие <?php echo (int) $panel['due_days']; ?> дней
                </div>
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="text-xs text-gray-400">
                        <tr>
                            <th scope="col" class="text-left px-4 py-2">Заказ</th>
                            <th scope="col" class="text-left px-4 py-2">Поставщик</th>
                            <th scope="col" class="text-right px-4 py-2">Дата поставки</th>
                            <th scope="col" class="text-right px-4 py-2">Осталось</th>
                            <th scope="col" class="text-right px-4 py-2">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                        <?php if ( ! $panel['due_rows']): ?>
                            <tr><td colspan="5" class="px-4 py-4 text-gray-500 dark:text-gray-400">В ближайшие дни поставок не ожидается.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($panel['due_rows'] as $row): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="<?php echo HTML::chars($row['url']); ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline"><?php echo HTML::chars($row['number']); ?></a>
                                    <div class="text-xs text-gray-400"><?php echo HTML::chars($row['status_label']); ?></div>
                                </td>
                                <td class="px-4 py-2"><?php echo HTML::chars($row['supplier_name']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['delivery_date']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo (int) $row['days_left']; ?> дн.</td>
                                <td class="px-4 py-2 text-right"><?php echo number_format((float) $row['amount'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
