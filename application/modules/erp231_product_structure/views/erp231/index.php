<?php defined('SYSPATH') or die('No direct script access.');
$input = 'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
$table_rows = array();
foreach ($rows as $row)
{
    $table_rows[] = array(
        '<a class="font-medium text-blue-700 hover:underline dark:text-blue-400" href="'.Route::url('erp231', array('action'=>'view','id'=>$row['id'])).'">'.HTML::chars($row['product_code'].' · '.$row['product_name']).'</a>',
        HTML::chars(strtoupper($row['kind']).' · '.$row['revision']),
        HTML::chars($row['site_code'] ?: '—'),
        Component::render('badge', array('label'=>$row['status'], 'state'=>$row['status']==='approved' ? 'ok' : ($row['status']==='review' ? 'warn' : 'neutral'))),
        HTML::chars($row['line_count']),
    );
}
?>
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Составы EBOM / MBOM</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Версионируемые конструкторские и производственные составы. Утверждённая ревизия неизменяема.</p>
    </header>

    <?php if ($error): ?>
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"><?php echo HTML::chars($error); ?></div>
    <?php endif; ?>

    <?php if ($can_manage): ?>
        <?php Component::begin('card', array('title'=>'Новый состав','subtitle'=>'Выберите изделие по коду или названию — внутренний ID вводить не нужно.','icon'=>'fas fa-plus-circle','id'=>'new-structure')); ?>
        <form method="post" autocomplete="off" class="grid gap-4 md:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>">
            <div class="md:col-span-2"><?php echo Component::render('smart_reference', array('id'=>'structure_product','name'=>'product_item_id','label'=>'Изделие','url'=>URL::site('product-structures/api/reference'),'required'=>TRUE,'placeholder'=>'Код или название изделия')); ?></div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Вид<select name="kind" class="<?php echo $input; ?>"><option value="ebom">EBOM</option><option value="mbom">MBOM</option><option value="service">Service BOM</option></select></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Ревизия<input name="revision" autocomplete="off" class="<?php echo $input; ?>" placeholder="автоматически"></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Площадка<input name="site_code" autocomplete="off" class="<?php echo $input; ?>" placeholder="например, SHOP-01"></label>
            <div class="flex items-end"><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">Создать черновик</button></div>
        </form>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card', array('title'=>'Ревизии составов','subtitle'=>'Найдено: '.(int)$total,'icon'=>'fas fa-sitemap','flush'=>TRUE)); ?>
        <form method="get" autocomplete="off" aria-label="Фильтр составов" class="grid gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-4">
            <label class="sm:col-span-2"><span class="sr-only">Изделие, код или ревизия</span><input type="search" name="term" autocomplete="off" value="<?php echo HTML::chars(Arr::get($_GET,'term','')); ?>" class="<?php echo $input; ?> mt-0" placeholder="Изделие, код или ревизия"></label>
            <select name="kind" aria-label="Вид состава" class="<?php echo $input; ?> mt-0"><option value="">Все виды</option><?php foreach(array('ebom','mbom','service') as $value): ?><option value="<?php echo $value; ?>" <?php echo $value===Arr::get($_GET,'kind')?'selected':''; ?>><?php echo strtoupper($value); ?></option><?php endforeach; ?></select>
            <div class="flex gap-2"><select name="status" aria-label="Статус" class="<?php echo $input; ?> mt-0"><option value="">Все статусы</option><?php foreach(array('draft','review','approved','obsolete') as $value): ?><option value="<?php echo $value; ?>" <?php echo $value===Arr::get($_GET,'status')?'selected':''; ?>><?php echo $value; ?></option><?php endforeach; ?></select><button class="rounded-lg border border-gray-300 px-4 text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">Найти</button></div>
        </form>
        <?php echo Component::render('table', array(
            'caption'=>'Ревизии составов изделий',
            'columns'=>array(
                array('label'=>'Изделие','priority'=>1), array('label'=>'Вид / ревизия','priority'=>1),
                array('label'=>'Площадка','priority'=>3), array('label'=>'Статус','priority'=>1), array('label'=>'Строк','align'=>'right','priority'=>2),
            ),
            'rows'=>$table_rows, 'row_header'=>TRUE,
            'empty_title'=>'Спецификации не найдены', 'empty_hint'=>'Измените фильтры или создайте первый черновик для изделия.',
        )); ?>
    <?php Component::end(); ?>
</div>
