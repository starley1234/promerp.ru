<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_filter input {
        @apply bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white;
        margin-bottom: 1rem;
    }
    .dataTables_wrapper .dataTables_length select {
        @apply bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-1.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white;
    }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate {
        @apply text-sm text-gray-500 dark:text-gray-400 mt-4;
    }
    table.dataTable { border-collapse: collapse !important; }
    table.dataTable thead th { border-bottom: 1px solid #e5e7eb !important; }
    .dark table.dataTable thead th { border-bottom: 1px solid #374151 !important; }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Локальный провайдер (LM Studio)</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Управление моделями и мониторинг состояния API</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300"><?php echo HTML::chars($base_url); ?></span>
            </div>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                    <div>
                        <h3 class="text-sm font-bold text-red-800 dark:text-red-400">Ошибка соединения</h3>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1"><?php echo HTML::chars($error); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table id="modelsTable" class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                <th scope="col" class="px-4 py-3">Модель</th>
                                <th scope="col" class="px-4 py-3">Архитектура / Параметры</th>
                                <th scope="col" class="px-4 py-3">Контекст</th>
                                <th scope="col" class="px-4 py-3">Размер / Квантование</th>
                                <th scope="col" class="px-4 py-3 text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if (isset($status_data['models']) && !empty($status_data['models'])): 
                                $models = $status_data['models'];
                            ?>
                                <?php foreach ($models as $model): ?>
                                    <tr id="model-row-<?php echo md5($model['key']); ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors <?php echo !empty($model['loaded_instances']) ? 'bg-green-50/30 dark:bg-green-900/10' : ''; ?>">
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <div class="flex items-center">
                                                    <i class="fas <?php echo ($model['type'] === 'embedding') ? 'fa-font' : 'fa-robot'; ?> <?php echo !empty($model['loaded_instances']) ? 'text-green-500' : 'text-blue-500'; ?> mr-3"></i>
                                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($model['display_name'] ?? $model['key']); ?></span>
                                                    <?php if (!empty($model['loaded_instances'])): ?>
                                                        <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400 text-[10px] font-bold rounded-full uppercase tracking-wide">Loaded</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center mt-1 ml-7 group">
                                                    <span class="text-[10px] text-gray-400 truncate max-w-[200px]"><?php echo HTML::chars($model['key']); ?></span>
                                                    <button data-call="copyToClipboard" data-call-args="<?php echo HTML::chars(json_encode(array((string) $model['key']), JSON_UNESCAPED_UNICODE)); ?>" class="ml-2 text-gray-400 hover:text-blue-500 transition-colors" title="Копировать ID">
                                                        <i class="far fa-copy text-[10px]"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <span class="uppercase"><?php echo HTML::chars($model['architecture'] ?? 'n/a'); ?></span>
                                                <?php if (!empty($model['params_string'])): ?>
                                                    <span class="ml-2 px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px]"><?php echo HTML::chars($model['params_string']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex gap-1 mt-1">
                                                <?php if ($model['capabilities']['vision'] ?? false): ?>
                                                    <span title="Vision" class="text-blue-400 text-[10px]"><i class="fas fa-eye"></i></span>
                                                <?php endif; ?>
                                                <?php if ($model['capabilities']['trained_for_tool_use'] ?? false): ?>
                                                    <span title="Tools" class="text-green-400 text-[10px]"><i class="fas fa-tools"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                            <?php echo number_format($model['max_context_length'] ?? 0, 0, '.', ' '); ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo round(($model['size_bytes'] ?? 0) / 1024 / 1024 / 1024, 2); ?> GB
                                            </div>
                                            <div class="text-[10px] text-gray-500">
                                                <?php echo HTML::chars($model['quantization']['name'] ?? 'unknown'); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <?php if (!empty($model['loaded_instances'])): ?>
                                                <button 
                                                   data-call="unloadModel" data-call-args="<?php echo HTML::chars(json_encode(array((string) $model['key'], md5($model['key'])), JSON_UNESCAPED_UNICODE)); ?>"
                                                   class="unload-btn inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors">
                                                    <i class="fas fa-eject mr-1.5"></i> <span>Выгрузить</span>
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                   data-call="loadModel" data-call-args="<?php echo HTML::chars(json_encode(array((string) $model['key'], md5($model['key'])), JSON_UNESCAPED_UNICODE)); ?>"
                                                   class="load-btn inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 transition-colors">
                                                    <i class="fas fa-download mr-1.5"></i> <span>Загрузить</span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i> Справка по интеграции
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p>Для работы с локальными моделями используйте <strong>LM Studio</strong>.</p>
                    <p>Убедитесь, что сервер запущен и принимает запросы по адресу, указанному в конфигурации <code>llm.php</code>.</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <code class="text-xs text-pink-600 dark:text-pink-400 break-all">
                        // Config path:<br>
                        application/config/llm.php
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#modelsTable').DataTable({
        pageLength: 50,
        // order: [[0, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ru.json'
        },
        ordering: false,
        columnDefs: [
            { sorting: false, orderable: false, targets: 4 }
        ],
        dom: '<"flex flex-col sm:flex-row justify-between items-center mb-4"f l>rtip'
    });
});

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        icon.classList.remove('far', 'fa-copy');
        icon.classList.add('fas', 'fa-check', 'text-green-500');
        
        if (window.showNotification) {
            window.showNotification('ID модели скопирован', 'success');
        }

        setTimeout(() => {
            icon.classList.remove('fas', 'fa-check', 'text-green-500');
            icon.classList.add('far', 'fa-copy');
        }, 2000);
    });
}

function replaceModelAction(actionCell, action, modelKey, rowId) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.call = action === 'load' ? 'loadModel' : 'unloadModel';
    button.dataset.callArgs = JSON.stringify([modelKey, rowId]);
    button.className = (action === 'load' ? 'load-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20' : 'unload-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20') + ' inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg transition-colors';
    const icon = document.createElement('i');
    icon.className = 'fas ' + (action === 'load' ? 'fa-download' : 'fa-eject') + ' mr-1.5';
    icon.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.textContent = action === 'load' ? 'Загрузить' : 'Выгрузить';
    button.append(icon, label);
    actionCell.replaceChildren(button);
}

function unloadModel(modelKey, rowId) {
    if (!confirm('Вы уверены, что хотите выгрузить модель ' + modelKey + ' из памяти?')) {
        return;
    }

    const row = document.getElementById('model-row-' + rowId);
    const btn = row.querySelector('.unload-btn');
    const originalContent = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Выгрузка...';

    fetch('<?php echo Route::url("default", ["controller" => "llm_admin", "action" => "unload"]); ?>?model=' + encodeURIComponent(modelKey), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const actionCell = btn.parentElement;
            replaceModelAction(actionCell, 'load', modelKey, rowId);
            row.classList.remove('bg-green-50/30', 'dark:bg-green-900/10');
            const icon = row.querySelector('.fa-robot, .fa-font');
            if (icon) {
                icon.classList.remove('text-green-500');
                icon.classList.add('text-blue-500');
            }
            const badge = row.querySelector('.font-bold.uppercase.tracking-wide');
            if (badge) badge.remove();

            if (window.showNotification) {
                window.showNotification('Модель успешно выгружена', 'success');
            }
        } else {
            alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка при отправке запроса');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

function loadModel(modelKey, rowId) {
    if (!confirm('Вы уверены, что хотите загрузить модель ' + modelKey + ' в память?')) {
        return;
    }

    const row = document.getElementById('model-row-' + rowId);
    const btn = row.querySelector('.load-btn');
    const originalContent = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Загрузка...';

    fetch('<?php echo Route::url("default", ["controller" => "llm_admin", "action" => "load"]); ?>?model=' + encodeURIComponent(modelKey), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const actionCell = btn.parentElement;
            replaceModelAction(actionCell, 'unload', modelKey, rowId);
            row.classList.add('bg-green-50/30', 'dark:bg-green-900/10');
            const icon = row.querySelector('.fa-robot, .fa-font');
            if (icon) {
                icon.classList.remove('text-blue-500');
                icon.classList.add('text-green-500');
            }
            const nameSpan = row.querySelector('.font-medium');
            if (nameSpan) {
                nameSpan.insertAdjacentHTML('afterend', '<span class="ml-2 px-2 py-0.5 bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400 text-[10px] font-bold rounded-full uppercase tracking-wide">Loaded</span>');
            }

            if (window.showNotification) {
                window.showNotification('Модель успешно загружена', 'success');
            }
        } else {
            alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка при отправке запроса');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}
</script>