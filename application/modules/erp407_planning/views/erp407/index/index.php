<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-calendar-alt mr-2 text-blue-600"></i> Планирование потребностей
        </h1>
        <a href="/planning/programs/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Новая программа
        </a>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Программ</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['programs']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                утверждено <?php echo (int) $stats['approved']; ?>, черновиков <?php echo (int) $stats['draft']; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Дефицитов в конце расчёта</div>
            <div class="text-2xl font-semibold <?php echo $deficit_summary['deficit'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo (int) $deficit_summary['deficit']; ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                просрочено по дате заказа: <?php echo (int) $deficit_summary['late']; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Предложений без решения</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo (int) $proposal_stats['new']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                закупка <?php echo (int) $proposal_stats['purchase']; ?>, выпуск <?php echo (int) $proposal_stats['produce']; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Последний расчёт</div>
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                <?php echo $stats['last_run'] ? HTML::chars(date('d.m.Y H:i', strtotime($stats['last_run']))) : 'ещё не было'; ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                просроченных предложений: <?php echo (int) $proposal_stats['late']; ?>
            </div>
        </div>
    </div>

    <?php if ( ! empty($deficits)): ?>
        <div class="border rounded-md px-4 py-3 text-sm mb-6 bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Не хватает позиций: <strong><?php echo (int) $deficit_summary['deficit']; ?></strong>,
            суммарный дефицит <strong><?php echo number_format((float) $deficit_summary['shortage'], 3, '.', ' '); ?></strong>.
            <a href="/planning/deficits" class="underline ml-1">Все дефициты</a>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Чего не хватает</h2>
                <a href="/planning/deficits" class="text-sm text-blue-600 hover:text-blue-800">Все дефициты</a>
            </div>

            <?php if (empty($deficits)): ?>
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                    <?php if ($stats['last_run']): ?>
                        Дефицитов нет: последний расчёт закрыт полностью.
                    <?php else: ?>
                        Расчётов ещё не было. Откройте программу и нажмите
                        «Рассчитать потребность» — приложение покажет, чего и к какому сроку не хватает.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <?php
                $deficit_rows=array();
                foreach($deficits as $row)
                {
                    $position='<span class="text-gray-900 dark:text-white">'.HTML::chars($row->name).'</span>'.($row->parent_name?'<br><span class="text-xs text-gray-500 dark:text-gray-400">состав: '.HTML::chars($row->parent_name).'</span>':'');
                    $order_date=$row->order_date?'<span class="'.($row->is_late()?'font-medium text-red-600 dark:text-red-400':'text-gray-600 dark:text-gray-300').'">'.HTML::chars(date('d.m.Y',strtotime($row->order_date))).'</span>'.($row->is_late()?'<br><span class="text-xs text-red-600 dark:text-red-400">срок заказа прошёл</span>':''):'—';
                    $supply=HTML::chars($row->supply_label()).'<br><span class="text-xs text-gray-500 dark:text-gray-400">срок поставки '.(int)$row->lead_time_days.' дн.</span>';
                    $deficit_rows[]=array($position,'<span class="font-medium text-red-600 dark:text-red-400">'.number_format((float)$row->deficit_qty,3,'.',' ').'</span> '.HTML::chars($row->uom_code),$order_date,$supply);
                }
                echo Component::render('table',array('caption'=>'Критические дефициты планирования','columns'=>array(array('label'=>'Позиция'),array('label'=>'Не хватает','align'=>'right','nowrap'=>TRUE),array('label'=>'Заказать к','priority'=>2),array('label'=>'Способ','priority'=>3)),'rows'=>$deficit_rows,'row_header'=>TRUE));
                ?>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ждут решения</h2>
                <a href="/planning/proposals?status=new" class="text-sm text-blue-600 hover:text-blue-800">Все</a>
            </div>

            <?php if (empty($proposals)): ?>
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Открытых предложений нет.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php foreach ($proposals as $proposal): ?>
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($proposal->number); ?></span>
                                <span class="px-2 py-0.5 rounded text-xs <?php echo $proposal->status_class(); ?>">
                                    <?php echo HTML::chars($proposal->status_label()); ?>
                                </span>
                            </div>
                            <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($proposal->name); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo HTML::chars($proposal->kind_label()); ?>
                                <?php echo number_format((float) $proposal->qty, 3, '.', ' '); ?>
                                <?php echo HTML::chars($proposal->uom_code); ?>
                                <?php if ($proposal->order_date): ?>
                                    · заказать к <?php echo HTML::chars(date('d.m.Y', strtotime($proposal->order_date))); ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Последние программы</h2>
            <a href="/planning/programs" class="text-sm text-blue-600 hover:text-blue-800">Все программы</a>
        </div>

        <?php if (empty($programs)): ?>
            <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Программ пока нет. Начните с «Новая программа»: укажите период и изделия с количеством —
                остальное посчитает расчёт потребности.
            </p>
        <?php else: ?>
            <?php
            $program_rows=array();
            foreach($programs as $program)
            {
                $name='<a href="/planning/programs/view/'.(int)$program->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($program->number).'</a><br><span class="text-gray-900 dark:text-white">'.HTML::chars($program->title).'</span>';
                $period=HTML::chars(date('d.m.Y',strtotime($program->period_from))).' — '.HTML::chars(date('d.m.Y',strtotime($program->period_to)));
                $status='<span class="rounded px-2 py-1 text-xs '.$program->status_class().'">'.HTML::chars($program->status_label()).'</span>';
                $program_rows[]=array($name,$period,$status);
            }
            echo Component::render('table',array('caption'=>'Последние программы планирования','columns'=>array(array('label'=>'Программа'),array('label'=>'Период','priority'=>2,'nowrap'=>TRUE),array('label'=>'Статус')),'rows'=>$program_rows,'row_header'=>TRUE));
            ?>
        <?php endif; ?>
    </div>
</div>
