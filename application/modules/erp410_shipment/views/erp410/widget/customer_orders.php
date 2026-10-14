<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Виджет «Заказы и счета клиента» для карточки контрагента.
 *
 * Отвечает на вопрос «работаем ли мы с ним сейчас»: что в работе и что не
 * оплачено. Раскрывается ссылками в разделы продаж.
 */
$rows = array();

foreach ($orders as $order)
{
    $rows[] = array(
        '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>',
        number_format((float) $order->amount, 2, '.', ' ').' ₽',
        Component::render('badge', array('label' => $order->status_label(), 'state' => $order->status_state())),
    );
}

echo Component::render('table', array(
    'caption'     => 'Последние заказы клиента (виджет)',
    'columns'     => array(array('label' => 'Заказ'), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
    'rows'        => $rows,
    'row_header'  => TRUE,
    'empty_title' => 'Заказов нет',
    'empty_hint'  => 'Клиент ещё не заказывал.',
));

if ($invoices)
{
    $rows = array();

    foreach ($invoices as $invoice)
    {
        $rows[] = array(
            '<a href="/sales/invoices/view/'.(int) $invoice->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($invoice->number).'</a>',
            number_format((float) $invoice->outstanding(), 2, '.', ' ').' ₽',
            Component::render('badge', array('label' => $invoice->status_label(), 'state' => $invoice->status_state())),
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Неоплаченные счета клиента (виджет)',
        'columns'    => array(array('label' => 'Счёт'), array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
        'rows'       => $rows,
        'row_header' => TRUE,
    ));
}
?>
