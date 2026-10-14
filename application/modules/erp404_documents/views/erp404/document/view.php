<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-start justify-between mb-4">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                <a href="/documents" class="hover:text-gray-700">Документы</a>
                <?php if ($document->ref_type !== ''): ?>
                    · <?php echo HTML::chars(Model_Erp404_Template::ref_type_label($document->ref_type)); ?>
                    <?php if ($document->ref_id !== NULL): ?>
                        <span class="font-mono">#<?php echo HTML::chars((string) $document->ref_id); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <span class="font-mono"><?php echo HTML::chars($document->number); ?></span>
                <span class="px-2 py-0.5 rounded text-xs align-middle <?php echo $document->status_class(); ?>">
                    <?php echo HTML::chars($document->status_label()); ?>
                </span>
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><?php echo HTML::chars($document->title); ?></p>
        </div>
        <?php if ($can_print): ?>
            <div class="flex items-center gap-2">
                <?php if ($document->is_editable()): ?>
                    <a href="/documents/edit/<?php echo (int) $document->id; ?>" class="px-3 py-2 bg-white border text-gray-700 text-sm rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fas fa-pen mr-1"></i> Править
                    </a>
                <?php endif; ?>
                <?php if ($document->is_printable()): ?>
                    <a href="/documents/print/<?php echo (int) $document->id; ?>" target="_blank"
                       class="px-3 py-2 bg-white border text-gray-700 text-sm rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fas fa-print mr-1"></i> Открыть форму
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ($problems): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 rounded-md px-4 py-3 text-sm mb-4">
            <div class="font-medium mb-1">Выпуск пока невозможен:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($problems as $problem): ?>
                    <li><?php echo HTML::chars($problem); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Печатная форма</div>
                    <?php if ($template): ?>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <?php echo HTML::chars($template->code); ?>
                            · версия <?php echo $document->template_version ? (int) $document->template_version : (int) $template->version; ?>
                            <?php if ($document->template_version AND (int) $document->template_version < (int) $template->version): ?>
                                <span class="text-amber-600">(шаблон с тех пор обновили — бумага остаётся прежней)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900">
                    <?php if ($document->rendered !== NULL AND $document->rendered !== ''): ?>
                        <div class="bg-white shadow-sm p-6 prose max-w-none dark:prose-invert">
                            <?php echo $document->rendered; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Снимка ещё нет: документ не выпущен. Снимок фиксируется при выпуске и после этого не меняется —
                            правки шаблона задним числом на бумагу не влияют.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $print_rows = array();
            foreach ($prints as $print)
            {
                $print_rows[] = array(
                    HTML::chars((string) $print->printed_at),
                    HTML::chars($print->printed_by_name() !== '' ? $print->printed_by_name() : 'система'),
                    $print->copies(),
                    HTML::chars((string) $print->note),
                );
            }
            Component::begin('card', array('title'=>'Журнал печати','icon'=>'fas fa-print','flush'=>TRUE));
            echo Component::render('table', array(
                'caption'=>'Журнал печати документа '.(string) $document->number,
                'columns'=>array(
                    array('label'=>'Когда','nowrap'=>TRUE),
                    array('label'=>'Кто'),
                    array('label'=>'Копий','align'=>'right'),
                    array('label'=>'Примечание','priority'=>2),
                ),
                'rows'=>$print_rows,
                'empty_title'=>'Документ ещё не печатали',
                'empty_hint'=>'После отметки печати здесь появятся время, исполнитель и число копий.',
            ));
            Component::end();
            ?>
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-sm">
                <div class="font-medium text-gray-700 dark:text-gray-200 mb-3">Шаги</div>

                <?php if ($can_print AND $document->is_editable()): ?>
                    <form method="post" action="/documents/act/issue/<?php echo (int) $document->id; ?>" class="mb-4 space-y-2">
                        <?php if ($document->ref_type === ''): ?>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Данные для универсального шаблона (JSON)</label>
                            <textarea name="payload" rows="4" placeholder='{"ref": {"custom": "значение"}}'
                                      class="w-full border rounded-md px-2 py-2 font-mono text-xs dark:bg-gray-700 dark:text-gray-100"></textarea>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Данные возьмутся у источника (<?php echo HTML::chars(Model_Erp404_Template::ref_type_label($document->ref_type)); ?>).
                            </p>
                        <?php endif; ?>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-check mr-1"></i> Выпустить документ
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_print AND $document->is_printable()): ?>
                    <form method="post" action="/documents/act/print/<?php echo (int) $document->id; ?>" class="mb-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500 dark:text-gray-400">Копий</label>
                            <input type="number" name="copies" value="1" min="1" max="50"
                                   class="w-20 border rounded-md px-2 py-1 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <i class="fas fa-print mr-1"></i> Отметить печать
                        </button>
                        <p class="text-xs text-gray-400">Отметка попадает в журнал, форма открывается в новой вкладке.</p>
                    </form>
                <?php endif; ?>

                <?php if ($can_print AND $document->is_archivable()): ?>
                    <form method="post" action="/documents/act/archive/<?php echo (int) $document->id; ?>" class="mb-4">
                        <button type="submit" class="w-full px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">
                            <i class="fas fa-box-archive mr-1"></i> Сдать в архив
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_print AND $document->is_cancellable()): ?>
                    <form method="post" action="/documents/act/cancel/<?php echo (int) $document->id; ?>" class="space-y-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400">Причина отмены (обязательна)</label>
                        <input type="text" name="reason" required
                               class="w-full border rounded-md px-2 py-1 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <i class="fas fa-ban mr-1"></i> Отменить документ
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ( ! $can_print): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Нужно право <span class="font-mono">erp404.print</span>, чтобы выпускать и печатать.</p>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-sm space-y-2">
                <div class="font-medium text-gray-700 dark:text-gray-200 mb-1">Документ</div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Создан</span><span class="text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $document->created_at); ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Выпущен</span><span class="text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $document->issued_at); ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Последняя печать</span><span class="text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $document->printed_at); ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Напечатано раз</span><span class="text-gray-700 dark:text-gray-200"><?php echo (int) $document->print_count; ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Архив</span><span class="text-gray-700 dark:text-gray-200"><?php echo HTML::chars((string) $document->archived_at); ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">uuid</span><span class="font-mono text-xs text-gray-500"><?php echo HTML::chars((string) $document->uuid); ?></span></div>
                <?php if ($document->note): ?>
                    <div class="pt-2 border-t dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 whitespace-pre-line"><?php echo HTML::chars((string) $document->note); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
