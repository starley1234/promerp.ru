<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля качества (erp405_quality).
 *
 * ID: 405 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_405_*
 * Слой: Operations — качество отвечает на вопрос «годится ли партия и что
 *       делать с браком»: контроль (входной, операционный, итоговый),
 *       несоответствия с кодом и причиной, карантин, решения (переделать /
 *       вернуть / утиль / как есть) и статистика потерь.
 *
 * Порядок маршрутов: частные (api, widget, действия и карточки) объявлены
 * раньше общих — иначе составной адрес разберёт общее правило и вызовет не то
 * действие.
 */

// JSON API
Route::set('erp405_quality_api', 'quality/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp405',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// Виджеты (для дашбордов и карточек соседних модулей)
Route::set('erp405_quality_widget', 'quality/widget/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp405',
        'controller' => 'Widget',
        'action'     => 'summary',
    ));

// --- Контроли --------------------------------------------------------------
Route::set('erp405_quality_inspection_act', 'quality/inspections/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'act'));

Route::set('erp405_quality_inspection_defect', 'quality/inspections/defect/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'defect'));

Route::set('erp405_quality_inspection_decide', 'quality/inspections/decide/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'decide'));

Route::set('erp405_quality_inspection_quarantine', 'quality/inspections/quarantine/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'quarantine'));

Route::set('erp405_quality_inspection_release', 'quality/inspections/release/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'release'));

Route::set('erp405_quality_inspection_view', 'quality/inspections/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'view'));

Route::set('erp405_quality_inspection_edit', 'quality/inspections/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'edit'));

Route::set('erp405_quality_inspections', 'quality/inspections(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Inspection', 'action' => 'index'));

// --- Справочник кодов дефектов ---------------------------------------------
Route::set('erp405_quality_defect_save', 'quality/defects/save')
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Defect', 'action' => 'save'));

Route::set('erp405_quality_defect_edit', 'quality/defects/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Defect', 'action' => 'edit'));

Route::set('erp405_quality_defects', 'quality/defects(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Defect', 'action' => 'index'));

// --- Доска и карантин -------------------------------------------------------
Route::set('erp405_quality_quarantine', 'quality/quarantine')
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Index', 'action' => 'quarantine'));

Route::set('erp405_quality_index', 'quality(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp405', 'controller' => 'Index', 'action' => 'index'));
