<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инструменты MCP модуля QR-маркировки (erp199_sqr).
 *
 * Агенту доступно только чтение: разобраться, что за предмет, что с ним
 * происходило и как идёт инвентаризация. Записи в физический мир (привязка
 * наклейки, сценарий, смена статуса) выполняются человеком в терминале — у них
 * есть автор, место и момент, а у ответа модели их нет.
 */
$s = function (array $p, array $r = array()) {
    return array('type' => 'object', 'properties' => $p, 'required' => $r);
};
$f = function ($t, $d, array $x = array()) {
    return array('type' => $t, 'description' => $d) + $x;
};

return array('module' => 'erp199', 'tools' => array(

    'qr.resolve' => array(
        'title'       => 'Что за предмет по коду наклейки',
        'description' => 'Расшифровывает код с QR-наклейки: состояние наклейки, цель (объект, партия, заказ, сотрудник), доступные сценарии и последние действия. Код можно передавать как есть — ссылкой из QR.',
        'scope'       => 'erp199.scan',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $r = Service_Erp199_Scan::instance()->resolve($a['code'], NULL, array());

            return array('summary' => 'Наклейка '.$a['code'].': '.$r['message'], 'resolve' => $r);
        },
        'schema'      => $s(array('code' => $f('string', 'Код наклейки, UUID или ссылка из QR')), array('code')),
    ),

    'qr.tokens' => array(
        'title'       => 'Реестр наклеек',
        'description' => 'Поиск наклеек по коду, подписи цели, состоянию, виду цели и партии печати. Возвращает состояние и к какой цели привязана наклейка.',
        'scope'       => 'erp199.tokens.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $r = Service_Erp199_Tokens::instance()->search($a, Arr::get($a, 'limit', 50));

            return array('summary' => 'Наклеек: '.$r['total'], 'tokens' => $r['rows'], 'stats' => Service_Erp199_Tokens::instance()->stats());
        },
        'schema'      => $s(array(
            'q'      => $f('string', 'Код наклейки, подпись цели или пометка'),
            'status' => $f('string', 'Состояние наклейки', array('enum' => array('new', 'active', 'blocked', 'damaged', 'lost', 'archived'))),
            'kind'   => $f('string', 'Вид цели', array('enum' => array('local', 'registry', 'item', 'lot', 'order', 'employee'))),
            'batch'  => $f('string', 'Партия печати'),
            'limit'  => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),

    'qr.token' => array(
        'title'       => 'Карточка наклейки',
        'description' => 'Одна наклейка целиком: цель у владельца данных, фотографии и история действий. Нужна, когда по коду из qr.resolve требуется подробность.',
        'scope'       => 'erp199.tokens.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $t = Service_Erp199_Tokens::instance()->get($a['token']);

            return array(
                'summary'  => 'Наклейка '.$t['code'].': '.Service_Erp199_Tokens::instance()->status_label($t['status']),
                'token'    => $t,
                'target'   => $t['target_kind'] ? Service_Erp199_Targets::instance()->card($t['target_kind'], $t['target_id']) : NULL,
                'photos'   => Service_Erp199_Media::instance()->search(array('token_id' => $t['id'])),
                'activity' => Service_Erp199_Scan::instance()->activity($t['id'], Arr::get($a, 'limit', 20)),
            );
        },
        'schema'      => $s(array(
            'token' => $f('string', 'Код или UUID наклейки'),
            'limit' => $f('integer', 'Сколько последних действий показать', array('minimum' => 1, 'maximum' => 100)),
        ), array('token')),
    ),

    'qr.objects' => array(
        'title'       => 'Свободные объекты модуля',
        'description' => 'Объекты, заведённые в поле или вручную (то, для чего нет владельца в других модулях): статус, тип, атрибуты и привязанные наклейки.',
        'scope'       => 'erp199.objects.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $r = Service_Erp199_Objects::instance()->search($a, Arr::get($a, 'limit', 50));

            return array('summary' => 'Объектов: '.$r['total'], 'objects' => $r['rows']);
        },
        'schema'      => $s(array(
            'q'      => $f('string', 'Название, серийный номер или марка'),
            'status' => $f('string', 'Статус', array('enum' => array('draft', 'active', 'rejected', 'archived'))),
            'type'   => $f('string', 'Код типа, например equipment'),
            'limit'  => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),

    'qr.inventories' => array(
        'title'       => 'Инвентаризации и прогресс',
        'description' => 'Открытые и закрытые сессии инвентаризации: область проверки, прогресс и расхождения «ожидалось − отмечено».',
        'scope'       => 'erp199.inventories.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $service = Service_Erp199_Inventories::instance();
            $rows    = $service->all(Arr::get($a, 'limit', 20));
            $result  = array();

            foreach ($rows as $row)
            {
                $result[] = array('inventory' => $row, 'progress' => $service->progress($row['id']));
            }

            return array('summary' => 'Сессий: '.count($result), 'inventories' => $result);
        },
        'schema'      => $s(array('limit' => $f('integer', 'Сколько сессий показать', array('minimum' => 1, 'maximum' => 200)))),
    ),

    'qr.journal' => array(
        'title'       => 'Журнал QR-маркировки',
        'description' => 'Кто, когда и где касался физического мира: сканирования, фото, смены статуса, отметки инвентаризации. Фильтры по коду наклейки, действию и результату.',
        'scope'       => 'erp199.activity.view',
        'mutating'    => FALSE,
        'handler'     => function (array $a) {
            $r = Service_Erp199_Tokens::instance()->journal($a, Arr::get($a, 'limit', 50));

            return array('summary' => 'Записей: '.$r['total'], 'journal' => $r['rows']);
        },
        'schema'      => $s(array(
            'q'      => $f('string', 'Код наклейки, комментарий или содержимое записи'),
            'action' => $f('string', 'Вид действия', array('enum' => array_keys(Service_Erp199_Tokens::instance()->actions()))),
            'result' => $f('string', 'Результат, например ok'),
            'code'   => $f('string', 'Код наклейки'),
            'limit'  => $f('integer', 'Лимит', array('minimum' => 1, 'maximum' => 200)),
        )),
    ),
));
