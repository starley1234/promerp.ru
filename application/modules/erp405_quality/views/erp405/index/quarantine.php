<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-box-open mr-2 text-blue-600"></i> Карантин
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Партии, изолированные после контроля. Пока партия здесь, контроль не закрывается: сначала решение и выпуск.
            </p>
        </div>
    </div>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if (empty($warehouses)): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-md px-4 py-3 mb-4">
            Склада типа «карантин» нет. Заведите его в модуле склада (erp401) — иначе партию некуда изолировать.
        </div>
    <?php endif; ?>

    <?php
    $quarantine_rows = array();
    foreach ($rows as $row)
    {
        $quarantine_rows[] = array('<a href="/quality/inspections/view/'.(int)$row['id'].'" class="font-medium text-blue-600 hover:text-blue-800">'.HTML::chars($row['number']).'</a>',HTML::chars($row['item_name']),HTML::chars(isset($warehouses[(int)$row['quarantine_warehouse_id']])?$warehouses[(int)$row['quarantine_warehouse_id']]:'—'),number_format((float)$row['qty'],3,'.',' '),'<span class="font-medium text-blue-600">'.number_format((float)$row['quarantine_qty'],3,'.',' ').'</span>',HTML::chars(substr((string)$row['created_at'],0,16)));
    }
    echo Component::render('table',array(
        'caption'=>'Партии на карантинных складах','columns'=>array(array('label'=>'Контроль','nowrap'=>TRUE),array('label'=>'Позиция'),array('label'=>'Склад карантина','priority'=>2),array('label'=>'Партия','align'=>'right','priority'=>3),array('label'=>'На карантине','align'=>'right','nowrap'=>TRUE),array('label'=>'С какого','priority'=>3,'nowrap'=>TRUE)),
        'rows'=>$quarantine_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Карантин пуст','empty_hint'=>'Сейчас нет партий, ожидающих решения по качеству.',
    ));
    ?>
</div>
