<?php defined('SYSPATH') or die('No direct script access.');

$field = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};
$schema = function (array $properties = array()) {
    return array('type' => 'object', 'properties' => $properties);
};

return array(
    'module' => 'erp401',
    'title' => 'Складской учёт',
    'description' => 'Остатки, склады и журнал движений WMS для сверки производственного контура.',
    'tools' => array(
        'warehouse.stock.list' => array(
            'title' => 'Остатки по складам',
            'description' => 'Возвращает физический, зарезервированный и доступный остаток с номенклатурой и признаком ниже минимума.',
            'scope' => 'erp401_wms.view_stock',
            'mutating' => FALSE,
            'schema' => $schema(array(
                'warehouse_id' => $field('integer', 'ID склада'),
                'item_id' => $field('integer', 'ID позиции номенклатуры'),
                'low_only' => $field('boolean', 'Только позиции ниже минимального остатка'),
                'limit' => $field('integer', 'Размер страницы', array('minimum' => 1, 'maximum' => 100, 'default' => 50)),
                'offset' => $field('integer', 'Смещение', array('minimum' => 0, 'default' => 0)),
            )),
            'handler' => function (array $args) {
                $filters = array_intersect_key($args, array_flip(array('warehouse_id', 'item_id', 'low_only')));
                $result = Service_Erp401_Stock::instance()->balances($filters, isset($args['limit']) ? $args['limit'] : 50, isset($args['offset']) ? $args['offset'] : 0);
                return array('summary' => 'Остатков найдено: '.$result['total'], 'rows' => $result['rows'], 'total' => $result['total']);
            },
        ),
        'warehouse.movements.list' => array(
            'title' => 'Движения склада',
            'description' => 'Журнал приходов, расходов, перемещений и корректировок с фильтрами по складу, позиции и датам.',
            'scope' => 'erp401_wms.view_stock',
            'mutating' => FALSE,
            'schema' => $schema(array(
                'type' => $field('string', 'Тип движения', array('enum' => array('receipt', 'issue', 'transfer', 'adjust'))),
                'warehouse_id' => $field('integer', 'ID склада'),
                'item_id' => $field('integer', 'ID позиции'),
                'date_from' => $field('string', 'Дата от YYYY-MM-DD'),
                'date_to' => $field('string', 'Дата до YYYY-MM-DD'),
                'limit' => $field('integer', 'Размер страницы', array('minimum' => 1, 'maximum' => 100, 'default' => 50)),
                'offset' => $field('integer', 'Смещение', array('minimum' => 0, 'default' => 0)),
            )),
            'handler' => function (array $args) {
                $filters = array_intersect_key($args, array_flip(array('type', 'warehouse_id', 'item_id', 'date_from', 'date_to')));
                $result = Service_Erp401_Stock::instance()->movements($filters, isset($args['limit']) ? $args['limit'] : 50, isset($args['offset']) ? $args['offset'] : 0);
                return array('summary' => 'Движений найдено: '.$result['total'], 'rows' => $result['rows'], 'total' => $result['total']);
            },
        ),
        'warehouse.list' => array(
            'title' => 'Список складов',
            'description' => 'Активные склады и точки хранения, доступные для фильтрации остатков и движений.',
            'scope' => 'erp401_wms.view_stock',
            'mutating' => FALSE,
            'schema' => $schema(),
            'handler' => function (array $args) {
                $rows = array();
                foreach (Service_Erp401_Stock::instance()->warehouses(TRUE) as $warehouse) {
                    $rows[] = array(
                        'id' => (int) $warehouse->id,
                        'code' => (string) $warehouse->code,
                        'name' => (string) $warehouse->name,
                        'type' => (string) $warehouse->type,
                    );
                }
                return array('summary' => 'Складов: '.count($rows), 'warehouses' => $rows, 'total' => count($rows));
            },
        ),
    ),
);
