<div>
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6"><?php echo __('Управление зависимостями модулей'); ?></h2>

    <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo __('Добавить новую зависимость'); ?></h3>
        <form id="addDependencyForm" method="POST" action="<?php echo URL::site('erp218_index/add_dependency'); ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="module_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo __('Основной модуль (зависимый)'); ?>
                    </label>
                    <select id="module_id" name="module_id" required
                            class="select2-search block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white p-2">
                        <option value=""><?php echo __('Выберите модуль'); ?></option>
                        <?php foreach ($all_modules as $module): ?>
                            <option value="<?php echo $module->id; ?>" <?php echo (isset($_COOKIE['last_selected_module_id']) && $_COOKIE['last_selected_module_id'] == $module->id) ? 'selected' : ''; ?>>
                                <?php echo HTML::chars($module->display_name); ?> (<?php echo HTML::chars($module->system_name); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="required_module_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo __('Требуемый модуль (зависимость)'); ?>
                    </label>
                    <select id="required_module_id" name="required_module_id" required
                            class="select2-search block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white p-2">
                        <option value=""><?php echo __('Выберите модуль'); ?></option>
                        <?php foreach ($all_modules as $module): ?>
                            <option value="<?php echo $module->id; ?>"><?php echo HTML::chars($module->display_name); ?> (<?php echo HTML::chars($module->system_name); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="min_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo __('Минимальная версия (опционально)'); ?>
                    </label>
                    <input type="text" id="min_version" name="min_version"
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white p-2"
                           placeholder="1.0.0">
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i> <?php echo __('Добавить зависимость'); ?>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo __('Существующие зависимости'); ?></h3>
        <?php if ($dependencies->count()): ?>
            <div class="overflow-x-auto -mx-6 sm:-mx-0">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Основной модуль'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Зависит от'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Мин. версия'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Действия'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($dependencies as $dependency): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($dependency->module->display_name); ?> (<?php echo HTML::chars($dependency->module->system_name); ?>)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo HTML::chars($dependency->required->display_name); ?> (<?php echo HTML::chars($dependency->required->system_name); ?>)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo HTML::chars($dependency->min_version ?: __('Любая')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button type="button" data-dependency-id="<?php echo $dependency->id; ?>"
                                            class="delete-dependency-btn text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-400 transition-colors duration-200 p-1 rounded-md">
                                        <i class="fas fa-trash-alt mr-1"></i> <?php echo __('Удалить'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 dark:text-gray-400 p-4 bg-gray-50 dark:bg-gray-700 rounded-md border border-gray-200 dark:border-gray-600">
                <?php echo __('Зависимости пока не добавлены. Используйте форму выше, чтобы добавить первую зависимость.'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .select2-container--default .select2-selection--single {
        background-color: transparent !important;
        border-color: rgb(209 213 219) !important;
        height: 38px !important;
        display: flex !important;
        align-items: center !important;
    }
    .dark .select2-container--default .select2-selection--single {
        border-color: rgb(75 85 99) !important;
        color: white !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: white !important;
    }
    .dark .select2-dropdown {
        background-color: rgb(31 41 55) !important;
        border-color: rgb(75 85 99) !important;
        color: white !important;
    }
    .dark .select2-search__field {
        background-color: rgb(55 65 81) !important;
        color: white !important;
        border-color: rgb(75 85 99) !important;
    }
    .dark .select2-results__option--highlighted[aria-selected] {
        background-color: rgb(37 99 235) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-results__options {
        max-height: 400px !important;
    }
</style>

<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2-search').select2({
            width: '100%',
            placeholder: "<?php echo __('Поиск модуля...'); ?>",
            allowClear: true,
            language: {
                noResults: function() {
                    return "<?php echo __('Ничего не найдено'); ?>";
                }
            }
        });
    }

    $('#module_id').on('change', function() {
        var val = $(this).val();
        document.cookie = "last_selected_module_id=" + val + "; path=/; max-age=" + (60*60*24*30);
    });

    $('#addDependencyForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action');
        var formData = form.serialize();

        $.ajax({
            type: "POST",
            url: url,
            data: formData,
            dataType: "json",
            success: function(response) {
                if (response.status === 'ok') {
                    showNotification(response.message, 'success');
                    location.reload(); 
                } else {
                    showNotification(response.message || 'Ошибка при добавлении зависимости.', 'error');
                }
            },
            error: function(xhr) {
                var errorMsg = 'Произошла ошибка при добавлении зависимости.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    if (typeof xhr.responseJSON.message === 'object') {
                        errorMsg = Object.values(xhr.responseJSON.message).join('<br>');
                    } else {
                        errorMsg = xhr.responseJSON.message;
                    }
                }
                showNotification(errorMsg, 'error');
            }
        });
    });

    $('.delete-dependency-btn').on('click', function() {
        var dependencyId = $(this).data('dependency-id');
        if (confirm('<?php echo __('Вы уверены, что хотите удалить эту зависимость? Это действие необратимо.'); ?>')) {
            $.ajax({
                type: "POST",
                url: '<?php echo URL::site('erp218_index/delete_dependency'); ?>',
                data: { dependency_id: dependencyId },
                dataType: "json",
                success: function(response) {
                    if (response.status === 'ok') {
                        showNotification(response.message, 'success');
                        location.reload();
                    } else {
                        showNotification(response.message || 'Ошибка при удалении зависимости.', 'error');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Произошла ошибка при удалении зависимости.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showNotification(errorMsg, 'error');
                }
            });
        }
    });
});
</script>