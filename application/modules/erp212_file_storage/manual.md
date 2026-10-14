# Документация модуля ERP212 Storage

Модуль предоставляет унифицированный интерфейс для работы с файловыми хранилищами (Local, S3).

## Интеграция в проект

Для максимально интуитивного доступа из других модулей рекомендуется создать вспомогательный класс-прокси в директории `application/classes/Storage.php`.

### Файл: /application/classes/Storage.php

```php
<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фасад для работы с модулем erp212_storage
 */
class Storage {

    /**
     * Загрузка файла
     * 
     * @param array $file Элемент массива $_FILES
     * @param string|null $path Кастомный путь (опционально)
     * @param bool $is_public Публичный ли файл
     * @return Model_Erp212_File
     */
    public static function upload(array $file, $path = null, $is_public = false)
    {
        $service = Service_Erp212_Storage::instance();
        
        $metadata = [
            'is_public' => $is_public,
            'custom_path' => $path,
            'user_id' => Auth::instance()->get_user() ? Auth::instance()->get_user()->id : null
        ];

        $file_id = $service->save($file, $metadata);
        return ORM::factory('Erp212_File', $file_id);
    }

    /**
     * Получение URL для скачивания
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
     * Удаление файла
     * 
     * @param int $file_id
     * @return bool
     */
    public static function delete($file_id)
    {
        return Service_Erp212_Storage::instance()->delete($file_id);
    }
}
```

## Примеры использования

### 1. Сохранение файла из формы
При отправке формы с полем `<input type="file" name="report">`:

```php
// Загрузка в папку lims_reports, файл будет приватным по умолчанию
$file = Storage::upload($_FILES['report'], 'lims_reports');

// ID сохраненного файла для записи в вашу таблицу БД
$file_id = $file->id;
```

### 2. Получение ссылки для скачивания
Метод автоматически генерирует либо временную подписанную ссылку (для S3), либо внутренний роут с токеном (для Local):

```php
$url = Storage::url($file_id);
// Результат: https://test.promerp.ru/erp212_download/shared/abc123token...
```

### 3. Удаление файла
```php
Storage::delete($file_id);
```

## API (REST)
Модуль также предоставляет API эндпоинты для внешних систем:
- `POST /erp212_api/upload` — Загрузка
- `GET /erp212_api/get_metadata/<id>` — Информация о файле
- `DELETE /erp212_api/delete/<id>` — Удаление

## Безопасность
- Доступ к приватным файлам осуществляется через контроллер `Controller_Erp212_Download`, который проверяет права текущего пользователя или валидность временного токена.
- Список разрешенных MIME-типов и максимальный размер настраиваются в конфиге `erp212_storage.php`.