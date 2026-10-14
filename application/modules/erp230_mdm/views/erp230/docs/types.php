<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="max-w-8xl mx-auto">
        
        <!-- Заголовок -->
        <div class="mb-10 border-b border-gray-200 pb-6">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Методология управления типами компонентов</h1>
            <p class="text-lg text-slate-600">Стандарты заведения, классификации и описания схем данных в модуле MDM</p>
        </div>

        <!-- Секция 1: Иерархическая структура -->
        <section class="mb-12">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-sitemap"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">1. Иерархия и наследование</h2>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                <p class="mb-4 leading-relaxed">
                    Система MDM использует механизм <strong>ltree</strong> для построения древовидной структуры типов. 
                    Это позволяет организовать классификатор от общего к частному (например: <code>electronics.active.ic.microcontroller</code>).
                </p>
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <h4 class="font-bold text-sm uppercase text-slate-500 mb-2">Правила именования ID</h4>
                        <ul class="list-disc list-inside text-sm space-y-2 text-slate-700">
                            <li>Только строчные латинские буквы (a-z)</li>
                            <li>Цифры и символ подчеркивания (_)</li>
                            <li>Запрещены пробелы и спецсимволы</li>
                            <li>Пример: <code class="text-blue-600">raw_material_steel</code></li>
                        </ul>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <h4 class="font-bold text-sm uppercase text-slate-500 mb-2">Принцип наследования</h4>
                        <p class="text-sm text-slate-700">
                            Дочерние типы наследуют логику обработки от родительских. При поиске по родительскому узлу 
                            система автоматически включает все вложенные подтипы.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Секция 2: JSON-схемы -->
        <section class="mb-12">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-code"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">2. Описание схем атрибутов</h2>
            </div>
            <div class="bg-slate-900 rounded-xl p-6 shadow-xl mb-6">
                <h4 class="text-indigo-400 font-mono text-sm mb-4">// Пример валидной схемы типа</h4>
                <pre class="text-gray-300 font-mono text-xs leading-relaxed">
{
  "required": ["name", "manufacturer"],
  "properties": {
    "weight": { "type": "number", "uom": "kg" },
    "material": { "type": "string", "enum": ["Steel", "Aluminum", "Plastic"] },
    "is_hazardous": { "type": "boolean", "default": false }
  }
}</pre>
            </div>
            <div class="prose prose-slate max-w-none">
                <p>Каждый тип может иметь жестко заданную <strong>JSON-схему</strong>. Она определяет:</p>
                <ul class="grid md:grid-cols-3 gap-4 list-none p-0">
                    <li class="bg-white p-4 rounded-lg border border-slate-200 text-sm">
                        <strong class="block text-indigo-600 mb-1">Валидация</strong>
                        Контроль типов данных (строка, число, дата) при импорте из внешних систем.
                    </li>
                    <li class="bg-white p-4 rounded-lg border border-slate-200 text-sm">
                        <strong class="block text-indigo-600 mb-1">Обязательность</strong>
                        Список полей, без которых объект не может перейти в состояние <code>active</code>.
                    </li>
                    <li class="bg-white p-4 rounded-lg border border-slate-200 text-sm">
                        <strong class="block text-indigo-600 mb-1">Ед. измерения</strong>
                        Привязка ключей к справочнику UOM для автоматической конвертации.
                    </li>
                </ul>
            </div>
        </section>

        <!-- Секция 3: Рекомендации -->
        <section class="mb-12">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-emerald-600 text-white rounded-lg flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-check-double"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">3. Рекомендации по заведению</h2>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-6">
                <div class="space-y-4">
                    <div class="flex">
                        <i class="fas fa-info-circle text-emerald-600 mt-1 mr-3"></i>
                        <p class="text-sm text-emerald-900">
                            <strong>Избегайте избыточной вложенности:</strong> Оптимальная глубина дерева типов — 3-5 уровней. Слишком глубокая иерархия усложняет навигацию и поиск.
                        </p>
                    </div>
                    <div class="flex">
                        <i class="fas fa-info-circle text-emerald-600 mt-1 mr-3"></i>
                        <p class="text-sm text-emerald-900">
                            <strong>Атомарность атрибутов:</strong> Не создавайте типы под каждое уникальное значение. Используйте атрибуты (properties) для вариативности внутри одного типа.
                        </p>
                    </div>
                    <div class="flex">
                        <i class="fas fa-info-circle text-emerald-600 mt-1 mr-3"></i>
                        <p class="text-sm text-emerald-900">
                            <strong>Единый язык:</strong> Display Name должен быть понятен конечному пользователю (на русском языке), в то время как ID типа всегда остается техническим.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <div class="bg-slate-100 rounded-lg p-4 text-center">
            <p class="text-xs text-slate-500 uppercase tracking-widest">
                Управление типами доступно пользователям с ролью <span class="font-bold text-slate-700">MDM Admin</span> или <span class="font-bold text-slate-700">Data Steward</span>
            </p>
        </div>
    </div>
</div>