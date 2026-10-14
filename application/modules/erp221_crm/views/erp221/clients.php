<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Клиенты CRM: контрагенты с ролью «Покупатель» и профилем продаж.
 *
 * Переменные: rows, total, page, per_page, filters, stats, roles, url,
 * can_manage, messages.
 */
$query = function (array $extra = array()) use ($filters, $url) {
    $params = array_filter(array_merge(array(
        'q'      => $filters['term'],
        'status' => $filters['status'],
        'kind'   => $filters['kind'],
        'role'   => $filters['role'],
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
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Клиенты</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Это те же контрагенты, что и в разделе «Контрагенты»: CRM дополняет их профилем продаж
                (сегмент, отрасль, источник, ответственный), а не заводит вторую карточку.
            </p>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            всего <span class="font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['total']; ?></span>,
            со сделками <span class="font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['with_deals']; ?></span>,
            новых за месяц <span class="font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['new_this_month']; ?></span>
        </div>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <?php ob_start(); ?>
        <?php echo Component::render('form_field', array('id' => 'client_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['term'], 'hint' => 'название, код или ИНН')); ?>
        <div class="space-y-1">
            <label for="client_role" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Роль</label>
            <select id="client_role" name="role" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="customer"<?php echo $filters['role'] === 'customer' ? ' selected' : ''; ?>>Покупатель</option>
                <option value="supplier"<?php echo $filters['role'] === 'supplier' ? ' selected' : ''; ?>>Поставщик</option>
                <option value=""<?php echo $filters['role'] === '' ? ' selected' : ''; ?>>Все контрагенты</option>
            </select>
        </div>
        <div class="space-y-1">
            <label for="client_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Состояние</label>
            <select id="client_status" name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Любое</option>
                <?php foreach (array('active' => 'Активен', 'blocked' => 'Заблокирован', 'archived' => 'В архиве') as $code => $label): ?>
                    <option value="<?php echo $code; ?>"<?php echo $filters['status'] === $code ? ' selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php echo Component::render('filters', array(
        'action'       => '/crm/clients',
        'body'         => ob_get_clean(),
        'submit_label' => 'Показать',
        'reset_url'    => '/crm/clients',
    )); ?>

    <?php if ($can_manage): ?>
        <details class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Новый клиент</summary>
            <form method="post" action="/crm/client_save" class="mt-4 grid gap-3 md:grid-cols-3">
                <?php echo Component::render('form_field', array('id' => 'client_name', 'label' => 'Название', 'name' => 'name', 'required' => TRUE)); ?>
                <?php echo Component::render('form_field', array('id' => 'client_inn', 'label' => 'ИНН', 'name' => 'inn')); ?>
                <?php echo Component::render('form_field', array('id' => 'client_phone', 'label' => 'Телефон', 'name' => 'phone', 'autocomplete' => 'tel')); ?>
                <?php echo Component::render('form_field', array('id' => 'client_email', 'label' => 'Почта', 'name' => 'email', 'type' => 'email', 'autocomplete' => 'email')); ?>
                <?php echo Component::render('form_field', array('id' => 'client_segment', 'label' => 'Сегмент', 'name' => 'segment', 'hint' => 'например: крупный, средний, малый')); ?>
                <?php echo Component::render('form_field', array('id' => 'client_source', 'label' => 'Источник', 'name' => 'source')); ?>
                <div class="md:col-span-3">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Добавить клиента</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <?php
    $table_rows = array();

    foreach ($rows as $client)
    {
        $table_rows[] = array(
            '<a href="/crm/client/'.(int) $client['id'].'" class="font-medium text-blue-600 hover:underline dark:text-blue-400">'.HTML::chars($client['name']).'</a>'
                .($client['code'] ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($client['code']).'</div>' : ''),
            '<span class="font-mono text-xs">'.HTML::chars($client['inn'] ? $client['inn'] : '—').'</span>',
            HTML::chars(trim(implode(' · ', array_filter(array($client['phone'], $client['email'])))) ?: '—'),
            Component::render('badge', array('label' => $client['status'], 'state' => $client['status'] === 'active' ? 'ok' : ($client['status'] === 'blocked' ? 'bad' : 'neutral'))),
            '<span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(implode(', ', $client['role_labels']) ?: '—').'</span>',
        );
    }
    ?>
    <?php Component::begin('card', array('flush' => TRUE)); ?>
        <?php echo Component::render('table', array(
            'caption'     => 'Список клиентов',
            'columns'     => array(
                array('label' => 'Клиент', 'priority' => 1),
                array('label' => 'ИНН', 'priority' => 3),
                array('label' => 'Контакты', 'priority' => 2),
                array('label' => 'Состояние', 'priority' => 2),
                array('label' => 'Роли', 'priority' => 4),
            ),
            'rows'        => $table_rows,
            'empty_title' => 'Клиентов по заданным условиям нет',
            'empty_hint'  => 'Снимите часть условий отбора или добавьте клиента выше.',
        )); ?>
    <?php Component::end(); ?>

    <?php echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
        'url'   => function ($page) use ($query) { return $query(array('page' => $page)); },
    )); ?>
</div>
