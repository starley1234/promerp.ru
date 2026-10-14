<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <div class="relative mr-3">
                    <i class="fas fa-bell text-blue-500"></i>
                    <span id="unread-badge-main" class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden">0</span>
                </div>
                <?php echo __('Центр уведомлений'); ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo __('Управление вашими сообщениями и подписками'); ?>
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button data-call="markAllAsRead" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm active:scale-95">
                <i class="fas fa-check-double mr-2 text-green-500"></i>
                <?php echo __('Прочитать всё'); ?>
            </button>
            <a href="/erp217_settings" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                <i class="fas fa-cog mr-2 text-gray-500"></i>
                <?php echo __('Настройки'); ?>
            </a>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="mb-6 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <form id="filter-form" method="get" class="flex flex-wrap items-end gap-6">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase"><?php echo __('Поиск по теме'); ?></label>
                <input type="text" name="subject" value="<?php echo HTML::chars(Arr::get($filters, 'subject')); ?>" 
                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            
            <div class="min-w-[250px]">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase"><?php echo __('Статус'); ?></label>
                <div class="flex items-center space-x-4 bg-gray-50 dark:bg-gray-900 p-1.5 rounded-lg border border-gray-300 dark:border-gray-600">
                    <label class="flex items-center cursor-pointer px-3 py-1 rounded-md transition-colors hover:bg-white dark:hover:bg-gray-800">
                        <input type="radio" name="unread" value="" data-action="submit-target" data-target="#filter-form" class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300" <?php if(Arr::get($filters, 'unread') === NULL || Arr::get($filters, 'unread') === '') echo 'checked'; ?>>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300"><?php echo __('Все'); ?></span>
                    </label>
                    <label class="flex items-center cursor-pointer px-3 py-1 rounded-md transition-colors hover:bg-white dark:hover:bg-gray-800">
                        <input type="radio" name="unread" value="1" data-action="submit-target" data-target="#filter-form" class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300" <?php if(Arr::get($filters, 'unread') == '1') echo 'checked'; ?>>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300"><?php echo __('Только непрочитанные'); ?></span>
                    </label>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-filter mr-2"></i><?php echo __('Применить'); ?>
                </button>
                <a href="?reset=1" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <?php echo __('Сброс'); ?>
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th scope="col" class="px-6 py-4 font-semibold w-16 text-center">ID</th>
                        <th scope="col" class="px-6 py-4 font-semibold w-32"><?php echo __('Канал'); ?></th>
                        <th scope="col" class="px-6 py-4 font-semibold"><?php echo __('Сообщение'); ?></th>
                        <th scope="col" class="px-6 py-4 font-semibold w-44"><?php echo __('Дата и время'); ?></th>
                        <th scope="col" class="px-6 py-4 font-semibold w-24 text-center"><?php echo __('Статус'); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $item): ?>
                            <?php 
                                $is_unread = ($item->is_read == 0);
                                $row_class = $is_unread ? 'bg-blue-50/40 dark:bg-blue-900/10 font-medium' : 'opacity-70';
                                $payload = json_decode($item->payload, true);
                                $body = isset($payload['body']) ? $payload['body'] : $item->payload;
                            ?>
                            <tr class="notification-row hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors <?php echo $row_class; ?>" 
                                data-id="<?php echo $item->id; ?>" 
                                data-is-read="<?php echo $item->is_read; ?>">
                                <td class="px-6 py-4 text-xs font-mono text-gray-400 text-center">
                                    <?php echo $item->id; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $icon = 'fa-envelope';
                                        $color = 'text-blue-500 bg-blue-50 dark:bg-blue-900/20';
                                        if ($item->channel == 'telegram') {
                                            $icon = 'fa-paper-plane';
                                            $color = 'text-sky-500 bg-sky-50 dark:bg-sky-900/20';
                                        } elseif ($item->channel == 'push') {
                                            $icon = 'fa-bell';
                                            $color = 'text-purple-500 bg-purple-50 dark:bg-purple-900/20';
                                        }
                                    ?>
                                    <div class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-tight <?php echo $color; ?>">
                                        <i class="fas <?php echo $icon; ?> mr-1.5"></i>
                                        <?php echo $item->channel; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white mb-1">
                                        <?php echo HTML::chars($item->subject); ?>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 notification-body">
                                        <?php echo $body; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col">
                                        <span class="text-gray-700 dark:text-gray-300"><?php echo date('d.m.Y', strtotime($item->created_at)); ?></span>
                                        <span class="text-xs opacity-60"><?php echo date('H:i:s', strtotime($item->created_at)); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="status-icon-container flex justify-center">
                                        <button data-call="toggleRead" data-call-args="[<?php echo $item->id; ?>]" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all active:scale-90">
                                            <?php if ($item->is_read == 0): ?>
                                                <i class="fas fa-circle text-blue-500 text-[10px]" title="<?php echo __('Пометить как прочитанное'); ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check-double text-gray-400 text-sm" title="<?php echo __('Вернуть статус нового'); ?>"></i>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-24 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700/50 rounded-full flex items-center justify-center mb-4">
                                        <i class="fas fa-inbox text-3xl text-gray-300 dark:text-gray-600"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white"><?php echo __('Уведомлений нет'); ?></h3>
                                    <p class="text-gray-500 dark:text-gray-400 max-w-xs mx-auto mt-2 text-sm">
                                        <?php echo __('Здесь будут появляться важные системные сообщения и обновления.'); ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($pagination->total_items > $pagination->items_per_page): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .notification-body a {
        color: #3b82f6;
        text-decoration: underline;
        font-weight: 500;
    }
    .notification-body a:hover {
        color: #2563eb;
    }
</style>

<script>
function updateBadge() {
    $.ajax({
        url: '/erp217_api/unread_count',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            const badgeMain = $('#unread-badge-main');
            const badgeNav = $('#notification-badge');
            const count = parseInt(res.count) || 0;
            
            if (count > 0) {
                badgeMain.text(count).removeClass('hidden');
                badgeNav.text(count).show();
            } else {
                badgeMain.addClass('hidden');
                badgeNav.hide();
            }
        }
    });
}

function toggleRead(id, element) {
    const row = $(element).closest('.notification-row');
    row.addClass('pointer-events-none opacity-50');

    $.ajax({
        url: '/erp217_api/toggle_read/' + id,
        type: 'POST',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                row.removeClass('pointer-events-none opacity-50');
                row.attr('data-is-read', res.is_read);
                
                if (res.is_read == 1) {
                    row.removeClass('bg-blue-50/40 dark:bg-blue-900/10 font-medium').addClass('opacity-70');
                    row.find('.status-icon-container button').html('<i class="fas fa-check-double text-gray-400 text-sm" title="<?php echo __('Вернуть статус нового'); ?>"></i>');
                } else {
                    row.addClass('bg-blue-50/40 dark:bg-blue-900/10 font-medium').removeClass('opacity-70');
                    row.find('.status-icon-container button').html('<i class="fas fa-circle text-blue-500 text-[10px]" title="<?php echo __('Пометить как прочитанное'); ?>"></i>');
                }
                updateBadge();
            } else {
                row.removeClass('pointer-events-none opacity-50');
            }
        },
        error: function() {
            row.removeClass('pointer-events-none opacity-50');
        }
    });
}

function markAllAsRead() {
    if (!confirm('<?php echo __('Пометить все уведомления как прочитанные?'); ?>')) return;
    
    $.ajax({
        url: '/erp217_api/mark_all_read',
        type: 'POST',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                location.reload();
            }
        }
    });
}

$(document).ready(function() {
    updateBadge();
    setInterval(updateBadge, 60000);

    // Автоматическое прочтение при клике на ссылку внутри уведомления
    $('.notification-body').on('click', 'a', function(e) {
        const row = $(this).closest('.notification-row');
        const id = row.data('id');
        const isRead = parseInt(row.attr('data-is-read'));
        
        if (isRead === 0) {
            // Помечаем как прочитанное без перезагрузки перед переходом по ссылке
            $.ajax({
                url: '/erp217_api/toggle_read/' + id,
                type: 'POST',
                async: false // Важно для корректного срабатывания перед переходом
            });
        }
    });
});
</script>