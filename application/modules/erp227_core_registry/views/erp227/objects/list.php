<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Реестр объектов</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Найдено объектов: <span class="font-semibold"><?php echo $total_count; ?></span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button data-call="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                <i class="fas fa-plus mr-2"></i> Создать объект
            </button>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <form method="GET" action="" class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="q" value="<?php echo HTML::chars($current_filters['q']); ?>" 
                       placeholder="Поиск по коду, названию или атрибутам..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            <div>
                <select name="type_id" class="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Все типы</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo $type->id; ?>" <?php if ($current_filters['type_id'] == $type->id) echo 'selected'; ?>>
                            <?php echo HTML::chars($type->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    Применить
                </button>
                <a href="/erp227_objects" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors" title="Сбросить">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- Таблица объектов -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Код / Артикул</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Наименование</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип / Вид</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Статус</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($objects) > 0): ?>
                        <?php foreach ($objects as $obj): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono font-bold text-blue-600 dark:text-blue-400">
                                        <?php echo HTML::chars($obj->code); ?>
                                    </div>
                                    <div class="text-xs text-gray-400">rev. <?php echo $obj->revision; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <a href="/erp227_objects/view/<?php echo $obj->id; ?>" class="hover:underline">
                                            <?php echo HTML::chars($obj->name); ?>
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs">
                                        <?php echo HTML::chars($obj->path); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        <?php echo HTML::chars($obj->type->name); ?>
                                    </span>
                                    <?php if ($obj->kind): ?>
                                        <div class="text-[10px] text-gray-400 mt-1 uppercase"><?php echo HTML::chars($obj->kind); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                        $status_classes = [
                                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                            'draft' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                            'archived' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
                                        ];
                                        $class = $status_classes[$obj->status] ?? $status_classes['draft'];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $class; ?>">
                                        <?php echo HTML::chars($obj->status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="/erp227_objects/view/<?php echo $obj->id; ?>" class="p-2 text-gray-400 hover:text-blue-600 transition-colors" title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button data-call="deleteObject" data-call-args="[<?php echo $obj->id; ?>]" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-box-open text-4xl mb-3 block opacity-20"></i>
                                Объекты не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <?php 
            $config = Kohana::$config->load('erp227_core_registry');
            $limit = $config['items_per_page'] ?? 20;
            $total_pages = ceil($total_count / $limit);
            $current_page = (int) ($current_filters['page'] ?? 1);
        ?>
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo URL::query(['page' => $current_page - 1]); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Назад</a>
                    <?php endif; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo URL::query(['page' => $current_page + 1]); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Вперед</a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-400">
                            Показано от <span class="font-medium"><?php echo ($current_page - 1) * $limit + 1; ?></span> до <span class="font-medium"><?php echo min($current_page * $limit, $total_count); ?></span> из <span class="font-medium"><?php echo $total_count; ?></span> результатов
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <a href="<?php echo URL::query(['page' => max(1, $current_page - 1)]); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span class="sr-only">Назад</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            
                            <?php 
                            $start = max(1, $current_page - 2);
                            $end = min($total_pages, $current_page + 2);
                            
                            if ($start > 1): ?>
                                <a href="<?php echo URL::query(['page' => 1]); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50">1</a>
                                <?php if ($start > 2): ?><span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700">...</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="<?php echo URL::query(['page' => $i]); ?>" class="relative inline-flex items-center px-4 py-2 border <?php echo ($i == $current_page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50'; ?> text-sm font-medium">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?><span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700">...</span><?php endif; ?>
                                <a href="<?php echo URL::query(['page' => $total_pages]); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <a href="<?php echo URL::query(['page' => min($total_pages, $current_page + 1)]); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span class="sr-only">Вперед</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function deleteObject(id) {
        if (confirm('Вы уверены, что хотите удалить этот объект?')) {
            fetch('/erp227_objects/delete/' + id, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(response => {
                if (response.ok) window.location.reload();
            });
        }
    }

    function openCreateModal() {
        window.location.href = '/erp227_objects/create';
    }
</script>