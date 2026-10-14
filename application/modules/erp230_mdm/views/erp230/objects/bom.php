<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-sitemap mr-3 text-blue-500"></i>
                Спецификация изделия (BOM)
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Визуализация иерархической структуры компонентов и материалов
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <button data-action="reload" class="p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm" title="Обновить">
                <i class="fas fa-sync-alt text-gray-600 dark:text-gray-300"></i>
            </button>
            <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center">
                <i class="fas fa-download mr-2"></i> Экспорт
            </button>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- BOM Tree Sidebar -->
        <div class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                <span class="text-sm font-semibold uppercase tracking-wider text-gray-500">Структура дерева</span>
                <span class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-full font-medium">
                    <?php echo count($bom_items ?? []); ?> поз.
                </span>
            </div>
            
            <div class="overflow-x-auto p-4">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead>
                        <tr class="text-xs font-semibold text-gray-500 uppercase border-b border-gray-100 dark:border-gray-700">
                            <th scope="col" class="px-4 py-3">Наименование компонента</th>
                            <th scope="col" class="px-4 py-3">Артикул / Код</th>
                            <th scope="col" class="px-4 py-3 text-center">Кол-во</th>
                            <th scope="col" class="px-4 py-3 text-center">Ед. изм.</th>
                            <th scope="col" class="px-4 py-3 text-right">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (!empty($bom_items)): ?>
                            <?php foreach ($bom_items as $item): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center" data-indent="<?php echo (int) $item['level'] * 20; ?>">
                                            <?php if ($item['has_children']): ?>
                                                <i class="fas fa-chevron-down text-[10px] mr-2 text-gray-400"></i>
                                                <i class="fas fa-box-open mr-2 text-amber-500"></i>
                                            <?php else: ?>
                                                <i class="fas fa-minus text-[10px] mr-2 text-gray-300"></i>
                                                <i class="fas fa-component mr-2 text-blue-400"></i>
                                            <?php endif; ?>
                                            <span class="text-sm font-medium <?php echo $item['level'] == 0 ? 'text-blue-600 dark:text-blue-400 font-bold' : ''; ?>">
                                                <?php echo HTML::chars($item['name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">
                                        <?php echo HTML::chars($item['sku']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center font-semibold">
                                        <?php echo number_format($item['quantity'], 3, '.', ' '); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-500">
                                        <?php echo HTML::chars($item['uom']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <?php if ($item['is_verified']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                <i class="fas fa-check-double mr-1"></i> Golden
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                <i class="fas fa-triangle-exclamation mr-1"></i> Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                    <i class="fas fa-folder-open text-4xl mb-3 block opacity-20"></i>
                                    Данные о структуре изделия отсутствуют
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Golden Record Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                    <i class="fas fa-award text-amber-500 mr-2"></i>
                    Мастер-данные (Golden Record)
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                        <span class="text-xs text-gray-500 uppercase">Trust Score</span>
                        <span class="text-lg font-bold text-green-600 dark:text-green-400"><?php echo $trust_score ?? '0'; ?>%</span>
                    </div>
                    <div class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Источник:</span>
                            <span class="font-medium"><?php echo HTML::chars($source_system ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Последняя сверка:</span>
                            <span class="font-medium"><?php echo date('d.m.Y H:i'); ?></span>
                        </div>
                    </div>
                    <button class="w-full py-2 text-sm border border-blue-600 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                        Арбитраж данных
                    </button>
                </div>
            </div>

            <!-- Cross-References -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Кросс-референсы</h3>
                <div class="space-y-2">
                    <?php if (!empty($xrefs)): ?>
                        <?php foreach ($xrefs as $ref): ?>
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition-colors border-l-2 border-blue-500 pl-3">
                                <div>
                                    <p class="text-xs font-bold"><?php echo HTML::chars($ref['system']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo HTML::chars($ref['external_id']); ?></p>
                                </div>
                                <i class="fas fa-external-link-alt text-[10px] text-gray-400"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 italic">Связи с внешними системами не найдены</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>