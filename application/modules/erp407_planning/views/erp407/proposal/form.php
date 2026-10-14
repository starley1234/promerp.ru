<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">

    <div class="mb-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <a href="/planning/proposals" class="text-blue-600 hover:text-blue-800">Предложения</a>
            <span class="mx-1">/</span>
            <span><?php echo $proposal ? HTML::chars($proposal->number) : 'новое предложение'; ?></span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            <?php echo $proposal ? 'Правка предложения' : 'Предложение вручную'; ?>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Обычно предложения создаются из расчёта потребности. Ручное предложение нужно,
            когда решение принимается вне плана: аварийная закупка, замена, опытная партия.
        </p>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ($proposal AND $proposal->is_locked()): ?>
        <div class="border rounded-md px-4 py-3 text-sm mb-4 bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
            Предложение в состоянии «<?php echo HTML::chars($proposal->status_label()); ?>» — правки запрещены.
        </div>
    <?php endif; ?>

    <form method="post" action="/planning/proposals/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
        <?php if ($proposal): ?>
            <input type="hidden" name="id" value="<?php echo (int) $proposal->id; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Способ обеспечения</label>
                <select name="kind" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($kinds as $key => $label): ?>
                        <option value="<?php echo HTML::chars($key); ?>"
                            <?php echo ($proposal AND $proposal->kind === $key) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    «Выпустить» при передаче создаёт заказ производства (erp402),
                    «Закупить» — заявку закупки (erp403).
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Программа</label>
                <select name="program_id" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <option value="">без привязки к программе</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo (int) $program->id; ?>"
                            <?php echo ($proposal AND (int) $proposal->program_id === (int) $program->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($program->number.' · '.$program->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Позиция</label>
            <input type="text" name="name" required maxlength="255"
                   value="<?php echo $proposal ? HTML::chars($proposal->name) : ''; ?>"
                   placeholder="что нужно закупить или выпустить"
                   class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Количество</label>
                <input type="number" name="qty" step="0.001" min="0.001" required
                       value="<?php echo $proposal ? (float) $proposal->qty : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Ед.</label>
                <input type="text" name="uom_code" maxlength="20"
                       value="<?php echo $proposal ? HTML::chars($proposal->uom_code) : 'шт'; ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Нужно к</label>
                <input type="date" name="due_date"
                       value="<?php echo ($proposal AND $proposal->due_date) ? HTML::chars($proposal->due_date) : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Заказать к</label>
                <input type="date" name="order_date"
                       value="<?php echo ($proposal AND $proposal->order_date) ? HTML::chars($proposal->order_date) : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Приоритет</label>
            <select name="priority" class="w-full md:w-48 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?php echo (int) $key; ?>"
                        <?php echo ($proposal AND (int) $proposal->priority === (int) $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Примечание</label>
            <textarea name="note" rows="3"
                      class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"
                      placeholder="обоснование, ссылка на договор или заявку"><?php echo $proposal ? HTML::chars((string) $proposal->note) : ''; ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="/planning/proposals" class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Отмена</a>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
        </div>
    </form>
</div>
