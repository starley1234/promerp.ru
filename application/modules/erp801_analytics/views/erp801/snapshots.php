<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-camera mr-2 text-indigo-600"></i> Снимки витрин
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Снимок фиксирует «как считалось тогда»: цифра за прошлый период перестаёт зависеть от того, что перепроведут позже.
            </p>
        </div>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <form method="post" action="/analytics/snapshots/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-sm space-y-3">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">Снять снимок</div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Витрина</label>
                    <select name="code" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                        <?php foreach ($codes as $code => $label): ?>
                            <option value="<?php echo HTML::chars($code); ?>" <?php echo ($code === 'summary') ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Период с</label>
                        <input type="date" name="filters_from" value="<?php echo HTML::chars(isset($filters['from']) ? $filters['from'] : ''); ?>"
                               class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">по</label>
                        <input type="date" name="filters_to" value="<?php echo HTML::chars(isset($filters['to']) ? $filters['to'] : ''); ?>"
                               class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">День отчёта</label>
                    <input type="date" name="day" value="<?php echo date('Y-m-d'); ?>"
                           class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название (необязательно)</label>
                    <input type="text" name="title" maxlength="255" placeholder="Например: планёрка 1 сентября"
                           class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Зачем сняли</label>
                    <textarea name="note" rows="2" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"></textarea>
                </div>

                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    <i class="fas fa-camera mr-1"></i> Снять снимок
                </button>

                <p class="text-xs text-gray-400">
                    Снимок считается на момент нажатия и больше не обновляется. Пустой период — период витрины по умолчанию.
                </p>
            </form>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                <div class="px-4 py-3 border-b dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Снятые снимки</div>
                    <div class="text-xs text-gray-400">всего: <?php echo count($rows); ?></div>
                </div>
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <tr>
                            <th scope="col" class="text-left px-4 py-2">Название</th>
                            <th scope="col" class="text-left px-4 py-2">Витрина</th>
                            <th scope="col" class="text-right px-4 py-2">День</th>
                            <th scope="col" class="text-right px-4 py-2">Снят</th>
                            <th scope="col" class="text-right px-4 py-2">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <?php if ( ! $rows): ?>
                            <tr><td colspan="5" class="px-4 py-4 text-gray-500 dark:text-gray-400">Снимков ещё нет.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="/analytics/snapshots/view/<?php echo (int) $row['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <?php echo HTML::chars($row['title']); ?>
                                    </a>
                                    <?php if ($row['note']): ?>
                                        <div class="text-xs text-gray-400"><?php echo HTML::chars($row['note']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200"><?php echo HTML::chars($row['code_label']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo HTML::chars($row['day']); ?></td>
                                <td class="px-4 py-2 text-right text-xs text-gray-400"><?php echo HTML::chars($row['created_at']); ?></td>
                                <td class="px-4 py-2 text-right">
                                    <a href="/analytics/snapshots/view/<?php echo (int) $row['id']; ?>" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline mr-2">открыть</a>
                                    <?php if ($can_delete): ?>
                                        <form method="post" action="/analytics/snapshots/delete/<?php echo (int) $row['id']; ?>" class="inline"
                                              data-confirm="Удалить снимок «<?php echo HTML::chars($row['title']); ?>»? Это необратимо.">
                                            <button type="submit" class="text-xs text-red-600 hover:underline">удалить</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
