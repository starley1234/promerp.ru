<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
$planning_rows = array();
foreach ($rows as $row)
{
    $planning_rows[] = array(
        '<span class="font-mono text-gray-500 dark:text-gray-400">'.HTML::chars(substr((string)$row['run_uuid'],0,8)).'</span>',
        number_format((float)$row['demand_qty'],3,'.',' '),
        '<span class="'.((float)$row['deficit_qty']>0?'font-medium text-red-600 dark:text-red-400':'text-gray-500 dark:text-gray-400').'">'.number_format((float)$row['deficit_qty'],3,'.',' ').'</span>',
        '<span class="'.(!empty($row['late'])?'text-red-600 dark:text-red-400':'text-gray-600 dark:text-gray-300').'">'.($row['order_date']?HTML::chars(date('d.m.Y',strtotime($row['order_date']))):'—').'</span>',
    );
}
Component::begin('card',array('title'=>'Планирование по позиции','icon'=>'fas fa-boxes','actions_html'=>'<a href="/planning/deficits" class="text-xs text-blue-600 hover:text-blue-800">Все дефициты →</a>','flush'=>TRUE));
echo Component::render('table',array(
    'caption'=>'Расчёты потребности по позиции',
    'columns'=>array(array('label'=>'Расчёт'),array('label'=>'Спрос','align'=>'right'),array('label'=>'Дефицит','align'=>'right'),array('label'=>'Заказать к','priority'=>2)),
    'rows'=>$planning_rows,'row_header'=>TRUE,
    'empty_title'=>'Позиция не участвовала в расчётах','empty_hint'=>'Добавьте её в состав или программу планирования.',
));
Component::end();
?>
