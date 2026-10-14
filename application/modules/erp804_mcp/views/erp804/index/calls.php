<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-list-check mr-2 text-blue-600"></i> Журнал вызовов MCP
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Что делали агенты: инструмент, ключ, аргументы и результат. Изменяющие вызовы дополнительно
            попадают в журнал действий <a class="text-blue-600 hover:text-blue-800" href="/audit/journal">/audit/journal</a>.
        </p>
    </div>

    <?php echo View::factory('erp804/partials/flash')->set('messages', $messages); ?>

    <form method="get" action="/mcp/calls" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1" for="status">Статус</label>
            <select name="status" id="status" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                <option value="">Все</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo HTML::chars($status); ?>" <?php echo (isset($filters['status']) AND $filters['status'] === $status) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1" for="term">Инструмент</label>
            <input type="text" name="term" id="term" value="<?php echo HTML::chars(isset($filters['term']) ? $filters['term'] : ''); ?>"
                   placeholder="production.order…"
                   class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
            <input type="checkbox" name="mutating" value="1" <?php echo (isset($filters['mutating']) AND $filters['mutating']) ? 'checked' : ''; ?>>
            только изменяющие
        </label>
        <button type="submit" class="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Показать
        </button>
    </form>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <?php if (empty($calls)): ?>
            <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Вызовов не найдено.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">#</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Инструмент</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Аргументы</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Ключ</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Статус</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Результат</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Когда</th>
                    <?php if ($can_approve): ?>
                        <th scope="col" class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-200"></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($calls as $call): ?>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400"><?php echo (int) $call['id']; ?></td>
                        <td class="px-4 py-3 font-mono text-gray-900 dark:text-white"><?php echo HTML::chars($call['tool_name']); ?></td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate" title="<?php echo HTML::chars(json_encode($call['arguments'], JSON_UNESCAPED_UNICODE)); ?>">
                            <?php echo HTML::chars(mb_substr(json_encode($call['arguments'], JSON_UNESCAPED_UNICODE), 0, 80)); ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?php echo HTML::chars((string) $call['key_name']); ?></td>
                        <td class="px-4 py-3">
                            <?php echo View::factory('erp804/index/_status')->set('status', $call['status']); ?>
                            <?php if ($call['duration_ms'] !== NULL): ?>
                                <div class="text-xs text-gray-400 mt-1"><?php echo (int) $call['duration_ms']; ?> мс</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 max-w-xs">
                            <?php if ($call['error']): ?>
                                <span class="text-red-600 dark:text-red-400"><?php echo HTML::chars($call['error']); ?></span>
                            <?php elseif ($call['result']): ?>
                                <span class="truncate" title="<?php echo HTML::chars(json_encode($call['result'], JSON_UNESCAPED_UNICODE)); ?>">
                                    <?php echo HTML::chars(mb_substr(json_encode($call['result'], JSON_UNESCAPED_UNICODE), 0, 90)); ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?php echo HTML::chars(substr((string) $call['created_at'], 0, 19)); ?></td>
                        <?php if ($can_approve): ?>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($call['status'] === 'awaiting_approval'): ?>
                                    <form method="post" action="/mcp/calls" class="inline-flex items-center gap-2">
                                        <input type="hidden" name="id" value="<?php echo (int) $call['id']; ?>">
                                        <button type="submit" name="do" value="approve" class="px-2 py-1 text-xs rounded text-white bg-green-600 hover:bg-green-700">Подтвердить</button>
                                        <button type="submit" name="do" value="reject" class="px-2 py-1 text-xs rounded text-red-700 border border-red-200 dark:border-red-800">Отклонить</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
        <?php
        $query = array_filter(array(
            'status'   => isset($filters['status']) ? $filters['status'] : NULL,
            'term'     => isset($filters['term']) ? $filters['term'] : NULL,
            'mutating' => ! empty($filters['mutating']) ? 1 : NULL,
        ), 'strlen');
        ?>
        <div class="flex items-center justify-between mt-4 text-sm text-gray-600 dark:text-gray-300">
            <div>Всего вызовов: <?php echo (int) $total; ?></div>
            <div class="space-x-2">
                <?php if ($page > 1): ?>
                    <a class="text-blue-600 hover:text-blue-800" href="/mcp/calls?<?php echo http_build_query($query + array('page' => $page - 1)); ?>">← Назад</a>
                <?php endif; ?>
                <span>страница <?php echo (int) $page; ?> из <?php echo (int) $pages; ?></span>
                <?php if ($page < $pages): ?>
                    <a class="text-blue-600 hover:text-blue-800" href="/mcp/calls?<?php echo http_build_query($query + array('page' => $page + 1)); ?>">Вперёд →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
