<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Список сделок: отбор, сводка и создание новой сделки.
 *
 * Переменные: rows, total, page, per_page, filters, stages, users, sources,
 * url, can_manage, messages.
 */
$query = function (array $extra = array()) use ($filters, $url) {
    $params = array_filter(array_merge(array(
        'q'           => $filters['q'],
        'stage'       => $filters['stage'],
        'kind'        => $filters['kind'],
        'assigned_to' => $filters['assigned_to'],
        'priority'    => $filters['priority'],
        'source'      => $filters['source'],
        'overdue'     => $filters['overdue'],
    ), $extra), function ($value) {
        return $value !== NULL AND $value !== '';
    });

    return $url.http_build_query($params);
};

$pages = (int) ceil($total / max(1, $per_page));
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Сделки</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Всего по отбору: <span class="font-semibold text-gray-900 dark:text-white"><?php echo (int) $total; ?></span>.
                Срок в прошлом и стадия «в работе» — сделка помечается просроченной.
            </p>
        </div>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <?php ob_start(); ?>
        <?php echo Component::render('form_field', array('id' => 'filter_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['q'], 'hint' => 'номер, название, клиент')); ?>
        <div class="space-y-1">
            <label for="filter_stage" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Стадия</label>
            <select id="filter_stage" name="stage" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Любая</option>
                <?php foreach ($stages as $stage): ?>
                    <option value="<?php echo HTML::chars($stage['code']); ?>"<?php echo $filters['stage'] === $stage['code'] ? ' selected' : ''; ?>><?php echo HTML::chars($stage['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label for="filter_assignee" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Ответственный</label>
            <select id="filter_assignee" name="assigned_to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Любой</option>
                <?php foreach ($users as $id => $name): ?>
                    <option value="<?php echo (int) $id; ?>"<?php echo (int) $filters['assigned_to'] === (int) $id ? ' selected' : ''; ?>><?php echo HTML::chars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="space-y-1">
            <label for="filter_priority" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Приоритет</label>
            <select id="filter_priority" name="priority" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Любой</option>
                <?php foreach (array('low' => 'Низкий', 'medium' => 'Обычный', 'high' => 'Высокий', 'critical' => 'Критичный') as $code => $label): ?>
                    <option value="<?php echo $code; ?>"<?php echo $filters['priority'] === $code ? ' selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" name="overdue" value="1"<?php echo $filters['overdue'] ? ' checked' : ''; ?> class="rounded border-gray-300">
            Только просроченные
        </label>
    <?php echo Component::render('filters', array(
        'action'       => '/crm/deals',
        'body'         => ob_get_clean(),
        'submit_label' => 'Показать',
        'reset_url'    => '/crm/deals',
    )); ?>

    <?php if ($can_manage): ?>
        <details class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Новая сделка</summary>
            <form method="post" action="/crm/deal_save" class="mt-4 grid gap-3 md:grid-cols-3">
                <?php echo Component::render('form_field', array('id' => 'new_title', 'label' => 'Название', 'name' => 'title', 'required' => TRUE)); ?>
                <div class="space-y-1">
                    <label for="new_client" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Клиент <span class="text-rose-600">*</span></label>
                    <select id="new_client" name="counterparty_id" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></select>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Начните вводить название — подсказка придёт из справочника контрагентов.</p>
                </div>
                <?php echo Component::render('form_field', array('id' => 'new_amount', 'label' => 'Сумма, ₽', 'name' => 'amount', 'type' => 'number', 'value' => 0)); ?>
                <?php echo Component::render('form_field', array('id' => 'new_deadline', 'label' => 'Срок решения', 'name' => 'deadline', 'type' => 'date')); ?>
                <div class="space-y-1">
                    <label for="new_stage" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Стадия</label>
                    <select id="new_stage" name="stage_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo (int) $stage['id']; ?>"><?php echo HTML::chars($stage['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="new_source" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Источник</label>
                    <input id="new_source" name="source" list="source_list" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <datalist id="source_list">
                        <?php foreach ($sources as $source): ?><option value="<?php echo HTML::chars($source); ?>"></option><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="md:col-span-3">
                    <?php echo Component::render('form_field', array('id' => 'new_description', 'label' => 'Описание', 'name' => 'description', 'type' => 'textarea')); ?>
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Создать сделку</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <?php
    $table_rows = array();

    foreach ($rows as $deal)
    {
        $table_rows[] = array(
            '<a href="/crm/deal/'.(int) $deal['id'].'" class="font-mono text-xs text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($deal['number']).'</a>'
                .'<div class="font-medium text-gray-900 dark:text-white">'.HTML::chars($deal['title']).'</div>',
            '<a href="/crm/client/'.(int) $deal['client']['id'].'" class="text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($deal['client']['name']).'</a>',
            Component::render('badge', array('label' => $deal['stage']['label'], 'state' => $deal['stage']['kind'] === 'won' ? 'ok' : ($deal['stage']['kind'] === 'lost' ? 'bad' : 'info'))),
            '<span class="font-medium text-gray-900 dark:text-white">'.number_format($deal['amount'], 0, '.', ' ').' '.HTML::chars($deal['currency']).'</span>',
            (int) $deal['probability'].' %',
            '<span class="'.($deal['is_overdue'] ? 'text-rose-600 dark:text-rose-400' : '').'">'.($deal['deadline'] ? HTML::chars($deal['deadline']) : '—').'</span>',
        );
    }
    ?>
    <?php Component::begin('card', array('flush' => TRUE)); ?>
        <?php echo Component::render('table', array(
            'caption'     => 'Список сделок',
            'columns'     => array(
                array('label' => 'Сделка', 'priority' => 1),
                array('label' => 'Клиент', 'priority' => 1),
                array('label' => 'Стадия', 'priority' => 2),
                array('label' => 'Сумма', 'align' => 'right', 'priority' => 2),
                array('label' => 'Вероятность', 'align' => 'right', 'priority' => 4),
                array('label' => 'Срок', 'priority' => 3),
            ),
            'rows'        => $table_rows,
            'empty_title' => 'По заданным условиям сделок нет',
            'empty_hint'  => 'Снимите часть условий отбора или создайте сделку выше.',
        )); ?>
    <?php Component::end(); ?>

    <?php echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
        'url'   => function ($page) use ($query) { return $query(array('page' => $page)); },
    )); ?>
</div>

<script>
// Подсказка клиентов: список контрагентов с ролью «Покупатель» (см. /crm/api/suggest).
(function () {
    var select = document.getElementById('new_client');
    if (!select) return;

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    input.placeholder = 'Название клиента';
    select.parentNode.insertBefore(input, select);
    select.classList.add('hidden');

    var timer = null;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch('/crm/api/suggest?term=' + encodeURIComponent(input.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success || !data.data.clients.length) return;
                    select.innerHTML = '';
                    data.data.clients.forEach(function (client) {
                        var option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = client.label;
                        select.appendChild(option);
                    });
                    select.classList.remove('hidden');
                })
                .catch(function () { /* подсказка — необязательна: выберем вручную в карточке */ });
        }, 250);
    });
})();
</script>
