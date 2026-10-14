<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp228/partials/flash')->set('messages', $messages); ?>

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Технологические карты</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Исходник изделия → техпроцесс → операции, нормы времени и стоимость
            </p>
        </div>
        <a href="/tech_cards/editor"
           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <i class="fas fa-plus mr-2"></i> Новая техкарта
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <?php echo Component::render('kpi', array('label' => 'Всего карт', 'value' => (int) $stats['total'], 'unit' => 'шт.', 'state' => 'info', 'url' => '/tech_cards/list')); ?>
        <?php echo Component::render('kpi', array('label' => 'Готовых техпроцессов', 'value' => (int) $stats['ready'], 'unit' => 'шт.', 'state' => 'ok', 'url' => '/tech_cards/list?status=ready')); ?>
        <?php echo Component::render('kpi', array('label' => 'Версий документов', 'value' => (int) $stats['documents'], 'unit' => 'шт.')); ?>
        <?php echo Component::render('kpi', array('label' => 'Операций', 'value' => (int) $stats['operations'], 'unit' => 'шт.')); ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">По статусам</h2>
            <ul class="space-y-2 text-sm">
                <?php foreach ($statuses as $code => $label): ?>
                    <li class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-gray-300"><?php echo HTML::chars($label); ?></span>
                        <span class="font-medium text-gray-900 dark:text-white"><?php echo (int) $stats['by_status'][$code]; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">По типу производства</h2>
            <ul class="space-y-2 text-sm">
                <?php foreach ($production_types as $code => $label): ?>
                    <li class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-gray-300"><?php echo HTML::chars($label); ?></span>
                        <span class="font-medium text-gray-900 dark:text-white"><?php echo (int) $stats['by_type'][$code]; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Стоимость операций</h2>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                <?php echo number_format($stats['cost_total'], 2, ',', ' '); ?> <span class="text-base font-normal text-gray-500">₽</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Сумма по всем операциям всех версий техпроцессов.
            </p>
        </div>
    </div>

    <?php
    $status_states = array('draft' => 'neutral', 'pending' => 'warn', 'processing' => 'info', 'ready' => 'ok', 'error' => 'bad');
    $table_rows    = array();

    foreach ($recent as $card)
    {
        $table_rows[] = array(
            '<a href="/tech_cards/view/'.(int) $card->id.'" class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">'.HTML::chars($card->title).'</a>'
                .'<div class="text-xs text-gray-400">#'.(int) $card->id.' · '.HTML::chars($card->uuid).'</div>',
            HTML::chars($card->production_label()),
            Component::render('badge', array(
                'label' => $card->status_label(),
                'state' => isset($status_states[$card->status]) ? $status_states[$card->status] : 'neutral',
            )),
            HTML::chars($card->created_at),
        );
    }

    Component::begin('card', array(
        'title'        => 'Последние карты',
        'actions_html' => '<a href="/tech_cards/list" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Все карты</a>',
        'flush'        => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'Последние технологические карты',
        'columns'     => array(
            array('label' => 'Карта', 'priority' => 1),
            array('label' => 'Производство', 'priority' => 2),
            array('label' => 'Статус', 'priority' => 2),
            array('label' => 'Создана', 'priority' => 3),
        ),
        'rows'        => $table_rows,
        'empty_title' => 'Карт пока нет',
        'empty_hint'  => 'Создайте первую техкарту.',
    ));
    Component::end();
    ?>
</div>
