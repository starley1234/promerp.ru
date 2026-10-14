<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-tasks mr-1 text-blue-600"></i> Работы в цехе
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Операций <?php echo (int) $totals['total']; ?>: закрыто <?php echo (int) $totals['done']; ?>,
                в работе <?php echo (int) $totals['in_work']; ?>, ждут <?php echo (int) $totals['planned']; ?>.
                Годных <?php echo number_format($totals['qty_ok'], 0, '.', ' '); ?><?php if ($totals['qty_scrap'] > 0): ?>,
                брак <?php echo number_format($totals['qty_scrap'], 0, '.', ' '); ?><?php endif; ?>.
            </p>
        </div>
        <a href="/shopfloor/operations?order_id=<?php echo (int) $order_id; ?>" class="text-sm text-blue-600 hover:text-blue-800">
            Все операции заказа
        </a>
    </div>

    <?php if (empty($operations)): ?>
        <div class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
            Маршрут в цехе ещё не разложен:
            <form autocomplete="off" method="post" action="/shopfloor/plan" class="inline">
                <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
                <button type="submit" class="text-blue-600 hover:text-blue-800 underline">разложить по техкарте</button>
            </form>.
        </div>
    <?php else: ?>
        <?php
        $widget_rows=array();foreach($operations as $operation){$time=number_format($operation['worked_hours'],1,'.',' ').' ч'.($operation['downtime_hours']>0?'<div class="text-xs text-orange-600">простой '.number_format($operation['downtime_hours'],1,'.',' ').' ч</div>':'');$widget_rows[]=array((int)$operation['op_no'],'<a href="/shopfloor/operations/view/'.(int)$operation['id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($operation['name']).'</a>',$operation['work_center_label']?HTML::chars($operation['work_center_label']):'—',HTML::chars((string)$operation['planned_day']),number_format($operation['qty_planned'],0,'.',' ').' / <strong>'.number_format($operation['qty_ok'],0,'.',' ').'</strong>',$time,'<span class="rounded-full px-2 py-1 text-xs '.HTML::chars($operation['status_class']).'">'.HTML::chars($operation['status_label']).'</span>');}
        echo Component::render('table',array('caption'=>'Операции производственного заказа','columns'=>array(array('label'=>'Оп.'),array('label'=>'Работа'),array('label'=>'Центр','priority'=>2),array('label'=>'День','priority'=>3),array('label'=>'План / факт','align'=>'right'),array('label'=>'Время','align'=>'right','priority'=>2),array('label'=>'Статус')),'rows'=>$widget_rows));
        ?>
    <?php endif; ?>
</div>
