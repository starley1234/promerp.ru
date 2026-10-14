<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp119/partials/flash')->set('messages', $messages); ?>

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Лента действий</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Найдено записей: <?php echo (int) $total; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="/audit" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-chart-pie mr-2"></i> Сводка
            </a>
            <?php if ($can_export): ?>
                <a href="/audit/journal/export?<?php echo http_build_query(array_filter($filters, function ($value) { return ! is_bool($value); })); ?>"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    <i class="fas fa-file-csv mr-2"></i> Выгрузить найденное
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Ссылка на страницу с сохранением фильтров
    $link = function ($page_number) use ($filters) {
        $params = array('page' => $page_number);

        foreach ($filters as $key => $value) {
            if ($value === NULL OR $value === '' OR $value === FALSE) {
                continue;
            }

            $params[$key] = $value === TRUE ? 1 : $value;
        }

        return '/audit/journal?'.http_build_query($params);
    };

    // Значение фильтра для формы
    $value = function ($key) use ($filters) {
        return isset($filters[$key]) ? $filters[$key] : '';
    };
    ?>

    <form method="get" action="/audit/journal" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
                <input type="text" name="term" value="<?php echo HTML::chars($value('term')); ?>"
                       placeholder="объект, пользователь, сообщение"
                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Сущность</label>
                <select name="entity_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— все —</option>
                    <?php foreach ($facets['entities'] as $entity): ?>
                        <?php $key = $entity['module'].':'.$entity['entity_type']; ?>
                        <option value="<?php echo HTML::chars($entity['entity_type']); ?>"
                            <?php echo $value('entity_type') === $entity['entity_type'] ? 'selected' : ''; ?>
                            data-module="<?php echo HTML::chars($entity['module']); ?>">
                            <?php echo HTML::chars($entity['label'].' ('.$key.')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Модуль</label>
                <select name="module" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— все —</option>
                    <?php foreach ($facets['modules'] as $module): ?>
                        <option value="<?php echo HTML::chars($module); ?>" <?php echo $value('module') === $module ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($module); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Действие</label>
                <select name="action" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— все —</option>
                    <?php foreach ($facets['actions'] as $action): ?>
                        <option value="<?php echo HTML::chars($action); ?>" <?php echo $value('action') === $action ? 'selected' : ''; ?>>
                            <?php echo HTML::chars(Erp119_Ui::action_label($action)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Важность</label>
                <select name="severity" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— любая —</option>
                    <?php foreach (array('info', 'notice', 'warning', 'critical') as $severity): ?>
                        <option value="<?php echo $severity; ?>" <?php echo $value('severity') === $severity ? 'selected' : ''; ?>>
                            <?php echo HTML::chars(Erp119_Ui::severity_label($severity)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">С даты</label>
                <input type="date" name="from" value="<?php echo HTML::chars($value('from')); ?>"
                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">По дату</label>
                <input type="date" name="to" value="<?php echo HTML::chars($value('to')); ?>"
                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="has_changes" value="1" class="mr-2" <?php echo ! empty($filters['has_changes']) ? 'checked' : ''; ?>>
                    Только с изменениями
                </label>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                <i class="fas fa-search mr-2"></i> Найти
            </button>
            <a href="/audit/journal" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                Сбросить
            </a>
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <?php if (empty($rows)): ?>
            <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Ничего не найдено.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-900/40 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left whitespace-nowrap">Время</th>
                            <th scope="col" class="px-4 py-3 text-left">Действие</th>
                            <th scope="col" class="px-4 py-3 text-left">Объект</th>
                            <th scope="col" class="px-4 py-3 text-left hidden lg:table-cell">Изменения</th>
                            <th scope="col" class="px-4 py-3 text-left whitespace-nowrap">Кто</th>
                            <th scope="col" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300"
                                    title="<?php echo HTML::chars($row['occurred_at']); ?>">
                                    <?php echo HTML::chars(date('d.m.Y H:i', strtotime($row['occurred_at']))); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::action_tone($row['action']); ?>">
                                        <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
                                    </span>
                                    <?php if ($row['severity'] !== 'info'): ?>
                                        <span class="inline-flex ml-1 px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::severity_tone($row['severity']); ?>">
                                            <?php echo HTML::chars(Erp119_Ui::severity_label($row['severity'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-900 dark:text-white">
                                        <?php echo HTML::chars($row['entity_label'] ? $row['entity_label'] : $journal->label_for($row['module'], $row['entity_type'])); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo HTML::chars($journal->label_for($row['module'], $row['entity_type'])); ?>
                                        <?php if ($row['entity_id'] !== ''): ?>
                                            ·
                                            <a class="text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                               href="/audit/journal/entity?entity_type=<?php echo urlencode($row['entity_type']); ?>&entity_id=<?php echo urlencode($row['entity_id']); ?>">#<?php echo HTML::chars($row['entity_id']); ?></a>
                                        <?php endif; ?>
                                    </p>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs text-gray-600 dark:text-gray-300">
                                    <?php
                                    $preview = array();

                                    foreach (array_slice($row['changes'], 0, 3) as $change) {
                                        $preview[] = $change['field'].': '.Erp119_Ui::value($change['old'], 24).' → '.Erp119_Ui::value($change['new'], 24);
                                    }
                                    ?>
                                    <?php echo HTML::chars(implode('; ', $preview)); ?>
                                    <?php if (count($row['changes']) > 3): ?>
                                        <span class="text-gray-400">… ещё <?php echo count($row['changes']) - 3; ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($preview) AND $row['message']): ?>
                                        <span class="text-gray-500 dark:text-gray-400"><?php echo HTML::chars($row['message']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    <?php echo HTML::chars($row['actor_name'] ? $row['actor_name'] : Erp119_Ui::source_label($row['source'])); ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="/audit/journal/view/<?php echo (int) $row['id']; ?>"
                                       class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">Открыть</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 px-4 py-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Страница <?php echo (int) $page; ?> из <?php echo (int) $pages; ?> (всего <?php echo (int) $total; ?>)
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo HTML::chars($link($page - 1)); ?>"
                               class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Назад</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a href="<?php echo HTML::chars($link($page + 1)); ?>"
                               class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Вперёд</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
