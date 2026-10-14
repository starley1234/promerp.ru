<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="space-y-5">
    <div><p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Workforce · штат</p><h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">Штатное расписание и вакансии</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">План ставок сравнивается с действующими назначениями; отрицательный остаток означает превышение штата.</p></div>
    <?php foreach($messages as $message): ?><?php echo Component::render('toast', array('message'=>$message['message'],'type'=>$message['type'])); ?><?php endforeach; ?>

    <?php if ($can_manage): ?>
    <?php Component::begin('card', array('title'=>'Добавить строку штатного расписания','subtitle'=>'В одном подразделении может быть одна действующая строка на должность.')); ?>
    <form method="post" action="/workforce/staffing/save" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div><label for="staff_department" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Подразделение *</label><select required id="staff_department" name="department_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Выберите</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d->id; ?>"><?php echo HTML::chars($d->name); ?></option><?php endforeach; ?></select></div>
        <div><label for="staff_position" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Должность *</label><select required id="staff_position" name="position_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Выберите</option><?php foreach($positions as $p): ?><option value="<?php echo (int)$p->id; ?>"><?php echo HTML::chars($p->name); ?></option><?php endforeach; ?></select></div>
        <div><label for="planned_fte" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Ставок *</label><input required type="number" min="0.01" max="999" step="0.01" id="planned_fte" name="planned_fte" value="1" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
        <div><label for="valid_from" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Действует с *</label><input required type="date" id="valid_from" name="valid_from" value="<?php echo date('Y-m-d'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Добавить</button>
    </form>
    <?php Component::end(); ?>
    <?php endif; ?>

    <?php
    $table_rows=array();
    foreach($rows as $row) {
        $vacant=(float)$row['vacant_fte']; $state=$row['status']==='archived'?'neutral':($vacant<0?'bad':($vacant>0?'warn':'ok'));
        $actions='';
        if($can_manage && $row['status']==='active') $actions='<form method="post" action="/workforce/staffing/archive/'.(int)$row['id'].'"><button type="submit" data-confirm="Закрыть строку штатного расписания?" class="text-sm text-rose-600 hover:underline">Закрыть</button></form>';
        $table_rows[]=array('cells'=>array(
            '<span class="font-semibold text-gray-900 dark:text-white">'.HTML::chars($row['position_name']).'</span><div class="text-xs text-gray-500">'.HTML::chars($row['position_code']).'</div>',
            HTML::chars($row['department_name']),
            number_format((float)$row['planned_fte'],2,',',' '), number_format((float)$row['occupied_fte'],2,',',' '),
            Component::render('badge',array('label'=>number_format($vacant,2,',',' ').' ставки','state'=>$state)),
            HTML::chars($row['valid_from']).($row['valid_to']?' — '.HTML::chars($row['valid_to']):''), $actions
        ));
    }
    Component::begin('card',array('title'=>'План и фактическая занятость','flush'=>TRUE));
    echo Component::render('table',array('caption'=>'Штатное расписание','columns'=>array(
        array('label'=>'Должность'),array('label'=>'Подразделение'),array('label'=>'План'),array('label'=>'Занято'),array('label'=>'Вакантно'),array('label'=>'Период'),array('label'=>'Действия')
    ),'rows'=>$table_rows,'empty_title'=>'Штатное расписание ещё не задано','empty_hint'=>'Добавьте первую плановую строку — вакансии рассчитаются автоматически.'));
    Component::end();
    ?>
</div>
