<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-tasks mr-2 text-blue-600"></i> Операции цеха
        </h1>
        <div class="flex items-center gap-2 text-sm">
            <a href="/shopfloor" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-chart-pie mr-1"></i> Загрузка
            </a>
            <a href="/shopfloor/operations/edit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus mr-1"></i> Операция вручную
            </a>
        </div>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <form autocomplete="off" method="get" action="/shopfloor/operations" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Статус</label>
            <select name="status" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">Любой</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Рабочий центр</label>
            <select name="center_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="0">Все</option>
                <?php foreach ($centers as $center): ?>
                    <option value="<?php echo (int) $center['id']; ?>" <?php echo (int) $filters['center_id'] === (int) $center['id'] ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($center['code']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">День плана</label>
            <input type="date" name="day" value="<?php echo $filters['day'] ? HTML::chars($filters['day']) : ''; ?>"
                   class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>" placeholder="заказ или название"
                   class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
        </div>
        <div class="flex items-end gap-2">
            <label class="inline-flex items-center text-xs text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="open_only" value="1" <?php echo $filters['open_only'] ? 'checked' : ''; ?> class="mr-2">
                Только открытые
            </label>
            <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
            Найдено операций: <strong class="text-gray-900 dark:text-white"><?php echo (int) $total; ?></strong>
        </div>

        <?php if (empty($operations)): ?>
            <p class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">
                Операций нет. Разложите маршрут заказа на странице <a href="/shopfloor" class="text-blue-600 hover:text-blue-800">«Загрузка»</a>.
            </p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <?php
            $operation_rows = array();
            foreach ($operations as $operation)
            {
                $order = '<a href="/shopfloor/operations/view/'.(int) $operation['id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($operation['order_number']).'</a><div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(mb_substr((string) $operation['order_title'], 0, 40, 'UTF-8')).'</div>';
                $fact = number_format($operation['qty_planned'],0,'.',' ').' / <strong>'.number_format($operation['qty_ok'],0,'.',' ').'</strong><div class="ml-auto mt-1 h-1.5 w-24 rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-1.5 rounded-full bg-green-500" data-progress="'.(int) $operation['progress'].'"></div></div>';
                $time = number_format($operation['worked_hours'],1,'.',' ').' ч'.($operation['downtime_hours'] > 0 ? '<div class="text-xs text-orange-600">простой '.number_format($operation['downtime_hours'],1,'.',' ').' ч</div>' : '');
                $operation_rows[] = array((int)$operation['op_no'],$order,HTML::chars($operation['name']),$operation['work_center_label'] ? HTML::chars($operation['work_center_label']) : '— не назначен',HTML::chars((string)$operation['planned_day']),$fact,$time,'<span class="rounded-full px-2 py-1 text-xs '.HTML::chars($operation['status_class']).'">'.HTML::chars($operation['status_label']).'</span>');
            }
            echo Component::render('table',array('caption'=>'Операции цеха','sticky'=>TRUE,'columns'=>array(array('label'=>'Опер.'),array('label'=>'Заказ'),array('label'=>'Работа'),array('label'=>'Центр','priority'=>2),array('label'=>'День','priority'=>3),array('label'=>'План / факт','align'=>'right'),array('label'=>'Время','align'=>'right','priority'=>2),array('label'=>'Статус')),'rows'=>$operation_rows));
            ?>
            </div>

            <?php if ($pages > 1): ?>
                <?php
                $operation_url = function ($target_page) use ($filters) {
                    return '/shopfloor/operations?'.http_build_query(array_filter(array('status'=>$filters['status'],'center_id'=>$filters['center_id'] ?: NULL,'day'=>$filters['day'],'term'=>$filters['term'],'open_only'=>$filters['open_only'] ? 1 : NULL,'page'=>$target_page),function($value){ return $value !== NULL AND $value !== ''; }));
                };
                echo Component::render('pagination',array('page'=>(int)$page,'pages'=>(int)$pages,'url'=>$operation_url,'total'=>(int)$total));
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
