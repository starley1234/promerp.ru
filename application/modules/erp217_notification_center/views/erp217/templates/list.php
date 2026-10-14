<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-gray-500 dark:text-gray-400 text-sm">Управление макетами сообщений для различных каналов связи.</p>
        </div>
        <div class="flex items-center gap-3">
            <button data-action="show-target" data-target="#llmModal" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                <i class="fas fa-robot mr-2"></i>
                Generate LLM
            </button>
            <a href="/erp217_templates/edit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                <i class="fas fa-plus mr-2"></i>
                Создать шаблон
            </a>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-700 card-shadow">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="templateSearch" placeholder="Поиск по коду или теме..."
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
        </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Код и Тема</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Канал</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Описание</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Обновлен</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                    </tr>
                </thead>
                <tbody id="templatesTableBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (isset($templates) && count($templates) > 0): ?>
                        <?php foreach ($templates as $template): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($template->alias); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($template->subject); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $channel_icons = [
                                            'email' => ['icon' => 'fa-envelope', 'color' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
                                            'telegram' => ['icon' => 'fa-paper-plane', 'color' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'],
                                            'push' => ['icon' => 'fa-bell', 'color' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'],
                                            'max' => ['icon' => 'fa-robot', 'color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                                        ];
                                        $style = $channel_icons[$template->channel] ?? ['icon' => 'fa-comment', 'color' => 'bg-gray-100 text-gray-700'];
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $style['color']; ?>">
                                        <i class="fas <?php echo $style['icon']; ?> mr-1"></i> <?php echo ucfirst($template->channel); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                        <?php echo HTML::chars($template->description); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d.m.Y H:i', strtotime($template->updated_at)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="/erp217_templates/edit/<?php echo $template->id; ?>"
                                           class="p-2 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                                           title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button data-call="confirmDelete" data-call-args="<?php echo HTML::chars(json_encode(array((int) $template->id, (string) $template->alias), JSON_UNESCAPED_UNICODE)); ?>"
                                                class="p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                                title="Удалить">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-gray-300 dark:text-gray-600 text-5xl mb-4"></i>
                                    <p class="text-gray-500 dark:text-gray-400">Шаблоны еще не созданы</p>
                                    <a href="/erp217_templates/edit" class="mt-2 text-blue-600 dark:text-blue-400 hover:underline">Создать первый шаблон</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal LLM -->
<div id="llmModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">Генерация шаблона через LLM</h3>
                        <div class="mt-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Сырые данные (Alias + JSON)</label>
                            <textarea id="llmRawData" rows="8" class="block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-mono" placeholder="order_created&#10;{&quot;id&quot;: 123, &quot;customer&quot;: &quot;Иван&quot;}"></textarea>
                            <p class="mt-2 text-xs text-gray-500">Первая строка - код события (alias), далее - JSON данные. Канал по умолчанию: Push.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="btnGenerateLlm" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Сгенерировать
                </button>
                <button type="button" data-action="hide-target" data-target="#llmModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $("#templateSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#templatesTableBody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    $("#btnGenerateLlm").on("click", function() {
        const rawData = $("#llmRawData").val();
        if (!rawData.trim()) return alert('Введите данные');

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Обработка...');

        $.ajax({
            url: '/erp217_templates/generate_llm',
            type: 'POST',
            data: { raw_data: rawData },
            success: function(response) {
                if (response.status === 'success') {
                    location.reload();
                } else {
                    alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
                    btn.prop('disabled', false).text('Сгенерировать');
                }
            },
            error: function(xhr) {
                alert('Ошибка сервера при генерации');
                btn.prop('disabled', false).text('Сгенерировать');
            }
        });
    });
});

function confirmDelete(id, alias) {
    if (confirm('Вы уверены, что хотите удалить шаблон "' + alias + '"?')) {
        $.ajax({
            url: '/erp217_templates/delete/' + id,
            type: 'POST',
            success: function(response) {
                location.reload();
            },
            error: function() {
                alert('Ошибка при удалении шаблона');
            }
        });
    }
}
</script>