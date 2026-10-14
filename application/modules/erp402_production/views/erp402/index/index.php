<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-2 text-blue-600"></i> Производство
        </h1>
        <a href="/production/orders/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Новый заказ
        </a>
    </div>

    <?php echo View::factory('erp402/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Заказов всего</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['total']; ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">В работе (план, резерв, цех)</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['open']; ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Просрочено</div>
            <div class="text-2xl font-semibold <?php echo $stats['overdue'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo (int) $stats['overdue']; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Выпуск / план</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['qty_done'], 0, '.', ' '); ?> / <?php echo number_format($stats['qty'], 0, '.', ' '); ?>
            </div>
        </div>
    </div>

    <?php if ($stats['short_lines'] > 0 OR $stats['unmapped'] > 0): ?>
        <div class="border rounded-md px-4 py-3 text-sm mb-6 bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Требуют решения: строк без остатка — <strong><?php echo (int) $stats['short_lines']; ?></strong>,
            строк без номенклатуры — <strong><?php echo (int) $stats['unmapped']; ?></strong>.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2">
            <?php
            $open_rows = array();
            foreach ($open_orders as $row)
            {
                $order_cell = '<a href="/production/orders/view/'.(int)$row['id'].'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($row['number']).'</a><br><span class="text-gray-900 dark:text-white">'.HTML::chars($row['title']).'</span>';
                $status = '<span class="rounded px-2 py-1 text-xs '.Model_Erp402_Order::$status_classes[$row['status']].'">'.HTML::chars(isset($statuses[$row['status']])?$statuses[$row['status']]:$row['status']).'</span>';
                $open_rows[] = array($order_cell,number_format((float)$row['qty_done'],0,'.',' ').' / '.number_format((float)$row['qty'],0,'.',' '),$status,$row['target_date']?HTML::chars($row['target_date']):'—');
            }
            Component::begin('card',array('title'=>'Открытые заказы','icon'=>'fas fa-clipboard-list','actions_html'=>'<a href="/production/orders" class="text-sm text-blue-600 hover:text-blue-800">Все заказы</a>','flush'=>TRUE));
            echo Component::render('table',array('caption'=>'Открытые производственные заказы','columns'=>array(array('label'=>'Заказ'),array('label'=>'План','align'=>'right','nowrap'=>TRUE),array('label'=>'Статус'),array('label'=>'Срок','priority'=>2,'nowrap'=>TRUE)),'rows'=>$open_rows,'row_header'=>TRUE,'empty_title'=>'Открытых заказов нет','empty_hint'=>'Создайте заказ или переведите черновик в план.'));
            Component::end();
            ?>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Не хватает материалов</h2>
            </div>

            <?php if (empty($shortages)): ?>
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Все материалы обеспечены.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php foreach ($shortages as $short): ?>
                        <li class="px-5 py-3">
                            <a href="/production/orders/view/<?php echo (int) $short['order']->id; ?>" class="text-blue-600 hover:text-blue-800 font-mono">
                                <?php echo HTML::chars($short['order']->number); ?>
                            </a>
                            <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($short['name']); ?></div>
                            <div class="text-xs text-red-600 dark:text-red-400">
                                нужно <?php echo number_format($short['needed'], 3, '.', ' '); ?>,
                                есть <?php echo number_format((float) $short['have'], 3, '.', ' '); ?>,
                                не хватает <?php echo number_format($short['shortage'], 3, '.', ' '); ?> <?php echo HTML::chars($short['uom_code']); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty($overdue)): ?>
        <?php
        $overdue_rows = array();
        foreach ($overdue as $row) $overdue_rows[] = array('<a href="/production/orders/view/'.(int)$row['id'].'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($row['number']).'</a>',HTML::chars($row['title']),'<span class="text-red-600 dark:text-red-400">срок '.HTML::chars($row['target_date']).'</span>');
        ?>
        <div class="mt-6">
            <?php Component::begin('card',array('title'=>'Просроченные заказы','icon'=>'fas fa-triangle-exclamation','state'=>'bad','flush'=>TRUE)); ?>
            <?php echo Component::render('table',array('caption'=>'Просроченные производственные заказы','columns'=>array(array('label'=>'Заказ','nowrap'=>TRUE),array('label'=>'Изделие'),array('label'=>'Срок','align'=>'right','nowrap'=>TRUE)),'rows'=>$overdue_rows,'row_header'=>TRUE)); ?>
            <?php Component::end(); ?>
        </div>
    <?php endif; ?>
</div>
