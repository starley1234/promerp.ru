<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <i class="fas fa-lock mr-2 text-blue-600"></i> Резервы номенклатуры
    </h1>

    <?php echo View::factory('erp401/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <!-- Фильтр -->
    <form method="get" action="/warehouse/reservations"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="status" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Статус</label>
            <select id="status" name="status" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">Все</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>"
                        <?php echo (Arr::get($filters, 'status') === $key) ? 'selected' : ''; ?>>
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

        <button type="submit"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
            <i class="fas fa-filter mr-1"></i> Применить
        </button>
    </form>

    <!-- Создание резерва -->
    <form method="post" action="/warehouse/reservations/create"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="r-warehouse" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Склад</label>
            <select id="r-warehouse" name="warehouse_id" required
                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <?php foreach ($warehouses as $warehouse): ?>
                    <option value="<?php echo (int) $warehouse->id; ?>"><?php echo HTML::chars($warehouse->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="r-item" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Номенклатура</label>
            <select id="r-item" name="item_id" required
                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <?php foreach ($catalog as $entry): ?>
                    <option value="<?php echo (int) $entry['id']; ?>"><?php echo HTML::chars($entry['code'].' — '.$entry['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="r-qty" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Количество</label>
            <input type="number" id="r-qty" name="qty" step="0.001" min="0.001" required
                   class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="r-ref" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Документ-потребитель</label>
            <input type="text" id="r-ref" name="ref_type" placeholder="order / production"
                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="r-ref-id" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Номер документа</label>
            <input type="number" id="r-ref-id" name="ref_id"
                   class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="r-exp" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Действует до</label>
            <input type="date" id="r-exp" name="expires_at"
                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <button type="submit"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
            <i class="fas fa-lock mr-1"></i> Зарезервировать
        </button>
    </form>

    <?php
    $reservation_rows = array();
    $states = array('active'=>'warn','released'=>'neutral','consumed'=>'ok');
    foreach ($rows as $row)
    {
        $item = '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['item']['code']).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['item']['name']).'</span>';
        $consumer = HTML::chars((string) $row['ref_type']).($row['ref_id'] ? '#'.(int) $row['ref_id'] : '');
        $actions = '';
        if ($row['status'] === 'active')
        {
            $actions = '<a href="/warehouse/reservations/consume/'.(int) $row['id'].'" class="mr-3 text-green-600 hover:text-green-800" title="Списать по резерву" aria-label="Списать по резерву"><i class="fas fa-check" aria-hidden="true"></i></a>'
                .'<a href="/warehouse/reservations/release/'.(int) $row['id'].'" class="text-red-600 hover:text-red-800" title="Снять резерв" aria-label="Снять резерв"><i class="fas fa-times" aria-hidden="true"></i></a>';
        }
        $reservation_rows[] = array(
            (int) $row['id'],$item,HTML::chars($row['warehouse_name']),number_format((float) $row['qty'],3,'.',' '),$consumer,
            Component::render('badge',array('label'=>isset($statuses[$row['status']])?$statuses[$row['status']]:$row['status'],'state'=>isset($states[$row['status']])?$states[$row['status']]:'neutral')),
            $actions,
        );
    }
    Component::begin('card',array('title'=>'Резервов: '.(int) $total,'icon'=>'fas fa-lock','flush'=>TRUE));
    echo Component::render('table',array(
        'caption'=>'Резервы номенклатуры',
        'columns'=>array(
            array('label'=>'#','priority'=>4),array('label'=>'Номенклатура'),array('label'=>'Склад','priority'=>2),
            array('label'=>'Количество','align'=>'right','nowrap'=>TRUE),array('label'=>'Потребитель','priority'=>3),
            array('label'=>'Статус'),array('label'=>'Действия','align'=>'right','nowrap'=>TRUE),
        ),
        'rows'=>$reservation_rows,'sticky'=>TRUE,
        'empty_title'=>'Резервов не найдено','empty_hint'=>'Измените фильтр или создайте резерв для склада и номенклатуры.',
    ));
    Component::end();
    ?>

</div>
