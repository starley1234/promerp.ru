<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-key mr-2 text-blue-600"></i> Ключи агентов
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Ключ — учётная запись агента, а не человека: свои права, запрет на изменения, срок жизни.
            В базе хранится только хеш токена, сам токен показывается один раз при выдаче.
        </p>
    </div>

    <?php echo View::factory('erp804/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Выдать ключ</h2>

            <form method="post" action="/mcp/keys" class="space-y-3">
                <input type="hidden" name="do" value="create">

                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-200 mb-1" for="name">Имя ключа *</label>
                    <input type="text" name="name" id="name" required maxlength="128"
                           placeholder="Агент мастера цеха"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-200 mb-1" for="scopes">Права (через запятую) *</label>
                    <input type="text" name="scopes" id="scopes" required
                           placeholder="erp402_production.view,erp402_production.manage"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm font-mono text-xs">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Права — как в ACL. Можно префиксом: <code>erp402_production.*</code> или <code>*</code> (всё).
                        Изменяющие инструменты требуют <code>erp402_production.manage</code> (подготовка),
                        <code>…plan</code> (резерв, старт), <code>…complete</code> (выпуск, отмена).
                    </p>
                </div>

                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-200 mb-1" for="client">Клиент</label>
                    <input type="text" name="client" id="client" maxlength="64" placeholder="claude / cursor / worker"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-200 mb-1" for="expires_at">Действует до</label>
                    <input type="date" name="expires_at" id="expires_at"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="read_only" value="1">
                    только чтение (изменяющие инструменты запрещены)
                </label>

                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-200 mb-1" for="note">Примечание</label>
                    <input type="text" name="note" id="note"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <button type="submit" class="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-key mr-1"></i> Выдать ключ
                </button>
            </form>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                Из консоли: <code>bin/console mcp:key --name="Агент" --scopes=erp402_production.view</code>
            </p>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Выданные ключи</h2>
            </div>

            <?php if (empty($keys)): ?>
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Ключей пока нет.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Ключ</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Права</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-200">Вызовов</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Использован</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-200"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($keys as $key): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($key['name']); ?>
                                    <?php if ( ! $key['enabled']): ?>
                                        <span class="ml-1 px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">отозван</span>
                                    <?php endif; ?>
                                    <?php if ($key['read_only']): ?>
                                        <span class="ml-1 px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">только чтение</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-mono"><?php echo HTML::chars($key['token_prefix']); ?>…</span>
                                    <?php if ($key['client']): ?> · <?php echo HTML::chars($key['client']); ?><?php endif; ?>
                                    <?php if ($key['expires_at']): ?> · до <?php echo HTML::chars(substr((string) $key['expires_at'], 0, 10)); ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-600 dark:text-gray-300">
                                <?php echo HTML::chars(implode(', ', $key['scopes'])); ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300"><?php echo (int) $key['calls_count']; ?></td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                <?php echo $key['last_used_at'] ? HTML::chars(substr((string) $key['last_used_at'], 0, 19)) : '—'; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="post" action="/mcp/keys" class="inline-flex items-center gap-1">
                                    <input type="hidden" name="id" value="<?php echo (int) $key['id']; ?>">
                                    <input type="hidden" name="read_only" value="<?php echo $key['read_only'] ? '0' : '1'; ?>">
                                    <button type="submit" name="do" value="read_only"
                                            class="px-2 py-1 text-xs rounded <?php echo $key['read_only'] ? 'text-amber-700 border border-amber-200' : 'text-blue-700 border border-blue-200'; ?>">
                                        <?php echo $key['read_only'] ? 'Разрешить запись' : 'Только чтение'; ?>
                                    </button>
                                    <?php if ($key['enabled']): ?>
                                        <button type="submit" name="do" value="revoke" class="px-2 py-1 text-xs rounded text-red-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            Отозвать
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="do" value="enable" class="px-2 py-1 text-xs rounded text-green-700 border border-green-200 dark:border-green-800 hover:bg-green-50 dark:hover:bg-green-900/20">
                                            Включить
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="do" value="delete"
                                            data-confirm="Удалить ключ? История вызовов останется."
                                            class="px-2 py-1 text-xs rounded text-gray-600 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        Удалить
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
