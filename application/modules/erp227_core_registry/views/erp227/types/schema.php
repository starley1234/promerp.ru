<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="/erp227_types" class="text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400">
                            Типы объектов
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Конструктор атрибутов
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Схема атрибутов: <?php echo HTML::chars($type->name); ?>
            </h1>
        </div>
        <div class="flex gap-2">
            <a href="/erp227_types" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 transition-colors">
                Назад к списку
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Панель инструментов конструктора -->
        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 card-shadow">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-tools mr-2 text-blue-500"></i> Инструменты
                </h2>
                <div class="space-y-2">
                    <p class="text-sm text-gray-500 mb-4">Настройте структуру JSON-схемы для валидации дополнительных полей объектов данного типа.</p>
                    
                    <div class="p-3 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                        <h3 class="text-xs font-bold uppercase text-gray-400 mb-2">Пример структуры</h3>
                        <pre class="text-[10px] font-mono text-blue-600 dark:text-blue-400 overflow-x-auto">
{
  "fields": [
    {
      "name": "weight",
      "type": "number",
      "label": "Вес (кг)"
    }
  ]
}</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Редактор JSON -->
        <div class="lg:col-span-8">
            <form action="" method="POST" id="schema-form">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
                    <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 font-mono">attribute_schema.json</span>
                        <div id="json-status" class="text-xs text-green-500 hidden">
                            <i class="fas fa-check-circle"></i> JSON валиден
                        </div>
                    </div>
                    
                    <div class="p-0">
                        <textarea name="attribute_schema" id="schema_editor" rows="20" 
                                  class="w-full p-6 bg-gray-900 text-green-400 font-mono text-sm focus:ring-0 outline-none border-none resize-none"
                                  spellcheck="false"><?php echo !empty($type->attribute_schema) ? HTML::chars($type->attribute_schema) : "{\n  \"fields\": []\n}"; ?></textarea>
                    </div>

                    <div class="p-6 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center">
                            <i class="fas fa-save mr-2"></i> Сохранить схему
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const editor = document.getElementById('schema_editor');
    const status = document.getElementById('json-status');

    editor.addEventListener('input', function() {
        try {
            JSON.parse(this.value);
            status.classList.remove('hidden', 'text-red-500');
            status.classList.add('text-green-500');
            status.innerHTML = '<i class="fas fa-check-circle"></i> JSON валиден';
        } catch (e) {
            status.classList.remove('hidden', 'text-green-500');
            status.classList.add('text-red-500');
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Ошибка в JSON';
        }
    });

    // Инициализация проверки при загрузке
    editor.dispatchEvent(new Event('input'));
</script>