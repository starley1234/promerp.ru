<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo __('Links Registry'); ?></h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Управление связями между объектами системы (BOM, вложения, зависимости)
            </p>
        </div>
    </div>

    <!-- Фильтры -->
    <form method="GET" action="" class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase">Тип связи</label>
                <select name="link_type" class="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Все типы</option>
                    <option value="bom" <?php if ($filters['link_type'] == 'bom') echo 'selected'; ?>>BOM (Состав)</option>
                    <option value="attachment" <?php if ($filters['link_type'] == 'attachment') echo 'selected'; ?>>Attachment (Вложение)</option>
                    <option value="dependency" <?php if ($filters['link_type'] == 'dependency') echo 'selected'; ?>>Dependency (Зависимость)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase">ID Источника</label>
                <input type="number" name="source_id" value="<?php echo HTML::chars($filters['source_id']); ?>" 
                       placeholder="Source ID..." 
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase">ID Цели</label>
                <input type="number" name="target_id" value="<?php echo HTML::chars($filters['target_id']); ?>" 
                       placeholder="Target ID..." 
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-filter mr-2"></i>Применить
                </button>
                <a href="<?php echo Route::url('default', ['controller' => 'erp227_links', 'action' => 'index']); ?>" 
                   class="p-2 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-md transition-colors" title="Сбросить">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- Таблица связей -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Исходный объект (Source)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип связи</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Целевой объект (Target)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Кол-во</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (isset($links) && count($links) > 0): ?>
                        <?php foreach ($links as $link): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors" data-id="<?php echo $link->id; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo $link->id; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <a href="/erp227_objects/view/<?php echo $link->source_id; ?>" class="hover:text-blue-600">
                                            <?php echo HTML::chars($link->source->name); ?>
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-400">ID: <?php echo $link->source_id; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        <?php echo HTML::chars($link->link_type); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <a href="/erp227_objects/view/<?php echo $link->target_id; ?>" class="hover:text-blue-600">
                                            <?php echo HTML::chars($link->target->name); ?>
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-400">ID: <?php echo $link->target_id; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo number_format($link->quantity, 3, '.', ''); ?> 
                                    <span class="text-gray-400 text-xs"><?php echo HTML::chars($link->unit); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button data-call="deleteLink" data-call-args="[<?php echo $link->id; ?>]" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-link text-4xl mb-3 block opacity-20"></i>
                                Связи не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($pagination) && $pagination->total_items > $pagination->items_per_page): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function deleteLink(id) {
        if (confirm('<?php echo __("Are you sure you want to delete this link?"); ?>')) {
            $.ajax({
                url: '/erp227_links/delete/' + id,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        $('tr[data-id="' + id + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.message || 'Error');
                    }
                },
                error: function() {
                    alert('Server error');
                }
            });
        }
    }
</script>