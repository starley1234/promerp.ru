<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-code mr-2 text-blue-600"></i> Шаблоны печатных форм
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Форма — HTML с подстановками <span class="font-mono">{{ref.number}}</span> и блоками
                <span class="font-mono">{{#rows ref.lines}}…{{/rows}}</span>. Правка тела поднимает версию: выпущенные документы помнят свою.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/documents/templates/edit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-plus-circle mr-1"></i> Новый шаблон
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/documents/templates" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Тип объекта</label>
            <select name="ref_type" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach ($ref_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['ref_type'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Вид</label>
            <select name="kind" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach ($kinds as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['kind'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">Найти</button>
            <a href="/documents/templates" class="text-xs text-gray-500 hover:text-gray-700">сбросить</a>
        </div>
    </form>

    <?php
    $table_rows = array();
    foreach ($rows as $row)
    {
        $template = $row['template'];
        $actions = '<a href="/documents/templates/check/'.(int) $template->id.'" class="text-xs text-gray-500 hover:text-gray-700">проверить</a>';
        if ($can_manage) $actions .= ' · <a href="/documents/templates/edit/'.(int) $template->id.'" class="text-xs text-blue-600 hover:text-blue-800">править</a>';
        $table_rows[] = array(
            'class'=>$template->is_active() ? '' : 'opacity-50',
            'cells'=>array(
                '<span class="font-mono">'.HTML::chars($template->code).'</span>',
                HTML::chars($template->name),
                HTML::chars($template->type_label()),
                HTML::chars($template->kind_label()),
                (int) $template->version,
                (int) $row['usage'],
                $actions,
            ),
        );
    }
    echo Component::render('table', array(
        'caption'=>'Шаблоны печатных форм',
        'aria_label'=>'Шаблоны печатных форм',
        'columns'=>array(
            array('label'=>'Код','nowrap'=>TRUE),
            array('label'=>'Название'),
            array('label'=>'Тип объекта','priority'=>3),
            array('label'=>'Вид','priority'=>3),
            array('label'=>'Версия','align'=>'right','priority'=>2),
            array('label'=>'Выпущено','align'=>'right','priority'=>2),
            array('label'=>'Действия','align'=>'right','nowrap'=>TRUE),
        ),
        'rows'=>$table_rows,
        'row_header'=>TRUE,
        'empty_title'=>'Шаблонов пока нет',
        'empty_hint'=>'Создайте первый шаблон печатной формы.',
    ));
    ?>

</div>
