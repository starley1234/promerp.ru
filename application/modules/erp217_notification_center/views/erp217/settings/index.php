<?php defined('SYSPATH') or die('No direct script access.'); ?> 
<div> 
    <div class="max-w-5xl mx-auto">
        
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Настройки уведомлений</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Управляйте подписками на системные события и конкретные объекты.</p>
            </div>
        </div>

        <!-- View Settings -->
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-desktop mr-2 text-blue-500"></i> Внешний вид списка уведомлений
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php 
                    $current_view = isset($_COOKIE['erp217_notification_view']) ? $_COOKIE['erp217_notification_view'] : 'list_outlook';
                    $view_options = [
                        'list_standard' => ['name' => 'Стандартный список', 'desc' => 'Классическое отображение'],
                        'list_outlook' => ['name' => 'Outlook стиль', 'desc' => 'Две колонки с предпросмотром'],
                        'list_compact' => ['name' => 'Компактный', 'desc' => 'Максимальная плотность данных']
                    ];
                ?>
                <?php foreach ($view_options as $val => $opt): ?>
                <label class="relative flex flex-col p-4 border rounded-xl cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-gray-700/50 <?php echo ($current_view === $val) ? 'border-blue-500 bg-blue-50/30 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'; ?>">
                    <input type="radio" name="view_mode" value="<?php echo $val; ?>" class="view-mode-toggle absolute top-4 right-4" <?php echo ($current_view === $val) ? 'checked' : ''; ?>>
                    <span class="font-bold text-gray-900 dark:text-white"><?php echo $opt['name']; ?></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo $opt['desc']; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Global Event Subscriptions -->
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Системные события</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <th scope="col" class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Событие</th>
                            <?php foreach ($channels as $channel_id => $channel_name): ?>
                            <th scope="col" class="px-4 py-4 text-center text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                <div class="flex flex-col items-center">
                                    <?php 
                                        $icon = 'fa-bell';
                                        $color = 'text-gray-400';
                                        switch($channel_id) {
                                            case 'email': $icon = 'fa-envelope'; $color = 'text-blue-500'; break;
                                            case 'telegram': $icon = 'fab fa-telegram'; $color = 'text-sky-500'; break;
                                            case 'push': $icon = 'fa-bell'; $color = 'text-amber-500'; break;
                                            case 'max': $icon = 'fa-robot'; $color = 'text-purple-500'; break;
                                        }
                                    ?>
                                    <i class="fas <?php echo $icon; ?> <?php echo $color; ?> mb-1"></i>
                                    <span class="text-[10px]"><?php echo HTML::chars($channel_name); ?></span>
                                </div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (!empty($event_types)): ?>
                            <?php foreach ($event_types as $alias => $event): ?>
                            <?php 
                                if (!is_array($event)) {
                                    $event = ['name' => $event, 'description' => ''];
                                }
                            ?>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($event['name']); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($event['description'] ?: $alias); ?></div>
                                </td>
                                <?php foreach ($channels as $channel_id => $channel_name): ?>
                                <td class="px-4 py-4 text-center">
                                    <label class="inline-flex items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               data-event="<?php echo $alias; ?>" 
                                               data-channel="<?php echo $channel_id; ?>"
                                               class="subscription-toggle hidden"
                                               <?php echo (isset($user_settings[$alias][$channel_id]) && $user_settings[$alias][$channel_id]) ? 'checked' : ''; ?>>
                                        <div class="w-10 h-5 bg-gray-200 dark:bg-gray-700 rounded-full p-1 transition-colors duration-200 ease-in-out toggle-bg">
                                            <div class="dot w-3 h-3 bg-white rounded-full shadow-sm transition-transform duration-200 ease-in-out"></div>
                                        </div>
                                    </label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Entity Subscriptions -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Подписки на объекты</h2>
                <p class="text-sm text-gray-500">Объекты (сделки, задачи), за которыми вы следите.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-gray-500 uppercase">Тип</th>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-gray-500 uppercase">ID объекта</th>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-gray-500 uppercase">Дата подписки</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Действие</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (count($entity_subscriptions) > 0): ?>
                            <?php foreach ($entity_subscriptions as $entity_sub): ?>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors" id="entity-sub-<?php echo $entity_sub->id; ?>">
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-md bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        <?php echo HTML::chars(strtoupper($entity_sub->entity_type)); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-mono">
                                    #<?php echo $entity_sub->entity_id; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('d.m.Y H:i', strtotime($entity_sub->created_at)); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-red-500 hover:text-red-700 transition-colors entity-unsubscribe" 
                                            data-type="<?php echo $entity_sub->entity_type; ?>" 
                                            data-id="<?php echo $entity_sub->entity_id; ?>"
                                            title="Отписаться">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 italic">
                                    У вас пока нет активных подписок на конкретные объекты.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<style>
    .subscription-toggle:checked + .toggle-bg { background-color: #3b82f6; }
    .subscription-toggle:checked + .toggle-bg .dot { transform: translateX(20px); }
    .toggle-bg { position: relative; display: block; cursor: pointer; }
</style>

<script>
$(document).ready(function() {
    // Переключение вида
    $('.view-mode-toggle').on('change', function() {
        const val = $(this).val();
        document.cookie = "erp217_notification_view=" + val + "; path=/; max-age=" + (30*24*60*60);
        if (typeof showNotification === 'function') {
            showNotification('Вид отображения изменен', 'success');
        }
        setTimeout(() => location.reload(), 300);
    });

    // Переключение глобальных подписок
    $('.subscription-toggle').on('change', function() {
        const $checkbox = $(this);
        const eventType = $checkbox.data('event');
        const channel = $checkbox.data('channel');
        const isEnabled = $checkbox.is(':checked') ? 1 : 0;
        
        $checkbox.prop('disabled', true);

        $.ajax({
            url: '/erp217_index/save_subscription',
            method: 'POST',
            dataType: 'json',
            data: { event_type: eventType, channel: channel, is_enabled: isEnabled },
            success: function(response) {
                if (typeof showNotification === 'function') showNotification(response.message, 'success');
            },
            error: function() {
                $checkbox.prop('checked', !isEnabled);
                if (typeof showNotification === 'function') showNotification('Ошибка сохранения', 'error');
            },
            complete: function() { $checkbox.prop('disabled', false); }
        });
    });

    // Отписка от сущности
    $('.entity-unsubscribe').on('click', function() {
        if (!confirm('Вы уверены, что хотите отписаться от этого объекта?')) return;
        
        const $btn = $(this);
        const entityType = $btn.data('type');
        const entityId = $btn.data('id');

        $.ajax({
            url: '/erp217_index/toggle_entity_subscription',
            method: 'POST',
            dataType: 'json',
            data: { entity_type: entityType, entity_id: entityId, subscribe: 0 },
            success: function(response) {
                $btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                if (typeof showNotification === 'function') showNotification(response.message, 'success');
            },
            error: function() {
                if (typeof showNotification === 'function') showNotification('Ошибка при отписке', 'error');
            }
        });
    });
});
</script>