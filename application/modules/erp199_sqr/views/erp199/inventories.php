<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Инвентаризации: открытые и закрытые сессии.
 *
 * Сессия — это область проверки (виды целей, партия) и отметки терминала.
 * Пока сессия открыта, каждый скан в поле попадает именно в неё.
 *
 * Переменные: rows, progress, kinds, can_manage, messages.
 */
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Инвентаризации</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Область проверки задаётся видами целей и партией наклеек. Терминал отмечает найденное,
            повреждённое и чужое; расхождения считаются на сервере.
        </p>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php if ($can_manage): ?>
        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Открыть инвентаризацию</h2>
            <form method="post" action="/qr/inventory_open" class="space-y-3">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-[18rem] flex-1">
                        <?php echo Component::render('form_field', array('id' => 'inv_label', 'label' => 'Название', 'name' => 'label', 'required' => TRUE, 'value' => 'Инвентаризация '.date('d.m.Y'), 'hint' => 'что именно проверяем')); ?>
                    </div>
                    <div class="min-w-[12rem]">
                        <?php echo Component::render('form_field', array('id' => 'inv_batch', 'label' => 'Партия наклеек', 'name' => 'batch', 'hint' => 'пусто — все партии')); ?>
                    </div>
                </div>
                <fieldset>
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Виды целей (пусто — все)</legend>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <?php foreach ($kinds as $code => $label): ?>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="kinds[]" value="<?php echo HTML::chars($code); ?>" class="rounded border-gray-300">
                                <?php echo HTML::chars($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php echo Component::render('form_field', array('id' => 'inv_note', 'label' => 'Примечание', 'name' => 'note')); ?>
                <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <i class="fas fa-play mr-1" aria-hidden="true"></i> Открыть
                </button>
            </form>
        </section>
    <?php endif; ?>

    <?php
    $rows_html = array();

    foreach ($rows as $row)
    {
        $p = $progress[(int) $row['id']];

        $scope = array();

        if ( ! empty($row['scope']['kinds']))
        {
            foreach ($row['scope']['kinds'] as $kind)
            {
                $scope[] = HTML::chars(Arr::get($kinds, $kind, $kind));
            }
        }

        if ( ! empty($row['scope']['batch']))
        {
            $scope[] = 'партия '.HTML::chars((string) $row['scope']['batch']);
        }

        $actions = '<a href="/qr/inventory/'.(int) $row['id'].'" class="text-blue-600 hover:underline dark:text-blue-400">Открыть</a>';

        if ($can_manage AND $row['status'] === 'open')
        {
            $actions .= '<form method="post" action="/qr/inventory_close" class="mt-1">'
                .'<input type="hidden" name="id" value="'.(int) $row['id'].'">'
                .'<button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Закрыть сессию</button></form>';
        }

        $rows_html[] = array(
            '<a href="/qr/inventory/'.(int) $row['id'].'" class="font-mono text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['code']).'</a>'
                .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['label']).'</div>',
            Component::render('badge', array('label' => $row['status'] === 'open' ? 'Открыта' : 'Закрыта', 'state' => $row['status'] === 'open' ? 'info' : 'neutral')),
            $scope ? implode(', ', $scope) : '<span class="text-xs text-gray-500 dark:text-gray-400">все цели</span>',
            $p['marked'].' / '.$p['expected'],
            (string) $p['percent'].' %',
            $actions,
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Сессии инвентаризации',
        'columns'    => array(
            array('label' => 'Сессия'),
            array('label' => 'Статус'),
            array('label' => 'Область', 'priority' => 2),
            array('label' => 'Отмечено / ожидалось', 'align' => 'right'),
            array('label' => 'Прогресс', 'align' => 'right', 'priority' => 2),
            array('label' => 'Действия', 'align' => 'right'),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'Инвентаризаций не было',
        'empty_hint'  => 'Откройте сессию — терминал начнёт отмечать наклейки в ней.',
    ));
    ?>
</div>
