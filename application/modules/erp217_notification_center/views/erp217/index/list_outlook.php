<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="flex h-[calc(100vh-120px)] bg-white dark:bg-gray-900 overflow-hidden border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg">
    <!-- Левая колонка: Список сообщений -->
    <div class="w-full md:w-1/3 lg:w-1/4 flex flex-col border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <!-- Заголовок и поиск -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-inbox mr-2 text-blue-500"></i>
                    <?php echo __('Входящие'); ?>
                    <span id="unread-badge-main" class="ml-2 bg-blue-100 text-blue-600 text-xs font-bold px-2 py-0.5 rounded-full hidden">0</span>
                </h1>
                <div class="flex gap-1">
                    <button data-call="markAllAsRead" title="<?php echo __('Прочитать всё'); ?>" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-500 transition-colors">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <a href="/erp217_settings" title="<?php echo __('Настройки'); ?>" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-500 transition-colors">
                        <i class="fas fa-cog"></i>
                    </a>
                </div>
            </div>
            
            <form id="filter-form" method="get" class="relative">
                <input type="text" name="subject" value="<?php echo HTML::chars(Arr::get($filters, 'subject')); ?>" 
                       placeholder="<?php echo __('Поиск...'); ?>"
                       class="w-full pl-9 pr-4 py-2 bg-gray-100 dark:bg-gray-900 border-transparent focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-blue-500 rounded-lg text-sm transition-all">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                <input type="hidden" name="unread" value="<?php echo Arr::get($filters, 'unread'); ?>">
            </form>

            <div class="flex mt-3 gap-2">
                <button data-call="setUnreadFilter" data-call-args='["" ]' class="flex-1 py-1 text-xs font-medium rounded-md <?php echo !Arr::get($filters, 'unread') ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'; ?>">
                    <?php echo __('Все'); ?>
                </button>
                <button data-call="setUnreadFilter" data-call-args='["1"]' class="flex-1 py-1 text-xs font-medium rounded-md <?php echo Arr::get($filters, 'unread') == '1' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'; ?>">
                    <?php echo __('Непрочитанные'); ?>
                </button>
            </div>
        </div>

        <!-- Список -->
        <div class="flex-1 overflow-y-auto custom-scrollbar">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $item): ?>
                    <div class="notification-item cursor-pointer border-b border-gray-100 dark:border-gray-700/50 p-4 hover:bg-white dark:hover:bg-gray-800 transition-all relative <?php echo $item->is_read == 0 ? 'border-l-4 border-l-blue-500 bg-blue-50/30 dark:bg-blue-900/10' : 'opacity-80'; ?>"
                         data-call="loadMessage" data-call-args="[<?php echo $item->id; ?>]"
                         data-id="<?php echo $item->id; ?>">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                <i class="fas <?php echo $item->channel == 'telegram' ? 'fa-paper-plane' : ($item->channel == 'push' ? 'fa-bell' : 'fa-envelope'); ?> mr-1"></i>
                                <?php echo $item->channel; ?>
                            </span>
                            <span class="text-[10px] text-gray-400">
                                <?php echo date('H:i', strtotime($item->created_at)); ?>
                            </span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate pr-4">
                            <?php echo HTML::chars($item->subject); ?>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mt-1">
                            <?php 
                                $payload = json_decode($item->payload, true);
                                echo HTML::chars(isset($payload['body']) ? $payload['body'] : $item->payload); 
                            ?>
                        </p>
                        <?php if ($item->is_read == 0): ?>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-500 rounded-full unread-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-400">
                    <i class="fas fa-folder-open text-4xl mb-3 opacity-20"></i>
                    <p class="text-sm"><?php echo __('Нет сообщений'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($pagination->total_items > $pagination->items_per_page): ?>
            <div class="p-2 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Правая колонка: Просмотр сообщения -->
    <div id="message-viewer" class="hidden md:flex flex-1 flex-col bg-white dark:bg-gray-900">
        <div id="viewer-empty" class="flex-1 flex flex-col items-center justify-center text-gray-400">
            <div class="w-24 h-24 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-envelope-open text-4xl opacity-20"></i>
            </div>
            <p><?php echo __('Выберите сообщение для просмотра'); ?></p>
        </div>

        <div id="viewer-content" class="hidden flex-1 flex-col overflow-hidden">
            <!-- Шапка сообщения -->
            <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                <div class="flex justify-between items-start mb-6">
                    <h2 id="msg-subject" class="text-2xl font-bold text-gray-900 dark:text-white"></h2>
                    <div class="flex gap-2">
                        <button data-call="toggleCurrentRead" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-500" title="<?php echo __('Отметить как непрочитанное'); ?>">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <button data-call="closeMessage" class="md:hidden p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold mr-3">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">System Notification</div>
                        <div class="text-xs text-gray-500" id="msg-date"></div>
                    </div>
                </div>
            </div>
            <!-- Тело сообщения -->
            <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                <div id="msg-body" class="prose dark:prose-invert max-w-none text-gray-800 dark:text-gray-200">
                    <!-- Сюда подгружается контент -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
    .notification-item.active { background-color: white; box-shadow: inset 4px 0 0 0 #3b82f6; }
    .dark .notification-item.active { background-color: #1e293b; }
</style>

<script>
let currentMsgId = null;

function updateBadge() {
    $.get('/erp217_api/unread_count', function(res) {
        const count = parseInt(res.count) || 0;
        const badge = $('#unread-badge-main');
        if (count > 0) {
            badge.text(count).removeClass('hidden');
            $('#notification-badge').text(count).show();
        } else {
            badge.addClass('hidden');
            $('#notification-badge').hide();
        }
    });
}

function loadMessage(id, el) {
    currentMsgId = id;
    $('.notification-item').removeClass('active bg-white dark:bg-gray-800');
    $(el).addClass('active');

    $('#viewer-empty').addClass('hidden');
    $('#viewer-content').removeClass('hidden').addClass('flex').css('opacity', '0.5');

    $.get('/erp217_api/get_message_body/' + id, function(res) {
        if (res.status === 'success') {
            $('#msg-subject').text(res.data.subject);
            $('#msg-body').html(res.data.body || '<p class="italic text-gray-400">Нет содержимого</p>');
            $('#msg-date').text($(el).find('.text-\\[10px\\]:last').text());
            $('#viewer-content').css('opacity', '1');
            
            // Если сообщение было непрочитанным, помечаем как прочитанное
            if ($(el).hasClass('border-l-blue-500')) {
                markAsRead(id, el);
            }
        }
    });
}

function markAsRead(id, el) {
    $.post('/erp217_api/toggle_read/' + id, function(res) {
        if (res.status === 'success' && res.is_read == 1) {
            $(el).removeClass('border-l-4 border-l-blue-500 bg-blue-50/30 dark:bg-blue-900/10 opacity-100').addClass('opacity-80');
            $(el).find('.unread-dot').remove();
            updateBadge();
        }
    });
}

function toggleCurrentRead() {
    if (!currentMsgId) return;
    $.post('/erp217_api/toggle_read/' + currentMsgId, function(res) {
        location.reload();
    });
}

function markAllAsRead() {
    if (!confirm('<?php echo __('Пометить все как прочитанные?'); ?>')) return;
    $.post('/erp217_api/mark_all_read', function() {
        location.reload();
    });
}

function setUnreadFilter(val) {
    const form = $('#filter-form');
    form.find('input[name="unread"]').val(val);
    form.submit();
}

function closeMessage() {
    $('#viewer-content').addClass('hidden').removeClass('flex');
    $('#viewer-empty').removeClass('hidden');
    $('.notification-item').removeClass('active');
}

$(document).ready(function() {
    updateBadge();
    // Авто-выбор первого сообщения на десктопе если есть
    if ($(window).width() > 768) {
        $('.notification-item').first().click();
    }
});
</script>