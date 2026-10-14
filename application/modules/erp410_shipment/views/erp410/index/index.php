<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Доска продаж: что решить, что везти и что ждёт денег.
 *
 * Экран отвечает на три вопроса по порядку — деньги сегодня (счета), работа
 * сегодня (заказы и отгрузки), состояние клиентов. Списки короткие (по пять
 * строк) и ведут в свои разделы: доска не заменяет журналы, а показывает, куда
 * идти.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-chart-line mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Продажи
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Заказы клиентов, резерв товара, отгрузка со склада, счета и оплата.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <div class="flex flex-wrap gap-2">
                <a href="/sales/orders/edit"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i> Новый заказ
                </a>
                <a href="/sales/customers"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-handshake mr-2" aria-hidden="true"></i> Клиенты
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <?php
        echo Component::render('kpi', array(
            'label' => 'Заказы в работе',
            'value' => (int) $order_stats['open'],
            'unit'  => 'шт',
            'hint'  => 'на '.number_format((float) $order_stats['amount_open'], 0, '.', ' ').' ₽',
            'url'   => '/sales/orders?open=1',
            'state' => ((int) $order_stats['open'] > 0) ? 'info' : 'neutral',
        ));

        echo Component::render('kpi', array(
            'label' => 'Просроченные заказы',
            'value' => (int) $order_stats['overdue'],
            'unit'  => 'шт',
            'hint'  => ((int) $order_stats['overdue'] > 0) ? 'срок отгрузки прошёл' : 'сроки соблюдаются',
            'url'   => '/sales/orders?overdue=1',
            'state' => ((int) $order_stats['overdue'] > 0) ? 'bad' : 'ok',
        ));

        echo Component::render('kpi', array(
            'label' => 'Готовы к отправке',
            'value' => (int) $shipment_stats['ready'],
            'unit'  => 'шт',
            'hint'  => 'отгрузки собраны и ждут проведения',
            'url'   => '/sales/shipments',
            'state' => ((int) $shipment_stats['ready'] > 0) ? 'warn' : 'neutral',
        ));

        echo Component::render('kpi', array(
            'label' => 'Отгружено за месяц',
            'value' => (int) $shipment_stats['shipped_month'],
            'unit'  => 'шт',
            'hint'  => 'на '.number_format((float) $shipment_stats['amount_month'], 0, '.', ' ').' ₽',
            'url'   => '/sales/shipments?status=shipped',
            'state' => 'ok',
        ));

        echo Component::render('kpi', array(
            'label' => 'Ждут оплаты',
            'value' => (int) $invoice_stats['issued'],
            'unit'  => 'сч.',
            'hint'  => 'остаток '.number_format((float) $invoice_stats['outstanding'], 0, '.', ' ').' ₽',
            'url'   => '/sales/invoices?status=issued',
            'state' => ((int) $invoice_stats['issued'] > 0) ? 'warn' : 'ok',
        ));

        echo Component::render('kpi', array(
            'label' => 'Просроченные счета',
            'value' => (int) $invoice_stats['overdue'],
            'unit'  => 'сч.',
            'hint'  => 'оплата не пришла в срок',
            'url'   => '/sales/invoices?overdue=1',
            'state' => ((int) $invoice_stats['overdue'] > 0) ? 'bad' : 'ok',
        ));
        ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        <?php
        // --- Черновики: заказы, которые ещё не подтверждены -------------------
        $rows = array();

        foreach ($draft_orders as $order)
        {
            $rows[] = array(
                '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>',
                HTML::chars($order->customer_id ? 'Клиент #'.(int) $order->customer_id : 'без клиента'),
                number_format((float) $order->amount, 2, '.', ' ').' ₽',
                Component::render('badge', array('label' => $order->status_label(), 'state' => $order->status_state())),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Черновики заказов',
            'icon'     => 'fas fa-pen',
            'subtitle' => 'подтвердите или дополните',
            'flush'    => TRUE,
            'actions_html' => (int) $order_stats['draft'] > 0
                ? '<a href="/sales/orders?status=draft" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">все черновики ('.(int) $order_stats['draft'].')</a>'
                : NULL,
        ));
        echo Component::render('table', array(
            'caption'     => 'Черновики заказов клиентов',
            'columns'     => array(array('label' => 'Заказ'), array('label' => 'Клиент', 'priority' => 3), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Черновиков нет',
            'empty_hint'  => 'Все заказы подтверждены — можно собирать отгрузки.',
        ));
        echo Component::end();

        // --- Ближайшие отгрузки ---------------------------------------------
        $rows = array();

        foreach ($due_orders as $order)
        {
            $days = $order->days_left();

            $rows[] = array(
                '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>',
                $order->ship_date ? HTML::chars(date('d.m.Y', strtotime((string) $order->ship_date))) : '—',
                $days === NULL ? '—' : ((int) $days).' дн.',
                number_format((float) $order->amount, 2, '.', ' ').' ₽',
                Component::render('badge', array(
                    'label' => $order->is_overdue() ? 'просрочен' : $order->status_label(),
                    'state' => $order->is_overdue() ? 'bad' : $order->status_state(),
                )),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Отгрузить на этой неделе',
            'icon'     => 'fas fa-truck-fast',
            'subtitle' => 'заказы со сроком в ближайшие 7 дней',
            'flush'    => TRUE,
            'state'    => ((int) $order_stats['overdue'] > 0) ? 'bad' : NULL,
            'actions_html' => '<a href="/sales/orders?open=1" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">все открытые</a>',
        ));
        echo Component::render('table', array(
            'caption'     => 'Заказы клиентов со сроком отгрузки в ближайшие семь дней',
            'columns'     => array(array('label' => 'Заказ'), array('label' => 'Срок', 'nowrap' => TRUE), array('label' => 'Осталось', 'nowrap' => TRUE), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Ближайших отгрузок нет',
            'empty_hint'  => 'Заказы со сроком в пределах недели появятся здесь.',
        ));
        echo Component::end();

        // --- Сборка и резерв -------------------------------------------------
        $rows = array();

        foreach ($picking as $order)
        {
            $rows[] = array(
                '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>',
                HTML::chars($order->warehouse_id ? 'Склад #'.(int) $order->warehouse_id : 'склад не задан'),
                number_format((float) $order->amount, 2, '.', ' ').' ₽',
                Component::render('badge', array('label' => $order->status_label(), 'state' => $order->status_state())),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Резерв и сборка',
            'icon'     => 'fas fa-warehouse',
            'subtitle' => 'товар зарезервирован под заказы',
            'flush'    => TRUE,
            'actions_html' => '<a href="/sales/shipments" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">отгрузки</a>',
        ));
        echo Component::render('table', array(
            'caption'     => 'Заказы клиентов с зарезервированным товаром',
            'columns'     => array(array('label' => 'Заказ'), array('label' => 'Склад', 'priority' => 3), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Резервов нет',
            'empty_hint'  => 'Резерв ставится из карточки подтверждённого заказа.',
        ));
        echo Component::end();

        // --- Деньги -----------------------------------------------------------
        $rows = array();

        foreach ($unpaid as $invoice)
        {
            $rows[] = array(
                '<a href="/sales/invoices/view/'.(int) $invoice->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($invoice->number).'</a>',
                $invoice->due_date ? HTML::chars(date('d.m.Y', strtotime((string) $invoice->due_date))) : '—',
                number_format((float) $invoice->outstanding(), 2, '.', ' ').' ₽',
                Component::render('badge', array(
                    'label' => $invoice->is_overdue() ? 'просрочен' : $invoice->status_label(),
                    'state' => $invoice->is_overdue() ? 'bad' : $invoice->status_state(),
                )),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Просроченные счета',
            'icon'     => 'fas fa-file-invoice-dollar',
            'subtitle' => 'ждали оплату к дате',
            'flush'    => TRUE,
            'state'    => ((int) $invoice_stats['overdue'] > 0) ? 'bad' : NULL,
            'actions_html' => $can_invoice
                ? '<a href="/sales/invoices?status=issued" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">все неоплаченные</a>'
                : NULL,
        ));
        echo Component::render('table', array(
            'caption'     => 'Просроченные счета клиентов',
            'columns'     => array(array('label' => 'Счёт'), array('label' => 'Оплатить до', 'nowrap' => TRUE), array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Просроченных счетов нет',
            'empty_hint'  => 'Все выставленные счета оплачиваются в срок.',
        ));
        echo Component::end();
        ?>
    </div>

    <?php
    $rows = array();

    $rows[] = array('Клиентов с профилем продажи', (int) $customer_stats['profiles'].' из '.(int) $customer_stats['counterparties']);
    $rows[] = array('Активных профилей', (int) $customer_stats['active'].' шт');
    $rows[] = array('Суммарный лимит отсрочки', number_format((float) $customer_stats['limit_total'], 2, '.', ' ').' ₽');
    $rows[] = array('Открытая задолженность', number_format((float) $customer_stats['exposure'], 2, '.', ' ').' ₽');
    $rows[] = array('Клиентов без условий продажи', (int) $customer_stats['without_profile'].' шт');

    echo Component::begin('card', array(
        'title'    => 'Условия клиентов',
        'icon'     => 'fas fa-handshake',
        'subtitle' => 'отсрочка, лимит и задолженность',
        'flush'    => TRUE,
        'actions_html' => '<a href="/sales/customers" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">открыть клиентов</a>',
    ));
    echo Component::render('table', array(
        'caption'    => 'Сводка условий продажи по клиентам',
        'columns'    => array(array('label' => 'Показатель'), array('label' => 'Значение', 'align' => 'right', 'nowrap' => TRUE)),
        'rows'       => $rows,
        'row_header' => TRUE,
    ));
    echo Component::end();
    ?>
</div>
