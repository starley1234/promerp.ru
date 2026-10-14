<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Фильтры и статистика -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <p class="text-sm text-gray-500 dark:text-gray-400">Всего конфликтов</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo count($objects); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <p class="text-sm text-gray-500 dark:text-gray-400">Критический приоритет</p>
            <p class="text-2xl font-bold text-red-500"><?php echo $stats['critical'] ?? 0; ?></p>
        </div>
        <div class="md:col-span-2 flex items-end">
            <div class="w-full flex gap-2">
                <div class="relative flex-grow">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" placeholder="Поиск по SKU или наименованию..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Список задач -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th scope="col" class="px-6 py-4 font-semibold">Объект / SKU</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Тип / Организация</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Статус</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Дата изменения</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Действие</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($objects) > 0): ?>
                        <?php foreach ($objects as $item): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($item->master_code); ?></span>
                                        <span class="text-xs text-gray-500">UUID: <?php echo HTML::chars($item->id); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo HTML::chars($item->type_id); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo HTML::chars($item->org_id); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                        <i class="fas fa-exclamation-triangle text-[8px] mr-1.5"></i>
                                        Конфликт
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d.m.Y H:i', strtotime($item->updated_at)); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="/erp230/stewardship/resolve/<?php echo $item->id; ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        Арбитраж
                                        <i class="fas fa-arrow-right ml-2 text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-check-double text-4xl text-green-500 mb-3"></i>
                                    <p class="text-gray-500 dark:text-gray-400">Очередь пуста. Все данные синхронизированы.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Пагинация -->
        <?php if (isset($pagination)): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-200 dark:border-gray-700">
                <?php echo $pagination; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Логика быстрого поиска
    $('input[type="text"]').on('keyup', function() {
        let value = $(this).val().toLowerCase();
        $("tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>