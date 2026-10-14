<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP демонстрационного стенда (erp811_demo).
 *
 * Агент видит стенд так же, как посетитель: что за производство, что на нём
 * собрано, куда смотреть и в каком порядке. Все инструменты читающие.
 *
 * Почему здесь нет ни одного изменяющего инструмента. Стенд показывает систему
 * в отдельной базе; вход и выход из демо-режима — это смена базы у сессии, а не
 * данные завода. Если такое действие отдать агенту, он сможет «переключить»
 * чужую сессию, поэтому вход живёт только на странице `/demo` и только POST-ом.
 *
 * Сборка стенда (`bin/console demo --build`) — операция инфраструктуры, а не
 * работа с данными: она сносит и заново наполняет демо-базу. Права на неё
 * выдаются доступом к серверу, а не ключом MCP.
 *
 * @package    Erp811
 * @category   Mcp
 */

$field = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};

return array(
    'module'      => 'erp811_demo',
    'title'       => 'Демонстрационный стенд',
    'description' => 'Синтетический стенд «Печатня 3D»: состояние сборки, сценарий производства, маршрут осмотра и состав данных. Всё только на чтение — стенд не принимает правок.',
    'tools'       => array(

        'demo.status' => array(
            'title'       => 'Состояние демо-стенда',
            'description' => 'Включён ли стенд, собрана ли демо-база, когда и кем собрана, сколько чего в ней. Блок без данных отвечает available=false и причиной, а не нулём.',
            'scope'       => 'erp811.view',
            'mutating'    => FALSE,
            'schema'      => array('type' => 'object', 'properties' => array()),
            'handler'     => function (array $args) {
                $demo = Service_Erp811_Demo::instance();

                if ( ! $demo->enabled())
                {
                    return array('summary' => 'Демо-стенд выключен', 'enabled' => FALSE);
                }

                $status = $demo->status();

                return array(
                    'summary'  => empty($status['available'])
                        ? ($status['tables'] > 0
                            ? 'Схема создана, наполнение не завершено: продолжите сборку в /demo/setup'
                            : 'Стенд не собран: откройте /demo/setup')
                        : 'Стенд собран '.$status['built_at'].': слоёв '.count((array) $status['counts']),
                    'code'     => strtoupper($status['build_state']),
                    'enabled'  => TRUE,
                    // active/inside означает cookie текущего HTTP-запроса, а не
                    // готовность стенда. У MCP-вызова значение обычно false.
                    'inside'   => $demo->active(),
                    'inside_hint' => 'inside показывает режим текущей browser-сессии; готовность определяется status.available',
                    'database' => $demo->database_name(),
                    'status'   => $status,
                );
            },
        ),

        'demo.scenario' => array(
            'title'       => 'Сценарий стенда',
            'description' => 'Что за производство показывает стенд: компания, детали, материалы, рабочие центры, смены, глубина истории. Нужен, чтобы объяснять цифры витрин словами производства.',
            'scope'       => 'erp811.view',
            'mutating'    => FALSE,
            'schema'      => array('type' => 'object', 'properties' => array()),
            'handler'     => function (array $args) {
                $scenario = Service_Erp811_Scenario::instance();

                return array(
                    'summary'      => $scenario->company()['name'].': '.count($scenario->parts()).' деталей, '
                        .count($scenario->materials()).' материалов, '.count($scenario->centers()).' центров',
                    'code'         => $scenario->code(),
                    'company'      => $scenario->company(),
                    'headline'     => $scenario->headline(),
                    'parts'        => $scenario->parts(),
                    'materials'    => $scenario->materials(),
                    'centers'      => $scenario->centers(),
                    'history_days' => $scenario->history_days(),
                    'orders'       => $scenario->order_count(),
                );
            },
        ),

        'demo.tour' => array(
            'title'       => 'Маршрут осмотра',
            'description' => 'Порядок показа системы: адрес экрана, что на нём видно и какой вопрос к нему задать. Годится и для человека, и для автономной демонстрации роботом.',
            'scope'       => 'erp811.view',
            'mutating'    => FALSE,
            'schema'      => array('type' => 'object', 'properties' => array()),
            'handler'     => function (array $args) {
                $rows = array();

                foreach (Service_Erp811_Scenario::instance()->tour() as $index => $step)
                {
                    $rows[] = array(
                        'step'  => isset($step['step']) ? (int) $step['step'] : $index + 1,
                        'title' => isset($step['title']) ? $step['title'] : '',
                        'url'   => isset($step['url']) ? $step['url'] : '/',
                        'what'  => isset($step['what']) ? $step['what'] : '',
                        'ask'   => isset($step['ask']) ? $step['ask'] : '',
                    );
                }

                return array(
                    'summary' => 'Шагов осмотра: '.count($rows),
                    'steps'   => $rows,
                );
            },
        ),

        'demo.counts' => array(
            'title'       => 'Сколько данных на стенде',
            'description' => 'Журнал последней сборки: сколько заказов, операций, контролей, документов и снимков собрано в демо-базе, и какие шаги пропущены.',
            'scope'       => 'erp811.view',
            'mutating'    => FALSE,
            'schema'      => array('type' => 'object', 'properties' => array()),
            'handler'     => function (array $args) {
                $status = Service_Erp811_Demo::instance()->status();

                if (empty($status['available']))
                {
                    return array(
                        'summary' => $status['tables'] > 0 ? 'Схема готова, наполнение не завершено' : 'Стенд не собран',
                        'code'    => strtoupper($status['build_state']),
                        'counts'  => array(),
                        'hint'    => 'Откройте /demo/setup и запустите или продолжите сборку',
                    );
                }

                return array(
                    'summary'  => 'Собрано: '.count((array) $status['counts']).' показателей, замечаний '
                        .count((array) $status['problems']),
                    'built_at' => isset($status['built_at']) ? $status['built_at'] : NULL,
                    'counts'   => (array) $status['counts'],
                    'problems' => (array) $status['problems'],
                );
            },
        ),

        'demo.runs' => array(
            'title'       => 'Журнал сборок стенда',
            'description' => 'Кто и когда собирал демо-базу, по какому сценарию и с каким составом данных. Нужен, чтобы понимать, насколько свежая демонстрация перед показом.',
            'scope'       => 'erp811.view',
            'mutating'    => FALSE,
            'schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'limit' => $field('integer', 'Сколько последних сборок вернуть (по умолчанию 10)'),
                ),
            ),
            'handler'     => function (array $args) {
                $limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 10;

                return array(
                    'summary' => 'Последних сборок: '.$limit,
                    'runs'    => Service_Erp811_Demo::instance()->runs($limit),
                );
            },
        ),
    ),
);
