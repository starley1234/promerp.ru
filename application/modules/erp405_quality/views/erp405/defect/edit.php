<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
        <i class="fas fa-tag mr-2 text-green-600"></i>
        <?php echo $code ? 'Код '.HTML::chars($code->code) : 'Новый код дефекта'; ?>
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Код, который уже встречался в несоответствиях, не удаляется — его выключают: история дефектов должна остаться читаемой.
    </p>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/quality/defects/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-4 text-sm">
        <?php if ($code): ?>
            <input type="hidden" name="id" value="<?php echo (int) $code->id; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Обозначение</label>
                <input type="text" name="code" required <?php echo $code ? 'readonly' : ''; ?>
                       value="<?php echo $code ? HTML::chars($code->code) : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 font-mono dark:bg-gray-700 dark:text-gray-100 <?php echo $code ? 'bg-gray-100' : ''; ?>">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название</label>
                <input type="text" name="name" required value="<?php echo $code ? HTML::chars($code->name) : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Категория</label>
                <select name="category" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($code AND $code->category === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Важность</label>
                <select name="severity" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($severities as $key => $label): ?>
                        <option value="<?php echo (int) $key; ?>" <?php echo ($code AND (int) $code->severity === (int) $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Описание</label>
            <textarea name="description" rows="3" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"><?php echo $code ? HTML::chars((string) $code->description) : ''; ?></textarea>
        </div>

        <?php if ($code): ?>
            <div class="text-xs text-gray-500 dark:text-gray-400">Встречался в несоответствиях: <?php echo (int) $usage; ?> раз.</div>
            <?php if ((int) $usage > 0 AND $code->is_active()): ?>
                <p class="text-xs text-amber-600">
                    Код используется — вместо удаления выключите его (кнопка ниже): история дефектов останется читаемой.
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Сохранить</button>
            <a href="/quality/defects" class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
        </div>
    </form>

    <?php if ($code AND $code->is_active()): ?>
        <form method="post" action="/quality/defects/save" class="mt-4">
            <input type="hidden" name="id" value="<?php echo (int) $code->id; ?>">
            <input type="hidden" name="name" value="<?php echo HTML::chars($code->name); ?>">
            <input type="hidden" name="category" value="<?php echo HTML::chars($code->category); ?>">
            <input type="hidden" name="severity" value="<?php echo (int) $code->severity; ?>">
            <input type="hidden" name="is_active" value="0">
            <button type="submit" class="text-xs text-red-600 hover:text-red-800">выключить код</button>
        </form>
    <?php endif; ?>
</div>
