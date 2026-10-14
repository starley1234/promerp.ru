<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <?php echo isset($object) ? 'Редактирование объекта: ' . HTML::chars($object->name) : 'Создание нового объекта'; ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            Универсальный реестр объектов ERP v2.27
        </p>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo HTML::chars($error); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Основные поля -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Наименование объекта *</label>
                            <input type="text" name="name" required
                                   value="<?php echo isset($object) ? HTML::chars($object->name) : ''; ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Системный код (Артикул/ID) *</label>
                            <input type="text" name="code" required
                                   <?php echo isset($object) ? 'readonly' : ''; ?>
                                   value="<?php echo isset($object) ? HTML::chars($object->code) : ''; ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-white <?php echo isset($object) ? 'bg-gray-50' : ''; ?>">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Тип объекта</label>
                            <select name="type_id" <?php echo isset($object) ? 'disabled' : ''; ?>
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type->id; ?>" 
                                        <?php echo (isset($object) && $object->type_id == $type->id) ? 'selected' : ''; ?>>
                                        <?php echo HTML::chars($type->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Динамические атрибуты (JSON) -->
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Дополнительные характеристики</h3>
                    <div id="attributes-container" class="space-y-3">
                        <?php 
                        $attrs = isset($object) ? json_decode($object->attributes, true) : [];
                        if (!empty($attrs)):
                            foreach ($attrs as $key => $value): ?>
                                <div class="flex gap-2 attr-row">
                                    <input type="text" name="attr_keys[]" value="<?php echo HTML::chars($key); ?>" placeholder="Ключ" class="w-1/3 px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                    <input type="text" name="attr_values[]" value="<?php echo HTML::chars($value); ?>" placeholder="Значение" class="w-2/3 px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                    <button type="button" data-action="remove-parent" class="text-red-500 px-2"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                    <button type="button" data-call="addAttributeRow" class="mt-4 text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-plus mr-1"></i> Добавить поле
                    </button>
                </div>
            </div>

            <!-- Боковая панель -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Родительский объект (ID)</label>
                        <input type="number" name="parent_id" 
                               value="<?php echo isset($object) ? $object->parent_id : ''; ?>"
                               class="w-full px-4 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Статус</label>
                        <select name="status" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                            <option value="active" <?php echo (isset($object) && $object->status == 'active') ? 'selected' : ''; ?>>Активен</option>
                            <option value="draft" <?php echo (isset($object) && $object->status == 'draft') ? 'selected' : ''; ?>>Черновик</option>
                            <option value="archived" <?php echo (isset($object) && $object->status == 'archived') ? 'selected' : ''; ?>>Архив</option>
                        </select>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                            <?php echo isset($object) ? 'Создать ревизию' : 'Создать объект'; ?>
                        </button>
                        <a href="/erp227_objects<?php echo isset($object) ? '/view/'.$object->id : ''; ?>" 
                           class="block text-center mt-3 text-sm text-gray-500 hover:text-gray-700">
                            Отмена
                        </a>
                    </div>
                </div>

                <?php if (isset($object)): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-4">
                    <h4 class="text-blue-800 dark:text-blue-300 text-sm font-bold mb-2">Информация о версии</h4>
                    <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                        <li>Текущая ревизия: <?php echo $object->revision; ?></li>
                        <li>Дата изменения: <?php echo date('d.m.Y H:i', strtotime($object->updated_at)); ?></li>
                        <li>Путь: <?php echo $object->path; ?></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
function addAttributeRow() {
    const container = document.getElementById('attributes-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2 attr-row';
    div.innerHTML = `
        <input type="text" name="attr_keys[]" placeholder="Ключ" class="w-1/3 px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
        <input type="text" name="attr_values[]" placeholder="Значение" class="w-2/3 px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-600 dark:text-white">
        <button type="button" data-action="remove-parent" class="text-red-500 px-2"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

// Обработка отправки для преобразования массивов ключей/значений в JSON-атрибуты
document.querySelector('form').addEventListener('submit', function(e) {
    const keys = document.getElementsByName('attr_keys[]');
    const values = document.getElementsByName('attr_values[]');
    const attributes = {};
    
    for(let i=0; i<keys.length; i++) {
        if(keys[i].value.trim() !== '') {
            attributes[keys[i].value] = values[i].value;
        }
    }
    
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'attributes';
    hiddenInput.value = JSON.stringify(attributes);
    this.appendChild(hiddenInput);
});
</script>