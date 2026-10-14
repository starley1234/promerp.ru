<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="relative flex-grow max-w-md">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" 
                   id="uomSearch" 
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                   placeholder="Поиск по коду, названию или обозначению...">
        </div>
        
        <div class="flex items-center gap-2">
            <button data-call="uomManager.openModal" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i> Добавить ЕИ
            </button>
            <button data-action="reload" class="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- UOM Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 text-xs uppercase font-semibold">
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700">Код (ОКЕИ)</th>
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700">Наименование</th>
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700">Нац. обозн.</th>
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700">Межд. код</th>
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700">Базовая / Коэф.</th>
                        <th scope="col" class="px-6 py-4 border-b dark:border-gray-700 text-right">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (isset($uoms) && count($uoms) > 0): ?>
                        <?php foreach ($uoms as $uom): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm font-mono text-blue-600 dark:text-blue-400">
                                    <?php echo HTML::chars($uom->code); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($uom->name); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100"><?php echo HTML::chars($uom->symbol_nat ?: '—'); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo HTML::chars($uom->code_nat ?: ''); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100"><?php echo HTML::chars($uom->code_intl ?: '—'); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo HTML::chars($uom->symbol_intl ?: ''); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-gray-500">Баз: <?php echo HTML::chars($uom->base_code ?: '—'); ?></span>
                                        <span class="font-mono text-xs">x<?php echo number_format($uom->factor, 4, '.', ''); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <button type="button" data-call="uomManager.edit" data-call-args="<?php echo HTML::chars(json_encode(array($uom->as_array()), JSON_UNESCAPED_UNICODE)); ?>" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 mx-2" aria-label="Редактировать единицу">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="/erp230_dictionaries/uom_delete/<?php echo $uom->code; ?>" method="POST" class="inline" data-confirm="Удалить единицу измерения?">
                                        <button type="submit" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-box-open text-4xl mb-3 block opacity-20"></i>
                                Справочник единиц измерения пуст
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="uomModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modalTitle">Единица измерения</h3>
                <button data-call="uomManager.closeModal" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="uomForm" method="POST" action="/erp230_dictionaries/uom_save" class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код ОКЕИ (ID)</label>
                        <input type="text" name="code" id="uom_code" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-transparent focus:ring-2 focus:ring-blue-500 outline-none text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Наименование</label>
                        <input type="text" name="name" id="uom_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-transparent focus:ring-2 focus:ring-blue-500 outline-none text-gray-900 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold uppercase text-gray-400">Национальный стандарт (ГОСТ)</h4>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Условное обозначение (напр. "с", "мин")</label>
                            <input type="text" name="symbol_nat" id="uom_symbol_nat" class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Кодовое обозначение (напр. "С", "МИН")</label>
                            <input type="text" name="code_nat" id="uom_code_nat" class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-sm">
                        </div>
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold uppercase text-gray-400">Международный стандарт (ISO)</h4>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Symbol (e.g. "s", "min")</label>
                            <input type="text" name="symbol_intl" id="uom_symbol_intl" class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Code (e.g. "SEC", "MIN")</label>
                            <input type="text" name="code_intl" id="uom_code_intl" class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-sm">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Базовая ЕИ (код)</label>
                        <input type="text" name="base_code" id="uom_base_code" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-transparent focus:ring-2 focus:ring-blue-500 outline-none text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Коэффициент</label>
                        <input type="number" step="0.00000001" name="factor" id="uom_factor" value="1.0000" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-transparent focus:ring-2 focus:ring-blue-500 outline-none text-gray-900 dark:text-white">
                    </div>
                </div>
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" data-call="uomManager.closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">Отмена</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const uomManager = {
    openModal: function() {
        $('#uomForm')[0].reset();
        $('#uom_code').prop('readonly', false);
        $('#modalTitle').text('Добавить единицу измерения');
        $('#uomModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    },
    closeModal: function() {
        $('#uomModal').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    },
    edit: function(data) {
        this.openModal();
        $('#modalTitle').text('Редактировать: ' + data.code);
        $('#uom_code').val(data.code).prop('readonly', true);
        $('#uom_name').val(data.name);
        $('#uom_base_code').val(data.base_code);
        $('#uom_factor').val(data.factor);
        $('#uom_symbol_nat').val(data.symbol_nat);
        $('#uom_symbol_intl').val(data.symbol_intl);
        $('#uom_code_nat').val(data.code_nat);
        $('#uom_code_intl').val(data.code_intl);
    }
};

$(document).ready(function() {
    $('#uomSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>