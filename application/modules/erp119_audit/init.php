<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля «Журнал действий» (erp119_audit).
 *
 * Маршруты — под префиксом /audit. Порядок регистрации важен: частные правила
 * объявлены раньше общих, иначе составные адреса разберёт общее правило и
 * вызовет не то действие (параметр <action> в середине пути превращается
 * в метод action_<значение> — грабли, уже пойманные на erp222).
 *
 * Модуль ничего не публикует: он только читает события шины (erp_208_events)
 * задачей `erp119:sync` и пишет записи в erp_119_audit_log.
 */

// Сводка
Route::set('erp119_audit_index', 'audit')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// Лента: экспорт объявлен раньше общего правила, иначе 'export' попал бы в <action>
Route::set('erp119_audit_journal_export', 'audit/journal/export')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Journal',
        'action'     => 'export',
    ));

Route::set('erp119_audit_journal_entity', 'audit/journal/entity')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Journal',
        'action'     => 'entity',
    ));

Route::set('erp119_audit_journal_view', 'audit/journal/view/<id>', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Journal',
        'action'     => 'view',
    ));

Route::set('erp119_audit_journal', 'audit/journal')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Journal',
        'action'     => 'index',
    ));

// Секции журнала (администрирование)
Route::set('erp119_audit_partitions_rotate', 'audit/partitions/rotate')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Partitions',
        'action'     => 'rotate',
    ));

Route::set('erp119_audit_partitions', 'audit/partitions')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Partitions',
        'action'     => 'index',
    ));

// JSON API: /audit/api/<action>(/<id>)
Route::set('erp119_audit_api', 'audit/api/<action>(/<id>)', array('id' => '\d+'))
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты: /audit/widget/<action>
Route::set('erp119_audit_widget', 'audit/widget/<action>')
    ->defaults(array(
        'directory'  => 'Erp119',
        'controller' => 'Widget',
        'action'     => 'recent',
    ));
