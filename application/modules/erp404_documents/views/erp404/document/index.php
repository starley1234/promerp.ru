<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-alt mr-2 text-blue-600"></i> Документы и печать
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Что отдали на бумагу и чем это подтверждается: номер, версия шаблона, снимок выпуска и журнал печати.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/documents/templates" class="px-4 py-2 bg-white border text-gray-700 text-sm rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                <i class="fas fa-file-code mr-1"></i> Шаблоны форм
            </a>
            <?php if ($can_print): ?>
                <a href="/documents/edit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    <i class="fas fa-plus-circle mr-1"></i> Новый документ
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp404/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <?php
        echo Component::render('kpi',array('label'=>'Всего','value'=>(int)$stats['total'],'unit'=>'док.','compact'=>TRUE));
        echo Component::render('kpi',array('label'=>'Черновики','value'=>(int)$stats['drafts'],'unit'=>'док.','compact'=>TRUE));
        echo Component::render('kpi',array('label'=>'Выпущено','value'=>(int)$stats['issued'],'unit'=>'док.','compact'=>TRUE));
        echo Component::render('kpi',array('label'=>'В архиве','value'=>(int)$stats['archived'],'unit'=>'док.','compact'=>TRUE));
        echo Component::render('kpi',array('label'=>'Отменено','value'=>(int)$stats['cancelled'],'unit'=>'док.','compact'=>TRUE));
        echo Component::render('kpi',array('label'=>'Печатали','value'=>(int)$stats['print_runs'],'unit'=>'раз','compact'=>TRUE));
        ?>
    </div>

    <?php if ( ! empty($by_type)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Что печатаем</div>
            <div class="flex flex-wrap gap-2 text-xs">
                <?php foreach ($by_type as $row): ?>
                    <a href="/documents?ref_type=<?php echo urlencode($row['ref_type']); ?>"
                       class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200">
                        <?php echo HTML::chars($row['label']); ?>: <?php echo (int) $row['docs']; ?>
                        <span class="text-gray-400">· печатей <?php echo (int) $row['print_runs']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="get" action="/documents" autocomplete="off" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Состояние</label>
            <select name="status" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['status'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
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
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Шаблон</label>
            <input type="text" name="template_code" value="<?php echo HTML::chars((string) $filters['template_code']); ?>"
                   placeholder="ORDER-SUPPLIER"
                   class="w-full border rounded-md px-3 py-2 font-mono dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">Найти</button>
            <a href="/documents" class="text-xs text-gray-500 hover:text-gray-700">сбросить</a>
        </div>
    </form>

    <?php
    $table_rows = array();
    foreach ($rows as $document)
    {
        $number = '<a href="/documents/view/'.(int) $document->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($document->number).'</a>';
        $template = '<span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $document->template_code);
        if ($document->template_version) $template .= ' <span class="text-gray-400">v'.(int) $document->template_version.'</span>';
        $template .= '</span>';
        $object = HTML::chars(Model_Erp404_Template::ref_type_label($document->ref_type));
        if ($document->ref_id !== NULL) $object .= ' <span class="font-mono">#'.HTML::chars((string) $document->ref_id).'</span>';
        $status = '<span class="rounded px-2 py-0.5 text-xs '.$document->status_class().'">'.HTML::chars($document->status_label()).'</span>';
        $table_rows[] = array($number, HTML::chars($document->title), $template, $object, $status, (int) $document->print_count, HTML::chars((string) $document->created_at));
    }
    echo Component::render('table', array(
        'caption'=>'Документы и журнал печати',
        'aria_label'=>'Документы и журнал печати',
        'columns'=>array(
            array('label'=>'Номер','nowrap'=>TRUE),
            array('label'=>'Название'),
            array('label'=>'Шаблон','priority'=>3),
            array('label'=>'Объект','priority'=>3),
            array('label'=>'Состояние'),
            array('label'=>'Печатей','align'=>'right','priority'=>2),
            array('label'=>'Создан','priority'=>4,'nowrap'=>TRUE),
        ),
        'rows'=>$table_rows,
        'row_header'=>TRUE,
        'sticky'=>TRUE,
        'empty_title'=>'Документов пока нет',
        'empty_hint'=>'Начните с шаблона печатной формы и нового черновика.',
    ));
    ?>

    <?php
    $document_url = function ($target_page) use ($filters) {
        return '/documents?'.http_build_query(array_filter(array(
            'page'=>$target_page, 'term'=>$filters['term'], 'status'=>$filters['status'],
            'ref_type'=>$filters['ref_type'], 'template_code'=>$filters['template_code'],
        ), function ($value) { return $value !== NULL AND $value !== ''; }));
    };
    echo Component::render('pagination',array(
        'page'=>(int)$page, 'pages'=>$limit > 0 ? (int)ceil($total/$limit) : 0,
        'total'=>(int)$total, 'url'=>$document_url,
    ));
    ?>
</div>
