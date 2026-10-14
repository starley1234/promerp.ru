<?php defined('SYSPATH') or die('No direct script access.');

$s = function (array $properties, array $required = array()) {
    return array('type' => 'object', 'properties' => $properties, 'required' => $required);
};
$field = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};
$row = function (Model_Erp301_Counterparty $model) {
    return array(
        'id' => (int) $model->id,
        'code' => (string) $model->code,
        'name' => (string) $model->name,
        'full_name' => $model->full_name ? (string) $model->full_name : NULL,
        'kind' => (string) $model->kind,
        'roles' => $model->role_codes(),
        'inn' => $model->inn ? (string) $model->inn : NULL,
        'kpp' => $model->kpp ? (string) $model->kpp : NULL,
        'country' => $model->country ? (string) $model->country : NULL,
        'city' => $model->city ? (string) $model->city : NULL,
        'status' => (string) $model->status,
        'rating' => (int) $model->rating,
    );
};

return array(
    'module' => 'erp301',
    'tools' => array(
        'counterparty.search' => array(
            'title' => 'Поиск контрагентов',
            'description' => 'Клиенты, поставщики, перевозчики и подрядчики. Возвращает id для документов, закупок и продаж; фильтрует по роли без угадывания внутренних идентификаторов.',
            'scope' => 'erp301.view',
            'mutating' => FALSE,
            'handler' => function (array $args) use ($row) {
                $filters = array_intersect_key($args, array_flip(array('term', 'role', 'status', 'kind')));
                $result = Service_Erp301_Counterparties::instance()->search(
                    $filters,
                    isset($args['limit']) ? (int) $args['limit'] : 25,
                    isset($args['offset']) ? (int) $args['offset'] : 0
                );
                $rows = array();
                foreach ($result['rows'] as $model) $rows[] = $row($model);
                return array(
                    'summary' => 'Найдено контрагентов: '.$result['total'],
                    'rows' => $rows,
                    'total' => (int) $result['total'],
                    'limit' => isset($args['limit']) ? (int) $args['limit'] : 25,
                    'offset' => isset($args['offset']) ? (int) $args['offset'] : 0,
                );
            },
            'schema' => $s(array(
                'term' => $field('string', 'Название, код или ИНН'),
                'role' => $field('string', 'Роль', array('enum' => array('supplier', 'customer', 'carrier', 'contractor'))),
                'status' => $field('string', 'Статус', array('enum' => array('active', 'blocked', 'archived'))),
                'kind' => $field('string', 'Тип лица', array('enum' => array('legal', 'individual', 'state', 'foreign'))),
                'limit' => $field('integer', 'Размер страницы', array('minimum' => 1, 'maximum' => 100, 'default' => 25)),
                'offset' => $field('integer', 'Смещение', array('minimum' => 0, 'default' => 0)),
            )),
        ),
        'counterparty.get' => array(
            'title' => 'Карточка контрагента',
            'description' => 'Реквизиты одного клиента или поставщика по id из counterparty.search.',
            'scope' => 'erp301.view',
            'mutating' => FALSE,
            'handler' => function (array $args) use ($row) {
                $model = Service_Erp301_Counterparties::instance()->get((int) $args['id']);
                return array('summary' => $model->name) + $row($model);
            },
            'schema' => $s(array(
                'id' => $field('integer', 'ID из counterparty.search'),
            ), array('id')),
        ),
    ),
);
