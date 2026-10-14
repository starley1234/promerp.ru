<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement" class="hover:text-blue-600">Закупки</a>
        <span class="mx-1">/</span>
        <a href="/procurement/receipts" class="hover:text-blue-600">Приёмки</a>
        <span class="mx-1">/</span>
        <span class="font-mono"><?php echo HTML::chars($receipt->number); ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono"><?php echo HTML::chars($receipt->number); ?></h1>
                <div class="mt-2 flex items-center gap-3 text-sm">
                    <span class="px-2 py-1 rounded <?php echo $receipt->status_class(); ?>"><?php echo HTML::chars($receipt->status_label()); ?></span>
                    <span class="text-gray-600 dark:text-gray-300">
                        Поставщик:
                        <?php if ($supplier): ?>
                            <a href="/procurement/suppliers/view/<?php echo (int) $supplier->id; ?>" class="text-blue-600 hover:text-blue-800"><?php echo HTML::chars($supplier->name); ?></a>
                        <?php else: ?>
                            #<?php echo (int) $receipt->supplier_id; ?>
                        <?php endif; ?>
                    </span>
                    <span class="text-gray-600 dark:text-gray-300">Принято <?php echo $receipt->received_at ? HTML::chars(date('d.m.Y', strtotime($receipt->received_at))) : '—'; ?></span>
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <?php if ($receipt->order_id AND $order): ?>
                        По заказу:
                        <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>" class="text-blue-600 hover:text-blue-800 font-mono"><?php echo HTML::chars($order->number); ?></a>
                    <?php elseif ($receipt->order_id): ?>
                        По заказу #<?php echo (int) $receipt->order_id; ?>
                    <?php else: ?>
                        Приёмка без заказа
                    <?php endif; ?>
                    <?php if ($receipt->doc_number): ?> · документ поставщика: <?php echo HTML::chars($receipt->doc_number); ?><?php endif; ?>
                    <?php if ($receipt->posted_at): ?> · проведена <?php echo HTML::chars(date('d.m.Y H:i', strtotime($receipt->posted_at))); ?><?php endif; ?>
                </div>
                <?php if ($receipt->note): ?>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300"><?php nl2br(HTML::chars((string) $receipt->note)); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($can_manage): ?>
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
                <?php if ($receipt->can_post() AND $can_approve): ?>
                    <form method="post" action="/procurement/receipts/act/post/<?php echo (int) $receipt->id; ?>" class="flex items-end gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Склад прихода</label>
                            <select name="warehouse_id" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ((int) $receipt->warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                                        <?php echo HTML::chars($warehouse->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                            <i class="fas fa-check-double mr-1"></i> Провести приёмку
                        </button>
                    </form>
                    <form method="post" action="/procurement/receipts/act/cancel/<?php echo (int) $receipt->id; ?>" class="flex items-end gap-2">
                        <input type="text" name="reason" placeholder="причина отмены"
                               class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50">
                            <i class="fas fa-ban mr-1"></i> Отменить
                        </button>
                    </form>
                <?php elseif ((string) $receipt->status === 'posted'): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Приход оформлен на складе. Обратное движение оформляется расходом в модуле склада (erp401) —
                        проведённый документ не отменяется.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Позиции -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-list mr-2 text-gray-500"></i> Позиции приёмки</h2>
        </div>

        <?php
        $receipt_rows=array();$total=0.0;foreach($lines as $line){$total+=$line->amount();$name='<span class="text-gray-900 dark:text-white">'.HTML::chars($line->name).'</span><br><span class="text-xs text-gray-500">'.($line->item_id?'#'.(int)$line->item_id.' · ':'').(!$line->item_id?'<span class="text-amber-600">без номенклатуры — приход не оформится</span>':($line->order_line_id?'строка заказа #'.(int)$line->order_line_id:'')).'</span>';if($receipt->is_editable() AND $can_manage){$quality='<form method="post" action="/procurement/receipts/act/quality/'.(int)$receipt->id.'" class="flex items-center gap-2"><input type="hidden" name="line_id" value="'.(int)$line->id.'"><select name="quality_status" class="rounded-md border px-2 py-1 text-xs dark:bg-gray-700 dark:text-gray-100">';foreach($quality_statuses as $key=>$label)$quality.='<option value="'.HTML::chars($key).'"'.((string)$line->quality_status===$key?' selected':'').'>'.HTML::chars($label).'</option>';$quality.='</select><button type="submit" class="text-xs text-blue-600 hover:text-blue-800">записать</button></form>';}else{$quality='<span class="'.($line->is_blocked()?'font-medium text-red-600':'text-gray-600 dark:text-gray-300').'">'.HTML::chars($line->quality_label()).'</span>';}$receipt_rows[]=array($name,(float)$line->qty.' '.HTML::chars($line->uom_code),number_format((float)$line->price,2,'.',' ').' ₽','<span class="font-medium">'.number_format($line->amount(),2,'.',' ').' ₽</span>',$quality);}
        echo Component::render('table',array('caption'=>'Позиции приёмки поставщика','columns'=>array(array('label'=>'Позиция'),array('label'=>'Количество','align'=>'right'),array('label'=>'Цена','align'=>'right','priority'=>2),array('label'=>'Сумма','align'=>'right'),array('label'=>'Контроль качества')),'rows'=>$receipt_rows,'row_header'=>TRUE));
        ?><div class="border-t border-gray-200 px-5 py-3 text-right text-sm font-semibold dark:border-gray-700">Итого по накладной: <?php echo number_format($total,2,'.',' '); ?> ₽</div>
    </div>
</div>
