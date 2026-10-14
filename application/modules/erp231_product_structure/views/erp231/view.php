<?php defined('SYSPATH') or die('No direct script access.');
$input = 'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
$table_rows = array();
foreach ($structure['lines'] as $line)
{
    $table_rows[] = array(
        HTML::chars($line['line_no']),
        HTML::chars($line['component_code'].' · '.$line['component_name']),
        HTML::chars($line['qty'].' '.$line['uom']),
        Component::render('badge',array('label'=>$line['supply_type'],'state'=>$line['supply_type']==='make'?'info':'neutral')),
        HTML::chars($line['operation_no'] ?: '—'), HTML::chars($line['note']),
    );
}
?>
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><a href="<?php echo Route::url('erp231'); ?>" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все составы</a><h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars(strtoupper($structure['kind']).' '.$structure['revision']); ?></h1><p class="text-sm text-gray-500 dark:text-gray-400"><?php echo HTML::chars($structure['product_code'].' · '.$structure['product_name'].' · площадка '.($structure['site_code']?:'не задана')); ?></p></div>
        <div class="text-right"><?php echo Component::render('badge',array('label'=>$structure['status'],'state'=>$structure['status']==='approved'?'ok':($structure['status']==='review'?'warn':'neutral'))); ?><p class="mt-1 text-xs <?php echo $check['valid']?'text-green-700 dark:text-green-400':'text-red-700 dark:text-red-400'; ?>"><?php echo $check['valid']?'Проверка пройдена':'Есть ошибки'; ?></p></div>
    </header>
    <?php if($error): ?><div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"><?php echo HTML::chars($error); ?></div><?php endif; ?>
    <?php if(!$check['valid']): ?><div role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"><p class="font-semibold">Что нужно исправить</p><ul class="ml-5 mt-1 list-disc"><?php foreach($check['errors'] as $message): ?><li><?php echo HTML::chars($message); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if($can_manage&&in_array($structure['status'],array('draft','review'),TRUE)): ?>
        <?php Component::begin('card',array('title'=>'Добавить компонент','subtitle'=>'Поиск компонента работает по коду и названию.','icon'=>'fas fa-plus-circle')); ?>
        <form method="post" autocomplete="off" class="grid gap-4 md:grid-cols-5"><input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>"><input type="hidden" name="form_action" value="add_line"><div class="md:col-span-2"><?php echo Component::render('smart_reference',array('id'=>'structure_component','name'=>'component_item_id','label'=>'Компонент','url'=>URL::site('product-structures/api/reference'),'required'=>TRUE,'placeholder'=>'Код или название компонента')); ?></div><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Количество<input required type="number" min="0.000001" step="0.000001" name="qty" autocomplete="off" class="<?php echo $input; ?>"></label><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Единица<input name="uom" value="pcs" autocomplete="off" class="<?php echo $input; ?>"></label><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Обеспечение<select name="supply_type" class="<?php echo $input; ?>"><option value="buy">Покупка</option><option value="make">Производство</option><option value="phantom">Фантом</option><option value="consumable">Расходник</option></select></label><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Операция<input type="number" name="operation_no" autocomplete="off" class="<?php echo $input; ?>"></label><label class="text-sm font-medium text-gray-700 dark:text-gray-200 md:col-span-3">Примечание<input name="note" autocomplete="off" class="<?php echo $input; ?>"></label><div class="flex items-end"><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Добавить строку</button></div></form>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card',array('title'=>'Строки состава','subtitle'=>'Количество относится к одной единице изделия, если в регламенте не указано иное.','icon'=>'fas fa-list','flush'=>TRUE)); ?>
        <?php echo Component::render('table',array('caption'=>'Строки состава изделия','columns'=>array(array('label'=>'№','priority'=>1),array('label'=>'Компонент','priority'=>1),array('label'=>'Количество','priority'=>1),array('label'=>'Обеспечение','priority'=>2),array('label'=>'Операция','priority'=>3),array('label'=>'Примечание','priority'=>4)),'rows'=>$table_rows,'row_header'=>TRUE,'row_header_compact'=>TRUE,'empty_title'=>'Состав пока пуст','empty_hint'=>'Добавьте хотя бы один компонент перед передачей на проверку.')); ?>
    <?php Component::end(); ?>

    <div class="flex flex-wrap gap-3"><?php if($can_manage&&$structure['status']==='draft'): ?><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>"><input type="hidden" name="form_action" value="review"><button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Передать на проверку</button></form><?php endif; ?><?php if($can_approve&&$structure['status']==='review'): ?><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>"><input type="hidden" name="form_action" value="approve"><button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800" data-confirm="Утвердить ревизию? После утверждения состав станет неизменяемым.">Утвердить ревизию</button></form><?php endif; ?></div>
</div>
