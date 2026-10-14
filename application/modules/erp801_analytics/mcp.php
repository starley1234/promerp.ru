<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для аналитики (erp801_analytics).
 *
 * Агент видит то же, что руководитель на витринах: сводку KPI, оборачиваемость
 * и дефицит запасов, ABC/XYZ, OEE с Парето простоев, качество с Парето брака,
 * закупки с просрочкой, снимки и пороги. И может сделать то, что человек
 * нажимает: снять снимок витрины, изменить пороги, удалить снимок.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение витрин и порогов — `erp801.view`: пороги видны всем, по ним
 *    считается «плохо/хорошо», и прятать их от цеха странно;
 *  - выгрузка CSV — `erp801.export` (это вынос данных за пределы системы);
 *  - пороги и удаление снимков — `erp801.manage`;
 *  - **удаление снимка** — под подтверждением человека (`approval`): снимок
 *    фиксирует «как считалось тогда», и его пропажа означает, что спор о цифре
 *    за прошлый период нечем закрыть.
 *
 * Отдельное свойство аналитики, важное для агента: витрина, чей источник
 * выключен, не отдаёт нули, а честно отвечает ошибкой `source_missing`. Агент,
 * получивший такую ошибку, не должен считать это отсутствием данных у завода —
 * это отсутствие источника (см. `analytics.sources`).
 *
 * Обработчики вызывают сервисы модуля, а не пишут в таблицы: формулы, пороги и
 * журнал остаются в одном месте.
 *
 * @package    Erp801
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

// Период — общий для всех витрин
$period_fields = array(
    'from'         => $field('string', 'Начало периода (ГГГГ-ММ-ДД). По умолчанию: запасы — 90 дней, производство и качество — текущий месяц'),
    'to'           => $field('string', 'Конец периода (ГГГГ-ММ-ДД), по умолчанию сегодня'),
    'warehouse_id' => $field('integer', 'Склад (для витрин запасов)'),
    'center_id'    => $field('integer', 'Рабочий центр (для витрины производства)'),
);

// Компактный KPI
$kpi_row = function ($kpi) {
    return array(
        'code'   => $kpi['code'],
        'title'  => $kpi['title'],
        'value'  => $kpi['value'],
        'unit'   => $kpi['unit'],
        'target' => $kpi['target'],
        'state'  => $kpi['state'],
        'hint'   => $kpi['hint'],
    );
};

// Состояние блока сводки без тяжёлых списков
$block_row = function ($block) {
    if (empty($block['available']))
    {
        return array(
            'code'      => $block['code'],
            'title'     => $block['title'],
            'available' => FALSE,
            'reason'    => isset($block['reason']) ? $block['reason'] : 'unknown',
            'message'   => isset($block['message']) ? $block['message'] : NULL,
        );
    }

    return array(
        'code'      => $block['code'],
        'title'     => $block['title'],
        'available' => TRUE,
        'summary'   => isset($block['summary']) ? $block['summary'] : NULL,
        'deficit'   => isset($block['deficit']) ? $block['deficit'] : NULL,
    );
};

// Позиция запаса в компактном виде
$stock_row = function ($row) {
    return array(
        'item_id'    => (int) $row['item_id'],
        'code'       => $row['code'],
        'name'       => $row['name'],
        'qty_in'     => $row['qty_in'],
        'qty_out'    => $row['qty_out'],
        'stock'      => $row['stock'],
        'reserved'   => $row['reserved'],
        'min_qty'    => $row['min_qty'],
        'cover_days' => $row['cover_days'],
        'turns'      => $row['turns_period'],
        'class'      => $row['class'],
        'is_dead'    => (bool) $row['is_dead'],
        'is_out'     => (bool) $row['is_out'],
    );
};

return array(
    'module'      => 'erp801_analytics',
    'title'       => 'Аналитика и витрины',
    'description' => 'Витрины по запасам (оборачиваемость, покрытие, ABC/XYZ), производству (OEE, простои), качеству (Парето брака) и закупкам (просрочка), снимки и пороги. Агрегаты берутся у владельцев данных: erp401, erp408, erp405, erp403.',
    'tools'       => array(

        // =====================================================================
        //  Чтение
        // =====================================================================

        'analytics.summary' => array(
            'title'       => 'Сводка аналитики',
            'description' => 'KPI завода одним вызовом: покрытие запасов, мёртвый запас, OEE, выполнение плана, доля брака, просроченные поставки — с целью и состоянием каждого показателя, плюс что требует внимания. Блок, чей источник выключен, помечен available=false, а не нулём.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s($period_fields),
            'handler'     => function (array $args) use ($kpi_row, $block_row) {
                $summary = Service_Erp801_Summary::instance()->build($args);

                $kpi = array();

                foreach ($summary['kpi'] as $row)
                {
                    $kpi[] = $kpi_row($row);
                }

                $blocks = array();

                foreach ($summary['blocks'] as $block)
                {
                    $blocks[] = $block_row($block);
                }

                $unknown = count(array_filter($kpi, function ($row) {
                    return $row['value'] === NULL OR $row['state'] === 'unknown';
                }));

                return array(
                    'summary'      => 'Показателей: '.count($kpi).'; требуют внимания: '.count($summary['attention'])
                        .'; без данных: '.$unknown,
                    'status'       => $unknown > 0 ? 'degraded' : 'ready',
                    'unknown_count'=> $unknown,
                    'generated_at' => $summary['generated_at'],
                    'kpi'          => $kpi,
                    'attention'    => $summary['attention'],
                    'blocks'       => $blocks,
                    'sources'      => $summary['sources'],
                );
            },
        ),

        'analytics.sources' => array(
            'title'       => 'Состояние источников',
            'description' => 'Какие модули-владельцы данных подключены и что каждый даёт аналитике. Первое, что нужно проверить, если витрина вернула source_missing.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $sources = Service_Erp801_Sources::instance()->available();

                $offline = array();

                foreach ($sources as $source)
                {
                    if (empty($source['ok']))
                    {
                        $offline[] = $source['title'];
                    }
                }

                return array(
                    'summary' => $offline
                        ? 'Источники недоступны: '.implode(', ', $offline)
                        : 'Все источники подключены ('.count($sources).')',
                    'sources' => $sources,
                );
            },
        ),

        'analytics.stock' => array(
            'title'       => 'Оборачиваемость и покрытие запасов',
            'description' => 'По каждой позиции: приход, расход, текущий остаток, дни покрытия, обороты за период и класс движения (быстрый/средний/медленный/мёртвый/нет запаса). Покрытие считается от текущего темпа расхода — истории остатков в системе нет.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s($period_fields + array(
                'item_id' => $field('integer', 'Одна позиция номенклатуры'),
                'limit'   => $field('integer', 'Сколько строк вернуть (по умолчанию 25, максимум 200)'),
                'class'   => $field('string', 'Фильтр по классу: fast, medium, slow, dead, out, idle'),
            )),
            'handler'     => function (array $args) use ($stock_row) {
                $turnover = Service_Erp801_Stock::instance()->turnover($args);

                if (isset($args['class']) AND $args['class'] !== '')
                {
                    $turnover['rows'] = array_values(array_filter($turnover['rows'], function ($row) use ($args) {
                        return $row['class'] === $args['class'];
                    }));
                }

                $limit = isset($args['limit']) ? min(200, max(1, (int) $args['limit'])) : 25;

                $rows = array();

                foreach (array_slice($turnover['rows'], 0, $limit) as $row)
                {
                    $rows[] = $stock_row($row);
                }

                return array(
                    'summary' => 'Позиций: '.$turnover['totals']['items'].', покрытие '.($turnover['totals']['cover_days'] === NULL ? 'не определено' : $turnover['totals']['cover_days'].' дн.').', мёртвый запас '.$turnover['totals']['dead_stock'].' ед. в '.$turnover['totals']['dead_items'].' поз.',
                    'from'    => $turnover['from'],
                    'to'      => $turnover['to'],
                    'days'    => $turnover['days'],
                    'totals'  => $turnover['totals'],
                    'matched' => $turnover['matched'],
                    'rows'    => $rows,
                );
            },
        ),

        'analytics.deficit' => array(
            'title'       => 'Чего не хватает',
            'description' => 'Позиции, которых не хватит до цели покрытия или которые уже ниже минимума склада, с прикидкой «сколько докупить». Это не заказ: заказ считает планирование (erp407) со сроками поставки.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s($period_fields + array(
                'item_id' => $field('integer', 'Одна позиция номенклатуры'),
            )),
            'handler'     => function (array $args) {
                $deficit = Service_Erp801_Stock::instance()->deficit($args);

                $rows = array();

                foreach (array_slice($deficit['rows'], 0, 25) as $row)
                {
                    $rows[] = array(
                        'item_id'            => (int) $row['item_id'],
                        'code'               => $row['code'],
                        'name'               => $row['name'],
                        'stock'              => $row['stock'],
                        'min_qty'            => $row['min_qty'],
                        'qty_out'            => $row['qty_out'],
                        'cover_days'         => $row['cover_days'],
                        'reasons'            => $row['reasons'],
                        'shortage_to_target' => $row['shortage_to_target'],
                    );
                }

                return array(
                    'summary'      => 'Требуют внимания: '.$deficit['items'].' поз., из них без запаса '.$deficit['out_items'].'; докупить до цели — '.$deficit['shortage_sum'],
                    'from'         => $deficit['from'],
                    'to'           => $deficit['to'],
                    'target_days'  => $deficit['target_days'],
                    'items'        => $deficit['items'],
                    'out_items'    => $deficit['out_items'],
                    'shortage_sum' => $deficit['shortage_sum'],
                    'rows'         => $rows,
                );
            },
        ),

        'analytics.abc' => array(
            'title'       => 'ABC/XYZ анализ',
            'description' => 'Разложение расхода по позициям: ABC — по накопительной доле расхода (что двигает склад), XYZ — по вариации месячного расхода (насколько ровно). Считается по количеству, а не по деньгам: цены живут в закупках. Матрица — сколько позиций в каждой из девяти клеток.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s($period_fields + array(
                'limit' => $field('integer', 'Сколько строк вернуть (по умолчанию 25, максимум 200)'),
            )),
            'handler'     => function (array $args) {
                $analysis = Service_Erp801_Stock::instance()->abc_xyz($args);

                $limit = isset($args['limit']) ? min(200, max(1, (int) $args['limit'])) : 25;
                $rows  = array();

                foreach (array_slice($analysis['rows'], 0, $limit) as $row)
                {
                    $rows[] = array(
                        'item_id'     => (int) $row['item_id'],
                        'code'        => $row['code'],
                        'name'        => $row['name'],
                        'qty_out'     => $row['qty_out'],
                        'share'       => $row['share'],
                        'share_total' => $row['share_total'],
                        'abc'         => $row['abc'],
                        'cv'          => $row['cv'],
                        'xyz'         => $row['xyz'],
                        'months'      => $row['months'],
                    );
                }

                return array(
                    'summary' => 'Позиций в анализе: '.$analysis['totals']['items'].'; месяцев наблюдений: '.count($analysis['months']).'; границы: A ≤ '.$analysis['borders']['a'].' %, B ≤ '.$analysis['borders']['b'].' %',
                    'from'    => $analysis['from'],
                    'to'      => $analysis['to'],
                    'months'  => $analysis['months'],
                    'borders' => $analysis['borders'],
                    'matrix'  => $analysis['matrix'],
                    'totals'  => $analysis['totals'],
                    'rows'    => $rows,
                );
            },
        ),

        'analytics.production' => array(
            'title'       => 'OEE и простои',
            'description' => 'OEE за период (доступность × производительность × качество) с составляющими, план/факт по выпуску и времени, Парето простоев по причинам и разрез по рабочим центрам. Значение OEE выше 100 % — признак заниженной нормы, о чём инструмент сообщает в notes.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'from'      => $field('string', 'Начало периода (ГГГГ-ММ-ДД), по умолчанию — начало месяца'),
                'to'        => $field('string', 'Конец периода (ГГГГ-ММ-ДД), по умолчанию сегодня'),
                'center_id' => $field('integer', 'Рабочий центр (без него — все)'),
            )),
            'handler'     => function (array $args) {
                $panel = Service_Erp801_Production::instance()->panel($args);

                $centers = array();

                foreach ($panel['by_center'] as $row)
                {
                    $centers[] = array(
                        'work_center_id'    => $row['work_center_id'],
                        'work_center_label' => $row['work_center_label'],
                        'operations'        => $row['operations'],
                        'qty_ok'            => $row['qty_ok'],
                        'qty_scrap'         => $row['qty_scrap'],
                        'availability'      => $row['availability'],
                        'performance'       => $row['performance'],
                        'quality'           => $row['quality'],
                        'oee'               => $row['oee'],
                    );
                }

                return array(
                    'summary'     => 'OEE '.($panel['oee'] === NULL ? 'не определён' : $panel['oee'].' %').' при цели '.$panel['target'].' %; план выпуска выполнен на '.($panel['plan_fact']['qty_complete'] === NULL ? '—' : $panel['plan_fact']['qty_complete'].' %'),
                    'from'        => $panel['from'],
                    'to'          => $panel['to'],
                    'state'       => $panel['state'],
                    'target'      => $panel['target'],
                    'availability'=> $panel['availability'],
                    'performance' => $panel['performance'],
                    'quality'     => $panel['quality'],
                    'oee'         => $panel['oee'],
                    'notes'       => $panel['notes'],
                    'totals'      => $panel['totals'],
                    'plan_fact'   => $panel['plan_fact'],
                    'pareto'      => $panel['pareto'],
                    'by_center'   => $centers,
                );
            },
        ),

        'analytics.quality' => array(
            'title'       => 'Качество: брак и причины',
            'description' => 'Доля забракованных контролей против цели, количества брака и карантина, Парето причин и кодов дефектов, разрез по поставщикам и последние месяцы. Доля считается от контролей, а не от выпуска: партии и штуки выпуска не свести без общего ключа.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'from'  => $field('string', 'Начало периода (ГГГГ-ММ-ДД), по умолчанию — начало месяца'),
                'to'    => $field('string', 'Конец периода (ГГГГ-ММ-ДД), по умолчанию сегодня'),
                'limit' => $field('integer', 'Сколько причин и кодов вернуть (по умолчанию 15)'),
            )),
            'handler'     => function (array $args) {
                $panel = Service_Erp801_Quality::instance()->panel($args);

                return array(
                    'summary'      => 'Контролей: '.(int) $panel['summary']['total'].'; забраковано '.(int) $panel['summary']['failed'].' ('.($panel['defect_share'] === NULL ? '—' : $panel['defect_share'].' %').' при цели '.$panel['target'].' %); брака '.$panel['summary']['defect_qty'].' ед.',
                    'from'         => $panel['from'],
                    'to'           => $panel['to'],
                    'state'        => $panel['state'],
                    'target'       => $panel['target'],
                    'defect_share' => $panel['defect_share'],
                    // Ключ `summary` в ответе инструмента — строка-итог для
                    // человека, её показывает сервер MCP. Числовая сводка
                    // качества идёт отдельным ключом, иначе строка-итог
                    // затирается массивом и ответ падает.
                    'stats'        => $panel['summary'],
                    'cause_pareto' => $panel['cause_pareto'],
                    'code_pareto'  => $panel['code_pareto'],
                    'quarantine'   => $panel['quarantine'],
                    'by_month'     => $panel['by_month'],
                );
            },
        ),

        'analytics.purchases' => array(
            'title'       => 'Закупки: сроки и просрочка',
            'description' => 'Состояние заказов поставщикам, доля просроченных против цели, открытая сумма, списки просроченных и подходящих к сроку заказов с именами поставщиков.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'limit'    => $field('integer', 'Сколько заказов вернуть в каждом списке (по умолчанию 10)'),
                'due_days' => $field('integer', 'Горизонт «ближайших сроков», дней (по умолчанию 7)'),
            )),
            'handler'     => function (array $args) {
                $panel = Service_Erp801_Purchases::instance()->panel($args);

                return array(
                    'summary'       => 'Открытых заказов: '.$panel['open'].', просрочено '.$panel['overdue'].' ('.$panel['overdue_share'].' % при цели '.$panel['target'].' %)',
                    'state'         => $panel['state'],
                    'stats'         => $panel['stats'],
                    'open'          => $panel['open'],
                    'overdue'       => $panel['overdue'],
                    'overdue_share' => $panel['overdue_share'],
                    'target'        => $panel['target'],
                    'overdue_rows'  => $panel['overdue_rows'],
                    'due_rows'      => $panel['due_rows'],
                    'due_days'      => $panel['due_days'],
                );
            },
        ),

        'analytics.snapshots' => array(
            'title'       => 'Снимки витрин',
            'description' => 'Список снятых снимков: какие витрины и на какие даты зафиксированы. Снимок не пересчитывается задним числом, поэтому по нему можно спорить о цифре за прошлый период.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'code'  => $field('string', 'Код витрины: summary, stock, abc, production, quality, purchases'),
                'limit' => $field('integer', 'Сколько снимков вернуть (по умолчанию 25)'),
            )),
            'handler'     => function (array $args) {
                $rows = Service_Erp801_Snapshots::instance()->rows(
                    isset($args['code']) ? $args['code'] : NULL,
                    isset($args['limit']) ? (int) $args['limit'] : 25,
                    0
                );

                return array(
                    'summary' => 'Снимков: '.count($rows),
                    'codes'   => Model_Erp801_Snapshot::$codes,
                    'rows'    => $rows,
                );
            },
        ),

        'analytics.snapshot' => array(
            'title'       => 'Снимок витрины',
            'description' => 'Снимок целиком: фильтры, результат и — по флагу compare — отличия от предыдущего снимка той же витрины (KPI и итоги, без построчных списков).',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id'      => $field('integer', 'Идентификатор снимка или его uuid'),
                'compare' => $field('boolean', 'Показать отличия от предыдущего снимка витрины'),
            ), array('id')),
            'handler'     => function (array $args) {
                $snapshots = Service_Erp801_Snapshots::instance();
                $snapshot  = $snapshots->find($args['id']);

                if ( ! $snapshot)
                {
                    throw new Kohana_Exception('Снимок не найден: :id', array(':id' => (string) $args['id']));
                }

                $result = array(
                    'summary'  => 'Снимок «'.$snapshot->title.'» ('.Model_Erp801_Snapshot::code_label((string) $snapshot->code).')',
                    'snapshot' => $snapshot->as_row(),
                );

                if ( ! empty($args['compare']))
                {
                    $earlier = ORM::factory('Erp801_Snapshot')
                        ->where('code', '=', (string) $snapshot->code)
                        ->where('id', '<', (int) $snapshot->id)
                        ->order_by('id', 'DESC')
                        ->find();

                    if ($earlier->loaded())
                    {
                        $result['compare'] = $snapshots->compare((int) $earlier->id, (int) $snapshot->id);
                    }
                }

                return $result;
            },
        ),

        'analytics.settings' => array(
            'title'       => 'Пороги витрин',
            'description' => 'По каким границам аналитика считает «плохо»: целевое покрытие запасов, порог мёртвого запаса, границы ABC и XYZ, цель по OEE, допустимая доля брака и просрочки. Показывает и значения, и допустимые диапазоны.',
            'scope'       => 'erp801.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array()),
            'handler'     => function (array $args) {
                $settings = Service_Erp801_Settings::instance();

                return array(
                    'summary'  => 'Настроек: '.count($settings->rows()),
                    'rows'     => $settings->rows(),
                    'values'   => $settings->all(),
                );
            },
        ),

        'analytics.export' => array(
            'title'       => 'Выгрузка витрины в CSV',
            'description' => 'Таблица витрины в CSV (BOM, точка с запятой) — открывается в Excel двойным щелчком. Возвращается текстом: агент сам решает, куда его положить.',
            'scope'       => 'erp801.export',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s($period_fields + array(
                'code' => $field('string', 'Код витрины для выгрузки', array('enum' => Service_Erp801_Csv::codes())),
            ), array('code')),
            'handler'     => function (array $args) {
                $file = Service_Erp801_Csv::instance()->render($args['code'], $args);

                return array(
                    'summary'  => 'Выгрузка «'.$file['title'].'»: строк '.$file['rows'].', файл '.$file['filename'],
                    'code'     => $args['code'],
                    'title'    => $file['title'],
                    'filename' => $file['filename'],
                    'rows'     => $file['rows'],
                    'content'  => $file['content'],
                );
            },
        ),

        // =====================================================================
        //  Изменения
        // =====================================================================

        'analytics.save_snapshot' => array(
            'title'       => 'Снять снимок витрины',
            'description' => 'Фиксирует витрину на дату: фильтры и результат сохраняются в базе и больше не пересчитываются. Повторный вызов создаёт второй снимок — это не «обновить», а «запомнить ещё раз».',
            'scope'       => 'erp801.view',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s($period_fields + array(
                'code'  => $field('string', 'Код витрины: summary, stock, abc, production, quality, purchases'),
                'day'   => $field('string', 'День, за который снимается отчёт (ГГГГ-ММ-ДД), по умолчанию сегодня'),
                'title' => $field('string', 'Название снимка (по умолчанию — витрина и дата)'),
                'note'  => $field('string', 'Примечание: зачем сняли'),
            ), array('code')),
            'handler'     => function (array $args) {
                $snapshot = Service_Erp801_Snapshots::instance()->save(
                    $args['code'],
                    $args,
                    NULL,
                    isset($args['title']) ? $args['title'] : NULL,
                    isset($args['note']) ? $args['note'] : NULL,
                    isset($args['day']) ? $args['day'] : NULL
                );

                return array(
                    'summary'  => 'Снимок «'.$snapshot->title.'» сохранён',
                    'snapshot' => $snapshot->as_row(),
                );
            },
        ),

        'analytics.save_settings' => array(
            'title'       => 'Изменить пороги витрин',
            'description' => 'Меняет пороги, по которым считается «плохо». Значение вне допустимого диапазона не сохраняется: молчаливая правка порога меняет решения, а не только цифры на экране.',
            'scope'       => 'erp801.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'values' => $field('object', 'Пороги: {«cover_target_days»: 30, …}. Пустая строка возвращает значение по умолчанию'),
            ), array('values')),
            'handler'     => function (array $args) {
                $settings = Service_Erp801_Settings::instance();
                $saved    = $settings->save((array) $args['values'], NULL);

                return array(
                    'summary' => 'Порогов сохранено: '.count($saved),
                    'values'  => $saved,
                    'rows'    => $settings->rows(),
                );
            },
        ),

        'analytics.delete_snapshot' => array(
            'title'       => 'Удалить снимок витрины',
            'description' => 'Удаляет снимок навсегда. Подтверждает человек: снимок — это «как считалось тогда», и после удаления спор о цифре за прошлый период закрыть нечем.',
            'scope'       => 'erp801.manage',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'approval'    => TRUE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор снимка или его uuid'),
            ), array('id')),
            'handler'     => function (array $args) {
                Service_Erp801_Snapshots::instance()->delete($args['id'], NULL);

                return array('summary' => 'Снимок удалён: '.(string) $args['id']);
            },
        ),
    ),
);
