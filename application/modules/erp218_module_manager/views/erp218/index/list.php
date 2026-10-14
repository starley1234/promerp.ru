<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Фильтры и Действия -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="moduleSearch" placeholder="Поиск модуля..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
            </div>

            <div class="flex items-center gap-2">
                <label for="visibilityFilter" class="text-sm font-medium text-gray-700 dark:text-gray-300">Видимость:</label>
                <select id="visibilityFilter" data-action="navigate-value" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['visibility' => 'all']); ?>" <?php echo (!isset($filters['visibility']) || $filters['visibility'] == 'all') ? 'selected' : ''; ?>>Все</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['visibility' => 'published']); ?>" <?php echo (isset($filters['visibility']) && $filters['visibility'] == 'published') ? 'selected' : ''; ?>>Опубликованные</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['visibility' => 'hidden']); ?>" <?php echo (isset($filters['visibility']) && $filters['visibility'] == 'hidden') ? 'selected' : ''; ?>>Скрытые</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label for="sortSelect" class="text-sm font-medium text-gray-700 dark:text-gray-300">Сортировка:</label>
                <select id="sortSelect" data-action="navigate-value" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['sort' => 'sort_order', 'dir' => 'asc']); ?>" <?php echo ($filters['sort'] == 'sort_order') ? 'selected' : ''; ?>>По порядку (Drag-n-Drop)</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['sort' => 'display_name', 'dir' => 'asc']); ?>" <?php echo ($filters['sort'] == 'display_name' && $filters['dir'] == 'ASC') ? 'selected' : ''; ?>>По названию (А-Я)</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['sort' => 'display_name', 'dir' => 'desc']); ?>" <?php echo ($filters['sort'] == 'display_name' && $filters['dir'] == 'DESC') ? 'selected' : ''; ?>>По названию (Я-А)</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['sort' => 'updated_at', 'dir' => 'desc']); ?>" <?php echo ($filters['sort'] == 'updated_at') ? 'selected' : ''; ?>>Сначала новые</option>
                    <option value="<?php echo URL::site(Request::current()->uri()) . URL::query(['sort' => 'status', 'dir' => 'asc']); ?>" <?php echo ($filters['sort'] == 'status') ? 'selected' : ''; ?>>По статусу</option>
                </select>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">Всего модулей: <?php echo $pagination->total_items; ?></span>
        </div>
    </div>

    <!-- Таблица реестра -->
    <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-fixed">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="w-10 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <i class="fas fa-arrows-alt-v"></i>
                        </th>
                        <th scope="col" class="w-10 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <i class="fas fa-home"></i>
                        </th>
                        <th scope="col" class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Модуль</th>
                        <th scope="col" class="w-32 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Статус</th>
                        <th scope="col" class="w-24 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Видимость</th>
                        <th scope="col" class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Примечание разработчика</th>
                        <th scope="col" class="w-40 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Обновлен</th>
                        <th scope="col" class="w-48 relative px-6 py-3">
                            <span class="sr-only">Действия</span>
                        </th>
                    </tr>
                </thead>
                <tbody id="sortable-modules" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if ($modules->count()): ?>
                        <?php foreach ($modules as $module): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" data-module-id="<?php echo $module->id; ?>">
                                <td class="px-4 py-4 text-center <?php echo ($filters['sort'] == 'sort_order') ? 'cursor-move handle' : 'opacity-20 cursor-not-allowed'; ?> text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-grip-vertical"></i>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="main-page-link-container">
                                        <?php if (!empty($module->main_page_uri)): ?>
                                            <a href="<?php echo URL::site($module->main_page_uri); ?>" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" title="Перейти на главную страницу модуля">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <i class="fas fa-link text-gray-300 dark:text-gray-600" title="URI не указан"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <a href="/erp218_index/details/<?php echo $module->id; ?>" class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-800/50 transition-colors" title="Подробнее о модуле">
                                            <?php if (!empty($module->icon)): ?>
                                                <i class="fas <?php echo HTML::chars($module->icon); ?> text-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-box-open text-lg"></i>
                                            <?php endif; ?>
                                        </a>
                                        <div class="ml-4 overflow-hidden">
                                            <div class="text-sm font-bold text-gray-900 dark:text-white editable-field truncate" data-field="display_name" data-module-id="<?php echo $module->id; ?>" title="<?php echo HTML::chars($module->display_name); ?>">
                                                <?php echo HTML::chars($module->display_name); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                /<?php echo HTML::chars($module->system_name); ?>
                                            </div>
                                            <?php if (!empty($module->description)): ?>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 editable-field line-clamp-2" data-field="description" data-module-id="<?php echo $module->id; ?>">
                                                    <?php 
                                                        $description_words = explode(' ', HTML::chars($module->description));
                                                        echo implode(' ', array_slice($description_words, 0, 12));
                                                        if (count($description_words) > 12) {
                                                            echo '...';
                                                        }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                        $status_classes = [
                                            'alpha'    => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                            'beta'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                            'stable'   => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                            'archived' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
                                        ];
                                        $current_status = $module->status ?? 'alpha';
                                    ?>
                                    <button class="edit-status-btn inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium <?php echo $status_classes[$current_status] ?? $status_classes['alpha']; ?>"
                                            data-module-id="<?php echo $module->id; ?>"
                                            data-current-status="<?php echo HTML::chars($current_status); ?>"
                                            title="Изменить статус">
                                        <i class="fas fa-circle text-[8px] mr-1.5"></i>
                                        <span class="status-text"><?php echo ucfirst(HTML::chars($current_status)); ?></span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button class="toggle-published relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?php echo $module->is_published ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'; ?>"
                                            data-module-id="<?php echo $module->id; ?>"
                                            data-is-published="<?php echo $module->is_published ? '1' : '0'; ?>">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?php echo $module->is_published ? 'translate-x-5' : 'translate-x-0'; ?>"></span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-sm text-purple-500 dark:text-purple-400 editable-field" data-field="developer_notes" data-module-id="<?php echo $module->id; ?>">
                                    <div class="line-clamp-3">
                                        <?php if (!empty($module->developer_notes)): ?>
                                            <i class="fas fa-sticky-note mr-1"></i> <?php echo HTML::chars($module->developer_notes); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500">Нет примечаний</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d.m.Y H:i', strtotime($module->updated_at)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button class="run-ai-btn text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 p-2 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" title="Запустить LLM анализ" data-module-id="<?php echo $module->id; ?>">
                                            <i class="fas fa-robot"></i>
                                        </button>
                                        <button class="edit-notes-btn text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300 p-2 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors" title="Редактировать примечания" data-module-id="<?php echo $module->id; ?>" data-notes="<?php echo HTML::chars($module->developer_notes); ?>">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>
                                        <button class="edit-attributes-btn text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Редактировать атрибуты" 
                                            data-module-id="<?php echo $module->id; ?>" 
                                            data-display-name="<?php echo HTML::chars($module->display_name); ?>" 
                                            data-description="<?php echo HTML::chars($module->description); ?>" 
                                            data-version="<?php echo HTML::chars($module->version); ?>" 
                                            data-icon="<?php echo HTML::chars($module->icon); ?>"
                                            data-main-page-uri="<?php echo HTML::chars($module->main_page_uri); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="/erp218_index/details/<?php echo $module->id; ?>" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Документация">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                    <p class="text-gray-500 dark:text-gray-400">Модули не найдены. Запустите сканирование системы.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($pagination->total_pages > 1): ?>
        <div class="mt-6">
            <?php echo $pagination->render(); ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для редактирования примечаний -->
<div id="notesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Редактировать примечания для <span id="notesModuleName" class="font-bold text-blue-600 dark:text-blue-400"></span></h3>
            <div class="mt-2 px-7 py-3">
                <textarea id="developerNotesInput" class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" rows="5" placeholder="Введите примечания разработчика..."></textarea>
            </div>
            <div class="items-center px-4 py-3">
                <button id="saveNotesBtn" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-2 focus:ring-blue-300">
                    Сохранить
                </button>
                <button id="cancelNotesBtn" class="mt-2 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования атрибутов -->
<div id="attributesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Редактировать атрибуты модуля <span id="attributesModuleName" class="font-bold text-blue-600 dark:text-blue-400"></span></h3>
            <div class="mt-2 px-7 py-3 text-left">
                <div class="mb-4">
                    <label for="displayNameInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Название</label>
                    <input type="text" id="displayNameInput" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                </div>
                <div class="mb-4">
                    <label for="descriptionInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Описание</label>
                    <textarea id="descriptionInput" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" rows="3"></textarea>
                </div>
                <div class="mb-4">
                    <label for="versionInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Версия</label>
                    <input type="text" id="versionInput" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                </div>
                <div class="mb-4">
                    <label for="iconInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Иконка (Font Awesome класс)</label>
                    <input type="text" id="iconInput" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" placeholder="Например: fas fa-cog">
                </div>
                <div class="mb-4">
                    <label for="mainPageUriInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Main Page URI</label>
                    <input type="text" id="mainPageUriInput" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" placeholder="Например: erp218_index">
                </div>
            </div>
            <div class="items-center px-4 py-3">
                <button id="saveAttributesBtn" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Сохранить
                </button>
                <button id="cancelAttributesBtn" class="mt-2 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для изменения статуса -->
<div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-sm shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Изменить статус модуля <span id="statusModuleName" class="font-bold text-blue-600 dark:text-blue-400"></span></h3>
            <div class="mt-2 px-7 py-3 text-left">
                <label for="statusSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Выберите статус</label>
                <select id="statusSelect" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                    <option value="alpha">Alpha</option>
                    <option value="beta">Beta</option>
                    <option value="stable">Stable</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="items-center px-4 py-3">
                <button id="saveStatusBtn" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Сохранить
                </button>
                <button id="cancelStatusBtn" class="mt-2 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(document).ready(function() {
    // Инициализация Drag and Drop (только если выбрана сортировка по умолчанию)
    const el = document.getElementById('sortable-modules');
    const currentSort = '<?php echo $filters['sort']; ?>';
    
    if (el && currentSort === 'sort_order') {
        Sortable.create(el, {
            handle: '.handle',
            animation: 150,
            ghostClass: 'bg-blue-50',
            onEnd: function (evt) {
                const rows = el.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    const moduleId = row.getAttribute('data-module-id');
                    if (moduleId) {
                        $.ajax({
                            url: '/erp218_api/update_sort_order',
                            method: 'POST',
                            data: {
                                id: moduleId,
                                sort_order: index + 1
                            }
                        });
                    }
                });
            }
        });
    }

    // Простой поиск по таблице
    $("#moduleSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Переключение публикации модуля
    $(document).on('click', '.toggle-published', function() {
        const button = $(this);
        const moduleId = button.data('module-id');
        const isPublished = button.data('is-published') === 1 ? 0 : 1; // Инвертируем статус

        $.ajax({
            url: '/erp218_api/toggle_published',
            method: 'POST',
            data: {
                id: moduleId,
                is_published: isPublished
            },
            success: function(response) {
                if (response.status === 'ok') {
                    button.data('is-published', response.is_published);
                    if (response.is_published) {
                        button.removeClass('bg-gray-200 dark:bg-gray-700').addClass('bg-blue-600');
                        button.find('span').removeClass('translate-x-0').addClass('translate-x-5');
                    } else {
                        button.removeClass('bg-blue-600').addClass('bg-gray-200 dark:bg-gray-700');
                        button.find('span').removeClass('translate-x-5').addClass('translate-x-0');
                    }
                } else {
                    alert('Ошибка: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Произошла ошибка при обновлении статуса публикации.');
                console.error(xhr.responseText);
            }
        });
    });

    // Запуск LLM анализа
    $(document).on('click', '.run-ai-btn', function() {
        const button = $(this);
        const moduleId = button.data('module-id');
        
        if (!confirm('Запустить процесс LLM анализа для этого модуля? Это может занять некоторое время.')) return;

        button.find('i').addClass('fa-spin');
        button.prop('disabled', true);

        $.ajax({
            url: '/erp218_api/run_ai_analysis',
            method: 'POST',
            data: { id: moduleId },
            success: function(response) {
                button.find('i').removeClass('fa-spin');
                button.prop('disabled', false);
                if (response.status === 'ok') {
                    alert('Анализ успешно запущен. ID анализа: ' + response.analysis_id);
                } else {
                    alert('Ошибка: ' + response.message);
                }
            },
            error: function(xhr) {
                button.find('i').removeClass('fa-spin');
                button.prop('disabled', false);
                alert('Произошла ошибка при запуске анализа.');
            }
        });
    });

    // --- Функционал для примечаний разработчика ---
    let currentModuleIdForNotes = null;

    $(document).on('click', '.edit-notes-btn', function() {
        currentModuleIdForNotes = $(this).data('module-id');
        const currentNotes = $(this).data('notes');
        const moduleName = $(`tr[data-module-id="${currentModuleIdForNotes}"]`).find('.editable-field[data-field="display_name"]').text().trim();
        
        $('#notesModuleName').text(moduleName);
        $('#developerNotesInput').val(currentNotes);
        $('#notesModal').removeClass('hidden');
    });

    $('#cancelNotesBtn').on('click', function() {
        $('#notesModal').addClass('hidden');
        currentModuleIdForNotes = null;
    });

    $('#saveNotesBtn').on('click', function() {
        if (!currentModuleIdForNotes) return;

        const newNotes = $('#developerNotesInput').val();

        $.ajax({
            url: '/erp218_api/update_developer_notes',
            method: 'POST',
            data: {
                id: currentModuleIdForNotes,
                developer_notes: newNotes
            },
            success: function(response) {
                if (response.status === 'ok') {
                    const moduleRow = $(`tr[data-module-id="${currentModuleIdForNotes}"]`);
                    let notesElement = moduleRow.find('.editable-field[data-field="developer_notes"]');
                    
                    if (notesElement.length) {
                        if (response.developer_notes) {
                            notesElement.html(`<div class="line-clamp-3"><i class="fas fa-sticky-note mr-1"></i> ${response.developer_notes}</div>`);
                            notesElement.removeClass('text-gray-400 dark:text-gray-500').addClass('text-purple-500 dark:text-purple-400');
                        } else {
                            notesElement.html(`<div class="line-clamp-3"><span class="text-gray-400 dark:text-gray-500">Нет примечаний</span></div>`);
                            notesElement.removeClass('text-purple-500 dark:text-purple-400').addClass('text-gray-400 dark:text-gray-500');
                        }
                    }
                    
                    moduleRow.find('.edit-notes-btn').data('notes', response.developer_notes);
                    $('#notesModal').addClass('hidden');
                } else {
                    alert('Ошибка: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Произошла ошибка при сохранении примечаний.');
            }
        });
    });

    // --- Функционал для редактирования атрибутов модуля ---
    let currentModuleIdForAttributes = null;

    $(document).on('click', '.edit-attributes-btn', function() {
        currentModuleIdForAttributes = $(this).data('module-id');
        const moduleName = $(`tr[data-module-id="${currentModuleIdForAttributes}"]`).find('.editable-field[data-field="display_name"]').text().trim();

        $('#attributesModuleName').text(moduleName);
        $('#displayNameInput').val($(this).data('display-name'));
        $('#descriptionInput').val($(this).data('description'));
        $('#versionInput').val($(this).data('version'));
        $('#iconInput').val($(this).data('icon'));
        $('#mainPageUriInput').val($(this).data('main-page-uri'));
        $('#attributesModal').removeClass('hidden');
    });

    $('#cancelAttributesBtn').on('click', function() {
        $('#attributesModal').addClass('hidden');
        currentModuleIdForAttributes = null;
    });

    $('#saveAttributesBtn').on('click', function() {
        if (!currentModuleIdForAttributes) return;

        const newDisplayName = $('#displayNameInput').val();
        const newDescription = $('#descriptionInput').val();
        const newVersion = $('#versionInput').val();
        const newIcon = $('#iconInput').val();
        const newMainPageUri = $('#mainPageUriInput').val();

        $.ajax({
            url: '/erp218_api/update_attributes',
            method: 'POST',
            data: {
                id: currentModuleIdForAttributes,
                display_name: newDisplayName,
                description: newDescription,
                version: newVersion,
                icon: newIcon,
                main_page_uri: newMainPageUri
            },
            success: function(response) {
                if (response.status === 'ok') {
                    const moduleRow = $(`tr[data-module-id="${currentModuleIdForAttributes}"]`);
                    const btn = moduleRow.find('.edit-attributes-btn');
                    
                    if (response.updated_fields.display_name !== undefined) {
                        moduleRow.find('.editable-field[data-field="display_name"]').text(response.updated_fields.display_name).attr('title', response.updated_fields.display_name);
                        btn.data('display-name', response.updated_fields.display_name);
                    }
                    if (response.updated_fields.description !== undefined) {
                        let descriptionElement = moduleRow.find('.editable-field[data-field="description"]');
                        const truncatedDescription = response.updated_fields.description.split(' ').slice(0, 12).join(' ') + (response.updated_fields.description.split(' ').length > 12 ? '...' : '');
                        if (descriptionElement.length) descriptionElement.text(truncatedDescription);
                        btn.data('description', response.updated_fields.description);
                    }
                    if (response.updated_fields.version !== undefined) {
                        moduleRow.find('.editable-field[data-field="version"]').html(`v${response.updated_fields.version}`);
                        btn.data('version', response.updated_fields.version);
                    }
                    if (response.updated_fields.icon !== undefined) {
                        moduleRow.find('.flex-shrink-0.h-10.w-10 i').attr('class', `fas ${response.updated_fields.icon} text-lg`);
                        btn.data('icon', response.updated_fields.icon);
                    }
                    if (response.updated_fields.main_page_uri !== undefined) {
                        const linkContainer = moduleRow.find('.main-page-link-container');
                        if (response.updated_fields.main_page_uri) {
                            linkContainer.html(`<a href="/${response.updated_fields.main_page_uri}" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" title="Перейти на главную страницу модуля"><i class="fas fa-external-link-alt"></i></a>`);
                        } else {
                            linkContainer.html(`<i class="fas fa-link text-gray-300 dark:text-gray-600" title="URI не указан"></i>`);
                        }
                        btn.data('main-page-uri', response.updated_fields.main_page_uri);
                    }

                    $('#attributesModal').addClass('hidden');
                } else {
                    alert('Ошибка: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Произошла ошибка при сохранении атрибутов.');
            }
        });
    });

    // --- Функционал для изменения статуса модуля ---
    let currentModuleIdForStatus = null;
    const statusClasses = {
        'alpha': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        'beta': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'stable': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'archived': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
    };

    $(document).on('click', '.edit-status-btn', function() {
        currentModuleIdForStatus = $(this).data('module-id');
        const currentStatus = $(this).data('current-status');
        const moduleName = $(`tr[data-module-id="${currentModuleIdForStatus}"]`).find('.editable-field[data-field="display_name"]').text().trim();

        $('#statusModuleName').text(moduleName);
        $('#statusSelect').val(currentStatus);
        $('#statusModal').removeClass('hidden');
    });

    $('#cancelStatusBtn').on('click', function() {
        $('#statusModal').addClass('hidden');
        currentModuleIdForStatus = null;
    });

    $('#saveStatusBtn').on('click', function() {
        if (!currentModuleIdForStatus) return;

        const newStatus = $('#statusSelect').val();

        $.ajax({
            url: '/erp218_api/update_status',
            method: 'POST',
            data: {
                id: currentModuleIdForStatus,
                status: newStatus
            },
            success: function(response) {
                if (response.status === 'ok') {
                    const moduleRow = $(`tr[data-module-id="${currentModuleIdForStatus}"]`);
                    const statusButton = moduleRow.find('.edit-status-btn');
                    for (const key in statusClasses) { statusButton.removeClass(statusClasses[key]); }
                    statusButton.addClass(statusClasses[newStatus]);
                    statusButton.find('.status-text').text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                    statusButton.data('current-status', newStatus);
                    $('#statusModal').addClass('hidden');
                } else {
                    alert('Ошибка: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Произошла ошибка при сохранении статуса.');
            }
        });
    });
});
</script>