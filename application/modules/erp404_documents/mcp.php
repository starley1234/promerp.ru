<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для документов и печати (erp404_documents).
 *
 * Агент видит то же, что видит человек: сводку по документам, список, снимок
 * выпущенного документа, журнал печати, шаблоны форм и предпросмотр — и может
 * сделать то, что человек нажимает: завести черновик, выпустить документ,
 * отметить печать, сдать в архив.
 *
 * Правила безопасности заданы здесь, а не в промпте:
 *
 *  - чтение (сводка, документы, шаблоны, журнал печати, предпросмотр) — `erp404.view`;
 *  - завести черновик, выпустить, напечатать, сдать в архив — `erp404.print`;
 *  - вести шаблоны форм — `erp404.manage` (правка тела формы меняет то, что
 *    печатает весь завод, поэтому это отдельное право);
 *  - **отмена выпущенного документа** — под подтверждением человека
 *    (`approval`): номер остаётся занятым, а бумага с этим номером может быть
 *    уже у контрагента. Агент готовит отмену и объясняет причину, решение
 *    принимает человек.
 *
 * Обработчики вызывают сервисы модуля (`Service_Erp404_Documents`,
 * `Service_Erp404_Templates`), а не пишут в таблицы: переходы состояний, снимок
 * выпуска, журнал и события остаются в одном месте.
 *
 * @package    Erp404
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

// Компактный вид документа: одинаковый во всех инструментах
$document_row = function ($document) {
    return array(
        'id'               => (int) $document->id,
        'uuid'             => (string) $document->uuid,
        'number'           => (string) $document->number,
        'title'            => (string) $document->title,
        'status'           => (string) $document->status,
        'status_label'     => $document->status_label(),
        'ref_type'         => (string) $document->ref_type,
        'ref_type_label'   => Model_Erp404_Template::ref_type_label($document->ref_type),
        'ref_id'           => $document->ref_id === NULL ? NULL : (string) $document->ref_id,
        'template_code'    => $document->template_code === NULL ? NULL : (string) $document->template_code,
        'template_version' => $document->template_version === NULL ? NULL : (int) $document->template_version,
        'print_count'      => (int) $document->print_count,
        'has_snapshot'     => ($document->rendered !== NULL AND $document->rendered !== ''),
        'editable'         => $document->is_editable(),
        'printable'        => $document->is_printable(),
        'issued_at'        => $document->issued_at === NULL ? NULL : (string) $document->issued_at,
        'printed_at'       => $document->printed_at === NULL ? NULL : (string) $document->printed_at,
        'created_at'       => $document->created_at ? (string) $document->created_at : NULL,
    );
};

// Компактный вид шаблона
$template_row = function ($template, $with_body = FALSE) {
    $row = array(
        'id'           => (int) $template->id,
        'uuid'         => (string) $template->uuid,
        'code'         => (string) $template->code,
        'name'         => (string) $template->name,
        'ref_type'     => (string) $template->ref_type,
        'ref_type_label' => $template->type_label(),
        'kind'         => (string) $template->kind,
        'kind_label'   => $template->kind_label(),
        'version'      => (int) $template->version,
        'active'       => $template->is_active(),
        'placeholders' => $template->placeholders(),
        'rows'         => $template->row_blocks(),
        'used'         => Service_Erp404_Templates::instance()->usage($template->id),
    );

    if ($with_body)
    {
        $row['body'] = (string) $template->body;
        $row['note'] = $template->note ? (string) $template->note : NULL;
    }

    return $row;
};

// Компактный вид записи журнала печати
$print_row = function ($print) {
    return array(
        'id'         => (int) $print->id,
        'printed_by' => $print->printed_by === NULL ? NULL : (int) $print->printed_by,
        'printed_by_name' => $print->printed_by_name(),
        'copies'     => $print->copies(),
        'note'       => $print->note ? (string) $print->note : NULL,
        'printed_at' => (string) $print->printed_at,
    );
};

// Тело действия: параметры выпуска собираются одинаково во всех инструментах
$action_payload = function (array $args) {
    $data = array();

    if (isset($args['payload']))
    {
        $data['payload'] = $args['payload'];
    }

    return $data;
};

return array(
    'module'      => 'erp404_documents',
    'title'       => 'Документы и печать',
    'description' => 'Шаблоны печатных форм, выпуск документов со снимком и версией шаблона, журнал печати, архив. Данные берутся у владельцев: закупки (erp403), производство (erp402), качество (erp405), цех (erp408).',
    'tools'       => array(

        // =====================================================================
        //  Чтение
        // =====================================================================

        'documents.stats' => array(
            'title'       => 'Сводка по документам',
            'description' => 'Состояние печати одним вызовом: сколько документов ждёт выпуска, сколько выпущено, напечатано, в архиве и отменено, и какие типы объектов печатаем чаще.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'from'     => $field('string', 'Начало периода (ГГГГ-ММ-ДД)'),
                'to'       => $field('string', 'Конец периода (ГГГГ-ММ-ДД)'),
                'ref_type' => $field('string', 'Ограничить типом объекта: erp402_order, erp403_order, erp403_receipt, erp405_inspection, erp408_operation'),
            )),
            'handler'     => function (array $args) {
                $filters = array(
                    'from'     => isset($args['from']) ? $args['from'] : NULL,
                    'to'       => isset($args['to']) ? $args['to'] : NULL,
                    'ref_type' => isset($args['ref_type']) ? $args['ref_type'] : NULL,
                );

                $stats = Service_Erp404_Documents::instance()->stats($filters);

                return array(
                    'summary' => 'Документов: '.(int) $stats['total']
                        .', черновиков: '.(int) $stats['drafts']
                        .', выпущено: '.(int) $stats['issued']
                        .', в архиве: '.(int) $stats['archived']
                        .', отменено: '.(int) $stats['cancelled']
                        .', печатей: '.(int) $stats['print_runs'],
                    'stats'      => $stats,
                    'by_ref_type' => Service_Erp404_Documents::instance()->by_ref_type($filters),
                );
            },
        ),

        'documents.list' => array(
            'title'       => 'Документы',
            'description' => 'Список документов с фильтрами (состояние, тип объекта, объект, шаблон, период, поиск). Показывает номер, шаблон с версией и сколько раз печатали.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'status'        => $field('string', 'Состояние: draft, issued, printed, archived, cancelled'),
                'ref_type'      => $field('string', 'Тип объекта-источника'),
                'ref_id'        => $field('string', 'Идентификатор объекта (id или uuid)'),
                'template_code' => $field('string', 'Код шаблона, например ORDER-SUPPLIER'),
                'term'          => $field('string', 'Поиск по номеру, названию или объекту'),
                'from'          => $field('string', 'Созданы не раньше (ГГГГ-ММ-ДД)'),
                'to'            => $field('string', 'Созданы не позже (ГГГГ-ММ-ДД)'),
                'limit'         => $field('integer', 'Сколько строк вернуть (по умолчанию 25)'),
                'offset'        => $field('integer', 'Сдвиг для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($document_row) {
                $filters = array(
                    'status'        => isset($args['status']) ? $args['status'] : NULL,
                    'ref_type'      => isset($args['ref_type']) ? $args['ref_type'] : NULL,
                    'ref_id'        => isset($args['ref_id']) ? $args['ref_id'] : NULL,
                    'template_code' => isset($args['template_code']) ? $args['template_code'] : NULL,
                    'term'          => isset($args['term']) ? $args['term'] : NULL,
                    'from'          => isset($args['from']) ? $args['from'] : NULL,
                    'to'            => isset($args['to']) ? $args['to'] : NULL,
                );

                $rows = array();

                foreach (Service_Erp404_Documents::instance()->search($filters,
                    isset($args['limit']) ? (int) $args['limit'] : 25,
                    isset($args['offset']) ? (int) $args['offset'] : 0) as $document)
                {
                    $rows[] = $document_row($document);
                }

                return array(
                    'summary' => 'Найдено документов: '.Service_Erp404_Documents::instance()->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'documents.get' => array(
            'title'       => 'Документ целиком',
            'description' => 'Один документ: шапка, состояние, снимок выпуска (по флагу with_body) и журнал печати — кто, когда и сколько копий вывел.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id'        => $field('integer', 'Идентификатор документа или его uuid'),
                'with_body' => $field('boolean', 'Вернуть снимок печатной формы (HTML)'),
            ), array('id')),
            'handler'     => function (array $args) use ($document_row, $print_row) {
                $document = Service_Erp404_Documents::instance()->get($args['id']);

                if ( ! $document)
                {
                    throw new Kohana_Exception('Документ не найден');
                }

                $prints = array();

                foreach (Service_Erp404_Documents::instance()->prints($document->id, 100) as $print)
                {
                    $prints[] = $print_row($print);
                }

                $result = array(
                    'summary'  => 'Документ '.$document->number.' — '.$document->status_label()
                        .', печатей: '.(int) $document->print_count,
                    'document' => $document_row($document),
                    'prints'   => $prints,
                );

                if ( ! empty($args['with_body']))
                {
                    $result['rendered'] = ($document->rendered === NULL) ? NULL : (string) $document->rendered;
                    $result['payload']  = $document->payload_array();
                }

                return $result;
            },
        ),

        'documents.preview' => array(
            'title'       => 'Предпросмотр документа',
            'description' => 'Что подставится в форму прямо сейчас — до выпуска. Возвращает список проблем (чего не хватает) и готовый HTML. Удобно проверить шаблон и данные до необратимого шага.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор документа или его uuid'),
            ), array('id')),
            'handler'     => function (array $args) {
                $preview = Service_Erp404_Documents::instance()->preview($args['id']);

                return array(
                    'summary'  => $preview['problems']
                        ? 'Выпуск невозможен: '.count($preview['problems']).' замечание(й)'
                        : 'Данные собраны, документ готов к выпуску',
                    'problems' => $preview['problems'],
                    'ready'    => ! $preview['problems'],
                    'rendered' => $preview['rendered'],
                );
            },
        ),

        'documents.templates' => array(
            'title'       => 'Шаблоны печатных форм',
            'description' => 'Список шаблонов с фильтрами: код, название, тип объекта, вид формы, версия и сколько документов по нему выпущено.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'ref_type' => $field('string', 'Тип объекта-источника'),
                'kind'     => $field('string', 'Вид формы: print, label, act'),
                'code'     => $field('string', 'Точный код шаблона'),
                'term'     => $field('string', 'Поиск по коду и названию'),
                'active'   => $field('boolean', 'Только включённые'),
                'limit'    => $field('integer', 'Сколько строк вернуть (по умолчанию 25)'),
                'offset'   => $field('integer', 'Сдвиг для постраничного вывода'),
            )),
            'handler'     => function (array $args) use ($template_row) {
                $filters = array(
                    'ref_type' => isset($args['ref_type']) ? $args['ref_type'] : NULL,
                    'kind'     => isset($args['kind']) ? $args['kind'] : NULL,
                    'code'     => isset($args['code']) ? $args['code'] : NULL,
                    'term'     => isset($args['term']) ? $args['term'] : NULL,
                    'active'   => (isset($args['active']) AND $args['active']) ? TRUE : NULL,
                );

                $rows = array();

                foreach (Service_Erp404_Templates::instance()->search($filters,
                    isset($args['limit']) ? (int) $args['limit'] : 25,
                    isset($args['offset']) ? (int) $args['offset'] : 0) as $template)
                {
                    $rows[] = $template_row($template);
                }

                return array(
                    'summary' => 'Найдено шаблонов: '.Service_Erp404_Templates::instance()->count($filters),
                    'rows'    => $rows,
                );
            },
        ),

        'documents.template' => array(
            'title'       => 'Шаблон формы целиком',
            'description' => 'Тело шаблона, список подстановок и списков ({{#rows}}), версия и тип объекта. По подстановкам видно, из какого модуля шаблон берёт данные.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id'   => $field('integer', 'Идентификатор шаблона, его uuid или код'),
                'code' => $field('string', 'Код шаблона, если нет id'),
            )),
            'handler'     => function (array $args) use ($template_row) {
                $templates = Service_Erp404_Templates::instance();

                $template = isset($args['id'])
                    ? $templates->get($args['id'])
                    : (isset($args['code']) ? $templates->find_by_code($args['code']) : NULL);

                if ( ! $template)
                {
                    throw new Kohana_Exception('Шаблон не найден');
                }

                return array(
                    'summary'  => 'Шаблон '.$template->code.', версия '.(int) $template->version,
                    'template' => $template_row($template, TRUE),
                );
            },
        ),

        'documents.template_check' => array(
            'title'       => 'Проверка шаблона',
            'description' => 'Проверяет подстановки шаблона и, если указан объект, собирает форму на его данных: видно, что подставится и чего не хватает. Ничего не сохраняет.',
            'scope'       => 'erp404.view',
            'mutating'    => FALSE,
            'destructive' => FALSE,
            'idempotent'  => TRUE,
            'schema'      => $s(array(
                'id'     => $field('integer', 'Идентификатор шаблона, его uuid или код'),
                'ref_id' => $field('string', 'Объект-источник (id или uuid) для проверки на реальных данных'),
            ), array('id')),
            'handler'     => function (array $args) {
                $result = Service_Erp404_Templates::instance()->check(
                    $args['id'],
                    isset($args['ref_id']) ? $args['ref_id'] : NULL
                );

                return array(
                    'summary'  => $result['problems']
                        ? 'В шаблоне проблем: '.count($result['problems'])
                        : 'Шаблон разобран, ошибок подстановок нет',
                    'problems' => $result['problems'],
                    'ready'    => ! $result['problems'],
                    'rendered' => $result['rendered'],
                );
            },
        ),

        // =====================================================================
        //  Изменения
        // =====================================================================

        'documents.create' => array(
            'title'       => 'Завести документ (черновик)',
            'description' => 'Создаёт черновик под шаблон, связанный с объектом (заказ, приёмка, контроль, операция). Номер выдаётся сразу и остаётся за документом. Печатать можно только выпущенный документ.',
            'scope'       => 'erp404.print',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'template_code' => $field('string', 'Код шаблона, например ORDER-SUPPLIER'),
                'template_id'   => $field('integer', 'Идентификатор шаблона (вместо кода)'),
                'ref_type'      => $field('string', 'Тип объекта, если он не задан шаблоном'),
                'ref_id'        => $field('string', 'Идентификатор объекта (id или uuid)'),
                'title'         => $field('string', 'Название документа (по умолчанию — название шаблона)'),
                'note'          => $field('string', 'Примечание'),
            )),
            'handler'     => function (array $args) use ($document_row) {
                $document = Service_Erp404_Documents::instance()->create(array(
                    'template_code' => isset($args['template_code']) ? $args['template_code'] : NULL,
                    'template_id'   => isset($args['template_id']) ? $args['template_id'] : NULL,
                    'ref_type'      => isset($args['ref_type']) ? $args['ref_type'] : NULL,
                    'ref_id'        => isset($args['ref_id']) ? $args['ref_id'] : NULL,
                    'title'         => isset($args['title']) ? $args['title'] : NULL,
                    'note'          => isset($args['note']) ? $args['note'] : NULL,
                ));

                return array(
                    'summary'  => 'Черновик '.$document->number.' создан по шаблону '.$document->template_code,
                    'document' => $document_row($document),
                );
            },
        ),

        'documents.issue' => array(
            'title'       => 'Выпустить документ',
            'description' => 'Собирает данные у владельца (закупки, производство, качество, цех), раскрывает подстановки и фиксирует снимок вместе с версией шаблона. После выпуска документ не переписывается: нужен другой текст — новый документ.',
            'scope'       => 'erp404.print',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'      => $field('integer', 'Идентификатор документа или его uuid'),
                'payload' => $field('object', 'Данные для универсального шаблона (без объекта-источника): {"ref": {…}}'),
            ), array('id')),
            'handler'     => function (array $args) use ($document_row, $action_payload) {
                $document = Service_Erp404_Documents::instance()->issue(
                    $args['id'],
                    NULL,
                    $action_payload($args)
                );

                return array(
                    'summary'  => 'Документ '.$document->number.' выпущен по шаблону '.$document->template_code
                        .' версии '.(int) $document->template_version,
                    'document' => $document_row($document),
                    'rendered' => (string) $document->rendered,
                );
            },
        ),

        'documents.print' => array(
            'title'       => 'Отметить печать',
            'description' => 'Записывает вывод бумаги в журнал печати: кто, когда и сколько копий. Отметка — часть истории документа; сам вывод делает человек или внешний воркер.',
            'scope'       => 'erp404.print',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'     => $field('integer', 'Идентификатор документа или его uuid'),
                'copies' => $field('integer', 'Сколько копий напечатали (по умолчанию 1)'),
                'note'   => $field('string', 'Примечание: куда ушла копия'),
            ), array('id')),
            'handler'     => function (array $args) use ($document_row) {
                $document = Service_Erp404_Documents::instance()->print_document(
                    $args['id'],
                    NULL,
                    isset($args['copies']) ? $args['copies'] : 1,
                    isset($args['note']) ? $args['note'] : NULL
                );

                return array(
                    'summary'  => 'Печать '.$document->number.' отмечена в журнале, всего печатей: '.(int) $document->print_count,
                    'document' => $document_row($document),
                );
            },
        ),

        'documents.archive' => array(
            'title'       => 'Сдать документ в архив',
            'description' => 'Переводит выпущенный документ в архив: он больше не рабочий, но читаемый. Черновик в архив не уходит — его отменяют или выпускают.',
            'scope'       => 'erp404.print',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id' => $field('integer', 'Идентификатор документа или его uuid'),
            ), array('id')),
            'handler'     => function (array $args) use ($document_row) {
                $document = Service_Erp404_Documents::instance()->archive($args['id']);

                return array(
                    'summary'  => 'Документ '.$document->number.' сдан в архив',
                    'document' => $document_row($document),
                );
            },
        ),

        'documents.cancel' => array(
            'title'       => 'Отменить документ',
            'description' => 'Отменяет черновик или выпущенный документ с обязательной причиной. Номер остаётся занятым, запись — в журнале: отменённый документ не исчезает. Подтверждает человек: бумага с этим номером могла уже уйти контрагенту.',
            'scope'       => 'erp404.print',
            'mutating'    => TRUE,
            'destructive' => TRUE,
            'approval'    => TRUE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'     => $field('integer', 'Идентификатор документа или его uuid'),
                'reason' => $field('string', 'Причина отмены (обязательна)'),
            ), array('id', 'reason')),
            'handler'     => function (array $args) use ($document_row) {
                $document = Service_Erp404_Documents::instance()->cancel(
                    $args['id'],
                    isset($args['reason']) ? $args['reason'] : NULL
                );

                return array(
                    'summary'  => 'Документ '.$document->number.' отменён: '.$document->note,
                    'document' => $document_row($document),
                );
            },
        ),

        'documents.template_save' => array(
            'title'       => 'Сохранить шаблон формы',
            'description' => 'Заводит или правит шаблон печатной формы. Правка тела поднимает версию: выпущенные документы помнят версию, по которой их печатали. Подстановка с опечаткой не сохраняется — форма с дырами до бумаги не доходит.',
            'scope'       => 'erp404.manage',
            'mutating'    => TRUE,
            'destructive' => FALSE,
            'idempotent'  => FALSE,
            'schema'      => $s(array(
                'id'       => $field('integer', 'Идентификатор шаблона для правки (без него шаблон заводится)'),
                'code'     => $field('string', 'Код шаблона (у существующего не меняется)'),
                'name'     => $field('string', 'Название шаблона'),
                'ref_type' => $field('string', 'Тип объекта-источника (пусто — универсальный шаблон)'),
                'kind'     => $field('string', 'Вид формы: print, label, act'),
                'body'     => $field('string', 'Тело формы: HTML с подстановками {{ref.number}} и блоками {{#rows ref.lines}}…{{/rows}}'),
                'is_active' => $field('boolean', 'Включён ли шаблон'),
                'note'     => $field('string', 'Примечание'),
            ), array('name', 'body')),
            'handler'     => function (array $args) use ($template_row) {
                $data = array(
                    'code'     => isset($args['code']) ? $args['code'] : NULL,
                    'name'     => isset($args['name']) ? $args['name'] : NULL,
                    'ref_type' => isset($args['ref_type']) ? $args['ref_type'] : NULL,
                    'kind'     => isset($args['kind']) ? $args['kind'] : NULL,
                    'body'     => isset($args['body']) ? $args['body'] : NULL,
                    'note'     => isset($args['note']) ? $args['note'] : NULL,
                );

                if (array_key_exists('is_active', $args))
                {
                    $data['is_active'] = $args['is_active'];
                }

                $templates = Service_Erp404_Templates::instance();

                if (isset($args['id']) AND $args['id'])
                {
                    $template = $templates->update($args['id'], $data);
                }
                else
                {
                    $template = $templates->create($data);
                }

                return array(
                    'summary'  => 'Шаблон '.$template->code.' сохранён, версия '.(int) $template->version,
                    'template' => $template_row($template, TRUE),
                );
            },
        ),
    ),
);
