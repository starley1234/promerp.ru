<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
            <i class="fas fa-bell mr-2 text-blue-500 text-sm"></i>
            <?php echo __('Уведомления'); ?>
            <span id="unread-count-compact" class="ml-2 text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full hidden"></span>
        </h1>
        <div class="flex items-center space-x-2">
            <button data-call="markAllAsRead" class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md transition-colors" title="<?php echo __('Прочитать всё'); ?>">
                <i class="fas fa-check-double text-sm"></i>
            </button>
            <a href="/erp217_settings" class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md transition-colors">
                <i class="fas fa-cog text-sm"></i>
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $item): ?>
                    <?php $is_unread = ($item->is_read == 0); ?>
                    <div class="notification-item flex items-start p-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer <?php echo $is_unread ? 'bg-blue-50/30 dark:bg-blue-900/5' : ''; ?>" 
                         data-call="toggleReadCompact" data-call-args="[<?php echo $item->id; ?>]"
                         data-id="<?php echo $item->id; ?>">
                        
                        <div class="mt-1 mr-3 flex-shrink-0">
                            <?php 
                                $icon = 'fa-envelope';
                                $color = 'text-blue-400';
                                if ($item->channel == 'telegram') { $icon = 'fa-paper-plane'; $color = 'text-sky-400'; }
                                elseif ($item->channel == 'push') { $icon = 'fa-bell'; $color = 'text-purple-400'; }
                            ?>
                            <i class="fas <?php echo $icon; ?> <?php echo $color; ?> text-xs"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline mb-0.5">
                                <h4 class="text-sm <?php echo $is_unread ? 'font-bold text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400'; ?> truncate pr-2">
                                    <?php echo HTML::chars($item->subject); ?>
                                </h4>
                                <span class="text-[10px] text-gray-400 whitespace-nowrap">
                                    <?php echo date('H:i', strtotime($item->created_at)); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-500 line-clamp-1">
                                <?php 
                                    $payload = json_decode($item->payload, true);
                                    echo HTML::chars(isset($payload['body']) ? $payload['body'] : $item->payload); 
                                ?>
                            </p>
                        </div>

                        <?php if ($is_unread): ?>
                            <div class="ml-2 mt-1.5 flex-shrink-0">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-10 text-center">
                    <p class="text-sm text-gray-400"><?php echo __('Нет новых уведомлений'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pagination->total_items > $pagination->items_per_page): ?>
            <div class="p-2 bg-gray-50 dark:bg-gray-800/30 border-t border-gray-100 dark:border-gray-800">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleReadCompact(id, el) {
    $.ajax({
        url: '/erp217_api/toggle_read/' + id,
        type: 'POST',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                if (res.is_read == 1) {
                    $(el).removeClass('bg-blue-50/30 dark:bg-blue-900/5');
                    $(el).find('h4').removeClass('font-bold text-gray-900 dark:text-white').addClass('text-gray-600 dark:text-gray-400');
                    $(el).find('.bg-blue-500.rounded-full').remove();
                } else {
                    location.reload();
                }
                updateBadgeCompact();
            }
        }
    });
}

function updateBadgeCompact() {
    $.get('/erp217_api/unread_count', function(res) {
        const count = parseInt(res.count) || 0;
        const badge = $('#unread-count-compact');
        if (count > 0) {
            badge.text(count).removeClass('hidden');
        } else {
            badge.addClass('hidden');
        }
    });
}

function markAllAsRead() {
    $.post('/erp217_api/mark_all_read', function() {
        location.reload();
    });
}

$(document).ready(function() {
    updateBadgeCompact();
});
</script>