<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="space-y-5">
<div><p class="text-xs font-bold uppercase tracking-wider text-blue-600">ERP100 · безопасность</p><h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Учётные записи и доступ</h1><p class="mt-1 text-sm text-gray-500">Роли определяют действия, область — доступные подразделения, склады и рабочие центры.</p></div>
<?php $table=array(); foreach($rows as $row) $table[]=array('cells'=>array(
    '<a class="font-semibold text-blue-600 hover:underline" href="/access/users/view/'.(int)$row['id'].'">'.HTML::chars($row['username']).'</a><div class="text-xs text-gray-500">'.HTML::chars($row['email']).'</div>',
    HTML::chars($row['roles']?:'—'), Component::render('badge',array('label'=>$row['status']==='blocked'?'заблокирован':'активен','state'=>$row['status']==='blocked'?'bad':'ok')),
    HTML::chars($row['access_policy']==='restricted'?'ограниченная':'без ограничений'), $row['last_login']?date('d.m.Y H:i',(int)$row['last_login']):'—'
)); echo Component::render('table',array('caption'=>'Учётные записи','columns'=>array(array('label'=>'Пользователь'),array('label'=>'Роли'),array('label'=>'Статус'),array('label'=>'Область'),array('label'=>'Последний вход')),'rows'=>$table,'empty_title'=>'Учётных записей нет')); ?>
</div>
