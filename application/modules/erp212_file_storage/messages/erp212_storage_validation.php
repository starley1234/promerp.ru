php
<?php defined('SYSPATH') OR die('No direct script access.');

return array(
    // Общие ошибки валидации
    'required' => 'Поле :field обязательно для заполнения',
    'not_empty' => 'Поле :field не может быть пустым',
    
    // Ошибки файлов (хранилище)
    'file' => array(
        'upload_valid' => 'Ошибка загрузки файла',
        'upload_error' => 'Ошибка загрузки: :error',
        'upload_invalid' => 'Загруженный файл недействителен',
        'upload_not_found' => 'Файл не найден',
        'upload_extension' => 'Недопустимое расширение файла. Разрешены: :extensions',
        'upload_mime' => 'Недопустимый MIME-тип файла. Разрешены: :mimes',
        'upload_size' => 'Файл слишком большой. Максимальный размер: :maxsize',
        'upload_dimensions' => 'Недопустимые размеры изображения',
        'upload_not_uploaded' => 'Файл не был загружен через HTTP POST',
        'upload_no_file' => 'Файл не был загружен',
        'upload_temp_missing' => 'Отсутствует временный файл',
        'upload_cant_write' => 'Не удалось записать файл на диск',
        'upload_extension_missing' => 'Расширение PHP остановило загрузку файла',
    ),
    
    // Ошибки путей
    'path' => array(
        'not_empty' => 'Укажите путь для сохранения',
        'min_length' => 'Путь слишком короткий (минимум :min символов)',
        'max_length' => 'Путь слишком длинный (максимум :max символов)',
        'invalid_chars' => 'Путь содержит недопустимые символы',
        'directory_not_exists' => 'Директория не существует',
        'directory_not_writable' => 'Нет прав на запись в директорию',
    ),
    
    // Ошибки MIME-типов
    'mime' => array(
        'not_empty' => 'Укажите MIME-тип',
        'invalid' => 'Недопустимый MIME-тип',
        'not_allowed' => 'MIME-тип :mime не разрешен',
    ),
    
    // Ошибки размера
    'size' => array(
        'not_empty' => 'Укажите размер файла',
        'numeric' => 'Размер должен быть числом',
        'min' => 'Размер файла не может быть меньше :min',
        'max' => 'Размер файла не может быть больше :max',
    ),
    
    // Ошибки прав доступа
    'permission' => array(
        'read' => 'Нет прав на чтение файла',
        'write' => 'Нет прав на запись файла',
        'delete' => 'Нет прав на удаление файла',
        'access_denied' => 'Доступ запрещен',
    ),
    
    // Ошибки облачного хранилища (S3)
    's3' => array(
        'connection_failed' => 'Не удалось подключиться к S3',
        'upload_failed' => 'Ошибка загрузки в S3',
        'download_failed' => 'Ошибка скачивания из S3',
        'delete_failed' => 'Ошибка удаления из S3',
        'bucket_not_exists' => 'Бакет S3 не существует',
        'access_denied' => 'Доступ к S3 запрещен',
        'invalid_credentials' => 'Неверные учетные данные S3',
    ),
);