<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Доска продаж: сделки по стадиям воронки.
 *
 * Переменные: columns, stats, client_stats, tasks, stagnant, filters, users,
 * sources, can_manage, messages.
 */
$money = function ($value) {
    return number_format((float) $value, 0, '.', ' ');
};
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-table-columns mr-2 text-blue-600" aria-hidden="true"></i> Продажи
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Сделка — это договорённость с клиентом и её история: звонки, письма, встречи и задачи.
                Прогноз — сумма, взвешенная по вероятности стадии: рабочая оценка, а не бухгалтерская выручка.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/crm/deals" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <i class="fas fa-list mr-1" aria-hidden="true"></i> Список сделок
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <?php echo Component::render('kpi', array('label' => 'В работе', 'value' => $money($stats['open_count']), 'unit' => 'сделок', 'hint' => 'на сумму '.$money($stats['open_amount']).' ₽', 'url' => '/crm/deals?kind=open')); ?>
        <?php echo Component::render('kpi', array('label' => 'Прогноз', 'value' => $money($stats['forecast']), 'unit' => '₽', 'hint' => 'по вероятности стадий')); ?>
        <?php echo Component::render('kpi', array('label' => 'Выиграно', 'value' => $money($stats['won_count']), 'unit' => 'сделок', 'state' => 'ok', 'hint' => 'на сумму '.$money($stats['won_amount']).' ₽')); ?>
        <?php echo Component::render('kpi', array('label' => 'Отказы', 'value' => $money($stats['lost_count']), 'unit' => 'сделок', 'state' => $stats['lost_count'] ? 'warn' : 'neutral', 'hint' => 'причина видна в карточке')); ?>
        <?php echo Component::render('kpi', array('label' => 'Просрочено', 'value' => $money($stats['overdue']), 'unit' => 'сделок', 'state' => $stats['overdue'] ? 'bad' : 'ok', 'hint' => 'срок решения прошёл', 'url' => '/crm/deals?overdue=1')); ?>
        <?php echo Component::render('kpi', array('label' => 'Клиенты', 'value' => $money($client_stats['total']), 'unit' => 'чел.', 'hint' => 'новых за месяц: '.$money($client_stats['new_this_month']), 'url' => '/crm/clients')); ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        <section class="lg:col-span-2 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Воронка</h2>
                <a href="/crm/deals" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Все сделки →</a>
            </header>
            <div class="flex gap-3 overflow-x-auto p-4">
                <?php foreach ($columns as $column): ?>
                    <?php $stage = $column['stage']; ?>
                    <div class="min-w-[16rem] flex-1 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                <svg class="inline-block h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?php echo HTML::chars($stage['color'] ? $stage['color'] : '#94a3b8'); ?>"></circle></svg>
                                <?php echo HTML::chars($stage['label']); ?>
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo (int) $column['count']; ?></span>
                        </div>
                        <div class="space-y-2 p-3">
                            <?php if ( ! $column['deals']): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Пусто</p>
                            <?php endif; ?>
                            <?php foreach ($column['deals'] as $deal): ?>
                                <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="block rounded-lg border border-gray-200 bg-white p-3 hover:border-blue-400 dark:border-gray-700 dark:bg-gray-800">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['number']); ?></span>
                                        <?php if ($deal['is_overdue']): ?>
                                            <?php echo Component::render('badge', array('label' => 'Срок', 'state' => 'bad', 'title' => 'Срок решения прошёл')); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($deal['title']); ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['client']['name']); ?></div>
                                    <div class="mt-2 flex items-center justify-between text-xs">
                                        <span class="font-semibold text-gray-900 dark:text-white"><?php echo $money($deal['amount']); ?> <?php echo HTML::chars($deal['currency']); ?></span>
                                        <span class="text-gray-500 dark:text-gray-400"><?php echo (int) $deal['probability']; ?> %</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Мои задачи</h2>
                </header>
                <div class="p-5">
                    <?php if ( ! $tasks): ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Незакрытых задач нет.</p>
                    <?php else: ?>
                        <ul class="space-y-3">
                            <?php foreach (array_slice($tasks, 0, 8) as $task): ?>
                                <li class="text-sm">
                                    <a href="/crm/deal/<?php echo (int) $task['deal_id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        <?php echo HTML::chars($task['subject'] ? $task['subject'] : 'Задача'); ?>
                                    </a>
                                    <div class="text-xs <?php echo $task['is_overdue'] ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400'; ?>">
                                        <?php echo $task['scheduled_at'] ? HTML::chars($task['scheduled_at']) : ''; ?>
                                        <?php echo $task['deal_number'] ? ' · '.HTML::chars($task['deal_number']) : ''; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="/crm/reminders" class="mt-3 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400">Все напоминания →</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сделки без движения</h2>
                </header>
                <div class="p-5">
                    <?php if ( ! $stagnant): ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Все сделки в работе двигаются — застоя нет.</p>
                    <?php else: ?>
                        <ul class="space-y-3">
                            <?php foreach ($stagnant as $deal): ?>
                                <li class="text-sm">
                                    <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($deal['title']); ?></a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['client']['name']); ?> · <?php echo (int) $deal['stale_days']; ?> дн. без записей</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
