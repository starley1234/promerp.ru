<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-clipboard-check mr-2 text-blue-600"></i> Производственные программы
        </h1>
        <a href="/planning/programs/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Новая программа
        </a>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/planning/programs" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="номер или название"
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
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Период с</label>
            <input type="date" name="period_from" value="<?php echo HTML::chars((string) $filters['period_from']); ?>"
                   class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">по</label>
            <input type="date" name="period_to" value="<?php echo HTML::chars((string) $filters['period_to']); ?>"
                   class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Найти
        </button>
        <?php if ($filters['search'] !== NULL OR $filters['status'] !== NULL): ?>
            <a href="/planning/programs" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Сбросить</a>
        <?php endif; ?>
    </form>

    <?php
    $program_rows=array();
    foreach($rows as $program)
    {
        $name='<a href="/planning/programs/view/'.(int)$program->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($program->number).'</a><br><span class="text-gray-900 dark:text-white">'.HTML::chars($program->title).'</span>';
        $period=HTML::chars(date('d.m.Y',strtotime($program->period_from))).' — '.HTML::chars(date('d.m.Y',strtotime($program->period_to))).'<br><span class="text-xs text-gray-500 dark:text-gray-400">горизонт '.(int)$program->horizon_days.' дн.'.($program->days_left()!==NULL?' · осталось '.(int)$program->days_left().' дн.':'').'</span>';
        $status='<span class="rounded px-2 py-1 text-xs '.$program->status_class().'">'.HTML::chars($program->status_label()).'</span>';
        $action='<a href="/planning/programs/view/'.(int)$program->id.'" class="text-blue-600 hover:text-blue-800" aria-label="Открыть программу"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>';
        $program_rows[]=array($name,$period,$program->source==='orders'?'Заказы':'Вручную',$status,$program->run_at?HTML::chars(date('d.m.Y H:i',strtotime($program->run_at))):'—',$action);
    }
    echo Component::render('table',array('caption'=>'Производственные программы','columns'=>array(array('label'=>'Программа'),array('label'=>'Период','nowrap'=>TRUE),array('label'=>'Источник','priority'=>3),array('label'=>'Состояние'),array('label'=>'Последний расчёт','priority'=>2,'nowrap'=>TRUE),array('label'=>'Действия','align'=>'right')),'rows'=>$program_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Программ не найдено','empty_hint'=>'Создайте программу: период, номенклатура и количество станут источником спроса.'));
    ?>

    <?php if ($total > $limit): ?>
        <?php
        $program_url=function($target_page) use($filters){return '/planning/programs?'.http_build_query(array_filter(array('page'=>$target_page,'search'=>$filters['search'],'status'=>$filters['status'],'period_from'=>$filters['period_from'],'period_to'=>$filters['period_to']),function($v){return $v!==NULL AND $v!=='';}));};
        echo Component::render('pagination',array('page'=>(int)$page,'pages'=>(int)ceil($total/$limit),'url'=>$program_url,'total'=>(int)$total));
        ?>
    <?php endif; ?>
</div>
