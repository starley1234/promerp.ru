<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/** @var array $rows @var int $total @var array $filters */
$query = array_filter(array(
    'term'   => isset($filters['term']) ? $filters['term'] : '',
    'kind'   => isset($filters['kind']) ? $filters['kind'] : '',
    'status' => isset($filters['status']) ? $filters['status'] : '',
), 'strlen');

$base_url = '/counterparties/contracts?'.($query ? http_build_query($query).'&' : '');
?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-file-signature mr-2 text-blue-600"></i> Договоры
            <span class="text-base font-normal text-gray-500 dark:text-gray-400">(<?php echo (int) $total; ?>)</span>
        </h1>
    </div>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $control = 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-3 py-2 border';
    $fields  = '<div class="w-64"><label for="ct_term" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Поиск</label>'
        .'<input type="text" id="ct_term" name="term" value="'.HTML::chars(isset($filters['term']) ? $filters['term'] : '').'" placeholder="Номер или предмет договора" class="'.$control.'"></div>';

    $fields .= '<div class="w-48"><label for="ct_kind" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Вид</label>'
        .'<select id="ct_kind" name="kind" class="'.$control.'"><option value="">Все виды</option>';

    foreach ($kinds as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.((isset($filters['kind']) AND $filters['kind'] === $code) ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';

    $fields .= '<div class="w-48"><label for="ct_status" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Статус</label>'
        .'<select id="ct_status" name="status" class="'.$control.'"><option value="">Все статусы</option>';

    foreach ($statuses as $code => $label)
    {
        $fields .= '<option value="'.HTML::chars($code).'"'.((isset($filters['status']) AND $filters['status'] === $code) ? ' selected' : '').'>'.HTML::chars($label).'</option>';
    }

    $fields .= '</select></div>';
    ?>
    <?php echo Component::render('filters', array(
        'action'       => '/counterparties/contracts',
        'body'         => $fields,
        'submit_label' => 'Найти',
        'reset_url'    => '/counterparties/contracts',
    )); ?>

    <?php
    $table_rows = array();

    foreach ($rows as $row)
    {
        $table_rows[] = array(
            '<a class="font-mono text-blue-600 hover:underline" href="/counterparties/contracts/edit/'.(int) $row->id.'">№ '.HTML::chars($row->number).'</a>'
                .($row->title ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row->title).'</div>' : ''),
            '<a class="text-blue-600 hover:underline" href="/counterparties/view/'.(int) $row->counterparty_id.'">'.HTML::chars($row->counterparty->name).'</a>',
            HTML::chars($row->kind_label()),
            HTML::chars($row->valid_from ?: '—').' — '.HTML::chars($row->valid_to ?: '—'),
            HTML::chars($row->amount_label()),
            (string) View::factory('erp301/partials/status_badge')->set('status', $row->status)->set('labels', $statuses),
        );
    }
    ?>
    <?php Component::begin('card', array('flush' => TRUE)); ?>
        <?php echo Component::render('table', array(
            'caption'     => 'Список договоров',
            'columns'     => array(
                array('label' => 'Номер', 'priority' => 1),
                array('label' => 'Контрагент', 'priority' => 1),
                array('label' => 'Вид', 'priority' => 3),
                array('label' => 'Срок действия', 'priority' => 3, 'nowrap' => TRUE),
                array('label' => 'Сумма', 'align' => 'right', 'priority' => 2, 'nowrap' => TRUE),
                array('label' => 'Статус', 'priority' => 2),
            ),
            'rows'        => $table_rows,
            'empty_title' => 'Договоры не найдены',
            'empty_hint'  => 'Договор создаётся из карточки контрагента.',
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
