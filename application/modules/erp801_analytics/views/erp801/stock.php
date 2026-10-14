<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-boxes mr-2 text-indigo-600"></i> Запасы: оборачиваемость и покрытие
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Что двигается быстро, что лежит мёртвым грузом и чего не хватит до цели покрытия.
            </p>
        </div>
        <?php if ($can_export): ?>
            <a href="/analytics/export/stock?<?php echo http_build_query(array_filter($filters, function ($value) { return $value !== NULL AND $value !== ''; })); ?>"
               class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                <i class="fas fa-file-csv mr-1"></i> Выгрузить CSV
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/analytics/stock" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
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
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Класс движения</label>
            <select name="class" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach ($class_labels as $code => $label): ?>
                    <option value="<?php echo HTML::chars($code); ?>" <?php echo ($class_filter === $code) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                <i class="fas fa-sync-alt mr-1"></i> Пересчитать
            </button>
        </div>
    </form>

    <?php if ($turnover): ?>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
            <?php
            $cards = array(
                array('Позиций', (int) $turnover['totals']['items'], 'text-gray-900 dark:text-white'),
                array('Расход за период', $turnover['totals']['qty_out'], 'text-blue-600'),
                array('Остаток', $turnover['totals']['stock'], 'text-gray-900 dark:text-white'),
                array('Резерв', $turnover['totals']['reserved'], 'text-amber-600'),
                array('Мёртвый запас', $turnover['totals']['dead_stock'], 'text-red-600'),
                array('Покрытие, дней', $turnover['totals']['cover_days'] === NULL ? '—' : $turnover['totals']['cover_days'], 'text-green-600'),
            );
            ?>
            <?php foreach ($cards as $card): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo HTML::chars($card[0]); ?></div>
                    <div class="text-xl font-semibold <?php echo $card[2]; ?>"><?php echo HTML::chars((string) $card[1]); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-4 mb-3 text-sm">
        <a href="/analytics/stock?<?php echo http_build_query(array_merge($filters, array('tab' => 'turnover'))); ?>"
           class="<?php echo ($tab === 'deficit') ? 'text-gray-500' : 'font-semibold text-indigo-600 dark:text-indigo-400'; ?>">Оборачиваемость</a>
        <a href="/analytics/stock?<?php echo http_build_query(array_merge($filters, array('tab' => 'deficit'))); ?>"
           class="<?php echo ($tab === 'deficit') ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-gray-500'; ?>">
            Дефицит (<?php echo (int) $deficit['items']; ?>)
        </a>
    </div>

    <?php if ($turnover === NULL): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 rounded-md px-4 py-3 text-sm">
            Витрина не посчитана: источник данных недоступен.
            <?php foreach ($sources as $source): ?>
                <?php if ( ! $source['ok']): ?>
                    <div class="text-xs mt-1">Не подключён: <?php echo HTML::chars($source['title']); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php elseif ($tab === 'deficit'): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th scope="col" class="text-left px-4 py-2">Позиция</th>
                        <th scope="col" class="text-right px-4 py-2">Остаток</th>
                        <th scope="col" class="text-right px-4 py-2">Минимум</th>
                        <th scope="col" class="text-right px-4 py-2">Расход за период</th>
                        <th scope="col" class="text-right px-4 py-2">Хватит, дней</th>
                        <th scope="col" class="text-right px-4 py-2">Докупить до цели</th>
                        <th scope="col" class="text-left px-4 py-2">Почему</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php if ( ! $deficit['rows']): ?>
                        <tr><td colspan="7" class="px-4 py-4 text-gray-500 dark:text-gray-400">Дефицита нет: покрытие выше цели, минимумы не пробиты.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($deficit['rows'] as $row): ?>
                        <tr>
                            <td class="px-4 py-2">
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($row['name']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo HTML::chars($row['code']); ?></div>
                            </td>
                            <td class="px-4 py-2 text-right <?php echo ((float) $row['stock'] <= 0) ? 'text-red-600 font-semibold' : ''; ?>"><?php echo HTML::chars((string) $row['stock']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['min_qty']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_out']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['cover_days'] === NULL ? '—' : HTML::chars((string) $row['cover_days']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['shortage_to_target']); ?></td>
                            <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                <?php
                                $reasons = array(
                                    'out'        => 'нет запаса при расходе',
                                    'below_min'  => 'остаток ниже минимума',
                                    'low_cover'  => 'покрытие ниже цели',
                                );
                                $labels = array();

                                foreach ($row['reasons'] as $reason)
                                {
                                    $labels[] = isset($reasons[$reason]) ? $reasons[$reason] : $reason;
                                }

                                echo HTML::chars(implode(', ', $labels));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 border-t dark:border-gray-700">
                Цель покрытия: <?php echo HTML::chars((string) $deficit['target_days']); ?> дней.
                «Докупить до цели» — прикидка по текущему темпу расхода, а не заказ: заказ считает планирование (erp407) со сроками поставки.
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th scope="col" class="text-left px-4 py-2">Позиция</th>
                        <th scope="col" class="text-right px-4 py-2">Приход</th>
                        <th scope="col" class="text-right px-4 py-2">Расход</th>
                        <th scope="col" class="text-right px-4 py-2">Остаток</th>
                        <th scope="col" class="text-right px-4 py-2">Резерв</th>
                        <th scope="col" class="text-right px-4 py-2">Хватит, дней</th>
                        <th scope="col" class="text-right px-4 py-2">Оборотов</th>
                        <th scope="col" class="text-left px-4 py-2">Класс</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php if ( ! $turnover['rows']): ?>
                        <tr><td colspan="8" class="px-4 py-4 text-gray-500 dark:text-gray-400">Движений и остатков по фильтру нет.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($turnover['rows'] as $row): ?>
                        <tr>
                            <td class="px-4 py-2">
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($row['name']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo HTML::chars($row['code']); ?></div>
                            </td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_in']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['qty_out']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['stock']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo HTML::chars((string) $row['reserved']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['cover_days'] === NULL ? '—' : HTML::chars((string) $row['cover_days']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo $row['turns_period'] === NULL ? '—' : HTML::chars((string) $row['turns_period']); ?></td>
                            <td class="px-4 py-2">
                                <?php
                                $classes = array(
                                    'fast'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'medium' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                    'slow'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                    'dead'   => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'out'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'idle'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                );
                                $class = isset($classes[$row['class']]) ? $classes[$row['class']] : $classes['idle'];
                                $labels = isset($class_labels[$row['class']]) ? $class_labels[$row['class']] : $row['class'];
                                ?>
                                <span class="px-2 py-0.5 rounded text-xs <?php echo $class; ?>"><?php echo HTML::chars($labels); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 border-t dark:border-gray-700">
                Показано <?php echo count($turnover['rows']); ?> из <?php echo (int) $turnover['matched']; ?> позиций.
                Обороты считаются как «расход за период / остаток сейчас»: истории остатков в системе нет, и средний остаток за период аналитика не выдумывает.
            </div>
        </div>
    <?php endif; ?>
</div>
