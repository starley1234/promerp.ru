<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/procurement" class="hover:text-blue-600">Закупки</a>
        <span class="mx-1">/</span>
        <a href="/procurement/suppliers" class="hover:text-blue-600">Поставщики</a>
        <span class="mx-1">/</span>
        <span><?php echo HTML::chars($counterparty->name); ?></span>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Карточка -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 lg:col-span-1">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($counterparty->name); ?></h1>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                <?php if ($counterparty->inn): ?>ИНН <?php echo HTML::chars($counterparty->inn); ?><br><?php endif; ?>
                <?php if ($counterparty->city): ?><?php echo HTML::chars($counterparty->city); ?><br><?php endif; ?>
                <?php if ($counterparty->phone): ?><?php echo HTML::chars($counterparty->phone); ?><br><?php endif; ?>
                <?php if ($counterparty->email): ?><?php echo HTML::chars($counterparty->email); ?><?php endif; ?>
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Срок поставки</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo (int) $profile->lead_time(); ?> дн.</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Минимальная партия</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo number_format((float) $profile->min_order, 2, '.', ' '); ?> <?php echo HTML::chars($profile->currency); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Оценка</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo (int) $profile->rating; ?>/5 — <?php echo HTML::chars($profile->rating_label()); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Поставки в срок</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $on_time === NULL ? 'поставок ещё не было' : round($on_time).' %'; ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Отсрочка</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $profile->payment_terms ? HTML::chars($profile->payment_terms) : '—'; ?></dd>
                </div>
            </dl>
            <?php if ($profile->is_preferred()): ?>
                <div class="mt-3 px-3 py-2 rounded bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300 text-sm">
                    Предпочтительный поставщик: автоподбор ставит его выше при равной цене и сроке.
                </div>
            <?php endif; ?>
        </div>

        <!-- Условия -->
        <?php if ($can_manage): ?>
            <form method="post" action="/procurement/suppliers/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 lg:col-span-2 space-y-4">
                <input type="hidden" name="supplier_id" value="<?php echo (int) $counterparty->id; ?>">
                <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-sliders-h mr-2 text-gray-500"></i> Закупочные условия</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Срок поставки, дней</label>
                        <input type="number" name="lead_time_days" min="0" value="<?php echo (int) $profile->lead_time_days; ?>"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Минимальная партия</label>
                        <input type="number" step="0.01" name="min_order" min="0" value="<?php echo (float) $profile->min_order; ?>"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Валюта</label>
                        <select name="currency" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                            <?php foreach (Model_Erp403_Price::$currencies as $code => $sign): ?>
                                <option value="<?php echo HTML::chars($code); ?>" <?php echo ((string) $profile->currency === $code) ? 'selected' : ''; ?>>
                                    <?php echo HTML::chars($code.' '.$sign); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Оценка</label>
                        <select name="rating" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                            <?php foreach (Model_Erp403_Supplier::$ratings as $value => $label): ?>
                                <option value="<?php echo (int) $value; ?>" <?php echo ((int) $profile->rating === (int) $value) ? 'selected' : ''; ?>>
                                    <?php echo (int) $value; ?>/5 — <?php echo HTML::chars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Отсрочка / оплата</label>
                        <input type="text" name="payment_terms" value="<?php echo HTML::chars((string) $profile->payment_terms); ?>"
                               class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div class="flex items-end gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_preferred" value="1" <?php echo $profile->is_preferred() ? 'checked' : ''; ?>>
                            предпочтительный
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_active" value="1" <?php echo $profile->is_active() ? 'checked' : ''; ?>>
                            действует
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Заметка закупщика</label>
                    <textarea name="note" rows="2" class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"><?php echo HTML::chars((string) $profile->note); ?></textarea>
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Сохранить условия
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Заказы поставщика -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-file-signature mr-2 text-gray-500"></i> Последние заказы</h2>
        </div>
        <?php if (empty($orders)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Заказов этому поставщику ещё не было.</div>
        <?php else: ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <?php foreach ($orders as $order): ?>
                    <li class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="/procurement/orders/view/<?php echo (int) $order->id; ?>" class="font-mono text-blue-600 hover:text-blue-800"><?php echo HTML::chars($order->number); ?></a>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                поставка <?php echo $order->delivery_date ? HTML::chars(date('d.m.Y', strtotime($order->delivery_date))) : '—'; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-gray-900 dark:text-white"><?php echo number_format((float) $order->amount, 2, '.', ' '); ?> <?php echo HTML::chars($order->currency); ?></div>
                            <span class="px-2 py-1 rounded text-xs <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- История цен -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-tags mr-2 text-gray-500"></i> История цен</h2>
        </div>
        <?php if (empty($prices)): ?>
            <div class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">
                Цен ещё нет: история наполняется отправленными заказами и проведёнными приёмками.
            </div>
        <?php else: ?>
            <?php
            $price_rows=array();foreach($prices as $price)$price_rows[]=array(HTML::chars(date('d.m.Y',strtotime($price['dated_at']))),HTML::chars($price['name']),number_format((float)$price['price'],2,'.',' ').' '.HTML::chars($price['currency']),(float)$price['qty'].' '.HTML::chars($price['uom_code']),HTML::chars(isset(Model_Erp403_Price::$sources[$price['source']])?Model_Erp403_Price::$sources[$price['source']]:$price['source']));
            echo Component::render('table',array('caption'=>'История цен поставщика','columns'=>array(array('label'=>'Дата','nowrap'=>TRUE),array('label'=>'Позиция'),array('label'=>'Цена','align'=>'right'),array('label'=>'Количество','align'=>'right','priority'=>2),array('label'=>'Источник','priority'=>3)),'rows'=>$price_rows,'row_header'=>FALSE));
            ?>
        <?php endif; ?>
    </div>
</div>
