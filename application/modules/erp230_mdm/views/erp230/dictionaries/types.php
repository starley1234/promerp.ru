<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Иерархия типов изделий</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Управление категориями и классификацией мастер-данных</p>
        </div>
        <div class="flex items-center gap-2">
            <button data-call="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                <i class="fas fa-plus mr-2"></i> Добавить тип
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Дерево типов -->
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" id="treeSearch" class="block w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-sm placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Поиск по дереву...">
                </div>
            </div>
            <div class="p-4 overflow-y-auto max-h-[600px]">
                <ul class="space-y-1">
                    <?php if ( ! empty($tree)): ?>
                        <?php foreach ($tree as $type): ?>
                            <?php echo View::factory('erp230/dictionaries/partial_tree_item')->set('item', $type); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">Справочник пуст</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Детальная информация / Редактирование -->
        <div class="lg:col-span-2 space-y-6">
            <div id="details-container" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <?php if (isset($selected_type)): ?>
                    <div class="flex justify-between items-start mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                <i class="fas <?php echo HTML::chars($selected_type['icon'] ?? 'fa-folder'); ?> text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold"><?php echo HTML::chars($selected_type['name']); ?></h3>
                                <p class="text-xs text-gray-500 uppercase tracking-wider">ID: <?php echo $selected_type['id']; ?> | Код: <?php echo HTML::chars($selected_type['code']); ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button class="p-2 text-gray-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit"></i></button>
                            <button class="p-2 text-gray-400 hover:text-red-600 transition-colors"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Описание</label>
                                <p class="text-sm"><?php echo HTML::chars($selected_type['description'] ?? 'Описание отсутствует'); ?></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Родительский элемент</label>
                                <p class="text-sm flex items-center">
                                    <i class="fas fa-level-up-alt fa-rotate-90 mr-2 text-gray-400"></i>
                                    <?php echo HTML::chars($selected_type['parent_name'] ?? 'Корень'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-100 dark:border-gray-700">
                            <h4 class="text-xs font-bold uppercase text-gray-400 mb-3">Статистика типа</h4>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm">Объектов в реестре:</span>
                                <span class="font-mono font-bold text-blue-600"><?php echo number_format($selected_type['objects_count'] ?? 0, 0, '.', ' '); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm">Дочерних типов:</span>
                                <span class="font-mono font-bold"><?php echo count($selected_type['children'] ?? []); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Атрибуты типа -->
                    <div class="mt-8">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold uppercase tracking-widest text-gray-500">Обязательные атрибуты</h4>
                            <button class="text-xs text-blue-600 hover:underline">Настроить схему</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                                <thead>
                                    <tr class="text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                        <th scope="col" class="pb-2 font-medium">Атрибут</th>
                                        <th scope="col" class="pb-2 font-medium">Тип данных</th>
                                        <th scope="col" class="pb-2 font-medium">Наследование</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    <?php if (isset($selected_type['attributes']) && !empty($selected_type['attributes'])): ?>
                                        <?php foreach ($selected_type['attributes'] as $attr): ?>
                                            <tr>
                                                <td class="py-3 font-medium"><?php echo HTML::chars($attr['name']); ?></td>
                                                <td class="py-3 text-gray-500"><?php echo HTML::chars($attr['data_type']); ?></td>
                                                <td class="py-3">
                                                    <?php if ($attr['inherited']): ?>
                                                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-[10px] rounded text-gray-500">От: <?php echo HTML::chars($attr['source']); ?></span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-[10px] rounded text-blue-600">Локальный</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-gray-400 italic">Атрибуты не заданы</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <i class="fas fa-mouse-pointer text-4xl mb-4 opacity-20"></i>
                        <p>Выберите тип изделия в дереве слева для просмотра деталей</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Логика раскрытия дерева
        $('.tree-toggle').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $childList = $btn.closest('li').find('> ul');
            const $icon = $btn.find('i');
            
            $childList.toggleClass('hidden');
            $icon.toggleClass('fa-chevron-right fa-chevron-down');
        });

        // Поиск по дереву (простая фильтрация)
        $('#treeSearch').on('input', function() {
            const val = $(this).val().toLowerCase();
            $('.tree-item-label').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).closest('li').toggle(text.indexOf(val) > -1);
            });
        });
    });

    function openCreateModal() {
        // Заглушка для вызова уведомления из главного шаблона
        if (window.showNotification) {
            window.showNotification('Функционал создания типа будет доступен в следующем обновлении', 'info');
        }
    }
</script>