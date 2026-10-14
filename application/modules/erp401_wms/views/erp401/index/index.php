<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-warehouse mr-2 text-blue-600"></i> Складской учёт
        </h1>
        <a href="/warehouse/movements/create"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus-circle mr-2"></i> Провести операцию
        </a>
    </div>

    <!-- Карточки сводки -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <?php
        echo Component::render('kpi',array('label'=>'Складов','value'=>(int) $warehouses_count,'unit'=>'скл.','state'=>'neutral'));
        echo Component::render('kpi',array('label'=>'Позиций в учёте','value'=>(int) $positions,'unit'=>'поз.','state'=>'info'));
        echo Component::render('kpi',array('label'=>'Всего учётных единиц','value'=>number_format($total_qty,3,'.',' '),'unit'=>'ед.','state'=>'neutral','hint'=>'Сумма количеств; для денежной оценки используйте себестоимость'));
        echo Component::render('kpi',array('label'=>'Зарезервировано','value'=>number_format($total_reserved,3,'.',' '),'unit'=>'ед.','state'=>$total_reserved>0?'warn':'neutral'));
        ?>
    </div>

    <?php
    $low_rows = array();
    foreach ($low_stock as $row)
    {
        $item = '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['item']['code']).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['item']['name']).'</span>';
        $quantity = '<span class="font-semibold text-red-600 dark:text-red-400">'.number_format((float) $row['qty'],3,'.',' ').'</span> <span class="text-xs text-gray-500">/ min '.number_format((float) $row['min_qty'],0,'.',' ').'</span>';
        $low_rows[] = array($item,HTML::chars($row['warehouse_name']),$quantity);
    }
    $recent_rows = array();
    $movement_states = array('receipt'=>'ok','issue'=>'bad','transfer'=>'info','adjust'=>'neutral');
    foreach ($recent as $row)
    {
        $label = isset(Model_Erp401_Movement::$types[$row['type']])?Model_Erp401_Movement::$types[$row['type']]:$row['type'];
        $item = '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['item']['code']).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['warehouse_name']);
        if ( ! empty($row['warehouse_to_name'])) $item .= ' &rarr; '.HTML::chars($row['warehouse_to_name']);
        $item .= '</span>';
        $recent_rows[] = array(Component::render('badge',array('label'=>$label,'state'=>isset($movement_states[$row['type']])?$movement_states[$row['type']]:'neutral')),$item,number_format((float) $row['qty'],3,'.',' '),date('d.m.Y H:i',strtotime($row['performed_at'])));
    }
    ?>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <?php Component::begin('card',array('title'=>'Ниже минимума','icon'=>'fas fa-exclamation-triangle','actions_html'=>'<a href="/warehouse/balances?low_only=1" class="text-sm text-blue-600 hover:underline">Все</a>','flush'=>TRUE)); ?>
        <?php echo Component::render('table',array('caption'=>'Позиции склада ниже минимального остатка','columns'=>array(array('label'=>'Позиция'),array('label'=>'Склад','priority'=>2),array('label'=>'Остаток и минимум','align'=>'right','nowrap'=>TRUE)),'rows'=>$low_rows,'row_header'=>TRUE,'empty_title'=>'Все позиции выше минимума','empty_hint'=>'Сейчас складские остатки не требуют пополнения.')); ?>
        <?php Component::end(); ?>

        <?php Component::begin('card',array('title'=>'Последние движения','icon'=>'fas fa-exchange-alt','actions_html'=>'<a href="/warehouse/movements" class="text-sm text-blue-600 hover:underline">Журнал</a>','flush'=>TRUE)); ?>
        <?php echo Component::render('table',array('caption'=>'Последние движения склада','columns'=>array(array('label'=>'Тип'),array('label'=>'Позиция и склад'),array('label'=>'Количество','align'=>'right'),array('label'=>'Дата','align'=>'right','priority'=>2,'nowrap'=>TRUE)),'rows'=>$recent_rows,'empty_title'=>'Движений пока нет','empty_hint'=>'Зарегистрируйте первую приходную или расходную операцию.')); ?>
        <?php Component::end(); ?>
    </div>

    <!-- Склады -->
    <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Склады</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-5">
            <?php foreach ($warehouses as $warehouse): ?>
                <?php $totals = $warehouse->totals(); ?>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($warehouse->name); ?></span>
                        <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <?php echo HTML::chars($warehouse->type_label()); ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo HTML::chars($warehouse->code); ?></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                        <?php echo (int) $totals['positions']; ?> поз. &middot;
                        <?php echo number_format($totals['qty'], 3, '.', ' '); ?> ед.
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
