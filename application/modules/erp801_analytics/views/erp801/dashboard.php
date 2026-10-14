<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-chart-pie mr-2 text-indigo-600"></i> Аналитика: сводка
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                KPI завода одним экраном: запасы, производство, качество, закупки — с целями и тем, что требует внимания.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/analytics/snapshots" class="px-4 py-2 bg-white border text-gray-700 text-sm rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                <i class="fas fa-camera mr-1"></i> Снимки
            </a>
            <?php if ($can_export): ?>
                <a href="/analytics/export/summary?<?php echo http_build_query($filters); ?>" class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                    <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/analytics" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
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
        <div class="flex items-end text-xs text-gray-500 dark:text-gray-400">
            Пустой период — периоды по умолчанию: запасы за 90 дней, производство и качество за текущий месяц.
        </div>
    </form>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200 rounded-md px-4 py-3 mb-6 text-sm">
            <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo HTML::chars($error); ?>
        </div>
    <?php elseif ( ! $summary): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            Сводка не посчитана.
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <?php foreach ($summary['kpi'] as $kpi): ?>
                <?php $state = isset($states[$kpi['state']]) ? $states[$kpi['state']] : $states['unknown']; ?>
                <a href="<?php echo HTML::chars($kpi['url']); ?>" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:shadow-md transition">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo HTML::chars($kpi['title']); ?></div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                        <?php echo $kpi['value'] === NULL ? '—' : HTML::chars((string) $kpi['value']); ?>
                        <span class="text-xs text-gray-400"><?php echo HTML::chars($kpi['unit']); ?></span>
                    </div>
                    <div class="text-xs text-gray-400 mb-2">цель: <?php echo HTML::chars((string) $kpi['target']); ?> <?php echo HTML::chars($kpi['unit']); ?></div>
                    <span class="inline-block px-2 py-0.5 rounded text-xs <?php echo $state['class']; ?>"><?php echo HTML::chars($state['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-exclamation-circle mr-1 text-amber-500"></i> Требует внимания
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php if ( ! $summary['attention']): ?>
                        <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            Поводов для внимания нет: все показатели в пределах целей.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($summary['attention'] as $row): ?>
                        <?php $state = isset($states[$row['state']]) ? $states[$row['state']] : $states['unknown']; ?>
                        <a href="<?php echo HTML::chars($row['url']); ?>" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-900 dark:text-white"><?php echo HTML::chars($row['title']); ?></div>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $row['value']); ?></span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <span class="inline-block px-2 py-0.5 rounded mr-2 <?php echo $state['class']; ?>"><?php echo HTML::chars($state['label']); ?></span>
                                <?php echo HTML::chars($row['hint']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-plug mr-1 text-indigo-500"></i> Источники данных
                </div>
                <div class="divide-y dark:divide-gray-700">
                    <?php foreach ($summary['sources'] as $source): ?>
                        <div class="px-4 py-3 flex items-center justify-between text-sm">
                            <div>
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($source['title']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($source['for']); ?></div>
                            </div>
                            <?php if ($source['ok']): ?>
                                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">подключён</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">недоступен</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4">
            <?php foreach ($summary['blocks'] as $block): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2"><?php echo HTML::chars($block['title']); ?></div>

                    <?php if (empty($block['available'])): ?>
                        <div class="text-xs text-amber-700 dark:text-amber-300">
                            Источник недоступен (<?php echo HTML::chars(isset($block['reason']) ? $block['reason'] : 'unknown'); ?>):
                            <?php echo HTML::chars(isset($block['message']) ? $block['message'] : 'витрина не посчитана'); ?>
                        </div>
                    <?php else: ?>
                        <?php if ($block['code'] === 'stock'): ?>
                            <dl class="text-xs space-y-1 text-gray-600 dark:text-gray-300">
                                <div class="flex justify-between"><dt>Позиций с остатком</dt><dd><?php echo (int) $block['summary']['items']; ?></dd></div>
                                <div class="flex justify-between"><dt>Покрытие</dt><dd><?php echo $block['summary']['cover_days'] === NULL ? '—' : HTML::chars((string) $block['summary']['cover_days']).' дн.'; ?></dd></div>
                                <div class="flex justify-between"><dt>Мёртвый запас</dt><dd><?php echo HTML::chars((string) $block['summary']['dead_stock']); ?> ед.</dd></div>
                                <div class="flex justify-between"><dt>К дозаказу</dt><dd><?php echo (int) $block['deficit']['items']; ?> поз.</dd></div>
                            </dl>
                        <?php elseif ($block['code'] === 'production'): ?>
                            <dl class="text-xs space-y-1 text-gray-600 dark:text-gray-300">
                                <div class="flex justify-between"><dt>OEE</dt><dd><?php echo $block['summary']['oee'] === NULL ? '—' : HTML::chars((string) $block['summary']['oee']).' %'; ?></dd></div>
                                <div class="flex justify-between"><dt>Операций</dt><dd><?php echo (int) $block['summary']['operations']; ?></dd></div>
                                <div class="flex justify-between"><dt>Простои</dt><dd><?php echo round((int) $block['summary']['downtime_sec'] / 3600, 1); ?> ч</dd></div>
                                <div class="flex justify-between"><dt>План выпуска</dt><dd><?php echo $block['summary']['plan_complete'] === NULL ? '—' : HTML::chars((string) $block['summary']['plan_complete']).' %'; ?></dd></div>
                            </dl>
                        <?php elseif ($block['code'] === 'quality'): ?>
                            <dl class="text-xs space-y-1 text-gray-600 dark:text-gray-300">
                                <div class="flex justify-between"><dt>Контролей</dt><dd><?php echo (int) $block['summary']['total']; ?></dd></div>
                                <div class="flex justify-between"><dt>Не прошли</dt><dd><?php echo (int) $block['summary']['failed']; ?></dd></div>
                                <div class="flex justify-between"><dt>Доля брака</dt><dd><?php echo $block['summary']['defect_share'] === NULL ? '—' : HTML::chars((string) $block['summary']['defect_share']).' %'; ?></dd></div>
                                <div class="flex justify-between"><dt>Ждут решения</dt><dd><?php echo (int) $block['summary']['pending']; ?></dd></div>
                            </dl>
                        <?php elseif ($block['code'] === 'purchases'): ?>
                            <dl class="text-xs space-y-1 text-gray-600 dark:text-gray-300">
                                <div class="flex justify-between"><dt>Открытых заказов</dt><dd><?php echo (int) $block['summary']['open']; ?></dd></div>
                                <div class="flex justify-between"><dt>Просрочено</dt><dd><?php echo (int) $block['summary']['overdue']; ?></dd></div>
                                <div class="flex justify-between"><dt>Доля просрочки</dt><dd><?php echo HTML::chars((string) $block['summary']['overdue_share']); ?> %</dd></div>
                                <div class="flex justify-between"><dt>Сумма в работе</dt><dd><?php echo number_format((float) $block['summary']['amount_open'], 0, '.', ' '); ?></dd></div>
                            </dl>
                        <?php endif; ?>

                        <a href="<?php echo HTML::chars($block['url']); ?>" class="inline-block mt-3 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            Подробнее <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-xs text-gray-400 mt-6">
            Сводка посчитана: <?php echo HTML::chars($summary['generated_at']); ?>.
            Агрегаты берутся у владельцев данных; блок с выключенным источником помечен, а не показан нулями.
        </p>
    <?php endif; ?>
</div>
