<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Клиенты продажи: контрагенты erp301, увиденные с торговой стороны.
 *
 * Покупатели не заводятся здесь (это справочник контрагентов), но условия
 * продажи — отсрочка, лимит, скидка — живут в профиле этого модуля. Поэтому в
 * списке рядом стоят «есть условия» и открытая задолженность.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-handshake mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Клиенты
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Всего покупателей <?php echo (int) $stats['counterparties']; ?> ·
                с условиями <?php echo (int) $stats['profiles']; ?> ·
                задолженность <?php echo number_format((float) $stats['exposure'], 0, '.', ' '); ?> ₽
                <?php if ((int) $stats['without_profile']): ?>
                    · без условий <?php echo (int) $stats['without_profile']; ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="/sales/prices"
           class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="fas fa-tags mr-2" aria-hidden="true"></i> Прайс продажи
        </a>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $body = Component::render('form_field', array(
        'id'    => 'filter_search',
        'label' => 'Поиск',
        'name'  => 'search',
        'value' => (string) $filters['search'],
        'type'  => 'search',
    ));

    $body .= '<label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">'
        .'<input type="checkbox" name="active" value="1"'.($filters['active'] ? ' checked' : '').'> только активные</label>';

    $body .= '<label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">'
        .'<input type="checkbox" name="with_profile" value="1"'.($filters['with_profile'] ? ' checked' : '').'> только с условиями</label>';

    echo Component::render('filters', array(
        'action'       => '/sales/customers',
        'body'         => $body,
        'reset_url'    => '/sales/customers',
        'submit_label' => 'Найти',
    ));
    ?>

    <?php
    $table_rows = array();

    foreach ($rows as $row)
    {
        $profile = $row['profile'];

        $exposure = (float) $row['exposure'];
        $limit    = $profile ? (float) $profile->credit_limit : 0.0;

        $exposure_html = '<span class="'.($limit > 0 AND $exposure > $limit ? 'text-rose-600 dark:text-rose-400 font-medium' : '').'">'
            .number_format($exposure, 2, '.', ' ').' ₽</span>';

        $table_rows[] = array(
            '<a href="/sales/customers/view/'.(int) $row['customer_id'].'" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($row['name']).'</a>',
            $profile
                ? HTML::chars($profile->payment_label()).'<br><span class="text-xs text-gray-500 dark:text-gray-400">'.(int) $profile->credit_days().' дн. отсрочки</span>'
                : '<span class="text-xs text-gray-500 dark:text-gray-400">не заданы</span>',
            $limit > 0 ? number_format($limit, 2, '.', ' ').' ₽' : '—',
            $profile ? (float) $profile->discount().' %' : '—',
            $exposure_html,
            Component::render('badge', array(
                'label' => $profile ? ($profile->is_active() ? 'активен' : 'выключен') : 'без условий',
                'state' => $profile ? ($profile->is_active() ? 'ok' : 'bad') : 'warn',
            )),
            '<a href="/sales/customers/view/'.(int) $row['customer_id'].'" class="text-blue-600 hover:text-blue-800 dark:text-blue-400" aria-label="Открыть клиента '.HTML::chars($row['name']).'"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>',
        );
    }

    echo Component::render('table', array(
        'caption'     => 'Клиенты продажи: условия и задолженность',
        'columns'     => array(
            array('label' => 'Клиент'),
            array('label' => 'Оплата'),
            array('label' => 'Лимит', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
            array('label' => 'Скидка', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 4),
            array('label' => 'Задолженность', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Условия'),
            array('label' => 'Открыть', 'align' => 'right'),
        ),
        'rows'        => $table_rows,
        'row_header'  => TRUE,
        'sticky'      => TRUE,
        'empty_title' => 'Клиентов не найдено',
        'empty_hint'  => 'Покупатель заводится в справочнике контрагентов с ролью «покупатель».',
    ));

    $query = array_filter(array(
        'search'       => $filters['search'],
        'active'       => $filters['active'],
        'with_profile' => $filters['with_profile'],
    ), function ($value) { return $value !== NULL AND $value !== '' AND $value !== FALSE AND $value !== '0'; });

    echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => (int) ceil($total / max(1, $limit)),
        'total' => $total,
        'url'   => function ($p) use ($query) {
            $query['page'] = $p;

            return '/sales/customers?'.http_build_query($query);
        },
    ));
    ?>
</div>
