<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
        <i class="fas fa-clipboard-list mr-2 text-green-600"></i>
        <?php echo $inspection ? 'Контроль '.HTML::chars($inspection->number) : 'Новый контроль качества'; ?>
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Партия берётся под контроль: вид контроля, позиция и количество. Дефекты записываются в карточке контроля.
    </p>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/quality/inspections/edit<?php echo $inspection ? '/'.(int) $inspection->id : ''; ?>"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-4 text-sm">

        <?php if ( ! $inspection): ?>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Вид контроля</label>
                <select name="kind" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($kinds as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Позиция (id номенклатуры)</label>
                    <input type="number" name="item_id" min="1" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название позиции (если id нет)</label>
                    <input type="text" name="item_name" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Склад, где лежит партия</label>
                <select name="warehouse_id" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">не указан</option>
                    <?php foreach ($warehouses as $id => $name): ?>
                        <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Без склада и номенклатуры карантин и утиль невозможны — нечем двигать.</p>
            </div>
        <?php else: ?>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название позиции</label>
                <input type="text" name="item_name" value="<?php echo HTML::chars($inspection->item_name); ?>"
                       class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество в партии</label>
            <input type="number" step="0.001" min="0" name="qty" required
                   value="<?php echo $inspection ? (float) $inspection->qty : 1; ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Примечание</label>
            <textarea name="note" rows="3" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"><?php echo $inspection ? HTML::chars((string) $inspection->note) : ''; ?></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Сохранить</button>
            <a href="/quality/inspections" class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
        </div>
    </form>
</div>
