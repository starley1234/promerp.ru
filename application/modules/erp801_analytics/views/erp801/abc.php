<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-layer-group mr-2 text-indigo-600"></i> ABC/XYZ анализ запасов
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                ABC — что двигает склад по доле расхода; XYZ — насколько ровно это потребление идёт по месяцам.
            </p>
        </div>
        <?php if ($can_export): ?>
            <a href="/analytics/export/abc?<?php echo http_build_query(array_filter($filters, function ($value) { return $value !== NULL AND $value !== ''; })); ?>"
               class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/analytics/abc" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
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

    <?php if ($analysis === NULL): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 rounded-md px-4 py-3 text-sm">
            Витрина не посчитана: источник данных недоступен.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Период и объём</div>
                <dl class="text-sm space-y-1 text-gray-700 dark:text-gray-200">
                    <div class="flex justify-between"><dt>Период</dt><dd><?php echo HTML::chars($analysis['from']); ?> — <?php echo HTML::chars($analysis['to']); ?></dd></div>
                    <div class="flex justify-between"><dt>Месяцев наблюдений</dt><dd><?php echo count($analysis['months']); ?></dd></div>
                    <div class="flex justify-between"><dt>Позиций в анализе</dt><dd><?php echo (int) $analysis['totals']['items']; ?></dd></div>
                    <div class="flex justify-between"><dt>Расход всего</dt><dd><?php echo HTML::chars((string) $analysis['totals']['qty_out']); ?></dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Границы классов</div>
                <dl class="text-sm space-y-1 text-gray-700 dark:text-gray-200">
                    <div class="flex justify-between"><dt>Класс A</dt><dd>до <?php echo HTML::chars((string) $analysis['borders']['a']); ?> % расхода</dd></div>
                    <div class="flex justify-between"><dt>Класс B</dt><dd>до <?php echo HTML::chars((string) $analysis['borders']['b']); ?> % расхода</dd></div>
                    <div class="flex justify-between"><dt>Класс C</dt><dd>остальное</dd></div>
                    <div class="flex justify-between"><dt>X / Y по вариации</dt><dd>≤ <?php echo HTML::chars((string) $settings['xyz_x_cv']); ?> % / ≤ <?php echo HTML::chars((string) $settings['xyz_y_cv']); ?> %</dd></div>
                </dl>
                <div class="text-xs text-gray-400 mt-2">
                    Границы меняются на странице «Пороги». ABC считается по количеству: цены живут в закупках (erp403).
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Матрица ABC × XYZ</div>
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="text-xs text-gray-400">
                        <tr><th scope="col" class="text-left">Класс</th><th scope="col" class="text-right">X</th><th scope="col" class="text-right">Y</th><th scope="col" class="text-right">Z</th><th scope="col" class="text-right">мало данных</th></tr>
                    </thead>
                    <tbody class="text-gray-700 dark:text-gray-200">
                        <?php foreach (array('A', 'B', 'C') as $abc): ?>
                            <tr>
                                <td class="text-left font-semibold"><?php echo $abc; ?></td>
                                <td class="text-right"><?php echo (int) $analysis['matrix'][$abc.'X']; ?></td>
                                <td class="text-right"><?php echo (int) $analysis['matrix'][$abc.'Y']; ?></td>
                                <td class="text-right"><?php echo (int) $analysis['matrix'][$abc.'Z']; ?></td>
                                <td class="text-right text-gray-400"><?php echo (int) $analysis['matrix'][$abc]; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th scope="col" class="text-left px-4 py-2">Позиция</th>
                        <th scope="col" class="text-right px-4 py-2">Расход</th>
                        <th scope="col" class="text-right px-4 py-2">Доля</th>
                        <th scope="col" class="text-right px-4 py-2">Накоплено</th>
                        <th scope="col" class="text-center px-4 py-2">ABC</th>
                        <th scope="col" class="text-right px-4 py-2">Вариация</th>
                        <th scope="col" class="text-center px-4 py-2">XYZ</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php if ( ! $analysis['rows']): ?>
                        <tr><td colspan="7" class="px-4 py-4 text-gray-500 dark:text-gray-400">Расхода за период нет — ABC/XYZ не из чего считать.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($analysis['rows'] as $row): ?>
                        <tr>
                            <td class="px-4 py-2">
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($row['name']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo HTML::chars($row['code']); ?></div>
                            </td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_out']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['share']); ?> %</td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['share_total']); ?> %</td>
                            <td class="px-4 py-2 text-center font-semibold"><?php echo HTML::chars($row['abc']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['cv'] === NULL ? '—' : HTML::chars((string) $row['cv']).' %'; ?></td>
                            <td class="px-4 py-2 text-center">
                                <?php if ($row['xyz'] === NULL): ?>
                                    <span class="text-xs text-gray-400">мало данных</span>
                                <?php else: ?>
                                    <?php echo HTML::chars($row['xyz']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 border-t dark:border-gray-700">
                Класс X — ровное потребление, Z — непредсказуемое. Позиция без двух полных месяцев расхода остаётся без класса XYZ:
                по одной точке разброс не определён, и подставлять его нельзя.
            </div>
        </div>
    <?php endif; ?>
</div>
