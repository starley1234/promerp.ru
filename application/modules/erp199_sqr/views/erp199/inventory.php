<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Сессия инвентаризации: прогресс, ожидаемые наклейки и уже сделанные отметки.
 *
 * Ожидаемое считается по области сессии (активные наклейки выбранных видов и
 * партии), отметки приходят из терминала. Расхождение «ожидалось − отмечено» —
 * это не ошибка сразу, а список того, что ещё не нашли.
 *
 * Переменные: inventory, progress, expected, items, q, can_manage, messages.
 */
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="/qr/inventories" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все инвентаризации</a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($inventory['label']); ?></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-mono"><?php echo HTML::chars($inventory['code']); ?></span>
                <?php echo Component::render('badge', array('label' => $inventory['status'] === 'open' ? 'Открыта' : 'Закрыта', 'state' => $inventory['status'] === 'open' ? 'info' : 'neutral')); ?>
                <?php if ($inventory['started_at']): ?><span class="ml-2">начата <?php echo HTML::chars((string) $inventory['started_at']); ?></span><?php endif; ?>
                <?php if ($inventory['closed_at']): ?><span class="ml-2">закрыта <?php echo HTML::chars((string) $inventory['closed_at']); ?></span><?php endif; ?>
            </p>
        </div>
        <?php if ($can_manage AND $inventory['status'] === 'open'): ?>
            <form method="post" action="/qr/inventory_close" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="id" value="<?php echo (int) $inventory['id']; ?>">
                <?php echo Component::render('form_field', array('id' => 'close_comment', 'label' => 'Комментарий к закрытию', 'name' => 'comment')); ?>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm text-white hover:bg-rose-700">Закрыть сессию</button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <?php echo Component::render('kpi', array('label' => 'Ожидалось', 'value' => number_format($progress['expected'], 0, '.', ' '), 'unit' => 'шт.', 'hint' => 'активных наклеек в области')); ?>
        <?php echo Component::render('kpi', array('label' => 'Отмечено', 'value' => number_format($progress['marked'], 0, '.', ' '), 'unit' => 'шт.', 'state' => 'info')); ?>
        <?php echo Component::render('kpi', array('label' => 'Найдено', 'value' => number_format($progress['found'], 0, '.', ' '), 'unit' => 'шт.', 'state' => 'ok')); ?>
        <?php echo Component::render('kpi', array('label' => 'Не отмечено', 'value' => number_format($progress['missing'], 0, '.', ' '), 'unit' => 'шт.', 'state' => $progress['missing'] ? 'warn' : 'neutral')); ?>
        <?php echo Component::render('kpi', array('label' => 'Прогресс', 'value' => (string) $progress['percent'], 'unit' => '%', 'state' => $progress['percent'] >= 100 ? 'ok' : 'info', 'hint' => 'повреждено: '.$progress['damaged'].', чужое: '.$progress['foreign'])); ?>
    </div>

    <form method="get" action="/qr/inventory/<?php echo (int) $inventory['id']; ?>" class="mb-4 flex flex-wrap items-start gap-3">
        <div class="min-w-[16rem] flex-1">
            <?php echo Component::render('form_field', array('id' => 'inv_q', 'label' => 'Поиск по ожидаемым', 'name' => 'q', 'value' => $q, 'hint' => 'код наклейки или подпись цели')); ?>
        </div>
        <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Найти</button>
    </form>

    <?php
    $rows_html = array();

    foreach ($expected as $row)
    {
        $scan = $row['scan'];

        $state = $scan
            ? ($scan['status'] === 'found' ? 'ok' : ($scan['status'] === 'damaged' ? 'warn' : 'bad'))
            : 'neutral';

        $rows_html[] = array(
            '<a href="/qr/token/'.HTML::chars($row['code']).'" class="font-mono text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['code']).'</a>',
            HTML::chars((string) $row['target_label']) ?: '<span class="text-xs text-gray-500 dark:text-gray-400">без подписи</span>',
            HTML::chars((string) $row['batch']),
            $scan ? Component::render('badge', array('label' => (string) $scan['status'], 'state' => $state)) : '<span class="text-xs text-gray-500 dark:text-gray-400">не отмечено</span>',
            $scan ? HTML::chars((string) $scan['scanned_at']) : '—',
            $scan AND $scan['comment'] ? HTML::chars((string) $scan['comment']) : '—',
        );
    }

    echo Component::render('table', array(
        'caption'    => 'Ожидаемые наклейки и отметки',
        'columns'    => array(
            array('label' => 'Наклейка'),
            array('label' => 'Цель'),
            array('label' => 'Партия', 'priority' => 3),
            array('label' => 'Отметка'),
            array('label' => 'Когда', 'priority' => 2),
            array('label' => 'Комментарий', 'priority' => 3),
        ),
        'rows'       => $rows_html,
        'sticky'     => TRUE,
        'empty_title' => 'В области проверки нет активных наклеек',
        'empty_hint'  => 'Проверьте область сессии: виды целей и партию.',
    ));
    ?>
</div>
