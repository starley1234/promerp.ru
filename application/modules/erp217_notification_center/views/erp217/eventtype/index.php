<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="max-w-8xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Типы событий уведомлений</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Управление справочником системных событий для центра уведомлений.</p>
            </div>
            <button data-call="openEventTypeModal" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <i class="fas fa-plus mr-2"></i> Добавить тип события
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <th scope="col" class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">ID / Алиас</th>
                            <th scope="col" class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Название и описание</th>
                            <th scope="col" class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Роли</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors" id="row-<?php echo $item->id; ?>">
                                <td class="px-6 py-4">
                                    <div class="text-xs font-mono text-gray-400">#<?php echo $item->id; ?></div>
                                    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400"><?php echo HTML::chars($item->alias); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($item->name); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo HTML::chars($item->description); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($item->allowed_roles): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach (explode(',', $item->allowed_roles) as $role): ?>
                                                <span class="px-2 py-0.5 text-[10px] font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    <?php echo HTML::chars(trim($role)); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">Все роли</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button data-call="editEventType" data-call-args="[<?php echo $item->id; ?>]" class="text-gray-400 hover:text-blue-500 transition-colors" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button data-call="deleteEventType" data-call-args="[<?php echo $item->id; ?>]" class="text-gray-400 hover:text-red-500 transition-colors" title="Удалить">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400 italic">
                                    Типы событий не найдены.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="eventTypeModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="eventTypeForm">
                <input type="hidden" name="id" id="field-id">
                <div class="px-6 py-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4" id="modalTitle">Новый тип события</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Алиас (уникальный код)</label>
                            <input type="text" name="alias" id="field-alias" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Название</label>
                            <input type="text" name="name" id="field-name" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Описание</label>
                            <textarea name="description" id="field-description" rows="3" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Разрешенные роли (через запятую)</label>
                            <input type="text" name="allowed_roles" id="field-allowed_roles" placeholder="admin, manager" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex justify-end space-x-3">
                    <button type="button" data-call="closeEventTypeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">Отмена</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEventTypeModal() {
    $('#eventTypeForm')[0].reset();
    $('#field-id').val('');
    $('#modalTitle').text('Новый тип события');
    $('#eventTypeModal').removeClass('hidden');
}

function closeEventTypeModal() {
    $('#eventTypeModal').addClass('hidden');
}

function editEventType(id) {
    $.get('/erp217_eventtype/ajax_get', { id: id }, function(response) {
        if (response.status === 'success') {
            $('#field-id').val(response.data.id);
            $('#field-alias').val(response.data.alias);
            $('#field-name').val(response.data.name);
            $('#field-description').val(response.data.description);
            $('#field-allowed_roles').val(response.data.allowed_roles);
            $('#modalTitle').text('Редактирование события');
            $('#eventTypeModal').removeClass('hidden');
        }
    }, 'json');
}

function deleteEventType(id) {
    if (!confirm('Вы уверены, что хотите удалить этот тип события?')) return;
    
    $.post('/erp217_eventtype/ajax_delete', { id: id }, function(response) {
        if (response.status === 'success') {
            $(`#row-${id}`).fadeOut(300, function() { $(this).remove(); });
            if (typeof showNotification === 'function') showNotification(response.message, 'success');
        }
    }, 'json');
}

$('#eventTypeForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    
    $.post('/erp217_eventtype/ajax_save', formData, function(response) {
        if (response.status === 'success') {
            closeEventTypeModal();
            if (typeof showNotification === 'function') showNotification(response.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else if (response.errors) {
            alert('Ошибка валидации: ' + Object.values(response.errors).join(', '));
        } else {
            alert(response.message || 'Произошла ошибка');
        }
    }, 'json');
});
</script>