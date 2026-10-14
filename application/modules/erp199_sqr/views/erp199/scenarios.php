<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Сценарии терминала: что сервер предложит оператору после сканирования.
 *
 * Сценарий — это данные, а не код приложения: форма описывается полями JSON,
 * эффект выполняет сервер. Добавить новую полевую операцию можно без выпуска
 * новой версии приложения.
 *
 * Переменные: scenarios, kinds, can_manage, messages.
 */
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Сценарии терминала</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                После сканирования сервер возвращает список сценариев, приложение рисует их формы.
                Пустой список «виды целей» означает «любая цель».
            </p>
        </div>
        <?php if ($can_manage): ?>
            <a href="/qr/scenario" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                <i class="fas fa-plus" aria-hidden="true"></i> Новый сценарий
            </a>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php
    $rows_html = array();

    foreach ($scenarios as $scenario)
    {
        $kinds_html = array();

        foreach ($scenario['target_kinds'] as $kind)
        {
            $kinds_html[] = HTML::chars(Arr::get($kinds, $kind, $kind));
        }

        $requirements = array();

        if ($scenario['requires_photo'])
        {
            $requirements[] = 'фото обязательно';
        }

        if ($scenario['requires_comment'])
        {
            $requirements[] = 'комментарий обязателен';
        }

        $rows_html[] = array(
            '<a href="/qr/scenario/'.HTML::chars($scenario['code']).'" class="font-medium text-blue-600 hover:underline dark:text-blue-400">'
                .($scenario['icon'] ? '<i class="'.HTML::chars($scenario['icon']).' mr-1" aria-hidden="true"></i>' : '')
                .HTML::chars($scenario['label']).'</a>'
                .'<div class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($scenario['code']).'</div>',
            $kinds_html ? implode(', ', $kinds_html) : '<span class="text-xs text-gray-500 dark:text-gray-400">любая цель</span>',
            count($scenario['fields']).' пол.',
            HTML::chars((string) Arr::get($scenario['effect'], 'kind', 'record')),
            $requirements ? implode(', ', $requirements) : '<span class="text-xs text-gray-500 dark:text-gray-400">нет</span>',
            Component::render('badge', array('label' => $scenario['is_active'] ? 'Включён' : 'Выключен', 'state' => $scenario['is_active'] ? 'ok' : 'neutral')),
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Сценарии мобильного терминала',
        'columns'    => array(
            array('label' => 'Сценарий'),
            array('label' => 'Для каких целей', 'priority' => 2),
            array('label' => 'Полей', 'align' => 'right', 'priority' => 3),
            array('label' => 'Эффект'),
            array('label' => 'Требования', 'priority' => 3),
            array('label' => 'Состояние'),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'Сценариев нет',
        'empty_hint'  => 'Без сценариев терминал покажет карточку предмета, но не предложит действий.',
    ));
    ?>
</div>
