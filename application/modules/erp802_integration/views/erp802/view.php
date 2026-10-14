<?php defined('SYSPATH') or die('No direct script access.');
$table_rows = array();
foreach ($rows as $row)
{
    $result = is_array($row['result_data']) ? $row['result_data'] : json_decode((string)$row['result_data'], TRUE);
    $state  = $row['status']==='applied' ? 'ok' : ($row['status']==='error' ? 'bad' : 'neutral');
    $table_rows[] = array(
        HTML::chars($row['row_no']), HTML::chars($row['external_id']),
        Component::render('badge', array('label'=>$row['status'],'state'=>$state)),
        HTML::chars($row['reason_code']).'<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['message']).'</div>',
        HTML::chars(Arr::get((array)$result,'message','—')), HTML::chars($row['internal_uuid']),
    );
}
$package_state = $package['status']==='done' ? 'ok' : (in_array($package['status'],array('failed','partial'),TRUE) ? 'bad' : 'info');
?>
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><a class="text-sm text-blue-700 hover:underline dark:text-blue-400" href="<?php echo Route::url('erp802'); ?>">← Все пакеты</a><h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Пакет #<?php echo HTML::chars($package['id']); ?></h1><p class="text-sm text-gray-500 dark:text-gray-400"><?php echo HTML::chars($package['source_name'].' · '.$package['entity_kind'].' · '.($package['dry_run']?'только проверка':'импорт')); ?></p></div>
        <div class="text-right"><?php echo Component::render('badge',array('label'=>$package['status'],'state'=>$package_state)); ?><div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">UUID <?php echo HTML::chars($package['uuid']); ?></div></div>
    </header>
    <?php if($error): ?><div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"><?php echo HTML::chars($error); ?></div><?php endif; ?>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach(array('total_rows'=>'Всего строк','applied_rows'=>'Применено / план','skipped_rows'=>'Пропущено','error_rows'=>'Ошибки') as $key=>$label): ?>
            <?php echo Component::render('kpi',array('label'=>$label,'value'=>$package[$key],'unit'=>'стр.','state'=>$key==='error_rows'&&$package[$key]>0?'bad':'neutral')); ?>
        <?php endforeach; ?>
    </div>
    <?php if($can_retry && in_array($package['status'],array('failed','partial'),TRUE)): ?><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>"><button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700" data-confirm="Повторно обработать только неприменённые строки пакета?">Контролируемый повтор</button></form><?php endif; ?>
    <?php Component::begin('card',array('title'=>'Построчный протокол','subtitle'=>'Каждая строка объясняет результат и сохраняет связь с внешним ID.','icon'=>'fas fa-list-check','flush'=>TRUE)); ?>
        <?php echo Component::render('table',array('caption'=>'Построчный протокол пакета','columns'=>array(array('label'=>'№','priority'=>1),array('label'=>'External ID','priority'=>1),array('label'=>'Статус','priority'=>1),array('label'=>'Причина','priority'=>2),array('label'=>'Результат','priority'=>2),array('label'=>'UUID','priority'=>4)),'rows'=>$table_rows,'row_header'=>TRUE,'row_header_compact'=>TRUE,'sticky'=>TRUE,'empty_title'=>'В пакете нет строк','empty_hint'=>'Проверьте формат и содержимое исходного файла.')); ?>
    <?php Component::end(); ?>
</div>
