<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-warehouse mr-2 text-blue-600"></i> Склады
        </h1>
        <a href="/warehouse/warehouses/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Добавить склад
        </a>
    </div>

    <?php echo View::factory('erp401/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $warehouse_rows = array();
    foreach ($warehouses as $warehouse)
    {
        $totals = $warehouse->totals();
        $name = '<span class="text-gray-900 dark:text-white">'.HTML::chars($warehouse->name).'</span>';
        if ($warehouse->address) $name .= '<br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($warehouse->address).'</span>';
        $actions = '<a href="/warehouse/warehouses/edit/'.(int) $warehouse->id.'" class="mr-3 text-blue-600 hover:text-blue-800" title="Редактировать" aria-label="Редактировать склад"><i class="fas fa-edit" aria-hidden="true"></i></a>';
        if ($warehouse->is_active) $actions .= '<a href="/warehouse/warehouses/deactivate/'.(int) $warehouse->id.'" class="text-red-600 hover:text-red-800" title="Закрыть склад" aria-label="Закрыть склад" data-confirm="Закрыть склад &quot;'.HTML::chars($warehouse->name).'&quot;?"><i class="fas fa-lock" aria-hidden="true"></i></a>';
        $warehouse_rows[] = array(
            '<span class="font-mono">'.HTML::chars($warehouse->code).'</span>',$name,
            HTML::chars(isset($types[$warehouse->type])?$types[$warehouse->type]:$warehouse->type),
            (int) $totals['positions'].' / '.number_format($totals['qty'],3,'.',' '),
            Component::render('badge',array('label'=>$warehouse->is_active?'Активен':'Закрыт','state'=>$warehouse->is_active?'ok':'neutral')),
            $actions,
        );
    }
    echo Component::render('table',array(
        'caption'=>'Склады и складские остатки',
        'columns'=>array(
            array('label'=>'Код','nowrap'=>TRUE),array('label'=>'Наименование'),array('label'=>'Тип','priority'=>3),
            array('label'=>'Позиций / единиц','align'=>'right','priority'=>2,'nowrap'=>TRUE),array('label'=>'Статус'),
            array('label'=>'Действия','align'=>'right','nowrap'=>TRUE),
        ),
        'rows'=>$warehouse_rows,'row_header'=>TRUE,
        'empty_title'=>'Склады не созданы','empty_hint'=>'Добавьте первый склад, чтобы учитывать остатки и движения.',
    ));
    ?>

</div>
