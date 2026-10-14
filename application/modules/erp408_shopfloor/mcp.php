<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для управления цехом (erp408_shopfloor).
 *
 * Описывают для LLM-агента то же, что видит мастер на экране: загрузку смены,
 * очередь заказов без маршрута, задания терминала, карточку операции с
 * журналом отметок. И — то, что мастер нажимает: начать работу, записать
 * выработку, отметить простой, завершить операцию.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение — `erp408.view`;
 *  - отметки работы (старт, пауза, выработка, простой) — `erp408.terminal`;
 *  - управление маршрутом и справочниками (назначить, разложить, отменить,
 *    удалить) — `erp408.manage`;
 *  - завершение операции и планирование маршрута требуют подтверждения
 *    человека (`approval`): агент готовит работу, закрывает её мастер.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp408_Operations::act()` и
 * соседние), а не пишут в таблицы: журнал отметок, статусы, события и права
 * остаются в одном месте.
 *
 * @package    Erp408
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

// Компактный вид операции для агента: одинаковый во всех инструментах
$operation_row = function (array $operation) {
    return array(
        'id'             => (int) $operation['id'],
        'uuid'           => (string) $operation['uuid'],
        'op_no'          => (int) $operation['op_no'],
        'name'           => (string) $operation['name'],
        'order_id'       => (int) $operation['order_id'],
        'order_number'   => (string) $operation['order_number'],
        'order_title'    => (string) $operation['order_title'],
        'status'         => (string) $operation['status'],
        'status_label'   => (string) $operation['status_label'],
        'work_center_id' => $operation['work_center_id'] ? (int) $operation['work_center_id'] : NULL,
        'shift_id'       => $operation['shift_id'] ? (int) $operation['shift_id'] : NULL,
        'planned_day'    => (string) $operation['planned_day'],
        'qty_planned'    => (float) $operation['qty_planned'],
        'qty_ok'         => (float) $operation['qty_ok'],
        'qty_scrap'      => (float) $operation['qty_scrap'],
        'remaining'      => (float) $operation['remaining'],
        'progress'       => (int) $operation['progress'],
        'planned_hours'  => (float) $operation['planned_hours'],
        'worked_hours'   => (float) $operation['worked_hours'],
        'downtime_hours' => (float) $operation['downtime_hours'],
        'is_downtime'    => (bool) $operation['is_downtime'],
        'down_since'     => $operation['downtime_started_at'] ? (string) $operation['downtime_started_at'] : NULL,
        'actual_start'   => $operation['actual_start'] ? (string) $operation['actual_start'] : NULL,
        'actual_end'     => $operation['actual_end'] ? (string) $operation['actual_end'] : NULL,
        'actions'        => array_keys($operation['actions']),
    );
};

return array(
    'module' => 'erp408',
    'tools'  => array(

        // ---------------------------------------------------------------- чтение --

        'shopfloor.board' => array(
            'title'       => 'Загрузка цеха на день',
            'description' => 'Доска мастера: рабочие центры с операциями на день, плановые часы, свободные часы из календаря, процент загрузки. Возвращает и операции без назначенного центра.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($operation_row) {
                $board = Service_Erp408_Operations::instance()->board(
                    isset($args['day']) ? $args['day'] : NULL,
                    isset($args['center_id']) ? (int) $args['center_id'] : NULL
                );

                $centers = array();

                foreach ($board['centers'] as $row)
                {
                    $centers[] = array(
                        'center_id'   => (int) $row['center']['id'],
                        'code'        => (string) $row['center']['code'],
                        'name'        => (string) $row['center']['name'],
                        'hours'       => (float) $row['hours'],
                        'available'   => (float) $row['available'],
                        'load_pct'    => (int) $row['load_pct'],
                        'done'        => (int) $row['done'],
                        'is_working'  => (bool) $row['is_working'],
                        'operations'  => array_map($operation_row, $row['operations']),
                    );
                }

                return array(
                    'summary'    => 'День '.$board['day'].': центров '.count($centers).', операций '.(int) $board['totals']['operations']
                        .', плановых часов '.(float) $board['totals']['hours'],
                    'day'        => (string) $board['day'],
                    'totals'     => $board['totals'],
                    'centers'    => $centers,
                    'unassigned' => array_map($operation_row, $board['unassigned']),
                );
            },
            'schema'      => $s(array(
                'day'       => $field('string', 'День в виде ГГГГ-ММ-ДД (по умолчанию — сегодня)'),
                'center_id' => $field('integer', 'Только один рабочий центр'),
            )),
        ),

        'shopfloor.operations.search' => array(
            'title'       => 'Поиск операций цеха',
            'description' => 'Операции с фильтрами: статус, рабочий центр, день или период, поиск по названию и номеру заказа, только открытые. Основной способ найти работу по цеху.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($operation_row) {
                $result = Service_Erp408_Operations::instance()->search(array(
                    'status'    => isset($args['status']) ? $args['status'] : NULL,
                    'center_id' => isset($args['center_id']) ? (int) $args['center_id'] : NULL,
                    'day'       => isset($args['day']) ? $args['day'] : NULL,
                    'from'      => isset($args['from']) ? $args['from'] : NULL,
                    'to'        => isset($args['to']) ? $args['to'] : NULL,
                    'term'      => isset($args['term']) ? $args['term'] : NULL,
                    'order_id'  => isset($args['order_id']) ? (int) $args['order_id'] : NULL,
                    'open_only' => ! empty($args['open_only']),
                ), isset($args['limit']) ? (int) $args['limit'] : 20, isset($args['offset']) ? (int) $args['offset'] : 0);

                return array(
                    'summary'  => 'Найдено операций: '.(int) $result['total'],
                    'total'    => (int) $result['total'],
                    'statuses' => Model_Erp408_Operation::$statuses,
                    'operations' => array_map($operation_row, $result['rows']),
                );
            },
            'schema'      => $s(array(
                'status'    => $field('string', 'Статус операции', array('enum' => array('planned', 'in_progress', 'paused', 'done', 'cancelled'))),
                'center_id' => $field('integer', 'Рабочий центр'),
                'day'       => $field('string', 'День плана, ГГГГ-ММ-ДД'),
                'from'      => $field('string', 'Начало периода, ГГГГ-ММ-ДД'),
                'to'        => $field('string', 'Конец периода, ГГГГ-ММ-ДД'),
                'term'      => $field('string', 'Поиск по названию операции и номеру заказа'),
                'order_id'  => $field('integer', 'Операции одного производственного заказа'),
                'open_only' => $field('boolean', 'Только незакрытые (к выполнению, в работе, на паузе)'),
                'limit'     => $field('integer', 'Сколько строк вернуть (по умолчанию 20)', array('minimum' => 1, 'maximum' => 100)),
                'offset'    => $field('integer', 'Смещение для постраничного вывода'),
            )),
        ),

        'shopfloor.operation.get' => array(
            'title'       => 'Карточка операции',
            'description' => 'Одна операция целиком: план и факт, время работы и простоя, назначение, а также журнал отметок — кто и когда нажимал кнопки в терминале.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->get($args['id']);

                if ( ! $operation)
                {
                    throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'not_found');
                }

                return array(
                    'summary'   => 'Операция '.$operation['op_no'].' заказа '.$operation['order_number'].': '.$operation['status_label']
                        .', годных '.(float) $operation['qty_ok'].' из '.(float) $operation['qty_planned'],
                    'operation' => $operation_row($operation),
                    'worklogs'  => Service_Erp408_Operations::instance()->worklogs($operation['id'], isset($args['logs']) ? (int) $args['logs'] : 30),
                );
            },
            'schema'      => $s(array(
                'id'   => $field('string', 'Идентификатор или uuid операции'),
                'logs' => $field('integer', 'Сколько отметок вернуть (по умолчанию 30)'),
            ), array('id')),
        ),

        'shopfloor.terminal.tasks' => array(
            'title'       => 'Задания терминала',
            'description' => 'Открытые операции на день и просроченные — то, что оператор видит на планшете у станка: что делать, сколько осталось, где идёт простой.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) use ($operation_row) {
                $tasks = Service_Erp408_Operations::instance()->terminal_tasks(
                    isset($args['center_id']) ? (int) $args['center_id'] : NULL,
                    isset($args['day']) ? $args['day'] : NULL
                );

                $shift = Service_Erp408_Shifts::instance()->current();

                return array(
                    'summary' => 'Открытых заданий: '.count($tasks),
                    'tasks'   => array_map($operation_row, $tasks),
                    'shift'   => $shift ? array('id' => (int) $shift['id'], 'code' => (string) $shift['code'], 'label' => (string) $shift['label']) : NULL,
                );
            },
            'schema'      => $s(array(
                'center_id' => $field('integer', 'Рабочий центр'),
                'day'       => $field('string', 'День, ГГГГ-ММ-ДД (по умолчанию — сегодня)'),
            )),
        ),

        'shopfloor.stats' => array(
            'title'       => 'Сводка цеха',
            'description' => 'Итоги за период: сколько операций закрыто, в работе, просрочено, сколько годных и брака, часы работы и простоя — с разбивкой по дням и центрам.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $stats = Service_Erp408_Operations::instance()->stats(
                    isset($args['days']) ? (int) $args['days'] : 7,
                    isset($args['center_id']) ? (int) $args['center_id'] : NULL
                );

                return array(
                    'summary' => 'За '.$stats['days'].' дней: операций '.$stats['total'].', закрыто '.$stats['done']
                        .', годных '.$stats['qty_ok'].', брак '.$stats['qty_scrap'],
                ) + $stats;
            },
            'schema'      => $s(array(
                'days'      => $field('integer', 'Сколько дней назад считать (по умолчанию 7)', array('minimum' => 1, 'maximum' => 365)),
                'center_id' => $field('integer', 'Только один рабочий центр'),
            )),
        ),

        'shopfloor.queue' => array(
            'title'       => 'Заказы без маршрута',
            'description' => 'Производственные заказы, по которым цех ещё не получил работы: заказ в плане, работе или выпуске, а операций нет. Кандидаты на раскладку маршрута.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $orders = Service_Erp408_Operations::instance()->orders_without_route(isset($args['limit']) ? (int) $args['limit'] : 20);

                return array(
                    'summary' => 'Заказов без маршрута: '.count($orders),
                    'orders'  => $orders,
                );
            },
            'schema'      => $s(array(
                'limit' => $field('integer', 'Сколько заказов вернуть (по умолчанию 20)'),
            )),
        ),

        'shopfloor.centers.list' => array(
            'title'       => 'Рабочие центры',
            'description' => 'Справочник рабочих центров и участков: код, название, цех, производительность и стоимость нормо-часа, признак активности.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $result = Service_Erp408_WorkCenters::instance()->search(array(
                    'term'   => isset($args['term']) ? $args['term'] : NULL,
                    'kind'   => isset($args['kind']) ? $args['kind'] : NULL,
                    'active' => isset($args['active']) ? $args['active'] : NULL,
                ), isset($args['limit']) ? (int) $args['limit'] : 50, 0);

                return array(
                    'summary' => 'Рабочих центров: '.(int) $result['total'],
                    'total'   => (int) $result['total'],
                    'centers' => $result['rows'],
                );
            },
            'schema'      => $s(array(
                'term'   => $field('string', 'Поиск по коду, названию и цеху'),
                'kind'   => $field('string', 'Вид: рабочий центр или участок', array('enum' => array('center', 'area'))),
                'active' => $field('boolean', 'Только включённые центры'),
                'limit'  => $field('integer', 'Сколько строк вернуть (по умолчанию 50)'),
            )),
        ),

        'shopfloor.shifts.list' => array(
            'title'       => 'Смены и календарь',
            'description' => 'Шаблоны смен и записи календаря за период: какие дни рабочие и сколько часов доступно центру. По этим часам считается загрузка.',
            'scope'       => 'erp408.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $from = isset($args['from']) ? $args['from'] : date('Y-m-01');
                $to   = isset($args['to']) ? $args['to'] : date('Y-m-t');

                $shifts   = Service_Erp408_Shifts::instance()->search(array(), 50, 0);
                $calendar = Service_Erp408_Shifts::instance()->calendar($from, $to, isset($args['center_id']) ? (int) $args['center_id'] : NULL);

                return array(
                    'summary'  => 'Смен: '.(int) $shifts['total'].', записей календаря: '.count($calendar),
                    'shifts'   => $shifts['rows'],
                    'calendar' => $calendar,
                    'from'     => $from,
                    'to'       => $to,
                );
            },
            'schema'      => $s(array(
                'from'      => $field('string', 'Начало периода, ГГГГ-ММ-ДД'),
                'to'        => $field('string', 'Конец периода, ГГГГ-ММ-ДД'),
                'center_id' => $field('integer', 'Календарь одного рабочего центра'),
            )),
        ),

        // ------------------------------------------------------------- отметки --

        'shopfloor.operation.start' => array(
            'title'       => 'Начать операцию',
            'description' => 'Переводит операцию в работу и открывает отсчёт времени. Доступно для статуса «к выполнению» и после паузы — используйте shopfloor.operation.resume.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->start($args['id']);

                return array('summary' => 'Операция '.$operation['op_no'].' начата') + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid операции'),
            ), array('id')),
        ),

        'shopfloor.operation.pause' => array(
            'title'       => 'Пауза',
            'description' => 'Останавливает отсчёт времени: отработанные секунды уходят в факт операции. Продолжение — shopfloor.operation.resume.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->pause($args['id'], NULL, isset($args['comment']) ? $args['comment'] : NULL);

                return array('summary' => 'Операция '.$operation['op_no'].' на паузе, отработано '.(float) $operation['worked_hours'].' ч') + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'      => $field('string', 'Идентификатор или uuid операции'),
                'comment' => $field('string', 'Комментарий мастера'),
            ), array('id')),
        ),

        'shopfloor.operation.resume' => array(
            'title'       => 'Продолжить операцию',
            'description' => 'Возвращает приостановленную операцию в работу и снова открывает отсчёт времени.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->resume($args['id']);

                return array('summary' => 'Операция '.$operation['op_no'].' продолжена') + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid операции'),
            ), array('id')),
        ),

        'shopfloor.operation.output' => array(
            'title'       => 'Записать выработку',
            'description' => 'Добавляет годные и брак по операции. В терминале это нажатие «Выработка»: журнал хранит, кто и сколько записал.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->output(
                    $args['id'],
                    $args['qty_ok'],
                    isset($args['qty_scrap']) ? $args['qty_scrap'] : 0,
                    NULL,
                    isset($args['comment']) ? $args['comment'] : NULL
                );

                return array(
                    'summary' => 'Записано: годных '.(float) $operation['qty_ok'].' из '.(float) $operation['qty_planned']
                        .($operation['qty_scrap'] > 0 ? ', брак '.(float) $operation['qty_scrap'] : ''),
                ) + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'        => $field('string', 'Идентификатор или uuid операции'),
                'qty_ok'    => $field('number', 'Сколько годных изготовлено', array('minimum' => 0)),
                'qty_scrap' => $field('number', 'Сколько ушло в брак', array('minimum' => 0)),
                'comment'   => $field('string', 'Комментарий мастера'),
            ), array('id', 'qty_ok')),
        ),

        'shopfloor.operation.downtime_start' => array(
            'title'       => 'Отметить простой',
            'description' => 'Станок встал: операция уходит в паузу, время простоя начинает считаться, причина сохраняется в журнале — потом по ним разбирают потери.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->downtime_start(
                    $args['id'],
                    NULL,
                    isset($args['reason']) ? $args['reason'] : 'other',
                    isset($args['comment']) ? $args['comment'] : NULL
                );

                return array('summary' => 'Простой отмечен: '.(string) $operation['downtime_reason_label']) + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'      => $field('string', 'Идентификатор или uuid операции'),
                'reason'  => $field('string', 'Причина простоя', array('enum' => array_keys(Model_Erp408_Operation::$downtime_reasons))),
                'comment' => $field('string', 'Уточнение (что именно случилось)'),
            ), array('id')),
        ),

        'shopfloor.operation.downtime_stop' => array(
            'title'       => 'Закончить простой',
            'description' => 'Останавливает отсчёт простоя: секунды уходят в потери операции. Работа возобновляется отдельно — shopfloor.operation.resume.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->downtime_stop($args['id'], NULL, isset($args['comment']) ? $args['comment'] : NULL);

                return array('summary' => 'Простой закончен, всего '.(float) $operation['downtime_hours'].' ч') + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'      => $field('string', 'Идентификатор или uuid операции'),
                'comment' => $field('string', 'Уточнение'),
            ), array('id')),
        ),

        'shopfloor.operation.finish' => array(
            'title'       => 'Завершить операцию',
            'description' => 'Закрывает операцию: время работы фиксируется, факт уходит в производство событием erp408.operation.completed. Требует подтверждения человека — операцию в цехе закрывает мастер.',
            'scope'       => 'erp408.terminal',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->finish(
                    $args['id'],
                    NULL,
                    isset($args['qty_ok']) ? $args['qty_ok'] : NULL,
                    isset($args['qty_scrap']) ? $args['qty_scrap'] : NULL,
                    isset($args['comment']) ? $args['comment'] : NULL
                );

                return array(
                    'summary' => 'Операция '.$operation['op_no'].' завершена: годных '.(float) $operation['qty_ok']
                        .' из '.(float) $operation['qty_planned'].', брак '.(float) $operation['qty_scrap']
                        .', время '.(float) $operation['worked_hours'].' ч',
                ) + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'        => $field('string', 'Идентификатор или uuid операции'),
                'qty_ok'    => $field('number', 'Сколько годных закрыть (по умолчанию — остаток плана)'),
                'qty_scrap' => $field('number', 'Сколько в брак'),
                'comment'   => $field('string', 'Комментарий мастера'),
            ), array('id')),
        ),

        // --------------------------------------------------------- управление --

        'shopfloor.operation.assign' => array(
            'title'       => 'Назначить операцию',
            'description' => 'Ставит операцию на рабочий центр, смену и день. Если день нерабочий по календарю, он сдвигается на ближайший рабочий — об этом сообщается в ответе.',
            'scope'       => 'erp408.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->assign(
                    $args['id'],
                    $args['work_center_id'],
                    isset($args['shift_id']) ? $args['shift_id'] : NULL,
                    isset($args['day']) ? $args['day'] : NULL
                );

                return array('summary' => 'Операция '.$operation['op_no'].' назначена на '.$operation['planned_day']) + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'             => $field('string', 'Идентификатор или uuid операции'),
                'work_center_id' => $field('integer', 'Рабочий центр или участок'),
                'shift_id'       => $field('integer', 'Смена (по умолчанию — текущая у операции)'),
                'day'            => $field('string', 'День, ГГГГ-ММ-ДД (по умолчанию — плановый день операции)'),
            ), array('id', 'work_center_id')),
        ),

        'shopfloor.route.plan' => array(
            'title'       => 'Разложить маршрут заказа',
            'description' => 'Строит операции цеха по техкарте производственного заказа. Если техкарты нет, но есть состав — создаётся одна операция «Изготовление по заказу». Повторный вызов ничего не дублирует. Требует подтверждения человека.',
            'scope'       => 'erp408.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $order = Service_Erp402_Orders::instance()->get(isset($args['order_id']) ? $args['order_id'] : NULL);

                if ( ! $order)
                {
                    throw new Exception_Erp408_Shopfloor('Производственный заказ не найден', NULL, 'order_not_found');
                }

                $count = Service_Erp408_Operations::instance()->plan_order($order);

                return array(
                    'summary'    => 'Заказ '.$order->number.': операций в цехе '.$count,
                    'order_id'   => (int) $order->id,
                    'order_number' => (string) $order->number,
                    'operations' => array_map($operation_row, Service_Erp408_Operations::instance()->for_order($order->id)),
                );
            },
            'schema'      => $s(array(
                'order_id' => $field('integer', 'Идентификатор производственного заказа'),
            ), array('order_id')),
        ),

        'shopfloor.operation.cancel' => array(
            'title'       => 'Отменить операцию',
            'description' => 'Отменяет работу по операции с причиной. История и отметки остаются в журнале — операция не удаляется.',
            'scope'       => 'erp408.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->cancel($args['id'], NULL, isset($args['reason']) ? $args['reason'] : NULL);

                return array('summary' => 'Операция '.$operation['op_no'].' отменена') + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id'     => $field('string', 'Идентификатор или uuid операции'),
                'reason' => $field('string', 'Причина отмены (попадёт в журнал)'),
            ), array('id')),
        ),

        'shopfloor.operation.delete' => array(
            'title'       => 'Удалить операцию',
            'description' => 'Удаляет операцию, по которой ещё не было отметок и которая стоит «к выполнению». Исправление ошибки мастера, а не способ спрятать факт: по работе с отметками используйте отмену.',
            'scope'       => 'erp408.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'idempotent'  => TRUE,
            'approval'    => TRUE,
            'handler'     => function (array $args) use ($operation_row) {
                $operation = Service_Erp408_Operations::instance()->get($args['id']);

                if ( ! $operation)
                {
                    throw new Exception_Erp408_Shopfloor('Операция не найдена', NULL, 'not_found');
                }

                Service_Erp408_Operations::instance()->delete($operation['id']);

                return array('summary' => 'Операция '.$operation['op_no'].' удалена из маршрута заказа '.$operation['order_number'])
                    + $operation_row($operation);
            },
            'schema'      => $s(array(
                'id' => $field('string', 'Идентификатор или uuid операции'),
            ), array('id')),
        ),
    ),
);
