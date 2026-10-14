<?php defined('SYSPATH') or die('No direct script access.');
$fields = '<div><label for="term" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Поиск</label><input id="term" name="term" value="'.HTML::chars(Arr::get($filters, 'term')).'" placeholder="ФИО, табельный номер, e-mail" class="w-72 max-w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"></div>';
$fields .= '<div><label for="department_id" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Подразделение</label><select id="department_id" name="department_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"><option value="">Все</option>';
foreach ($departments as $d) $fields .= '<option value="'.(int)$d->id.'"'.((int)Arr::get($filters,'department_id')===(int)$d->id?' selected':'').'>'.HTML::chars($d->name).'</option>';
$fields .= '</select></div><div><label for="status" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Статус</label><select id="status" name="status" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"><option value="">Все</option>';
foreach ($statuses as $code=>$label) $fields .= '<option value="'.HTML::chars($code).'"'.(Arr::get($filters,'status')===$code?' selected':'').'>'.HTML::chars($label).'</option>';
$fields .= '</select></div>';
$table_rows = array();
foreach ($rows as $row) {
    $state = $row['status']==='active'?'ok':($row['status']==='leave'?'warn':'neutral');
    $table_rows[] = array('cells'=>array(
        '<a class="font-semibold text-blue-700 dark:text-blue-400 hover:underline" href="/workforce/employees/view/'.(int)$row['id'].'">'.HTML::chars(trim($row['last_name'].' '.$row['first_name'].' '.$row['middle_name'])).'</a><div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['personnel_no']).'</div>',
        HTML::chars($row['department_name'] ?: 'Не назначено'), HTML::chars($row['position_name'] ?: '—'),
        Component::render('badge', array('label'=>$statuses[$row['status']] ?? $row['status'], 'state'=>$state)),
        $row['username'] ? HTML::chars($row['username']) : '<span class="text-xs text-amber-700 dark:text-amber-400">не связана</span>',
    ));
}
$url=function($p) use($filters){return '?page='.$p.'&term='.urlencode(Arr::get($filters,'term')).'&status='.urlencode(Arr::get($filters,'status')).'&department_id='.(int)Arr::get($filters,'department_id');};
?>
<div class="space-y-5">
    <div class="flex items-center justify-between gap-4"><div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">Сотрудники</h1><p class="text-sm text-gray-500 dark:text-gray-400">Кадровый справочник и текущие назначения</p></div><a href="/workforce/employees/edit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Добавить</a></div>
    <?php echo Component::render('filters', array('action'=>'/workforce/employees','body'=>$fields,'reset_url'=>'/workforce/employees')); ?>
    <?php Component::begin('card', array('title'=>'Найдено: '.number_format($total,0,',',' '),'flush'=>TRUE)); echo Component::render('table', array('caption'=>'Сотрудники предприятия','columns'=>array(array('label'=>'Сотрудник'),array('label'=>'Подразделение'),array('label'=>'Должность'),array('label'=>'Статус'),array('label'=>'Учётная запись')),'rows'=>$table_rows,'empty_title'=>'Сотрудники не найдены','empty_hint'=>'Измените фильтры или создайте карточку сотрудника.')); Component::end(); ?>
    <?php echo Component::render('pagination', array('page'=>$page,'pages'=>$limit?(int)ceil($total/$limit):0,'total'=>$total,'url'=>$url)); ?>
</div>
