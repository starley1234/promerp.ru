<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP для журнала действий (erp119_audit).
 *
 * Агент должен уметь отвечать на вопрос «что здесь происходило»: кто менял
 * заказ, когда, что было до и после. Это тот же журнал, что видит человек в
 * `/audit/journal`, только через MCP — со своим правом `erp119_audit.view`.
 *
 * @package    Erp119
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

return array(
    'module' => 'erp119',
    'tools'  => array(

        'audit.journal.search' => array(
            'title'       => 'Поиск по журналу действий',
            'description' => 'Записи журнала: кто, когда, что изменил. Фильтры по модулю, сущности, действию, важности, периоду и тексту.',
            'scope'       => 'erp119_audit.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $filters = array();

                foreach (array('module', 'entity_type', 'entity_id', 'action', 'severity', 'source', 'term', 'from', 'to') as $name)
                {
                    if ( ! empty($args[$name]))
                    {
                        $filters[$name] = $args[$name];
                    }
                }

                $result = Service_Erp119_Query::instance()->search(
                    $filters,
                    isset($args['limit']) ? (int) $args['limit'] : 20,
                    isset($args['offset']) ? (int) $args['offset'] : 0
                );

                $rows = array();

                foreach ($result['rows'] as $row)
                {
                    $rows[] = array(
                        'id'            => (int) $row['id'],
                        'occurred_at'   => $row['occurred_at'],
                        'module'        => $row['module'],
                        'entity_type'   => $row['entity_type'],
                        'entity_id'     => $row['entity_id'],
                        'entity_label'  => $row['entity_label'],
                        'action'        => $row['action'],
                        'severity'      => $row['severity'],
                        'actor_name'    => $row['actor_name'],
                        'source'        => $row['source'],
                        'message'       => $row['message'],
                        'changes'       => $row['changes'],
                    );
                }

                return array('summary' => 'Записей: '.(int) $result['total'], 'total' => (int) $result['total'], 'rows' => $rows);
            },
            'schema'      => $s(array(
                'module'      => $field('string', 'Модуль (erp402, erp401, erp228 …)'),
                'entity_type' => $field('string', 'Тип сущности (order, movement, tech_card …)'),
                'entity_id'   => $field('string', 'Идентификатор сущности (id или uuid)'),
                'action'      => $field('string', 'Действие (create, update, status, delete …)'),
                'severity'    => $field('string', 'Важность', array('enum' => array('info', 'notice', 'warning', 'critical'))),
                'term'        => $field('string', 'Поиск по подписи, тексту и номеру'),
                'from'        => $field('string', 'Начало периода, ГГГГ-ММ-ДД'),
                'to'          => $field('string', 'Конец периода, ГГГГ-ММ-ДД'),
                'limit'       => $field('integer', 'Сколько записей вернуть', array('minimum' => 1, 'maximum' => 100, 'default' => 20)),
                'offset'      => $field('integer', 'Смещение', array('minimum' => 0, 'default' => 0)),
            )),
        ),

        'audit.entity.timeline' => array(
            'title'       => 'История объекта',
            'description' => 'Все изменения конкретного объекта по порядку: что было до и после. Например, история производственного заказа по его uuid.',
            'scope'       => 'erp119_audit.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $result = Service_Erp119_Query::instance()->timeline(
                    $args['entity_type'],
                    (string) $args['entity_id'],
                    isset($args['limit']) ? (int) $args['limit'] : 30,
                    0
                );

                $rows = array();

                foreach ($result['rows'] as $row)
                {
                    $rows[] = array(
                        'occurred_at' => $row['occurred_at'],
                        'action'      => $row['action'],
                        'actor_name'  => $row['actor_name'],
                        'message'     => $row['message'],
                        'changes'     => $row['changes'],
                    );
                }

                return array(
                    'summary'     => 'Изменений: '.(int) $result['total'],
                    'entity_type' => $args['entity_type'],
                    'entity_id'   => (string) $args['entity_id'],
                    'total'       => (int) $result['total'],
                    'timeline'    => $rows,
                );
            },
            'schema'      => $s(array(
                'entity_type' => $field('string', 'Тип сущности (order, movement, tech_card …)'),
                'entity_id'   => $field('string', 'Идентификатор сущности (id или uuid)'),
                'limit'       => $field('integer', 'Сколько записей вернуть', array('minimum' => 1, 'maximum' => 100, 'default' => 30)),
            ), array('entity_type', 'entity_id')),
        ),

        'audit.summary' => array(
            'title'       => 'Сводка журнала',
            'description' => 'Активность за период: сколько записей, по важности, топ действий, объектов и авторов. Ответ на вопрос «что происходило на этой неделе».',
            'scope'       => 'erp119_audit.view',
            'mutating'    => FALSE,
            'handler'     => function (array $args) {
                $days = isset($args['days']) ? (int) $args['days'] : 7;

                $stats = Service_Erp119_Query::instance()->stats($days);

                return array('summary' => 'Записей за '.$days.' дн.: '.(int) $stats['total']) + $stats;
            },
            'schema'      => $s(array(
                'days' => $field('integer', 'Период в днях', array('minimum' => 1, 'maximum' => 365, 'default' => 7)),
            )),
        ),
    ),
);
