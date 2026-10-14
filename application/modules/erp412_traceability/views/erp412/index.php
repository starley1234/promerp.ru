<?php defined('SYSPATH') or die('No direct script access.');
$input = 'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
$table_rows = array();
foreach ($lots as $lot)
{
    $states = array('available'=>'ok','quarantine'=>'warn','recalled'=>'bad','scrapped'=>'bad','consumed'=>'neutral');
    $table_rows[] = array(
        '<a class="font-medium text-blue-700 hover:underline dark:text-blue-400" href="'.Route::url('erp412',array('action'=>'view','id'=>$lot['uuid'])).'">'.HTML::chars($lot['lot_number']).'</a>'.($lot['serial_number']?'<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">S/N '.HTML::chars($lot['serial_number']).'</div>':''),
        HTML::chars($lot['item_code'].' · '.$lot['item_name']),
        Component::render('badge',array('label'=>$lot['status'],'state'=>isset($states[$lot['status']])?$states[$lot['status']]:'neutral')),
        HTML::chars($lot['qty_trace']), HTML::chars($lot['expires_at'] ?: '—'),
    );
}
?>
<div class="space-y-6">
    <header><h1 class="text-2xl font-bold text-gray-900 dark:text-white">Прослеживаемость партий</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Партии и серийные номера, складские распределения и genealogy от поставщика до выпуска.</p></header>
    <?php if($error): ?><div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"><?php echo HTML::chars($error); ?></div><?php endif; ?>

    <?php if($can_manage): ?>
        <?php Component::begin('card',array('title'=>'Новая партия или serial','subtitle'=>'Номенклатура выбирается по понятному коду или названию. Номер партии можно сгенерировать автоматически.','icon'=>'fas fa-plus-circle','id'=>'new-lot')); ?>
        <form method="post" autocomplete="off" class="grid gap-4 md:grid-cols-4"><input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>">
            <div class="md:col-span-2"><?php echo Component::render('smart_reference',array('id'=>'trace_item','name'=>'item_id','label'=>'Номенклатура','url'=>URL::site('traceability/api/reference'),'required'=>TRUE,'placeholder'=>'Код или название номенклатуры')); ?></div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Номер партии<input name="lot_number" autocomplete="off" class="<?php echo $input; ?>" placeholder="сгенерируется автоматически"></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Режим<select name="tracking_mode" class="<?php echo $input; ?>"><option value="lot">Партия</option><option value="serial">Серийный учёт</option></select></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Серийный номер<input name="serial_number" autocomplete="off" class="<?php echo $input; ?>"></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Изготовлено<input type="date" name="manufactured_at" autocomplete="off" class="<?php echo $input; ?>"></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Годен до<input type="date" name="expires_at" autocomplete="off" class="<?php echo $input; ?>"></label>
            <div class="flex items-end"><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Создать партию</button></div>
        </form>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card',array('title'=>'Партии и серийные номера','icon'=>'fas fa-code-branch','flush'=>TRUE)); ?>
        <form method="get" autocomplete="off" aria-label="Поиск партий" class="grid gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-[1fr_auto_auto]"><label><span class="sr-only">Партия, serial, код или название</span><input type="search" name="q" autocomplete="off" value="<?php echo HTML::chars(Arr::get($_GET,'q','')); ?>" class="<?php echo $input; ?> mt-0" placeholder="Партия, serial, код или название"></label><select name="status" aria-label="Статус партии" class="<?php echo $input; ?> mt-0"><option value="">Все статусы</option><?php foreach(array('available','quarantine','consumed','scrapped','recalled') as $status): ?><option value="<?php echo $status; ?>" <?php echo $status===Arr::get($_GET,'status')?'selected':''; ?>><?php echo $status; ?></option><?php endforeach; ?></select><button class="rounded-lg border border-gray-300 px-4 text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">Найти</button></form>
        <?php echo Component::render('table',array('caption'=>'Партии и серийные номера','columns'=>array(array('label'=>'Партия / serial','priority'=>1),array('label'=>'Номенклатура','priority'=>1),array('label'=>'Статус','priority'=>1),array('label'=>'Trace qty','priority'=>2,'align'=>'right'),array('label'=>'Годен до','priority'=>3,'nowrap'=>TRUE)),'rows'=>$table_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Партии не найдены','empty_hint'=>'Измените фильтры или создайте первую партию.')); ?>
    <?php Component::end(); ?>
</div>
