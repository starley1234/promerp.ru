<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Виджет «Сводка продаж» для дашборда.
 *
 * Показывает три цифры, которые меняются каждый день: заказы в работе, отгрузки
 * за месяц и деньги к получению. Виджет не заменяет доску — ведёт на неё.
 */
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <?php
    echo Component::render('kpi', array(
        'label' => 'Заказы в работе',
        'value' => (int) $order_stats['open'],
        'unit'  => 'шт',
        'hint'  => 'на '.number_format((float) $order_stats['amount_open'], 0, '.', ' ').' ₽',
        'url'   => '/sales/orders?open=1',
        'state' => ((int) $order_stats['overdue'] > 0) ? 'warn' : 'info',
        'compact' => TRUE,
    ));

    echo Component::render('kpi', array(
        'label' => 'Отгружено за месяц',
        'value' => (int) $shipment_stats['shipped_month'],
        'unit'  => 'шт',
        'hint'  => 'на '.number_format((float) $shipment_stats['amount_month'], 0, '.', ' ').' ₽',
        'url'   => '/sales/shipments?status=shipped',
        'state' => 'ok',
        'compact' => TRUE,
    ));

    echo Component::render('kpi', array(
        'label' => 'Ждут оплаты',
        'value' => number_format((float) $invoice_stats['outstanding'], 0, '.', ' '),
        'unit'  => '₽',
        'hint'  => (int) $invoice_stats['issued'].' сч., просрочено '.(int) $invoice_stats['overdue'],
        'url'   => '/sales/invoices?unpaid=1',
        'state' => ((int) $invoice_stats['overdue'] > 0) ? 'bad' : 'warn',
        'compact' => TRUE,
    ));
    ?>
</div>
