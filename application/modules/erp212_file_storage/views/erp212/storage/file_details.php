<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'index']); ?>" class="text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                            <i class="fas fa-folder-open mr-2"></i>Хранилище
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Детали файла</span>
                        </div>
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white truncate max-w-2xl">
                <?php echo HTML::chars($file->filename); ?>
            </h1>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?php echo Route::url('default', ['controller' => 'erp212_download', 'action' => 'file', 'id' => $file->id]); ?>" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <i class="fas fa-download mr-2"></i> Скачать
            </a>
            <?php if ($file->can_write(Auth::instance()->get_user()->id)): ?>
                <a href="/erp212_storage/edit/<?php echo HTML::chars($file->id); ?>" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                    <i class="fas fa-edit mr-2"></i> Редактировать
                </a>
                <button data-call="deleteFile" data-call-args="[<?php echo $file->id; ?>]" class="inline-flex items-center px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors border border-red-200 dark:border-red-800/50">
                    <i class="fas fa-trash-alt mr-2"></i> Удалить
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Основная информация -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i> Свойства файла
                    </h2>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">MIME-тип</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white flex items-center">
                                <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs font-mono mr-2"><?php echo $file->mime_type; ?></span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Размер</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo Text::bytes($file->file_size); ?> (<?php echo number_format($file->file_size, 0, '.', ' '); ?> байт)</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Дата создания</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo date('d.m.Y H:i:s', strtotime($file->created_at)); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Владелец</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                <?php if ($file->user->loaded()): ?>
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-[10px] font-bold text-blue-600 mr-2">
                                            <?php echo strtoupper(substr($file->user->username, 0, 1)); ?>
                                        </div>
                                        <?php echo HTML::chars($file->user->username); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">Системный</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Хэш (SHA256)</dt>
                            <dd class="mt-1 text-xs font-mono text-gray-600 dark:text-gray-400 break-all bg-gray-50 dark:bg-gray-900/50 p-2 rounded border border-gray-100 dark:border-gray-700">
                                <?php echo $file->hash ?: 'Не вычислен'; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Бэкенд хранилища</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                <span class="inline-flex items-center">
                                    <i class="fas fa-server mr-2 text-gray-400"></i>
                                    <?php echo HTML::chars($file->backend->name); ?> (<?php echo $file->backend->driver; ?>)
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Статус доступа</dt>
                            <dd class="mt-1">
                                <?php if ($file->is_public): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        <i class="fas fa-globe mr-1"></i> Публичный
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        <i class="fas fa-lock mr-1"></i> Приватный
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Секция предпросмотра (если применимо) -->
            <?php if (strpos($file->mime_type, 'image/') === 0): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Предпросмотр</h2>
                    </div>
                    <div class="p-6 flex justify-center bg-gray-100 dark:bg-gray-900/50">
                        <img src="<?php echo Route::url('default', ['controller' => 'erp212_download', 'action' => 'file', 'id' => $file->id]); ?>" 
                             alt="<?php echo HTML::chars($file->filename); ?>" 
                             class="max-w-full h-auto rounded shadow-lg">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Боковая панель: Ссылки и Статистика -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-link mr-2 text-green-500"></i> Временные ссылки
                    </h2>
                </div>
                <div class="p-6">
                    <?php $stats = $file->get_links_stats(); ?>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['active_links']; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Активных</div>
                        </div>
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo $stats['total_clicks']; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Переходов</div>
                        </div>
                    </div>
                    
                    <button class="w-full py-2 px-4 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-plus-circle mr-2"></i> Создать ссылку
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-user-shield mr-2 text-purple-500"></i> Доступ
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Права доступа (ACL)</span>
                            <span class="text-xs font-medium px-2 py-1 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 rounded">
                                <?php echo count($file->permissions->find_all()); ?> записей
                            </span>
                        </div>
                        <button data-call="manageAccess" data-call-args="[<?php echo $file->id; ?>]" class="w-full py-2 px-4 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Управление доступом
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteFile(id) {
    if (confirm('Вы уверены, что хотите удалить этот файл?')) {
        fetch('/erp212_api/delete/' + id, {
            method: 'DELETE',
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'index']); ?>';
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось удалить файл'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при удалении файла');
        });
    }
}

function manageAccess(id) {
    // В данной реализации перенаправляем на страницу управления правами или открываем модальное окно
    // Для примера реализуем переход на контроллер управления
    window.location.href = '/erp212_storage/permissions/' + id;
}
</script>