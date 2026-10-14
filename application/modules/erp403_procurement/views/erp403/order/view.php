<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement" class="hover:text-blue-600">Закупки</a>
        <span class="mx-1">/</span>
        <a href="/procurement/orders" class="hover:text-blue-600">Заказы</a>
        <span class="mx-1">/</span>
        <span class="font-mono"><?php echo HTML::chars($order->number); ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono"><?php echo HTML::chars($order->number); ?></h1>
                <div class="mt-2 flex items-center gap-3 text-sm">
                    <span class="px-2 py-1 rounded <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
                    <span class="text-gray-600 dark:text-gray-300">
                        Поставщик:
                        <?php if ($supplier): ?>
                            <a href="/procurement/suppliers/view/<?php echo (int) $supplier->id; ?>" class="text-blue-600 hover:text-blue-800"><?php echo HTML::chars($supplier->name); ?></a>
                        <?php else: ?>
                            #<?php echo (int) $order->supplier_id; ?>
                        <?php endif; ?>
                        <?php if ($supplier_profile): ?>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(срок <?php echo (int) $supplier_profile->lead_time(); ?> дн., оценка <?php echo (int) $supplier_profile->rating; ?>/5)</span>
                        <?php endif; ?>
                    </span>
                    <span class="text-gray-600 dark:text-gray-300">Поставка <?php echo $order->delivery_date ? HTML::chars(date('d.m.Y', strtotime($order->delivery_date))) : '—'; ?></span>
                    <?php if ($order->is_overdue()): ?>
                        <span class="text-red-600 font-medium">просрочена</span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Сумма: <span class="font-medium text-gray-900 dark:text-white"><?php echo number_format((float) $order->amount, 2, '.', ' '); ?> <?php echo HTML::chars($order->currency); ?></span>
                    <?php if ($supplier_profile AND $supplier_profile->payment_terms): ?> · <?php echo HTML::chars($supplier_profile->payment_terms); ?><?php endif; ?>
                    <?php if ($order->sent_at): ?> · отправлен <?php echo HTML::chars(date('d.m.Y H:i', strtotime($order->sent_at))); ?><?php endif; ?>
                </div>
                <?php if ($request): ?>
                    <div class="mt-2 text-sm">
                        Из заявки:
                        <a href="/procurement/requests/view/<?php echo (int) $request->id; ?>" class="text-blue-600 hover:text-blue-800 font-mono"><?php echo HTML::chars($request->number); ?></a>
                    </div>
                <?php endif; ?>
                <?php if ($order->note): ?>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300"><?php echo nl2br(HTML::chars((string) $order->note)); ?></p>
                <?php endif; ?>
            </div>

            <!-- Прогресс приёмки -->
            <div class="w-56">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Принято: <?php echo (float) $progress['qty_received']; ?> из <?php echo (float) $progress['qty']; ?></div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" data-progress="<?php echo (int) $progress['progress']; ?>"></div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo (int) $progress['progress']; ?> % · позиций <?php echo (int) $progress['lines']; ?></div>
            </div>
        </div>

        <?php if ($can_manage OR $can_approve): ?>
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
                <?php if ($order->is_editable()): ?>
                    <a href="/procurement/orders/edit/<?php echo (int) $order->id; ?>"
                       class="px-4 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50">
                        <i class="fas fa-pen mr-1"></i> Править
                    </a>
                    <form method="post" action="/procurement/orders/act/send/<?php echo (int) $order->id; ?>">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-1"></i> Отправить поставщику
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ((string) $order->status === 'sent'): ?>
                    <form method="post" action="/procurement/orders/act/confirm/<?php echo (int) $order->id; ?>">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i> Поставщик подтвердил
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_approve AND ! $order->is_closed()): ?>
                    <form method="post" action="/procurement/orders/act/receipt/<?php echo (int) $order->id; ?>" class="flex items-center gap-2">
                        <input type="text" name="doc_number" placeholder="накладная поставщика"
                               class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <input type="date" name="received_at" value="<?php echo date('Y-m-d'); ?>"
                               class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            <i class="fas fa-truck-loading mr-1"></i> Оформить приёмку
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_manage AND ! $order->is_closed()): ?>
                    <form method="post" action="/procurement/orders/act/cancel/<?php echo (int) $order->id; ?>" class="flex items-center gap-2">
                        <input type="text" name="reason" placeholder="причина отмены"
                               class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
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
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-list mr-2 text-gray-500"></i> Позиции заказа</h2>
        </div>
        <?php
        $line_rows=array();foreach($lines as $line){$name='<span class="text-gray-900 dark:text-white">'.HTML::chars($line->name).'</span><br><span class="text-xs text-gray-500">'.($line->code?HTML::chars($line->code):'').($line->item_id?' · #'.(int)$line->item_id:'').($line->note?' · '.HTML::chars($line->note):'').'</span>';$state=HTML::chars($line->status_label()).'<div class="mt-1 h-1.5 w-24 rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-1.5 rounded-full bg-blue-500" data-progress="'.(int)$line->progress().'"></div></div>';$line_rows[]=array($name,(float)$line->qty.' '.HTML::chars($line->uom_code),(float)$line->qty_received,number_format((float)$line->price,2,'.',' ').' ₽','<span class="font-medium">'.number_format($line->amount(),2,'.',' ').' ₽</span>',$state);}
        echo Component::render('table',array('caption'=>'Позиции заказа поставщику','columns'=>array(array('label'=>'Позиция'),array('label'=>'Заказано','align'=>'right'),array('label'=>'Принято','align'=>'right'),array('label'=>'Цена','align'=>'right','priority'=>2),array('label'=>'Сумма','align'=>'right'),array('label'=>'Состояние')),'rows'=>$line_rows,'row_header'=>TRUE));
        ?>
    </div>

    <!-- Приёмки -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-truck-loading mr-2 text-gray-500"></i> Приёмки по заказу</h2>
            <a href="/procurement/receipts" class="text-sm text-blue-600 hover:text-blue-800">все приёмки</a>
        </div>
        <?php if (empty($receipts)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Приёмок ещё нет. Кнопка «Оформить приёмку» создаст документ с тем, что заказ ещё не принял;
                после проведения приход уйдёт на склад (erp401).
            </div>
        <?php else: ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <?php foreach ($receipts as $receipt): ?>
                    <li class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="/procurement/receipts/view/<?php echo (int) $receipt->id; ?>" class="font-mono text-blue-600 hover:text-blue-800">
                                <?php echo HTML::chars($receipt->number); ?>
                            </a>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo $receipt->doc_number ? 'документ '.HTML::chars($receipt->doc_number).' · ' : ''; ?>
                                <?php echo $receipt->received_at ? HTML::chars(date('d.m.Y', strtotime($receipt->received_at))) : ''; ?>
                            </div>
                        </div>
                        <span class="px-2 py-1 rounded text-xs <?php echo $receipt->status_class(); ?>"><?php echo HTML::chars($receipt->status_label()); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
