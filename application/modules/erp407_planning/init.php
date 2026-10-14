<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля планирования потребностей (erp407_planning).
 *
 * ID: 407 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_407_*
 * Слой: Operations — планирование отвечает на вопрос «что, сколько и когда
 *       закупить или выпустить». Спрос берётся из программы и заказов erp402,
 *       покрытие — из склада erp401, состав — из техкарт erp228, номенклатура —
 *       из реестра erp227. Своих данных о материалах и заказах модуль не
 *       заводит: он их считает.
 *
 * Порядок маршрутов: частные (api, widget, действия и расчёт) объявлены раньше
 * общих — иначе составной адрес разберёт общее правило и вызовет не то действие.
 */

// JSON API
Route::set('erp407_planning_api', 'planning/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp407',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты (для карточек объектов и дашбордов)
Route::set('erp407_planning_widget', 'planning/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp407',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// --- Предложения ------------------------------------------------------------
Route::set('erp407_planning_proposals_act', 'planning/proposals/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Proposal', 'action' => 'act'));

Route::set('erp407_planning_proposals_save', 'planning/proposals/save')
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Proposal', 'action' => 'save'));

Route::set('erp407_planning_proposals_create', 'planning/proposals/create')
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Proposal', 'action' => 'create'));

Route::set('erp407_planning_proposals_edit', 'planning/proposals/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Proposal', 'action' => 'edit'));

Route::set('erp407_planning_proposals', 'planning/proposals(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Proposal', 'action' => 'index'));

// --- Программы --------------------------------------------------------------
Route::set('erp407_planning_program_calculate', 'planning/programs/calculate/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'calculate'));

Route::set('erp407_planning_program_fill', 'planning/programs/fill/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'fill'));

Route::set('erp407_planning_program_act', 'planning/programs/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'act'));

Route::set('erp407_planning_program_line', 'planning/programs/line/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'line'));

Route::set('erp407_planning_program_save', 'planning/programs/save')
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'save'));

Route::set('erp407_planning_program_view', 'planning/programs/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'view'));

Route::set('erp407_planning_program_edit', 'planning/programs/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'edit'));

Route::set('erp407_planning_programs', 'planning/programs(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Program', 'action' => 'index'));

// --- Доска планирования -----------------------------------------------------
Route::set('erp407_planning_deficits', 'planning/deficits(/<action>)')
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Index', 'action' => 'deficits'));

Route::set('erp407_planning_index', 'planning(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp407', 'controller' => 'Index', 'action' => 'index'));

/**
 * События модуля (шина erp208):
 *
 *   erp407.program.created    программа создана
 *   erp407.program.updated    программа изменена
 *   erp407.program.approved   план утверждён — можно считать потребность
 *   erp407.program.closed     период закрыт
 *   erp407.program.deleted    программа удалена
 *   erp407.mrp.calculated     расчёт потребности выполнен (снимок сохранён)
 *   erp407.proposal.created   предложение создано
 *   erp407.proposal.approved  предложение утверждено (идёт в покрытие)
 *   erp407.proposal.exported  предложение передано: заказ erp402 или заявка erp403
 *   erp407.proposal.done      предложение исполнено
 *   erp407.proposal.cancelled предложение отклонено
 *
 * Потребители: erp403_procurement (очередь закупки), erp217_notification
 * (оповещения плановика), erp119_audit (журнал заказов). Модуль никого не
 * вызывает напрямую — только публикует события.
 */
