<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-industry mr-2 text-blue-600"></i> Поставщики
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo (int) $stats['suppliers']; ?> поставщиков · <?php echo (int) $stats['preferred']; ?> предпочтительных ·
                <?php echo (int) $stats['prices']; ?> записей истории цен по <?php echo (int) $stats['priced_items']; ?> позициям ·
                средний срок <?php echo (float) $stats['avg_lead_time']; ?> дн.
            </p>
        </div>
        <a href="/procurement/suppliers/compare"
           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50">
            <i class="fas fa-balance-scale mr-2"></i> Сравнить по позиции
        </a>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/procurement/suppliers" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="название или ИНН"
                   class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 pb-2">
            <input type="checkbox" name="preferred" value="1" <?php echo $filters['preferred'] ? 'checked' : ''; ?>>
            только предпочтительные
        </label>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Найти
        </button>
    </form>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
        <?php if (empty($rows)): ?>
            <div class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                Поставщики — это контрагенты с ролью «поставщик» из справочника
                <a href="/counterparties" class="text-blue-600 hover:text-blue-800">«Контрагенты»</a>.
                Заведите контрагента и отметьте роль — он появится здесь.
            </div>
        <?php else: ?>
            <?php
            $supplier_rows=array();foreach($rows as $row){$counterparty=$row['counterparty'];$profile=$row['profile'];$name='<a href="/procurement/suppliers/view/'.(int)$counterparty->id.'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($counterparty->name).'</a><br><span class="text-xs text-gray-500">'.($counterparty->inn?'ИНН '.HTML::chars($counterparty->inn):'').($counterparty->city?' · '.HTML::chars($counterparty->city):'').'</span>'.($profile AND $profile->is_preferred()?Component::render('badge',array('label'=>'предпочтительный','state'=>'ok')):'');$supplier_rows[]=array($name,$profile?(int)$profile->lead_time():7,($profile?number_format((float)$profile->min_order,0,'.',' '):'—').' ₽',($profile?(int)$profile->rating:3).'/5',(int)$row['goods'],$row['last_price_at']?HTML::chars(date('d.m.Y',strtotime($row['last_price_at']))):'—','<a href="/procurement/suppliers/view/'.(int)$counterparty->id.'" class="text-blue-600 hover:text-blue-800" aria-label="Открыть поставщика"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>');}
            echo Component::render('table',array('caption'=>'Поставщики и закупочные условия','columns'=>array(array('label'=>'Поставщик'),array('label'=>'Срок, дн.','align'=>'right'),array('label'=>'Мин. партия','align'=>'right','priority'=>3),array('label'=>'Оценка','align'=>'right'),array('label'=>'Позиций с ценами','align'=>'right','priority'=>2),array('label'=>'Последняя цена','priority'=>3),array('label'=>'Действия','align'=>'right')),'rows'=>$supplier_rows,'row_header'=>TRUE));
            ?>
        <?php endif; ?>
    </div>

    <!-- Где поставщики расходятся в цене -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-chart-line mr-2 text-gray-500"></i> Где цены расходятся</h2>
        </div>
        <?php if (empty($savings)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Пока нет позиций с ценами от двух и более поставщиков — сравнивать нечего.
            </div>
        <?php else: ?>
            <?php
            $saving_rows=array();foreach($savings as $row)$saving_rows[]=array(HTML::chars($row['name']),number_format((float)$row['min_price'],2,'.',' ').' ₽',number_format((float)$row['max_price'],2,'.',' ').' ₽','<span class="text-green-600">−'.(float)$row['diff_pct'].' %</span>');
            echo Component::render('table',array('caption'=>'Позиции с расхождением цен поставщиков','columns'=>array(array('label'=>'Позиция'),array('label'=>'Минимум','align'=>'right'),array('label'=>'Максимум','align'=>'right'),array('label'=>'Разница','align'=>'right')),'rows'=>$saving_rows,'row_header'=>TRUE));
            ?>
        <?php endif; ?>
    </div>
</div>
