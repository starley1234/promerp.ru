<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Определение MCP инструментов для модуля erp812_docs.
 *
 * @package    Erp812
 * @category   Mcp
 */
return array(
    'module' => 'erp812_docs',
    'tools'  => array(
        'docs.search' => array(
            'title'       => 'Поиск по документации',
            'description' => 'Полнотекстовый поиск по процессам, модулям, таблицам БД и инструментам PromERP.',
            'scope'       => 'erp812.view',
            'mutating'    => FALSE,
            'schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'query' => array(
                        'type'        => 'string',
                        'description' => 'Поисковый запрос (например: склад, mdm_unit, order_create, закупки)',
                    ),
                ),
                'required'   => array('query'),
            ),
            'handler'     => function (array $args) {
                $q = isset($args['query']) ? trim((string)$args['query']) : '';
                $results = Service_Erp812_DocRegistry::instance()->search($q);
                return array(
                    'summary' => 'Найдено результатов: ' . count($results),
                    'results' => $results,
                );
            },
        ),
        'docs.process.get' => array(
            'title'       => 'Получить схему бизнес-процесса',
            'description' => 'Получить подробную схему и пошаговый регламент сквозного бизнес-процесса PromERP.',
            'scope'       => 'erp812.view',
            'mutating'    => FALSE,
            'schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'process_id' => array(
                        'type'        => 'string',
                        'description' => 'Идентификатор процесса (p2p, o2c, production, wms_flow, maintenance)',
                    ),
                ),
                'required'   => array('process_id'),
            ),
            'handler'     => function (array $args) {
                $id = isset($args['process_id']) ? trim((string)$args['process_id']) : '';
                $processes = Service_Erp812_DocRegistry::instance()->business_processes();
                if (isset($processes[$id])) {
                    return array(
                        'summary' => 'Процесс ' . $processes[$id]['title'],
                        'process' => $processes[$id],
                    );
                }
                throw new Exception('Process not found: ' . $id);
            },
        ),
        'docs.module.get' => array(
            'title'       => 'Получить карточку модуля',
            'description' => 'Получить метаданные, маршруты, таблицы БД и MCP инструменты конкретного модуля PromERP.',
            'scope'       => 'erp812.view',
            'mutating'    => FALSE,
            'schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'module' => array(
                        'type'        => 'string',
                        'description' => 'Имя модуля (например: mdm, orders, warehouse, erp812_docs)',
                    ),
                ),
                'required'   => array('module'),
            ),
            'handler'     => function (array $args) {
                $name = isset($args['module']) ? trim((string)$args['module']) : '';
                $mod = Service_Erp812_DocRegistry::instance()->module($name);
                if ($mod) {
                    return array(
                        'summary' => 'Модуль ' . $mod['title'],
                        'module'  => $mod,
                    );
                }
                throw new Exception('Module not found: ' . $name);
            },
        ),
    ),
);
