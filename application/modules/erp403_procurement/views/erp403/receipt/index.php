<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-truck-loading mr-2 text-blue-600"></i> Приёмки
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · ждут проведения <?php echo (int) $stats['draft']; ?> ·
                за месяц принято на <?php echo number_format((float) $stats['amount_month'], 0, '.', ' '); ?> ₽
                <?php if ((int) $stats['quality_blocked']): ?>
                    · <span class="text-red-600 font-medium">заблокировано контролем: <?php echo (int) $stats['quality_blocked']; ?></span>
                <?php endif; ?>
            </p>
        </div>
        <a href="/procurement/orders?open=1"
           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50">
            <i class="fas fa-file-signature mr-2"></i> Принять по заказу
        </a>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/procurement/receipts" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="номер приёмки, накладная, позиция"
                   class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Состояние</label>
            <select name="status" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любое</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo ($filters['status'] === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поставщик</label>
            <select name="supplier_id" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                <option value="">любой</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo (int) $supplier['id']; ?>" <?php echo ((int) $filters['supplier_id'] === (int) $supplier['id']) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($supplier['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Найти
        </button>
    </form>

    <?php
    $table_rows=array();foreach($rows as $receipt){$name='<a href="/procurement/receipts/view/'.(int)$receipt->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($receipt->number).'</a>'.($receipt->order_id?'<br><span class="text-xs text-gray-500">по заказу #'.(int)$receipt->order_id.'</span>':'');$doc=$receipt->doc_number?HTML::chars($receipt->doc_number):'—';if($receipt->doc_date)$doc.='<br><span class="text-xs text-gray-500">'.HTML::chars(date('d.m.Y',strtotime($receipt->doc_date))).'</span>';$table_rows[]=array($name,$doc,$receipt->received_at?HTML::chars(date('d.m.Y',strtotime($receipt->received_at))):'—','#'.(int)$receipt->warehouse_id,'<span class="rounded px-2 py-1 text-xs '.$receipt->status_class().'">'.HTML::chars($receipt->status_label()).'</span>','<a href="/procurement/receipts/view/'.(int)$receipt->id.'" class="text-blue-600 hover:text-blue-800" aria-label="Открыть приёмку"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>');}
    echo Component::render('table',array('caption'=>'Приёмки поставок','columns'=>array(array('label'=>'Приёмка'),array('label'=>'Документ поставщика','priority'=>2),array('label'=>'Принято','nowrap'=>TRUE),array('label'=>'Склад','priority'=>3),array('label'=>'Состояние'),array('label'=>'Действия','align'=>'right')),'rows'=>$table_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Приёмок не найдено','empty_hint'=>'Оформите приёмку из карточки заказа — непринятые позиции подставятся автоматически.'));
    ?>
</div>
