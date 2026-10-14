<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Журнал отгрузок: что собрано, что ждёт проведения, что уже уехало.
 *
 * Проведение отгрузки — момент, когда товар списывается со склада, поэтому
 * состояние вынесено в отдельную колонку, а сумма показывается по строкам
 * отгрузки.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-truck-fast mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Отгрузки
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · черновиков <?php echo (int) $stats['draft']; ?> ·
                готовы к отправке <?php echo (int) $stats['ready']; ?> ·
                отгружено за месяц <?php echo (int) $stats['shipped_month']; ?> на
                <?php echo number_format((float) $stats['amount_month'], 0, '.', ' '); ?> ₽
            </p>
        </div>
        <?php if ($can_ship): ?>
            <a href="/sales/orders?open=1"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-file-invoice mr-2" aria-hidden="true"></i> Собрать из заказа
            </a>
        <?php endif; ?>
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

    echo Component::render('filters', array(
        'action'       => '/sales/shipments',
        'body'         => $body,
        'reset_url'    => '/sales/shipments',
        'submit_label' => 'Найти',
    ));
    ?>

    <?php
    $table_rows = array();

    foreach ($rows as $shipment)
    {
        $number = '<a href="/sales/shipments/view/'.(int) $shipment->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'
            .HTML::chars($shipment->number).'</a>';

        if ($shipment->order_id)
        {
            $number .= '<br><span class="text-xs text-gray-500 dark:text-gray-400">к заказу #'.(int) $shipment->order_id.'</span>';
        }

        $table_rows[] = array(
            $number,
            HTML::chars($shipment->customer_id ? 'Клиент #'.(int) $shipment->customer_id : 'без клиента'),
            $shipment->shipment_date ? HTML::chars(date('d.m.Y', strtotime((string) $shipment->shipment_date))) : '—',
            HTML::chars($shipment->carrier ?: '—'),
            Component::render('badge', array('label' => $shipment->status_label(), 'state' => $shipment->status_state())),
            '<a href="/sales/shipments/view/'.(int) $shipment->id.'" class="text-blue-600 hover:text-blue-800 dark:text-blue-400" aria-label="Открыть отгрузку '.HTML::chars($shipment->number).'"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>',
        );
    }

    echo Component::render('table', array(
        'caption'     => 'Отгрузки клиентам',
        'columns'     => array(
            array('label' => 'Отгрузка'),
            array('label' => 'Клиент', 'priority' => 3),
            array('label' => 'Дата', 'nowrap' => TRUE),
            array('label' => 'Перевозчик', 'priority' => 4),
            array('label' => 'Состояние'),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'        => $table_rows,
        'row_header'  => TRUE,
        'sticky'      => TRUE,
        'empty_title' => 'Отгрузок не найдено',
        'empty_hint'  => 'Отгрузка собирается из подтверждённого заказа клиента.',
    ));

    $query = array_filter(array(
        'search'      => $filters['search'],
        'status'      => $filters['status'],
        'customer_id' => $filters['customer_id'],
    ), function ($value) { return $value !== NULL AND $value !== '' AND $value !== FALSE AND $value !== '0'; });

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / max(1, $limit)),
        'total' => $total,
        'url'   => function ($p) use ($query) {
            $query['page'] = $p;

            return '/sales/shipments?'.http_build_query($query);
        },
    ));
    ?>
</div>
