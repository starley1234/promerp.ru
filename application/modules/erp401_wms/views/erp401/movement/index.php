<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-exchange-alt mr-2 text-blue-600"></i> Журнал движений
        </h1>
        <a href="/warehouse/movements/create"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus-circle mr-2"></i> Новая операция
        </a>
    </div>

    <?php echo View::factory('erp401/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <!-- Фильтры -->
    <form method="get" action="/warehouse/movements"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="type" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Тип</label>
            <select id="type" name="type" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">Все типы</option>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>"
                        <?php echo (Arr::get($filters, 'type') === $key) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="warehouse_id" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Склад</label>
            <select id="warehouse_id" name="warehouse_id"
                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">Все склады</option>
                <?php foreach ($warehouses as $warehouse): ?>
                    <option value="<?php echo (int) $warehouse->id; ?>"
                        <?php echo ((string) Arr::get($filters, 'warehouse_id') === (string) $warehouse->id) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($warehouse->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="date_from" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">С</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo HTML::chars((string) Arr::get($filters, 'date_from')); ?>"
                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="date_to" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">По</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo HTML::chars((string) Arr::get($filters, 'date_to')); ?>"
                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <button type="submit"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
            <i class="fas fa-filter mr-1"></i> Применить
        </button>
    </form>

    <?php
    $movement_rows = array();
    $states = array('receipt'=>'ok','issue'=>'bad','transfer'=>'info','adjust'=>'neutral');
    foreach ($rows as $row)
    {
        $item = '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['item']['code']).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['item']['name']).'</span>';
        $route = HTML::chars($row['warehouse_name']);
        if ( ! empty($row['warehouse_to_name'])) $route .= ' <span class="mx-1 text-gray-400">&rarr;</span> '.HTML::chars($row['warehouse_to_name']);
        $qty = '<span class="font-semibold">'.number_format((float) $row['qty'],3,'.',' ').'</span> <span class="text-xs text-gray-500">'.HTML::chars($row['uom_code']).'</span>';
        $balance = number_format((float) $row['balance_before'],3,'.',' ').' <span class="mx-1 text-gray-400">&rarr;</span> '.number_format((float) $row['balance_after'],3,'.',' ');
        $basis = HTML::chars((string) $row['doc_type']).($row['doc_id'] ? '#'.(int) $row['doc_id'] : '');
        if ( ! empty($row['note'])) $basis .= '<br><span class="text-gray-400">'.HTML::chars($row['note']).'</span>';
        $movement_rows[] = array(
            (int) $row['id'],
            date('d.m.Y H:i',strtotime($row['performed_at'])),
            Component::render('badge',array('label'=>isset($types[$row['type']])?$types[$row['type']]:$row['type'],'state'=>isset($states[$row['type']])?$states[$row['type']]:'neutral')),
            $item,$route,$qty,$balance,$basis,
        );
    }
    Component::begin('card',array('title'=>'Движений: '.(int) $total,'icon'=>'fas fa-exchange-alt','flush'=>TRUE));
    echo Component::render('table',array(
        'caption'=>'Журнал складских движений',
        'columns'=>array(
            array('label'=>'#','priority'=>4),array('label'=>'Дата','nowrap'=>TRUE),array('label'=>'Тип'),
            array('label'=>'Номенклатура'),array('label'=>'Откуда → Куда','priority'=>2),
            array('label'=>'Количество','align'=>'right','nowrap'=>TRUE),array('label'=>'Остаток','align'=>'right','priority'=>3,'nowrap'=>TRUE),
            array('label'=>'Основание','priority'=>4),
        ),
        'rows'=>$movement_rows,'sticky'=>TRUE,
        'empty_title'=>'Движений по фильтру нет','empty_hint'=>'Измените период или склад либо зарегистрируйте новую операцию.',
    ));
    Component::end();
    ?>

    <?php if ($total > $limit): ?>
        <?php $pages = (int) ceil($total / $limit); ?>
        <div class="mt-4 flex justify-center space-x-1">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="?page=<?php echo $p; ?>&type=<?php echo urlencode((string) Arr::get($filters, 'type')); ?>&warehouse_id=<?php echo urlencode((string) Arr::get($filters, 'warehouse_id')); ?>"
                   class="px-3 py-1 rounded border text-sm <?php echo ($p === (int) $page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>
