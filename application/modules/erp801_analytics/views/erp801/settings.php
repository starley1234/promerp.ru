<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-sliders-h mr-2 text-indigo-600"></i> Пороги витрин
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Порог превращает число в решение: «покрытие 12 дней» само по себе нейтрально, а «12 при цели 30» — повод заказывать.
            </p>
        </div>
    </div>

    <?php echo View::factory('erp801/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/analytics/settings" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-500 dark:text-gray-400 uppercase">
                <tr>
                    <th scope="col" class="text-left px-4 py-2">Настройка</th>
                    <th scope="col" class="text-left px-4 py-2">Значение</th>
                    <th scope="col" class="text-left px-4 py-2">Диапазон</th>
                    <th scope="col" class="text-left px-4 py-2">Пояснение</th>
                    <th scope="col" class="text-left px-4 py-2">Сброс</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($row['label']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo HTML::chars($row['code']); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" name="values[<?php echo HTML::chars($row['code']); ?>]"
                                       value="<?php echo HTML::chars((string) $row['value']); ?>"
                                       min="<?php echo HTML::chars((string) $row['min']); ?>" max="<?php echo HTML::chars((string) $row['max']); ?>"
                                       class="w-28 border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                                <span class="text-xs text-gray-400"><?php echo HTML::chars($row['unit']); ?></span>
                            </div>
                            <?php if ($row['is_default']): ?>
                                <div class="text-xs text-gray-400 mt-1">значение по умолчанию</div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    изменено<?php echo $row['updated_by'] ? ', пользователь #'.(int) $row['updated_by'] : ''; ?><?php echo $row['updated_at'] ? ' ('.HTML::chars($row['updated_at']).')' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            от <?php echo HTML::chars((string) $row['min']); ?> до <?php echo HTML::chars((string) $row['max']); ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($row['note']); ?></td>
                        <td class="px-4 py-3">
                            <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" name="reset[<?php echo HTML::chars($row['code']); ?>]" value="1">
                                вернуть умолчание
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="px-4 py-3 border-t dark:border-gray-700 flex items-center justify-between">
            <div class="text-xs text-gray-400">
                Пустое поле означает «вернуть значение по умолчанию». Значение вне диапазона не сохранится — порог меняет решения, а не только цифры на экране.
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
        </div>
    </form>
</div>
