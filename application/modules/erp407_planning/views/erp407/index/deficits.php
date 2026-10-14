<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i> Дефициты
        </h1>
        <a href="/planning" class="text-sm text-blue-600 hover:text-blue-800">← Доска планирования</a>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/planning/deficits" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Программа</label>
            <select name="program_id" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">последний расчёт по любой программе</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?php echo (int) $program->id; ?>" <?php echo ($program_id == $program->id) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($program->number.' · '.$program->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Способ обеспечения</label>
            <select name="kind" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любой</option>
                <option value="purchase" <?php echo ($kind === 'purchase') ? 'selected' : ''; ?>>закупка</option>
                <option value="produce" <?php echo ($kind === 'produce') ? 'selected' : ''; ?>>своё производство</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-filter mr-1"></i> Показать
        </button>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <?php
        echo Component::render('kpi',array('label'=>'Позиций с дефицитом','value'=>(int)$summary['deficit'],'unit'=>'поз.','state'=>$summary['deficit']>0?'bad':'ok'));
        echo Component::render('kpi',array('label'=>'Просрочено по дате заказа','value'=>(int)$summary['late'],'unit'=>'поз.','state'=>$summary['late']>0?'bad':'ok'));
        echo Component::render('kpi',array('label'=>'Суммарный дефицит','value'=>number_format((float)$summary['shortage'],3,'.',' '),'unit'=>'ед.','state'=>$summary['shortage']>0?'warn':'neutral','hint'=>'Сумма количеств; денежную оценку даст erp409 по источникам ставок'));
        echo Component::render('kpi',array('label'=>'Покрытие спроса','value'=>(float)$summary['coverage'],'unit'=>'%','state'=>$summary['coverage']>=100?'ok':'warn','target'=>'100 %'));
        ?>
    </div>

    <?php
    $deficit_rows = array();
    foreach ($rows as $row)
    {
        $position='<span class="text-gray-900 dark:text-white">'.HTML::chars($row->name).'</span>'.($row->code?'<br><span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row->code).'</span>':'');
        $deadline='<span class="text-gray-600 dark:text-gray-300">нужен к '.($row->due_date?HTML::chars(date('d.m.Y',strtotime($row->due_date))):'—').'</span><br><span class="text-xs '.($row->is_late()?'font-medium text-red-600 dark:text-red-400':'text-gray-500 dark:text-gray-400').'">заказать к '.($row->order_date?HTML::chars(date('d.m.Y',strtotime($row->order_date))):'—').($row->is_late()?' · срок прошёл':'').'</span>';
        $deficit_rows[]=array($position,$row->parent_name?HTML::chars($row->parent_name):'—',number_format((float)$row->demand_qty,3,'.',' '),number_format((float)$row->covered_qty,3,'.',' '),'<span class="font-medium text-red-600 dark:text-red-400">'.number_format((float)$row->deficit_qty,3,'.',' ').'</span>',number_format((float)$row->order_qty,3,'.',' ').' <span class="text-xs text-gray-500">'.HTML::chars($row->uom_code).'</span>',$deadline);
    }
    echo Component::render('table',array('caption'=>'Дефициты последнего расчёта потребности','columns'=>array(array('label'=>'Позиция'),array('label'=>'Состав','priority'=>4),array('label'=>'Нужно','align'=>'right','priority'=>3),array('label'=>'Есть','align'=>'right','priority'=>3),array('label'=>'Не хватает','align'=>'right','nowrap'=>TRUE),array('label'=>'Заказать','align'=>'right','nowrap'=>TRUE),array('label'=>'Срок / заказ к','priority'=>2,'nowrap'=>TRUE)),'rows'=>$deficit_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Дефицитов нет','empty_hint'=>'Либо последний расчёт полностью обеспечен, либо расчёт нужно запустить из карточки программы.'));
    ?>
</div>
