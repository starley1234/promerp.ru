<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2 text-green-600"></i> Контроли качества
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Найдено: <?php echo (int) $total; ?></p>
        </div>
        <?php if ($can_inspect): ?>
            <a href="/quality/inspections/edit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-plus-circle mr-1"></i> Новый контроль
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/quality/inspections" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Вид контроля</label>
            <select name="kind" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach (Model_Erp405_Inspection::$kinds as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['kind'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Состояние</label>
            <select name="status" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach (Model_Erp405_Inspection::$statuses as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['status'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Итог</label>
            <select name="result" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                <option value="">все</option>
                <?php foreach (Model_Erp405_Inspection::$results as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($filters['result'] === $key) ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>"
                   class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100" placeholder="номер или позиция">
        </div>
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="pending" value="1" <?php echo $filters['pending'] ? 'checked' : ''; ?>> ждут решения
            </label>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">Найти</button>
        </div>
    </form>

    <?php
    $inspection_rows = array();
    foreach ($rows as $inspection)
    {
        $number = '<a href="/quality/inspections/view/'.(int)$inspection->id.'" class="font-medium text-blue-600 hover:text-blue-800">'.HTML::chars($inspection->number).'</a><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(substr((string)$inspection->created_at,0,16)).'</span>';
        $defects = '<span class="'.((float)$inspection->defect_qty>0?'font-medium text-red-600':'text-gray-400').'">'.number_format((float)$inspection->defect_qty,3,'.',' ').'</span>';
        $status = '<span class="rounded px-2 py-0.5 text-xs '.$inspection->status_class().'">'.HTML::chars($inspection->status_label()).'</span>';
        if ($inspection->decision) $decision = '<span class="rounded px-2 py-0.5 text-xs '.$inspection->decision_class().'">'.HTML::chars($inspection->decision_label()).'</span>';
        elseif ($inspection->is_pending_decision()) $decision='<span class="text-xs text-amber-600">нужно решение</span>';
        else $decision='<span class="text-xs text-gray-400">—</span>';
        $inspection_rows[] = array($number,HTML::chars($inspection->item_name),HTML::chars($inspection->kind_label()),number_format((float)$inspection->qty,3,'.',' '),$defects,$status,$decision);
    }
    echo Component::render('table',array(
        'caption'=>'Список контролей качества','columns'=>array(array('label'=>'Номер','nowrap'=>TRUE),array('label'=>'Позиция'),array('label'=>'Вид','priority'=>3),array('label'=>'Партия','align'=>'right','priority'=>2),array('label'=>'Брак','align'=>'right'),array('label'=>'Состояние'),array('label'=>'Решение')),
        'rows'=>$inspection_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Контролей качества нет','empty_hint'=>'Входной контроль создаётся из приёмки закупки; остальные можно создать вручную.',
    ));
    if ((int)$total>(int)$limit)
    {
        $page_url = function ($target_page) use ($filters) {
            return '/quality/inspections?'.http_build_query(array_filter(array(
                'page'=>$target_page,'kind'=>$filters['kind'],'status'=>$filters['status'],
                'result'=>$filters['result'],'term'=>$filters['term'],'pending'=>$filters['pending']?1:NULL,
            ), function ($value) { return $value !== NULL AND $value !== ''; }));
        };
        echo Component::render('pagination',array('page'=>(int)$page,'pages'=>(int)ceil($total/$limit),'url'=>$page_url,'shown'=>2,'total'=>(int)$total));
    }
    ?>
</div>
