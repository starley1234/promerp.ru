<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <i class="fas fa-dolly-flatbed mr-2 text-blue-600"></i> Провести складскую операцию
    </h1>

    <?php echo View::factory('erp401/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ( ! empty($errors)): ?>
        <div class="mb-4 border border-red-200 bg-red-50 dark:bg-red-900/30 dark:border-red-700 text-red-800 dark:text-red-200 rounded-md px-4 py-3 text-sm">
            <?php foreach ($errors as $error): ?>
                <div><i class="fas fa-exclamation-circle mr-1"></i> <?php echo HTML::chars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/warehouse/movements/post" id="erp401-movement-form"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5 max-w-3xl">

        <!-- Тип операции -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Тип операции</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?php foreach ($types as $key => $label): ?>
                    <label class="flex items-center p-3 border rounded-md cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700
                                  border-gray-300 dark:border-gray-600">
                        <input type="radio" name="type" value="<?php echo HTML::chars($key); ?>"
                               class="mr-2 text-blue-600"
                               <?php echo (Arr::get($old, 'type', 'receipt') === $key) ? 'checked' : ''; ?>>
                        <span class="text-sm text-gray-800 dark:text-gray-100"><?php echo HTML::chars($label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Номенклатура -->
        <div>
            <label for="item_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Номенклатура</label>
            <select id="item_id" name="item_id" required
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">— выберите позицию —</option>
                <?php foreach ($catalog as $entry): ?>
                    <option value="<?php echo (int) $entry['id']; ?>"
                        <?php echo ((string) Arr::get($old, 'item_id') === (string) $entry['id']) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($entry['code'].' — '.$entry['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Позиции берутся из реестра объектов (модуль erp227).
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Склад-источник -->
            <div>
                <label for="warehouse_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                    Склад <span class="text-xs text-gray-400">(источник)</span>
                </label>
                <select id="warehouse_id" name="warehouse_id" required
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>"
                            <?php echo ((string) Arr::get($old, 'warehouse_id') === (string) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Склад-получатель -->
            <div>
                <label for="warehouse_to_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                    Склад-получатель <span class="text-xs text-gray-400">(для перемещения)</span>
                </label>
                <select id="warehouse_to_id" name="warehouse_to_id"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">— не задан —</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>"
                            <?php echo ((string) Arr::get($old, 'warehouse_to_id') === (string) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Количество -->
            <div>
                <label for="qty" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Количество</label>
                <input type="number" id="qty" name="qty" step="0.001" min="0.001" required
                       value="<?php echo HTML::chars((string) Arr::get($old, 'qty', '')); ?>"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            </div>

            <!-- Единица измерения -->
            <div>
                <label for="uom_code" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Единица измерения</label>
                <select id="uom_code" name="uom_code"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <?php foreach ($units as $code => $name): ?>
                        <option value="<?php echo HTML::chars($code); ?>"
                            <?php echo (Arr::get($old, 'uom_code', 'pcs') === $code) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Документ-основание -->
            <div>
                <label for="doc_type" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Документ-основание</label>
                <input type="text" id="doc_type" name="doc_type" value="<?php echo HTML::chars((string) Arr::get($old, 'doc_type', 'manual')); ?>"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            </div>

            <!-- Номер документа -->
            <div>
                <label for="doc_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Номер документа</label>
                <input type="number" id="doc_id" name="doc_id" value="<?php echo HTML::chars((string) Arr::get($old, 'doc_id', '')); ?>"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            </div>
        </div>

        <!-- Комментарий -->
        <div>
            <label for="note" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Комментарий</label>
            <textarea id="note" name="note" rows="2"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"><?php echo HTML::chars((string) Arr::get($old, 'note', '')); ?></textarea>
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit"
                    class="inline-flex items-center px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                <i class="fas fa-check mr-2"></i> Провести
            </button>
            <a href="/warehouse/movements"
               class="px-5 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>

</div>
