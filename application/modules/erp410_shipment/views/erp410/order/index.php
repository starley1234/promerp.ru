<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Журнал заказов клиентов: отбор по состоянию, клиенту и сроку.
 *
 * Первая колонка — номер заказа (по нему ищут), последняя — переход к карточке.
 * Просроченные строки подсвечены, чтобы «что везти сегодня» читалось без
 * открытия карточки.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-invoice mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Заказы клиентов
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего <?php echo (int) $total; ?> · в работе <?php echo (int) $stats['open']; ?> на
                <?php echo number_format((float) $stats['amount_open'], 0, '.', ' '); ?> ₽
                <?php if ((int) $stats['overdue']): ?>
                    · <span class="text-rose-600 dark:text-rose-400 font-medium">просрочено <?php echo (int) $stats['overdue']; ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/sales/orders/edit"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2" aria-hidden="true"></i> Новый заказ
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $body = '';

    $body .= Component::render('form_field', array(
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
        .'<input type="checkbox" name="open" value="1"'.($filters['open'] ? ' checked' : '').'> только открытые</label>';

    $body .= '<label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">'
        .'<input type="checkbox" name="overdue" value="1"'.($filters['overdue'] ? ' checked' : '').'> только просроченные</label>';

    echo Component::render('filters', array(
        'action'     => '/sales/orders',
        'body'       => $body,
        'reset_url'  => '/sales/orders',
        'submit_label' => 'Найти',
    ));
    ?>

    <?php
    $table_rows = array();

    foreach ($rows as $order)
    {
        $number = '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'
            .HTML::chars($order->number).'</a>';

        if ($order->note)
        {
            $number .= '<br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(mb_substr((string) $order->note, 0, 80)).'</span>';
        }

        $ship = $order->ship_date ? HTML::chars(date('d.m.Y', strtotime((string) $order->ship_date))) : '—';

        if ($order->is_overdue())
        {
            $ship .= '<br><span class="text-xs font-medium text-rose-600 dark:text-rose-400">просрочен</span>';
        }
        elseif ($order->days_left() !== NULL)
        {
            $ship .= '<br><span class="text-xs text-gray-500 dark:text-gray-400">через '.(int) $order->days_left().' дн.</span>';
        }

        $table_rows[] = array(
            'class' => $order->is_overdue() ? 'bg-rose-50/60 dark:bg-rose-900/10' : NULL,
            'cells' => array(
                $number,
                HTML::chars($order->customer_id ? 'Клиент #'.(int) $order->customer_id : 'без клиента'),
                $ship,
                number_format((float) $order->amount, 2, '.', ' ').' '.HTML::chars($order->currency),
                Component::render('badge', array(
                    'label' => $order->status_label(),
                    'state' => $order->status_state(),
                    'title' => $order->source_label(),
                )),
                '<a href="/sales/orders/view/'.(int) $order->id.'" class="text-blue-600 hover:text-blue-800 dark:text-blue-400" aria-label="Открыть заказ '.HTML::chars($order->number).'"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>',
            ),
        );
    }

    echo Component::render('table', array(
        'caption'     => 'Заказы клиентов',
        'columns'     => array(
            array('label' => 'Заказ'),
            array('label' => 'Клиент', 'priority' => 3),
            array('label' => 'Отгрузка', 'nowrap' => TRUE),
            array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Состояние'),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'        => $table_rows,
        'row_header'  => TRUE,
        'sticky'      => TRUE,
        'empty_title' => 'Заказов не найдено',
        'empty_hint'  => 'Снимите часть фильтров или создайте заказ клиента.',
    ));

    $query = array_filter(array(
        'search'      => $filters['search'],
        'status'      => $filters['status'],
        'customer_id' => $filters['customer_id'],
        'open'        => $filters['open'],
        'overdue'     => $filters['overdue'],
    ), function ($value) { return $value !== NULL AND $value !== '' AND $value !== FALSE AND $value !== '0'; });

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / max(1, $limit)),
        'total' => $total,
        'url'   => function ($p) use ($query) {
            $query['page'] = $p;

            return '/sales/orders?'.http_build_query($query);
        },
    ));
    ?>
</div>
