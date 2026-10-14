<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Аналитика продаж: ключевые цифры, воронка, динамика, менеджеры, источники и отказы.
 *
 * Переменные: filters, overview, funnel, dynamics, managers, sources, reasons,
 * loss_comment, users, can_ai, messages.
 */
$money = function ($value) { return number_format((float) $value, 0, '.', ' '); };
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Аналитика продаж</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Конверсия — доля выигранных среди закрытых сделок. Прогноз — сумма в работе,
                взвешенная по вероятности стадии.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/crm/export" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                <i class="fas fa-download mr-1" aria-hidden="true"></i> Выгрузить CSV
            </a>
        </div>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <form method="get" action="/crm/analytics" class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start gap-3">
            <?php echo Component::render('form_field', array('id' => 'an_from', 'label' => 'С даты', 'name' => 'from', 'type' => 'date', 'value' => $filters['from'])); ?>
            <?php echo Component::render('form_field', array('id' => 'an_to', 'label' => 'По дату', 'name' => 'to', 'type' => 'date', 'value' => $filters['to'])); ?>
            <div class="space-y-1">
                <label for="an_manager" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Ответственный</label>
                <select id="an_manager" name="assigned_to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Все</option>
                    <?php foreach ($users as $id => $name): ?>
                        <option value="<?php echo (int) $id; ?>"<?php echo (int) $filters['assigned_to'] === (int) $id ? ' selected' : ''; ?>><?php echo HTML::chars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-700">Применить</button>
        </div>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <?php echo Component::render('kpi', array('label' => 'В работе', 'value' => $money($overview['open_count']), 'unit' => 'сделок', 'hint' => $money($overview['open_amount']).' ₽')); ?>
        <?php echo Component::render('kpi', array('label' => 'Прогноз', 'value' => $money($overview['forecast']), 'unit' => '₽', 'hint' => 'взвешено по вероятности')); ?>
        <?php echo Component::render('kpi', array('label' => 'Выиграно', 'value' => $money($overview['won_count']), 'unit' => 'сделок', 'state' => 'ok', 'hint' => $money($overview['won_amount']).' ₽')); ?>
        <?php echo Component::render('kpi', array('label' => 'Конверсия', 'value' => $overview['conversion'] === NULL ? '—' : $overview['conversion'], 'unit' => '%', 'state' => 'info', 'hint' => 'от закрытых сделок')); ?>
        <?php echo Component::render('kpi', array('label' => 'Средний чек', 'value' => $money($overview['average_deal']), 'unit' => '₽', 'hint' => 'по выигранным')); ?>
        <?php echo Component::render('kpi', array('label' => 'Цикл сделки', 'value' => $overview['cycle_days'], 'unit' => 'дн.', 'hint' => 'от создания до решения')); ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Воронка</h2></header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <caption class="sr-only">Воронка продаж</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Стадия</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сделок</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сумма</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Прогноз</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">От предыдущей</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($funnel as $row): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['label']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['count']; ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo $money($row['amount']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo $money($row['forecast']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400"><?php echo $row['conversion'] === NULL ? '—' : $row['conversion'].' %'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Динамика по месяцам</h2></header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <caption class="sr-only">Динамика по месяцам</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Месяц</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Создано</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Выиграно</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if ( ! $dynamics): ?><tr><td colspan="4" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Данных за период нет.</td></tr><?php endif; ?>
                        <?php foreach ($dynamics as $row): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['month']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['created']; ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['won']; ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo $money($row['won_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Менеджеры</h2></header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <caption class="sr-only">Работа менеджеров</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Сотрудник</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">В работе</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Выиграно</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Конверсия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($managers as $row): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['username']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['open_count']; ?> · <?php echo $money($row['open_amount']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['won_count']; ?> · <?php echo $money($row['won_amount']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400"><?php echo $row['conversion'] === NULL ? '—' : $row['conversion'].' %'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Источники</h2></header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <caption class="sr-only">Источники обращений</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Источник</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сделок</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Выиграно, ₽</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if ( ! $sources): ?><tr><td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Источники не указаны.</td></tr><?php endif; ?>
                        <?php foreach ($sources as $row): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['source']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['count']; ?></td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo $money($row['won_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Причины отказов</h2>
            <?php if ($can_ai): ?>
                <form method="post" action="/crm/loss_analysis" class="flex items-center gap-2">
                    <input type="hidden" name="from" value="<?php echo HTML::chars($filters['from']); ?>">
                    <button type="submit" class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700">Разобрать причины (ИИ)</button>
                </form>
            <?php endif; ?>
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <caption class="sr-only">Причины отказов</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Причина</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сделок</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Сумма</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if ( ! $reasons): ?><tr><td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Отказов за период нет.</td></tr><?php endif; ?>
                    <?php foreach ($reasons as $row): ?>
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['label']); ?></td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo (int) $row['count']; ?></td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300"><?php echo $money($row['amount']); ?> ₽</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-200 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Разбор моделью — подсказка, а не приказ: решение по клиенту принимает человек.
        </p>
    </section>
</div>
