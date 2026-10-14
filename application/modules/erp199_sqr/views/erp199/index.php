<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Дашборд QR-маркировки: состояние наклеек, открытая инвентаризация,
 * черновики из поля и последние касания физического мира.
 *
 * Переменные: stats, recent, inventory, inventory_progress, drafts.
 */
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-qrcode mr-2 text-blue-600" aria-hidden="true"></i> QR-маркировка
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Наклейка связывает запись в системе с предметом в цехе. Сканирование с телефона — это факт,
            который видно в журнале: кто, когда, где и с каким результатом.
        </p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <?php echo Component::render('kpi', array('label' => 'Всего наклеек', 'value' => number_format($stats['total'], 0, '.', ' '), 'unit' => 'шт.', 'hint' => 'партий: '.$stats['batches'], 'url' => '/qr/tokens')); ?>
        <?php echo Component::render('kpi', array('label' => 'Активные', 'value' => number_format($stats['active'], 0, '.', ' '), 'unit' => 'шт.', 'state' => 'ok', 'hint' => 'привязаны к цели', 'url' => '/qr/tokens?status=active')); ?>
        <?php echo Component::render('kpi', array('label' => 'Не привязаны', 'value' => number_format($stats['new'], 0, '.', ' '), 'unit' => 'шт.', 'state' => 'info', 'hint' => 'ждут наклейки на предмет', 'url' => '/qr/tokens?status=new')); ?>
        <?php echo Component::render('kpi', array('label' => 'Проблемные', 'value' => number_format($stats['problem'], 0, '.', ' '), 'unit' => 'шт.', 'state' => $stats['problem'] ? 'warn' : 'neutral', 'hint' => 'заблокированы, повреждены, утеряны', 'url' => '/qr/tokens?status=blocked')); ?>
        <?php echo Component::render('kpi', array('label' => 'Сканирований', 'value' => number_format($stats['scans'], 0, '.', ' '), 'unit' => 'раз', 'hint' => 'всего по журналу')); ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Открытая инвентаризация</h2>
                <a href="/qr/inventories" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Все сессии →</a>
            </header>
            <div class="p-5">
                <?php if ( ! $inventory): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Открытых сессий нет. Наклейки можно проверить, открыв инвентаризацию — терминал будет
                        отмечать найденное в ней.
                    </p>
                    <a href="/qr/inventories" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i> Открыть инвентаризацию
                    </a>
                <?php else: ?>
                    <p class="text-sm text-gray-900 dark:text-white">
                        <a href="/qr/inventory/<?php echo (int) $inventory['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($inventory['code']); ?></a>
                        — <?php echo HTML::chars($inventory['label']); ?>
                    </p>
                    <div class="mt-3">
                        <?php echo Component::render('kpi', array(
                            'label'  => 'Отмечено',
                            'value'  => $inventory_progress['marked'].' / '.$inventory_progress['expected'],
                            'unit'   => 'шт.',
                            'state'  => $inventory_progress['percent'] >= 100 ? 'ok' : 'info',
                            'hint'   => 'расхождения: '.$inventory_progress['missing'],
                            'target' => $inventory_progress['percent'].' %',
                        )); ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Черновики из поля</h2>
                <a href="/qr/objects?status=draft" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Все объекты →</a>
            </header>
            <div class="p-5">
                <?php if ( ! $drafts): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Черновиков нет — все заведённые в поле объекты разобраны.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($drafts as $draft): ?>
                            <li class="py-2">
                                <a href="/qr/object/<?php echo (int) $draft['id']; ?>" class="text-sm text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($draft['name']); ?></a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo $draft['type_label'] ? HTML::chars($draft['type_label']).' · ' : ''; ?>
                                    <?php echo $draft['serial_number'] ? 'S/N '.HTML::chars($draft['serial_number']) : 'без номера'; ?>
                                    <?php echo $draft['source'] === 'ai' ? ' · распознано по фото' : ''; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Последние действия</h2>
            <a href="/qr/activity" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Весь журнал →</a>
        </header>
        <?php
        $rows = array();

        foreach ($recent as $row)
        {
            $rows[] = array(
                $row['created_at'] ? '<span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $row['created_at']).'</span>' : '—',
                $row['token_code']
                    ? '<a href="/qr/token/'.HTML::chars($row['token_code']).'" class="font-mono text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['token_code']).'</a>'
                    : '<span class="text-xs text-gray-500 dark:text-gray-400">без наклейки</span>',
                HTML::chars(Arr::get(Service_Erp199_Tokens::instance()->actions(), (string) $row['action_type'], (string) $row['action_type'])),
                $row['result'] ? Component::render('badge', array('label' => (string) $row['result'], 'state' => $row['result'] === 'ok' ? 'ok' : 'warn')) : '—',
                HTML::chars(mb_substr((string) $row['comment'], 0, 60)),
            );
        }

        echo Component::render('table', array(
            'caption'    => 'Последние действия по наклейкам',
            'columns'    => array(
                array('label' => 'Когда', 'priority' => 2),
                array('label' => 'Наклейка'),
                array('label' => 'Действие'),
                array('label' => 'Результат'),
                array('label' => 'Комментарий', 'priority' => 3),
            ),
            'rows'       => $rows,
            'sticky'     => TRUE,
            'empty_title' => 'Журнал пуст',
            'empty_hint'  => 'Действия появятся здесь после первого сканирования.',
        ));
        ?>
    </section>
</div>
