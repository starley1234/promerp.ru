<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between mb-4 gap-3">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <a href="/planning/programs" class="text-blue-600 hover:text-blue-800">Программы</a>
                <span class="mx-1">/</span>
                <span class="font-mono"><?php echo HTML::chars($program->number); ?></span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                <?php echo HTML::chars($program->title); ?>
                <span class="px-2 py-1 rounded text-xs align-middle <?php echo $program->status_class(); ?>">
                    <?php echo HTML::chars($program->status_label()); ?>
                </span>
            </h1>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Период <?php echo HTML::chars(date('d.m.Y', strtotime($program->period_from))); ?>
                — <?php echo HTML::chars(date('d.m.Y', strtotime($program->period_to))); ?>
                · горизонт <?php echo (int) $program->horizon_days; ?> дн.
                <?php if ($program->days_left() !== NULL): ?>
                    · осталось <?php echo (int) $program->days_left(); ?> дн.
                <?php endif; ?>
                <?php if ($program->run_at): ?>
                    · расчёт от <?php echo HTML::chars(date('d.m.Y H:i', strtotime($program->run_at))); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <?php if ($can_plan): ?>
                <form method="post" action="/planning/programs/calculate/<?php echo (int) $program->id; ?>" class="inline">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                        <i class="fas fa-calculator mr-1"></i> Рассчитать потребность
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($can_manage AND $program->is_editable()): ?>
                <form method="post" action="/planning/programs/fill/<?php echo (int) $program->id; ?>" class="inline">
                    <button type="submit" class="px-4 py-2 bg-white dark:bg-gray-800 border text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-download mr-1"></i> Собрать из заказов
                    </button>
                </form>
                <a href="/planning/programs/edit/<?php echo (int) $program->id; ?>"
                   class="px-4 py-2 bg-white dark:bg-gray-800 border text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-pen mr-1"></i> Правка
                </a>
            <?php endif; ?>

            <?php if ($can_plan AND $program->status === 'draft'): ?>
                <form method="post" action="/planning/programs/act/approve/<?php echo (int) $program->id; ?>" class="inline">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i> Утвердить
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($can_plan AND $program->status === 'approved'): ?>
                <form method="post" action="/planning/programs/act/close/<?php echo (int) $program->id; ?>" class="inline">
                    <button type="submit" class="px-4 py-2 bg-white dark:bg-gray-800 border text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-lock mr-1"></i> Закрыть период
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <?php
        echo Component::render('kpi',array('label'=>'Строк программы','value'=>count($lines),'unit'=>'стр.','state'=>'neutral'));
        echo Component::render('kpi',array('label'=>'Позиций к обеспечению','value'=>(int)$summary['total'],'unit'=>'поз.','state'=>'info','hint'=>'Закупка '.(int)$summary['purchase'].', выпуск '.(int)$summary['produce']));
        echo Component::render('kpi',array('label'=>'Дефицит','value'=>(int)$summary['deficit'],'unit'=>'поз.','state'=>$summary['deficit']>0?'bad':'ok','hint'=>'Не хватает '.number_format((float)$summary['shortage'],3,'.',' ').' учётных единиц'));
        echo Component::render('kpi',array('label'=>'Покрытие спроса','value'=>(float)$summary['coverage'],'unit'=>'%','state'=>$summary['coverage']>=100?'ok':'warn','target'=>'100 %','hint'=>'Просрочено: '.(int)$summary['late']));
        ?>
    </div>

    <!-- Строки программы -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Изделия программы</h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">источник спроса для расчёта</span>
        </div>

        <?php if (empty($lines)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Строк нет. Добавьте изделия вручную ниже или соберите из открытых заказов производства.
            </div>
        <?php else: ?>
            <?php
            $line_rows=array();
            foreach($lines as $line)
            {
                $name='<span class="text-gray-900 dark:text-white">'.HTML::chars($line->name).'</span>'.($line->code?'<br><span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($line->code).'</span>':'').($line->order_id?'<br><span class="text-xs text-gray-500">из заказа <a href="/production/orders/view/'.(int)$line->order_id.'" class="text-blue-600 hover:text-blue-800">#'.(int)$line->order_id.'</a></span>':'');
                $status='<span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300">'.HTML::chars($line->status_label()).'</span>';
                $cells=array((int)$line->line_no,$name,number_format((float)$line->qty,3,'.',' ').' <span class="text-xs text-gray-500">'.HTML::chars($line->uom_code).'</span>',$line->due_date?HTML::chars(date('d.m.Y',strtotime($line->due_date))):'—',$status);
                if($can_manage)$cells[]='<form method="post" action="/planning/programs/line/remove/'.(int)$line->id.'" data-confirm="Удалить строку?"><button type="submit" class="text-red-600 hover:text-red-800" title="Удалить строку" aria-label="Удалить строку"><i class="fas fa-trash" aria-hidden="true"></i></button></form>';
                $line_rows[]=$cells;
            }
            $line_columns=array(array('label'=>'№','priority'=>4),array('label'=>'Изделие'),array('label'=>'Количество','align'=>'right','nowrap'=>TRUE),array('label'=>'Нужен к','priority'=>2,'nowrap'=>TRUE),array('label'=>'Состояние'));
            if($can_manage)$line_columns[]=array('label'=>'Действия','align'=>'right');
            echo Component::render('table',array('caption'=>'Изделия производственной программы','columns'=>$line_columns,'rows'=>$line_rows,'row_header'=>FALSE));
            ?>
        <?php endif; ?>

        <?php if ($can_manage AND $program->is_editable()): ?>
            <form method="post" action="/planning/programs/line/add" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="program_id" value="<?php echo (int) $program->id; ?>">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Изделие</label>
                    <input type="text" name="name" required placeholder="наименование изделия"
                           class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Артикул (необязательно)</label>
                    <input type="text" name="code" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество</label>
                    <input type="number" name="qty" step="0.001" min="0.001" value="1" required
                           class="w-28 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Ед.</label>
                    <input type="text" name="uom_code" value="шт" class="w-20 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Нужен к</label>
                    <input type="date" name="due_date" value="<?php echo HTML::chars($program->period_to); ?>"
                           class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Техкарта, id</label>
                    <input type="number" name="tech_card_id" class="w-24 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i> Добавить
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Потребность -->
    <?php if ( ! empty($requirements)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Потребность по последнему расчёту</h2>
                <a href="/planning/deficits?program_id=<?php echo (int) $program->id; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                    Только дефициты
                </a>
            </div>
            <?php
            $requirement_rows=array();
            foreach($requirements as $req)
            {
                $name='<span class="text-gray-900 dark:text-white">'.HTML::chars($req->name).'</span>'.(($req->parent_name OR (int)$req->level>0)?'<br><span class="text-xs text-gray-500 dark:text-gray-400">'.($req->parent_name?'состав: '.HTML::chars($req->parent_name):'').((int)$req->level>0?' · уровень '.((int)$req->level+1):'').'</span>':'');
                $deficit='<span class="'.($req->has_deficit()?'font-medium text-red-600 dark:text-red-400':'text-gray-500 dark:text-gray-400').'">'.number_format((float)$req->deficit_qty,3,'.',' ').'</span>';
                $order='<span class="'.($req->is_late()?'font-medium text-red-600 dark:text-red-400':'text-gray-600 dark:text-gray-300').'">'.($req->order_date?HTML::chars(date('d.m.Y',strtotime($req->order_date))):'—').'</span><br><span class="text-xs text-gray-500">срок поставки '.(int)$req->lead_time_days.' дн.'.($req->due_date?' · нужен к '.HTML::chars(date('d.m.Y',strtotime($req->due_date))):'').'</span>';
                $requirement_rows[]=array('class'=>$req->has_deficit()?'':'opacity-60','cells'=>array($name,HTML::chars($req->supply_label()),number_format((float)$req->demand_qty,3,'.',' '),number_format((float)$req->stock_qty,3,'.',' '),number_format((float)$req->wip_qty,3,'.',' '),number_format((float)$req->on_order_qty,3,'.',' '),$deficit,number_format((float)$req->order_qty,3,'.',' '),$order));
            }
            echo Component::render('table',array('caption'=>'Потребность по последнему расчёту','columns'=>array(array('label'=>'Позиция'),array('label'=>'Способ','priority'=>3),array('label'=>'Спрос','align'=>'right'),array('label'=>'Склад','align'=>'right','priority'=>2),array('label'=>'Незавершёнка','align'=>'right','priority'=>4),array('label'=>'В заказах','align'=>'right','priority'=>3),array('label'=>'Дефицит','align'=>'right'),array('label'=>'Заказать','align'=>'right'),array('label'=>'Заказ к','priority'=>2,'nowrap'=>TRUE)),'rows'=>$requirement_rows,'row_header'=>TRUE,'sticky'=>TRUE));
            ?>
        </div>
    <?php elseif ($program->run_at): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-5 py-8 mb-6 text-sm text-gray-500 dark:text-gray-400">
            Потребностей нет: либо в программе нет строк, либо всё покрыто складом и заказами.
        </div>
    <?php endif; ?>

    <!-- Предложения -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Предложения по программе</h2>
            <div class="flex gap-2">
                <?php if ($can_plan): ?>
                    <form method="post" action="/planning/proposals/create" class="inline">
                        <input type="hidden" name="program_id" value="<?php echo (int) $program->id; ?>">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                            <i class="fas fa-lightbulb mr-1"></i> Предложить по дефицитам
                        </button>
                    </form>
                <?php endif; ?>
                <a href="/planning/proposals?program_id=<?php echo (int) $program->id; ?>"
                   class="px-4 py-2 bg-white dark:bg-gray-800 border text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    Все предложения
                </a>
            </div>
        </div>

        <?php if (empty($proposals)): ?>
            <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Предложений по программе нет. Сначала рассчитайте потребность, затем «Предложить по дефицитам» —
                система заведёт по одному предложению на каждую нехватку.
            </p>
        <?php else: ?>
            <?php
            $program_proposal_rows=array();
            foreach($proposals as $status=>$group)foreach($group as $proposal)
            {
                $name='<span class="text-gray-900 dark:text-white">'.HTML::chars($proposal->name).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($proposal->kind_label()).'</span>';
                $state='<span class="rounded px-2 py-1 text-xs '.$proposal->status_class().'">'.HTML::chars($proposal->status_label()).'</span>';
                $program_proposal_rows[]=array('<span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($proposal->number).'</span>',$name,number_format((float)$proposal->qty,3,'.',' ').' <span class="text-xs text-gray-500">'.HTML::chars($proposal->uom_code).'</span>',$proposal->order_date?HTML::chars(date('d.m.Y',strtotime($proposal->order_date))):'—',$state,$proposal->target_ref?HTML::chars($proposal->target_ref):'—');
            }
            echo Component::render('table',array('caption'=>'Предложения по программе','columns'=>array(array('label'=>'Предложение','nowrap'=>TRUE),array('label'=>'Позиция'),array('label'=>'Количество','align'=>'right','nowrap'=>TRUE),array('label'=>'Заказ к','priority'=>2),array('label'=>'Состояние'),array('label'=>'Передано','priority'=>3)),'rows'=>$program_proposal_rows));
            ?>
        <?php endif; ?>
    </div>
</div>
