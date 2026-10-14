<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Виджет иконки уведомлений для шапки сайта.
 * @var int $unread_count Количество непрочитанных уведомлений
 */
$unread_count = isset($unread_count) ? (int)$unread_count : 0;
?>

<div class="relative inline-block" id="erp-notification-bell-container">
    <!-- Кнопка колокольчика -->
    <button id="notificationDropdownButton" 
            class="relative p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors focus:outline-none group"
            title="Уведомления">
        
        <i class="fas fa-bell text-xl group-hover:animate-ring"></i>
        
        <!-- Индикатор количества (Badge) -->
        <span id="notification-badge" 
              class="<?php echo ($unread_count > 0) ? '' : 'hidden'; ?> absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white border-2 border-white dark:border-gray-800">
            <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
        </span>
    </button>

    <!-- Выпадающее меню уведомлений -->
    <div id="notificationDropdownMenu" 
         class="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 hidden opacity-0 transition-all duration-200 transform scale-95 origin-top-right z-50">
        
        <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Уведомления</h3>
            <a href="/notification_center/settings" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                <i class="fas fa-cog mr-1"></i>Настройки
            </a>
        </div>

        <!-- Контейнер для списка уведомлений (заполняется динамически) -->
        <div id="notification-list" class="max-height-[400px] overflow-y-auto custom-scrollbar">
            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mb-2 text-xl"></i>
                <p class="text-xs">Загрузка...</p>
            </div>
        </div>

        <div class="p-3 border-t border-gray-100 dark:border-gray-700 text-center">
            <a href="/notification_center" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 transition-colors">
                Показать все
            </a>
        </div>
    </div>
</div>

<style>
    /* Анимация покачивания колокольчика при наведении */
    @keyframes ring {
        0% { transform: rotate(0); }
        10% { transform: rotate(15deg); }
        20% { transform: rotate(-15deg); }
        30% { transform: rotate(10deg); }
        40% { transform: rotate(-10deg); }
        50% { transform: rotate(5deg); }
        60% { transform: rotate(-5deg); }
        100% { transform: rotate(0); }
    }
    .group-hover\:animate-ring:hover {
        animation: ring 0.8s ease-in-out;
    }
    
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.getElementById('notificationDropdownButton');
    const bellMenu = document.getElementById('notificationDropdownMenu');

    if (bellBtn && bellMenu) {
        bellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = bellMenu.classList.contains('hidden');
            
            // Закрываем другие открытые меню (например, пользовательское), если нужно
            // closeOtherDropdowns(); 

            if (isHidden) {
                bellMenu.classList.remove('hidden');
                setTimeout(() => {
                    bellMenu.classList.remove('opacity-0', 'scale-95');
                }, 10);
                // Здесь можно вызвать функцию подгрузки последних уведомлений через AJAX
                // loadRecentNotifications();
            } else {
                closeBellDropdown();
            }
        });

        document.addEventListener('click', (e) => {
            if (!bellMenu.contains(e.target)) closeBellDropdown();
        });
    }

    function closeBellDropdown() {
        if (!bellMenu || bellMenu.classList.contains('hidden')) return;
        bellMenu.classList.add('opacity-0', 'scale-95');
        setTimeout(() => bellMenu.classList.add('hidden'), 200);
    }
});
</script>