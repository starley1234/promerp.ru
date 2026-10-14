<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инициализация модуля демонстрационного стенда (erp811_demo).
 *
 * ID: 811 (диапазон 800–999, аналитика и агенты)
 * Префикс таблиц: erp_811_*
 * Слой: Demo — стенд показывает систему на вымышленном производстве в отдельной
 *       базе. Порядок маршрутов: частные (вход, выход, тур, API) объявлены
 *       раньше общего `/demo`, иначе составной адрес разберёт общее правило.
 */

// JSON API стенда: состояние и сценарий.
Route::set('erp811_demo_api', 'demo/api/<action>(/<id>)', array('id' => '[^/]+'))
    ->defaults(array(
        'directory'  => 'Erp811',
        'controller' => 'Api',
        'action'     => 'status',
    ));

// Тур по системе: что смотреть и в каком порядке.
Route::set('erp811_demo_tour', 'demo/tour')
    ->defaults(array('directory' => 'Erp811', 'controller' => 'Demo', 'action' => 'tour'));

// Вход гостя и выход — меняют состояние, поэтому только POST (см. Dashboard).
Route::set('erp811_demo_enter', 'demo/enter')
    ->defaults(array('directory' => 'Erp811', 'controller' => 'Demo', 'action' => 'enter'));

Route::set('erp811_demo_exit', 'demo/exit')
    ->defaults(array('directory' => 'Erp811', 'controller' => 'Demo', 'action' => 'exit'));

// Рабочее место — экран «что делать прямо сейчас» (прототип из разбора интерфейса).
Route::set('erp811_demo_workspace', 'demo/workspace')
    ->defaults(array('directory' => 'Erp811', 'controller' => 'Demo', 'action' => 'workspace'));

// Лендинг стенда.
Route::set('erp811_demo_index', 'demo(/<action>)')
    ->defaults(array('directory' => 'Erp811', 'controller' => 'Demo', 'action' => 'index'));
