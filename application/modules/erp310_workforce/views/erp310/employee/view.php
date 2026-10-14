<?php defined('SYSPATH') or die('No direct script access.');
$name=trim($employee['last_name'].' '.$employee['first_name'].' '.$employee['middle_name']);
$history_rows=array();
foreach($history as $row)$history_rows[]=array('cells'=>array(
    HTML::chars($row['department_name']), HTML::chars($row['position_name']?:'—'), HTML::chars($row['manager_name']?:'—'),
    HTML::chars(date('d.m.Y',strtotime($row['valid_from']))),
    $row['valid_to']?HTML::chars(date('d.m.Y',strtotime($row['valid_to']))):Component::render('badge',array('label'=>'текущее','state'=>'ok')),
    number_format((float)$row['rate'],2,',',' '),
));
?>
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div><a href="/workforce/employees" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Сотрудники</a><h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo HTML::chars($name); ?></h1><p class="text-sm text-gray-500 dark:text-gray-400">Табельный № <?php echo HTML::chars($employee['personnel_no']); ?></p></div>
        <div class="flex gap-2"><a href="/workforce/employees/edit/<?php echo (int)$employee['id']; ?>" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Редактировать</a><?php echo Component::render('badge',array('label'=>$statuses[$employee['status']]??$employee['status'],'state'=>$employee['status']==='active'?'ok':($employee['status']==='leave'?'warn':'neutral'))); ?></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <?php Component::begin('card',array('title'=>'Текущее назначение')); ?><dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm"><div><dt class="text-gray-500 dark:text-gray-400">Подразделение</dt><dd class="font-semibold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($employee['department_name']?:'Не назначено'); ?></dd></div><div><dt class="text-gray-500 dark:text-gray-400">Должность</dt><dd class="font-semibold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($employee['position_name']?:'Не указана'); ?></dd></div><div><dt class="text-gray-500 dark:text-gray-400">Учётная запись</dt><dd class="font-semibold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($employee['username']?:'Не связана'); ?></dd></div><div><dt class="text-gray-500 dark:text-gray-400">Дата приёма</dt><dd class="font-semibold text-gray-900 dark:text-white mt-1"><?php echo $employee['hired_at']?date('d.m.Y',strtotime($employee['hired_at'])):'—'; ?></dd></div></dl><?php Component::end(); ?>
            <?php Component::begin('card',array('title'=>'История назначений','flush'=>TRUE)); echo Component::render('table',array('caption'=>'История назначений сотрудника','columns'=>array(array('label'=>'Подразделение'),array('label'=>'Должность'),array('label'=>'Руководитель'),array('label'=>'С'),array('label'=>'По'),array('label'=>'Ставка','align'=>'right')),'rows'=>$history_rows,'empty_title'=>'Назначений нет','empty_hint'=>'Укажите подразделение в карточке сотрудника.')); Component::end(); ?>
        </div>
        <div class="space-y-6">
            <?php Component::begin('card',array('title'=>'Контакты')); ?><dl class="space-y-3 text-sm"><div><dt class="text-gray-500 dark:text-gray-400">E-mail</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($employee['email']?:'—'); ?></dd></div><div><dt class="text-gray-500 dark:text-gray-400">Телефон</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($employee['phone']?:'—'); ?></dd></div></dl><?php Component::end(); ?>
            <?php if($employee['status']!=='dismissed'): ?><?php Component::begin('card',array('title'=>'Увольнение','subtitle'=>'Назначение будет закрыто указанной датой. Учётная запись сохраняется для аудита.')); ?><form method="post" action="/workforce/employees/dismiss/<?php echo (int)$employee['id']; ?>" class="space-y-3"><label for="dismissed_at" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Последний рабочий день</label><input required type="date" id="dismissed_at" name="dismissed_at" value="<?php echo date('Y-m-d'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><button type="submit" class="w-full rounded-lg border border-rose-300 dark:border-rose-800 px-4 py-2 text-sm font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/30">Оформить увольнение</button></form><?php Component::end(); ?><?php endif; ?>
        </div>
    </div>
</div>
