<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-tools mr-2 text-blue-600"></i> Инструменты MCP
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Инструменты объявляют сами модули файлом <code>mcp.php</code>: подключили модуль — агент увидел его возможности.
                Выключили — инструменты исчезли вместе с ним.
            </p>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Всего: <strong><?php echo count($tools); ?></strong>
            <?php foreach ($sources as $module => $count): ?>
                <span class="ml-3 px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-xs"><?php echo HTML::chars($module); ?>: <?php echo (int) $count; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php echo View::factory('erp804/partials/flash')->set('messages', $messages); ?>

    <?php if ( ! empty($problems)): ?>
        <div class="border rounded-md px-4 py-3 text-sm mb-4 bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
            <div class="font-medium mb-1">Замечания к объявлениям (<?php echo count($problems); ?>):</div>
            <ul class="list-disc list-inside">
                <?php foreach ($problems as $problem): ?>
                    <li>
                        <?php if ($problem['tool']): ?><code><?php echo HTML::chars($problem['tool']); ?></code> — <?php endif; ?>
                        <?php echo HTML::chars($problem['message']); ?>
                        <span class="text-xs">(<?php echo HTML::chars($problem['module']); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($tools as $tool): ?>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-mono text-sm text-gray-900 dark:text-white"><?php echo HTML::chars($tool['name']); ?></div>
                        <div class="text-gray-900 dark:text-white font-medium"><?php echo HTML::chars($tool['title']); ?></div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"><?php echo HTML::chars($tool['module']); ?></span>
                        <?php if ( ! empty($tool['self'])): ?>
                            <span class="px-2 py-1 rounded bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">любой действующий ключ</span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-mono"><?php echo HTML::chars($tool['scope']); ?></span>
                        <?php endif; ?>
                        <?php if ($tool['mutating']): ?>
                            <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">изменяет данные</span>
                        <?php endif; ?>
                        <?php if ($tool['approval']): ?>
                            <span class="px-2 py-1 rounded bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">нужно подтверждение</span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-300 mt-3"><?php echo HTML::chars($tool['description']); ?></p>

                <?php $properties = isset($tool['schema']['properties']) ? $tool['schema']['properties'] : array(); ?>
                <?php if ($properties): ?>
                    <table class="min-w-full text-xs mt-3">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead>
                        <tr class="text-gray-500 dark:text-gray-400">
                            <th scope="col" class="text-left py-1 pr-4 font-medium">Аргумент</th>
                            <th scope="col" class="text-left py-1 pr-4 font-medium">Тип</th>
                            <th scope="col" class="text-left py-1 font-medium">Описание</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($properties as $name => $rule): ?>
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-1 pr-4 font-mono text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($name); ?>
                                    <?php if (in_array($name, isset($tool['schema']['required']) ? (array) $tool['schema']['required'] : array(), TRUE)): ?>
                                        <span class="text-red-600">*</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1 pr-4 text-gray-600 dark:text-gray-300"><?php echo HTML::chars(isset($rule['type']) ? $rule['type'] : 'string'); ?></td>
                                <td class="py-1 text-gray-600 dark:text-gray-300">
                                    <?php echo HTML::chars(isset($rule['description']) ? $rule['description'] : ''); ?>
                                    <?php if ( ! empty($rule['enum'])): ?>
                                        <span class="text-gray-400">(<?php echo HTML::chars(implode(' | ', $rule['enum'])); ?>)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
