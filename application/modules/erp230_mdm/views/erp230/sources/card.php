<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Карточка источника данных MDM.
 * 
 * @var Model_Erp230_Source $source Объект источника
 * @var array               $stats  Статистика использования (properties_current, objects_touched, top_keys)
 * @var bool                $can_edit Права на редактирование
 */
?>

<div>

    <!-- Хлебные крошки и навигация -->
    <div class="flex items-center justify-between mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="<?php echo URL::site('erp230_sources'); ?>" class="text-sm text-gray-500 hover:text-emerald-600 dark:text-gray-400 dark:hover:text-white transition-colors">
                        <i class="fas fa-database mr-2"></i> Источники
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo HTML::chars($source->id); ?></span>
                    </div>
                </li>
            </ol>
        </nav>
        
        <?php if ($can_edit): ?>
        <div class="flex gap-2">
            <a href="<?php echo URL::site('erp230_sources/edit/'.$source->id); ?>" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
                <i class="fas fa-edit mr-2"></i> Редактировать
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Левая колонка: Основная информация -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Параметры</h2>
                        <span class="px-2 py-1 text-xs font-mono bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">ID: <?php echo HTML::chars($source->id); ?></span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Наименование</label>
                        <div class="text-gray-900 dark:text-white font-medium"><?php echo HTML::chars($source->display_name); ?></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Тип источника</label>
                        <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <?php echo HTML::chars($source->kind); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Trust Score (Доверие)</label>
                        <div class="flex items-center mt-1">
                            <span class="text-2xl font-black <?php echo $source->trust_score >= 80 ? 'text-emerald-600' : ($source->trust_score >= 40 ? 'text-amber-600' : 'text-rose-600'); ?>">
                                <?php echo (int) $source->trust_score; ?>
                            </span>
                            <span class="text-gray-400 ml-1">/ 100</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 mt-2">
                            <div class="h-2 rounded-full <?php echo $source->trust_score >= 80 ? 'bg-emerald-500' : ($source->trust_score >= 40 ? 'bg-amber-500' : 'bg-rose-500'); ?>" 
                                 data-progress="<?php echo (int) $source->trust_score; ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Виджет удаления -->
            <?php if ($can_edit): ?>
            <div class="bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30 rounded-xl p-6">
                <h3 class="text-sm font-bold text-rose-800 dark:text-rose-400 mb-2">Опасная зона</h3>
                <p class="text-xs text-rose-700 dark:text-rose-500 mb-4">
                    Удаление источника невозможно, если он используется в текущих значениях атрибутов.
                </p>
                <form action="<?php echo URL::site('erp230_sources/delete/'.$source->id); ?>" method="POST" data-confirm="Вы уверены, что хотите удалить этот источник?">
                    <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-rose-600 bg-white border border-rose-200 rounded-lg hover:bg-rose-50 transition-colors <?php echo $stats['properties_current'] > 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $stats['properties_current'] > 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-trash-alt mr-2"></i> Удалить источник
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Правая колонка: Аналитика -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Сводные показатели -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 mr-4">
                            <i class="fas fa-fingerprint text-xl"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['properties_current'], 0, '.', ' '); ?></div>
                            <div class="text-sm text-gray-500">Активных значений</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 mr-4">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['objects_touched'], 0, '.', ' '); ?></div>
                            <div class="text-sm text-gray-500">Уникальных объектов</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Топ атрибутов -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Популярные атрибуты</h2>
                    <p class="text-sm text-gray-500">Ключи, значения которых чаще всего поставляются данным источником</p>
                </div>
                <div class="p-0">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ключ атрибута</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Количество</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Доля</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                            <?php if (empty($stats['top_keys'])): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-400 italic text-sm">
                                        Нет данных об использовании
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stats['top_keys'] as $row): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <code class="text-xs font-mono text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-1.5 py-0.5 rounded">
                                                <?php echo HTML::chars($row['key']); ?>
                                            </code>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo number_format($row['cnt'], 0, '.', ' '); ?>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-right">
                                            <div class="text-xs text-gray-500">
                                                <?php echo $stats['properties_current'] > 0 ? round(($row['cnt'] / $stats['properties_current']) * 100, 1) : 0; ?>%
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>