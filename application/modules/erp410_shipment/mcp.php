<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для продаж (erp410_shipment).
 *
 * Агент видит то же, что видит менеджер продаж: заказы клиентов и их позиции,
 * отгрузки, счета и оплаты, условия клиентов и историю цен. Все инструменты
 * здесь — только чтение: они отвечают на вопросы «что везти», «сколько денег
 * ждёт оплаты», «по какой цене продавать этому клиенту». Изменения состояний
 * (подтвердить заказ, зарезервировать товар, провести отгрузку, принять оплату)
 * агент делает через JSON API, где у каждого шага своё право, а необратимые
 * шаги закрыты подтверждением человека.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - сводка, заказы, отгрузки, счета, клиенты, прайс, проверка лимита —
 *    `erp410.view`;
 *  - ничего, что пишет в базу, в этом файле нет намеренно: выдача инструментов
 *    чтения безопасна для любого ключа с правом просмотра продаж.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp410_Orders`,
 * `Service_Erp410_Shipments`, `Service_Erp410_Invoices`,
 * `Service_Erp410_Customers`, `Service_Erp410_Prices`), а не таблицы: правила
 * расчёта сумм, остатков и цены остаются в одном месте.
 *
 * @package    Erp410
 * @category   Mcp
 */

$s = function (array $properties, array $required = array()) {
    return array(
        'type'       => 'object',
        'properties' => $properties,
        'required'   => $required,
    );
};

$field = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};

// Компактный вид заказа: одинаковый во всех инструментах
$order_row = function ($order) {
    return array(
        'id'            => (int) $order->id,
        'uuid'          => (string) $order->uuid,
        'number'        => (string) $order->number,
        'customer_id'   => $order->customer_id ? (int) $order->customer_id : NULL,
        'status'        => (string) $order->status,
        'status_label'  => $order->status_label(),
        'source'        => (string) $order->source,
        'source_label'  => $order->source_label(),
        'currency'      => (string) $order->currency,
        'amount'        => (float) $order->amount,
        'warehouse_id'  => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
        'order_date'    => $order->order_date ? (string) $order->order_date : NULL,
        'ship_date'     => $order->ship_date ? (string) $order->ship_date : NULL,
        'days_left'     => $order->days_left(),
        'overdue'       => $order->is_overdue(),
        'note'          => $order->note ? (string) $order->note : NULL,
    );
};

// Компактный вид позиции заказа
$order_line_row = function ($line) {
    return array(
        'id'             => (int) $line->id,
        'line_no'        => (int) $line->line_no,
        'item_id'        => $line->item_id ? (int) $line->item_id : NULL,
        'name'           => (string) $line->name,
        'code'           => $line->code ? (string) $line->code : NULL,
        'qty'            => (float) $line->qty,
        'qty_reserved'   => (float) $line->qty_reserved,
        'qty_shipped'    => (float) $line->qty_shipped,
        'remaining'      => $line->remaining(),
        'uom_code'       => (string) $line->uom_code,
        'price'          => (float) $line->price,
        'discount'       => (float) $line->discount,
        'net_price'      => $line->net_price(),
        'amount'         => $line->amount(),
        'status'         => (string) $line->status,
        'status_label'   => $line->status_label(),
        'reservation_id' => $line->reservation_id ? (int) $line->reservation_id : NULL,
    );
};

// Компактный вид отгрузки
$shipment_row = function ($shipment) {
    return array(
        'id'           => (int) $shipment->id,
        'uuid'         => (string) $shipment->uuid,
        'number'       => (string) $shipment->number,
        'order_id'     => $shipment->order_id ? (int) $shipment->order_id : NULL,
        'customer_id'  => $shipment->customer_id ? (int) $shipment->customer_id : NULL,
        'warehouse_id' => $shipment->warehouse_id ? (int) $shipment->warehouse_id : NULL,
        'status'       => (string) $shipment->status,
        'status_label' => $shipment->status_label(),
        'shipment_date'=> $shipment->shipment_date ? (string) $shipment->shipment_date : NULL,
        'carrier'      => $shipment->carrier ? (string) $shipment->carrier : NULL,
        'posted_at'    => $shipment->posted_at ? (string) $shipment->posted_at : NULL,
    );
};

// Компактный вид счёта
$invoice_row = function ($invoice) {
    return array(
        'id'           => (int) $invoice->id,
        'uuid'         => (string) $invoice->uuid,
        'number'       => (string) $invoice->number,
        'order_id'     => $invoice->order_id ? (int) $invoice->order_id : NULL,
        'shipment_id'  => $invoice->shipment_id ? (int) $invoice->shipment_id : NULL,
        'customer_id'  => $invoice->customer_id ? (int) $invoice->customer_id : NULL,
        'status'       => (string) $invoice->status,
        'status_label' => $invoice->status_label(),
        'issue_date'   => $invoice->issue_date ? (string) $invoice->issue_date : NULL,
        'due_date'     => $invoice->due_date ? (string) $invoice->due_date : NULL,
        'amount'       => (float) $invoice->amount,
        'paid_amount'  => (float) $invoice->paid_amount,
        'outstanding'  => $invoice->outstanding(),
        'overdue'      => $invoice->is_overdue(),
    );
};

// Компактный вид клиента продажи (контрагент + условия)
$customer_row = function ($row) {
    $profile = isset($row['profile']) ? $row['profile'] : NULL;

    return array(
        'customer_id'   => (int) $row['customer_id'],
        'name'          => (string) $row['name'],
        'credit_days'   => $profile ? $profile->credit_days() : 0,
        'credit_limit'  => $profile ? (float) $profile->credit_limit : 0.0,
        'discount'      => $profile ? $profile->discount() : 0.0,
        'payment_terms' => $profile ? $profile->payment_label() : 'предоплата',
        'is_active'     => $profile ? $profile->is_active() : TRUE,
        'exposure'      => isset($row['exposure']) ? (float) $row['exposure'] : 0.0,
        'has_profile'   => (bool) $profile,
    );
};

return array(
    'module' => 'erp410',
    'tools'  => array(

        // ---------------------------------------------------------------- чтение --

        'sales.stats' => array(
            'title'       => 'Сводка продаж',
            'description' => 'Состояние продаж одним вызовом: сколько заказов в работе и на какую сумму, что просрочено по сроку отгрузки, сколько отгрузок собрано и ждёт проведения, сколько отгружено и на какую сумму за месяц, сколько счетов ждёт оплаты и что просрочено, как выглядит клиентская база.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $orders    = Service_Erp410_Orders::instance()->stats();
                $shipments = Service_Erp410_Shipments::instance()->stats();
                $invoices  = Service_Erp410_Invoices::instance()->stats();
                $customers = Service_Erp410_Customers::instance()->stats();

                return array(
                    'summary' => 'Заказов в работе: '.(int) $orders['open']
                        .' на '.number_format((float) $orders['amount_open'], 2, '.', ' ').' ₽'
                        .', просрочено: '.(int) $orders['overdue']
                        .', отгрузок готово: '.(int) $shipments['ready']
                        .', отгружено за месяц: '.(int) $shipments['shipped_month']
                        .' на '.number_format((float) $shipments['amount_month'], 2, '.', ' ').' ₽'
                        .', счетов ждут оплаты: '.(int) $invoices['issued']
                        .' (просрочено '.(int) $invoices['overdue'].')'
                        .', задолженность '.number_format((float) $invoices['outstanding'], 2, '.', ' ').' ₽',
                    'orders'     => $orders,
                    'shipments'  => $shipments,
                    'invoices'   => $invoices,
                    'customers'  => $customers,
                    'prices'     => Service_Erp410_Prices::instance()->stats(),
                );
            },
        ),

        'sales.orders.search' => array(
            'title'       => 'Поиск заказов клиентов',
            'description' => 'Заказы клиентов с фильтрами по состоянию, клиенту, тексту и сроку отгрузки. Заказ — это обещание клиенту: что, сколько и к какой дате продаём.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'      => $field('array', 'Состояния заказов', array('items' => array('type' => 'string', 'enum' => array('draft', 'confirmed', 'reserved', 'picking', 'shipped', 'closed', 'cancelled')))),
                'customer_id' => $field('integer', 'Заказы одного клиента (контрагент erp301)'),
                'open'        => $field('boolean', 'Только открытые: черновики, подтверждённые, с резервом и в сборке'),
                'overdue'     => $field('boolean', 'Только просроченные по дате отгрузки'),
                'search'      => $field('string', 'Поиск по номеру, клиенту, позиции и примечанию'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($order_row) {
                $orders = Service_Erp410_Orders::instance();

                $filters = array(
                    'status'      => isset($args['status']) ? (array) $args['status'] : NULL,
                    'customer_id' => isset($args['customer_id']) ? (int) $args['customer_id'] : NULL,
                    'open'        => ! empty($args['open']),
                    'overdue'     => ! empty($args['overdue']),
                    'search'      => isset($args['search']) ? (string) $args['search'] : NULL,
                );

                $limit  = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 20;
                $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

                $rows = array();

                foreach ($orders->search($filters, $limit, $offset) as $order)
                {
                    $rows[] = $order_row($order);
                }

                return array(
                    'summary' => 'Заказов по отбору: '.count($rows).' из '.$orders->count($filters),
                    'total'   => $orders->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'sales.order.get' => array(
            'title'       => 'Заказ клиента целиком',
            'description' => 'Заказ с позициями (сколько заказано, зарезервировано и отгружено), его отгрузками, счетами и проверкой кредитного лимита клиента. Основной инструмент, когда нужно понять, можно ли отгружать.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'order_id' => $field('integer', 'Идентификатор заказа', array('minimum' => 1)),
                'number'   => $field('string', 'Номер заказа (если идентификатор неизвестен)'),
            )),
            'handler'     => function (array $args) use ($order_row, $order_line_row, $shipment_row, $invoice_row) {
                $orders = Service_Erp410_Orders::instance();

                $key = isset($args['order_id']) ? (int) $args['order_id'] : (isset($args['number']) ? (string) $args['number'] : NULL);
                $order = $key ? $orders->get($key) : NULL;

                if ( ! $order)
                {
                    return array('summary' => 'Заказ не найден', 'found' => FALSE);
                }

                $lines = array();

                foreach ($orders->lines($order->id) as $line)
                {
                    $lines[] = $order_line_row($line);
                }

                $shipments = array();

                foreach (Service_Erp410_Shipments::instance()->for_order($order->id) as $shipment)
                {
                    $shipments[] = $shipment_row($shipment);
                }

                $invoices = array();

                foreach (Service_Erp410_Invoices::instance()->for_order($order->id) as $invoice)
                {
                    $invoices[] = $invoice_row($invoice);
                }

                $credit = $order->customer_id
                    ? Service_Erp410_Customers::instance()->credit_check($order->customer_id, (float) $order->amount)
                    : NULL;

                return array(
                    'summary' => 'Заказ '.$order->number.': '.$order->status_label()
                        .', позиций '.count($lines)
                        .', отгрузок '.count($shipments)
                        .', счетов '.count($invoices),
                    'found'     => TRUE,
                    'order'     => $order_row($order),
                    'lines'     => $lines,
                    'shipments' => $shipments,
                    'invoices'  => $invoices,
                    'credit'    => $credit,
                );
            },
        ),

        'sales.shipments.search' => array(
            'title'       => 'Поиск отгрузок',
            'description' => 'Отгрузки клиентам с фильтрами по состоянию, клиенту и заказу. Проведённая отгрузка означает, что товар списан со склада.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'      => $field('array', 'Состояния отгрузок', array('items' => array('type' => 'string', 'enum' => array('draft', 'ready', 'shipped', 'cancelled')))),
                'customer_id' => $field('integer', 'Отгрузки одного клиента'),
                'order_id'    => $field('integer', 'Отгрузки по заказу клиента'),
                'open'        => $field('boolean', 'Только непроведённые: черновики и готовые к отправке'),
                'search'      => $field('string', 'Поиск по номеру, перевозчику и адресу'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($shipment_row) {
                $shipments = Service_Erp410_Shipments::instance();

                $filters = array(
                    'status'      => isset($args['status']) ? (array) $args['status'] : NULL,
                    'customer_id' => isset($args['customer_id']) ? (int) $args['customer_id'] : NULL,
                    'order_id'    => isset($args['order_id']) ? (int) $args['order_id'] : NULL,
                    'open'        => ! empty($args['open']),
                    'search'      => isset($args['search']) ? (string) $args['search'] : NULL,
                );

                $limit  = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 20;
                $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

                $rows = array();

                foreach ($shipments->search($filters, $limit, $offset) as $shipment)
                {
                    $rows[] = $shipment_row($shipment);
                }

                return array(
                    'summary' => 'Отгрузок по отбору: '.count($rows).' из '.$shipments->count($filters),
                    'total'   => $shipments->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'sales.shipment.get' => array(
            'title'       => 'Отгрузка целиком',
            'description' => 'Отгрузка со строками (что везём и на какую сумму) и счётом, если он уже выставлен.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'shipment_id' => $field('integer', 'Идентификатор отгрузки', array('minimum' => 1)),
                'number'      => $field('string', 'Номер отгрузки (если идентификатор неизвестен)'),
            )),
            'handler'     => function (array $args) use ($shipment_row, $invoice_row) {
                $shipments = Service_Erp410_Shipments::instance();

                $key = isset($args['shipment_id']) ? (int) $args['shipment_id'] : (isset($args['number']) ? (string) $args['number'] : NULL);
                $shipment = $key ? $shipments->get($key) : NULL;

                if ( ! $shipment)
                {
                    return array('summary' => 'Отгрузка не найдена', 'found' => FALSE);
                }

                $lines = array();

                foreach ($shipments->lines($shipment->id) as $line)
                {
                    $lines[] = array(
                        'id'       => (int) $line->id,
                        'item_id'  => $line->item_id ? (int) $line->item_id : NULL,
                        'name'     => (string) $line->name,
                        'code'     => $line->code ? (string) $line->code : NULL,
                        'qty'      => (float) $line->qty,
                        'uom_code' => (string) $line->uom_code,
                        'price'    => (float) $line->price,
                        'amount'   => $line->amount(),
                    );
                }

                $invoice = Service_Erp410_Invoices::instance()->for_shipment($shipment->id);

                return array(
                    'summary'   => 'Отгрузка '.$shipment->number.': '.$shipment->status_label()
                        .', позиций '.count($lines)
                        .', на '.number_format($shipments->amount($shipment->id), 2, '.', ' ').' ₽'
                        .($invoice ? ', счёт '.$invoice->number : ', счёт не выставлен'),
                    'found'     => TRUE,
                    'shipment'  => $shipment_row($shipment),
                    'lines'     => $lines,
                    'invoice'   => $invoice ? $invoice_row($invoice) : NULL,
                );
            },
        ),

        'sales.invoices.search' => array(
            'title'       => 'Поиск счетов',
            'description' => 'Счета клиентам с фильтрами по состоянию, клиенту, неоплаченным и просроченным. Показывает, сколько денег ждёт оплаты и насколько она задержалась.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'      => $field('array', 'Состояния счетов', array('items' => array('type' => 'string', 'enum' => array('draft', 'issued', 'paid', 'cancelled')))),
                'customer_id' => $field('integer', 'Счета одного клиента'),
                'order_id'    => $field('integer', 'Счета по заказу'),
                'unpaid'      => $field('boolean', 'Только неоплаченные (выставленные)'),
                'overdue'     => $field('boolean', 'Только просроченные по дате оплаты'),
                'search'      => $field('string', 'Поиск по номеру и примечанию'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($invoice_row) {
                $invoices = Service_Erp410_Invoices::instance();

                $filters = array(
                    'status'      => isset($args['status']) ? (array) $args['status'] : NULL,
                    'customer_id' => isset($args['customer_id']) ? (int) $args['customer_id'] : NULL,
                    'order_id'    => isset($args['order_id']) ? (int) $args['order_id'] : NULL,
                    'unpaid'      => ! empty($args['unpaid']),
                    'overdue'     => ! empty($args['overdue']),
                    'search'      => isset($args['search']) ? (string) $args['search'] : NULL,
                );

                $limit  = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 20;
                $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

                $rows = array();

                foreach ($invoices->search($filters, $limit, $offset) as $invoice)
                {
                    $rows[] = $invoice_row($invoice);
                }

                return array(
                    'summary' => 'Счетов по отбору: '.count($rows).' из '.$invoices->count($filters),
                    'total'   => $invoices->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'sales.invoice.get' => array(
            'title'       => 'Счёт целиком',
            'description' => 'Счёт с позициями, суммой, оплаченным и остатком; показывает заказ и отгрузку, из которых он собран.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'invoice_id' => $field('integer', 'Идентификатор счёта', array('minimum' => 1)),
                'number'     => $field('string', 'Номер счёта (если идентификатор неизвестен)'),
            )),
            'handler'     => function (array $args) use ($invoice_row) {
                $invoices = Service_Erp410_Invoices::instance();

                $key = isset($args['invoice_id']) ? (int) $args['invoice_id'] : (isset($args['number']) ? (string) $args['number'] : NULL);
                $invoice = $key ? $invoices->get($key) : NULL;

                if ( ! $invoice)
                {
                    return array('summary' => 'Счёт не найден', 'found' => FALSE);
                }

                $lines = array();

                foreach ($invoices->lines($invoice->id) as $line)
                {
                    $lines[] = array(
                        'id'       => (int) $line->id,
                        'item_id'  => $line->item_id ? (int) $line->item_id : NULL,
                        'name'     => (string) $line->name,
                        'code'     => $line->code ? (string) $line->code : NULL,
                        'qty'      => (float) $line->qty,
                        'uom_code' => (string) $line->uom_code,
                        'price'    => (float) $line->price,
                        'amount'   => $line->amount(),
                    );
                }

                return array(
                    'summary' => 'Счёт '.$invoice->number.': '.$invoice->status_label()
                        .', сумма '.number_format((float) $invoice->amount, 2, '.', ' ').' ₽'
                        .', остаток '.number_format((float) $invoice->outstanding(), 2, '.', ' ').' ₽',
                    'found'   => TRUE,
                    'invoice' => $invoice_row($invoice),
                    'lines'   => $lines,
                );
            },
        ),

        'sales.customers.search' => array(
            'title'       => 'Поиск клиентов продажи',
            'description' => 'Покупатели (контрагенты erp301) с условиями продажи и открытой задолженностью. Кредитный лимит и отсрочка живут в профиле продажи.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'search'       => $field('string', 'Поиск по названию клиента'),
                'active'       => $field('boolean', 'Только действующие условия'),
                'with_profile' => $field('boolean', 'Только клиенты с условиями продажи'),
                'limit'        => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'       => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($customer_row) {
                $customers = Service_Erp410_Customers::instance();

                $filters = array(
                    'search'       => isset($args['search']) ? (string) $args['search'] : NULL,
                    'active'       => ! empty($args['active']),
                    'with_profile' => ! empty($args['with_profile']),
                );

                $limit  = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 20;
                $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

                $rows = array();

                foreach ($customers->search($filters, $limit, $offset) as $row)
                {
                    $rows[] = $customer_row($row);
                }

                return array(
                    'summary' => 'Клиентов по отбору: '.count($rows).' из '.$customers->count($filters),
                    'total'   => $customers->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'sales.customer.get' => array(
            'title'       => 'Клиент целиком',
            'description' => 'Карточка клиента продажи: условия (отсрочка, лимит, скидка), открытая задолженность, свободный остаток лимита, последние заказы и неоплаченные счета.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'customer_id' => $field('integer', 'Идентификатор контрагента-покупателя', array('minimum' => 1)),
            ), array('customer_id')),
            'handler'     => function (array $args) use ($customer_row, $order_row, $invoice_row) {
                $customer_id = (int) $args['customer_id'];

                $customers = Service_Erp410_Customers::instance();
                $profile   = $customers->profile($customer_id, FALSE);

                $orders = array();

                foreach (Service_Erp410_Orders::instance()->search(array('customer_id' => $customer_id), 10, 0) as $order)
                {
                    $orders[] = $order_row($order);
                }

                $invoices = array();

                foreach (Service_Erp410_Invoices::instance()->search(array('customer_id' => $customer_id, 'unpaid' => TRUE), 10, 0) as $invoice)
                {
                    $invoices[] = $invoice_row($invoice);
                }

                return array(
                    'summary' => 'Клиент «'.$customers->name($customer_id).'»: лимит '
                        .number_format((float) ($profile ? $profile->credit_limit : 0), 2, '.', ' ').' ₽, задолженность '
                        .number_format($customers->credit_exposure($customer_id), 2, '.', ' ').' ₽, заказов '.count($orders),
                    'customer'  => $customer_row(array(
                        'customer_id' => $customer_id,
                        'name'        => $customers->name($customer_id),
                        'profile'     => $profile,
                        'exposure'    => $customers->credit_exposure($customer_id),
                    )),
                    'credit'    => $customers->credit_check($customer_id, 0),
                    'orders'    => $orders,
                    'invoices'  => $invoices,
                );
            },
        ),

        'sales.credit.check' => array(
            'title'       => 'Проверка кредитного лимита',
            'description' => 'Можно ли продать клиенту на указанную сумму: лимит отсрочки, текущая задолженность, свободный остаток и причина отказа. Вызывать перед оформлением заказа без предоплаты.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'customer_id' => $field('integer', 'Идентификатор клиента', array('minimum' => 1)),
                'amount'      => $field('number', 'Сумма предполагаемой продажи, ₽', array('minimum' => 0)),
            ), array('customer_id')),
            'handler'     => function (array $args) {
                $customer_id = (int) $args['customer_id'];
                $amount      = isset($args['amount']) ? (float) $args['amount'] : 0.0;

                $credit = Service_Erp410_Customers::instance()->credit_check($customer_id, $amount);

                return array(
                    'summary' => ($credit['allowed'] ? 'Можно продавать' : 'Лимит не позволяет')
                        .': свободно '.number_format((float) $credit['available'], 2, '.', ' ').' ₽ из '
                        .number_format((float) $credit['limit'], 2, '.', ' ').' ₽ ('.$credit['reason'].')',
                ) + $credit + array('customer_id' => $customer_id, 'amount' => $amount);
            },
        ),

        'sales.due' => array(
            'title'       => 'Что отгрузить на неделе',
            'description' => 'Заказы со сроком отгрузки в ближайшие дни и уже просроченные — рабочий список «что везти». Возвращает заказы, сгруппированные по срочности.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'days'  => $field('integer', 'Горизонт в днях (по умолчанию 7)', array('minimum' => 1, 'maximum' => 60)),
                'limit' => $field('integer', 'Сколько заказов вернуть (по умолчанию 20, максимум 100)', array('minimum' => 1, 'maximum' => 100)),
            )),
            'handler'     => function (array $args) use ($order_row) {
                $orders = Service_Erp410_Orders::instance();

                $days  = isset($args['days']) ? min(60, max(1, (int) $args['days'])) : 7;
                $limit = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 20;

                $soon = array();

                foreach ($orders->due_soon($days, $limit) as $order)
                {
                    $soon[] = $order_row($order);
                }

                $overdue = array();

                foreach ($orders->search(array('overdue' => TRUE), $limit, 0) as $order)
                {
                    $overdue[] = $order_row($order);
                }

                return array(
                    'summary' => 'Просрочено: '.count($overdue).', в ближайшие '.$days.' дн.: '.count($soon)
                        .', сумма ближайших '.number_format(array_sum(array_map(function ($row) { return $row['amount']; }, $soon)), 2, '.', ' ').' ₽',
                    'days'    => $days,
                    'overdue' => $overdue,
                    'due_soon'=> $soon,
                );
            },
        ),

        'sales.price.get' => array(
            'title'       => 'Цена позиции для клиента',
            'description' => 'По какой цене продавать: договорная цена клиента, базовая цена прайса или цена последней отгрузки — с основанием, скидкой и итоговой ценой. Вызывать перед добавлением позиции в заказ.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'item_id'     => $field('integer', 'Позиция номенклатуры (erp227)', array('minimum' => 1)),
                'customer_id' => $field('integer', 'Клиент: если не указан, вернётся базовая цена', array('minimum' => 1)),
            ), array('item_id')),
            'handler'     => function (array $args) {
                $item_id     = (int) $args['item_id'];
                $customer_id = isset($args['customer_id']) ? (int) $args['customer_id'] : NULL;

                $price = Service_Erp410_Prices::instance()->price_for($item_id, $customer_id ?: NULL);

                return array(
                    'summary' => $price['found']
                        ? 'Цена '.number_format((float) $price['net_price'], 2, '.', ' ').' ₽ ('.$price['source_label'].', '.$price['basis'].')'
                        : 'Цены нет: ни прайса, ни истории отгрузок по этой позиции',
                ) + $price + array('item_id' => $item_id, 'customer_id' => $customer_id ?: NULL);
            },
        ),

        'sales.price.history' => array(
            'title'       => 'История цен позиции',
            'description' => 'Все записи прайса по позиции: когда, для какого клиента, по какой цене и из какого источника (вручную, из заказа, отгрузки или счёта). Показывает, как менялась цена.',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'item_id' => $field('integer', 'Позиция номенклатуры (erp227)', array('minimum' => 1)),
                'limit'   => $field('integer', 'Сколько записей вернуть (по умолчанию 25)', array('minimum' => 1, 'maximum' => 100)),
            ), array('item_id')),
            'handler'     => function (array $args) {
                $item_id = (int) $args['item_id'];
                $limit   = isset($args['limit']) ? min(100, max(1, (int) $args['limit'])) : 25;

                $rows = array();

                foreach (Service_Erp410_Prices::instance()->history($item_id, $limit) as $entry)
                {
                    $rows[] = array(
                        'id'          => (int) $entry->id,
                        'customer_id' => $entry->customer_id ? (int) $entry->customer_id : NULL,
                        'price'       => (float) $entry->price,
                        'discount'    => (float) $entry->discount,
                        'net_price'   => $entry->net_price(),
                        'currency'    => (string) $entry->currency,
                        'source'      => (string) $entry->source,
                        'source_label'=> $entry->source_label(),
                        'valid_from'  => $entry->valid_from ? (string) $entry->valid_from : NULL,
                        'created_at'  => (string) $entry->created_at,
                    );
                }

                $last = $rows ? $rows[0] : NULL;

                return array(
                    'summary' => $rows
                        ? 'Записей: '.count($rows).', последняя '
                            .number_format((float) $last['net_price'], 2, '.', ' ').' '.$last['currency']
                            .' ('.$last['source_label'].')'
                        : 'История цен по позиции пуста',
                    'item_id' => $item_id,
                    'rows'    => $rows,
                );
            },
        ),

        'sales.catalog.prices' => array(
            'title'       => 'Прайс каталога для клиента',
            'description' => 'Цены всех позиций каталога для выбранного клиента: договорная цена, базовый прайс или цена последней отгрузки. Показывает, по каким позициям цены вообще нет, — это риск продать «за ноль».',
            'scope'       => 'erp410.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'customer_id' => $field('integer', 'Клиент: если не указан, вернётся базовый прайс', array('minimum' => 1)),
                'limit'       => $field('integer', 'Сколько позиций вернуть (по умолчанию 50)', array('minimum' => 1, 'maximum' => 200)),
            )),
            'handler'     => function (array $args) {
                $customer_id = isset($args['customer_id']) ? (int) $args['customer_id'] : NULL;
                $limit       = isset($args['limit']) ? min(200, max(1, (int) $args['limit'])) : 50;

                $rows = array();
                $missing = 0;

                if (class_exists('Service_Erp401_Stock'))
                {
                    $prices = Service_Erp410_Prices::instance();

                    foreach (Service_Erp401_Stock::instance()->catalog($limit) as $item)
                    {
                        $price = $prices->price_for((int) $item['id'], $customer_id ?: NULL);

                        if ( ! $price['found'])
                        {
                            $missing++;
                        }

                        $rows[] = array(
                            'item_id'      => (int) $item['id'],
                            'code'         => (string) $item['code'],
                            'name'         => (string) $item['name'],
                            'price'        => (float) $price['price'],
                            'discount'     => (float) $price['discount'],
                            'net_price'    => (float) $price['net_price'],
                            'currency'     => (string) $price['currency'],
                            'source'       => $price['source'],
                            'source_label' => $price['source_label'],
                            'basis'        => $price['basis'],
                            'found'        => (bool) $price['found'],
                        );
                    }
                }

                return array(
                    'summary' => 'Позиций: '.count($rows).', без цены: '.$missing,
                    'customer_id' => $customer_id ?: NULL,
                    'missing'     => $missing,
                    'rows'        => $rows,
                );
            },
        ),
    ),
);
