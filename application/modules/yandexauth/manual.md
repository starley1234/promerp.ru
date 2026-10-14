# Интеграция Yandex OAuth в модульную структуру ERP

Для обеспечения чистоты кода и соблюдения принципов модульности, сохранение данных в таблицу `users_oauth` организовано следующим образом:

## 1. Архитектура моделей

Модель данных разделена на базовую логику и реализацию в приложении:

*   **Базовая модель:** `application/modules/erp100_core/classes/Model/Erp100/User/Oauth.php`
    *   Содержит описание таблицы `users_oauth`.
    *   Определяет правила валидации (`rules`) и метки полей (`labels`).
    *   Реализует проверку уникальности связки `provider` + `provider_id`.
*   **Прокси-модель:** `application/classes/Model/User/Oauth.php` (рекомендуется создать)
    *   Должна наследоваться от `Model_Erp100_User_Oauth`.
    *   Позволяет обращаться к модели через короткое имя `ORM::factory('User_Oauth')`.

## 2. Логика сохранения в контроллере

В контроллере `Controller_Yandexauth` процесс сохранения разделен на два этапа:

### Этап 1: Работа с сущностью User
1.  Формируется системный `username` (например, `yandex_12345678`).
2.  Проверяется наличие пользователя в таблице `users` по `username` или `email`.
3.  Если пользователь не найден, создается новая запись с генерацией случайного пароля и назначением роли `login`.

### Этап 2: Работа с сущностью User_Oauth
Вместо дублирования данных в основной таблице пользователей, расширенная информация от Яндекса сохраняется в `users_oauth`:

```php
// Поиск существующей привязки к соцсети
$user_oauth = ORM::factory('User_Oauth')
    ->where('provider', '=', 'yandex')
    ->and_where('provider_id', '=', $user_info['id'])
    ->find();

// Если привязки нет, создаем новую и связываем с ID пользователя
if (!$user_oauth->loaded()) {
    $user_oauth->user_id = $user->id;
    $user_oauth->provider = 'yandex';
    $user_oauth->provider_id = $user_info['id'];
}

// Обновляем токены и профильные данные при каждом входе
$user_oauth->access_token = $oauth_token;
$user_oauth->first_name   = Arr::get($user_info, 'first_name');
$user_oauth->last_name    = Arr::get($user_info, 'last_name');
$user_oauth->email        = Arr::get($user_info, 'default_email');
$user_oauth->sex          = Arr::get($user_info, 'sex');
$user_oauth->birthday     = Arr::get($user_info, 'birthday');
$user_oauth->avatar_id    = Arr::get($user_info, 'avatar_id');
$user_oauth->save();
```

## 3. Преимущества такого подхода

1.  **Связанность данных:** Таблица `users` остается легкой и содержит только данные для аутентификации. Все метаданные внешних сервисов вынесены в `users_oauth`.
2.  **Мультиаккаунт:** Один пользователь (`user_id`) потенциально может иметь несколько записей в `users_oauth` (Yandex, Google, VK), привязанных к одному профилю.
3.  **Автоматизация:** Использование `ORM` позволяет автоматически применять правила валидации, описанные в `Model_Erp100_User_Oauth`, предотвращая сохранение некорректных данных.
4.  **Безопасность:** Использование `Auth::instance()->force_login($user)` позволяет авторизовать пользователя без знания его пароля после успешной проверки OAuth-токена на стороне Яндекса.

## 4. Рекомендации по БД

Убедитесь, что в таблице `users_oauth` созданы индексы:
*   `user_id` (Foreign Key к `users.id`).
*   Уникальный составной индекс `provider` + `provider_id` для исключения дублей.