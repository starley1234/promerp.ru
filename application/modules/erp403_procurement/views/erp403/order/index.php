<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-signature mr-2 text-blue-600"></i> Заказы поставщикам
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · в работе <?php echo (int) $stats['open']; ?> на
                <?php echo number_format((float) $stats['amount_open'], 0, '.', ' '); ?> ₽
                <?php if ((int) $stats['overdue']): ?>
                    · <span class="text-red-600 font-medium">просрочено <?php echo (int) $stats['overdue']; ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/procurement/orders/edit"
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i> Новый заказ
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/procurement/orders" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="search" value="<?php echo HTML::chars((string) $filters['search']); ?>"
                   placeholder="номер заказа или позиция"
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
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 pb-2">
            <input type="checkbox" name="overdue" value="1" <?php echo $filters['overdue'] ? 'checked' : ''; ?>>
            только просроченные
        </label>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Найти
        </button>
    </form>

    <?php
    $table_rows=array();foreach($rows as $order){$name='<a href="/procurement/orders/view/'.(int)$order->id.'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($order->number).'</a>'.($order->note?'<br><span class="text-xs text-gray-500">'.HTML::chars(mb_substr((string)$order->note,0,80)).'</span>':'');$delivery=($order->delivery_date?HTML::chars(date('d.m.Y',strtotime($order->delivery_date))):'—').($order->is_overdue()?'<br><span class="text-xs font-medium text-red-600">просрочена</span>':($order->days_left()!==NULL?'<br><span class="text-xs text-gray-500">через '.(int)$order->days_left().' дн.</span>':''));$table_rows[]=array($name,'#'.(int)$order->supplier_id,$delivery,number_format((float)$order->amount,2,'.',' ').' '.HTML::chars($order->currency),'<span class="rounded px-2 py-1 text-xs '.$order->status_class().'">'.HTML::chars($order->status_label()).'</span>','<a href="/procurement/orders/view/'.(int)$order->id.'" class="text-blue-600 hover:text-blue-800" aria-label="Открыть заказ"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>');}
    echo Component::render('table',array('caption'=>'Заказы поставщикам','columns'=>array(array('label'=>'Заказ'),array('label'=>'Поставщик','priority'=>3),array('label'=>'Поставка','nowrap'=>TRUE),array('label'=>'Сумма','align'=>'right','nowrap'=>TRUE),array('label'=>'Состояние'),array('label'=>'Действия','align'=>'right')),'rows'=>$table_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Заказов не найдено','empty_hint'=>'Соберите новый заказ из согласованных заявок.'));
    ?>
</div>
