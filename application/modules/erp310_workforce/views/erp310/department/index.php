<?php defined('SYSPATH') or die('No direct script access.');
$by_id=array(); foreach($departments as $d)$by_id[$d->id]=$d;
?>
<div class="space-y-6">
    <div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">Организационная структура</h1><p class="text-sm text-gray-500 dark:text-gray-400">Организации, филиалы, цеха, участки и бригады</p></div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2"><?php
        $rows=array(); foreach($departments as $d)$rows[]=array('cells'=>array('<span class="font-semibold text-gray-900 dark:text-white">'.HTML::chars($d->name).'</span><div class="text-xs text-gray-500">'.HTML::chars($d->code).'</div>',HTML::chars($kinds[$d->kind]??$d->kind),$d->parent_id&&isset($by_id[$d->parent_id])?HTML::chars($by_id[$d->parent_id]->name):'—',Component::render('badge',array('label'=>$d->status==='active'?'Активно':'Архив','state'=>$d->status==='active'?'ok':'neutral'))));
        Component::begin('card',array('title'=>'Подразделения','flush'=>TRUE)); echo Component::render('table',array('caption'=>'Организационная структура','columns'=>array(array('label'=>'Подразделение'),array('label'=>'Тип'),array('label'=>'Родитель'),array('label'=>'Статус')),'rows'=>$rows,'empty_title'=>'Структура не создана','empty_hint'=>'Создайте корневую организацию.')); Component::end();
        ?></div>
        <?php Component::begin('card',array('title'=>'Новое подразделение')); ?><form method="post" action="/workforce/departments/save" class="space-y-4">
            <div><label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Код *</label><input required id="code" name="code" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="SHOP-01"></div>
            <div><label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Название *</label><input required id="name" name="name" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="kind" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Тип</label><select id="kind" name="kind" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><?php foreach($kinds as $code=>$label): ?><option value="<?php echo $code; ?>"><?php echo HTML::chars($label); ?></option><?php endforeach; ?></select></div>
            <div><label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">В составе</label><select id="parent_id" name="parent_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Корневое</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d->id; ?>"><?php echo HTML::chars($d->name); ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Создать</button>
        </form><?php Component::end(); ?>
    </div>
</div>
