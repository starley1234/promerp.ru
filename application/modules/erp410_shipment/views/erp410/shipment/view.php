<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка отгрузки: что везём, кому и куда; проведение и счёт.
 *
 * Проведение (`post`) списывает товар со склада — по резерву, если он есть, иначе
 * напрямую. Поэтому кнопка одна и с пояснением, а после проведения отгрузку
 * править нельзя.
 */
$lines_total = 0.0;

foreach ($lines as $line_row)
{
    $lines_total += (float) $line_row->amount();
}

$editable = $can_ship AND $shipment->is_editable();
?>

<div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">
                    <?php echo HTML::chars($shipment->number); ?>
                </h1>
                <?php echo Component::render('badge', array('label' => $shipment->status_label(), 'state' => $shipment->status_state())); ?>
                <?php if ($shipment->is_posted()): ?>
                    <?php echo Component::render('badge', array('label' => 'товар списан', 'state' => 'ok')); ?>
                <?php endif; ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($customer ?: 'Клиент не задан'); ?>
                <?php if ($order): ?>
                    · заказ <a href="/sales/orders/view/<?php echo (int) $order->id; ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono"><?php echo HTML::chars($order->number); ?></a>
                <?php endif; ?>
                · склад <?php echo HTML::chars($warehouse ?: 'не задан'); ?>
                <?php if ($shipment->shipment_date): ?> · дата отгрузки <?php echo HTML::chars(date('d.m.Y', strtotime((string) $shipment->shipment_date))); ?><?php endif; ?>
            </p>
        </div>
        <a href="/sales/shipments"
           class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> К журналу
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <?php
        echo Component::render('kpi', array('label' => 'Позиций', 'value' => count($lines), 'unit' => 'шт', 'state' => 'neutral'));
        echo Component::render('kpi', array('label' => 'Сумма отгрузки', 'value' => number_format($lines_total, 2, '.', ' '), 'unit' => '₽', 'state' => 'info'));

        echo Component::render('kpi', array(
            'label' => 'Счёт',
            'value' => $invoice ? 1 : 0,
            'unit'  => 'сч.',
            'hint'  => $invoice ? HTML::chars($invoice->number.' · '.$invoice->status_label()) : 'счёт ещё не выставлен',
            'state' => $invoice ? $invoice->status_state() : 'warn',
        ));

        echo Component::render('kpi', array(
            'label' => 'Проведена',
            'value' => $shipment->posted_at ? date('d.m.Y', strtotime((string) $shipment->posted_at)) : '—',
            'unit'  => $shipment->posted_at ? '' : 'нет',
            'hint'  => $shipment->posted_at ? 'товар списан со склада' : 'товар ещё на складе',
            'state' => $shipment->posted_at ? 'ok' : 'neutral',
        ));
        ?>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <?php if ($editable AND (string) $shipment->status === 'draft'): ?>
            <form method="post" action="/sales/shipments/act/ready/<?php echo (int) $shipment->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-box-open mr-2" aria-hidden="true"></i> Собрано, готово к отправке
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_ship AND ! $shipment->is_posted() AND (string) $shipment->status !== 'cancelled'): ?>
            <form method="post" action="/sales/shipments/act/post/<?php echo (int) $shipment->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700"
                        title="Списать товар со склада по этой отгрузке">
                    <i class="fas fa-dolly mr-2" aria-hidden="true"></i> Провести отгрузку
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_invoice AND $shipment->is_posted() AND ! $invoice): ?>
            <form method="post" action="/sales/shipments/act/post/<?php echo (int) $shipment->id; ?>" class="inline">
                <input type="hidden" name="invoice" value="1">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                    <i class="fas fa-file-invoice-dollar mr-2" aria-hidden="true"></i> Выставить счёт
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_ship AND ! $shipment->is_posted() AND (string) $shipment->status !== 'cancelled'): ?>
            <form method="post" action="/sales/shipments/act/cancel/<?php echo (int) $shipment->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                    <i class="fas fa-ban mr-2" aria-hidden="true"></i> Отменить отгрузку
                </button>
            </form>
        <?php endif; ?>

        <?php if ($invoice): ?>
            <a href="/sales/invoices/view/<?php echo (int) $invoice->id; ?>"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-file-invoice-dollar mr-2" aria-hidden="true"></i> Счёт <?php echo HTML::chars($invoice->number); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php
    // --- Строки отгрузки ----------------------------------------------------
    $rows = array();

    foreach ($lines as $line)
    {
        $rows[] = array(
            HTML::chars($line->name)
                .($line->code ? '<br><span class="text-xs text-gray-500 dark:text-gray-400 font-mono">'.HTML::chars($line->code).'</span>' : ''),
            number_format((float) $line->qty, 3, '.', ' ').' '.HTML::chars($line->uom_code),
            number_format((float) $line->price, 2, '.', ' '),
            number_format((float) $line->amount(), 2, '.', ' '),
            $editable
                ? '<form method="post" action="/sales/shipments/act/line_remove/'.(int) $shipment->id.'" class="inline">'
                    .'<input type="hidden" name="line_id" value="'.(int) $line->id.'">'
                    .'<button type="submit" class="text-rose-600 hover:text-rose-800 dark:text-rose-400" aria-label="Убрать строку '.HTML::chars($line->name).'"><i class="fas fa-trash" aria-hidden="true"></i></button>'
                    .'</form>'
                : '',
        );
    }

    echo Component::begin('card', array(
        'title'    => 'Что везём',
        'icon'     => 'fas fa-boxes',
        'subtitle' => count($lines).' поз. на '.number_format($lines_total, 2, '.', ' ').' ₽',
        'flush'    => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'Строки отгрузки '.$shipment->number,
        'columns'     => array(
            array('label' => 'Позиция'),
            array('label' => 'Количество', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'        => $rows,
        'row_header'  => TRUE,
        'empty_title' => 'В отгрузке нет строк',
        'empty_hint'  => 'Отгрузка собирается из заказа; строки можно добавить вручную.',
    ));
    echo Component::end();
    ?>

    <?php if ($editable): ?>
        <div class="mt-4">
            <?php
            $item_options = '<option value="">— выберите позицию —</option>';

            foreach ($items as $item)
            {
                $item_options .= '<option value="'.(int) $item['id'].'">'.HTML::chars($item['code'].' — '.$item['name']).'</option>';
            }

            echo Component::begin('card', array(
                'title'    => 'Добавить строку',
                'icon'     => 'fas fa-plus',
                'subtitle' => 'обычно строки приходят из заказа',
            ));
            ?>
            <form method="post" action="/sales/shipments/act/line_add/<?php echo (int) $shipment->id; ?>">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-[220px] space-y-1">
                        <label for="ship_item" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Позиция</label>
                        <select id="ship_item" name="item_id"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <?php echo $item_options; ?>
                        </select>
                    </div>
                    <?php
                    echo Component::render('form_field', array('id' => 'ship_qty', 'label' => 'Количество', 'name' => 'qty', 'value' => '1', 'type' => 'number'));
                    echo Component::render('form_field', array('id' => 'ship_price', 'label' => 'Цена, ₽', 'name' => 'price', 'value' => '', 'type' => 'number', 'hint' => 'пусто — из прайса клиента'));
                    ?>
                    <button type="submit" class="mt-6 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-plus mr-2" aria-hidden="true"></i> Добавить
                    </button>
                </div>
            </form>
            <?php
            echo Component::end();
            ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
        <?php
        echo Component::render('card', array(
            'title' => 'Доставка',
            'icon'  => 'fas fa-truck',
            'body'  => '<dl class="text-sm space-y-1">'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Перевозчик</dt><dd>'.HTML::chars($shipment->carrier ?: 'не задан').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Транспорт</dt><dd>'.HTML::chars($shipment->transport_number ?: 'не задан').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Адрес</dt><dd>'.HTML::chars($shipment->delivery_address ?: 'не задан').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Склад</dt><dd>'.HTML::chars($warehouse ?: 'не задан').'</dd></div>'
                .'</dl>',
        ));

        echo Component::render('card', array(
            'title' => 'Проведение',
            'icon'  => 'fas fa-dolly',
            'state' => $shipment->is_posted() ? 'ok' : NULL,
            'body'  => '<dl class="text-sm space-y-1">'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Проведена</dt><dd>'.HTML::chars($shipment->posted_at ? date('d.m.Y H:i', strtotime((string) $shipment->posted_at)) : 'нет').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Списание</dt><dd>'.($shipment->is_posted() ? 'товар ушёл со склада' : 'товар ещё на складе').'</dd></div>'
                .'</dl>'
                .'<p class="mt-3 text-xs text-gray-500 dark:text-gray-400">'.($shipment->is_posted()
                    ? 'Отгрузка проведена: строки и суммы зафиксированы.'
                    : 'При проведении товар списывается со склада: по резерву, если он есть, иначе напрямую.').'</p>'
                .($shipment->comment ? '<p class="mt-2 text-sm text-gray-600 dark:text-gray-300">'.HTML::chars($shipment->comment).'</p>' : ''),
        ));
        ?>
    </div>
</div>
