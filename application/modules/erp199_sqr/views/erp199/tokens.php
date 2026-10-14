<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Реестр наклеек: выпуск партии, отбор, печать этикеток.
 *
 * Переменные: rows, total, page, per_page, filters, stats, states, kinds,
 * can_issue, can_bind, url, messages.
 */
$query_string = function (array $extra = array()) use ($filters, $url) {
    $params = array_filter(array_merge(array(
        'q'      => $filters['q'],
        'status' => $filters['status'],
        'kind'   => $filters['kind'],
        'batch'  => $filters['batch'],
    ), $extra), function ($value) { return $value !== NULL AND $value !== ''; });

    return $url.http_build_query($params);
};
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Наклейки</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Наклейка — это паспорт предмета: код из QR, состояние и цель, к которой она привязана.
                Партию печатают здесь, а наклеивают уже в цехе.
            </p>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            всего <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($total, 0, '.', ' '); ?></span>,
            активных <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($stats['active'], 0, '.', ' '); ?></span>,
            свободных <span class="font-semibold text-gray-900 dark:text-white"><?php echo number_format($stats['new'], 0, '.', ' '); ?></span>
        </div>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php if ($can_issue): ?>
        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Выпустить партию наклеек</h2>
            <form method="post" action="/qr/issue" class="flex flex-wrap items-start gap-3">
                <?php echo Component::render('form_field', array('id' => 'issue_count', 'label' => 'Сколько', 'name' => 'count', 'type' => 'number', 'value' => 10, 'hint' => 'от 1 до 200')); ?>
                <?php echo Component::render('form_field', array('id' => 'issue_batch', 'label' => 'Партия (необязательно)', 'name' => 'batch', 'value' => 'QR-'.date('ymd'), 'hint' => 'по партии удобно печатать и отбирать')); ?>
                <?php echo Component::render('form_field', array('id' => 'issue_note', 'label' => 'Пометка на наклейке', 'name' => 'note', 'hint' => 'например: «участок сборки»')); ?>
                <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i> Выпустить
                </button>
            </form>
        </section>
    <?php endif; ?>

    <form method="get" action="/qr/tokens" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-[14rem] flex-1">
                <?php echo Component::render('form_field', array('id' => 'filter_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['q'], 'hint' => 'код наклейки, подпись цели или пометка')); ?>
            </div>
            <div class="min-w-[12rem]">
                <label for="filter_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Состояние</label>
                <select id="filter_status" name="status" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">любое</option>
                    <?php foreach ($states as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $filters['status'] === $code ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label for="filter_kind" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Вид цели</label>
                <select id="filter_kind" name="kind" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">любой</option>
                    <?php foreach ($kinds as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $filters['kind'] === $code ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[10rem]">
                <?php echo Component::render('form_field', array('id' => 'filter_batch', 'label' => 'Партия', 'name' => 'batch', 'value' => $filters['batch'])); ?>
            </div>
            <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Применить</button>
            <a href="/qr/tokens" class="mt-6 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Сбросить</a>
        </div>
    </form>

    <form method="post" action="/qr/label" target="_blank">
        <?php
        $rows_html = array();

        foreach ($rows as $row)
        {
            $target = $row['target'];

            if ($row['target_kind'])
            {
                $target_html = $target
                    ? ($target['url']
                        ? '<a href="'.HTML::chars($target['url']).'" class="text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($target['label']).'</a>'
                        : HTML::chars($target['label']))
                    : '<span class="text-xs text-rose-600 dark:text-rose-400">цель не найдена</span>';
            }
            else
            {
                $target_html = '<span class="text-xs text-gray-500 dark:text-gray-400">не привязана</span>';
            }

            $check = $can_issue
                ? '<input type="checkbox" name="codes[]" value="'.HTML::chars($row['code']).'" aria-label="Выбрать наклейку '.HTML::chars($row['code']).'">'
                : '';

            $rows_html[] = array(
                $check,
                '<a href="/qr/token/'.HTML::chars($row['code']).'" class="font-mono text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($row['code']).'</a>'
                    .($row['label_note'] ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['label_note']).'</div>' : ''),
                Component::render('badge', array('label' => $row['status_label'], 'state' => Service_Erp199_Tokens::instance()->state_of($row['status']))),
                $target_html,
                '<span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $row['batch']).'</span>',
                '<span class="text-xs text-gray-500 dark:text-gray-400">'.(int) $row['scans_count'].' · '.HTML::chars((string) $row['last_scan_at']).'</span>',
            );
        }

        echo Component::render('table', array(
            'caption'    => 'Реестр наклеек',
            'columns'    => array(
                array('label' => '', 'class' => 'w-8'),
                array('label' => 'Код'),
                array('label' => 'Состояние'),
                array('label' => 'Цель'),
                array('label' => 'Партия', 'priority' => 3),
                array('label' => 'Сканирований', 'priority' => 3),
            ),
            'rows'       => $rows_html,
            'sticky'     => TRUE,
            'empty_title' => 'Наклеек нет',
            'empty_hint'  => 'Выпустите партию — коды можно распечатать и наклеить на предметы.',
        ));
        ?>

        <?php if ($rows): ?>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <i class="fas fa-print mr-1" aria-hidden="true"></i> Печать выбранных
                </button>
                <span class="text-xs text-gray-500 dark:text-gray-400">Откроется страница этикеток 58×40 мм — печатать без полей.</span>
            </div>
        <?php endif; ?>
    </form>

    <?php
    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / $per_page),
        'total' => $total,
        'url'   => function ($p) use ($query_string) { return $query_string(array('page' => $p)); },
    ));
    ?>
</div>
