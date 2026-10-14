<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-industry mr-2 text-indigo-600"></i> Производство: OEE и простои
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                OEE = доступность × производительность × качество. Рядом — план/факт и Парето простоев: с чего начинать разбор.
            </p>
        </div>
        <?php if ($can_export): ?>
            <a href="/analytics/export/production?<?php echo http_build_query(array_filter($filters, function ($value) { return $value !== NULL AND $value !== ''; })); ?>"
               class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/analytics/production" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
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

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">OEE</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">
                    <?php echo $panel['oee'] === NULL ? '—' : HTML::chars((string) $panel['oee']); ?><span class="text-base text-gray-400"> %</span>
                </div>
                <div class="text-xs text-gray-400 mb-2">цель <?php echo HTML::chars((string) $panel['target']); ?> %</div>
                <span class="inline-block px-2 py-0.5 rounded text-xs <?php echo $state['class']; ?>"><?php echo HTML::chars($state['label']); ?></span>
            </div>

            <?php
            $parts = array(
                array('Доступность', $panel['availability'], 'работа / (работа + простои)'),
                array('Производительность', $panel['performance'], 'норма такта против факта'),
                array('Качество', $panel['quality'], 'годное / выпуск'),
            );
            ?>
            <?php foreach ($parts as $part): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo HTML::chars($part[0]); ?></div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        <?php echo $part[1] === NULL ? '—' : HTML::chars((string) $part[1]); ?><span class="text-sm text-gray-400"> %</span>
                    </div>
                    <div class="text-xs text-gray-400"><?php echo HTML::chars($part[2]); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($panel['notes']): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-900 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200 rounded-md px-4 py-3 mb-6 text-xs space-y-1">
                <?php foreach ($panel['notes'] as $note): ?>
                    <div><i class="fas fa-info-circle mr-1"></i> <?php echo HTML::chars($note); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-3">План и факт</div>
                <dl class="text-sm space-y-2 text-gray-700 dark:text-gray-200">
                    <div class="flex justify-between">
                        <dt>Выпуск годного к плану</dt>
                        <dd>
                            <?php echo HTML::chars((string) $panel['plan_fact']['qty_ok']); ?> /
                            <?php echo HTML::chars((string) $panel['plan_fact']['qty_planned']); ?>
                            <span class="text-gray-400">(<?php echo $panel['plan_fact']['qty_complete'] === NULL ? '—' : HTML::chars((string) $panel['plan_fact']['qty_complete']).' %'; ?>)</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Часы работы к плановым</dt>
                        <dd>
                            <?php echo HTML::chars((string) $panel['plan_fact']['worked_hours']); ?> /
                            <?php echo HTML::chars((string) $panel['plan_fact']['planned_hours']); ?>
                            <span class="text-gray-400">(<?php echo $panel['plan_fact']['hours_complete'] === NULL ? '—' : HTML::chars((string) $panel['plan_fact']['hours_complete']).' %'; ?>)</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Брак</dt>
                        <dd><?php echo HTML::chars((string) $panel['plan_fact']['qty_scrap']); ?> ед.</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>С опозданием</dt>
                        <dd>
                            <?php echo (int) $panel['plan_fact']['late']; ?> из <?php echo (int) $panel['plan_fact']['done']; ?>
                            <span class="text-gray-400">(<?php echo $panel['plan_fact']['late_share'] === NULL ? '—' : HTML::chars((string) $panel['plan_fact']['late_share']).' %'; ?>)</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Парето простоев</div>
                <?php if ( ! $panel['pareto']): ?>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Простоев за период не зафиксировано.</div>
                <?php else: ?>
                    <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="text-xs text-gray-400">
                            <tr>
                                <th scope="col" class="text-left">Причина</th>
                                <th scope="col" class="text-right">Событий</th>
                                <th scope="col" class="text-right">Часов</th>
                                <th scope="col" class="text-right">Доля</th>
                                <th scope="col" class="text-right">Накоплено</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-200">
                            <?php foreach ($panel['pareto'] as $row): ?>
                                <tr>
                                    <td class="text-left"><?php echo HTML::chars($row['reason_label']); ?></td>
                                    <td class="text-right"><?php echo (int) $row['events']; ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['hours']); ?></td>
                                    <td class="text-right"><?php echo HTML::chars((string) $row['share']); ?> %</td>
                                    <td class="text-right text-gray-400"><?php echo HTML::chars((string) $row['share_total']); ?> %</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Разрез по рабочим центрам</div>
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th scope="col" class="text-left px-4 py-2">Центр</th>
                        <th scope="col" class="text-right px-4 py-2">Операций</th>
                        <th scope="col" class="text-right px-4 py-2">Годное</th>
                        <th scope="col" class="text-right px-4 py-2">Брак</th>
                        <th scope="col" class="text-right px-4 py-2">Простои, ч</th>
                        <th scope="col" class="text-right px-4 py-2">Доступность</th>
                        <th scope="col" class="text-right px-4 py-2">Производительность</th>
                        <th scope="col" class="text-right px-4 py-2">Качество</th>
                        <th scope="col" class="text-right px-4 py-2">OEE</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php if ( ! $panel['by_center']): ?>
                        <tr><td colspan="9" class="px-4 py-4 text-gray-500 dark:text-gray-400">Операций за период нет.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($panel['by_center'] as $row): ?>
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-white"><?php echo HTML::chars($row['work_center_label']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo (int) $row['operations']; ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_ok']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_scrap']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo round((int) $row['downtime_sec'] / 3600, 2); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['availability'] === NULL ? '—' : HTML::chars((string) $row['availability']).' %'; ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['performance'] === NULL ? '—' : HTML::chars((string) $row['performance']).' %'; ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['quality'] === NULL ? '—' : HTML::chars((string) $row['quality']).' %'; ?></td>
                            <td class="px-4 py-2 text-right font-semibold"><?php echo $row['oee'] === NULL ? '—' : HTML::chars((string) $row['oee']).' %'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 border-t dark:border-gray-700">
                Доступность считается от учтённого времени операций (работа + простои), а не от календаря: планового фонда времени система не знает.
                Производительность выше 100 % означает, что норма оказалась пессимистичнее факта — это повод пересмотреть норму, а не рекорд.
            </div>
        </div>
    <?php endif; ?>
</div>
