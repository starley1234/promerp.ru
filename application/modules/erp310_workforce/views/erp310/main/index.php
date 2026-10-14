<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Workforce · erp310</p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">Сотрудники и оргструктура</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Единая связь человека, учётной записи, подразделения и должности.</p>
        </div>
        <a href="/workforce/employees/edit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fas fa-user-plus" aria-hidden="true"></i>Добавить сотрудника</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php echo Component::render('kpi', array('label' => 'Работают', 'value' => (int) $stats['employees'], 'unit' => 'сотрудников', 'state' => 'ok', 'url' => '/workforce/employees?status=active')); ?>
        <?php echo Component::render('kpi', array('label' => 'Подразделения', 'value' => (int) $stats['departments'], 'unit' => 'активных', 'state' => 'info', 'url' => '/workforce/departments')); ?>
        <?php echo Component::render('kpi', array('label' => 'Без учётной записи', 'value' => (int) $stats['unlinked'], 'unit' => 'нужно связать', 'state' => $stats['unlinked'] ? 'warn' : 'ok', 'url' => '/workforce/employees')); ?>
        <?php echo Component::render('kpi', array('label' => 'Вакантные ставки', 'value' => number_format((float)$stats['vacant_fte'],2,',',' '), 'unit' => 'из '.number_format((float)$stats['planned_fte'],2,',',' '), 'state' => $stats['vacant_fte'] > 0 ? 'warn' : 'ok', 'url' => '/workforce/staffing')); ?>
    </div>

    <?php
    $rows = array();
    foreach ($recent as $row) $rows[] = array('cells' => array(
        '<span class="font-semibold text-gray-900 dark:text-white">'.HTML::chars(trim($row['last_name'].' '.$row['first_name'])).'</span><div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['personnel_no']).'</div>',
        HTML::chars($row['department_name'] ?: 'Не назначено'), HTML::chars($row['position_name'] ?: '—'),
        $row['username'] ? HTML::chars($row['username']) : '<span class="text-amber-700 dark:text-amber-400">не связана</span>',
    ));
    Component::begin('card', array('title' => 'Активные сотрудники', 'subtitle' => 'Первые записи кадрового справочника', 'flush' => TRUE));
    echo Component::render('table', array('caption' => 'Активные сотрудники', 'columns' => array(
        array('label' => 'Сотрудник'), array('label' => 'Подразделение'), array('label' => 'Должность'), array('label' => 'Учётная запись')
    ), 'rows' => $rows, 'empty_title' => 'Сотрудников пока нет', 'empty_hint' => 'Добавьте первого сотрудника и назначьте его в подразделение.'));
    Component::end();
    ?>
</div>
