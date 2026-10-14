<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-start justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-code mr-2 text-blue-600"></i>
                <?php echo $template ? 'Шаблон '.HTML::chars($template->code) : 'Новый шаблон'; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Код шаблона не меняется: на него ссылаются документы и агенты. Выключённый шаблон перестаёт предлагаться для новых документов,
                но выпущенные по нему бумаги читаются дальше.
            </p>
        </div>
        <a href="/documents/templates" class="text-sm text-gray-500 hover:text-gray-700">к списку</a>
    </div>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/documents/templates/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-4 text-sm">
        <?php if ($template): ?>
            <input type="hidden" name="id" value="<?php echo (int) $template->id; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Код</label>
                <input type="text" name="code" required <?php echo $template ? 'readonly' : ''; ?>
                       value="<?php echo $template ? HTML::chars($template->code) : ''; ?>"
                       placeholder="ORDER-SUPPLIER"
                       class="w-full border rounded-md px-3 py-2 font-mono dark:bg-gray-700 dark:text-gray-100 <?php echo $template ? 'bg-gray-100' : ''; ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название</label>
                <input type="text" name="name" required value="<?php echo $template ? HTML::chars($template->name) : ''; ?>"
                       class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Вид формы</label>
                <select name="kind" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($kinds as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($template AND $template->kind === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Тип объекта-источника</label>
                <select name="ref_type" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($ref_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($template AND $template->ref_type === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Тип объекта у использованного шаблона не меняется: заведите новый, старые документы останутся читаемыми.
                </p>
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?php echo ( ! $template OR $template->is_active()) ? 'checked' : ''; ?>>
                    шаблон active
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Тело формы (HTML с подстановками)</label>
            <textarea name="body" rows="16" required
                      class="w-full border rounded-md px-3 py-2 font-mono text-xs dark:bg-gray-700 dark:text-gray-100"><?php echo $template ? HTML::chars((string) $template->body) : ''; ?></textarea>
            <div class="text-xs text-gray-400 mt-1 space-y-0.5">
                <div>Подстановка: <span class="font-mono">{{ref.number}}</span>, <span class="font-mono">{{document.number}}</span>, <span class="font-mono">{{meta.date}}</span>.</div>
                <div>Список: <span class="font-mono">{{#rows ref.lines}}…{{/rows}}</span>, внутри — поля строки и <span class="font-mono">{{index}}</span> (номер по порядку).</div>
                <div>Незнакомая подстановка — отказ при сохранении: молча оставлять её в бумаге нельзя.</div>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Примечание</label>
            <textarea name="note" rows="2" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"><?php echo $template ? HTML::chars((string) $template->note) : ''; ?></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Сохранить</button>
            <?php if ($template): ?>
                <a href="/documents/templates/check/<?php echo (int) $template->id; ?>" class="text-sm text-blue-600 hover:text-blue-800">проверить на данных</a>
            <?php endif; ?>
            <a href="/documents/templates" class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
        </div>
    </form>

    <?php if ($template): ?>
        <div class="mt-4 bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-xs text-gray-500 dark:text-gray-400">
            <div class="mb-2">
                Версия: <span class="text-gray-700 dark:text-gray-200"><?php echo (int) $template->version; ?></span>,
                выпущено документов: <span class="text-gray-700 dark:text-gray-200"><?php echo (int) $usage; ?></span>,
                uuid: <span class="font-mono"><?php echo HTML::chars((string) $template->uuid); ?></span>
            </div>
            <div class="mb-1">Используемые подстановки:</div>
            <div class="font-mono"><?php echo $template->placeholders() ? HTML::chars(implode(', ', $template->placeholders())) : '—'; ?></div>
            <?php if ($template->row_blocks()): ?>
                <div class="mt-2 mb-1">Списки:</div>
                <div class="font-mono"><?php echo HTML::chars(implode(', ', $template->row_blocks())); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
