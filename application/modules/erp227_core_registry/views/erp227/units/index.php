<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Управление единицами измерения для учета материальных ценностей и услуг
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button data-call="openUnitModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                <i class="fas fa-plus mr-2"></i> Добавить единицу
            </button>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-700 card-shadow">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="query" value="<?php echo HTML::chars($filter['query']); ?>" placeholder="Поиск по коду или наименованию..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
            </div>
            
            <div>
                <select name="is_decimal" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
                    <option value="">Все типы величин</option>
                    <option value="0" <?php if ($filter['is_decimal'] === '0') echo 'selected'; ?>>Целая (штучная)</option>
                    <option value="1" <?php if ($filter['is_decimal'] === '1') echo 'selected'; ?>>Дробная</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    Применить
                </button>
                <a href="/erp227_units" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 px-4 py-2 text-sm flex items-center">
                    Сбросить
                </a>
            </div>
        </form>
    </div>

    <!-- Таблица единиц измерения -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Код (ID)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Наименование</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип величины</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (isset($units) && count($units) > 0): ?>
                        <?php foreach ($units as $unit): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-mono font-bold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">
                                        <?php echo HTML::chars($unit->unit); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($unit->name); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($unit->is_decimal): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                            <i class="fas fa-ruler-combined mr-1"></i> Дробная
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                            <i class="fas fa-cubes mr-1"></i> Целая (штучная)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="/erp227_units/edit/<?php echo urlencode($unit->unit); ?>" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button data-call="confirmDelete" data-call-args="<?php echo HTML::chars(json_encode(array((string) $unit->unit), JSON_UNESCAPED_UNICODE)); ?>" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-balance-scale text-4xl mb-3 block opacity-20"></i>
                                Единицы измерения не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($pagination)): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('Вы уверены, что хотите удалить единицу измерения "' + id + '"?')) {
            window.location.href = '/erp227_units/delete/' + encodeURIComponent(id);
        }
    }

    function openUnitModal() {
        window.location.href = '/erp227_units/edit';
    }
</script>