<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Журнал QR-маркировки: кто, чем, когда и где касался физического мира.
 *
 * Журнал читается отдельно от карточек: разобрать смену или найти, кто сканировал
 * конкретную наклейку, удобнее здесь.
 *
 * Переменные: rows (array rows/total), page, url, filters, actions.
 */
$items = $rows['rows'];
$total = $rows['total'];
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Журнал QR-маркировки</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Каждое сканирование, фото, смена статуса и отметка инвентаризации записаны с автором
            (пользователь или ключ устройства) и временем. Всего записей: <?php echo number_format($total, 0, '.', ' '); ?>.
        </p>
    </div>

    <form method="get" action="/qr/activity" class="mb-4 flex flex-wrap items-start gap-3">
        <div class="min-w-[16rem] flex-1">
            <?php echo Component::render('form_field', array('id' => 'act_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['q'], 'hint' => 'код наклейки, комментарий, содержимое записи')); ?>
        </div>
        <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Применить</button>
        <a href="/qr/activity" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Сбросить</a>
    </form>

    <?php
    $rows_html = array();

    foreach ($items as $row)
    {
        $details = array();

        foreach ((array) $row['payload'] as $key => $value)
        {
            if (is_scalar($value) AND $value !== '')
            {
                $details[] = HTML::chars($key).'='.HTML::chars((string) $value);
            }
        }

        $geo = ($row['geo_lat'] AND $row['geo_lon'])
            ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['geo_lat']).', '.HTML::chars($row['geo_lon']).'</div>'
            : '';

        $rows_html[] = array(
            '<span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $row['created_at']).'</span>',
            $row['token_code']
                ? '<a href="/qr/token/'.HTML::chars($row['token_code']).'" class="font-mono text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['token_code']).'</a>'
                : '<span class="text-xs text-gray-500 dark:text-gray-400">без наклейки</span>',
            HTML::chars(Arr::get($actions, (string) $row['action_type'], (string) $row['action_type'])),
            $row['result'] ? Component::render('badge', array('label' => (string) $row['result'], 'state' => $row['result'] === 'ok' ? 'ok' : 'warn')) : '—',
            ($details ? implode(' · ', $details) : '').($row['comment'] ? '<div>'.HTML::chars((string) $row['comment']).'</div>' : ''),
            $row['user_id'] ? 'пользователь #'.(int) $row['user_id'] : ($row['key_id'] ? 'ключ #'.(int) $row['key_id'] : '—'),
            $geo ?: '—',
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Журнал действий QR-маркировки',
        'columns'    => array(
            array('label' => 'Когда', 'priority' => 2),
            array('label' => 'Наклейка'),
            array('label' => 'Действие'),
            array('label' => 'Результат'),
            array('label' => 'Что записано', 'priority' => 2),
            array('label' => 'Автор', 'priority' => 3),
            array('label' => 'Где', 'priority' => 3),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'Записей нет',
        'empty_hint'  => 'Отсканируйте наклейку в терминале — запись появится здесь.',
    ));

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / 50),
        'total' => $total,
        'url'   => function ($p) use ($filters) {
            return '/qr/activity?'.http_build_query(array_filter(array('q' => $filters['q'], 'page' => $p)));
        },
    ));
    ?>
</div>
