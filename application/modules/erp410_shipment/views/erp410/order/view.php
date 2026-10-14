<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка заказа клиента: состав, резерв, отгрузки и счета в одном месте.
 *
 * Порядок блоков повторяет путь заказа: состав → резерв и сборка → отгрузка →
 * счёт. Действия, меняющие состояния, — отдельные POST-формы с пояснением,
 * почему кнопка недоступна.
 */
?>

<div>

    <?php
    echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array());

    // Оплачено и зарезервировано — по данным страницы, без отдельных запросов.
    $paid = 0.0;

    foreach ($invoices as $invoice_row)
    {
        $paid += (float) $invoice_row->paid_amount;
    }

    $reserved_lines = 0;

    foreach ($lines as $line_row)
    {
        if ((float) $line_row->qty_reserved > 0)
        {
            $reserved_lines++;
        }
    }
    ?>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">
                    <?php echo HTML::chars($order->number); ?>
                </h1>
                <?php echo Component::render('badge', array('label' => $order->status_label(), 'state' => $order->status_state())); ?>
                <?php if ($order->is_overdue()): ?>
                    <?php echo Component::render('badge', array('label' => 'просрочен', 'state' => 'bad')); ?>
                <?php endif; ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($customer ?: 'Клиент не задан'); ?>
                · создан <?php echo HTML::chars(date('d.m.Y', strtotime((string) $order->created_at))); ?>
                · источник: <?php echo HTML::chars($order->source_label()); ?>
                <?php if ($order->order_date): ?> · дата заказа <?php echo HTML::chars(date('d.m.Y', strtotime((string) $order->order_date))); ?><?php endif; ?>
                <?php if ($order->ship_date): ?> · отгрузка <?php echo HTML::chars(date('d.m.Y', strtotime((string) $order->ship_date))); ?><?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/sales/orders"
               class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> К журналу
            </a>
            <?php if ($can_manage AND $order->is_editable()): ?>
                <a href="/sales/orders/edit/<?php echo (int) $order->id; ?>"
                   class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-pen mr-2" aria-hidden="true"></i> Правка шапки
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <?php
        echo Component::render('kpi', array('label' => 'Сумма заказа', 'value' => number_format((float) $order->amount, 2, '.', ' '), 'unit' => '₽', 'state' => 'info'));
        echo Component::render('kpi', array('label' => 'Оплачено', 'value' => number_format((float) $paid, 2, '.', ' '), 'unit' => '₽', 'hint' => 'по счетам заказа', 'state' => ($paid >= (float) $order->amount AND (float) $order->amount > 0) ? 'ok' : 'neutral'));

        $credit_label = $credit['allowed'] ? 'в лимите' : 'сверх лимита';
        echo Component::render('kpi', array(
            'label' => 'Кредитный лимит',
            'value' => number_format((float) $credit['available'], 2, '.', ' '),
            'unit'  => '₽',
            'hint'  => 'свободно · '.$credit_label,
            'state' => $credit['allowed'] ? 'ok' : 'warn',
        ));

        $unpaid = round((float) $order->amount - (float) $paid, 2);
        echo Component::render('kpi', array(
            'label' => 'Ждёт оплаты',
            'value' => number_format(max(0, $unpaid), 2, '.', ' '),
            'unit'  => '₽',
            'hint'  => (float) $order->amount > 0 ? 'по счетам заказа' : 'сумма ещё не набрана',
            'state' => ($unpaid > 0.009) ? 'warn' : 'ok',
        ));
        ?>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <?php if ($can_manage): ?>
            <?php if ((string) $order->status === 'draft'): ?>
                <form method="post" action="/sales/orders/act/confirm/<?php echo (int) $order->id; ?>" class="inline">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-check mr-2" aria-hidden="true"></i> Подтвердить
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array((string) $order->status, array('confirmed', 'reserved', 'picking'), TRUE)): ?>
                <form method="post" action="/sales/orders/act/reserve/<?php echo (int) $order->id; ?>" class="inline">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                            title="Поставить резерв под позиции заказа на складе">
                        <i class="fas fa-bookmark mr-2" aria-hidden="true"></i> Зарезервировать
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($reserved_lines > 0): ?>
                <form method="post" action="/sales/orders/act/release/<?php echo (int) $order->id; ?>" class="inline">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-eraser mr-2" aria-hidden="true"></i> Снять резерв (<?php echo (int) $reserved_lines; ?>)
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($can_ship AND $order->can_ship()): ?>
            <form method="post" action="/sales/orders/act/ship/<?php echo (int) $order->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700">
                    <i class="fas fa-truck-fast mr-2" aria-hidden="true"></i> Собрать отгрузку
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_manage AND (string) $order->status === 'shipped'): ?>
            <form method="post" action="/sales/orders/act/close/<?php echo (int) $order->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-emerald-700 hover:bg-emerald-800">
                    <i class="fas fa-flag-checkered mr-2" aria-hidden="true"></i> Закрыть заказ
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_manage AND ! $order->is_closed()): ?>
            <form method="post" action="/sales/orders/act/cancel/<?php echo (int) $order->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                    <i class="fas fa-ban mr-2" aria-hidden="true"></i> Отменить заказ
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php
    // --- Состав заказа ------------------------------------------------------
    $rows = array();

    foreach ($lines as $line)
    {
        $progress = (float) $line->progress();

        $rows[] = array(
            (int) $line->line_no,
            HTML::chars($line->name)
                .($line->code ? '<br><span class="text-xs text-gray-500 dark:text-gray-400 font-mono">'.HTML::chars($line->code).'</span>' : ''),
            number_format((float) $line->qty, 3, '.', ' ').' '.HTML::chars($line->uom_code),
            number_format((float) $line->qty_reserved, 3, '.', ' '),
            number_format((float) $line->qty_shipped, 3, '.', ' '),
            number_format((float) $line->net_price(), 2, '.', ' ')
                .((float) $line->discount > 0 ? '<br><span class="text-xs text-gray-500 dark:text-gray-400">скидка '.(float) $line->discount.' %</span>' : ''),
            number_format((float) $line->amount(), 2, '.', ' '),
            Component::render('badge', array('label' => $line->status_label(), 'state' => $line->status_state())),
            $can_manage AND $order->is_editable()
                ? '<form method="post" action="/sales/orders/line/remove/'.(int) $line->id.'" class="inline">'
                    .'<input type="hidden" name="order_id" value="'.(int) $order->id.'">'
                    .'<button type="submit" class="text-rose-600 hover:text-rose-800 dark:text-rose-400" aria-label="Убрать позицию '.HTML::chars($line->name).'"><i class="fas fa-trash" aria-hidden="true"></i></button>'
                    .'</form>'
                : '',
        );
    }

    echo Component::begin('card', array(
        'title'    => 'Состав заказа',
        'icon'     => 'fas fa-list-ol',
        'subtitle' => count($lines).' поз. · '.number_format((float) $order->amount, 2, '.', ' ').' ₽',
        'flush'    => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'Позиции заказа '.$order->number,
        'columns'     => array(
            array('label' => '№', 'nowrap' => TRUE),
            array('label' => 'Позиция'),
            array('label' => 'Количество', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Резерв', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
            array('label' => 'Отгружено', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
            array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Состояние'),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'        => $rows,
        'row_header_compact' => TRUE,
        'empty_title' => 'В заказе нет позиций',
        'empty_hint'  => 'Добавьте позицию — цена подставится из прайса клиента.',
    ));
    echo Component::end();
    ?>

    <?php if ($can_manage AND $order->is_editable()): ?>
        <div class="mt-4">
            <?php
            $item_options = '';

            foreach ($items as $item)
            {
                $item_options .= '<option value="'.(int) $item['id'].'">'.HTML::chars($item['code'].' — '.$item['name']).'</option>';
            }

            $body = '<div class="space-y-1"><label for="line_item" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Позиция</label>'
                .'<select id="line_item" name="item_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'.$item_options.'</select></div>';

            $body .= Component::render('form_field', array('id' => 'line_qty', 'label' => 'Количество', 'name' => 'qty', 'value' => '1', 'type' => 'number'));
            $body .= Component::render('form_field', array('id' => 'line_price', 'label' => 'Цена, ₽', 'name' => 'price', 'value' => '', 'type' => 'number', 'hint' => 'пусто — возьмётся из прайса'));
            $body .= Component::render('form_field', array('id' => 'line_discount', 'label' => 'Скидка, %', 'name' => 'discount', 'value' => '', 'type' => 'number'));

            echo Component::begin('card', array('title' => 'Добавить позицию', 'icon' => 'fas fa-plus', 'subtitle' => 'цена подставляется из прайса клиента'));
            ?>
            <form method="post" action="/sales/orders/line/add/<?php echo (int) $order->id; ?>">
                <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                <div class="flex flex-wrap items-start gap-3">
                    <?php echo $body; ?>
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
        $rows = array();

        foreach ($shipments as $shipment)
        {
            $rows[] = array(
                '<a href="/sales/shipments/view/'.(int) $shipment->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($shipment->number).'</a>',
                $shipment->shipment_date ? HTML::chars(date('d.m.Y', strtotime((string) $shipment->shipment_date))) : '—',
                Component::render('badge', array('label' => $shipment->status_label(), 'state' => $shipment->status_state())),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Отгрузки',
            'icon'     => 'fas fa-truck-fast',
            'subtitle' => count($shipments).' шт',
            'flush'    => TRUE,
        ));
        echo Component::render('table', array(
            'caption'     => 'Отгрузки по заказу '.$order->number,
            'columns'     => array(array('label' => 'Отгрузка'), array('label' => 'Дата', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Отгрузок по заказу нет',
            'empty_hint'  => 'Отгрузка собирается из позиций заказа после подтверждения.',
        ));
        echo Component::end();

        $rows = array();

        foreach ($invoices as $item)
        {
            $rows[] = array(
                '<a href="/sales/invoices/view/'.(int) $item->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($item->number).'</a>',
                number_format((float) $item->amount, 2, '.', ' ').' ₽',
                number_format((float) $item->outstanding(), 2, '.', ' ').' ₽',
                Component::render('badge', array(
                    'label' => $item->is_overdue() ? 'просрочен' : $item->status_label(),
                    'state' => $item->is_overdue() ? 'bad' : $item->status_state(),
                )),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Счета',
            'icon'     => 'fas fa-file-invoice-dollar',
            'subtitle' => count($invoices).' шт',
            'flush'    => TRUE,
        ));
        echo Component::render('table', array(
            'caption'     => 'Счета по заказу '.$order->number,
            'columns'     => array(array('label' => 'Счёт'), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Счетов нет',
            'empty_hint'  => 'Счёт выставляется из проведённой отгрузки.',
        ));
        echo Component::end();
        ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
        <?php
        echo Component::render('card', array(
            'title' => 'Условия поставки',
            'icon'  => 'fas fa-truck',
            'body'  => '<dl class="text-sm space-y-1">'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Склад отгрузки</dt><dd>'.HTML::chars($order->warehouse_id ? 'Склад #'.(int) $order->warehouse_id : 'не задан').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Адрес доставки</dt><dd>'.HTML::chars($order->delivery_address ?: 'не задан').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Условия оплаты</dt><dd>'.HTML::chars($order->payment_terms ?: 'по умолчанию').'</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Валюта</dt><dd>'.HTML::chars($order->currency).'</dd></div>'
                .'</dl>',
        ));

        echo Component::render('card', array(
            'title' => 'Кредит и примечание',
            'icon'  => 'fas fa-shield-alt',
            'state' => $credit['allowed'] ? 'ok' : 'warn',
            'body'  => '<dl class="text-sm space-y-1">'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Лимит</dt><dd>'.number_format((float) $credit['limit'], 2, '.', ' ').' ₽</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Задолженность клиента</dt><dd>'.number_format((float) $credit['exposure'], 2, '.', ' ').' ₽</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Свободно</dt><dd>'.number_format((float) $credit['available'], 2, '.', ' ').' ₽</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Проверка</dt><dd>'.HTML::chars($credit['reason']).'</dd></div>'
                .'</dl>'
                .($order->note ? '<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">'.HTML::chars($order->note).'</p>' : ''),
        ));
        ?>
    </div>
</div>
