<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фасад для работы с модулем erp212_storage
 * Реализована поддержка пространств имен (модулей) и индексированных папок
 * 
    ## Примеры использования
    
    ### 1. Сохранение файла из формы
    ```php
    // Загрузка в модуль 'erp123_lims', файл будет приватным
    $file = Storage::upload($_FILES['report'], 'erp123_lims');
    $file_id = $file->id;
    ```

    ### 2. Сохранение контента (System-generated)
    ```php
    $file = Storage::put($binary_data, 'report.pdf', 'erp567_billing');
    ```

    ### 3. Получение ссылки для скачивания
    ```php
    $url = Storage::url($file_id);
    ```

    ### 4. Получение пути на сервере
    ```php
    $path = Storage::path($file_id);
    ```

    ### 5. Удаление файла
    ```php
    Storage::delete($file_id);
    ```
 */
class Storage {

    /**
     * Загрузка файла из $_FILES
     * 
     * @param array $file Элемент массива $_FILES
     * @param string $module Префикс модуля (namespace) для организации хранения
     * @param bool $is_public Публичный ли файл
     * @return Model_Erp212_File
     */
    public static function upload(array $file, $module = 'default', $is_public = false)
    {
        $service = Service_Erp212_Storage::instance();
        
        $metadata = [
            'is_public' => $is_public,
            'module'    => $module,
            'user_id'   => Auth::instance()->get_user() ? Auth::instance()->get_user()->id : null
        ];

        $file_id = $service->save($file, $metadata);
        return ORM::factory('Erp212_File', $file_id);
    }

    /**
     * Сохранение файла из бинарных данных (строки)
     * 
     * @param string $content Содержимое файла
     * @param string $filename Имя файла с расширением
     * @param string $module Префикс модуля (namespace)
     * @param bool $is_public Публичный ли файл
     * @return Model_Erp212_File
     */
    public static function put($content, $filename, $module = 'default', $is_public = false)
    {
        $service = Service_Erp212_Storage::instance();
        
        $metadata = [
            'is_public' => $is_public,
            'module'    => $module,
            'user_id'   => Auth::instance()->get_user() ? Auth::instance()->get_user()->id : null
        ];

        $file_id = $service->saveRaw($content, $filename, $metadata);
        return ORM::factory('Erp212_File', $file_id);
    }

    /**
     * Получение URL для скачивания (фронтенд)
     * 
     * @param int $file_id
     * @param int $expires Срок жизни ссылки в секундах
     * @return string
     */
    public static function url($file_id, $expires = 3600)
    {
        return Service_Erp212_Storage::instance()->generateUrl($file_id, $expires);
    }

    /**
     * Получение абсолютного пути к файлу на сервере (бэкенд)
     * 
     * @param int $file_id
     * @return string|bool
     */
    public static function path($file_id)
    {
        return Service_Erp212_Storage::instance()->getFilePath($file_id);
    }

    /**
     * Полное удаление файла и записи из БД
     * 
     * @param int $file_id
     * @return bool
     */
    public static function delete($file_id)
    {
        return Service_Erp212_Storage::instance()->delete($file_id);
    }

    /**
     * Получение расширенных метаданных файла
     * 
     * @param int $file_id
     * @return array|null
     */
    public static function metadata($file_id)
    {
        return Service_Erp212_Storage::instance()->get_extended_metadata($file_id);
    }
}