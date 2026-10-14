<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для управления производством (erp402_production).
 *
 * Описывают для LLM-агента то же, что доступно пользователю в интерфейсе:
 * найти заказ, посмотреть состав и потребность, собрать состав из техкарты,
 * утвердить план, зарезервировать материалы, начать работу, выпустить
 * продукцию, отменить заказ.
 *
 * Правила безопасности, которые заданы здесь, а не в промпте:
 *
 *  - чтение требует `erp402_production.view`;
 *  - подготовка заказа (черновик, состав, план) — `erp402_production.manage`;
 *  - резерв и начало работы — `erp402_production.plan`;
 *  - выпуск и отмена — `erp402_production.complete` **и подтверждение
 *    человека** (`approval`): агент готовит операцию, выполняет её мастер.
 *
 * Обработчики вызывают сервисы модуля, а не пишут в таблицы: правила
 * производства (резерв через склад, списание, журнал, события) остаются
 * в одном месте.
 *
 * @package    Erp402
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

// Сериализация заказа: агенту нужен компактный и стабильный вид
$order_row = function (Model_Erp402_Order $order, $with_items = FALSE) {
    $row = array(
        'id'                  => (int) $order->id,
        'uuid'                => (string) $order->uuid,
        'number'              => (string) $order->number,
        'title'               => (string) $order->title,
        'status'              => (string) $order->status,
        'status_label'        => $order->status_label(),
        'qty'                 => (float) $order->qty,
        'qty_done'            => (float) $order->qty_done,
        'progress'            => $order->progress(),
        'uom_code'            => (string) $order->uom_code,
        'priority'            => (int) $order->priority,
        'target_date'         => $order->target_date ? (string) $order->target_date : NULL,
        'tech_card_id'        => $order->tech_card_id ? (int) $order->tech_card_id : NULL,
        'item_id'             => $order->item_id ? (int) $order->item_id : NULL,
        'warehouse_id'        => $order->warehouse_id ? (int) $order->warehouse_id : NULL,
        'output_warehouse_id' => $order->output_warehouse_id ? (int) $order->output_warehouse_id : NULL,
        'customer_id'         => $order->customer_id ? (int) $order->customer_id : NULL,
        'note'                => $order->note ? (string) $order->note : NULL,
    );

    if ($with_items)
    {
        $items = array();

        foreach (Service_Erp402_Orders::instance()->items($order->id) as $item)
        {
            $items[] = array(
                'line_no'      => (int) $item->line_no,
                'name'         => (string) $item->name,
                'item_id'      => $item->item_id ? (int) $item->item_id : NULL,
                'uom_code'     => (string) $item->uom_code,
                'qty_per_unit' => (float) $item->qty_per_unit,
                'qty_total'    => (float) $item->qty_total,
                'qty_issued'   => (float) $item->qty_issued,
                'status'       => (string) $item->status,
                'status_label' => $item->status_label(),
            );
        }

        $row['items']   = $items;
        $row['summary'] = Service_Erp402_Materials::instance()->summary($order);
        $row['actions'] = Service_Erp402_Orders::instance()->allowed_actions($order);
    }

    return $row;
};

return array(
    'module' => 'erp402',
    'tools'  => array(

        // ---------------------------------------------------------------- чтение --

        'production.orders.search' => array(
            'title'       => 'Поиск производственных заказов',
            'description' => 'Список заказов с фильтрами: статус, поиск по номеру/изделию, только просроченные. Возвращает номер, изделие, статус, план и факт выпуска.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $result = Service_Erp402_Orders::instance()->search(array(
                    'status'  => isset($args['status']) ? $args['status'] : NULL,
                    'term'    => isset($args['term']) ? $args['term'] : NULL,
                    'overdue' => ! empty($args['overdue']),
                ), isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0);

                return array(
                    'summary'  => 'Найдено заказов: '.(int) $result['total'],
                    'total'    => (int) $result['total'],
                    'statuses' => Model_Erp402_Order::$statuses,
                    'orders'   => $result['rows'],
                );
            },
            'schema'      => $s(array(
                'status'  => $field('string', 'Статус заказа', array('enum' => array('draft', 'planned', 'released', 'in_progress', 'done', 'cancelled'))),
                'term'    => $field('string', 'Поиск по номеру, изделию и примечанию (кириллица поддерживается)'),
                'overdue' => $field('boolean', 'Только заказы с истёкшим сроком'),
                'limit'   => $field('integer', 'Сколько записей вернуть (1–100)', array('minimum' => 1, 'maximum' => 100, 'default' => 20)),
                'offset'  => $field('integer', 'Смещение для постраничного вывода', array('minimum' => 0, 'default' => 0)),
            )),
        ),

        'production.order.get' => array(
            'title'       => 'Карточка производственного заказа',
            'description' => 'Заказ целиком: изделие, план и факт выпуска, состав с нормами и списанием, обеспеченность материалами и доступные действия (что можно сделать дальше).',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp402_Orders::instance()->get($args['id']);

                if ( ! $order)
                {
                    throw new Exception_Erp402_Production('Заказ :id не найден', array(':id' => $args['id']), 'not_found');
                }

                return array('summary' => 'Заказ '.$order->number.' — '.$order->status_label()) + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id' => $field('string', 'Номер заказа (id) или uuid'),
            ), array('id')),
        ),

        'production.order.requirements' => array(
            'title'       => 'Потребность заказа в материалах',
            'description' => 'По каждой строке состава: нужно, есть на складе заказа, всего по всем складам, нехватка. Помогает понять, можно ли запускать заказ.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $order = Service_Erp402_Orders::instance()->get($args['id']);

                if ( ! $order)
                {
                    throw new Exception_Erp402_Production('Заказ :id не найден', array(':id' => $args['id']), 'not_found');
                }

                $materials = Service_Erp402_Materials::instance();

                return array(
                    'summary'      => 'Строк: '.count($materials->requirements($order)).', не хватает по '.$materials->summary($order)['short'].'',
                    'order'        => (string) $order->number,
                    'requirements' => $materials->requirements($order),
                    'totals'       => $materials->summary($order),
                );
            },
            'schema'      => $s(array(
                'id' => $field('string', 'Номер заказа (id) или uuid'),
            ), array('id')),
        ),

        'production.materials.shortages' => array(
            'title'       => 'Нехватка материалов по всем заказам',
            'description' => 'Открытые заказы (план, резерв, в работе), по которым не хватает материалов: сколько нужно, сколько есть и сколько не хватает.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $rows = array();

                foreach (Service_Erp402_Materials::instance()->shortages(isset($args['limit']) ? (int) $args['limit'] : 20) as $short)
                {
                    $rows[] = array(
                        'order_id' => (int) $short['order']->id,
                        'number'   => (string) $short['order']->number,
                        'title'    => (string) $short['order']->title,
                        'material' => (string) $short['name'],
                        'item_id'  => $short['item_id'] ? (int) $short['item_id'] : NULL,
                        'needed'   => (float) $short['needed'],
                        'have'     => $short['have'] !== NULL ? (float) $short['have'] : NULL,
                        'shortage' => (float) $short['shortage'],
                        'uom_code' => (string) $short['uom_code'],
                    );
                }

                return array('summary' => 'Позиций с нехваткой: '.count($rows), 'shortages' => $rows);
            },
            'schema'      => $s(array(
                'limit' => $field('integer', 'Сколько заказов просмотреть', array('minimum' => 1, 'maximum' => 200, 'default' => 20)),
            )),
        ),

        'production.orders.stats' => array(
            'title'       => 'Сводка производства',
            'description' => 'Заказы по статусам, просроченные, объём выпуска, строки без остатка и без номенклатуры.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $stats = Service_Erp402_Orders::instance()->stats();

                return array('summary' => 'Всего заказов: '.$stats['total'].', в работе: '.$stats['open'].', просрочено: '.$stats['overdue']) + $stats;
            },
            'schema'      => $s(array()),
        ),

        'production.catalog.search' => array(
            'title'       => 'Поиск номенклатуры',
            'description' => 'Найти позицию реестра (материал или изделие) по названию или коду. Возвращает id, который нужен в аргументе item_id других инструментов.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $rows = array();

                foreach (Service_Erp402_Materials::instance()->stock()->search_items($args['term'], isset($args['limit']) ? (int) $args['limit'] : 15) as $item)
                {
                    $rows[] = array(
                        'id'   => (int) $item['id'],
                        'code' => isset($item['code']) ? (string) $item['code'] : '',
                        'name' => isset($item['name']) ? (string) $item['name'] : '',
                    );
                }

                return array('summary' => 'Найдено позиций: '.count($rows), 'items' => $rows);
            },
            'schema'      => $s(array(
                'term'  => $field('string', 'Название или код позиции'),
                'limit' => $field('integer', 'Сколько позиций вернуть', array('minimum' => 1, 'maximum' => 50, 'default' => 15)),
            ), array('term')),
        ),

        'production.warehouses' => array(
            'title'       => 'Склады',
            'description' => 'Активные склады: id и название. Нужны для аргументов warehouse_id и output_warehouse_id.',
            'scope'       => 'erp402_production.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $rows = array();

                foreach (Service_Erp402_Materials::instance()->stock()->warehouses(TRUE) as $warehouse)
                {
                    $rows[] = array(
                        'id'   => (int) $warehouse->id,
                        'code' => (string) $warehouse->code,
                        'name' => (string) $warehouse->name,
                        'type' => (string) $warehouse->type,
                    );
                }

                return array('summary' => 'Складов: '.count($rows), 'warehouses' => $rows);
            },
            'schema'      => $s(array()),
        ),

        'production.tech_cards.search' => array(
            'title'       => 'Поиск технологических карт',
            'description' => 'Найти техкарту по названию. Возвращает id карты для аргумента tech_card_id.',
            'scope'       => 'erp228.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $result = Service_Erp228_TechCards::instance()->search(array(
                    'term' => isset($args['term']) ? $args['term'] : NULL,
                ), isset($args['limit']) ? (int) $args['limit'] : 20, 0);

                $rows = array();

                foreach ($result['rows'] as $card)
                {
                    $rows[] = array(
                        'id'              => (int) $card->id,
                        'title'           => (string) $card->title,
                        'status'          => (string) $card->status,
                        'production_type' => (string) $card->production_type,
                    );
                }

                return array('summary' => 'Найдено карт: '.(int) $result['total'], 'total' => (int) $result['total'], 'tech_cards' => $rows);
            },
            'schema'      => $s(array(
                'term'  => $field('string', 'Часть названия техкарты'),
                'limit' => $field('integer', 'Сколько карт вернуть', array('minimum' => 1, 'maximum' => 50, 'default' => 20)),
            )),
        ),

        'production.tech_card.composition' => array(
            'title'       => 'Состав техкарты',
            'description' => 'Спецификация техкарты и маршрут операций до создания заказа: какие материалы нужны, с какими нормами и нашлась ли под них номенклатура реестра.',
            'scope'       => 'erp228.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $bom   = Service_Erp402_Bom::instance()->from_tech_card((int) $args['tech_card_id']);
                $rows  = array();

                foreach ((array) (isset($bom['items']) ? $bom['items'] : array()) as $item)
                {
                    $rows[] = array(
                        'line_no'      => isset($item['line_no']) ? (int) $item['line_no'] : NULL,
                        'name'         => isset($item['name']) ? (string) $item['name'] : '',
                        'item_id'      => ! empty($item['item_id']) ? (int) $item['item_id'] : NULL,
                        'match'        => isset($item['match']) ? (string) $item['match'] : NULL,
                        'qty_per_unit' => isset($item['qty_per_unit']) ? (float) $item['qty_per_unit'] : NULL,
                        'uom_code'     => isset($item['uom_code']) ? (string) $item['uom_code'] : NULL,
                    );
                }

                $operations = Service_Erp402_Bom::instance()->operations((int) $args['tech_card_id']);

                return array(
                    'summary'    => 'Строк состава: '.count($rows).', операций: '.count((array) $operations),
                    'tech_card'  => array(
                        'id'    => (int) $args['tech_card_id'],
                        'title' => isset($bom['card']) ? (string) $bom['card']->title : NULL,
                    ),
                    'items'      => $rows,
                    'operations' => array_slice((array) $operations, 0, 50),
                );
            },
            'schema'      => $s(array(
                'tech_card_id' => $field('integer', 'Идентификатор техкарты (erp228)'),
            ), array('tech_card_id')),
        ),

        // ----------------------------------------------------------- изменения --

        'production.order.create' => array(
            'title'       => 'Создать производственный заказ',
            'description' => 'Создаёт заказ в статусе «черновик»: изделие, план выпуска, техкарта, склады, срок. Состав заполняется отдельно (production.order.fill_from_tech_card или item_add).',
            'scope'       => 'erp402_production.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp402_Orders::instance()->create($args);

                return array('summary' => 'Создан заказ '.$order->number) + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'title'               => $field('string', 'Что производим (изделие или назначение заказа)'),
                'qty'                 => $field('number', 'План выпуска', array('minimum' => 0.001, 'default' => 1)),
                'uom_code'            => $field('string', 'Единица измерения (pcs, kg, m …)', array('default' => 'pcs')),
                'tech_card_id'        => $field('integer', 'Техкарта (erp228) — источник состава'),
                'item_id'             => $field('integer', 'Номенклатура изделия (реестр erp227)'),
                'warehouse_id'        => $field('integer', 'Склад материалов'),
                'output_warehouse_id' => $field('integer', 'Склад готовой продукции'),
                'customer_id'         => $field('integer', 'Заказчик (erp301), если заказ под клиента'),
                'target_date'         => $field('string', 'Срок изготовления, ГГГГ-ММ-ДД'),
                'priority'            => $field('integer', 'Приоритет 1 (высший) — 9 (низший)', array('minimum' => 1, 'maximum' => 9, 'default' => 5)),
                'note'                => $field('string', 'Примечание'),
            ), array('title')),
        ),

        'production.order.update' => array(
            'title'       => 'Изменить производственный заказ',
            'description' => 'Меняет поля заказа, пока он не запущен (черновик, план, резерв). При смене плана выпуска потребность пересчитывается.',
            'scope'       => 'erp402_production.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($order_row) {
                $fields = $args;
                unset($fields['id']);

                $order = Service_Erp402_Orders::instance()->update($args['id'], $fields);

                return array('summary' => 'Заказ '.$order->number.' обновлён') + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id'                  => $field('integer', 'Идентификатор заказа'),
                'title'               => $field('string', 'Изделие / наименование'),
                'qty'                 => $field('number', 'План выпуска'),
                'tech_card_id'        => $field('integer', 'Техкарта'),
                'item_id'             => $field('integer', 'Номенклатура изделия'),
                'warehouse_id'        => $field('integer', 'Склад материалов'),
                'output_warehouse_id' => $field('integer', 'Склад готовой продукции'),
                'customer_id'         => $field('integer', 'Заказчик'),
                'target_date'         => $field('string', 'Срок изготовления, ГГГГ-ММ-ДД'),
                'priority'            => $field('integer', 'Приоритет 1–9', array('minimum' => 1, 'maximum' => 9)),
                'note'                => $field('string', 'Примечание'),
            ), array('id')),
        ),

        'production.order.fill_from_tech_card' => array(
            'title'       => 'Заполнить состав из техкарты',
            'description' => 'Читает спецификацию техкарты и добавляет строки состава в заказ (норма × план), сопоставляя материалы с номенклатурой реестра.',
            'scope'       => 'erp402_production.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'handler'     => function (array $args) use ($order_row) {
                $result = Service_Erp402_Orders::instance()->fill_from_tech_card($args['id'], isset($args['tech_card_id']) ? $args['tech_card_id'] : NULL);
                $order  = Service_Erp402_Orders::instance()->get($args['id']);

                return array(
                    'summary'    => 'Добавлено строк: '.(int) $result['added'].', пропущено: '.(int) $result['skipped'].', операций: '.count((array) $result['operations']),
                    'added'      => (int) $result['added'],
                    'skipped'    => (int) $result['skipped'],
                    'operations' => array_slice((array) $result['operations'], 0, 50),
                ) + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id'           => $field('integer', 'Идентификатор заказа'),
                'tech_card_id' => $field('integer', 'Другая техкарта (по умолчанию — карта из заказа)'),
            ), array('id')),
        ),

        'production.order.item_add' => array(
            'title'       => 'Добавить строку состава',
            'description' => 'Добавляет материал в состав заказа вручную: наименование, норма на изделие, при необходимости номенклатура и склад.',
            'scope'       => 'erp402_production.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'handler'     => function (array $args) {
                $item = Service_Erp402_Orders::instance()->add_item($args['order_id'], $args);

                return array(
                    'summary'      => 'Строка добавлена: '.$item->name.' — потребность '.(float) $item->qty_total.' '.$item->uom_code,
                    'line_no'      => (int) $item->line_no,
                    'name'         => (string) $item->name,
                    'qty_per_unit' => (float) $item->qty_per_unit,
                    'qty_total'    => (float) $item->qty_total,
                );
            },
            'schema'      => $s(array(
                'order_id'     => $field('integer', 'Идентификатор заказа'),
                'name'         => $field('string', 'Наименование материала'),
                'qty_per_unit' => $field('number', 'Норма расхода на единицу изделия', array('minimum' => 0, 'default' => 1)),
                'item_id'      => $field('integer', 'Номенклатура (реестр erp227) — нужна для резерва со склада'),
                'uom_code'     => $field('string', 'Единица измерения', array('default' => 'pcs')),
                'warehouse_id' => $field('integer', 'Склад, с которого списывать'),
                'note'         => $field('string', 'Комментарий'),
            ), array('order_id', 'name')),
        ),

        'production.order.item_remove' => array(
            'title'       => 'Удалить строку состава',
            'description' => 'Убирает строку состава из заказа (доступно, пока заказ не запущен).',
            'scope'       => 'erp402_production.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) {
                Service_Erp402_Orders::instance()->remove_item($args['item_id']);

                return array('summary' => 'Строка состава удалена');
            },
            'schema'      => $s(array(
                'item_id' => $field('integer', 'Идентификатор строки состава (line id)'),
            ), array('item_id')),
        ),

        'production.order.plan' => array(
            'title'       => 'Утвердить план заказа',
            'description' => 'Переводит заказ в статус «план»: состав должен быть заполнен, потребность пересчитывается.',
            'scope'       => 'erp402_production.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp402_Orders::instance()->plan($args['id']);

                return array('summary' => 'Заказ '.$order->number.' утверждён (план)') + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор заказа'),
            ), array('id')),
        ),

        'production.order.release' => array(
            'title'       => 'Зарезервировать материалы',
            'description' => 'Пытается зарезервировать материалы под заказ. Если чего-то не хватает, заказ остаётся планом, а строки получают статус «не хватает» — об этом сообщается в ответе.',
            'scope'       => 'erp402_production.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($order_row) {
                $result = Service_Erp402_Orders::instance()->release($args['id']);

                return array(
                    'summary'  => 'Зарезервировано строк: '.count((array) $result['reserved']).', без остатка: '.count((array) $result['short']),
                    'reserved' => $result['reserved'],
                    'short'    => $result['short'],
                    'unmapped' => $result['unmapped'],
                ) + $order_row($result['order'], TRUE);
            },
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор заказа'),
            ), array('id')),
        ),

        'production.order.start' => array(
            'title'       => 'Начать работу по заказу',
            'description' => 'Переводит заказ в статус «в работе» (можно и из плана, если материалы придут позже).',
            'scope'       => 'erp402_production.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp402_Orders::instance()->start($args['id']);

                return array('summary' => 'Работа по заказу '.$order->number.' начата') + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор заказа'),
            ), array('id')),
        ),

        'production.order.complete' => array(
            'title'       => 'Выпустить продукцию',
            'description' => 'Списывает материалы и принимает готовую продукцию на склад. Поддерживает частичный выпуск: заказ закрывается, когда выпущено не меньше плана. Требует подтверждения человека.',
            'scope'       => 'erp402_production.complete',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) {
                $result = Service_Erp402_Orders::instance()->complete($args['id'], isset($args['qty']) ? $args['qty'] : NULL);

                return array(
                    'summary'  => 'Выпуск по заказу '.$result['order']->number.': списано строк — '.(int) $result['issued'].', статус — '.$result['order']->status_label(),
                    'issued'   => (int) $result['issued'],
                    'errors'   => $result['errors'],
                    'received' => $result['received'],
                    'order'    => array(
                        'id'        => (int) $result['order']->id,
                        'number'    => (string) $result['order']->number,
                        'status'    => (string) $result['order']->status,
                        'qty_done'  => (float) $result['order']->qty_done,
                        'qty'       => (float) $result['order']->qty,
                    ),
                );
            },
            'schema'      => $s(array(
                'id'  => $field('integer', 'Идентификатор заказа'),
                'qty' => $field('number', 'Сколько выпущено (по умолчанию — остаток плана)', array('minimum' => 0.001)),
            ), array('id')),
        ),

        'production.order.cancel' => array(
            'title'       => 'Отменить заказ',
            'description' => 'Отменяет заказ и снимает резервы материалов. Требует подтверждения человека.',
            'scope'       => 'erp402_production.complete',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) use ($order_row) {
                $order = Service_Erp402_Orders::instance()->cancel($args['id'], isset($args['reason']) ? $args['reason'] : NULL);

                return array('summary' => 'Заказ '.$order->number.' отменён') + $order_row($order, TRUE);
            },
            'schema'      => $s(array(
                'id'     => $field('integer', 'Идентификатор заказа'),
                'reason' => $field('string', 'Причина отмены (попадёт в журнал и события)'),
            ), array('id')),
        ),
    ),
);
