<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля аналитики (erp801_analytics).
 *
 * ID: 801 (диапазон 800–999, аналитика и агенты)
 * Префикс таблиц: erp_801_*
 * Слой: Analytics — модуль собирает витрины поверх данных владельцев: склад
 *       (erp401), цех (erp408), качество (erp405), закупки (erp403). Своих
 *       предметных данных не ведёт: только снимки отчётов и пороги витрин.
 *
 * Порядок маршрутов: частные (api, выгрузка, снимки, пороги) объявлены раньше
 * общих — иначе составной адрес разберёт общее правило и вызовет не то действие.
 */

// JSON API
Route::set('erp801_analytics_api', 'analytics/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp801',
        'controller' => 'Api',
        'action'     => 'summary',
    ));

// Выгрузка витрины в CSV
Route::set('erp801_analytics_export', 'analytics/export(/<code>)', array('code' => '[a-z_]+'))
    ->defaults(array(
        'directory'  => 'Erp801',
        'controller' => 'Dashboard',
        'action'     => 'export',
        'code'       => 'summary',
    ));

// --- Снимки витрин ----------------------------------------------------------
Route::set('erp801_analytics_snapshot_save', 'analytics/snapshots/save')
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'snapshot_save'));

Route::set('erp801_analytics_snapshot_delete', 'analytics/snapshots/delete/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'snapshot_delete'));

Route::set('erp801_analytics_snapshot_view', 'analytics/snapshots/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'snapshot_view'));

Route::set('erp801_analytics_snapshots', 'analytics/snapshots(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'snapshots'));

// --- Пороги витрин ----------------------------------------------------------
Route::set('erp801_analytics_settings', 'analytics/settings')
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'settings'));

// --- Витрины ----------------------------------------------------------------
// Имя раздела совпадает с именем действия контроллера: одно правило вместо
// пяти копий, и новый раздел добавляется без правки маршрутов.
Route::set('erp801_analytics_section', 'analytics/<action>', array('action' => 'stock|abc|production|quality|purchases'))
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard'));

// --- Обзор ------------------------------------------------------------------
Route::set('erp801_analytics_index', 'analytics(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp801', 'controller' => 'Dashboard', 'action' => 'index'));
