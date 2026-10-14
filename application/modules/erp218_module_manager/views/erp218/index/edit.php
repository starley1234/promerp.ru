<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
            Редактирование модуля: <span class="block sm:inline-block mt-1 sm:mt-0"><?php echo HTML::chars($module->display_name); ?></span>
        </h2>
        <a href="<?php echo Route::url('erp218_module_manager_admin', ['controller' => 'index', 'action' => 'view', 'id' => $module->id]); ?>" class="btn btn-secondary w-full sm:w-auto">
            <i class="fas fa-arrow-left mr-2"></i> Назад к просмотру
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 sm:p-8 card-shadow">
        <form id="moduleEditForm" method="POST" action="<?php echo Route::url('erp218_module_manager_admin', ['controller' => 'index', 'action' => 'edit', 'id' => $module->id]); ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Display Name -->
                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Название модуля <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="display_name" id="display_name"
                           class="form-input w-full p-2.5 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo HTML::chars($module->display_name); ?>"
                           required>
                </div>

                <!-- Main Page URI -->
                <div>
                    <label for="main_page_uri" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        URI главной страницы
                    </label>
                    <input type="text" name="main_page_uri" id="main_page_uri"
                           class="form-input w-full p-2.5 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo HTML::chars($module->main_page_uri); ?>"
                           placeholder="например: erp/dashboard">
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Относительный путь к основной странице модуля.
                    </p>
                </div>

                <!-- System Name (Read-only) -->
                <div>
                    <label for="system_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Системное имя (папка)
                    </label>
                    <input type="text" id="system_name"
                           class="form-input w-full p-2.5 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400 cursor-not-allowed"
                           value="<?php echo HTML::chars($module->system_name); ?>"
                           readonly>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Это имя папки модуля и не может быть изменено вручную.
                    </p>
                </div>

                <!-- Version (Read-only) -->
                <div>
                    <label for="version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Текущая версия
                    </label>
                    <input type="text" id="version"
                           class="form-input w-full p-2.5 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400 cursor-not-allowed"
                           value="<?php echo HTML::chars($module->version); ?>"
                           readonly>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Версия обновляется через историю изменений.
                    </p>
                </div>

                <!-- Status (Read-only) -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Статус
                    </label>
                    <input type="text" id="status"
                           class="form-input w-full p-2.5 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400 cursor-not-allowed"
                           value="<?php echo ucfirst(HTML::chars($module->status)); ?>"
                           readonly>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Статус изменяется отдельно через действия.
                    </p>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-6">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Описание функционала
                </label>
                <textarea name="description" id="description" rows="6"
                          class="form-textarea w-full p-2.5 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500"><?php echo HTML::chars($module->description); ?></textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Подробное описание назначения и возможностей модуля.
                </p>
            </div>

            <!-- Developer Notes -->
            <div class="mt-6">
                <label for="developer_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Заметки разработчика
                </label>
                <textarea name="developer_notes" id="developer_notes" rows="4"
                          class="form-textarea w-full p-2.5 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500"><?php echo HTML::chars($module->developer_notes); ?></textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Технические заметки, особенности внедрения, важные предупреждения для других разработчиков.
                </p>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row justify-end gap-4">
                <a href="<?php echo Route::url('erp218_module_manager_admin', ['controller' => 'index', 'action' => 'view', 'id' => $module->id]); ?>" class="btn btn-secondary w-full sm:w-auto">
                    Отмена
                </a>
                <button type="submit" class="btn btn-primary w-full sm:w-auto">
                    <i class="fas fa-save mr-2"></i> Сохранить изменения
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('moduleEditForm');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const actionUrl = form.action;

            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Для Kohana, чтобы определить AJAX-запрос
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    // Используем вашу систему уведомлений
                    if (typeof showNotification === 'function') {
                        showNotification(data.message, 'success');
                    } else {
                        alert(data.message); // Временное решение
                    }
                    window.location.href = '<?php echo Route::url('erp218_module_manager_admin', ['controller' => 'index', 'action' => 'view', 'id' => $module->id]); ?>';
                } else {
                    if (typeof showNotification === 'function') {
                        showNotification('Ошибка: ' + data.message, 'error');
                    } else {
                        alert('Ошибка: ' + data.message); // Временное решение
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Произошла ошибка при сохранении.', 'error');
                } else {
                    alert('Произошла ошибка при сохранении.'); // Временное решение
                }
            });
        });
    });
</script>