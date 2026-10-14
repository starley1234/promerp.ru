<?php
return array(
    // Общие сообщения
    'storage.title' => 'Хранилище файлов',
    'storage.description' => 'Управление файлами и хранилищами',
    'storage.select' => 'Выберите хранилище',
    'storage.create' => 'Создать хранилище',
    'storage.edit' => 'Редактировать хранилище',
    'storage.delete.confirm' => 'Вы уверены, что хотите удалить это хранилище?',
    
    // Типы хранилищ
    'storage.local' => 'Локальное хранилище',
    'storage.s3' => 'Amazon S3',
    
    // Действия с файлами
    'storage.upload' => 'Загрузить',
    'storage.download' => 'Скачать',
    'storage.delete' => 'Удалить',
    'storage.generate.temp.link' => 'Сгенерировать временную ссылку',
    'storage.file' => 'Файл',
    'storage.files' => 'Файлы',
    
    // Поля
    'storage.path' => 'Путь',
    'storage.permissions' => 'Права доступа',
    'storage.file.name' => 'Имя файла',
    'storage.file.size' => 'Размер файла',
    'storage.file.type' => 'Тип файла',
    'storage.file.path' => 'Путь к файлу',
    
    // Права доступа
    'permission.read' => 'Чтение',
    'permission.write' => 'Запись',
    'permission.delete' => 'Удаление',
    'permission.all' => 'Полный доступ',
    
    // Временные ссылки
    'temp.link.expires' => 'Ссылка действительна до: {date}',
    'temp.link.generate' => 'Сгенерировать ссылку',
    'temp.link.copied' => 'Ссылка скопирована в буфер обмена',
    
    // Сообщения об успехе
    'file.upload.success' => 'Файл успешно загружен.',
    'file.delete.success' => 'Файл успешно удален.',
    'file.download.success' => 'Файл готов к скачиванию.',
    'storage.create.success' => 'Хранилище успешно создано.',
    'storage.update.success' => 'Хранилище успешно обновлено.',
    'storage.delete.success' => 'Хранилище успешно удалено.',
    'temp.link.generate.success' => 'Временная ссылка успешно создана.',
    
    // Ошибки валидации
    'file.mime.type.invalid' => 'Недопустимый MIME-тип файла.',
    'file.size.exceeded' => 'Размер файла превышает допустимый лимит.',
    'file.upload.error' => 'Ошибка загрузки файла.',
    'file.not.found' => 'Файл не найден.',
    'file.access.denied' => 'Доступ к файлу запрещен.',
    'file.path.invalid' => 'Недопустимый путь к файлу.',
    'file.extension.not.allowed' => 'Расширение файла не разрешено.',
    'file.empty' => 'Файл пустой.',
    'storage.connection.failed' => 'Не удалось подключиться к хранилищу.',
    'storage.configuration.invalid' => 'Некорректная конфигурация хранилища.',
    'permission.insufficient' => 'Недостаточно прав для выполнения операции.',
    'temp.link.expired' => 'Срок действия временной ссылки истек.',
    'temp.link.generate.failed' => 'Не удалось сгенерировать временную ссылку.',
    
    // Системные сообщения
    'error.storage.connection' => 'Ошибка подключения к хранилищу.',
    'error.storage.configuration' => 'Ошибка конфигурации хранилища.',
    'error.file.operation' => 'Ошибка при операции с файлом.',
    'error.permission.check' => 'Ошибка проверки прав доступа.',
    
    // Подсказки
    'storage.local.path.hint' => 'Относительный путь от корня приложения',
    'storage.s3.bucket.hint' => 'Имя S3 bucket',
    'storage.s3.region.hint' => 'Регион S3 (например, us-east-1)',
    'file.max.size.hint' => 'Максимальный размер файла: {size}',
    'file.allowed.types.hint' => 'Допустимые типы: {types}',
);