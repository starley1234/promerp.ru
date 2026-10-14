<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2 text-indigo-600"></i> Качество: брак и причины
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Доля забракованных контролей против цели и Парето причин: где теряем годное — у поставщика, в цехе или на складе.
            </p>
        </div>
        <?php if ($can_export): ?>
            <a href="/analytics/export/quality?<?php echo http_build_query(array_filter($filters, function ($value) { return $value !== NULL AND $value !== ''; })); ?>"
               class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/analytics/quality" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">С</label>
            <input type="date" name="from" value="<?php echo HTML::chars(isset($filters['from']) ? $filters['from'] : ''); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">По</label>
            <input type="date" name="to" value="<?php echo HTML::chars(isset($filters['to']) ? $filters['to'] : ''); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div class="flex items-end">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                <i class="fas fa-sync-alt mr-1"></i> Пересчитать
            </button>
        </div>
    </form>

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

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
            <?php
            $summary = $panel['summary'];
            $cards = array(
                array('Контролей всего', isset($summary['total']) ? (int) $summary['total'] : 0, 'text-gray-900 dark:text-white'),
                array('Не прошли', isset($summary['failed']) ? (int) $summary['failed'] : 0, 'text-red-600'),
                array('Прошли', isset($summary['passed']) ? (int) $summary['passed'] : 0, 'text-green-600'),
                array('Открытых', isset($summary['open']) ? (int) $summary['open'] : 0, 'text-amber-600'),
                array('Ждут решения', isset($summary['pending']) ? (int) $summary['pending'] : 0, 'text-amber-700'),
                array('Брака, ед.', isset($summary['defect_qty']) ? $summary['defect_qty'] : 0, 'text-red-700'),
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
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Доля забракованных контролей за период</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                    <?php echo $panel['defect_share'] === NULL ? '—' : HTML::chars((string) $panel['defect_share']); ?><span class="text-sm text-gray-400"> %</span>
                </div>
                <div class="text-xs text-gray-400">цель: не больше <?php echo HTML::chars((string) $panel['target']); ?> %</div>
            </div>
            <span class="px-3 py-1 rounded text-sm <?php echo $state['class']; ?>"><?php echo HTML::chars($state['label']); ?></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Парето по причинам</div>
                <?php if ( ! $panel['cause_pareto']): ?>
                    <div class="text-sm text-gray-500 dark:text-gray-400">За период дефектов не зафиксировано.</div>
                <?php else: ?>
                    <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="text-xs text-gray-400">
                            <tr>
                                <th scope="col" class="text-left">Причина</th>
                                <th scope="col" class="text-right">Записей</th>
                                <th scope="col" class="text-right">Количество</th>
                                <th scope="col" class="text-right">Доля</th>
                                <th scope="col" class="text-right">Накоплено</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-200">
                            <?php foreach ($panel['cause_pareto'] as $row): ?>
                                <tr>
                                    <td class="text-left"><?php echo HTML::chars($row['label']); ?></td>
                                    <td class="text-right"><?php echo $row['records'] === NULL ? '—' : (int) $row['records']; ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['qty']); ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['share']); ?> %</td>
                                    <td class="text-right text-gray-400"><?php echo HTML::chars((string) $row['share_total']); ?> %</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Парето по кодам дефектов</div>
                <?php if ( ! $panel['code_pareto']): ?>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Кодов дефектов за период нет.</div>
                <?php else: ?>
                    <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="text-xs text-gray-400">
                            <tr>
                                <th scope="col" class="text-left">Код</th>
                                <th scope="col" class="text-left">Категория</th>
                                <th scope="col" class="text-right">Количество</th>
                                <th scope="col" class="text-right">Доля</th>
                                <th scope="col" class="text-right">Накоплено</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-200">
                            <?php foreach ($panel['code_pareto'] as $row): ?>
                                <tr>
                                    <td class="text-left"><?php echo HTML::chars($row['label']); ?></td>
                                    <td class="text-left text-xs text-gray-400"><?php echo HTML::chars((string) $row['category']); ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['qty']); ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['share']); ?> %</td>
                                    <td class="text-right text-gray-400"><?php echo HTML::chars((string) $row['share_total']); ?> %</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mt-6">
            Доля считается от контролей, а не от выпуска: партии качества и штуки выпуска не свести без общего ключа, поэтому метрика названа честно.
            Подробности и решения по браку — в модуле качества (erp405).
        </p>
    <?php endif; ?>
</div>
