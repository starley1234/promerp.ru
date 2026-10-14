<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Статистика очереди -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Всего в очереди</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo count($items); ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <i class="fas fa-list-ol text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Ожидают</p>
                    <p class="text-2xl font-bold text-amber-500"><?php echo isset($stats['pending']) ? $stats['pending'] : 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Ошибки</p>
                    <p class="text-2xl font-bold text-red-500"><?php echo isset($stats['failed']) ? $stats['failed'] : 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center text-red-600 dark:text-red-400">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Отправлено (24ч)</p>
                    <p class="text-2xl font-bold text-green-500"><?php echo isset($stats['sent_today']) ? $stats['sent_today'] : 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center text-green-600 dark:text-green-400">
                    <i class="fas fa-check-double text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица очереди -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-lg font-bold flex items-center">
                <i class="fas fa-tasks mr-2 text-blue-500"></i> Текущие задачи
            </h2>
            <div class="flex items-center space-x-2">
                <button data-action="reload" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Обновить">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th scope="col" class="px-6 py-4 font-semibold">ID</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Канал</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Получатель</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Статус</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Попытки</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Создано / Исполнено</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <tr id="notification-row-<?php echo $item->id; ?>" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm font-mono text-gray-500">#<?php echo $item->id; ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        <?php 
                                            $icon = 'fa-envelope';
                                            if ($item->channel == 'telegram') $icon = 'fa-paper-plane';
                                            if ($item->channel == 'push') $icon = 'fa-bell';
                                        ?>
                                        <i class="fas <?php echo $icon; ?> mr-1.5"></i>
                                        <?php echo HTML::chars(ucfirst($item->channel)); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php 
                                            if ($item->recipient_id && isset($profiles[$item->recipient_id])) {
                                                echo HTML::chars($profiles[$item->recipient_id]['full_name']);
                                            } else {
                                                echo HTML::chars($item->destination);
                                            }
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate max-w-[200px]"><?php echo HTML::chars($item->subject); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                        if ($item->status == 'pending') $status_class = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
                                        if ($item->status == 'sent') $status_class = 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
                                        if ($item->status == 'failed') $status_class = 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
                                    ?>
                                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?php echo $status_class; ?>">
                                        <?php echo $item->status; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo $item->attempts; ?> / 5
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div title="Создано"><i class="far fa-plus-square mr-1 opacity-50"></i> <?php echo date('d.m.Y H:i', strtotime($item->created_at)); ?></div>
                                    <?php if ($item->sent_at): ?>
                                        <div title="Исполнено" class="text-green-600 dark:text-green-400 mt-1"><i class="far fa-check-square mr-1 opacity-50"></i> <?php echo date('d.m.Y H:i', strtotime($item->sent_at)); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end space-x-1">
                                        <a href="<?php echo Route::url('default', array('controller' => 'erp217_notification', 'action' => 'view', 'id' => $item->id)); ?>" title="Просмотр" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($item->status != 'sent'): ?>
                                            <button data-call="executeNotificationItem" data-call-args="[<?php echo $item->id; ?>]" title="Выполнить сейчас" class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($item->status == 'failed'): ?>
                                            <a href="<?php echo Route::url('default', array('controller' => 'erp217_notification', 'action' => 'retry', 'id' => $item->id)); ?>" title="Повторить" class="p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors">
                                                <i class="fas fa-redo"></i>
                                            </a>
                                        <?php endif; ?>

                                        <button data-call="deleteNotificationItem" data-call-args="[<?php echo $item->id; ?>]" title="Удалить" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-inbox text-4xl mb-3 block opacity-20"></i>
                                Очередь пуста
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($pagination)): ?>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <?php echo $pagination; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function executeNotificationItem(id) {
    // if (!confirm('Выполнить отправку этого сообщения немедленно?')) return;
    
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    $.post('/erp217_notification/ajax_execute', {id: id}, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }, 'json');
}

function deleteNotificationItem(id) {
    if (!confirm('Вы уверены, что хотите удалить это сообщение из очереди?')) return;
    
    $.post('/erp217_notification/ajax_delete', {id: id}, function(response) {
        if (response.success) {
            $('#notification-row-' + id).fadeOut(300, function() { $(this).remove(); });
        } else {
            alert('Ошибка при удалении: ' + (response.error || 'Неизвестная ошибка'));
        }
    }, 'json');
}
</script>