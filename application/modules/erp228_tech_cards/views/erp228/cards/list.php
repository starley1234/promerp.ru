<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp228/partials/flash')->set('messages', $messages); ?>

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Технологические карты</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Всего <?php echo (int) $total; ?> карт</p>
        </div>
        <a href="/tech_cards/editor"
           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <i class="fas fa-plus mr-2"></i> Новая техкарта
        </a>
    </div>

    <?php
    $control = 'rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white';
    $fields  = '<div class="w-64"><label for="tc_term" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Поиск</label>'
        .'<input type="text" id="tc_term" name="term" value="'.HTML::chars($filters['term']).'" placeholder="Наименование или тип производства" class="w-full '.$control.'"></div>';

    $fields .= '<div><label for="tc_status" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Статус</label>'
        .'<select id="tc_status" name="status" class="'.$control.'"><option value="">Все статусы</option>';

    foreach ($statuses as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.($filters['status'] === $code ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';

    $fields .= '<div><label for="tc_production" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Производство</label>'
        .'<select id="tc_production" name="production_type" class="'.$control.'"><option value="">Любое производство</option>';

    foreach ($production_types as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.($filters['production_type'] === $code ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';

    echo Component::render('filters', array(
        'action'       => '/tech_cards/list',
        'body'         => $fields,
        'submit_label' => 'Найти',
        'reset_url'    => '/tech_cards/list',
    ));

    // Состояние карты: серый — черновик, жёлтый — ждёт, синий — в обработке, зелёный — готова, красный — ошибка
    $status_states = array('draft' => 'neutral', 'pending' => 'warn', 'processing' => 'info', 'ready' => 'ok', 'error' => 'bad');
    $table_rows    = array();

    foreach ($items as $card)
    {
        $table_rows[] = array(
            '<span class="text-gray-400">'.(int) $card->id.'</span>',
            '<a href="/tech_cards/view/'.(int) $card->id.'" class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">'.HTML::chars($card->title).'</a>'
                .($card->error_msg ? '<div class="text-xs text-red-500 truncate max-w-md" title="'.HTML::chars($card->error_msg).'">'.HTML::chars($card->error_msg).'</div>' : ''),
            HTML::chars($card->production_label()),
            Component::render('badge', array(
                'label' => $card->status_label(),
                'state' => isset($status_states[$card->status]) ? $status_states[$card->status] : 'neutral',
            )),
            HTML::chars($card->created_at),
            '<a href="/tech_cards/view/'.(int) $card->id.'" class="text-gray-500 hover:text-blue-600 mx-1" title="Открыть" aria-label="Открыть"><i class="fas fa-eye" aria-hidden="true"></i></a>'
                .'<a href="/tech_cards/editor/'.(int) $card->id.'" class="text-gray-500 hover:text-blue-600 mx-1" title="Править" aria-label="Править"><i class="fas fa-pen" aria-hidden="true"></i></a>',
        );
    }

    Component::begin('card', array('flush' => TRUE));
    echo Component::render('table', array(
        'caption'     => 'Технологические карты',
        'columns'     => array(
            array('label' => '#', 'priority' => 4),
            array('label' => 'Карта', 'priority' => 1),
            array('label' => 'Производство', 'priority' => 2),
            array('label' => 'Статус', 'priority' => 2),
            array('label' => 'Создана', 'priority' => 3),
            array('label' => 'Действия', 'align' => 'right', 'nowrap' => TRUE),
        ),
        'rows'        => $table_rows,
        'empty_title' => 'Ничего не найдено',
        'empty_hint'  => 'Измените условия отбора или создайте техкарту кнопкой «Новая техкарта».',
    ));

    echo View::factory('erp228/partials/pagination')
        ->set('page', $page)
        ->set('pages', $pages)
        ->set('total', $total)
        ->set('filters', $filters);

    Component::end();
    ?>
</div>
