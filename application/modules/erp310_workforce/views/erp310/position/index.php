<?php defined('SYSPATH') or die('No direct script access.');
$rows=array();
foreach($positions as $p){
    $action=$p['status']==='active'
        ? '<form method="post" action="/workforce/positions/archive/'.(int)$p['id'].'"><button class="text-xs text-rose-600 dark:text-rose-400 hover:underline" type="submit">В архив</button></form>'
        : '<span class="text-xs text-gray-400">архив</span>';
    $rows[]=array('cells'=>array(
        '<span class="font-semibold text-gray-900 dark:text-white">'.HTML::chars($p['name']).'</span><div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($p['code']).'</div>',
        HTML::chars($categories[$p['category']]??$p['category']), HTML::chars($p['department_name']?:'Общая'),
        '<span class="tabular-nums">'.(int)$p['current_employees'].'</span>', $action,
    ));
}
?>
<div class="space-y-6">
    <div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">Должности</h1><p class="text-sm text-gray-500 dark:text-gray-400">Справочник должностей и действующие назначения</p></div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2"><?php Component::begin('card',array('title'=>'Штатные должности','flush'=>TRUE)); echo Component::render('table',array('caption'=>'Должности предприятия','columns'=>array(array('label'=>'Должность'),array('label'=>'Категория'),array('label'=>'Подразделение'),array('label'=>'Сотрудников','align'=>'right'),array('label'=>'Действие','align'=>'right')),'rows'=>$rows,'empty_title'=>'Должностей пока нет','empty_hint'=>'Создайте первую должность справа.')); Component::end(); ?></div>
        <?php Component::begin('card',array('title'=>'Новая должность')); ?>
        <form method="post" action="/workforce/positions/save" class="space-y-4">
            <div><label for="position_code" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Код *</label><input required id="position_code" name="code" placeholder="ENGINEER" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="position_name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Название *</label><input required id="position_name" name="name" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="position_category" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Категория</label><select id="position_category" name="category" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><?php foreach($categories as $code=>$label): ?><option value="<?php echo $code; ?>"><?php echo HTML::chars($label); ?></option><?php endforeach; ?></select></div>
            <div><label for="position_department" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Подразделение</label><select id="position_department" name="department_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Общая должность</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d->id; ?>"><?php echo HTML::chars($d->name); ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Создать</button>
        </form>
        <?php Component::end(); ?>
    </div>
</div>
