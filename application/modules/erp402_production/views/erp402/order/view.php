<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <a href="/production/orders" class="hover:text-blue-600">Заказы</a> /
                <span class="font-mono"><?php echo HTML::chars($order->number); ?></span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($order->title); ?></h1>
            <div class="mt-2 flex items-center gap-3">
                <span class="px-2 py-1 rounded text-xs <?php echo $order->status_class(); ?>"><?php echo HTML::chars($order->status_label()); ?></span>
                <?php if ($order->target_date): ?>
                    <span class="text-sm <?php echo ( ! $order->is_editable() OR $order->target_date >= date('Y-m-d')) ? 'text-gray-600 dark:text-gray-300' : 'text-red-600 dark:text-red-400'; ?>">
                        срок: <?php echo HTML::chars($order->target_date); ?>
                    </span>
                <?php endif; ?>
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    <?php echo HTML::chars(isset(Model_Erp402_Order::$priorities[(int) $order->priority]) ? Model_Erp402_Order::$priorities[(int) $order->priority] : ''); ?> приоритет
                </span>
                <a href="/audit/journal/entity?entity_type=order&amp;entity_id=<?php echo urlencode($order->uuid); ?>"
                   class="text-sm text-blue-600 hover:text-blue-800" title="История изменений заказа">
                    <i class="fas fa-history mr-1"></i> История
                </a>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($can_manage AND $order->is_editable()): ?>
                <a href="/production/orders/edit/<?php echo (int) $order->id; ?>"
                   class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-edit mr-1"></i> Изменить
                </a>
            <?php endif; ?>

            <?php if ($can_manage AND $order->status === 'draft'): ?>
                <form method="post" action="/production/orders/fill" class="inline">
                    <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
                    <button type="submit" class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-file-import mr-1"></i> Состав из техкарты
                    </button>
                </form>
            <?php endif; ?>

            <?php foreach ($transitions as $action => $label): ?>
                <?php
                $allowed = in_array($action, array('start', 'complete'), TRUE) ? $can_complete : $can_plan;
                if ( ! $allowed) { continue; }
                $primary = in_array($action, array('release', 'complete'), TRUE);
                ?>
                <a href="/production/orders/<?php echo HTML::chars($action); ?>/<?php echo (int) $order->id; ?>"
                   class="px-3 py-2 text-sm rounded-md <?php echo $primary
                        ? 'text-white bg-blue-600 hover:bg-blue-700'
                        : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    <?php echo HTML::chars($label); ?>
                </a>
            <?php endforeach; ?>

            <?php if ($can_manage AND in_array($order->status, array('draft', 'cancelled'), TRUE)): ?>
                <a href="/production/orders/delete/<?php echo (int) $order->id; ?>"
                   data-confirm="Удалить заказ <?php echo HTML::chars($order->number); ?>?"
                   class="px-3 py-2 text-sm rounded-md text-red-600 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <i class="fas fa-trash"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp402/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Выполнение заказа</span>
                <span class="font-semibold text-gray-900 dark:text-white">
                    <?php echo number_format((float) $order->qty_done, 0, '.', ' '); ?> / <?php echo number_format((float) $order->qty, 0, '.', ' '); ?>
                    <?php echo HTML::chars($order->uom_code); ?> (<?php echo $progress; ?>%)
                </span>
            </div>
            <div class="w-full h-3 bg-gray-200 dark:bg-gray-700 rounded">
                <div class="h-3 rounded bg-blue-600" data-progress="<?php echo (float) $progress; ?>"></div>
            </div>

            <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-5 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Изделие (номенклатура)</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $order->item_id ? '#'.(int) $order->item_id : '—'; ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Технологическая карта</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $order->tech_card_id ? '#'.(int) $order->tech_card_id : '—'; ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Склад материалов</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $order->warehouse_id ? '#'.(int) $order->warehouse_id : '—'; ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Склад готовой продукции</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $order->output_warehouse_id ? '#'.(int) $order->output_warehouse_id : '—'; ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Заказчик</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo $order->customer_id ? '#'.(int) $order->customer_id : '—'; ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Начало / завершение</dt>
                    <dd class="text-gray-900 dark:text-white">
                        <?php echo $order->started_at ? HTML::chars(substr((string) $order->started_at, 0, 16)) : '—'; ?>
                        /
                        <?php echo $order->finished_at ? HTML::chars(substr((string) $order->finished_at, 0, 16)) : '—'; ?>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Обеспеченность материалами</h2>
            <?php if ($summary === NULL): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">Склад недоступен — потребность не рассчитана.</p>
            <?php else: ?>
                <ul class="text-sm space-y-2">
                    <li class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Строк в составе</span><span class="text-gray-900 dark:text-white"><?php echo (int) $summary['total']; ?></span></li>
                    <li class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Обеспечено</span><span class="text-green-600 dark:text-green-400"><?php echo (int) $summary['ready']; ?></span></li>
                    <li class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Не хватает</span><span class="<?php echo $summary['short'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'; ?>"><?php echo (int) $summary['short']; ?></span></li>
                    <li class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Без номенклатуры</span><span class="<?php echo $summary['unmapped'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-white'; ?>"><?php echo (int) $summary['unmapped']; ?></span></li>
                </ul>
            <?php endif; ?>

            <?php if ($order->note): ?>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mt-5 mb-2">Примечание</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line"><?php echo HTML::chars($order->note); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $material_rows = array();
    foreach ($items as $index=>$item)
    {
        $requirement = isset($requirements[$index])?$requirements[$index]:NULL;
        $material = '<span class="text-gray-900 dark:text-white">'.HTML::chars($item->name).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">';
        $material .= $item->item_id?'номенклатура #'.(int)$item->item_id:'<span class="text-yellow-600 dark:text-yellow-400">номенклатура не сопоставлена</span>';
        if ($item->note) $material .= ' · '.HTML::chars($item->note);
        $material .= '</span>';
        $available = '<span class="text-gray-400">—</span>';
        if ($requirement AND $requirement['have']!==NULL)
        {
            $available = number_format($requirement['have'],3,'.',' ');
            if ($requirement['shortage']>0) $available .= '<br><span class="text-xs text-red-600 dark:text-red-400">не хватает '.number_format($requirement['shortage'],3,'.',' ').'</span>';
        }
        $actions = '';
        if ($can_manage AND ($order->status==='draft' OR $order->status==='planned')) $actions = '<a href="/production/orders/item/delete/'.(int)$order->id.'/'.(int)$item->id.'" data-confirm="Удалить строку &quot;'.HTML::chars($item->name).'&quot;?" class="text-red-600 hover:text-red-800" title="Удалить строку" aria-label="Удалить строку"><i class="fas fa-times" aria-hidden="true"></i></a>';
        $cells = array($material,number_format((float)$item->qty_per_unit,3,'.',' ').' '.HTML::chars($item->uom_code),number_format((float)$item->qty_total,3,'.',' '),number_format((float)$item->qty_issued,3,'.',' '),$available,'<span class="rounded px-2 py-1 text-xs '.$item->status_class().'">'.HTML::chars($item->status_label()).'</span>');
        if ($can_manage) $cells[]=$actions;
        $material_rows[]=$cells;
    }
    $material_columns = array(array('label'=>'Материал'),array('label'=>'Норма','align'=>'right','nowrap'=>TRUE),array('label'=>'Потребность','align'=>'right','nowrap'=>TRUE),array('label'=>'Списано','align'=>'right','priority'=>3,'nowrap'=>TRUE),array('label'=>'Наличие','align'=>'right','priority'=>2,'nowrap'=>TRUE),array('label'=>'Статус'));
    if ($can_manage) $material_columns[]=array('label'=>'Действия','align'=>'right','nowrap'=>TRUE);
    Component::begin('card',array('title'=>'Состав заказа (потребность в материалах)','icon'=>'fas fa-list-check','flush'=>TRUE));
    echo Component::render('table',array('caption'=>'Потребность производственного заказа в материалах','columns'=>$material_columns,'rows'=>$material_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Состав заказа пуст','empty_hint'=>'Заполните его из технологической карты или добавьте строки вручную.'));
    Component::end();
    ?>

    <?php if ($can_manage AND in_array($order->status, array('draft', 'planned'), TRUE)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Добавить строку состава</h2>
            <form method="post" action="/production/orders/item/save" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Номенклатура</label>
                    <select name="item_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                        <option value="">— не выбрана —</option>
                        <?php foreach ($catalog as $position): ?>
                            <option value="<?php echo (int) $position['id']; ?>">
                                <?php echo HTML::chars($position['code'].' · '.$position['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Наименование материала</label>
                    <input type="text" name="name" required maxlength="255"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Норма на изделие</label>
                    <input type="number" name="qty_per_unit" step="0.000001" min="0" required value="1"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Склад</label>
                    <select name="warehouse_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                        <option value="">— как в заказе —</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo (int) $warehouse->id; ?>"><?php echo HTML::chars($warehouse->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-6">
                    <button type="submit" class="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-plus mr-1"></i> Добавить строку
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
