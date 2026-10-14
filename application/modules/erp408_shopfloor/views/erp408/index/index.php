<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-2 text-blue-600"></i> Цех · загрузка на <?php echo HTML::chars($day); ?>
        </h1>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="px-3 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300">
                <i class="fas fa-clock mr-1"></i>
                <?php echo $shift ? 'Смена '.HTML::chars($shift['code']).' ('.HTML::chars($shift['label']).')' : 'Смена не определена'; ?>
            </span>

            <form autocomplete="off" method="get" action="/shopfloor" class="flex items-center gap-2">
                <input type="date" name="day" value="<?php echo HTML::chars($day); ?>"
                       class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <select name="center_id" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <option value="0">Все центры</option>
                    <?php foreach ($centers as $center): ?>
                        <option value="<?php echo (int) $center['id']; ?>" <?php echo (int) $center_id === (int) $center['id'] ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($center['code'].' · '.$center['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-search mr-1"></i> Показать
                </button>
            </form>

            <?php if ($can_print_documents): ?>
                <a href="/documents/edit?template_code=SHIFT-ASSIGNMENT&amp;ref_type=erp408_shift_day&amp;ref_id=<?php echo rawurlencode($day); ?>"
                   class="px-4 py-2 rounded-md border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300">
                    <i class="fas fa-print mr-1"></i> Сменное задание
                </a>
            <?php endif; ?>

            <a href="/shopfloor/terminal<?php echo $center_id ? '?center_id='.(int) $center_id : ''; ?>"
               class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                <i class="fas fa-tablet-alt mr-1"></i> Терминал
            </a>
        </div>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <?php echo Component::render('kpi',array('label'=>'Операций на день','value'=>(int)$board['totals']['operations'],'unit'=>'оп.','hint'=>'Закрыто '.(int)$board['totals']['done'])); ?>
        <?php echo Component::render('kpi',array('label'=>'Плановая загрузка','value'=>number_format($board['totals']['hours'],1,'.',' '),'unit'=>'ч','hint'=>'Без назначения: '.count($board['unassigned']).' оп.')); ?>
        <?php echo Component::render('kpi',array('label'=>'Выработка за 7 дней','value'=>number_format(isset($stats['qty_ok'])?$stats['qty_ok']:0,0,'.',' '),'unit'=>'шт.','hint'=>'Брак '.number_format(isset($stats['qty_scrap'])?$stats['qty_scrap']:0,0,'.',' '),'state'=>(!empty($stats['qty_scrap'])?'warn':'ok'))); ?>
        <?php $downtime_hours = Erp::hours(isset($stats['downtime_sec']) ? $stats['downtime_sec'] : 0); echo Component::render('kpi',array('label'=>'Простой за 7 дней','value'=>number_format($downtime_hours,1,'.',' '),'unit'=>'ч','hint'=>'Работа '.number_format(Erp::hours(isset($stats['worked_sec'])?$stats['worked_sec']:0),1,'.',' ').' ч','state'=>$downtime_hours>1?'warn':'ok')); ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">
            <?php if (empty($board['centers'])): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-sm text-gray-500 dark:text-gray-400">
                    Рабочих центров нет. Заведите их в разделе <a href="/shopfloor/centers/edit" class="text-blue-600 hover:text-blue-800">«Центры»</a>,
                    иначе операции будет не на что назначить.
                </div>
            <?php endif; ?>

            <?php foreach ($board['centers'] as $row): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">
                                <?php echo HTML::chars($row['center']['code'].' · '.$row['center']['name']); ?>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo HTML::chars($row['center']['kind_label']); ?>
                                <?php if ($row['center']['workshop']): ?> · <?php echo HTML::chars($row['center']['workshop']); ?><?php endif; ?>
                                <?php if ( ! $row['is_working']): ?> · <span class="text-orange-600">нерабочий день</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                            <div><?php echo number_format($row['hours'], 1, '.', ' '); ?> ч из <?php echo number_format($row['available'], 1, '.', ' '); ?> ч</div>
                            <div class="w-40 h-2 bg-gray-200 dark:bg-gray-700 rounded-full mt-1">
                                <?php $load = min(100, (int) $row['load_pct']); ?>
                                <div class="h-2 rounded-full <?php echo $row['load_pct'] > 100 ? 'bg-red-500' : ($row['load_pct'] > 80 ? 'bg-yellow-500' : 'bg-green-500'); ?>"
                                     data-progress="<?php echo $load; ?>"></div>
                            </div>
                            <div class="mt-1">загрузка <?php echo (int) $row['load_pct']; ?>%</div>
                        </div>
                    </div>

                    <?php if (empty($row['operations'])): ?>
                        <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Операций на этот день нет.</p>
                    <?php else: ?>
                        <?php
                        $board_rows=array();
                        foreach($row['operations'] as $operation)
                        {
                            $work=HTML::chars($operation['name']).($operation['is_downtime']?' <span class="text-xs text-orange-600"><i class="fas fa-tools" aria-hidden="true"></i> простой</span>':'');
                            $fact=number_format($operation['qty_planned'],0,'.',' ').' / <strong>'.number_format($operation['qty_ok'],0,'.',' ').'</strong>'.($operation['qty_scrap']>0?' <span class="text-xs text-red-600">(брак '.number_format($operation['qty_scrap'],0,'.',' ').')</span>':'');
                            $hours=number_format($operation['planned_hours'],1,'.',' ').($operation['worked_hours']>0?' / <span class="text-green-700 dark:text-green-400">'.number_format($operation['worked_hours'],1,'.',' ').'</span>':'');
                            $board_rows[]=array((int)$operation['op_no'],'<a href="/shopfloor/operations/view/'.(int)$operation['id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($operation['order_number']).'</a>',$work,$fact,'<span class="rounded-full px-2 py-1 text-xs '.HTML::chars($operation['status_class']).'">'.HTML::chars($operation['status_label']).'</span>',$hours);
                        }
                        echo Component::render('table',array('caption'=>'Операции рабочего центра '.(string)$row['center']['code'],'columns'=>array(array('label'=>'Опер.'),array('label'=>'Заказ'),array('label'=>'Работа'),array('label'=>'План / факт','align'=>'right'),array('label'=>'Статус'),array('label'=>'Часы','align'=>'right','priority'=>2)),'rows'=>$board_rows));
                        ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ( ! empty($board['unassigned'])): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Операции без рабочего центра</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Их нужно назначить: без центра и дня работа не попадёт в смену.</p>
                    </div>
                    <?php
                    $unassigned_rows=array();foreach($board['unassigned'] as $operation)$unassigned_rows[]=array((int)$operation['op_no'],HTML::chars($operation['order_number']),'<a href="/shopfloor/operations/edit/'.(int)$operation['id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($operation['name']).'</a>',number_format($operation['qty_planned'],0,'.',' ').' шт');
                    echo Component::render('table',array('caption'=>'Операции без рабочего центра','columns'=>array(array('label'=>'Опер.'),array('label'=>'Заказ'),array('label'=>'Работа'),array('label'=>'План','align'=>'right')),'rows'=>$unassigned_rows));
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Последние отметки</h2>
                    <a href="/shopfloor/operations" class="text-sm text-blue-600 hover:text-blue-800">Операции</a>
                </div>

                <?php if (empty($recent)): ?>
                    <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Отметок пока нет: работа в цехе не начиналась.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($recent as $log): ?>
                            <li class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-900 dark:text-gray-100"><?php echo HTML::chars($log['kind_label']); ?></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars(substr((string) $log['at'], 11, 5)); ?></span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <a href="/shopfloor/operations/view/<?php echo (int) $log['operation_id']; ?>" class="hover:text-blue-600">
                                        <?php echo HTML::chars($log['order_number'].' · оп. '.$log['op_no'].' · '.$log['operation_name']); ?>
                                    </a>
                                </div>
                                <?php if ($log['qty_ok'] !== NULL): ?>
                                    <div class="text-xs text-green-700 dark:text-green-400">годных <?php echo number_format($log['qty_ok'], 0, '.', ' '); ?>
                                        <?php if ($log['qty_scrap']): ?>, брак <?php echo number_format($log['qty_scrap'], 0, '.', ' '); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($log['reason_label']): ?>
                                    <div class="text-xs text-orange-600"><?php echo HTML::chars($log['reason_label']); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Заказы без маршрута</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Заказ в производстве, а операций в цехе нет.</p>
                </div>

                <?php if (empty($queue)): ?>
                    <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Все заказы разложены по операциям.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($queue as $order): ?>
                            <li class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="/production/orders/view/<?php echo (int) $order['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        <?php echo HTML::chars($order['number']); ?>
                                    </a>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo number_format((float) $order['qty'], 0, '.', ' '); ?> <?php echo HTML::chars($order['uom_code']); ?>
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($order['title']); ?></div>

                                <form autocomplete="off" method="post" action="/shopfloor/plan" class="mt-2">
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                    <button type="submit" class="text-xs px-3 py-1 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                        <i class="fas fa-sitemap mr-1"></i> Разложить маршрут
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
