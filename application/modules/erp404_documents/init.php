<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля документов и печати (erp404_documents).
 *
 * ID: 404 (диапазон 400–599, Operations)
 * Префикс таблиц: erp_404_*
 * Слой: Operations — модуль отвечает на вопрос «что мы отдали на бумагу и чем
 *       это подтверждается»: шаблоны печатных форм, выпуск документа со снимком
 *       и версией шаблона, журнал печати, архив.
 *
 * Порядок маршрутов: частные (api, действия и карточки) объявлены раньше общих —
 * иначе составной адрес разберёт общее правило и вызовет не то действие.
 */

// JSON API
Route::set('erp404_documents_api', 'documents/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp404',
        'controller' => 'Api',
        'action'     => 'stats',
    ));

// --- Печатная форма на экран (страница браузера для печати) ------------------
Route::set('erp404_documents_print_page', 'documents/print/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'print_page'));

// --- Шаблоны печатных форм --------------------------------------------------
Route::set('erp404_documents_template_check', 'documents/templates/check(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Template', 'action' => 'check'));

Route::set('erp404_documents_template_save', 'documents/templates/save')
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Template', 'action' => 'save'));

Route::set('erp404_documents_template_edit', 'documents/templates/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Template', 'action' => 'edit'));

Route::set('erp404_documents_templates', 'documents/templates(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Template', 'action' => 'index'));

// --- Документы --------------------------------------------------------------
Route::set('erp404_documents_act', 'documents/act/<act>/<id>', array('act' => '[a-z_]+', 'id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'act'));

Route::set('erp404_documents_view', 'documents/view/<id>', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'view'));

Route::set('erp404_documents_edit', 'documents/edit(/<id>)', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'edit'));

Route::set('erp404_documents_save', 'documents/save')
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'save'));

Route::set('erp404_documents_index', 'documents(/<action>(/<id>))', array('id' => '[^/]+'))
    ->defaults(array('directory' => 'Erp404', 'controller' => 'Document', 'action' => 'index'));
