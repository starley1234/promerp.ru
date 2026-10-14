<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Остатки на складах.
 *
 * Страница переведена на компоненты интерфейса
 * (`application/views/components/README.md`): таблица получила подпись,
 * заголовки со `scope` и прокрутку на узком экране (до этого на телефоне
 * таблица просто обрезалась по краю), фильтры — единый блок отбора, пустой
 * результат объясняет, что произошло, а листание сохраняет выбранный склад.
 */
$filters = isset($filters) ? $filters : array();

$fields = array();
$fields[] = '<div>'
    .'<label for="warehouse_id" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Склад</label>'
    .'<select id="warehouse_id" name="warehouse_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">'
    .'<option value="">Все склады</option>';

foreach ($warehouses as $warehouse)
{
    $fields[] = '<option value="'.(int) $warehouse->id.'"'
        .((string) Arr::get($filters, 'warehouse_id') === (string) $warehouse->id ? ' selected' : '')
        .'>'.HTML::chars($warehouse->name).'</option>';
}

$fields[] = '</select></div>';

$fields[] = '<div class="flex items-center">'
    .'<input type="checkbox" id="low_only" name="low_only" value="1"'
    .' class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"'
    .(Arr::get($filters, 'low_only') ? ' checked' : '').'>'
    .'<label for="low_only" class="ml-2 text-sm text-gray-700 dark:text-gray-200">Только ниже минимума</label>'
    .'</div>';

$columns = array(
    array('label' => 'Номенклатура'),
    array('label' => 'Склад', 'priority' => 2),
    array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE),
    array('label' => 'Резерв', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
    array('label' => 'Доступно', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
    array('label' => 'Минимум', 'align' => 'right', 'nowrap' => TRUE),
);

$source = $rows;
$rows   = array();

foreach ($source as $row)
{
    $min_input = '<form method="post" action="/warehouse/balances/set_min" autocomplete="off" class="flex items-center justify-end gap-2">'
        .'<input type="hidden" name="warehouse_id" value="'.(int) $row['warehouse_id'].'">'
        .'<input type="hidden" name="item_id" value="'.(int) $row['item_id'].'">'
        .'<label class="sr-only" for="min_qty_'.(int) $row['warehouse_id'].'_'.(int) $row['item_id'].'">'
        .'Неснижаемый минимум для '.HTML::chars($row['item']['name']).'</label>'
        .'<input id="min_qty_'.(int) $row['warehouse_id'].'_'.(int) $row['item_id'].'" type="number" step="0.001" min="0" name="min_qty"'
        .' value="'.number_format((float) $row['min_qty'], 3, '.', '').'"'
        .' class="w-24 text-right rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">'
        .'<button type="submit" class="p-1 text-blue-600 hover:text-blue-800"'
        .' aria-label="Сохранить минимум для '.HTML::chars($row['item']['name']).'" title="Сохранить минимум">'
        .'<i class="fas fa-save" aria-hidden="true"></i></button>'
        .'</form>';

    $row_class = $row['is_low'] ? 'bg-amber-50 dark:bg-amber-900/20' : '';

    $rows[] = array(
        'class' => $row_class,
        'cells' => array(
        '<div class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['item']['code'])
            .($row['is_low'] ? ' '.Component::render('badge', array(
                'label' => 'ниже минимума',
                'state' => 'warn',
                'title' => 'Остаток ниже неснижаемого минимума',
            )) : '')
            .'</div>'
            .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['item']['name']).'</div>',
        HTML::chars($row['warehouse_name']),
        '<span class="font-semibold text-gray-900 dark:text-white">'.number_format((float) $row['qty'], 3, '.', ' ').'</span>'
            .' <span class="text-xs text-gray-500">'.HTML::chars($row['uom_code']).'</span>',
        '<span class="text-yellow-700 dark:text-yellow-400">'.number_format((float) $row['reserved'], 3, '.', ' ').'</span>',
        '<span class="'.((float) $row['available'] < 0 ? 'text-rose-600' : 'text-green-700 dark:text-green-400').'">'
            .number_format((float) $row['available'], 3, '.', ' ').'</span>',
        $min_input,
        ),
    );
}

$url = function ($page) use ($filters) {
    return '?page='.(int) $page
        .'&warehouse_id='.urlencode((string) Arr::get($filters, 'warehouse_id'))
        .'&low_only='.(Arr::get($filters, 'low_only') ? 1 : 0);
};
?>

<div>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <i class="fas fa-boxes mr-2 text-blue-600" aria-hidden="true"></i> Остатки на складах
    </h1>

    <?php echo Component::render('filters', array(
        'action'    => '/warehouse/balances',
        'body'      => implode('', $fields),
        'reset_url' => '/warehouse/balances',
    )); ?>

    <?php Component::begin('card', array(
        'flush' => TRUE,
        'title' => 'Найдено позиций: '.number_format((int) $total, 0, ',', ' '),
        'subtitle' => 'Остаток, резерв и неснижаемый минимум по складам. Минимум можно поправить прямо в строке.',
    )); ?>
        <?php echo Component::render('table', array(
            'caption'    => 'Остатки номенклатуры по складам',
            'columns'    => $columns,
            'rows'       => $rows,
            'row_header' => TRUE,
            'empty_title' => 'Остатков по заданным условиям нет',
            'empty_hint'  => 'Снимите фильтр «только ниже минимума» или выберите другой склад — данные появятся из движений склада.',
            'sticky'      => TRUE,
        )); ?>
    <?php Component::end(); ?>

    <?php echo Component::render('pagination', array(
        'page'  => isset($page) ? (int) $page : 1,
        'pages' => isset($limit) AND $limit > 0 ? (int) ceil($total / $limit) : 0,
        'total' => (int) $total,
        'url'   => $url,
    )); ?>

</div>
