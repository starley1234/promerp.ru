<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <!-- Хлебные крошки и навигация -->
    <nav class="flex mb-6 text-sm font-medium text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="/erp227_objects" class="hover:text-blue-600 dark:hover:text-white">
                    <i class="fas fa-database mr-2"></i> Реестр
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2 text-xs"></i>
                    <span class="text-gray-900 dark:text-white"><?php echo HTML::chars($object->type->name); ?></span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Левая колонка: Основная информация -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs font-bold rounded uppercase tracking-wider">
                                <?php echo HTML::chars($object->code); ?>
                            </span>
                            <span class="text-xs text-gray-400">Ревизия: <?php echo $object->revision; ?></span>
                        </div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">
                            <?php echo HTML::chars($object->name); ?>
                        </h1>
                    </div>
                    <div class="flex gap-2">
                        <a href="/erp227_objects/edit/<?php echo $object->id; ?>" class="p-2 text-gray-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit"></i></a>
                        <button data-call="deleteObject" data-call-args="[<?php echo $object->id; ?>]" class="p-2 text-gray-400 hover:text-red-600 transition-colors"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Характеристики и атрибуты</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php 
                        $attrs = json_decode($object->attributes, TRUE);
                        if (!empty($attrs)): 
                            foreach ($attrs as $key => $value): ?>
                                <div class="flex flex-col p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-800">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold mb-1"><?php echo HTML::chars($key); ?></span>
                                    <span class="text-sm text-gray-900 dark:text-white font-medium">
                                        <?php echo is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : HTML::chars($value); ?>
                                    </span>
                                </div>
                            <?php endforeach; 
                        else: ?>
                            <p class="text-gray-500 italic text-sm col-span-2">Дополнительные атрибуты не заданы</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Связи объекта (BOM / Состав) -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="font-bold text-gray-900 dark:text-white">Связанные объекты и компоненты</h3>
                    <button data-call="addLink" data-call-args="[<?php echo $object->id; ?>]" class="text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i> Добавить связь</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип связи</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Объект</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Кол-во</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($links as $link): ?>
                                <tr id="link-row-<?php echo $link->id; ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo HTML::chars($link->link_type); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/erp227_objects/view/<?php echo $link->target->id; ?>" class="text-sm font-medium text-blue-600 hover:underline">
                                            <?php echo HTML::chars($link->target->name); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono">
                                        <?php echo number_format($link->quantity, 2); ?> <?php echo $link->unit; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button data-call="editLink" data-call-args="<?php echo HTML::chars(json_encode(array((int) $link->id, (float) $link->quantity))); ?>" class="text-blue-600 hover:text-blue-800 mr-3"><i class="fas fa-edit"></i></button>
                                        <button data-call="deleteLink" data-call-args="[<?php echo $link->id; ?>]" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($links) == 0): ?>
                                <tr id="no-links-row"><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Связи не обнаружены</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Правая колонка: Метаданные и Статус -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4">Статус и Жизненный цикл</h3>
                
                <div class="mb-6">
                    <label class="block text-xs text-gray-500 mb-1">Текущее состояние</label>
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                        <span class="font-semibold text-gray-900 dark:text-white"><?php echo ucfirst($object->status); ?></span>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Создан:</span>
                        <span class="text-gray-900 dark:text-gray-300"><?php echo date('d.m.Y H:i', strtotime($object->created_at)); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Обновлен:</span>
                        <span class="text-gray-900 dark:text-gray-300"><?php echo date('d.m.Y H:i', strtotime($object->updated_at)); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">UUID:</span>
                        <span class="text-xs font-mono text-gray-400"><?php echo substr($object->external_uuid, 0, 8); ?>...</span>
                    </div>
                </div>
            </div>

            <div class="bg-blue-600 rounded-xl shadow-lg p-6 text-white">
                <h3 class="font-bold mb-2">Цифровой двойник</h3>
                <p class="text-blue-100 text-sm mb-4">Объект интегрирован в дерево ltree. Путь в иерархии:</p>
                <div class="bg-blue-700/50 rounded p-3 font-mono text-xs break-all">
                    <?php echo $object->path; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteObject(id) {
    if (confirm('Вы уверены, что хотите удалить этот объект?')) {
        $.post('/erp227_objects/delete/' + id, function(response) {
            if (response.status === 'ok') {
                window.location.href = '/erp227_objects';
            } else {
                alert(response.message || 'Ошибка при удалении');
            }
        }, 'json');
    }
}

function addLink(sourceId) {
    const targetId = prompt('Введите ID целевого объекта:');
    if (!targetId) return;
    
    const quantity = prompt('Введите количество:', '1');
    if (quantity === null) return;

    $.post('/erp227_links/add', {
        source_id: sourceId,
        target_id: targetId,
        quantity: quantity,
        link_type: 'bom'
    }, function(response) {
        if (response.status === 'ok') {
            location.reload();
        } else {
            alert(response.message || 'Ошибка при добавлении связи');
        }
    }, 'json');
}

function editLink(linkId, currentQty) {
    const newQty = prompt('Введите новое количество:', currentQty);
    if (newQty === null || newQty == currentQty) return;

    $.post('/erp227_links/update/' + linkId, {
        quantity: newQty
    }, function(response) {
        if (response.status === 'ok') {
            location.reload();
        } else {
            alert(response.message || 'Ошибка при обновлении');
        }
    }, 'json');
}

function deleteLink(linkId) {
    if (confirm('Удалить эту связь?')) {
        $.post('/erp227_links/delete/' + linkId, function(response) {
            if (response.status === 'ok') {
                $('#link-row-' + linkId).fadeOut();
            } else {
                alert(response.message || 'Ошибка при удалении');
            }
        }, 'json');
    }
}
</script>