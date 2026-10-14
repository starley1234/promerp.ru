<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-camera mr-2 text-indigo-600"></i> <?php echo HTML::chars($snapshot->title); ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Витрина «<?php echo HTML::chars(Model_Erp801_Snapshot::code_label((string) $snapshot->code)); ?>»,
                день отчёта <?php echo HTML::chars((string) $snapshot->day); ?>,
                снят <?php echo HTML::chars((string) $snapshot->created_at); ?>.
            </p>
        </div>
        <a href="/analytics/snapshots" class="px-4 py-2 bg-white border text-gray-700 text-sm rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
            <i class="fas fa-arrow-left mr-1"></i> К списку снимков
        </a>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 text-sm">
        <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Фильтры снимка</div>
        <?php $filters = $snapshot->filters(); ?>
        <?php if ( ! $filters): ?>
            <div class="text-gray-700 dark:text-gray-200">Период витрины по умолчанию</div>
        <?php else: ?>
            <div class="flex flex-wrap gap-3 text-gray-700 dark:text-gray-200">
                <?php foreach ($filters as $key => $value): ?>
                    <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-xs"><?php echo HTML::chars($key); ?>: <?php echo HTML::chars(is_array($value) ? json_encode($value) : (string) $value); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($snapshot->note): ?>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2"><?php echo HTML::chars((string) $snapshot->note); ?></div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">Что было в снимке</div>
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php
                    $payload = $snapshot->payload();
                    ?>
                    <?php if (isset($payload['kpi'])): ?>
                        <?php foreach ($payload['kpi'] as $kpi): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200"><?php echo HTML::chars($kpi['title']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-900 dark:text-white font-semibold">
                                    <?php echo $kpi['value'] === NULL ? '—' : HTML::chars((string) $kpi['value']); ?>
                                    <span class="text-xs text-gray-400"><?php echo HTML::chars($kpi['unit']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif (isset($payload['totals'])): ?>
                        <?php foreach ($payload['totals'] as $key => $value): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $key); ?></td>
                                <td class="px-4 py-2 text-right text-gray-900 dark:text-white"><?php echo HTML::chars(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td class="px-4 py-4 text-gray-500 dark:text-gray-400">Результат витрины сохранён целиком; откройте выгрузку, чтобы увидеть таблицу.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
                Отличия от предыдущего снимка
                <?php if ($previous): ?>
                    <span class="text-xs text-gray-400 ml-1">(<?php echo HTML::chars((string) $previous->day); ?>)</span>
                <?php endif; ?>
            </div>
            <?php if ( ! $previous): ?>
                <div class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                    Предыдущего снимка этой витрины нет — сравнивать не с чем.
                </div>
            <?php elseif ( ! $diff OR ! $diff['rows']): ?>
                <div class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                    Скалярные показатели не изменились.
                </div>
            <?php else: ?>
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="text-xs text-gray-400">
                        <tr>
                            <th scope="col" class="text-left px-4 py-2">Показатель</th>
                            <th scope="col" class="text-right px-4 py-2">Было</th>
                            <th scope="col" class="text-right px-4 py-2">Стало</th>
                            <th scope="col" class="text-right px-4 py-2">Изменение</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                        <?php foreach ($diff['rows'] as $row): ?>
                            <tr>
                                <td class="px-4 py-2"><?php echo HTML::chars($row['label']); ?></td>
                                <td class="px-4 py-2 text-right text-gray-400"><?php echo $row['before'] === NULL ? '—' : HTML::chars((string) $row['before']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo $row['after'] === NULL ? '—' : HTML::chars((string) $row['after']); ?></td>
                                <td class="px-4 py-2 text-right <?php echo ((float) $row['delta'] > 0) ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $row['delta'] === NULL ? '—' : HTML::chars((string) $row['delta']); ?>
                                    <?php if ($row['rate'] !== NULL): ?>
                                        <span class="text-xs text-gray-400">(<?php echo HTML::chars((string) $row['rate']); ?> %)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
