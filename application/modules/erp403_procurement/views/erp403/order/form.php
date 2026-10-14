<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement/orders" class="hover:text-blue-600">Заказы</a>
        <span class="mx-1">/</span>
        <span><?php echo $order ? 'Правка '.HTML::chars($order->number) : 'Новый заказ'; ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/procurement/orders/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
        <?php if ($order): ?>
            <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
        <?php elseif ($request_id): ?>
            <input type="hidden" name="request_id" value="<?php echo (int) $request_id; ?>">
        <?php endif; ?>

        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
            <?php echo $order ? 'Заказ '.HTML::chars($order->number) : 'Новый заказ поставщику'; ?>
        </h1>

        <?php if ($request_id AND ! $order): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Заказ собирается из согласованной заявки: поставщик берётся из позиций (или подбирается по цене и сроку),
                цены — последние известные.
            </p>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php if ( ! $request_id OR $order): ?>
                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Поставщик</label>
                    <select name="supplier_id" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <option value="">выберите поставщика</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int) $supplier['id']; ?>" <?php echo ($order AND (int) $order->supplier_id === (int) $supplier['id']) ? 'selected' : ''; ?>>
                                <?php echo HTML::chars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Срок поставки</label>
                <input type="date" name="delivery_date" value="<?php echo HTML::chars($order ? (string) $order->delivery_date : date('Y-m-d', strtotime('+7 days'))); ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Склад приёмки</label>
                <select name="warehouse_id" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <option value="">склад по умолчанию</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ($order AND (int) $order->warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Валюта</label>
                <select name="currency" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    <?php foreach (Model_Erp403_Price::$currencies as $code => $sign): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo ($order AND (string) $order->currency === $code) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($code.' '.$sign); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Условия оплаты</label>
                <input type="text" name="payment_terms" value="<?php echo HTML::chars($order ? (string) $order->payment_terms : ''); ?>"
                       placeholder="например, 50 % предоплата"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Пояснение</label>
            <textarea name="note" rows="2" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"><?php echo HTML::chars($order ? (string) $order->note : ''); ?></textarea>
        </div>

        <?php if ( ! $order AND ! $request_id): ?>
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Первая позиция</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Позиция</label>
                        <input type="text" name="lines[0][name]" required placeholder="что заказываем"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Номенклатура (id)</label>
                        <input type="number" name="lines[0][item_id]" placeholder="из реестра"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Количество</label>
                        <input type="number" step="0.001" name="lines[0][qty]" value="1" min="0"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Цена, ₽</label>
                        <input type="number" step="0.01" name="lines[0][price]" value="0"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Остальные позиции добавляются в карточке заказа. Пустая цена подставится из истории цен поставщика.
                </p>
            </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
            <a href="<?php echo $order ? '/procurement/orders/view/'.(int) $order->id : '/procurement/orders'; ?>"
               class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Отмена</a>
        </div>
    </form>

    <?php if ($order AND ! empty($lines)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mt-6">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-list mr-2 text-gray-500"></i> Позиции</h2>
            </div>
            <?php
            $line_rows=array();foreach($lines as $line)$line_rows[]=array(HTML::chars($line->name),(float)$line->qty.' '.HTML::chars($line->uom_code),number_format((float)$line->price,2,'.',' ').' ₽','<span class="font-medium">'.number_format($line->amount(),2,'.',' ').' ₽</span>','<form method="post" action="/procurement/orders/line/remove/'.(int)$line->id.'"><input type="hidden" name="order_id" value="'.(int)$order->id.'"><button type="submit" class="text-red-600 hover:text-red-800" title="Удалить позицию" aria-label="Удалить позицию"><i class="fas fa-trash" aria-hidden="true"></i></button></form>');
            echo Component::render('table',array('caption'=>'Позиции заказа поставщику','columns'=>array(array('label'=>'Позиция'),array('label'=>'Количество','align'=>'right'),array('label'=>'Цена','align'=>'right'),array('label'=>'Сумма','align'=>'right'),array('label'=>'Действия','align'=>'right')),'rows'=>$line_rows,'row_header'=>TRUE));
            ?>

            <form method="post" action="/procurement/orders/line/add" autocomplete="off" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-7 gap-3 items-end">
                <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Позиция</label>
                    <input type="text" name="name" required placeholder="что заказываем"
                           class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div class="md:col-span-2"><?php echo Component::render('smart_reference',array('id'=>'order_line_item','name'=>'item_id','label'=>'Номенклатура','url'=>'/procurement/suppliers/reference','placeholder'=>'Код или название','hint'=>'ID подставится после выбора.')); ?></div>
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
        </div>
    <?php endif; ?>
</div>
