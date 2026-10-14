<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement" class="hover:text-blue-600">Закупки</a>
        <span class="mx-1">/</span>
        <a href="/procurement/suppliers" class="hover:text-blue-600">Поставщики</a>
        <span class="mx-1">/</span>
        <span>Сравнение по позиции</span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-balance-scale mr-2 text-blue-600"></i> У кого брать
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Оценка складывается из цены (до 50 баллов), срока поставки (до 30), оценки закупщика (до 15),
            исполнительности (до 15) и метки «предпочтительный» (+10). Партия меньше минимальной — минус 20.
        </p>

        <form method="get" action="/procurement/suppliers/compare" autocomplete="off" class="mt-4 flex flex-wrap gap-3 items-end">
            <div class="min-w-[320px] flex-1">
                <?php echo Component::render('smart_reference', array(
                    'id'=>'supplier_compare_item','name'=>'item_id','label'=>'Номенклатура',
                    'url'=>'/procurement/suppliers/reference','value'=>$item_id ? (int) $item_id : '',
                    'selected_label'=>$item_name ? $item_name : '','required'=>TRUE,
                    'placeholder'=>'Код или название позиции','hint'=>'Выберите позицию — внутренний ID вводить не нужно.',
                )); ?>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество</label>
                <input type="number" step="0.001" name="qty" value="<?php echo $qty !== NULL ? (float) $qty : ''; ?>"
                       placeholder="для проверки минимальной партии"
                       class="w-48 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-search mr-1"></i> Сравнить
            </button>
        </form>

        <?php if ($item_id): ?>
            <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Позиция: <span class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($item_name ? $item_name : '#' . (int) $item_id); ?></span>
                <?php if ($choice): ?>
                    · рекомендация:
                    <span class="font-medium text-green-700 dark:text-green-400"><?php echo HTML::chars($choice['name']); ?></span>
                    (<?php echo (float) $choice['score']; ?> баллов, <?php echo number_format((float) $choice['price'], 2, '.', ' '); ?> <?php echo HTML::chars($choice['currency']); ?>)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($item_id AND empty($rows)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
            По этой позиции ещё нет цен ни от одного поставщика. История цен наполняется отправленными заказами и проведёнными приёмками —
            сравнение появится, когда хотя бы один поставщик отгрузит позицию.
        </div>
    <?php endif; ?>

    <?php if ( ! empty($rows)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
            <?php
            $compare_rows=array();$rank=0;foreach($rows as $row){$rank++;$name='<a href="/procurement/suppliers/view/'.(int)$row['supplier_id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($row['name']).'</a>'.($row['preferred']?' '.Component::render('badge',array('label'=>'предпочтительный','state'=>'ok')):'').'<br><span class="text-xs text-gray-500">сделок '.(int)$row['deals'].($row['last_dated_at']?' · последняя цена '.HTML::chars(date('d.m.Y',strtotime($row['last_dated_at']))):'').'</span>';$compare_rows[]=array('class'=>$rank===1?'bg-green-50 dark:bg-green-900/20':'','cells'=>array($rank,$name,number_format((float)$row['price'],2,'.',' ').' '.HTML::chars($row['currency']),number_format((float)$row['best_price'],2,'.',' '),(int)$row['lead_time'].' дн.',$row['on_time']===NULL?'—':round($row['on_time']).' %','<span class="font-semibold">'.(float)$row['score'].'</span>',HTML::chars(implode(' · ',$row['reasons']))));}
            echo Component::render('table',array('caption'=>'Рейтинг поставщиков по позиции','columns'=>array(array('label'=>'#'),array('label'=>'Поставщик'),array('label'=>'Цена','align'=>'right','nowrap'=>TRUE),array('label'=>'Лучшая','align'=>'right','priority'=>3),array('label'=>'Срок','align'=>'right'),array('label'=>'В срок','align'=>'right','priority'=>3),array('label'=>'Баллы','align'=>'right'),array('label'=>'Почему','priority'=>2)),'rows'=>$compare_rows,'row_header'=>FALSE,'sticky'=>TRUE));
            ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty($history)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-tags mr-2 text-gray-500"></i> Как менялась цена</h2>
            </div>
            <?php
            $history_rows=array();foreach($history as $price)$history_rows[]=array(HTML::chars(date('d.m.Y',strtotime($price['dated_at']))),HTML::chars($price['supplier_name']),number_format((float)$price['price'],2,'.',' ').' '.HTML::chars($price['currency']),(float)$price['qty'].' '.HTML::chars($price['uom_code']),HTML::chars(isset(Model_Erp403_Price::$sources[$price['source']])?Model_Erp403_Price::$sources[$price['source']]:$price['source']));
            echo Component::render('table',array('caption'=>'История изменения закупочной цены','columns'=>array(array('label'=>'Дата','nowrap'=>TRUE),array('label'=>'Поставщик'),array('label'=>'Цена','align'=>'right'),array('label'=>'Количество','align'=>'right','priority'=>2),array('label'=>'Источник','priority'=>3)),'rows'=>$history_rows,'row_header'=>FALSE));
            ?>
        </div>
    <?php endif; ?>
</div>
