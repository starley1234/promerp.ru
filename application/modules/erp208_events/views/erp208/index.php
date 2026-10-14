<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Левая колонка: Статистика и быстрые действия -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-blue-500"></i> Состояние шины
                </h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Всего событий</span>
                        <span class="text-xl font-bold text-blue-600 dark:text-blue-400"><?php echo $total_events ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Активных подписок</span>
                        <span class="text-xl font-bold text-green-600 dark:text-green-400"><?php echo $active_subscribers ?? 0; ?></span>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <a href="/erp208_events/create_event" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i> Опубликовать событие
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-plug mr-2 text-amber-500"></i> Быстрые ссылки
                </h2>
                <nav class="space-y-2">
                    <a href="/erp208_events/list_subscribers" class="flex items-center p-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors">
                        <i class="fas fa-users-cog w-6"></i> Управление подписчиками
                    </a>
                    <a href="/erp208_events/list_events" class="flex items-center p-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors">
                        <i class="fas fa-list-ul w-6"></i> Полный лог событий
                    </a>
                    <a href="/erp208_events/settings" class="flex items-center p-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors">
                        <i class="fas fa-cog w-6"></i> Настройки модуля
                    </a>
                </nav>
            </div>
        </div>

        <!-- Правая колонка: Последние события -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h2 class="text-lg font-bold">Последние события в системе</h2>
                    <button data-action="reload" class="text-gray-400 hover:text-blue-500 transition-colors">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-6 py-3 font-medium">Событие</th>
                                <th scope="col" class="px-6 py-3 font-medium">Инициатор</th>
                                <th scope="col" class="px-6 py-3 font-medium">Дата и время</th>
                                <th scope="col" class="px-6 py-3 font-medium text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if (!empty($latest_events)): ?>
                                <?php foreach ($latest_events as $event): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="font-mono text-sm text-blue-600 dark:text-blue-400"><?php echo HTML::chars($event->event_name); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <?php echo $event->initiator_id ? 'User ID: '.$event->initiator_id : '<span class="text-gray-400">System</span>'; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('d.m.Y H:i:s', strtotime($event->created_at)); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button data-call="showEventDetails" data-call-args="[<?php echo $event->id; ?>]" class="text-gray-400 hover:text-blue-500">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-ghost mb-2 text-2xl block"></i>
                                        Событий пока не зафиксировано
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700">
                    <a href="/erp208_events/list_events" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                        Смотреть всю историю <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для деталей (пример реализации) -->
<div id="eventModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-white" id="modal-title">Детали события</h3>
                <div class="mt-4">
                    <pre id="eventPayload" class="text-xs bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-700 dark:text-gray-300"></pre>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" data-call="closeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
function showEventDetails(id) {
    // В реальном приложении здесь будет AJAX запрос
    document.getElementById('eventPayload').innerText = "// Загрузка данных для ID: " + id + "...\n{\n  \"status\": \"success\",\n  \"module\": \"erp208\",\n  \"action\": \"view_details\"\n}";
    document.getElementById('eventModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('eventModal').classList.add('hidden');
}
</script>