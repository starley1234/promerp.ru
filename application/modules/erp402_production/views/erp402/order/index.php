<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-clipboard-list mr-2 text-blue-600"></i> Производственные заказы
        </h1>
        <a href="/production/orders/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Новый заказ
        </a>
    </div>

    <?php echo View::factory('erp402/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="get" action="/production/orders" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1" for="status">Статус</label>
            <select name="status" id="status" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                <option value="">Все</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo (isset($filters['status']) AND $filters['status'] === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1" for="term">Поиск</label>
            <input type="text" name="term" id="term" value="<?php echo HTML::chars(isset($filters['term']) ? (string) $filters['term'] : ''); ?>"
                   placeholder="номер, изделие, примечание"
                   class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
            <input type="checkbox" name="overdue" value="1" <?php echo ! empty($filters['overdue']) ? 'checked' : ''; ?>>
            только просроченные
        </label>
        <button type="submit" class="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
            <i class="fas fa-search mr-1"></i> Показать
        </button>
    </form>

    <?php
    $order_rows = array();
    foreach ($orders as $row)
    {
        $number = '<a href="/production/orders/view/'.(int) $row['id'].'" class="font-mono text-blue-600 hover:text-blue-800">'.HTML::chars($row['number']).'</a>';
        $output = number_format((float) $row['qty_done'],0,'.',' ').' / '.number_format((float) $row['qty'],0,'.',' ').' <span class="text-xs text-gray-500">'.HTML::chars($row['uom_code']).'</span>';
        $status = '<span class="rounded px-2 py-1 text-xs '.Model_Erp402_Order::$status_classes[$row['status']].'">'.HTML::chars(isset($statuses[$row['status']])?$statuses[$row['status']]:$row['status']).'</span>';
        $overdue = $row['target_date'] AND $row['target_date']<date('Y-m-d') AND ! in_array($row['status'],array('done','cancelled'),TRUE);
        $deadline = '<span class="'.($overdue?'text-red-600 dark:text-red-400':'text-gray-600 dark:text-gray-300').'">'.($row['target_date']?HTML::chars($row['target_date']):'—').'</span>';
        $actions = '<a href="/production/orders/view/'.(int) $row['id'].'" class="mr-3 text-blue-600 hover:text-blue-800" title="Карточка" aria-label="Открыть карточку"><i class="fas fa-eye" aria-hidden="true"></i></a>';
        if (in_array($row['status'],array('draft','planned','released'),TRUE)) $actions .= '<a href="/production/orders/edit/'.(int) $row['id'].'" class="text-gray-600 hover:text-gray-800 dark:text-gray-300" title="Редактировать" aria-label="Редактировать заказ"><i class="fas fa-edit" aria-hidden="true"></i></a>';
        $order_rows[] = array($number,HTML::chars($row['title']),$output,$status,HTML::chars(isset($priorities[(int)$row['priority']])?$priorities[(int)$row['priority']]:(string)$row['priority']),$deadline,$actions);
    }
    echo Component::render('table',array(
        'caption'=>'Производственные заказы',
        'columns'=>array(array('label'=>'Номер','nowrap'=>TRUE),array('label'=>'Изделие'),array('label'=>'Выпуск / план','align'=>'right','nowrap'=>TRUE),array('label'=>'Статус'),array('label'=>'Приоритет','priority'=>3),array('label'=>'Срок','priority'=>2,'nowrap'=>TRUE),array('label'=>'Действия','align'=>'right','nowrap'=>TRUE)),
        'rows'=>$order_rows,'row_header'=>TRUE,'sticky'=>TRUE,
        'empty_title'=>'Заказы не найдены','empty_hint'=>'Измените фильтр или создайте новый производственный заказ.',
    ));
    ?>

    <?php if ($pages > 1): ?>
        <?php
        $query = array_filter(array(
            'status'  => isset($filters['status']) ? $filters['status'] : NULL,
            'term'    => isset($filters['term']) ? $filters['term'] : NULL,
            'overdue' => ! empty($filters['overdue']) ? 1 : NULL,
        ), 'strlen');
        ?>
        <div class="flex items-center justify-between mt-4 text-sm text-gray-600 dark:text-gray-300">
            <div>Всего заказов: <?php echo (int) $total; ?></div>
            <div class="space-x-2">
                <?php if ($page > 1): ?>
                    <a class="text-blue-600 hover:text-blue-800" href="/production/orders?<?php echo http_build_query($query + array('page' => $page - 1)); ?>">← Назад</a>
                <?php endif; ?>
                <span>страница <?php echo (int) $page; ?> из <?php echo (int) $pages; ?></span>
                <?php if ($page < $pages): ?>
                    <a class="text-blue-600 hover:text-blue-800" href="/production/orders?<?php echo http_build_query($query + array('page' => $page + 1)); ?>">Вперёд →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
