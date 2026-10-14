<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-tasks mr-2 text-blue-600"></i> Предложения плана
        </h1>
        <div class="flex gap-2">
            <?php if ($can_plan): ?>
                <a href="/planning/proposals/edit"
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i> Предложение вручную
                </a>
            <?php endif; ?>
            <a href="/planning/deficits"
               class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-exclamation-triangle mr-2"></i> Дефициты
            </a>
        </div>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5">
        <?php
        echo Component::render('kpi',array('label'=>'Ждут решения','value'=>(int)$stats['new'],'unit'=>'предл.','state'=>$stats['new']>0?'warn':'neutral'));
        echo Component::render('kpi',array('label'=>'Утверждено','value'=>(int)$stats['approved'],'unit'=>'предл.','state'=>'ok'));
        echo Component::render('kpi',array('label'=>'Передано','value'=>(int)$stats['exported'],'unit'=>'предл.','state'=>'info'));
        echo Component::render('kpi',array('label'=>'Закупка / выпуск','value'=>(int)$stats['purchase'].' / '.(int)$stats['produce'],'unit'=>'откр.','state'=>'neutral'));
        echo Component::render('kpi',array('label'=>'Просрочено к заказу','value'=>(int)$stats['late'],'unit'=>'предл.','state'=>$stats['late']>0?'bad':'ok'));
        ?>
    </div>

    <form method="get" action="/planning/proposals" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="позиция"
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
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Способ</label>
            <select name="kind" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любой</option>
                <?php foreach ($kinds as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo ($filters['kind'] === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-filter mr-1"></i> Показать
        </button>
        <?php if ($filters['search'] !== NULL OR $filters['status'] !== NULL OR $filters['kind'] !== NULL): ?>
            <a href="/planning/proposals" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Сбросить</a>
        <?php endif; ?>
    </form>

    <?php
    $proposal_rows=array();
    foreach($rows as $proposal)
    {
        $late=$proposal->days_to_order()!==NULL AND $proposal->days_to_order()<0 AND $proposal->status==='new';
        $position='<span class="text-gray-900 dark:text-white">'.HTML::chars($proposal->name).'</span>'.($proposal->program_id?'<br><a href="/planning/programs/view/'.(int)$proposal->program_id.'" class="text-xs text-blue-600 hover:text-blue-800">программа #'.(int)$proposal->program_id.'</a>':'');
        $order_date='<span class="'.($late?'font-medium text-red-600 dark:text-red-400':'text-gray-600 dark:text-gray-300').'">'.($proposal->order_date?HTML::chars(date('d.m.Y',strtotime($proposal->order_date))):'—').'</span>';
        if($proposal->days_to_order()!==NULL AND $proposal->status==='new')$order_date.='<br><span class="text-xs text-gray-500 dark:text-gray-400">'.($proposal->days_to_order()>=0?'через '.(int)$proposal->days_to_order().' дн.':'просрочено на '.abs((int)$proposal->days_to_order()).' дн.').'</span>';
        $status='<span class="rounded px-2 py-1 text-xs '.$proposal->status_class().'">'.HTML::chars($proposal->status_label()).'</span>';
        $actions='';
        if($can_plan AND $proposal->status==='new')$actions='<form method="post" action="/planning/proposals/act/approve/'.(int)$proposal->id.'" class="inline"><button type="submit" class="mr-2 text-green-600 hover:text-green-800" title="Утвердить" aria-label="Утвердить предложение"><i class="fas fa-check" aria-hidden="true"></i></button></form><form method="post" action="/planning/proposals/act/cancel/'.(int)$proposal->id.'" class="inline" data-confirm="Отклонить предложение?"><button type="submit" class="text-red-600 hover:text-red-800" title="Отклонить" aria-label="Отклонить предложение"><i class="fas fa-times" aria-hidden="true"></i></button></form>';
        elseif($can_approve AND $proposal->status==='approved')$actions='<form method="post" action="/planning/proposals/act/export/'.(int)$proposal->id.'" class="inline"><button type="submit" class="mr-2 text-blue-600 hover:text-blue-800"><i class="fas fa-share" aria-hidden="true"></i> Передать</button></form><form method="post" action="/planning/proposals/act/cancel/'.(int)$proposal->id.'" class="inline" data-confirm="Отклонить предложение?"><button type="submit" class="text-red-600 hover:text-red-800" aria-label="Отклонить предложение"><i class="fas fa-times" aria-hidden="true"></i></button></form>';
        elseif($can_plan AND $proposal->status==='exported')$actions='<form method="post" action="/planning/proposals/act/complete/'.(int)$proposal->id.'"><button type="submit" class="text-gray-600 hover:text-gray-900 dark:text-gray-300"><i class="fas fa-flag-checkered" aria-hidden="true"></i> Исполнено</button></form>';
        else $actions='<span class="text-xs text-gray-400">—</span>';
        $proposal_rows[]=array('class'=>$late?'bg-red-50 dark:bg-red-900/10':'','cells'=>array('<span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($proposal->number).'</span>',$position,HTML::chars($proposal->kind_label()),number_format((float)$proposal->qty,3,'.',' ').' <span class="text-xs text-gray-500">'.HTML::chars($proposal->uom_code).'</span>',$order_date,HTML::chars($proposal->priority_label()),$status,$proposal->target_ref?HTML::chars($proposal->target_ref):'—',$actions));
    }
    echo Component::render('table',array('caption'=>'Предложения плана','columns'=>array(array('label'=>'Номер','nowrap'=>TRUE),array('label'=>'Позиция'),array('label'=>'Способ','priority'=>3),array('label'=>'Количество','align'=>'right','nowrap'=>TRUE),array('label'=>'Заказ к','priority'=>2,'nowrap'=>TRUE),array('label'=>'Приоритет','priority'=>4),array('label'=>'Состояние'),array('label'=>'Передано','priority'=>4),array('label'=>'Действия','align'=>'right','nowrap'=>TRUE)),'rows'=>$proposal_rows,'sticky'=>TRUE,'empty_title'=>'Предложений нет','empty_hint'=>'Рассчитайте потребность программы и создайте предложения по подтверждённым дефицитам.'));
    ?>

    <?php if ($total > $limit): ?>
        <?php
        $proposal_url=function($target_page)use($filters){return '/planning/proposals?'.http_build_query(array_filter(array('page'=>$target_page,'search'=>$filters['search'],'status'=>$filters['status'],'kind'=>$filters['kind']),function($v){return $v!==NULL AND $v!=='';}));};
        echo Component::render('pagination',array('page'=>(int)$page,'pages'=>(int)ceil($total/$limit),'url'=>$proposal_url,'total'=>(int)$total));
        ?>
    <?php endif; ?>
</div>
