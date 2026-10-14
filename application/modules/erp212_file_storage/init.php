<?php defined('SYSPATH') or die('No direct script access.');

/**
 * ERP212 Storage Module Initialization
 * Абстракция хранилища: единый интерфейс для работы с файлами
 * ID модуля: 212
 */

// Регистрация роутов модуля
// Роуты соглашению: erp212_<controller>/<action>/<params>


Route::set('erp212_storage', 'erp212_storage(/<action>(/<id>))')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Storage',
        'action'     => 'index',
    ));    

// 2. REST API для работы с файлами (JSON)
Route::set('erp212_storage_api', 'erp212_api/<action>(/<id>)')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Api',
    ));

// 3. Контроллер скачивания файлов (Binary Stream)
// Альтернативный роут для скачивания
Route::set('erp212_storage_download_short', 'storage/get/<id>')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Download',
        'action'     => 'file',
    ));

Route::set('erp212_storage_download_file', 'erp212_download/file/<id>')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Download',
        'action'     => 'file',
    ));

Route::set('erp212_storage_download_shared', 'erp212_download/shared/<token>')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Download',
        'action'     => 'shared',
    ));

// 4. HMVC виджеты для вставки в другие модули
Route::set('erp212_storage_widget', 'erp212_widget/<action>')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Widget',
        'action'     => 'uploader',
    ));

// 5. Корневой роут модуля (редирект на интерфейс)
Route::set('erp212_storage_root', 'erp212')
    ->defaults(array(
        'directory'  => 'Erp212',
        'controller' => 'Index',
        'action'     => 'index',
    ));

// Регистрация событий (если требуется)
// Пример: очистка просроченных временных ссылок при завершении запроса
Event::listen('system.shutdown', function() {
    // Запуск фоновой задачи очистки (реализуется в Minion задаче)
    // Service_Erp212_Storage::cleanup_expired_links();
});



// Инициализация конфигурации модуля
// Загружаем конфигурационные настройки
$config = Kohana::$config->load('erp212_storage');

// Проверка обязательных настроек
/*
if (!$config->get('allowed_mime_types') || !$config->get('max_file_size')) {
    Kohana::$log->add(Log::ERROR, 'ERP212 Storage: Не настроены обязательные параметры allowed_mime_types или max_file_size');
}
*/


// Регистрация кастомного исключения для доступа к файлам
// HTTP_Exception_403::factory('storage_access_denied');