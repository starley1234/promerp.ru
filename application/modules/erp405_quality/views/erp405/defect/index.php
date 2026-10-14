<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-tags mr-2 text-green-600"></i> Коды дефектов
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Один словарь на весь завод: от того, назван ли дефект одним кодом, зависит вся статистика качества.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/quality/defects/edit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-plus-circle mr-1"></i> Новый код
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/quality/defects" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Категория</label>
            <select name="category" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['category'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="all" value="1" <?php echo $filters['active_only'] ? '' : 'checked'; ?>> показывать выключенные
            </label>
        </div>
        <div class="flex items-end">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">Найти</button>
        </div>
    </form>

    <?php
    $defect_rows = array();
    foreach ($rows as $row)
    {
        $code = $row['code'];
        $severity = '<span class="rounded px-2 py-0.5 text-xs '.(isset(Model_Erp405_DefectCode::$severity_classes[(int)$code->severity])?Model_Erp405_DefectCode::$severity_classes[(int)$code->severity]:'').'">'.HTML::chars($code->severity_label()).'</span>';
        $action = $can_manage?'<a href="/quality/defects/edit/'.(int)$code->id.'" class="text-xs text-blue-600 hover:text-blue-800">править</a>':'';
        $defect_rows[] = array('class'=>$code->is_active()?'':'opacity-50','cells'=>array('<span class="font-mono">'.HTML::chars($code->code).'</span>',HTML::chars($code->name),HTML::chars($code->category_label()),$severity,(int)$row['usage'],$action));
    }
    echo Component::render('table',array(
        'caption'=>'Единый справочник кодов дефектов','columns'=>array(array('label'=>'Код','nowrap'=>TRUE),array('label'=>'Название'),array('label'=>'Категория','priority'=>3),array('label'=>'Важность'),array('label'=>'Встречался','align'=>'right','priority'=>2),array('label'=>'Действия','align'=>'right')),
        'rows'=>$defect_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Коды дефектов не найдены','empty_hint'=>'Измените фильтр или добавьте новый код дефекта.',
    ));
    ?>
</div>
