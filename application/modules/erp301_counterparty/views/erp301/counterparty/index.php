<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/** @var array $rows @var int $total @var array $filters */
$query = array_filter(array(
    'term'   => isset($filters['term']) ? $filters['term'] : '',
    'role'   => isset($filters['role']) ? $filters['role'] : '',
    'status' => isset($filters['status']) ? $filters['status'] : '',
    'kind'   => isset($filters['kind']) ? $filters['kind'] : '',
), 'strlen');

$base_url = '/counterparties/list?'.($query ? http_build_query($query).'&' : '');
?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-address-book mr-2 text-blue-600"></i> Контрагенты
            <span class="text-base font-normal text-gray-500 dark:text-gray-400">(<?php echo (int) $total; ?>)</span>
        </h1>
        <a href="/counterparties/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Добавить
        </a>
    </div>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $control = 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-3 py-2 border';
    $fields  = '<div class="w-64"><label for="cp_term" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Поиск</label>'
        .'<input type="text" id="cp_term" name="term" value="'.HTML::chars(isset($filters['term']) ? $filters['term'] : '').'" placeholder="Наименование, код или ИНН" class="'.$control.'"></div>';

    $fields .= '<div class="w-48"><label for="cp_role" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Роль</label>'
        .'<select id="cp_role" name="role" class="'.$control.'"><option value="">Все роли</option>';

    foreach ($roles as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.((isset($filters['role']) AND $filters['role'] === $code) ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';

    $fields .= '<div class="w-48"><label for="cp_status" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Статус</label>'
        .'<select id="cp_status" name="status" class="'.$control.'"><option value="">Все статусы</option>';

    foreach ($statuses as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.((isset($filters['status']) AND $filters['status'] === $code) ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';
    ?>
    <?php echo Component::render('filters', array(
        'action'       => '/counterparties/list',
        'body'         => $fields,
        'submit_label' => 'Найти',
        'reset_url'    => '/counterparties/list',
    )); ?>

    <?php
    $table_rows = array();

    foreach ($rows as $row)
    {
        $contacts = '';

        if ($row->phone)
        {
            $contacts .= '<div><i class="fas fa-phone text-xs text-gray-400 mr-1" aria-hidden="true"></i>'.HTML::chars($row->phone).'</div>';
        }

        if ($row->email)
        {
            $contacts .= '<div class="text-xs"><i class="fas fa-envelope text-xs text-gray-400 mr-1" aria-hidden="true"></i>'.HTML::chars($row->email).'</div>';
        }

        $table_rows[] = array(
            '<span class="font-mono">'.HTML::chars($row->code).'</span>',
            '<a class="text-blue-600 hover:underline" href="/counterparties/view/'.(int) $row->id.'">'.HTML::chars($row->name).'</a>'
                .($row->city ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row->city).'</div>' : ''),
            HTML::chars($row->roles_label()),
            '<span class="font-mono">'.HTML::chars($row->inn).'</span>',
            $contacts,
            (string) View::factory('erp301/partials/status_badge')->set('status', $row->status)->set('labels', $statuses),
            '<a href="/counterparties/view/'.(int) $row->id.'" title="Карточка" aria-label="Карточка" class="text-gray-500 hover:text-blue-600 mr-3"><i class="fas fa-eye" aria-hidden="true"></i></a>'
                .'<a href="/counterparties/edit/'.(int) $row->id.'" title="Изменить" aria-label="Изменить" class="text-gray-500 hover:text-blue-600"><i class="fas fa-pen" aria-hidden="true"></i></a>',
        );
    }
    ?>
    <?php Component::begin('card', array('flush' => TRUE)); ?>
        <?php echo Component::render('table', array(
            'caption'     => 'Список контрагентов',
            'columns'     => array(
                array('label' => 'Код', 'priority' => 3),
                array('label' => 'Наименование', 'priority' => 1),
                array('label' => 'Роли', 'priority' => 2),
                array('label' => 'ИНН', 'priority' => 3),
                array('label' => 'Контакты', 'priority' => 2),
                array('label' => 'Статус', 'priority' => 2),
                array('label' => 'Действия', 'align' => 'right', 'nowrap' => TRUE),
            ),
            'rows'        => $table_rows,
            'empty_title' => 'Ничего не найдено',
            'empty_hint'  => 'Измените условия отбора или добавьте контрагента кнопкой «Добавить».',
        )); ?>

        <?php if (count($rows)): ?>
            <?php echo View::factory('erp301/partials/pagination')
                ->set('total', $total)
                ->set('page', $page)
                ->set('page_size', $page_size)
                ->set('base_url', $base_url); ?>
        <?php endif; ?>
    <?php Component::end(); ?>
</div>
