<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Свободные объекты модуля: то, что завели вручную или с телефона в поле.
 *
 * Это не номенклатура и не реестр — у них свои владельцы. Здесь лежат предметы,
 * которым пока нет места в чужом модуле: найденное оборудование, инструмент без
 * учётного номера, новый образец. После модерации объект можно связать с реестром.
 *
 * Переменные: rows, total, page, per_page, filters, statuses, types,
 * can_moderate, url, messages.
 */
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Свободные объекты</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Объекты, заведённые в модуле: черновики из поля после сканирования и записи, созданные вручную.
            Принятый объект можно привязать к наклейке и использовать в сценариях.
        </p>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <form method="get" action="/qr/objects" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-[14rem] flex-1">
                <?php echo Component::render('form_field', array('id' => 'obj_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['q'], 'hint' => 'название, серийный номер, марка')); ?>
            </div>
            <div class="min-w-[12rem]">
                <label for="obj_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Статус</label>
                <select id="obj_status" name="status" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">любой</option>
                    <?php foreach ($statuses as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $filters['status'] === $code ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[12rem]">
                <label for="obj_type" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Тип</label>
                <select id="obj_type" name="type" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">любой</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo HTML::chars($type['code']); ?>" <?php echo $filters['type'] === $type['code'] ? 'selected' : ''; ?>><?php echo HTML::chars($type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Применить</button>
            <a href="/qr/objects" class="mt-6 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Сбросить</a>
        </div>
    </form>

    <?php
    $rows_html = array();

    foreach ($rows as $row)
    {
        $tokens = '';

        foreach ($row['tokens'] as $token)
        {
            $tokens .= '<a href="/qr/token/'.HTML::chars($token['code']).'" class="mr-2 font-mono text-xs text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($token['code']).'</a>';
        }

        $moderate = '';

        if ($can_moderate AND $row['status'] === 'draft')
        {
            $moderate = '<form method="post" action="/qr/moderate" class="inline">'
                .'<input type="hidden" name="id" value="'.(int) $row['id'].'">'
                .'<input type="hidden" name="status" value="active">'
                .'<button type="submit" class="rounded bg-emerald-600 px-2 py-1 text-xs text-white hover:bg-emerald-700">Принять</button></form> '
                .'<form method="post" action="/qr/moderate" class="inline">'
                .'<input type="hidden" name="id" value="'.(int) $row['id'].'">'
                .'<input type="hidden" name="status" value="rejected">'
                .'<button type="submit" class="rounded bg-rose-600 px-2 py-1 text-xs text-white hover:bg-rose-700">Отклонить</button></form>';
        }

        $rows_html[] = array(
            '<a href="/qr/object/'.(int) $row['id'].'" class="font-medium text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['name']).'</a>'
                .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $row['type_label']).'</div>',
            Component::render('badge', array('label' => Service_Erp199_Objects::instance()->status_label($row['status']), 'state' => Service_Erp199_Objects::instance()->state_of($row['status']))),
            HTML::chars((string) $row['serial_number']) ?: '—',
            $row['source'] === 'ai' ? 'распознан по фото' : ($row['source'] === 'scan' ? 'заведён сканированием' : 'вручную'),
            $tokens ?: '<span class="text-xs text-gray-500 dark:text-gray-400">нет</span>',
            $moderate ?: '<span class="text-xs text-gray-500 dark:text-gray-400">—</span>',
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Свободные объекты модуля',
        'columns'    => array(
            array('label' => 'Объект'),
            array('label' => 'Статус'),
            array('label' => 'Серийный номер', 'priority' => 2),
            array('label' => 'Откуда', 'priority' => 3),
            array('label' => 'Наклейки', 'priority' => 2),
            array('label' => 'Модерация', 'priority' => 3),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'Объектов нет',
        'empty_hint'  => 'Объекты появятся здесь после сканирования незнакомого кода с телефона.',
    ));

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / $per_page),
        'total' => $total,
        'url'   => function ($p) use ($filters) {
            return '/qr/objects?'.http_build_query(array_filter(array(
                'q' => $filters['q'], 'status' => $filters['status'], 'type' => $filters['type'], 'page' => $p,
            )));
        },
    ));
    ?>
</div>
