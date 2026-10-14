<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Группировка свойств для отображения конфликтов
 */
$conflicts = [];
foreach ($properties as $prop) {
    $key = $prop->key;
    if (!isset($conflicts[$key])) {
        $conflicts[$key] = [
            'label' => $key, // В реальной системе здесь может быть маппинг на человекочитаемое название
            'sources' => []
        ];
    }
    
    $conflicts[$key]['sources'][] = [
        'id' => $prop->id,
        'system_name' => $prop->source_id,
        'value' => $prop->value,
        'score' => (int)($prop->confidence * 100),
        'updated_at' => $prop->created_at,
        'is_golden' => $prop->is_current
    ];
}

// Подготовка переменных для шапки, чтобы избежать Undefined variable
$object_name = $object->master_code;
$object_uuid = $object->id;
$object_type = $object->type_id;
?>

<div>
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 mb-1">
                <i class="fas fa-code-branch"></i>
                <span class="font-medium uppercase tracking-wider">Data Stewardship / Conflict Resolution</span>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Разрешение конфликта: <?php echo HTML::chars($object_name); ?>
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                ID объекта: <span class="font-mono"><?php echo HTML::chars($object_uuid); ?></span> | 
                Тип: <?php echo HTML::chars($object_type); ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?php echo Route::url('default', ['controller' => 'erp230_stewardship', 'action' => 'index']); ?>" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg transition-colors text-sm font-medium">
                Отложить
            </a>
            <button data-action="submit-target" data-target="#resolve-form" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-md transition-colors text-sm font-medium">
                Утвердить Золотую запись
            </button>
        </div>
    </div>

    <form id="resolve-form" method="post">
        <input type="hidden" name="resolve_action" value="apply_manual">
        
        <!-- Comparison Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Left Column: Attributes List -->
            <div class="lg:col-span-3 space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <h3 class="font-semibold text-sm">Атрибуты с конфликтами</h3>
                    </div>
                    <nav class="p-2">
                        <?php foreach ($conflicts as $attr_key => $conflict): ?>
                            <a href="#attr-<?php echo $attr_key; ?>" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors mb-1 group">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo HTML::chars($conflict['label']); ?></span>
                                <span class="px-2 py-0.5 bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 text-[10px] font-bold rounded-full">
                                    <?php echo count($conflict['sources']); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                    <h4 class="text-amber-800 dark:text-amber-400 text-xs font-bold uppercase mb-2 flex items-center">
                        <i class="fas fa-lightbulb mr-2"></i> Рекомендация AI
                    </h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                        Система рекомендует выбирать значения из источников с Trust Score выше 80%.
                    </p>
                </div>
            </div>

            <!-- Right Column: Comparison Table -->
            <div class="lg:col-span-9 space-y-6">
                <?php foreach ($conflicts as $attr_key => $conflict): ?>
                    <div id="attr-<?php echo $attr_key; ?>" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($conflict['label']); ?></h3>
                            <span class="text-xs text-gray-400 font-mono"><?php echo HTML::chars($attr_key); ?></span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th scope="col" class="px-6 py-3 font-semibold">Источник</th>
                                        <th scope="col" class="px-6 py-3 font-semibold">Значение</th>
                                        <th scope="col" class="px-6 py-3 font-semibold text-center">Trust Score</th>
                                        <th scope="col" class="px-6 py-3 font-semibold">Дата обновления</th>
                                        <th scope="col" class="px-6 py-3 text-right">Выбор</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <?php foreach ($conflict['sources'] as $source): ?>
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors <?php echo $source['is_golden'] ? 'bg-blue-50/30 dark:bg-blue-900/10' : ''; ?>">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-2 h-2 rounded-full mr-3 <?php echo $source['is_golden'] ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                                    <span class="text-sm font-medium"><?php echo HTML::chars($source['system_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-sm font-mono bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded text-blue-600 dark:text-blue-400">
                                                    <?php echo HTML::chars(is_array($source['value']) ? json_encode($source['value']) : $source['value']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $source['score'] > 80 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
                                                    <?php echo $source['score']; ?>%
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php echo date('d.m.Y H:i', strtotime($source['updated_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <input type="radio" 
                                                       name="properties[<?php echo $attr_key; ?>][value]" 
                                                       value="<?php echo HTML::chars(is_array($source['value']) ? json_encode($source['value']) : $source['value']); ?>"
                                                       <?php echo $source['is_golden'] ? 'checked' : ''; ?>
                                                       class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- Manual Entry Row -->
                                    <tr class="bg-gray-50/50 dark:bg-gray-900/20">
                                        <td class="px-6 py-4 text-sm italic text-gray-500">Ручной ввод</td>
                                        <td class="px-6 py-4" colspan="3">
                                            <input type="text" 
                                                   name="manual_<?php echo $attr_key; ?>"
                                                   placeholder="Введите эталонное значение..." 
                                                   data-action="mirror-target" data-target="#radio_manual_<?php echo HTML::chars($attr_key); ?>"
                                                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <input type="radio" 
                                                   id="radio_manual_<?php echo $attr_key; ?>"
                                                   name="properties[<?php echo $attr_key; ?>][value]" 
                                                   value=""
                                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Highlight row on radio change
    $('input[type="radio"]').on('change', function() {
        const name = $(this).attr('name');
        $(`input[name="${name}"]`).closest('tr').removeClass('bg-blue-50 dark:bg-blue-900/20');
        $(this).closest('tr').addClass('bg-blue-50 dark:bg-blue-900/20');
    });
});
</script>