<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для качества (erp405_quality).
 *
 * Агент видит то же, что видит контролёр: сводку, контроли, дефекты, коды
 * дефектов, статистику по причинам и поставщикам, очередь решений и карантин —
 * и может сделать то, что человек нажимает: завести контроль, записать дефект,
 * изолировать партию, принять партию годной, закрыть контроль.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение (сводка, контроли, дефекты, статистика, справочник) — `erp405.view`;
 *  - завести контроль, записать дефект, начать проверку — `erp405.inspect`;
 *  - изолировать партию, выпустить из карантина — `erp405.decide`;
 *  - **списать брак в утиль и вернуть поставщику** — под подтверждением человека
 *    (`approval`): это необратимые потери и разговор с контрагентом. Агент
 *    готовит решение и показывает расчёт, решение принимает человек.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp405_Inspections`,
 * `Service_Erp405_DefectCodes`, `Service_Erp405_Stats`), а не пишут в таблицы:
 * переходы состояний, движения склада, журнал и события остаются в одном месте.
 *
 * @package    Erp405
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

// Компактный вид контроля: одинаковый во всех инструментах
$inspection_row = function ($inspection) {
    return array(
        'id'            => (int) $inspection->id,
        'uuid'          => (string) $inspection->uuid,
        'number'        => (string) $inspection->number,
        'kind'          => (string) $inspection->kind,
        'kind_label'    => $inspection->kind_label(),
        'source'        => (string) $inspection->source,
        'source_label'  => $inspection->source_label(),
        'source_ref'    => $inspection->source_ref ? (string) $inspection->source_ref : NULL,
        'item_id'       => (int) $inspection->item_id ?: NULL,
        'item_name'     => (string) $inspection->item_name,
        'qty'           => (float) $inspection->qty,
        'defect_qty'    => (float) $inspection->defect_qty,
        'good_qty'      => $inspection->good_qty(),
        'defect_share'  => $inspection->defect_share(),
        'status'        => (string) $inspection->status,
        'status_label'  => $inspection->status_label(),
        'result'        => (string) $inspection->result,
        'decision'      => (string) $inspection->decision,
        'decision_label'=> $inspection->decision_label(),
        'warehouse_id'  => (int) $inspection->warehouse_id ?: NULL,
        'quarantine_qty'=> (float) $inspection->quarantine_qty,
        'supplier_id'   => (int) $inspection->supplier_id ?: NULL,
        'order_id'      => (int) $inspection->order_id ?: NULL,
        'pending'       => $inspection->is_pending_decision(),
        'note'          => $inspection->note ? (string) $inspection->note : NULL,
    );
};

// Компактный вид дефекта
$defect_row = function ($defect) {
    return array(
        'id'             => (int) $defect->id,
        'inspection_id'  => (int) $defect->inspection_id,
        'code'           => $defect->code ? (string) $defect->code : NULL,
        'qty'            => (float) $defect->qty,
        'severity'       => (int) $defect->severity,
        'severity_label' => $defect->severity_label(),
        'cause'          => (string) $defect->cause,
        'cause_label'    => $defect->cause_label(),
        'note'           => $defect->note ? (string) $defect->note : NULL,
    );
};

// Компактный вид кода дефекта
$code_row = function ($code) {
    return array(
        'id'             => (int) $code->id,
        'code'           => (string) $code->code,
        'name'           => (string) $code->name,
        'category'       => (string) $code->category,
        'category_label' => $code->category_label(),
        'severity'       => (int) $code->severity,
        'severity_label' => $code->severity_label(),
        'active'         => $code->is_active(),
    );
};

return array(
    'module'      => 'erp405_quality',
    'title'       => 'Качество',
    'description' => 'Контроль партий (входной, операционный, итоговый), несоответствия с причиной и виновником, карантин, решения по браку и статистика потерь.',
    'tools'       => array(

        // =====================================================================
        //  Чтение
        // =====================================================================

        'quality.stats' => array(
            'title'       => 'Сводка качества',
            'description' => 'Состояние качества одним вызовом: сколько контролей, сколько партий с браком и какова его доля, что ждёт решения, сколько стоит на карантине и где теряем качество (по причинам).',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $summary = Service_Erp405_Stats::instance()->summary();

                return array(
                    'summary'    => 'Контролей: '.(int) $summary['total']
                        .', с браком: '.(int) $summary['failed'].' ('.(float) $summary['defect_share'].' %)'
                        .', ждут решения: '.(int) $summary['pending']
                        .', на карантине: '.(float) $summary['quarantine_qty'].' ед.',
                    'stats'      => $summary,
                    'by_cause'   => Service_Erp405_Stats::instance()->by_cause(),
                    'quarantine' => Service_Erp405_Stats::instance()->quarantine(),
                );
            },
        ),

        'quality.inspections' => array(
            'title'       => 'Контроли качества',
            'description' => 'Список контролей с фильтрами (вид, состояние, итог, источник, ожидание решения, поиск). Возвращает партии с количеством брака и принятым решением.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'kind'    => $field('string', 'Вид контроля: incoming, operational, final'),
                'status'  => $field('string', 'Состояние: draft, registered, decided, closed, cancelled'),
                'result'  => $field('string', 'Итог: passed или failed'),
                'pending' => $field('boolean', 'Только те, где брак без решения'),
                'term'    => $field('string', 'Поиск по номеру или позиции'),
                'limit'   => $field('integer', 'Сколько строк вернуть (по умолчанию 25)'),
                'offset'  => $field('integer', 'Сдвиг для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($inspection_row) {
                $filters = array(
                    'kind'    => isset($args['kind']) ? $args['kind'] : NULL,
                    'status'  => isset($args['status']) ? $args['status'] : NULL,
                    'result'  => isset($args['result']) ? $args['result'] : NULL,
                    'term'    => isset($args['term']) ? $args['term'] : NULL,
                    'pending' => ! empty($args['pending']),
                );

                $rows = array();

                foreach (Service_Erp405_Inspections::instance()->search($filters,
                    isset($args['limit']) ? (int) $args['limit'] : 25,
                    isset($args['offset']) ? (int) $args['offset'] : 0) as $inspection)
                {
                    $rows[] = $inspection_row($inspection);
                }

                $total = (int) Service_Erp405_Inspections::instance()->count($filters);
                return array(
                    'summary' => 'Найдено контролей: '.$total,
                    'rows'    => $rows,
                    'total'   => $total,
                    'limit'   => isset($args['limit']) ? (int) $args['limit'] : 25,
                    'offset'  => isset($args['offset']) ? (int) $args['offset'] : 0,
                );
            },
        ),

        'quality.inspection' => array(
            'title'       => 'Контроль качества целиком',
            'description' => 'Одна партия: количество, брак и его доля, список несоответствий с причинами и виновником, журнал решений с ссылками на движения склада, состояние карантина.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор контроля или его uuid'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row, $defect_row) {
                $inspection = Service_Erp405_Inspections::instance()->get($args['id']);

                if ( ! $inspection)
                {
                    throw new Kohana_Exception('Контроль качества не найден');
                }

                $defects = array();

                foreach (Service_Erp405_Inspections::instance()->defects($inspection->id) as $defect)
                {
                    $defects[] = $defect_row($defect);
                }

                $decisions = array();

                foreach (Service_Erp405_Inspections::instance()->decisions($inspection->id) as $decision)
                {
                    $decisions[] = array(
                        'decision'       => (string) $decision->decision,
                        'decision_label' => $decision->kind_label(),
                        'qty'            => (float) $decision->qty,
                        'movement_ref'   => $decision->movement_ref ? (string) $decision->movement_ref : NULL,
                        'comment'        => $decision->comment ? (string) $decision->comment : NULL,
                        'created_at'     => (string) $decision->created_at,
                    );
                }

                return array(
                    'summary'    => 'Контроль '.$inspection->number.': '.(float) $inspection->qty.' ед., брак '
                        .(float) $inspection->defect_qty.' ('.$inspection->defect_share().' %), '.(string) $inspection->status_label(),
                    'inspection' => $inspection_row($inspection),
                    'defects'    => $defects,
                    'decisions'  => $decisions,
                );
            },
        ),

        'quality.pending' => array(
            'title'       => 'Очередь решений по браку',
            'description' => 'Партии с браком, по которым решения ещё нет. Именно эта очередь превращается в разговор с поставщиком или в переделку — держите её пустой.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'limit' => $field('integer', 'Сколько строк вернуть (по умолчанию 25)'),
            )),
            'handler'     => function (array $args) use ($inspection_row) {
                $rows = array();

                foreach (Service_Erp405_Inspections::instance()->pending(isset($args['limit']) ? (int) $args['limit'] : 25) as $inspection)
                {
                    $rows[] = $inspection_row($inspection);
                }

                return array(
                    'summary' => 'Ждут решения: '.count($rows).' партий',
                    'rows'    => $rows,
                );
            },
        ),

        'quality.quarantine' => array(
            'title'       => 'Карантин',
            'description' => 'Что физически лежит на складе карантина: позиции, количества, склады и контроли, к которым они относятся.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $rows = Service_Erp405_Stats::instance()->quarantine();

                return array(
                    'summary'    => 'На карантине партий: '.count($rows).', всего '
                        .array_sum(array_map(function ($row) { return (float) $row['quarantine_qty']; }, $rows)).' ед.',
                    'rows'       => $rows,
                    'warehouses' => Service_Erp405_Inspections::instance()->quarantine_warehouses(),
                );
            },
        ),

        'quality.defect_codes' => array(
            'title'       => 'Коды дефектов',
            'description' => 'Справочник кодов дефектов с категорией, важностью и частотой использования. Единый словарь — условие честной статистики качества.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'term'        => $field('string', 'Поиск по коду или названию'),
                'category'    => $field('string', 'Категория дефекта'),
                'all'         => $field('boolean', 'Показывать выключенные коды'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 200)'),
            )),
            'handler'     => function (array $args) use ($code_row) {
                $rows = array();

                foreach (Service_Erp405_DefectCodes::instance()->search(array(
                    'term'        => isset($args['term']) ? $args['term'] : NULL,
                    'category'    => isset($args['category']) ? $args['category'] : NULL,
                    'active_only' => empty($args['all']),
                ), isset($args['limit']) ? (int) $args['limit'] : 200, 0) as $code)
                {
                    $row = $code_row($code);
                    $row['usage'] = Service_Erp405_DefectCodes::instance()->usage((int) $code->id);
                    $rows[] = $row;
                }

                return array(
                    'summary'    => 'Кодов дефектов: '.count($rows),
                    'rows'       => $rows,
                    'categories' => Model_Erp405_DefectCode::$categories,
                );
            },
        ),

        'quality.defects_report' => array(
            'title'       => 'Статистика брака',
            'description' => 'Где теряем качество: брак по кодам, по причинам (поставщик, цех, склад, оборудование), по поставщикам, по номенклатуре и по месяцам — за период или за всё время.',
            'scope'       => 'erp405.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'from'   => $field('string', 'С даты (ГГГГ-ММ-ДД)'),
                'to'     => $field('string', 'По дату (ГГГГ-ММ-ДД)'),
                'months' => $field('integer', 'Сколько месяцев динамики показать (по умолчанию 6)'),
                'limit'  => $field('integer', 'Сколько строк в каждой таблице (по умолчанию 20)'),
            )),
            'handler'     => function (array $args) {
                $stats = Service_Erp405_Stats::instance();
                $from  = isset($args['from']) ? $args['from'] : NULL;
                $to    = isset($args['to']) ? $args['to'] : NULL;
                $limit = isset($args['limit']) ? (int) $args['limit'] : 20;

                return array(
                    'summary'     => 'Брак по причинам записей: '.count($stats->by_cause($from, $to)),
                    'by_cause'    => $stats->by_cause($from, $to),
                    'by_code'     => $stats->by_code($from, $to, $limit),
                    'by_supplier' => $stats->by_supplier($from, $to, $limit),
                    'by_item'     => $stats->by_item($from, $to, $limit),
                    'by_month'    => $stats->by_month(isset($args['months']) ? (int) $args['months'] : 6),
                );
            },
        ),

        // =====================================================================
        //  Контроль и дефекты
        // =====================================================================

        'quality.inspection.create' => array(
            'title'       => 'Завести контроль качества',
            'description' => 'Взять партию под контроль. Для складских решений (карантин, утиль) укажите номенклатуру и склад, где лежит партия: без них двигать нечего.',
            'scope'       => 'erp405.inspect',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'kind'         => $field('string', 'Вид контроля: incoming, operational, final'),
                'source'       => $field('string', 'Откуда партия: manual, erp403_receipt, erp402_order, erp408_operation'),
                'source_ref'   => $field('string', 'Ссылка на источник (например, id позиции приёмки)'),
                'item_id'      => $field('integer', 'Идентификатор номенклатуры (реестр erp227)'),
                'item_name'    => $field('string', 'Название позиции, если номенклатуры нет'),
                'qty'          => $field('number', 'Количество в партии', array('exclusiveMinimum' => TRUE)),
                'warehouse_id' => $field('integer', 'Склад, где физически лежит партия'),
                'supplier_id'  => $field('integer', 'Поставщик (для входного контроля)'),
                'order_id'     => $field('integer', 'Производственный заказ (для операционного контроля)'),
                'note'         => $field('string', 'Примечание'),
            ), array('qty')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->register($args, NULL);

                return array(
                    'summary'    => 'Контроль '.$inspection->number.' заведён: '.(float) $inspection->qty.' ед.',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.from_receipt' => array(
            'title'       => 'Входной контроль по приёмке',
            'description' => 'Завести контроль по позиции приёмки закупки (erp403): партия, количество и поставщик берутся из приёмки. Повторный вызов для той же позиции не создаёт второй контроль.',
            'scope'       => 'erp405.inspect',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'line_id' => $field('integer', 'Идентификатор позиции приёмки'),
            ), array('line_id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->from_receipt_line((int) $args['line_id'], NULL);

                return array(
                    'summary'    => 'Контроль '.$inspection->number.' по позиции приёмки '.(int) $args['line_id'],
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.defect.add' => array(
            'title'       => 'Записать дефект',
            'description' => 'Записать несоответствие: код дефекта, количество и причина. Факт дефекта сам переводит итог контроля в «брак» — партия больше не может быть принята годной без решения.',
            'scope'       => 'erp405.inspect',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'inspection_id'  => $field('integer', 'Контроль, к которому относится дефект'),
                'defect_code_id' => $field('integer', 'Код дефекта из справочника'),
                'qty'            => $field('number', 'Сколько единиц с этим дефектом', array('exclusiveMinimum' => TRUE)),
                'cause'          => $field('string', 'Причина: supplier, production, warehouse, equipment, design, unknown'),
                'severity'       => $field('integer', 'Важность 1..3 (по умолчанию — из кода дефекта)'),
                'note'           => $field('string', 'Примечание'),
            ), array('inspection_id', 'qty')),
            'handler'     => function (array $args) use ($inspection_row, $defect_row) {
                $defect = Service_Erp405_Inspections::instance()->add_defect((int) $args['inspection_id'], $args, NULL);

                $inspection = Service_Erp405_Inspections::instance()->get((int) $args['inspection_id']);

                return array(
                    'summary'    => 'Дефект записан: '.(float) $defect->qty.' ед. Итог контроля — '.(string) $inspection->result_label(),
                    'defect'     => $defect_row($defect),
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.start' => array(
            'title'       => 'Начать проверку',
            'description' => 'Перевести контроль из черновика в рабочий: партия проверяется, дефекты записываются.',
            'scope'       => 'erp405.inspect',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор контроля'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->start((int) $args['id'], NULL);

                return array(
                    'summary'    => 'Контроль '.$inspection->number.' начат',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.pass' => array(
            'title'       => 'Принять партию годной',
            'description' => 'Итог «годная». Требует, чтобы дефектов не было: если брак записан, нужен разбор и решение. Для входного контроля закупки сразу снимает блокировку прихода.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор контроля'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->mark_passed((int) $args['id'], NULL);

                return array(
                    'summary'    => 'Партия '.$inspection->number.' признана годной',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.quarantine' => array(
            'title'       => 'Изолировать партию',
            'description' => 'Переместить брак на склад карантина — партия физически уходит из оборота. Пока она на карантине, контроль не закрывается.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'           => $field('integer', 'Идентификатор контроля'),
                'warehouse_id' => $field('integer', 'Склад карантина (по умолчанию первый склад типа quarantine)'),
                'qty'          => $field('number', 'Сколько изолировать (по умолчанию — всё бракованное количество)'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->quarantine(
                    (int) $args['id'],
                    isset($args['warehouse_id']) ? $args['warehouse_id'] : NULL,
                    isset($args['qty']) ? $args['qty'] : NULL,
                    NULL
                );

                return array(
                    'summary'    => 'Изолировано '.(float) $inspection->quarantine_qty.' ед. по '.$inspection->number,
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.release' => array(
            'title'       => 'Выпустить из карантина',
            'description' => 'Вернуть партию из карантина в оборот: после сортировки, решения «использовать как есть» или прихода сопроводительных документов.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'           => $field('integer', 'Идентификатор контроля'),
                'warehouse_id' => $field('integer', 'Куда вернуть (по умолчанию — склад партии)'),
                'qty'          => $field('number', 'Сколько выпустить (по умолчанию всё, что на карантине)'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->release(
                    (int) $args['id'],
                    isset($args['warehouse_id']) ? $args['warehouse_id'] : NULL,
                    isset($args['qty']) ? $args['qty'] : NULL,
                    NULL
                );

                return array(
                    'summary'    => 'Партия '.$inspection->number.' выпущена из карантина',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.inspection.close' => array(
            'title'       => 'Закрыть контроль',
            'description' => 'Закрыть разобранный контроль: решение исполнено, документ больше не правится. Партия на карантине закрытию мешает — сначала выпуск.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор контроля'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->close((int) $args['id'], NULL);

                return array(
                    'summary'    => 'Контроль '.$inspection->number.' закрыт',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        // =====================================================================
        //  Решения по браку: под подтверждением человека
        // =====================================================================

        'quality.decision.scrap' => array(
            'title'       => 'Брак в утиль',
            'description' => 'Списать брак: перемещение на склад утиля (erp401). Необратимая потеря — поэтому решение подтверждает человек. Без склада типа scrap операция не выполняется, а не «делает вид».',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'approval'    => TRUE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'           => $field('integer', 'Идентификатор контроля'),
                'warehouse_id' => $field('integer', 'Склад утиля (по умолчанию первый склад типа scrap)'),
                'qty'          => $field('number', 'Сколько списать (по умолчанию — всё бракованное количество)'),
                'comment'      => $field('string', 'Обоснование решения'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->decide(
                    (int) $args['id'],
                    'scrap',
                    isset($args['comment']) ? $args['comment'] : NULL,
                    NULL,
                    array('warehouse_id' => isset($args['warehouse_id']) ? $args['warehouse_id'] : NULL)
                );

                return array(
                    'summary'    => 'Брак '.(float) $inspection->defect_qty.' ед. по '.$inspection->number.' списан в утиль',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.decision.return' => array(
            'title'       => 'Вернуть брак поставщику',
            'description' => 'Решение «вернуть поставщику»: партия изолируется, поставщику уходит событие с количеством и позицией для рекламации. Разговор с контрагентом — решение человека.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'approval'    => TRUE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'      => $field('integer', 'Идентификатор контроля'),
                'comment' => $field('string', 'Обоснование и договорённости с поставщиком'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->decide(
                    (int) $args['id'],
                    'return',
                    isset($args['comment']) ? $args['comment'] : NULL,
                    NULL
                );

                return array(
                    'summary'    => 'Партия '.$inspection->number.' возвращается поставщику: '
                        .(float) $inspection->defect_qty.' ед. '
                        .($inspection->supplier_id ? 'поставщик #'.(int) $inspection->supplier_id : 'поставщик не указан'),
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.decision.rework' => array(
            'title'       => 'Брак в переделку',
            'description' => 'Решение «переделать»: событие для производства (erp402) с количеством и заказом. Товар остаётся на складе, пока производство не возьмёт его в работу.',
            'scope'       => 'erp405.decide',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'      => $field('integer', 'Идентификатор контроля'),
                'comment' => $field('string', 'Что именно переделать и кто отвечает'),
            ), array('id')),
            'handler'     => function (array $args) use ($inspection_row) {
                $inspection = Service_Erp405_Inspections::instance()->decide(
                    (int) $args['id'],
                    'rework',
                    isset($args['comment']) ? $args['comment'] : NULL,
                    NULL
                );

                return array(
                    'summary'    => 'Партия '.$inspection->number.' отправлена в переделку: '.(float) $inspection->defect_qty.' ед.',
                    'inspection' => $inspection_row($inspection),
                );
            },
        ),

        'quality.defect_code.save' => array(
            'title'       => 'Код дефекта',
            'description' => 'Завести или изменить код дефекта. Свободный текст в статистике не сходится: один дефект должен называться одним кодом на всём заводе.',
            'scope'       => 'erp405.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'          => $field('integer', 'Идентификатор кода (пусто — завести новый)'),
                'code'        => $field('string', 'Обозначение, например SURF-SCRATCH'),
                'name'        => $field('string', 'Название дефекта'),
                'category'    => $field('string', 'Категория: size, surface, material, assembly, document, packaging, other'),
                'severity'    => $field('integer', 'Важность 1..3'),
                'description' => $field('string', 'Описание: как распознать дефект'),
                'is_active'   => $field('boolean', 'Выключить код (FALSE) вместо удаления'),
            ), array('name')),
            'handler'     => function (array $args) use ($code_row) {
                $codes = Service_Erp405_DefectCodes::instance();

                if ( ! empty($args['id']))
                {
                    $code = $codes->update((int) $args['id'], $args, NULL);
                }
                else
                {
                    $code = $codes->create($args, NULL);
                }

                return array(
                    'summary' => 'Код дефекта '.$code->code.' — '.$code->name,
                    'code'    => $code_row($code),
                );
            },
        ),
    ),
);
