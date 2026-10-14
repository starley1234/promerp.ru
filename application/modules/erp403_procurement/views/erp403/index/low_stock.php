<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-exclamation-triangle mr-2 text-amber-500"></i> Дефицит склада
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Позиции, остаток по которым опустился до минимума. Заявки создаются по минимуму с запасом 20 % и не задваиваются.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <form method="post" action="/procurement/auto-requests" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Склад</label>
                    <select name="warehouse_id" class="border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                        <option value="">все склады</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ((int) $warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                                <?php echo HTML::chars($warehouse->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Нужен через, дней</label>
                    <input type="number" name="need_days" value="14" min="1" max="180"
                           class="w-24 border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    <i class="fas fa-magic mr-1"></i> Создать заявки
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp403/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $stock_rows=array();foreach($rows as $row){$position='<span class="text-gray-900 dark:text-white">'.HTML::chars($row['name']).'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.($row['code']?HTML::chars($row['code']).' · ':'').'#'.(int)$row['item_id'].'</span>';$state=Component::render('badge',array('label'=>!empty($row['request_exists'])?'уже заявлено':'нет заявки','state'=>!empty($row['request_exists'])?'warn':'neutral'));$stock_rows[]=array($position,HTML::chars($row['warehouse_name']?$row['warehouse_name']:'#'.(int)$row['warehouse_id']),number_format((float)$row['qty'],3,'.',' ').' '.HTML::chars($row['uom_code']),number_format((float)$row['min_qty'],3,'.',' '),'<span class="font-medium text-blue-700 dark:text-blue-300">'.(int)$row['suggested_qty'].'</span>',$state);}
    echo Component::render('table',array('caption'=>'Позиции склада на минимальном остатке','columns'=>array(array('label'=>'Позиция'),array('label'=>'Склад','priority'=>2),array('label'=>'Остаток','align'=>'right','nowrap'=>TRUE),array('label'=>'Минимум','align'=>'right','priority'=>3),array('label'=>'К закупке','align'=>'right'),array('label'=>'Заявка')),'rows'=>$stock_rows,'row_header'=>TRUE,'sticky'=>TRUE,'empty_title'=>'Позиций на минимуме нет','empty_hint'=>'Минимальный остаток задаётся в WMS; сейчас автоматическая заявка не требуется.'));
    ?>
</div>
