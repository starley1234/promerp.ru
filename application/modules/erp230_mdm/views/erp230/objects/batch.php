<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Улучшенное представление для массового добавления объектов MDM.
 * Поддерживает Excel-подобный ввод, генерацию кодов и умную вставку.
 */

$schema_props = isset($schema['properties']) ? $schema['properties'] : [];
$csrf = class_exists('Security') ? Security::token() : '';
?>

<style>
    .excel-input:focus { outline: 2px solid #3b82f6; outline-offset: -2px; }
    .excel-input { border-radius: 0 !important; border-color: transparent !important; }
    .excel-input:hover { background-color: #f8fafc; }
    .row-selected { background-color: #eff6ff !important; }
    .sticky-col { position: sticky; left: 0; background: white; z-index: 10; }
    .sticky-col-2 { position: sticky; left: 40px; background: white; z-index: 10; }
</style>

<div  id="batch-app">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                <i class="fas fa-table text-blue-600 mr-2"></i>
                Массовый импорт объектов
            </h1>
            <p class="text-sm text-gray-500 mt-1">Excel-friendly интерфейс для быстрого наполнения реестра</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="btn-generate-codes" class="px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-medium hover:bg-indigo-100 transition-colors">
                <i class="fas fa-magic mr-1"></i> Сгенерировать коды
            </button>
            <a href="<?php echo URL::site('erp230_objects'); ?>" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Отмена
            </a>
        </div>
    </div>

    <?php if (!empty($results['errors'])): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl animate-pulse">
            <h3 class="text-red-800 font-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i> Ошибки валидации:</h3>
            <ul class="text-sm text-red-700 list-disc list-inside">
                <?php foreach ($results['errors'] as $err): ?>
                    <li><?php echo HTML::chars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <!-- Панель управления -->
        <form method="get" action="" id="filter-form" class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 flex flex-wrap items-end gap-4">
            <div class="w-64">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Тип объекта</label>
                <select name="type_id" data-action="submit-form" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500">
                    <option value="">— выберите тип —</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t->id; ?>" <?php if ($type_id == $t->id) echo 'selected'; ?>>
                            <?php echo HTML::chars($t->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-64">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Организация</label>
                <select name="org_id" id="batch_org_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500">
                    <option value="">— выберите организацию —</option>
                    <?php foreach ($orgs as $o): ?>
                        <option value="<?php echo $o->id; ?>" <?php if ($org_id == $o->id) echo 'selected'; ?>>
                            <?php echo HTML::chars($o->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 text-right">
                <div class="inline-flex items-center p-1 bg-gray-100 rounded-lg">
                    <button type="button" data-call="adjustRows" data-call-args="[5]" class="px-3 py-1 text-xs font-medium hover:bg-white rounded-md transition-all">+5 строк</button>
                    <button type="button" data-call="adjustRows" data-call-args="[10]" class="px-3 py-1 text-xs font-medium hover:bg-white rounded-md transition-all">+10</button>
                    <button type="button" id="btn-clear-table" class="px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded-md transition-all">Очистить</button>
                </div>
            </div>
        </form>

        <?php if ($type_id): ?>
        <form method="post" action="<?php echo URL::site('erp230_objects/batch'); ?>" id="batch-form">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="type_id" value="<?php echo HTML::chars($type_id); ?>">
            <input type="hidden" name="org_id" id="hidden_org_id" value="<?php echo HTML::chars($org_id); ?>">

            <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                <table class="w-full text-left border-collapse table-fixed" id="batch-table">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-900 sticky top-0 z-20">
                            <th scope="col" class="p-2 text-[10px] font-bold text-gray-500 uppercase w-10 border sticky-col">#</th>
                            <th scope="col" class="p-2 text-[10px] font-bold text-gray-500 uppercase w-64 border sticky-col-2">Мастер-код *</th>
                            <?php foreach ($schema_props as $key => $prop): ?>
                                <th scope="col" class="p-2 text-[10px] font-bold text-gray-500 uppercase w-48 border">
                                    <?php echo HTML::chars(isset($prop['title']) ? $prop['title'] : $key); ?>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="p-2 w-10 border"></th>
                        </tr>
                    </thead>
                    <tbody id="batch-tbody">
                        <?php for ($i = 0; $i < 10; $i++): ?>
                        <tr class="hover:bg-blue-50/50 transition-colors">
                            <td class="p-2 text-center text-xs text-gray-400 border sticky-col bg-inherit"><?php echo $i + 1; ?></td>
                            <td class="p-0 border sticky-col-2 bg-inherit">
                                <input type="text" name="rows[<?php echo $i; ?>][master_code]" 
                                       class="excel-input w-full p-2 text-sm font-mono bg-transparent focus:bg-white" 
                                       placeholder="Вставьте или введите...">
                            </td>
                            <?php foreach ($schema_props as $key => $prop): ?>
                            <td class="p-0 border">
                                <input type="text" name="rows[<?php echo $i; ?>][attrs][<?php echo $key; ?>]" 
                                       class="excel-input w-full p-2 text-sm bg-transparent focus:bg-white">
                            </td>
                            <?php endforeach; ?>
                            <td class="p-2 text-center border">
                                <button type="button" data-call="removeRow" data-call-args="[]" class="text-gray-300 hover:text-red-500">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-gray-50 border-t flex justify-between items-center">
                <div class="text-xs text-gray-500">
                    <kbd class="px-2 py-1 bg-white border rounded shadow-sm">Ctrl+V</kbd> для вставки из Excel
                </div>
                <button type="submit" class="px-10 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-200 transition-all transform hover:-translate-y-0.5">
                    Создать объекты
                </button>
            </div>
        </form>
        <?php else: ?>
            <div class="p-20 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-50 text-blue-500 mb-6">
                    <i class="fas fa-hand-pointer text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Начните с выбора типа</h3>
                <p class="text-gray-500 mt-2">Выберите категорию объектов для генерации таблицы ввода</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('batch-tbody');
    if (!tbody) return;

    const orgSelect = document.getElementById('batch_org_id');
    const hiddenOrg = document.getElementById('hidden_org_id');
    
    orgSelect.addEventListener('change', () => { hiddenOrg.value = orgSelect.value; });

    // Умная вставка из Excel
    tbody.addEventListener('paste', function(e) {
        const clipboardData = e.clipboardData || window.clipboardData;
        const pastedData = clipboardData.getData('Text');
        const rows = pastedData.split(/\r\n|\n|\r/);
        
        const targetInput = e.target;
        const targetCell = targetInput.closest('td');
        const targetRow = targetCell.closest('tr');
        
        if (rows.length > 1 || rows[0].split('\t').length > 1) {
            e.preventDefault();
            
            let currentRow = targetRow;
            const startColIndex = Array.from(targetRow.children).indexOf(targetCell);

            rows.forEach((rowText) => {
                if (!rowText.trim() && rowText !== rows[0]) return;
                if (!currentRow) {
                    adjustRows(1);
                    currentRow = tbody.lastElementChild;
                }

                const cells = rowText.split('\t');
                cells.forEach((cellText, colOffset) => {
                    const targetTd = currentRow.children[startColIndex + colOffset];
                    if (targetTd) {
                        const input = targetTd.querySelector('input');
                        if (input) input.value = cellText.trim();
                    }
                });
                currentRow = currentRow.nextElementSibling;
            });
        }
    });

    // Генерация кодов
    document.getElementById('btn-generate-codes').addEventListener('click', async function() {
        const typeId = document.querySelector('input[name="type_id"]').value;
        const orgId = orgSelect.value;
        
        if (!typeId || !orgId) return alert('Выберите тип и организацию');
        
        const inputs = tbody.querySelectorAll('input[name*="[master_code]"]');
        let count = 0;
        
        for (let input of inputs) {
            if (!input.value) {
                try {
                    const res = await fetch(`<?php echo URL::site('mdm/codes/next'); ?>?type_id=${typeId}&org_id=${orgId}`);
                    const data = await res.json();
                    if (data.status === 'ok') {
                        input.value = data.code;
                        count++;
                    }
                } catch (e) { break; }
            }
        }
        if (count === 0) alert('Нет пустых строк или автогенерация не настроена');
    });

    document.getElementById('btn-clear-table').addEventListener('click', () => {
        if(confirm('Очистить все введенные данные?')) {
            tbody.querySelectorAll('input').forEach(i => i.value = '');
        }
    });
});

function adjustRows(n) {
    const tbody = document.getElementById('batch-tbody');
    for (let i = 0; i < n; i++) {
        const rowCount = tbody.children.length;
        const newRow = tbody.children[0].cloneNode(true);
        newRow.querySelector('td:first-child').textContent = rowCount + 1;
        newRow.querySelectorAll('input').forEach(input => {
            input.value = '';
            input.name = input.name.replace(/rows\[\d+\]/, 'rows[' + rowCount + ']');
        });
        tbody.appendChild(newRow);
    }
}

function removeRow(btn) {
    const tbody = document.getElementById('batch-tbody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        Array.from(tbody.children).forEach((row, idx) => {
            row.querySelector('td:first-child').textContent = idx + 1;
        });
    }
}
</script>