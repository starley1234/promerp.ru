<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-robot mr-2 text-blue-600"></i> MCP-сервер
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                LLM-агенты работают с системой через протокол MCP: инструменты объявляют сами модули,
                доступ — по ключам с правами, все вызовы видны в журнале.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/mcp/console" class="px-3 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-gauge-high mr-1"></i> Обзор
            </a>
            <a href="/mcp/tools" class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-tools mr-1"></i> Инструменты
            </a>
            <a href="/mcp/calls" class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-list-check mr-1"></i> Журнал вызовов
            </a>
            <a href="/mcp/keys" class="px-3 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-key mr-1"></i> Ключи
            </a>
        </div>
    </div>

    <?php echo View::factory('erp804/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Инструментов</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $info['tools']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                модулей: <?php echo count($info['modules']); ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Вызовов за 30 дней</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['total']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                изменяющих: <?php echo (int) $stats['mutating']; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Ошибок</div>
            <div class="text-2xl font-semibold <?php echo $stats['error'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo (int) $stats['error']; ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">успешных: <?php echo (int) $stats['ok']; ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Ждут подтверждения</div>
            <div class="text-2xl font-semibold <?php echo count($pending) > 0 ? 'text-yellow-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo count($pending); ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">решений человека</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Подключение</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                Точка входа — <code class="px-1 bg-gray-100 dark:bg-gray-700 rounded">POST /mcp</code> (JSON-RPC 2.0),
                ключ передаётся заголовком <code class="px-1 bg-gray-100 dark:bg-gray-700 rounded">Authorization: Bearer …</code>.
                Локальные клиенты могут работать через stdio: <code class="px-1 bg-gray-100 dark:bg-gray-700 rounded">bin/console mcp:serve --key=…</code>.
            </p>
            <pre class="text-xs bg-gray-900 text-gray-100 rounded-md p-3 overflow-x-auto">curl -s http://127.0.0.1:8080/mcp \
  -H "Authorization: Bearer mcp_…" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'</pre>
            <dl class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Сервер</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($info['name']); ?> <?php echo HTML::chars($info['version']); ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Протокол</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($info['protocol']); ?></dd>
                </div>
            </dl>

            <?php if ( ! empty($info['problems'])): ?>
                <div class="mt-4 border rounded-md px-4 py-3 text-sm bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
                    Замечаний к объявлениям инструментов: <?php echo count($info['problems']); ?>.
                    Подробности — <code>bin/console mcp:tools --check</code>.
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Требуют подтверждения</h2>
                <a href="/mcp/calls?status=awaiting_approval" class="text-sm text-blue-600 hover:text-blue-800">все</a>
            </div>

            <?php if (empty($pending)): ?>
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Ожидающих операций нет.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($pending as $call): ?>
                        <li class="px-5 py-4 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-gray-900 dark:text-white">#<?php echo (int) $call['id']; ?> <?php echo HTML::chars($call['tool_name']); ?></span>
                                <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($call['created_at']); ?></span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                ключ: <?php echo HTML::chars((string) $call['key_name']); ?>
                            </div>
                            <pre class="text-xs bg-gray-100 dark:bg-gray-900 rounded p-2 mt-2 overflow-x-auto"><?php echo HTML::chars(json_encode($call['arguments'], JSON_UNESCAPED_UNICODE)); ?></pre>

                            <?php if ($can_approve): ?>
                                <form method="post" action="/mcp/calls" class="mt-3 flex items-center gap-2">
                                    <input type="hidden" name="id" value="<?php echo (int) $call['id']; ?>">
                                    <button type="submit" name="do" value="approve" class="px-3 py-1.5 text-xs rounded-md text-white bg-green-600 hover:bg-green-700">
                                        <i class="fas fa-check mr-1"></i> Подтвердить
                                    </button>
                                    <input type="text" name="reason" placeholder="причина отказа" class="text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1.5">
                                    <button type="submit" name="do" value="reject" class="px-3 py-1.5 text-xs rounded-md text-red-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        Отклонить
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Последние вызовы</h2>
        </div>

        <?php if (empty($recent)): ?>
            <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Вызовов ещё не было.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">#</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Инструмент</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Ключ</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Статус</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium text-gray-600 dark:text-gray-200">мс</th>
                    <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Когда</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($recent as $call): ?>
                    <tr>
                        <td class="px-5 py-3 font-mono text-gray-500 dark:text-gray-400"><?php echo (int) $call['id']; ?></td>
                        <td class="px-5 py-3 font-mono text-gray-900 dark:text-white">
                            <?php echo HTML::chars($call['tool_name']); ?>
                            <?php if ($call['mutating']): ?>
                                <span class="ml-1 text-xs text-yellow-600 dark:text-yellow-400" title="изменяет данные">●</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300"><?php echo HTML::chars((string) $call['key_name']); ?></td>
                        <td class="px-5 py-3">
                            <?php echo View::factory('erp804/index/_status')->set('status', $call['status']); ?>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300"><?php echo $call['duration_ms'] !== NULL ? (int) $call['duration_ms'] : '—'; ?></td>
                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?php echo HTML::chars(substr((string) $call['created_at'], 0, 19)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
