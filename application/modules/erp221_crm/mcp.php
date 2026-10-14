<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP модуля продаж (erp221_crm).
 *
 * Агенту доступно только чтение: разобраться, что за сделка, кто клиент, как
 * идут продажи и что менеджер обещал клиенту. Записи в сделку (звонки, смена
 * стадии, задачи) делает человек — у них есть автор и ответственность, а у
 * ответа модели их нет.
 */
$s = function (array $p, array $r = array()) {
    return array('type' => 'object', 'properties' => $p, 'required' => $r);
};
$f = function ($t, $d, array $x = array()) {
    return array('type' => $t, 'description' => $d) + $x;
};

return array('module' => 'crm', 'tools' => array(

    'crm.deals' => array(
        'title'       => 'Сделки и воронка',
        'description' => 'Поиск сделок по клиенту, стадии, ответственному, сроку и сумме. Возвращает воронку по стадиям и общие цифры: сколько в работе, прогноз, выиграно и просрочено. Деньги здесь — оценка продаж, а не бухгалтерская выручка.',
        'scope'       => 'erp221.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $deals = Service_Erp221_Deals::instance();

            $result = $deals->search($a, Arr::get($a, 'limit', 25), 0);

            return array(
                'summary' => 'Сделок: '.$result['total'].', в прогнозе '.$deals->stats()['forecast'].' RUB',
                'deals'   => $result['rows'],
                'board'   => $deals->board($a, 20),
                'stats'   => $deals->stats(),
            );
        },
        'schema'      => $s(array(
            'q'           => $f('string', 'Номер, название сделки или клиент'),
            'stage'       => $f('string', 'Код стадии, например negotiation'),
            'kind'        => $f('string', 'Вид стадии', array('enum' => array('open', 'won', 'lost'))),
            'counterparty'=> $f('integer', 'id клиента в справочнике контрагентов'),
            'assigned_to' => $f('integer', 'id ответственного менеджера'),
            'overdue'     => $f('boolean', 'Только сделки с прошедшим сроком'),
            'stale'       => $f('integer', 'Дней без записей в ленте (застой)'),
            'limit'       => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),

    'crm.deal' => array(
        'title'       => 'Карточка сделки',
        'description' => 'Одна сделка целиком: клиент, стадия, деньги, участники, лента работы с задачами, история разборов ИИ. Нужна, когда по списку crm.deals требуется подробность.',
        'scope'       => 'erp221.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $deal = Service_Erp221_Deals::instance()->get($a['deal']);

            return array(
                'summary'  => 'Сделка '.$deal['number'].' «'.$deal['title'].'»: '.$deal['stage']['label'].', '.$deal['amount'].' '.$deal['currency'],
                'deal'     => $deal,
                'contacts' => $deal['contacts'],
                'timeline' => $deal['timeline'],
                'tasks'    => $deal['tasks'],
                'insights' => $deal['insights'],
            );
        },
        'schema'      => $s(array(
            'deal' => $f('string', 'Номер сделки (DEAL-…) или её id'),
        ), array('deal')),
    ),

    'crm.clients' => array(
        'title'       => 'Клиенты и профиль продаж',
        'description' => 'Поиск клиентов по названию, ИНН или состоянию. Клиенты — это контрагенты erp301; CRM дополняет их профилем продаж (сегмент, отрасль, источник) и уровнем ЛПР у контактов.',
        'scope'       => 'erp221.clients.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $page = Service_Erp221_Clients::instance()->search($a, Arr::get($a, 'limit', 25), 0);

            return array(
                'summary' => 'Клиентов: '.$page['total'],
                'clients' => $page['rows'],
                'stats'   => Service_Erp221_Clients::instance()->stats(),
            );
        },
        'schema'      => $s(array(
            'term'   => $f('string', 'Название, код или ИНН'),
            'status' => $f('string', 'Состояние', array('enum' => array('active', 'blocked', 'archived'))),
            'role'   => $f('string', 'Роль контрагента', array('enum' => array('customer', 'supplier', 'carrier', 'contractor'))),
            'limit'  => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),

    'crm.analytics' => array(
        'title'       => 'Аналитика продаж',
        'description' => 'Воронка, динамика по месяцам, работа менеджеров, источники обращений и причины отказов за период. Прогноз взвешен по вероятности стадии.',
        'scope'       => 'erp221.analytics.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $analytics = Service_Erp221_Analytics::instance();

            return array(
                'summary'   => 'Сделок в работе: '.$analytics->overview($a)['open_count'].', конверсия '.$analytics->overview($a)['conversion'].' %',
                'overview'  => $analytics->overview($a),
                'funnel'    => $analytics->funnel($a),
                'dynamics'  => $analytics->dynamics(Arr::get($a, 'months', 6)),
                'managers'  => $analytics->managers($a),
                'sources'   => $analytics->sources($a),
                'reasons'   => $analytics->loss_reasons($a),
            );
        },
        'schema'      => $s(array(
            'from'        => $f('string', 'Дата начала периода, YYYY-MM-DD'),
            'to'          => $f('string', 'Дата конца периода, YYYY-MM-DD'),
            'assigned_to' => $f('integer', 'id менеджера'),
            'months'      => $f('integer', 'Сколько месяцев динамики', array('minimum' => 1, 'maximum' => 24)),
        )),
    ),

    'crm.journal' => array(
        'title'       => 'Лента работы и задачи',
        'description' => 'Что происходило по сделкам и клиентам: звонки, письма, встречи, задачи и файлы. Видны незакрытые задачи с их сроком — что менеджер обещал и когда.',
        'scope'       => 'erp221.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $journal = Service_Erp221_Timeline::instance()->journal($a, Arr::get($a, 'limit', 50), 0);

            return array(
                'summary' => 'Записей: '.$journal['total'],
                'entries' => $journal['rows'],
                'tasks'   => Service_Erp221_Timeline::instance()->tasks(Arr::get($a, 'assigned_to'), $a),
            );
        },
        'schema'      => $s(array(
            'deal_id'     => $f('integer', 'Записи одной сделки'),
            'counterparty'=> $f('integer', 'Записи одного клиента'),
            'type'        => $f('string', 'Вид записи', array('enum' => array('comment', 'call', 'meeting', 'email', 'task', 'file', 'note', 'stage', 'system'))),
            'status'      => $f('string', 'Состояние', array('enum' => array('pending', 'completed', 'cancelled'))),
            'assigned_to' => $f('integer', 'Чьи задачи показать'),
            'overdue'     => $f('boolean', 'Только просроченные задачи'),
            'limit'       => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),

    'crm.kb' => array(
        'title'       => 'База знаний менеджера',
        'description' => 'Готовые ответы на типовые вопросы клиентов: сроки, цены, гарантия. Поиск по вопросу, ответу и категории.',
        'scope'       => 'erp221.kb.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $rows = Service_Erp221_Knowledge::instance()->search(Arr::get($a, 'q', ''), (int) Arr::get($a, 'limit', 20));

            return array('summary' => 'Статей: '.count($rows), 'articles' => $rows);
        },
        'schema'      => $s(array(
            'q'     => $f('string', 'Вопрос или текст ответа'),
            'limit' => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 100)),
        )),
    ),
));
