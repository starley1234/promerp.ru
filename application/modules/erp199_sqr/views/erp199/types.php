<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Типы объектов: что модуль умеет распознавать и заводить с телефона.
 *
 * Тип отвечает на два вопроса: каким адаптером читается цель (реестр, партия,
 * заказ, сотрудник, свободный объект) и можно ли завести такой объект прямо в
 * поле. Подсказка для распознавания фото — необязательная помощь.
 *
 * Переменные: types, can_manage, messages.
 */
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Типы объектов</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Тип выбирает оператор при заведении объекта или подсказывает распознавание фото.
                Адаптер определяет, откуда сервер читает данные предмета.
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/qr/type" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                <i class="fas fa-plus" aria-hidden="true"></i> Новый тип
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php
    $rows_html = array();

    foreach ($types as $type)
    {
        $rows_html[] = array(
            '<a href="/qr/type/'.HTML::chars($type['code']).'" class="font-medium text-blue-600 hover:underline dark:text-blue-400">'
                .($type['icon'] ? '<i class="'.HTML::chars($type['icon']).' mr-1" aria-hidden="true"></i>' : '')
                .HTML::chars($type['label']).'</a>'
                .'<div class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($type['code']).'</div>',
            HTML::chars((string) $type['adapter_label']),
            $type['allow_local'] ? 'да' : '<span class="text-xs text-gray-500 dark:text-gray-400">нет</span>',
            $type['expected_attributes'] ? HTML::chars(implode(', ', $type['expected_attributes'])) : '<span class="text-xs text-gray-500 dark:text-gray-400">—</span>',
            Component::render('badge', array('label' => $type['is_active'] ? 'Включён' : 'Выключен', 'state' => $type['is_active'] ? 'ok' : 'neutral')),
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Типы объектов QR-маркировки',
        'columns'    => array(
            array('label' => 'Тип'),
            array('label' => 'Адаптер'),
            array('label' => 'Заводить в поле'),
            array('label' => 'Ожидаемые характеристики', 'priority' => 2),
            array('label' => 'Состояние'),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'Типов нет',
        'empty_hint'  => 'Добавьте хотя бы один тип — иначе в поле нечего будет выбирать.',
    ));
    ?>
</div>
