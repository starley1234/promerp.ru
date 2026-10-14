<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для закупок (erp403_procurement).
 *
 * Агент видит то же, что видит закупщик: заявки и их позиции, заказы
 * поставщикам, приёмки и входной контроль, историю цен и условия поставщиков.
 * И может сделать то, что закупщик нажимает: завести заявку, собрать заказы из
 * согласованных заявок, отправить заказ, оформить приёмку и провести её на
 * склад.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение (сводка, заявки, заказы, приёмки, поставщики, цены, дефицит) —
 *    `erp403.view`;
 *  - черновики и подготовка документов (создать заявку, добавить позицию,
 *    отправить на согласование, собрать заказ, оформить приёмку, условия
 *    поставщика, запись цены) — `erp403.manage`;
 *  - решение по деньгам (согласовать или отклонить заявку, провести приёмку) —
 *    `erp403.approve`;
 *  - отправка заказа поставщику и отмена уже отправленного заказа — под
 *    подтверждением человека (`approval`): это необратимые шаги наружу, агент
 *    готовит документ, решение принимает человек.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp403_Requests`,
 * `Service_Erp403_Orders`, `Service_Erp403_Receipts`,
 * `Service_Erp403_Suppliers`), а не пишут в таблицы: правила переходов,
 * журнал и события остаются в одном месте.
 *
 * @package    Erp403
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

// Компактный вид заявки: одинаковый во всех инструментах
$request_row = function ($request) {
    return array(
        'id'         => (int) $request->id,
        'uuid'       => (string) $request->uuid,
        'number'     => (string) $request->number,
        'status'     => (string) $request->status,
        'status_label' => $request->status_label(),
        'priority'   => (int) $request->priority,
        'priority_label' => $request->priority_label(),
        'source'     => (string) $request->source,
        'source_label' => $request->source_label(),
        'source_ref' => $request->source_ref ? (string) $request->source_ref : NULL,
        'warehouse_id' => $request->warehouse_id ? (int) $request->warehouse_id : NULL,
        'need_date'  => $request->need_date ? (string) $request->need_date : NULL,
        'overdue'    => $request->is_overdue(),
        'days_left'  => $request->days_left(),
        'order_id'   => $request->order_id ? (int) $request->order_id : NULL,
        'note'       => $request->note ? (string) $request->note : NULL,
    );
};

// Компактный вид заказа
$order_row = function ($order) {
    return array(
        'id'            => (int) $order->id,
        'uuid'          => (string) $order->uuid,
        'number'        => (string) $order->number,
        'status'        => (string) $order->status,
        'status_label'  => $order->status_label(),
        'supplier_id'   => $order->supplier_id ? (int) $order->supplier_id : NULL,
        'request_id'    => $order->request_id ? (int) $order->request_id : NULL,
        'warehouse_id'  => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
        'currency'      => (string) $order->currency,
        'amount'        => (float) $order->amount,
        'delivery_date' => $order->delivery_date ? (string) $order->delivery_date : NULL,
        'payment_terms' => $order->payment_terms ? (string) $order->payment_terms : NULL,
        'overdue'       => $order->is_overdue(),
        'closed'        => $order->is_closed(),
        'sent_at'       => $order->sent_at ? (string) $order->sent_at : NULL,
        'note'          => $order->note ? (string) $order->note : NULL,
    );
};

// Компактный вид приёмки
$receipt_row = function ($receipt) {
    return array(
        'id'           => (int) $receipt->id,
        'uuid'         => (string) $receipt->uuid,
        'number'       => (string) $receipt->number,
        'status'       => (string) $receipt->status,
        'status_label' => $receipt->status_label(),
        'order_id'     => $receipt->order_id ? (int) $receipt->order_id : NULL,
        'supplier_id'  => $receipt->supplier_id ? (int) $receipt->supplier_id : NULL,
        'warehouse_id' => (int) $receipt->warehouse_id,
        'doc_number'   => $receipt->doc_number ? (string) $receipt->doc_number : NULL,
        'doc_date'     => $receipt->doc_date ? (string) $receipt->doc_date : NULL,
        'received_at'  => $receipt->received_at ? (string) $receipt->received_at : NULL,
        'posted_at'    => $receipt->posted_at ? (string) $receipt->posted_at : NULL,
        'note'         => $receipt->note ? (string) $receipt->note : NULL,
    );
};

$request_line_row = function ($line) {
    return array(
        'id'          => (int) $line->id,
        'line_no'     => (int) $line->line_no,
        'item_id'     => $line->item_id ? (int) $line->item_id : NULL,
        'name'        => (string) $line->name,
        'code'        => $line->code ? (string) $line->code : NULL,
        'qty'         => (float) $line->qty,
        'uom_code'    => (string) $line->uom_code,
        'price'       => (float) $line->price,
        'amount'      => $line->amount(),
        'supplier_id' => $line->supplier_id ? (int) $line->supplier_id : NULL,
        'need_date'   => $line->due_date(),
        'status'      => (string) $line->status,
        'open'        => $line->is_open(),
    );
};

$order_line_row = function ($line) {
    return array(
        'id'            => (int) $line->id,
        'line_no'       => (int) $line->line_no,
        'item_id'       => $line->item_id ? (int) $line->item_id : NULL,
        'name'          => (string) $line->name,
        'qty'           => (float) $line->qty,
        'qty_received'  => (float) $line->qty_received,
        'remaining'     => $line->remaining(),
        'price'         => (float) $line->price,
        'amount'        => $line->amount(),
        'uom_code'      => (string) $line->uom_code,
        'delivery_date' => $line->delivery_date ? (string) $line->delivery_date : NULL,
        'status'        => (string) $line->status,
        'progress'      => $line->progress(),
    );
};

$receipt_line_row = function ($line) {
    return array(
        'id'             => (int) $line->id,
        'order_line_id'  => $line->order_line_id ? (int) $line->order_line_id : NULL,
        'item_id'        => $line->item_id ? (int) $line->item_id : NULL,
        'name'           => (string) $line->name,
        'qty'            => (float) $line->qty,
        'price'          => (float) $line->price,
        'amount'         => $line->amount(),
        'uom_code'       => (string) $line->uom_code,
        'quality_status' => (string) $line->quality_status,
        'quality_label'  => $line->quality_label(),
        'blocked'        => $line->is_blocked(),
    );
};

$supplier_row = function ($supplier_id, $counterparty, $profile) {
    return array(
        'supplier_id'    => (int) $supplier_id,
        'name'           => $counterparty ? (string) $counterparty->name : 'Поставщик #'.$supplier_id,
        'inn'            => ($counterparty AND $counterparty->inn) ? (string) $counterparty->inn : NULL,
        'city'           => ($counterparty AND $counterparty->city) ? (string) $counterparty->city : NULL,
        'lead_time_days' => $profile ? $profile->lead_time() : NULL,
        'currency'       => $profile ? (string) $profile->currency : NULL,
        'min_order'      => $profile ? (float) $profile->min_order : NULL,
        'rating'         => $profile ? (int) $profile->rating : NULL,
        'preferred'      => $profile ? $profile->is_preferred() : NULL,
        'active'         => $profile ? $profile->is_active() : NULL,
        'payment_terms'  => ($profile AND $profile->payment_terms) ? (string) $profile->payment_terms : NULL,
    );
};

return array(
    'module' => 'erp403',
    'tools'  => array(

        // ---------------------------------------------------------------- чтение --

        'procurement.stats' => array(
            'title'       => 'Сводка закупок',
            'description' => 'Состояние закупок одним вызовом: сколько заявок ждут решения, сколько заказов в работе и на какую сумму, сколько приёмок ждёт проведения, что заблокировано входным контролем и сколько позиций на минимуме склада.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $requests = Service_Erp403_Requests::instance()->stats();
                $orders   = Service_Erp403_Orders::instance()->stats();
                $receipts = Service_Erp403_Receipts::instance()->stats();
                $suppliers = Service_Erp403_Suppliers::instance()->stats();

                $low = array();

                if (class_exists('Service_Erp401_Stock'))
                {
                    try
                    {
                        $low = Service_Erp401_Stock::instance()->low_stock(200);
                    }
                    catch (Exception $e)
                    {
                        $low = array();
                    }
                }

                return array(
                    'summary'   => 'Заявок к решению: '.(int) $requests['submitted']
                        .', заказов в работе: '.(int) $orders['open']
                        .' на '.number_format((float) $orders['amount_open'], 2, '.', ' ').' ₽'
                        .', приёмок к проведению: '.(int) $receipts['draft']
                        .', позиций на минимуме: '.count($low),
                    'requests'  => $requests,
                    'orders'    => $orders,
                    'receipts'  => $receipts,
                    'suppliers' => $suppliers,
                    'low_stock' => array('count' => count($low)),
                );
            },
        ),

        'procurement.requests.search' => array(
            'title'       => 'Поиск заявок',
            'description' => 'Заявки на закупку с фильтрами по состоянию, источнику, складу, позиции и тексту. Заявка — это потребность: что купить и к какому сроку.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'       => $field('array', 'Состояния заявок', array('items' => array('type' => 'string', 'enum' => array('draft', 'submitted', 'approved', 'rejected', 'ordered', 'cancelled')))),
                'source'       => $field('string', 'Источник: manual, auto, erp407, import', array('enum' => array('manual', 'auto', 'erp407', 'import'))),
                'warehouse_id' => $field('integer', 'Заявки одного склада'),
                'item_id'      => $field('integer', 'Заявки, в которых есть эта позиция номенклатуры'),
                'overdue'      => $field('boolean', 'Только просроченные по сроку «нужен к»'),
                'search'       => $field('string', 'Поиск по номеру, позиции и пояснению'),
                'limit'        => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'       => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($request_row) {
                $requests = Service_Erp403_Requests::instance();

                $filters = array(
                    'status'       => isset($args['status']) ? $args['status'] : NULL,
                    'source'       => isset($args['source']) ? $args['source'] : NULL,
                    'warehouse_id' => isset($args['warehouse_id']) ? (int) $args['warehouse_id'] : NULL,
                    'item_id'      => isset($args['item_id']) ? (int) $args['item_id'] : NULL,
                    'overdue'      => ! empty($args['overdue']),
                    'search'       => isset($args['search']) ? $args['search'] : NULL,
                );

                $rows = array();

                foreach ($requests->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $request)
                {
                    $rows[] = $request_row($request);
                }

                $stats = $requests->stats();

                return array(
                    'summary'  => 'Найдено заявок: '.$requests->count($filters)
                        .' (к решению '.(int) $stats['submitted'].', просрочено '.(int) $stats['overdue'].')',
                    'total'    => (int) $requests->count($filters),
                    'statuses' => Model_Erp403_Request::$statuses,
                    'sources'  => Model_Erp403_Request::$sources,
                    'requests' => $rows,
                );
            },
        ),

        'procurement.request.get' => array(
            'title'       => 'Заявка целиком',
            'description' => 'Заявка, её позиции и заказ, в который она попала: видно, что уже закрыто, а что ещё ждёт решения.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заявки'),
            ), array('id')),
            'handler'     => function (array $args) use ($request_row, $request_line_row) {
                $request = Service_Erp403_Requests::instance()->get($args['id']);

                if ( ! $request)
                {
                    throw new Exception_Erp403_Procurement('Заявка не найдена', NULL, 'not_found');
                }

                $lines = array();

                foreach (Service_Erp403_Requests::instance()->lines($request->id) as $line)
                {
                    $lines[] = $request_line_row($line);
                }

                $order = $request->order_id ? Service_Erp403_Orders::instance()->get($request->order_id) : NULL;

                return array(
                    'summary' => 'Заявка '.$request->number.' ('.$request->status_label().'): позиций '.count($lines)
                        .($order ? ', заказ '.$order->number : ', заказа ещё нет'),
                    'request' => $request_row($request),
                    'lines'   => $lines,
                    'order'   => $order ? array('id' => (int) $order->id, 'number' => (string) $order->number, 'status' => (string) $order->status) : NULL,
                    'totals'  => array(
                        'lines'  => count($lines),
                        'qty'    => array_sum(array_column($lines, 'qty')),
                        'amount' => array_sum(array_column($lines, 'amount')),
                    ),
                );
            },
        ),

        'procurement.orders.search' => array(
            'title'       => 'Поиск заказов',
            'description' => 'Заказы поставщикам с фильтрами по состоянию, поставщику и тексту. Заказ — это обязательство: что и у кого куплено и к какому сроку привезут.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'      => $field('array', 'Состояния заказов', array('items' => array('type' => 'string', 'enum' => array('draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled')))),
                'supplier_id' => $field('integer', 'Заказы одного поставщика'),
                'open'        => $field('boolean', 'Только незакрытые (черновик, отправлен, подтверждён, частично принят)'),
                'overdue'     => $field('boolean', 'Только просроченные по дате поставки'),
                'search'      => $field('string', 'Поиск по номеру и позициям'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($order_row) {
                $orders = Service_Erp403_Orders::instance();

                $filters = array(
                    'status'      => isset($args['status']) ? $args['status'] : NULL,
                    'supplier_id' => isset($args['supplier_id']) ? (int) $args['supplier_id'] : NULL,
                    'open'        => ! empty($args['open']),
                    'overdue'     => ! empty($args['overdue']),
                    'search'      => isset($args['search']) ? $args['search'] : NULL,
                );

                $rows = array();

                foreach ($orders->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $order)
                {
                    $rows[] = $order_row($order);
                }

                $stats = $orders->stats();

                return array(
                    'summary'  => 'Найдено заказов: '.$orders->count($filters)
                        .' (в работе '.(int) $stats['open'].', просрочено '.(int) $stats['overdue'].')',
                    'total'    => (int) $orders->count($filters),
                    'statuses' => Model_Erp403_Order::$statuses,
                    'orders'   => $rows,
                );
            },
        ),

        'procurement.order.get' => array(
            'title'       => 'Заказ целиком',
            'description' => 'Заказ, его позиции с прогрессом приёмки, приёмки по заказу и заявка-источник. Главный инструмент, чтобы понять, что ещё не приехало.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заказа'),
            ), array('id')),
            'handler'     => function (array $args) use ($order_row, $order_line_row, $receipt_row) {
                $order = Service_Erp403_Orders::instance()->get($args['id']);

                if ( ! $order)
                {
                    throw new Exception_Erp403_Procurement('Заказ не найден', NULL, 'not_found');
                }

                $lines = array();

                foreach (Service_Erp403_Orders::instance()->lines($order->id) as $line)
                {
                    $lines[] = $order_line_row($line);
                }

                $receipts = array();

                foreach (Service_Erp403_Receipts::instance()->search(array('order_id' => (int) $order->id), 50, 0) as $receipt)
                {
                    $receipts[] = $receipt_row($receipt);
                }

                $progress = Service_Erp403_Receipts::instance()->order_progress($order->id);

                return array(
                    'summary' => 'Заказ '.$order->number.' ('.$order->status_label().'): позиций '.count($lines)
                        .', принято '.$progress['progress'].' % на '.number_format((float) $order->amount, 2, '.', ' ').' '.$order->currency,
                    'order'    => $order_row($order),
                    'lines'    => $lines,
                    'receipts' => $receipts,
                    'progress' => $progress,
                    'supplier' => Service_Erp403_Suppliers::instance()->profile($order->supplier_id, FALSE)
                        ? array(
                            'supplier_id' => (int) $order->supplier_id,
                            'name'        => Service_Erp403_Suppliers::instance()->name($order->supplier_id),
                            'lead_time'   => Service_Erp403_Suppliers::instance()->profile($order->supplier_id, FALSE)->lead_time(),
                        )
                        : NULL,
                );
            },
        ),

        'procurement.receipts.search' => array(
            'title'       => 'Поиск приёмок',
            'description' => 'Приёмки с фильтрами по состоянию, поставщику, заказу и тексту. Видно, что ещё не проведено на склад и что заблокировано входным контролем.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'      => $field('array', 'Состояния приёмок', array('items' => array('type' => 'string', 'enum' => array('draft', 'posted', 'cancelled')))),
                'supplier_id' => $field('integer', 'Приёмки одного поставщика'),
                'order_id'    => $field('integer', 'Приёмки по заказу'),
                'search'      => $field('string', 'Поиск по номеру, документу поставщика и позициям'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($receipt_row) {
                $receipts = Service_Erp403_Receipts::instance();

                $filters = array(
                    'status'      => isset($args['status']) ? $args['status'] : NULL,
                    'supplier_id' => isset($args['supplier_id']) ? (int) $args['supplier_id'] : NULL,
                    'order_id'    => isset($args['order_id']) ? (int) $args['order_id'] : NULL,
                    'search'      => isset($args['search']) ? $args['search'] : NULL,
                );

                $rows = array();

                foreach ($receipts->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $receipt)
                {
                    $rows[] = $receipt_row($receipt);
                }

                $stats = $receipts->stats();

                return array(
                    'summary'  => 'Найдено приёмок: '.$receipts->count($filters)
                        .' (к проведению '.(int) $stats['draft'].', заблокировано контролем '.(int) $stats['quality_blocked'].')',
                    'total'    => (int) $receipts->count($filters),
                    'statuses' => Model_Erp403_Receipt::$statuses,
                    'receipts' => $rows,
                );
            },
        ),

        'procurement.receipt.get' => array(
            'title'       => 'Приёмка целиком',
            'description' => 'Приёмка, её позиции с результатом входного контроля и документ поставщика. Показывает, что мешает провести приход на склад.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid приёмки'),
            ), array('id')),
            'handler'     => function (array $args) use ($receipt_row, $receipt_line_row) {
                $receipt = Service_Erp403_Receipts::instance()->get($args['id']);

                if ( ! $receipt)
                {
                    throw new Exception_Erp403_Procurement('Приёмка не найдена', NULL, 'not_found');
                }

                $lines  = array();
                $total  = 0.0;
                $blocked = array();

                foreach (Service_Erp403_Receipts::instance()->lines($receipt->id) as $line)
                {
                    $row    = $receipt_line_row($line);
                    $lines[] = $row;
                    $total  += (float) $row['amount'];

                    if ($row['blocked'])
                    {
                        $blocked[] = (string) $line->name;
                    }
                    elseif ( ! $row['item_id'])
                    {
                        $blocked[] = (string) $line->name.' (без номенклатуры)';
                    }
                }

                return array(
                    'summary' => 'Приёмка '.$receipt->number.' ('.$receipt->status_label().'): позиций '.count($lines)
                        .' на '.number_format($total, 2, '.', ' ').' ₽'
                        .($blocked ? ', не пройдёт: '.implode(', ', $blocked) : ''),
                    'receipt' => $receipt_row($receipt),
                    'lines'   => $lines,
                    'totals'  => array('lines' => count($lines), 'amount' => round($total, 2)),
                    'blockers'=> $blocked,
                );
            },
        ),

        'procurement.suppliers.search' => array(
            'title'       => 'Поиск поставщиков',
            'description' => 'Поставщики — контрагенты с ролью «поставщик» (erp301) вместе с закупочными условиями: срок поставки, минимальная партия, оценка, отсрочка, метка «предпочтительный».',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'search'    => $field('string', 'Поиск по названию и ИНН'),
                'preferred' => $field('boolean', 'Только предпочтительные поставщики'),
                'active'    => $field('boolean', 'Только действующие (false — только отключённые)'),
                'limit'     => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'    => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($supplier_row) {
                $suppliers = Service_Erp403_Suppliers::instance();

                $filters = array(
                    'search'    => isset($args['search']) ? $args['search'] : NULL,
                    'preferred' => ! empty($args['preferred']),
                );

                if (array_key_exists('active', $args) AND $args['active'] !== NULL)
                {
                    $filters['active'] = (bool) $args['active'];
                }

                $rows = array();

                foreach ($suppliers->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $row)
                {
                    $supplier_id = (int) $row['counterparty']->id;
                    $rows[] = $supplier_row($supplier_id, $row['counterparty'], $row['profile']) + array(
                        'goods'         => (int) $row['goods'],
                        'last_price_at' => $row['last_price_at'] ? (string) $row['last_price_at'] : NULL,
                    );
                }

                $stats = $suppliers->stats();

                return array(
                    'summary'   => 'Найдено поставщиков: '.$suppliers->count($filters)
                        .' (предпочтительных '.(int) $stats['preferred'].', оценок цен '.(int) $stats['prices'].')',
                    'total'     => (int) $suppliers->count($filters),
                    'ratings'   => Model_Erp403_Supplier::$ratings,
                    'suppliers' => $rows,
                );
            },
        ),

        'procurement.suppliers.compare' => array(
            'title'       => 'Сравнить поставщиков по позиции',
            'description' => 'У кого брать позицию: по каждому поставщику — последняя цена, срок, исполнительность и баллы (цена до 50, срок до 30, оценка до 15, исполнительность до 15, предпочтительный +10, партия меньше минимальной −20). Первая строка — рекомендация.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'item_id' => $field('integer', 'Позиция номенклатуры (erp227)'),
                'qty'     => $field('number', 'Количество — проверка минимальной партии'),
            ), array('item_id')),
            'handler'     => function (array $args) {
                $suppliers = Service_Erp403_Suppliers::instance();
                $item_id   = (int) $args['item_id'];
                $qty       = array_key_exists('qty', $args) ? (float) $args['qty'] : NULL;

                $rows = $suppliers->compare($item_id, $qty);
                $best = $rows ? $rows[0] : NULL;

                return array(
                    'summary'     => $rows
                        ? 'Позиция «'.$suppliers->item_name($item_id).'»: поставщиков '.count($rows).', рекомендуем '.$best['name']
                            .' (' .number_format((float) $best['price'], 2, '.', ' ').' ₽, '.$best['lead_time'].' дн.)'
                        : 'По позиции #'.$item_id.' истории цен нет — сравнивать нечего',
                    'item_id'     => $item_id,
                    'item_name'   => $suppliers->item_name($item_id),
                    'qty'         => $qty,
                    'recommended' => $best,
                    'suppliers'   => $rows,
                    'history'     => $suppliers->history($item_id, 10),
                );
            },
        ),

        'procurement.prices.history' => array(
            'title'       => 'История цен',
            'description' => 'Как менялись цены: по позиции номенклатуры (все поставщики) или по поставщику (всё, что он поставляет). Источник записи — заказ, приёмка или ручной ввод.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'item_id'     => $field('integer', 'Цены по позиции номенклатуры'),
                'supplier_id' => $field('integer', 'Цены одного поставщика'),
                'limit'       => $field('integer', 'Сколько записей вернуть (по умолчанию 30)', array('minimum' => 1, 'maximum' => 200)),
            )),
            'handler'     => function (array $args) {
                $suppliers = Service_Erp403_Suppliers::instance();
                $limit     = isset($args['limit']) ? (int) $args['limit'] : 30;

                if ( ! empty($args['item_id']))
                {
                    $rows = $suppliers->history((int) $args['item_id'], $limit);

                    return array(
                        'summary' => 'Записей цен по позиции #'.(int) $args['item_id'].': '.count($rows),
                        'item_id' => (int) $args['item_id'],
                        'item_name' => $suppliers->item_name((int) $args['item_id']),
                        'prices'  => $rows,
                    );
                }

                if ( ! empty($args['supplier_id']))
                {
                    $rows = $suppliers->supplier_prices((int) $args['supplier_id'], $limit);

                    return array(
                        'summary'     => 'Записей цен поставщика #'.(int) $args['supplier_id'].': '.count($rows),
                        'supplier_id' => (int) $args['supplier_id'],
                        'name'        => $suppliers->name((int) $args['supplier_id']),
                        'prices'      => $rows,
                    );
                }

                throw new Exception_Erp403_Procurement('Укажите item_id или supplier_id', NULL, 'validation');
            },
        ),

        'procurement.shortages' => array(
            'title'       => 'Дефицит склада',
            'description' => 'Позиции, остаток по которым опустился до минимума: сколько не хватает, сколько предложено закупить (минимум с запасом 20 %) и есть ли уже открытая заявка — чтобы не заказать дважды.',
            'scope'       => 'erp403.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'warehouse_id' => $field('integer', 'Только один склад'),
                'limit'        => $field('integer', 'Сколько позиций вернуть (по умолчанию 50)', array('minimum' => 1, 'maximum' => 200)),
            )),
            'handler'     => function (array $args) {
                $rows = Service_Erp403_Requests::instance()->low_stock_rows(isset($args['limit']) ? (int) $args['limit'] : 50);

                $result = array();

                foreach ($rows as $row)
                {
                    if ( ! empty($args['warehouse_id']) AND (int) $row['warehouse_id'] !== (int) $args['warehouse_id'])
                    {
                        continue;
                    }

                    $result[] = array(
                        'item_id'        => (int) $row['item_id'],
                        'name'           => (string) $row['name'],
                        'code'           => $row['code'],
                        'warehouse_id'   => (int) $row['warehouse_id'],
                        'warehouse_name' => $row['warehouse_name'],
                        'qty'            => (float) $row['qty'],
                        'min_qty'        => (float) $row['min_qty'],
                        'deficit_qty'    => (float) $row['deficit_qty'],
                        'suggested_qty'  => (int) $row['suggested_qty'],
                        'uom_code'       => (string) $row['uom_code'],
                        'request_exists' => (bool) $row['request_exists'],
                    );
                }

                $waiting = count(array_filter($result, function ($row) { return ! $row['request_exists']; }));

                return array(
                    'summary' => 'Позиций на минимуме: '.count($result).', без открытой заявки: '.$waiting,
                    'rows'    => $result,
                    'waiting' => $waiting,
                );
            },
        ),

        // ----------------------------------------------------------------- заявки --

        'procurement.request.create' => array(
            'title'       => 'Создать заявку',
            'description' => 'Заводит заявку на закупку: одна позиция (item_id/name/qty/uom_code) или список строк в lines. Черновик можно править, согласование — отдельный шаг.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'item_id'      => $field('integer', 'Позиция номенклатуры (erp227)'),
                'name'         => $field('string', 'Название позиции, если номенклатура не заведена'),
                'qty'          => $field('number', 'Количество', array('exclusiveMinimum' => TRUE, 'minimum' => 0)),
                'uom_code'     => $field('string', 'Единица измерения (по умолчанию шт)'),
                'price'        => $field('number', 'Ожидаемая цена за единицу'),
                'need_date'    => $field('string', 'Нужен к дате (ГГГГ-ММ-ДД)'),
                'priority'     => $field('integer', 'Приоритет: 1 — аварийная, 2 — высокая, 3 — обычная, 4 — плановая', array('minimum' => 1, 'maximum' => 4)),
                'warehouse_id' => $field('integer', 'Склад, для которого закупаем'),
                'source'       => $field('string', 'Источник: manual, auto, erp407, import', array('enum' => array('manual', 'auto', 'erp407', 'import'))),
                'source_ref'   => $field('string', 'Ссылка на источник (программа, минимум склада)'),
                'note'         => $field('string', 'Пояснение для согласующего'),
                'lines'        => $field('array', 'Позиции заявки: item_id, name, qty, uom_code, price, supplier_id, note', array('items' => array('type' => 'object'))),
            ), array('qty')),
            'handler'     => function (array $args) use ($request_row) {
                $request = Service_Erp403_Requests::instance()->create($args, NULL);

                return array(
                    'summary' => 'Заявка '.$request->number.' создана: позиций '.count(Service_Erp403_Requests::instance()->lines($request->id)),
                ) + $request_row($request);
            },
        ),

        'procurement.request.line.add' => array(
            'title'       => 'Добавить позицию в заявку',
            'description' => 'Добавляет позицию в черновик заявки. Позиции без номенклатуры допустимы — их потом сопоставят вручную.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'request_id'  => $field('string', 'Идентификатор или uuid заявки'),
                'item_id'     => $field('integer', 'Позиция номенклатуры (erp227)'),
                'name'        => $field('string', 'Название позиции'),
                'qty'         => $field('number', 'Количество', array('minimum' => 0)),
                'uom_code'    => $field('string', 'Единица измерения'),
                'price'       => $field('number', 'Ожидаемая цена за единицу'),
                'supplier_id' => $field('integer', 'Желаемый поставщик (erp301)'),
                'need_date'   => $field('string', 'Нужен к дате (ГГГГ-ММ-ДД)'),
                'note'        => $field('string', 'Примечание к позиции'),
            ), array('request_id', 'name')),
            'handler'     => function (array $args) use ($request_line_row) {
                $line = Service_Erp403_Requests::instance()->add_line($args['request_id'], $args);

                return array(
                    'summary' => 'Позиция «'.$line->name.'» добавлена: '.$line->qty.' '.$line->uom_code,
                ) + $request_line_row($line);
            },
        ),

        'procurement.request.update' => array(
            'title'       => 'Изменить заявку',
            'description' => 'Правит шапку черновика: срок, приоритет, склад, пояснение. Заявка, ушедшая на согласование, не правится — сначала отмените её.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id'           => $field('string', 'Идентификатор или uuid заявки'),
                'need_date'    => $field('string', 'Нужен к дате (ГГГГ-ММ-ДД)'),
                'priority'     => $field('integer', 'Приоритет 1–4', array('minimum' => 1, 'maximum' => 4)),
                'warehouse_id' => $field('integer', 'Склад'),
                'note'         => $field('string', 'Пояснение'),
            ), array('id')),
            'handler'     => function (array $args) use ($request_row) {
                $request = Service_Erp403_Requests::instance()->update($args['id'], $args, NULL);

                return array('summary' => 'Заявка '.$request->number.' изменена') + $request_row($request);
            },
        ),

        'procurement.request.submit' => array(
            'title'       => 'Отправить заявку на согласование',
            'description' => 'Переводит заявку из черновика в «на согласовании»: с этого момента её правят только решением согласующего. Пустая заявка не уходит.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заявки'),
            ), array('id')),
            'handler'     => function (array $args) use ($request_row) {
                $request = Service_Erp403_Requests::instance()->submit($args['id'], NULL);

                return array('summary' => 'Заявка '.$request->number.' отправлена на согласование') + $request_row($request);
            },
        ),

        'procurement.auto_requests' => array(
            'title'       => 'Создать заявки по минимуму склада',
            'description' => 'Проходит по позициям на минимуме (erp401) и заводит по заявке на склад: количество — до минимума с запасом 20 %, открытые заявки не задваиваются. dry_run показывает, что будет создано, ничего не записывая. Требует подтверждения человека: это пакет документов.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'warehouse_id' => $field('integer', 'Только один склад'),
                'need_days'    => $field('integer', 'Через сколько дней нужен товар (по умолчанию 14)', array('minimum' => 1, 'maximum' => 180)),
                'priority'     => $field('integer', 'Приоритет заявок 1–4 (по умолчанию 2)', array('minimum' => 1, 'maximum' => 4)),
                'dry_run'      => $field('boolean', 'Показать план, ничего не создавая'),
            )),
            'handler'     => function (array $args) {
                $result = Service_Erp403_Requests::instance()->auto_from_low_stock(array(
                    'warehouse_id' => isset($args['warehouse_id']) ? (int) $args['warehouse_id'] : NULL,
                    'need_days'    => isset($args['need_days']) ? (int) $args['need_days'] : 14,
                    'priority'     => isset($args['priority']) ? (int) $args['priority'] : 2,
                    'dry_run'      => ! empty($args['dry_run']),
                ), NULL);

                $created = array();

                foreach ($result['created'] as $row)
                {
                    $created[] = is_object($row)
                        ? array('id' => (int) $row->id, 'number' => (string) $row->number, 'status' => (string) $row->status)
                        : $row;
                }

                return array(
                    'summary' => ! empty($args['dry_run'])
                        ? 'Будет создано заявок: '.count($created).', пропущено: '.count($result['skipped'])
                        : 'Создано заявок: '.count($created).', пропущено: '.count($result['skipped']),
                    'created' => $created,
                    'skipped' => $result['skipped'],
                    'low_stock_total' => (int) $result['total_low_stock'],
                );
            },
        ),

        // ----------------------------------------------------------------- заказы --

        'procurement.order.create' => array(
            'title'       => 'Создать заказ поставщику',
            'description' => 'Два способа: из согласованных заявок (request_ids — заказы группируются по поставщику) или вручную (supplier_id + lines). Позиции без поставщика получают рекомендованного по цене и сроку.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'request_ids'   => $field('array', 'Согласованные заявки, из которых собрать заказ(ы)', array('items' => array('type' => 'integer'))),
                'supplier_id'   => $field('integer', 'Поставщик для ручного заказа (erp301)'),
                'warehouse_id'  => $field('integer', 'Склад приёмки'),
                'currency'      => $field('string', 'Валюта (по умолчанию RUB)'),
                'delivery_date' => $field('string', 'Дата поставки (ГГГГ-ММ-ДД)'),
                'payment_terms' => $field('string', 'Условия оплаты'),
                'note'          => $field('string', 'Пояснение к заказу'),
                'lines'         => $field('array', 'Позиции ручного заказа: item_id, name, qty, price, uom_code, delivery_date', array('items' => array('type' => 'object'))),
            )),
            'handler'     => function (array $args) use ($order_row) {
                $orders = Service_Erp403_Orders::instance();

                if ( ! empty($args['request_ids']))
                {
                    $result = $orders->from_requests((array) $args['request_ids'], array(
                        'warehouse_id'  => isset($args['warehouse_id']) ? (int) $args['warehouse_id'] : NULL,
                        'currency'      => isset($args['currency']) ? (string) $args['currency'] : 'RUB',
                        'delivery_date' => isset($args['delivery_date']) ? (string) $args['delivery_date'] : NULL,
                        'note'          => isset($args['note']) ? (string) $args['note'] : NULL,
                    ), NULL);

                    $created = array();

                    foreach ($result['orders'] as $order)
                    {
                        $created[] = $order_row($order);
                    }

                    return array(
                        'summary'  => 'Собрано заказов: '.count($created)
                            .($result['skipped'] ? ', пропущено заявок: '.count($result['skipped']) : '')
                            .($result['warnings'] ? ', предупреждений: '.count($result['warnings']) : ''),
                        'orders'   => $created,
                        'skipped'  => $result['skipped'],
                        'warnings' => $result['warnings'],
                    );
                }

                $order = $orders->create(array(
                    'supplier_id'   => isset($args['supplier_id']) ? (int) $args['supplier_id'] : NULL,
                    'warehouse_id'  => isset($args['warehouse_id']) ? (int) $args['warehouse_id'] : NULL,
                    'currency'      => isset($args['currency']) ? (string) $args['currency'] : 'RUB',
                    'delivery_date' => isset($args['delivery_date']) ? (string) $args['delivery_date'] : NULL,
                    'payment_terms' => isset($args['payment_terms']) ? (string) $args['payment_terms'] : NULL,
                    'note'          => isset($args['note']) ? (string) $args['note'] : NULL,
                    'lines'         => isset($args['lines']) ? (array) $args['lines'] : array(),
                ), NULL);

                return array(
                    'summary' => 'Заказ '.$order->number.' создан на '.number_format((float) $order->amount, 2, '.', ' ').' '.$order->currency,
                ) + $order_row($order);
            },
        ),

        'procurement.order.line.add' => array(
            'title'       => 'Добавить позицию в заказ',
            'description' => 'Добавляет позицию в черновик заказа. Цена — за единицу; если не указана, подставится последняя известная цена позиции.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'order_id'      => $field('string', 'Идентификатор или uuid заказа'),
                'item_id'       => $field('integer', 'Позиция номенклатуры (erp227)'),
                'name'          => $field('string', 'Название позиции'),
                'qty'           => $field('number', 'Количество', array('minimum' => 0)),
                'price'         => $field('number', 'Цена за единицу'),
                'uom_code'      => $field('string', 'Единица измерения'),
                'delivery_date' => $field('string', 'Срок по позиции (ГГГГ-ММ-ДД)'),
                'note'          => $field('string', 'Примечание к позиции'),
            ), array('order_id', 'name')),
            'handler'     => function (array $args) use ($order_line_row) {
                $line = Service_Erp403_Orders::instance()->add_line($args['order_id'], $args);

                return array(
                    'summary' => 'Позиция «'.$line->name.'» добавлена: '.$line->qty.' '.$line->uom_code
                        .' на '.number_format($line->amount(), 2, '.', ' ').' ₽',
                ) + $order_line_row($line);
            },
        ),

        'procurement.order.send' => array(
            'title'       => 'Отправить заказ поставщику',
            'description' => 'Отправляет заказ: фиксирует цены в истории цен и переводит заказ в «отправлен». Черновик после этого не правится. Требует подтверждения человека — это шаг наружу.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заказа'),
            ), array('id')),
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp403_Orders::instance()->send($args['id'], NULL);

                return array('summary' => 'Заказ '.$order->number.' отправлен поставщику') + $order_row($order);
            },
        ),

        'procurement.order.confirm' => array(
            'title'       => 'Поставщик подтвердил заказ',
            'description' => 'Переводит отправленный заказ в «подтверждён»: поставщик взял его в работу. Приёмку можно оформлять и без этого шага.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заказа'),
            ), array('id')),
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp403_Orders::instance()->confirm($args['id'], NULL);

                return array('summary' => 'Заказ '.$order->number.' подтверждён поставщиком') + $order_row($order);
            },
        ),

        'procurement.order.cancel' => array(
            'title'       => 'Отменить заказ',
            'description' => 'Отменяет заказ с причиной: позиции освобождаются, заявки возвращаются в работу по решению закупщика. Причина уходит в журнал. Требует подтверждения человека.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id'     => $field('string', 'Идентификатор или uuid заказа'),
                'reason' => $field('string', 'Причина отмены (попадёт в журнал)'),
            ), array('id')),
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp403_Orders::instance()->cancel($args['id'], isset($args['reason']) ? (string) $args['reason'] : NULL, NULL);

                return array('summary' => 'Заказ '.$order->number.' отменён') + $order_row($order);
            },
        ),

        // ---------------------------------------------------------------- приёмки --

        'procurement.receipt.create' => array(
            'title'       => 'Оформить приёмку',
            'description' => 'Создаёт черновик приёмки по заказу: строки подставляются как «то, что заказ ещё не принял». Свой список строк — в lines. Приход на склад произойдёт только после проведения.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'order_id'     => $field('integer', 'Заказ, по которому принимаем'),
                'supplier_id'  => $field('integer', 'Поставщик (если приёмка без заказа)'),
                'warehouse_id' => $field('integer', 'Склад-получатель'),
                'doc_number'   => $field('string', 'Номер накладной поставщика'),
                'doc_date'     => $field('string', 'Дата накладной (ГГГГ-ММ-ДД)'),
                'received_at'  => $field('string', 'Дата приёмки (по умолчанию сегодня)'),
                'note'         => $field('string', 'Пояснение'),
                'lines'        => $field('array', 'Свой список позиций: item_id, order_line_id, name, qty, price, uom_code', array('items' => array('type' => 'object'))),
            ), array('order_id')),
            'handler'     => function (array $args) use ($receipt_row) {
                $receipt = Service_Erp403_Receipts::instance()->create($args, NULL);

                return array(
                    'summary' => 'Приёмка '.$receipt->number.' создана: позиций '.count(Service_Erp403_Receipts::instance()->lines($receipt->id))
                        .', проведение оформит приход на склад',
                ) + $receipt_row($receipt);
            },
        ),

        'procurement.receipt.quality' => array(
            'title'       => 'Записать входной контроль',
            'description' => 'Отмечает позицию приёмки: pending («не проверена»), passed («годна») или failed («брак»). Позиция с проваленным контролем блокирует проведение всей приёмки.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'line_id'        => $field('integer', 'Позиция приёмки'),
                'quality_status' => $field('string', 'Результат контроля: pending, passed, failed', array('enum' => array('pending', 'passed', 'failed'))),
                'note'           => $field('string', 'Что нашли (для брака — обязательно)'),
            ), array('line_id', 'quality_status')),
            'handler'     => function (array $args) use ($receipt_line_row) {
                $line = Service_Erp403_Receipts::instance()->set_quality(
                    (int) $args['line_id'],
                    (string) $args['quality_status'],
                    isset($args['note']) ? (string) $args['note'] : NULL
                );

                return array(
                    'summary' => 'Позиция «'.$line->name.'»: '.$line->quality_label(),
                ) + $receipt_line_row($line);
            },
        ),

        'procurement.receipt.post' => array(
            'title'       => 'Провести приёмку на склад',
            'description' => 'Проводит приёмку: позиции с номенклатурой уходят приходом на склад (erp401), цены попадают в историю цен, заказ получает принятое количество. Позиции с проваленным входным контролем блокируют проведение. Требует подтверждения человека: это движение денег и товара.',
            'scope'       => 'erp403.approve',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id'           => $field('string', 'Идентификатор или uuid приёмки'),
                'warehouse_id' => $field('integer', 'Склад прихода (если нужно переопределить)'),
            ), array('id')),
            'handler'     => function (array $args) use ($receipt_row) {
                $receipt = Service_Erp403_Receipts::instance()->post($args['id'], NULL);

                return array(
                    'summary' => 'Приёмка '.$receipt->number.' проведена: товар на складе',
                ) + $receipt_row($receipt);
            },
        ),

        'procurement.receipt.cancel' => array(
            'title'       => 'Отменить приёмку',
            'description' => 'Отменяет черновик приёмки с причиной. Проведённую приёмку отменить нельзя: приход уже на складе, обратное движение оформляется расходом в модуле склада. Требует подтверждения человека.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id'     => $field('string', 'Идентификатор или uuid приёмки'),
                'reason' => $field('string', 'Причина отмены'),
            ), array('id')),
            'handler'     => function (array $args) use ($receipt_row) {
                $receipt = Service_Erp403_Receipts::instance()->cancel($args['id'], isset($args['reason']) ? (string) $args['reason'] : NULL, NULL);

                return array('summary' => 'Приёмка '.$receipt->number.' отменена') + $receipt_row($receipt);
            },
        ),

        // -------------------------------------------------------------- согласование --

        'procurement.request.approve' => array(
            'title'       => 'Согласовать заявку',
            'description' => 'Согласует заявку: после этого её можно проводить в заказ. Агент готовит заявку и показывает расчёт, решение по деньгам принимает человек — поэтому инструмент под подтверждением.',
            'scope'       => 'erp403.approve',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid заявки'),
            ), array('id')),
            'handler'     => function (array $args) use ($request_row) {
                $request = Service_Erp403_Requests::instance()->approve($args['id'], NULL);

                return array('summary' => 'Заявка '.$request->number.' согласована') + $request_row($request);
            },
        ),

        'procurement.request.reject' => array(
            'title'       => 'Отклонить заявку',
            'description' => 'Отклоняет заявку с причиной — она уходит в журнал и видна заявителю. Отклонённая заявка не правится: нужна новая. Требует подтверждения человека.',
            'scope'       => 'erp403.approve',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id'     => $field('string', 'Идентификатор или uuid заявки'),
                'reason' => $field('string', 'Причина отклонения'),
            ), array('id')),
            'handler'     => function (array $args) use ($request_row) {
                $request = Service_Erp403_Requests::instance()->reject($args['id'], isset($args['reason']) ? (string) $args['reason'] : NULL, NULL);

                return array('summary' => 'Заявка '.$request->number.' отклонена') + $request_row($request);
            },
        ),

        // ---------------------------------------------------------------- поставщики --

        'procurement.supplier.save' => array(
            'title'       => 'Сохранить условия поставщика',
            'description' => 'Закупочные условия: срок поставки, минимальная партия, валюта, оценка, отсрочка, метка «предпочтительный», активность. По этим условиям считается рекомендация поставщика.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'supplier_id'    => $field('integer', 'Поставщик — контрагент erp301'),
                'lead_time_days' => $field('integer', 'Срок поставки в днях', array('minimum' => 0)),
                'currency'       => $field('string', 'Валюта расчётов'),
                'min_order'      => $field('number', 'Минимальная партия', array('minimum' => 0)),
                'payment_terms'  => $field('string', 'Условия оплаты (отсрочка, предоплата)'),
                'rating'         => $field('integer', 'Оценка закупщика 1–5', array('minimum' => 1, 'maximum' => 5)),
                'is_preferred'   => $field('boolean', 'Предпочтительный поставщик'),
                'is_active'      => $field('boolean', 'Действующий поставщик'),
                'note'           => $field('string', 'Заметка закупщика'),
            ), array('supplier_id')),
            'handler'     => function (array $args) use ($supplier_row) {
                $supplier_id = (int) $args['supplier_id'];
                $profile     = Service_Erp403_Suppliers::instance()->save_profile($supplier_id, $args, NULL);

                return array(
                    'summary' => 'Условия поставщика «'.Service_Erp403_Suppliers::instance()->name($supplier_id).'» сохранены: срок '.$profile->lead_time().' дн.',
                ) + $supplier_row($supplier_id, Service_Erp403_Suppliers::instance()->counterparty($supplier_id), $profile);
            },
        ),

        'procurement.price.record' => array(
            'title'       => 'Записать цену',
            'description' => 'Добавляет цену в историю: полезно, когда поставщик назвал цену по телефону, без заказа и приёмки. Цены из заказов и приёмок пишутся сами.',
            'scope'       => 'erp403.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'supplier_id' => $field('integer', 'Поставщик (erp301)'),
                'item_id'     => $field('integer', 'Позиция номенклатуры (erp227)'),
                'name'        => $field('string', 'Название позиции (если нет номенклатуры)'),
                'price'       => $field('number', 'Цена за единицу', array('exclusiveMinimum' => TRUE, 'minimum' => 0)),
                'qty'         => $field('number', 'Количество, на которое названа цена', array('minimum' => 0)),
                'uom_code'    => $field('string', 'Единица измерения'),
                'currency'    => $field('string', 'Валюта (по умолчанию RUB)'),
                'dated_at'    => $field('string', 'Дата цены (по умолчанию сегодня)'),
                'note'        => $field('string', 'Откуда цена'),
            ), array('supplier_id', 'price')),
            'handler'     => function (array $args) {
                $price = Service_Erp403_Suppliers::instance()->record_price($args, NULL);

                return array(
                    'summary' => 'Цена '.number_format((float) $price->price, 2, '.', ' ').' '.$price->currency
                        .' на «'.$price->name.'» записана для поставщика «'.Service_Erp403_Suppliers::instance()->name((int) $price->supplier_id).'»',
                    'price_id'  => (int) $price->id,
                    'item_id'   => $price->item_id ? (int) $price->item_id : NULL,
                    'supplier_id' => (int) $price->supplier_id,
                    'price'     => (float) $price->price,
                    'currency'  => (string) $price->currency,
                    'dated_at'  => (string) $price->dated_at,
                    'source'    => (string) $price->source,
                );
            },
        ),
    ),
);
