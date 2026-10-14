<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
$stock_rows = array();
foreach ($rows as $row)
{
    $stock_rows[] = array(
        HTML::chars($row['warehouse_name']),
        number_format((float) $row['qty'], 3, '.', ' '),
        '<span class="text-xs text-yellow-700 dark:text-yellow-400">резерв '.number_format((float) $row['reserved'], 3, '.', ' ').'</span>',
    );
}
Component::begin('card', array(
    'title'=>(string) $item['code'],
    'icon'=>'fas fa-boxes',
    'subtitle'=>'Всего '.number_format((float) $total, 3, '.', ' '),
    'flush'=>TRUE,
));
echo Component::render('table', array(
    'caption'=>'Остатки '.(string) $item['code'].' по складам',
    'columns'=>array(
        array('label'=>'Склад'),
        array('label'=>'Остаток','align'=>'right'),
        array('label'=>'Резерв','align'=>'right','priority'=>2),
    ),
    'rows'=>$stock_rows,
    'row_header'=>TRUE,
    'empty_title'=>'Позиция не числится на складах',
    'empty_hint'=>'Остаток появится после приходной складской операции.',
));
Component::end();
?>
