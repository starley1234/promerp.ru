<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement/requests" class="hover:text-blue-600">Заявки</a>
        <span class="mx-1">/</span>
        <span><?php echo $request ? 'Правка '.HTML::chars($request->number) : 'Новая заявка'; ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/procurement/requests/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
        <?php if ($request): ?>
            <input type="hidden" name="id" value="<?php echo (int) $request->id; ?>">
        <?php endif; ?>

        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
            <?php echo $request ? 'Заявка '.HTML::chars($request->number) : 'Новая заявка на закупку'; ?>
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Нужен к</label>
                <input type="date" name="need_date" value="<?php echo HTML::chars($request ? (string) $request->need_date : date('Y-m-d', strtotime('+14 days'))); ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Приоритет</label>
                <select name="priority" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($priorities as $value => $label): ?>
                        <option value="<?php echo (int) $value; ?>" <?php echo ($request AND (int) $request->priority === (int) $value) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Склад</label>
                <select name="warehouse_id" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <option value="">склад по умолчанию</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ($request AND (int) $request->warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Пояснение</label>
            <textarea name="note" rows="2" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"><?php echo HTML::chars($request ? (string) $request->note : ''); ?></textarea>
        </div>

        <?php if ( ! $request): ?>
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Первая позиция</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Позиция</label>
                        <input type="text" name="name" required placeholder="что купить"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Номенклатура (id)</label>
                        <input type="number" name="item_id" placeholder="из реестра"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество</label>
                        <input type="number" step="0.001" name="qty" value="1" min="0"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Цена, ₽</label>
                        <input type="number" step="0.01" name="price" value="0"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Остальные позиции добавляются в карточке заявки. Если указать номенклатуру и не указать цену,
                    подставится последняя известная цена.
                </p>
            </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
            <a href="<?php echo $request ? '/procurement/requests/view/'.(int) $request->id : '/procurement/requests'; ?>"
               class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Отмена</a>
        </div>
    </form>
</div>
