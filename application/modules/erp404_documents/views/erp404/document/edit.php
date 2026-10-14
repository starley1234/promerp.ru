<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
        <i class="fas fa-file-alt mr-2 text-blue-600"></i>
        <?php echo $document ? 'Документ '.HTML::chars($document->number) : 'Новый документ'; ?>
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Черновик правится свободно. Выпуск фиксирует снимок и версию шаблона — после этого документ не переписывается.
    </p>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/documents/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-4 text-sm">
        <?php if ($document): ?>
            <input type="hidden" name="id" value="<?php echo (int) $document->id; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Шаблон формы</label>
                <?php if ($document): ?>
                    <input type="text" readonly value="<?php echo HTML::chars((string) $document->template_code); ?>"
                           class="w-full border rounded-md px-3 py-2 font-mono bg-gray-100 dark:bg-gray-700 dark:text-gray-100">
                <?php else: ?>
                    <select name="template_id" required class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo (int) $template->id; ?>"
                                <?php echo ($preselect AND $preselect === (string) $template->code) ? 'selected' : ''; ?>>
                                <?php echo HTML::chars($template->code.' — '.$template->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Название документа</label>
                <input type="text" name="title" value="<?php echo $document ? HTML::chars($document->title) : ''; ?>"
                       placeholder="по умолчанию — название шаблона"
                       class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Тип объекта</label>
                <select name="ref_type" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach ($ref_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>"
                            <?php echo (($document AND $document->ref_type === $key) OR ( ! $document AND isset($preselect_ref_type) AND $preselect_ref_type === $key)) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Объект (id или uuid)</label>
                <input type="text" name="ref_id" value="<?php echo ($document AND $document->ref_id !== NULL) ? HTML::chars((string) $document->ref_id) : HTML::chars(isset($preselect_ref_id) ? (string) $preselect_ref_id : ''); ?>"
                       class="w-full border rounded-md px-3 py-2 font-mono dark:bg-gray-700 dark:text-gray-100">
                <p class="text-xs text-gray-400 mt-1">Заполняется из карточки объекта; для сменного задания — дата ГГГГ-ММ-ДД.</p>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Примечание</label>
            <textarea name="note" rows="3" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"><?php echo $document ? HTML::chars((string) $document->note) : ''; ?></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Сохранить черновик</button>
            <a href="<?php echo $document ? '/documents/view/'.(int) $document->id : '/documents'; ?>" class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
        </div>
    </form>

    <?php if ($document): ?>
        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Выпуск, печать, архив и отмена — на <a href="/documents/view/<?php echo (int) $document->id; ?>" class="text-blue-600 hover:text-blue-800">карточке документа</a>.
        </div>
    <?php endif; ?>
</div>
