<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement" class="hover:text-blue-600">Закупки</a>
        <span class="mx-1">/</span>
        <a href="/procurement/requests" class="hover:text-blue-600">Заявки</a>
        <span class="mx-1">/</span>
        <span class="font-mono"><?php echo HTML::chars($request->number); ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono"><?php echo HTML::chars($request->number); ?></h1>
                <div class="mt-2 flex items-center gap-3 text-sm">
                    <span class="px-2 py-1 rounded <?php echo $request->status_class(); ?>"><?php echo HTML::chars($request->status_label()); ?></span>
                    <span class="text-gray-600 dark:text-gray-300">Приоритет: <?php echo HTML::chars($request->priority_label()); ?></span>
                    <span class="text-gray-600 dark:text-gray-300">Нужен к <?php echo $request->need_date ? HTML::chars(date('d.m.Y', strtotime($request->need_date))) : '—'; ?></span>
                    <?php if ($request->is_overdue()): ?>
                        <span class="text-red-600 font-medium">срок прошёл</span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Источник: <?php echo HTML::chars($request->source_label()); ?>
                    <?php if ($request->source_ref): ?> · <?php echo HTML::chars($request->source_ref); ?><?php endif; ?>
                    <?php if ($request->warehouse_id): ?> · склад #<?php echo (int) $request->warehouse_id; ?><?php endif; ?>
                </div>
                <?php if ($request->note): ?>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300"><?php echo nl2br(HTML::chars((string) $request->note)); ?></p>
                <?php endif; ?>
            </div>
            <div class="flex flex-col gap-2 items-end">
                <?php if ($order): ?>
                    <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>"
                       class="inline-flex items-center px-3 py-2 text-sm rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300">
                        <i class="fas fa-file-signature mr-2"></i> Заказ <?php echo HTML::chars($order->number); ?>
                    </a>
                <?php endif; ?>
                <?php if ($can_manage AND $request->is_editable()): ?>
                    <a href="/procurement/requests/edit/<?php echo (int) $request->id; ?>"
                       class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50">
                        <i class="fas fa-pen mr-2"></i> Править
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($can_manage OR $can_approve): ?>
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
                <?php if ($request->is_editable()): ?>
                    <form method="post" action="/procurement/requests/act/submit/<?php echo (int) $request->id; ?>">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-1"></i> На согласование
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_approve AND $request->can_decide()): ?>
                    <form method="post" action="/procurement/requests/act/approve/<?php echo (int) $request->id; ?>">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i> Согласовать
                        </button>
                    </form>
                    <form method="post" action="/procurement/requests/act/reject/<?php echo (int) $request->id; ?>" class="flex items-center gap-2">
                        <input type="text" name="reason" placeholder="причина отклонения"
                               class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-red-600 text-white hover:bg-red-700">
                            <i class="fas fa-times mr-1"></i> Отклонить
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_manage AND $request->can_order()): ?>
                    <form method="post" action="/procurement/requests/act/to_order/<?php echo (int) $request->id; ?>" class="flex items-center gap-2">
                        <select name="supplier_id" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                            <option value="">поставщик — по цене и сроку</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int) $supplier['id']; ?>"><?php echo HTML::chars($supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            <i class="fas fa-file-signature mr-1"></i> Оформить заказ
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_manage AND $request->status !== 'cancelled' AND $request->status !== 'ordered'): ?>
                    <form method="post" action="/procurement/requests/act/cancel/<?php echo (int) $request->id; ?>">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50">
                            <i class="fas fa-ban mr-1"></i> Отменить
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Позиции -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-list mr-2 text-gray-500"></i> Позиции</h2>
        </div>

        <?php if (empty($lines)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Позиций нет — добавьте то, что нужно купить.</div>
        <?php else: ?>
            <?php
            $request_line_rows=array();$total_amount=0.0;foreach($lines as $line){$total_amount+=$line->amount();$name='<span class="text-gray-900 dark:text-white">'.HTML::chars($line->name).'</span><br><span class="text-xs text-gray-500">'.($line->code?HTML::chars($line->code):'').($line->item_id?' · #'.(int)$line->item_id:'').'</span>';$cells=array($name,(float)$line->qty.' '.HTML::chars($line->uom_code),number_format((float)$line->price,2,'.',' ').' ₽','<span class="font-medium">'.number_format($line->amount(),2,'.',' ').' ₽</span>',$line->supplier_id?'#'.(int)$line->supplier_id:'—',HTML::chars($line->status_label()));if($can_manage AND $request->is_editable())$cells[]='<form method="post" action="/procurement/requests/line/remove/'.(int)$line->id.'"><input type="hidden" name="request_id" value="'.(int)$request->id.'"><button type="submit" class="text-red-600 hover:text-red-800" aria-label="Удалить позицию"><i class="fas fa-trash" aria-hidden="true"></i></button></form>';$request_line_rows[]=$cells;}$columns=array(array('label'=>'Позиция'),array('label'=>'Количество','align'=>'right'),array('label'=>'Оценка цены','align'=>'right','priority'=>2),array('label'=>'Сумма','align'=>'right'),array('label'=>'Поставщик','priority'=>3),array('label'=>'Состояние'));if($can_manage AND $request->is_editable())$columns[]=array('label'=>'Действия','align'=>'right');
            echo Component::render('table',array('caption'=>'Позиции заявки на закупку','columns'=>$columns,'rows'=>$request_line_rows,'row_header'=>TRUE));
            ?><div class="border-t border-gray-200 px-5 py-3 text-right text-sm font-semibold dark:border-gray-700">Итого (оценка): <?php echo number_format($total_amount,2,'.',' '); ?> ₽</div>
        <?php endif; ?>

        <?php if ($can_manage AND $request->is_editable()): ?>
            <form method="post" action="/procurement/requests/line/add" autocomplete="off" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-7 gap-3 items-end">
                <input type="hidden" name="request_id" value="<?php echo (int) $request->id; ?>">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Позиция</label>
                    <input type="text" name="name" required placeholder="что купить"
                           class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div class="md:col-span-2"><?php echo Component::render('smart_reference',array('id'=>'request_line_item','name'=>'item_id','label'=>'Номенклатура','url'=>'/procurement/suppliers/reference','placeholder'=>'Код или название','hint'=>'ID подставится после выбора.')); ?></div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество</label>
                    <input type="number" step="0.001" name="qty" value="1" min="0"
                           class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Цена, ₽</label>
                    <input type="number" step="0.01" name="price" value="0"
                           class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-1"></i> Добавить
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
