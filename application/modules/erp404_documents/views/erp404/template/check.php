<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-start justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-vial mr-2 text-blue-600"></i> Проверка шаблона <?php echo HTML::chars($template->code); ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Что подставится в форме: для шаблона с источником можно указать объект — тогда проверка идёт на его данных.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($can_manage): ?>
                <a href="/documents/templates/edit/<?php echo (int) $template->id; ?>" class="text-sm text-blue-600 hover:text-blue-800">править</a>
            <?php endif; ?>
            <a href="/documents/templates" class="text-sm text-gray-500 hover:text-gray-700">к списку</a>
        </div>
    </div>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ($template->ref_type !== ''): ?>
        <form method="get" action="/documents/templates/check/<?php echo (int) $template->id; ?>"
              class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 flex items-end gap-3 text-sm">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                    Объект: <?php echo HTML::chars($template->type_label()); ?> (id или uuid)
                </label>
                <input type="text" name="ref_id" value="<?php echo HTML::chars((string) $ref_id); ?>"
                       class="border rounded-md px-3 py-2 font-mono dark:bg-gray-700 dark:text-gray-100">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">Проверить</button>
        </form>
    <?php endif; ?>

    <?php if ($problems): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200 rounded-md px-4 py-3 text-sm mb-4">
            <div class="font-medium mb-1">Шаблон не готов:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($problems as $problem): ?>
                    <li><?php echo HTML::chars($problem); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($rendered !== NULL): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200">
                Так документ будет выглядеть (номер — «ПРОВЕРКА»)
            </div>
            <div class="p-6 bg-white dark:bg-gray-900">
                <?php echo $rendered; ?>
            </div>
        </div>
    <?php elseif ( ! $problems): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            Подстановки в шаблоне разобраны, ошибок нет.
            <?php if ($template->ref_type !== ''): ?>
                Укажите объект выше, чтобы увидеть форму на настоящих данных.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 space-y-1">
        <div>Подстановки: <span class="font-mono"><?php echo $template->placeholders() ? HTML::chars(implode(', ', $template->placeholders())) : '—'; ?></span></div>
        <?php if ($template->row_blocks()): ?>
            <div>Списки: <span class="font-mono"><?php echo HTML::chars(implode(', ', $template->row_blocks())); ?></span></div>
        <?php endif; ?>
    </div>
</div>
