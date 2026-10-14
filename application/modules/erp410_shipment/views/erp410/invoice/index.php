<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Журнал счетов: что выставлено, что просрочено, сколько ждёт оплаты.
 *
 * Деньги — главное на этом экране, поэтому сумма, оплачено и остаток стоят
 * рядом; фильтры «только неоплаченные» и «только просроченные» закрывают
 * ежедневную работу бухгалтера.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-invoice-dollar mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Счета
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · ждут оплаты <?php echo (int) $stats['issued']; ?> на
                <?php echo number_format((float) $stats['outstanding'], 0, '.', ' '); ?> ₽
                <?php if ((int) $stats['overdue']): ?>
                    · <span class="text-rose-600 dark:text-rose-400 font-medium">просрочено <?php echo (int) $stats['overdue']; ?></span>
                <?php endif; ?>
                · оплачено за месяц <?php echo number_format((float) $stats['paid_month'], 0, '.', ' '); ?> ₽
            </p>
        </div>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $body = Component::render('form_field', array(
        'id'    => 'filter_search',
        'label' => 'Поиск',
        'name'  => 'search',
        'value' => (string) $filters['search'],
        'type'  => 'search',
    ));

    $options = '<option value="">любое</option>';

    foreach ($statuses as $key => $label)
    {
        $options .= '<option value="'.HTML::chars($key).'"'.(((string) $filters['status'] === (string) $key) ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $body .= '<div class="space-y-1"><label for="filter_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Состояние</label>'
        .'<select id="filter_status" name="status" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'.$options.'</select></div>';

    $options = '<option value="">любой</option>';

    foreach ($customers as $customer)
    {
        $options .= '<option value="'.(int) $customer['id'].'"'.(((int) $filters['customer_id'] === (int) $customer['id']) ? ' selected' : '').'>'.HTML::chars($customer['name']).'</option>';
    }

    $body .= '<div class="space-y-1"><label for="filter_customer" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Клиент</label>'
        .'<select id="filter_customer" name="customer_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'.$options.'</select></div>';

    $body .= '<label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">'
        .'<input type="checkbox" name="unpaid" value="1"'.($filters['unpaid'] ? ' checked' : '').'> только неоплаченные</label>';

    $body .= '<label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">'
        .'<input type="checkbox" name="overdue" value="1"'.($filters['overdue'] ? ' checked' : '').'> только просроченные</label>';

    echo Component::render('filters', array(
        'action'       => '/sales/invoices',
        'body'         => $body,
        'reset_url'    => '/sales/invoices',
        'submit_label' => 'Найти',
    ));
    ?>

    <?php
    $table_rows = array();

    foreach ($rows as $invoice)
    {
        $number = '<a href="/sales/invoices/view/'.(int) $invoice->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'
            .HTML::chars($invoice->number).'</a>';

        $links = array();

        if ($invoice->order_id)
        {
            $links[] = 'заказ #'.(int) $invoice->order_id;
        }

        if ($invoice->shipment_id)
        {
            $links[] = 'отгрузка #'.(int) $invoice->shipment_id;
        }

        if ($links)
        {
            $number .= '<br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(implode(' · ', $links)).'</span>';
        }

        $due = $invoice->due_date ? HTML::chars(date('d.m.Y', strtotime((string) $invoice->due_date))) : '—';

        if ($invoice->is_overdue())
        {
            $due .= '<br><span class="text-xs font-medium text-rose-600 dark:text-rose-400">просрочен</span>';
        }

        $outstanding = (float) $invoice->outstanding();

        $table_rows[] = array(
            'class' => $invoice->is_overdue() ? 'bg-rose-50/60 dark:bg-rose-900/10' : NULL,
            'cells' => array(
                $number,
                HTML::chars($invoice->customer_id ? 'Клиент #'.(int) $invoice->customer_id : 'без клиента'),
                $due,
                number_format((float) $invoice->amount, 2, '.', ' ').' ₽',
                number_format((float) $invoice->paid_amount, 2, '.', ' ').' ₽',
                number_format($outstanding, 2, '.', ' ').' ₽',
                Component::render('badge', array('label' => $invoice->status_label(), 'state' => $invoice->status_state())),
                '<a href="/sales/invoices/view/'.(int) $invoice->id.'" class="text-blue-600 hover:text-blue-800 dark:text-blue-400" aria-label="Открыть счёт '.HTML::chars($invoice->number).'"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>',
            ),
        );
    }

    echo Component::render('table', array(
        'caption'     => 'Счета клиентам',
        'columns'     => array(
            array('label' => 'Счёт'),
            array('label' => 'Клиент', 'priority' => 3),
            array('label' => 'Оплатить до', 'nowrap' => TRUE),
            array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Оплачено', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
            array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Состояние'),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'        => $table_rows,
        'row_header'  => TRUE,
        'sticky'      => TRUE,
        'empty_title' => 'Счетов не найдено',
        'empty_hint'  => 'Счёт выставляется из проведённой отгрузки.',
    ));

    $query = array_filter(array(
        'search'      => $filters['search'],
        'status'      => $filters['status'],
        'customer_id' => $filters['customer_id'],
        'unpaid'      => $filters['unpaid'],
        'overdue'     => $filters['overdue'],
    ), function ($value) { return $value !== NULL AND $value !== '' AND $value !== FALSE AND $value !== '0'; });

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / max(1, $limit)),
        'total' => $total,
        'url'   => function ($p) use ($query) {
            $query['page'] = $p;

            return '/sales/invoices?'.http_build_query($query);
        },
    ));
    ?>
</div>
