<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для планирования потребностей (erp407_planning).
 *
 * Агент видит то же, что видит плановик: программы и их строки, расчёт
 * потребности с дефицитами и датами заказа, предложения и их судьбу. И может
 * сделать то, что плановик нажимает: добавить строку, рассчитать потребность,
 * завести предложения по дефицитам, утвердить их и передать в исполнение.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение (программы, расчёт, дефициты, предложения) — `erp407.view`;
 *  - правка программ и предложений — `erp407.manage`;
 *  - расчёт, утверждение, создание предложений — `erp407.plan`;
 *  - передача в исполнение (создаёт заказ производства или заявку закупки) —
 *    `erp407.approve`;
 *  - утверждение программы и передача предложения требуют подтверждения
 *    человека (`approval`): агент считает и предлагает, решение принимает
 *    человек. Именно поэтому `planning.proposal.export` — единственный способ
 *    породить заказ в соседнем модуле, и он под подтверждением.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp407_Programs`,
 * `Service_Erp407_Mrp`, `Service_Erp407_Proposals`), а не пишут в таблицы:
 * журнал, события и правила переходов остаются в одном месте.
 *
 * @package    Erp407
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

// Компактный вид программы: одинаковый во всех инструментах
$program_row = function (array $program) {
    return array(
        'id'           => (int) $program['id'],
        'uuid'         => (string) $program['uuid'],
        'number'       => (string) $program['number'],
        'title'        => (string) $program['title'],
        'status'       => (string) $program['status'],
        'period_from'  => (string) $program['period_from'],
        'period_to'    => (string) $program['period_to'],
        'horizon_days' => (int) $program['horizon_days'],
        'source'       => (string) $program['source'],
        'run_at'       => $program['run_at'] ? (string) $program['run_at'] : NULL,
        'days_left'    => ($program['days_left'] === NULL) ? NULL : (int) $program['days_left'],
    );
};

// Компактный вид строки предложения для плановика
$proposal_row = function (array $proposal) {
    return array(
        'id'          => (int) $proposal['id'],
        'uuid'        => (string) $proposal['uuid'],
        'number'      => (string) $proposal['number'],
        'name'        => (string) $proposal['name'],
        'kind'        => (string) $proposal['kind'],
        'qty'         => (float) $proposal['qty'],
        'uom_code'    => (string) $proposal['uom_code'],
        'status'      => (string) $proposal['status'],
        'priority'    => (int) $proposal['priority'],
        'due_date'    => $proposal['due_date'] ? (string) $proposal['due_date'] : NULL,
        'order_date'  => $proposal['order_date'] ? (string) $proposal['order_date'] : NULL,
        'program_id'  => (int) $proposal['program_id'],
        'target_ref'  => $proposal['target_ref'] ? (string) $proposal['target_ref'] : NULL,
        'days_to_order' => ($proposal['days_to_order'] === NULL) ? NULL : (int) $proposal['days_to_order'],
    );
};

// Поля программы и предложения для чтения моделей
$program_array = function (Model_Erp407_Program $program) {
    return array(
        'id'           => (int) $program->id,
        'uuid'         => (string) $program->uuid,
        'number'       => (string) $program->number,
        'title'        => (string) $program->title,
        'status'       => (string) $program->status,
        'period_from'  => (string) $program->period_from,
        'period_to'    => (string) $program->period_to,
        'horizon_days' => (int) $program->horizon_days,
        'source'       => (string) $program->source,
        'run_at'       => $program->run_at ? (string) $program->run_at : NULL,
        'days_left'    => $program->days_left(),
    );
};

$proposal_array = function (Model_Erp407_Proposal $proposal) {
    return array(
        'id'          => (int) $proposal->id,
        'uuid'        => (string) $proposal->uuid,
        'number'      => (string) $proposal->number,
        'name'        => (string) $proposal->name,
        'kind'        => (string) $proposal->kind,
        'qty'         => (float) $proposal->qty,
        'uom_code'    => (string) $proposal->uom_code,
        'status'      => (string) $proposal->status,
        'priority'    => (int) $proposal->priority,
        'due_date'    => $proposal->due_date ? (string) $proposal->due_date : NULL,
        'order_date'  => $proposal->order_date ? (string) $proposal->order_date : NULL,
        'program_id'  => (int) $proposal->program_id,
        'target_ref'  => $proposal->target_ref ? (string) $proposal->target_ref : NULL,
        'days_to_order' => $proposal->days_to_order(),
    );
};

return array(
    'module' => 'erp407',
    'tools'  => array(

        // ---------------------------------------------------------------- чтение --

        'planning.stats' => array(
            'title'       => 'Сводка планирования',
            'description' => 'Состояние планирования одним вызовом: сколько программ и в каком они состоянии, когда был последний расчёт, сколько предложений ждут решения и сколько из них просрочено по дате заказа.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                return array(
                    'summary'   => 'Программ: '.(int) Service_Erp407_Mrp::instance()->stats()['programs']
                        .', предложений к решению: '.(int) Service_Erp407_Proposals::instance()->stats()['new'],
                    'planning'  => Service_Erp407_Mrp::instance()->stats(),
                    'proposals' => Service_Erp407_Proposals::instance()->stats(),
                );
            },
            'schema'      => $s(array()),
        ),

        'planning.programs.search' => array(
            'title'       => 'Поиск программ',
            'description' => 'Производственные программы с фильтрами по состоянию, периоду и тексту. Программа — источник спроса: изделия, количество и срок.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($program_row, $program_array) {
                $filters = array(
                    'status'      => isset($args['status']) ? $args['status'] : NULL,
                    'search'      => isset($args['search']) ? $args['search'] : NULL,
                    'period_from' => isset($args['period_from']) ? $args['period_from'] : NULL,
                    'period_to'   => isset($args['period_to']) ? $args['period_to'] : NULL,
                );

                $programs = Service_Erp407_Programs::instance();

                $rows = array();

                foreach ($programs->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $program)
                {
                    $rows[] = $program_array($program);
                }

                return array(
                    'summary'  => 'Найдено программ: '.$programs->count($filters),
                    'total'    => (int) $programs->count($filters),
                    'statuses' => Model_Erp407_Program::$statuses,
                    'programs' => array_map($program_row, $rows),
                );
            },
            'schema'      => $s(array(
                'status'      => $field('string', 'Состояние программы', array('enum' => array('draft', 'approved', 'closed'))),
                'search'      => $field('string', 'Поиск по номеру и названию'),
                'period_from' => $field('string', 'Программы, начинающиеся не раньше даты (ГГГГ-ММ-ДД)'),
                'period_to'   => $field('string', 'Программы, заканчивающиеся не позже даты (ГГГГ-ММ-ДД)'),
                'limit'       => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'      => $field('integer', 'Смещение для постраничного вывода'),
            )),
        ),

        // Короткое предсказуемое имя для клиентов, которые ищут *.list.
        // planning.programs.search остаётся для обратной совместимости.
        'planning.program.list' => array(
            'title'       => 'Список программ выпуска',
            'description' => 'Список производственных программ перед вызовом planning.program.get. Возвращает id и uuid, поэтому агенту не нужно угадывать идентификатор.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($program_row, $program_array) {
                $filters = array(
                    'status' => isset($args['status']) ? $args['status'] : NULL,
                    'search' => isset($args['search']) ? $args['search'] : NULL,
                    'period_from' => isset($args['period_from']) ? $args['period_from'] : NULL,
                    'period_to' => isset($args['period_to']) ? $args['period_to'] : NULL,
                );
                $service = Service_Erp407_Programs::instance();
                $rows = array();
                foreach ($service->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $program)
                {
                    $rows[] = $program_row($program_array($program));
                }
                return array(
                    'summary' => 'Найдено программ: '.$service->count($filters),
                    'total' => (int) $service->count($filters),
                    'programs' => $rows,
                    'next_step' => 'Передайте id или uuid выбранной программы в planning.program.get.',
                );
            },
            'schema' => $s(array(
                'status' => $field('string', 'Состояние программы', array('enum' => array('draft', 'approved', 'closed'))),
                'search' => $field('string', 'Поиск по номеру и названию'),
                'period_from' => $field('string', 'Начало периода, ГГГГ-ММ-ДД'),
                'period_to' => $field('string', 'Конец периода, ГГГГ-ММ-ДД'),
                'limit' => $field('integer', 'Сколько строк вернуть', array('minimum' => 1, 'maximum' => 100, 'default' => 20)),
                'offset' => $field('integer', 'Смещение', array('minimum' => 0, 'default' => 0)),
            )),
        ),

        'planning.program.get' => array(
            'title'       => 'Программа целиком',
            'description' => 'Программа, её строки, потребность последнего расчёта и предложения — всё, что нужно, чтобы понять план и его узкие места.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid программы'),
            ), array('id')),
            'handler'     => function (array $args) use ($program_array, $program_row, $proposal_array) {
                $program = Service_Erp407_Programs::instance()->get($args['id']);

                if ( ! $program)
                {
                    throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
                }

                $lines = array();

                foreach (Service_Erp407_Programs::instance()->lines($program->id) as $line)
                {
                    $lines[] = array(
                        'id'       => (int) $line->id,
                        'line_no'  => (int) $line->line_no,
                        'name'     => (string) $line->name,
                        'code'     => $line->code ? (string) $line->code : NULL,
                        'qty'      => (float) $line->qty,
                        'uom_code' => (string) $line->uom_code,
                        'due_date' => $line->due_date ? (string) $line->due_date : NULL,
                        'order_id' => $line->order_id ? (int) $line->order_id : NULL,
                        'status'   => (string) $line->status,
                    );
                }

                $requirements = array();

                foreach (Service_Erp407_Mrp::instance()->requirements($program, array(), 200) as $req)
                {
                    $requirements[] = array(
                        'name'         => (string) $req->name,
                        'item_id'      => $req->item_id ? (int) $req->item_id : NULL,
                        'supply_type'  => (string) $req->supply_type,
                        'demand_qty'   => (float) $req->demand_qty,
                        'covered_qty'  => (float) $req->covered_qty,
                        'deficit_qty'  => (float) $req->deficit_qty,
                        'order_qty'    => (float) $req->order_qty,
                        'uom_code'     => (string) $req->uom_code,
                        'due_date'     => $req->due_date ? (string) $req->due_date : NULL,
                        'order_date'   => $req->order_date ? (string) $req->order_date : NULL,
                        'late'         => Erp::bool($req->late),
                        'parent_name'  => $req->parent_name ? (string) $req->parent_name : NULL,
                        'coverage'     => $req->coverage(),
                    );
                }

                $proposals = array();

                foreach (Service_Erp407_Proposals::instance()->search(array('program_id' => $program->id), 200) as $proposal)
                {
                    $proposals[] = $proposal_array($proposal);
                }

                return array(
                    'summary'      => 'Программа '.$program->number.' ('.$program->status_label().'): строк '.count($lines)
                        .', потребностей '.count($requirements).', предложений '.count($proposals),
                    'program'      => $program_row($program_array($program)),
                    'lines'        => $lines,
                    'requirements' => $requirements,
                    'proposals'    => $proposals,
                );
            },
        ),

        'planning.deficits' => array(
            'title'       => 'Дефициты последнего расчёта',
            'description' => 'Чего не хватает по последнему расчёту: сколько нужно, сколько покрыто (склад, незавершёнка, заказы) и к какой дате надо разместить заказ. Просроченные по дате заказа помечены флагом late.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'schema'      => $s(array(
                'program_id' => $field('integer', 'Программа (по умолчанию — последняя с расчётом)'),
                'limit'      => $field('integer', 'Сколько позиций вернуть (по умолчанию 50)', array('minimum' => 1, 'maximum' => 200)),
            )),
            'handler'     => function (array $args) {
                $mrp = Service_Erp407_Mrp::instance();

                $program = Service_Erp407_Programs::instance()->get(isset($args['program_id']) ? $args['program_id'] : NULL);

                if ( ! $program)
                {
                    $program = ORM::factory('Erp407_Program')
                        ->where('run_uuid', 'IS NOT', NULL)
                        ->order_by('run_at', 'DESC')
                        ->find();
                }

                if ( ! $program->loaded())
                {
                    return array('summary' => 'Расчётов ещё не было', 'rows' => array(), 'totals' => $mrp->stats());
                }

                $rows = array();

                foreach ($mrp->requirements($program, array('only_deficit' => TRUE), isset($args['limit']) ? (int) $args['limit'] : 50) as $req)
                {
                    $rows[] = array(
                        'name'         => (string) $req->name,
                        'item_id'      => $req->item_id ? (int) $req->item_id : NULL,
                        'supply_type'  => (string) $req->supply_type,
                        'demand_qty'   => (float) $req->demand_qty,
                        'stock_qty'    => (float) $req->stock_qty,
                        'wip_qty'      => (float) $req->wip_qty,
                        'on_order_qty' => (float) $req->on_order_qty,
                        'covered_qty'  => (float) $req->covered_qty,
                        'deficit_qty'  => (float) $req->deficit_qty,
                        'order_qty'    => (float) $req->order_qty,
                        'uom_code'     => (string) $req->uom_code,
                        'lead_time_days' => (int) $req->lead_time_days,
                        'order_date'   => $req->order_date ? (string) $req->order_date : NULL,
                        'due_date'     => $req->due_date ? (string) $req->due_date : NULL,
                        'late'         => Erp::bool($req->late),
                    );
                }

                // $rows уже нормализованы в массивы выше. Повторный вызов
                // ->as_array() на массиве вызывал Error и ронял HTTP transport.
                $summary = $mrp->summarize($rows);

                return array(
                    'summary'     => 'Дефицитов: '.(int) $summary['deficit'].', просрочено по дате заказа: '.(int) $summary['late'],
                    'program_id'  => (int) $program->id,
                    'run_uuid'    => $program->run_uuid,
                    'rows'        => $rows,
                    'totals'      => $summary,
                );
            },
        ),

        'planning.proposals.search' => array(
            'title'       => 'Поиск предложений',
            'description' => 'Предложения плана: что решено закупить или выпустить, что уже передано в исполнение и куда. Фильтры по состоянию, способу, программе и позиции.',
            'scope'       => 'erp407.view',
            'mutating'    => FALSE,
            'schema'      => $s(array(
                'status'     => $field('string', 'Состояние предложения', array('enum' => array('new', 'approved', 'exported', 'done', 'cancelled'))),
                'kind'       => $field('string', 'Способ обеспечения', array('enum' => array('purchase', 'produce'))),
                'program_id' => $field('integer', 'Предложения одной программы'),
                'item_id'    => $field('integer', 'Предложения одной позиции номенклатуры'),
                'search'     => $field('string', 'Поиск по названию позиции'),
                'limit'      => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'     => $field('integer', 'Смещение для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $proposals = Service_Erp407_Proposals::instance();

                $filters = array(
                    'status'     => isset($args['status']) ? $args['status'] : NULL,
                    'kind'       => isset($args['kind']) ? $args['kind'] : NULL,
                    'program_id' => isset($args['program_id']) ? (int) $args['program_id'] : NULL,
                    'item_id'    => isset($args['item_id']) ? (int) $args['item_id'] : NULL,
                    'search'     => isset($args['search']) ? $args['search'] : NULL,
                );

                $rows = array();

                foreach ($proposals->search($filters, isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0) as $proposal)
                {
                    $rows[] = $proposal_array($proposal);
                }

                return array(
                    'summary'   => 'Найдено предложений: '.$proposals->count($filters),
                    'total'     => (int) $proposals->count($filters),
                    'statuses'  => Model_Erp407_Proposal::$statuses,
                    'kinds'     => Model_Erp407_Proposal::$kinds,
                    'proposals' => array_map($proposal_row, $rows),
                );
            },
        ),

        // --------------------------------------------------------- планирование --

        'planning.program.create' => array(
            'title'       => 'Создать программу',
            'description' => 'Заводит производственную программу на период. Дальше в неё добавляются строки и запускается расчёт потребности.',
            'scope'       => 'erp407.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'title'        => $field('string', 'Название программы'),
                'period_from'  => $field('string', 'Начало периода, ГГГГ-ММ-ДД'),
                'period_to'    => $field('string', 'Конец периода, ГГГГ-ММ-ДД'),
                'horizon_days' => $field('integer', 'Горизонт планирования в днях (по умолчанию 30)', array('minimum' => 1, 'maximum' => 365)),
                'note'         => $field('string', 'Контекст плана'),
            ), array('title', 'period_from', 'period_to')),
            'handler'     => function (array $args) use ($program_array, $program_row) {
                $program = Service_Erp407_Programs::instance()->create(array(
                    'title'        => (string) $args['title'],
                    'period_from'  => (string) $args['period_from'],
                    'period_to'    => (string) $args['period_to'],
                    'horizon_days' => isset($args['horizon_days']) ? (int) $args['horizon_days'] : 30,
                    'note'         => isset($args['note']) ? (string) $args['note'] : NULL,
                ), NULL);

                return array(
                    'summary' => 'Программа '.$program->number.' создана (черновик): добавьте строки и рассчитайте потребность',
                ) + $program_row($program_array($program));
            },
        ),

        'planning.program.line.add' => array(
            'title'       => 'Добавить строку программы',
            'description' => 'Добавляет изделие в программу: что и сколько нужно к сроку. Если у изделия есть техкарта, расчёт разложит его на материалы и полуфабрикаты.',
            'scope'       => 'erp407.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'program_id'   => $field('string', 'Идентификатор или uuid программы из planning.program.list'),
                'name'         => $field('string', 'Наименование изделия'),
                'qty'          => $field('number', 'Количество', array('minimum' => 0.001)),
                'uom_code'     => $field('string', 'Единица измерения (по умолчанию шт)'),
                'due_date'     => $field('string', 'Нужен к дате, ГГГГ-ММ-ДД'),
                'code'         => $field('string', 'Артикул'),
                'item_id'      => $field('integer', 'Идентификатор номенклатуры erp227 — включает учёт остатков'),
                'tech_card_id' => $field('integer', 'Техкарта erp228 для разложения на материалы'),
            ), array('program_id', 'name', 'qty')),
            'handler'     => function (array $args) {
                $program = Service_Erp407_Programs::instance()->get($args['program_id']);
                if ( ! $program)
                {
                    throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
                }
                $line = Service_Erp407_Programs::instance()->add_line((int) $program->id, array(
                    'name'         => (string) $args['name'],
                    'qty'          => (float) $args['qty'],
                    'uom_code'     => isset($args['uom_code']) ? (string) $args['uom_code'] : 'шт',
                    'due_date'     => isset($args['due_date']) ? (string) $args['due_date'] : NULL,
                    'code'         => isset($args['code']) ? (string) $args['code'] : NULL,
                    'item_id'      => isset($args['item_id']) ? (int) $args['item_id'] : NULL,
                    'tech_card_id' => isset($args['tech_card_id']) ? (int) $args['tech_card_id'] : NULL,
                ), NULL);

                return array(
                    'summary'  => 'Строка '.$line->line_no.' добавлена: '.$line->name.' x'.$line->qty.' '.$line->uom_code,
                    'id'       => (int) $line->id,
                    'line_no'  => (int) $line->line_no,
                    'qty'      => (float) $line->qty,
                    'due_date' => $line->due_date ? (string) $line->due_date : NULL,
                );
            },
        ),

        'planning.program.approve' => array(
            'title'       => 'Утвердить программу',
            'description' => 'Переводит программу из черновика в утверждённую: план зафиксирован, строки больше не правятся. Требует подтверждения человека — утверждение плана это решение, а не расчёт.',
            'scope'       => 'erp407.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid программы'),
            ), array('id')),
            'handler'     => function (array $args) use ($program_array, $program_row) {
                $program = Service_Erp407_Programs::instance()->approve($args['id'], NULL);

                return array('summary' => 'Программа '.$program->number.' утверждена') + $program_row($program_array($program));
            },
        ),

        'planning.mrp.calculate' => array(
            'title'       => 'Рассчитать потребность',
            'description' => 'Главный расчёт модуля: разбирает изделия программы по техкартам (до 3 уровней), вычитает склад, незавершёнку и уже размещённые заказы, считает дефицит и дату заказа по сроку поставки. Результат сохраняется снимком и виден в планировании.',
            'scope'       => 'erp407.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'           => $field('string', 'Идентификатор или uuid программы'),
                'warehouse_id' => $field('integer', 'Склад, с которого считать покрытие (по умолчанию — все)'),
            ), array('id')),
            'handler'     => function (array $args) {
                $mrp = Service_Erp407_Mrp::instance();

                $result = $mrp->calculate($args['id'], array(
                    'warehouse_id' => isset($args['warehouse_id']) ? (int) $args['warehouse_id'] : NULL,
                ));

                return array(
                    'summary'  => 'Расчёт готов: строк '.count($result['rows']).', дефицитов '.(int) $result['summary']['deficit']
                        .', просрочено по дате заказа: '.(int) $result['summary']['late'],
                    'run_uuid' => $result['run_uuid'],
                    'totals'   => $result['summary'],
                    'deficits' => array_values(array_filter($result['rows'], function ($row) {
                        return (float) $row['deficit_qty'] > 0;
                    })),
                    'warnings' => $result['warnings'],
                );
            },
        ),

        'planning.proposals.create' => array(
            'title'       => 'Завести предложения по дефицитам',
            'description' => 'Превращает дефициты последнего расчёта в предложения: закупить или выпустить. По каждой позиции создаётся не больше одного открытого предложения — повторный вызов не задваивает.',
            'scope'       => 'erp407.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'program_id' => $field('integer', 'Программа с выполненным расчётом'),
                'only_late'  => $field('boolean', 'Только позиции, у которых срок заказа уже прошёл'),
            ), array('program_id')),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $result = Service_Erp407_Proposals::instance()->create_from_requirements((int) $args['program_id'], array(
                    'only_late' => ! empty($args['only_late']),
                ), NULL);

                $created = array();

                foreach ($result['ids'] as $proposal_id)
                {
                    $proposal = Service_Erp407_Proposals::instance()->get($proposal_id);

                    if ($proposal)
                    {
                        $created[] = $proposal_row($proposal_array($proposal));
                    }
                }

                return array(
                    'summary' => 'Создано предложений: '.(int) $result['created'].' (пропущено, уже открыты: '.(int) $result['skipped'].')',
                    'created' => (int) $result['created'],
                    'skipped' => (int) $result['skipped'],
                    'proposals' => $created,
                );
            },
        ),

        'planning.proposal.create' => array(
            'title'       => 'Создать предложение вручную',
            'description' => 'Заводит предложение вне расчёта: аварийная закупка, замена, опытная партия. Обязательно укажите способ (закупить или выпустить).',
            'scope'       => 'erp407.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'kind'       => $field('string', 'Способ обеспечения', array('enum' => array('purchase', 'produce'))),
                'name'       => $field('string', 'Наименование позиции'),
                'qty'        => $field('number', 'Количество', array('minimum' => 0.001)),
                'program_id' => $field('integer', 'Программа, к которой относится предложение'),
                'item_id'    => $field('integer', 'Номенклатура erp227'),
                'uom_code'   => $field('string', 'Единица измерения (по умолчанию шт)'),
                'due_date'   => $field('string', 'Нужно к дате, ГГГГ-ММ-ДД'),
                'order_date' => $field('string', 'Когда размещать заказ, ГГГГ-ММ-ДД'),
                'priority'   => $field('integer', 'Приоритет: 1 срочно, 3 обычный, 4 низкий', array('minimum' => 1, 'maximum' => 4)),
                'note'       => $field('string', 'Обоснование'),
            ), array('kind', 'name', 'qty')),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $proposal = Service_Erp407_Proposals::instance()->create(array(
                    'program_id' => isset($args['program_id']) ? (int) $args['program_id'] : NULL,
                    'kind'       => (string) $args['kind'],
                    'name'       => (string) $args['name'],
                    'qty'        => (float) $args['qty'],
                    'uom_code'   => isset($args['uom_code']) ? (string) $args['uom_code'] : 'шт',
                    'item_id'    => isset($args['item_id']) ? (int) $args['item_id'] : NULL,
                    'due_date'   => isset($args['due_date']) ? (string) $args['due_date'] : NULL,
                    'order_date' => isset($args['order_date']) ? (string) $args['order_date'] : NULL,
                    'priority'   => isset($args['priority']) ? (int) $args['priority'] : 3,
                    'note'       => isset($args['note']) ? (string) $args['note'] : NULL,
                ), NULL);

                return array(
                    'summary' => 'Предложение '.$proposal->number.' создано: '.$proposal->kind_label().' '.$proposal->name.' x'.$proposal->qty,
                ) + $proposal_row($proposal_array($proposal));
            },
        ),

        'planning.proposal.approve' => array(
            'title'       => 'Утвердить предложение',
            'description' => 'Предложение переходит в «утверждено»: с этого момента оно учитывается как покрытие потребности, а правки по нему закрываются.',
            'scope'       => 'erp407.plan',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid предложения'),
            ), array('id')),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $proposal = Service_Erp407_Proposals::instance()->approve($args['id'], NULL);

                return array('summary' => 'Предложение '.$proposal->number.' утверждено') + $proposal_row($proposal_array($proposal));
            },
        ),

        'planning.proposal.export' => array(
            'title'       => 'Передать предложение в исполнение',
            'description' => 'Создаёт работу для соседнего модуля: для выпуска — заказ производства (erp402), для закупки — заявку закупки (erp403), если модуль подключён. Ссылка на приёмник сохраняется в target_ref. Требует подтверждения человека: агент предлагает, заказ порождает человек.',
            'scope'       => 'erp407.approve',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid предложения'),
            ), array('id')),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $result = Service_Erp407_Proposals::instance()->export($args['id'], NULL);

                return array(
                    'summary' => 'Предложение '.$result['proposal']->number.' передано: '.$result['target']
                        .($result['warning'] ? ' ('.$result['warning'].')' : ''),
                    'target'  => (string) $result['target'],
                    'warning' => $result['warning'],
                ) + $proposal_row($proposal_array($result['proposal']));
            },
        ),

        'planning.proposal.cancel' => array(
            'title'       => 'Отклонить предложение',
            'description' => 'Отклоняет предложение с причиной. Причина уходит в журнал — по ней потом видно, почему план не выполнен. Требует подтверждения человека.',
            'scope'       => 'erp407.plan',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'schema'      => $s(array(
                'id'     => $field('string', 'Идентификатор или uuid предложения'),
                'reason' => $field('string', 'Причина отклонения (попадёт в журнал)'),
            ), array('id')),
            'handler'     => function (array $args) use ($proposal_array, $proposal_row) {
                $proposal = Service_Erp407_Proposals::instance()->cancel($args['id'], isset($args['reason']) ? (string) $args['reason'] : NULL, NULL);

                return array('summary' => 'Предложение '.$proposal->number.' отклонено') + $proposal_row($proposal_array($proposal));
            },
        ),
    ),
);
