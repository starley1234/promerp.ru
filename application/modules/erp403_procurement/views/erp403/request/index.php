<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-alt mr-2 text-blue-600"></i> Заявки на закупку
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · к решению <?php echo (int) $stats['submitted']; ?> ·
                согласованы и не заказаны <?php echo (int) $stats['approved']; ?>
                <?php if ((int) $stats['overdue']): ?>
                    · <span class="text-red-600 font-medium">просрочено <?php echo (int) $stats['overdue']; ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/procurement/requests/edit"
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i> Новая заявка
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/procurement/requests" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="номер, позиция, пояснение"
                   class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Состояние</label>
            <select name="status" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любое</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo ($filters['status'] === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Источник</label>
            <select name="source" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любой</option>
                <?php foreach ($sources as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo ($filters['source'] === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 pb-2">
            <input type="checkbox" name="overdue" value="1" <?php echo $filters['overdue'] ? 'checked' : ''; ?>>
            только просроченные
        </label>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Найти
        </button>
    </form>

    <?php
    $table_rows=array();foreach($rows as $request){$name='<a href="/procurement/requests/view/'.(int)$request->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($request->number).'</a>'.($request->note?'<br><span class="text-xs text-gray-500">'.HTML::chars(mb_substr((string)$request->note,0,80)).'</span>':'');$need=($request->need_date?HTML::chars(date('d.m.Y',strtotime($request->need_date))):'—').($request->is_overdue()?'<br><span class="text-xs font-medium text-red-600">срок прошёл</span>':'<br><span class="text-xs text-gray-500">через '.(int)$request->days_left().' дн.</span>');$source=HTML::chars($request->source_label()).($request->source_ref?'<br><span class="text-xs text-gray-500">'.HTML::chars($request->source_ref).'</span>':'');$table_rows[]=array($name,$need,HTML::chars($request->priority_label()),$source,'<span class="rounded px-2 py-1 text-xs '.$request->status_class().'">'.HTML::chars($request->status_label()).'</span>','<a href="/procurement/requests/view/'.(int)$request->id.'" class="text-blue-600 hover:text-blue-800" aria-label="Открыть заявку"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>');}
    echo Component::render('table',array('caption'=>'Заявки на закупку','columns'=>array(array('label'=>'Заявка'),array('label'=>'Нужен к','nowrap'=>TRUE),array('label'=>'Приоритет','priority'=>3),array('label'=>'Источник','priority'=>2),array('label'=>'Состояние'),array('label'=>'Действия','align'=>'right')),'rows'=>$table_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Заявок не найдено','empty_hint'=>'Создайте заявку вручную или по минимальному остатку WMS.'));
    if($total>$limit){$request_url=function($target_page)use($filters){return '/procurement/requests?'.http_build_query(array_filter(array('page'=>$target_page,'search'=>$filters['search'],'status'=>$filters['status'],'source'=>$filters['source'],'overdue'=>$filters['overdue']?1:NULL),function($v){return $v!==NULL AND $v!=='';}));};echo Component::render('pagination',array('page'=>(int)$page,'pages'=>(int)ceil($total/$limit),'url'=>$request_url,'total'=>(int)$total));}
    ?>
</div>
