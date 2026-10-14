# Базовый модуль для информационной системы

Базовый модуль для информационной системы (erp100_core), предоставляющий основные контроллеры, утилиты и общую структуру для других модулей.

## Содержание

- [Controller_Erp100_Base](#controller_erp100_base)
- [Controller_Erp100_Auth](#controller_erp100_auth)
- [Controller_Base](#controller_base)

---

## Controller_Erp100_Base

Базовый контроллер для всех контроллеров приложения. Он инициализирует основной шаблон, загружает общие данные и управляет мета-тегами.

### Основные возможности:

- **Шаблонизация**: Использует `template_tailwindcss` (или `template_bootstrap_5_3`) в качестве основного шаблона.
- **Мета-теги**: Управление `title`, `description`, `keywords` страницы.
- **CSS/JS**: Добавление стилей и скриптов (внешних и инлайн).
- **Авторизация**: Проверяет авторизацию пользователя, загружает объект `Model_User` и его роли.
- **Глобальные переменные**: Привязывает `current_user`, `current_user_roles`, `site_name`, `site_logo` и `table_view` к глобальным переменным View.
- **Меню**: Метод `set_menu()` для генерации и установки меню в шаблон с поддержкой разрешений (ACL).
- **Проверка прав**: Методы `has_role()`, `has_any_role()` для проверки наличия ролей у текущего пользователя.
- **Проверка владения**: Метод `is_owner()` для определения, является ли текущий пользователь владельцем объекта.
- **Кэширование классов**: Метод `action_update_declared_classes_cache()` для обновления кэша объявленных классов (только для разработки).
- **Единая проверка прав (`can`)**: Метод `can($ability, $object = NULL)` для централизованной проверки разрешений, учитывающий роль администратора, владение объектом и системный ACL.

### Использование:

Все контроллеры должны наследовать `Controller_Erp100_Base` (или `Controller_Base` для ERP-специфичных контроллеров).

```php
class Controller_MyModule_Example extends Controller_Erp100_Base {

    public function before()
    {
        parent::before();
        $this->set_title('Моя страница');
        $this->add_style('assets/css/my_style.css');
        $this->add_script('assets/js/my_script.js');
        
        $this->set_menu([
            [
                'title' => 'Главная',
                'href'  => '/',
                'icon'  => 'fas fa-home',
                'acl'   => 'all'
            ],
            [
                'title' => 'Админка',
                'href'  => '/admin',
                'icon'  => 'fas fa-cog',
                'acl'   => 'admin.access' // Проверка через ACL
            ]
        ]);
    }

    public function action_index()
    {
        if (!$this->can('my_module.view')) {
            throw new HTTP_Exception_403('Доступ запрещен');
        }
        $this->template->content = View::factory('my_module/index');
    }
}
```

---

## Идемпотентность JSON API (Service_Erp100_Idempotency)

Изменяющие запросы JSON API защищены от повтора. Обрыв связи, ретрай агента или
двойной клик не создают второй документ: клиент присылает ключ, система помнит
ответ и отдаёт его повторно.

```
POST /procurement/api/request_create
Idempotency-Key: 3f1c9a7e-…

# повтор с тем же телом → тот же ответ и заголовок Idempotent-Replay: true
# тот же ключ с другим телом → 409 conflict
# параллельный запрос тем же ключом → 409 in_progress
```

| Что | Где |
| :--- | :--- |
| Таблица ключей и ответов | `erp_100_api_idempotency` (`pgMigration_Erp_110_003_Api_Idempotency`) |
| Логика | `classes/Service/Erp100/Idempotency.php` — `begin()`, `finish()`, `release()`, `purge()` |
| Включение | `Controller_Base::execute()` — сам для `Controller_*_Api` на POST/PUT/DELETE и только при наличии ключа |
| Уборка | `bin/console idempotency:purge [--hours=N] [--dry-run] [--stats]` |
| Тесты | `tests/Unit/Erp100IdempotencyTest.php` |

Почему в ядре, а не в модулях: точка входа для машин есть у каждого модуля, и
защита должна работать одинаково у всех — модулю не нужно ни одной строки кода.
Без ключа поведение прежнее, поэтому веб-формы и существующие клиенты не задеты.
Ключ живёт 24 часа: это защита от повтора, а не журнал (журнал ведёт erp119).

---

## Управление доступом

Экран `/access/users` управляет ролями, блокировкой, отзывом всех сеансов и
областями данных. Реализация — `Service_Erp100_Access`; схема —
`pgMigration_Erp_110_004_AccessGovernance`; подробные границы и порядок
проверок записаны в `docs/ACCESS_SCOPE_ARCHITECTURE.md`.

`users.access_policy=restricted` работает по fail-closed правилу: без активных
строк в `erp_100_user_scopes` доступ к объектам запрещён. Поддержаны
подразделения (включая дочерние), склады и рабочие центры. Первым consumer стал
список, карточка и reference endpoint сотрудников erp310; erp401/erp408 должны
применять фильтр на уровне своих SQL-service в следующих срезах.

Блокировка увеличивает `session_version` и удаляет remember-токены. Версия
записывается Auth-драйвером в текущую сессию, поэтому старые сеансы перестают
работать на следующем запросе. Последнего активного администратора нельзя
заблокировать или лишить роли; проверка сериализована advisory-lock.

MCP предоставляет чтение карточки и approval-gated изменения ролей, статуса,
сеансов, policy и scopes. Обхода доменного сервиса для агента нет.

---

## Режим интерфейса и личные настройки

- `Service_Erp100_UserSettings` — личные настройки пользователя в
  `users_settings.settings_json` (jsonb, «ключ → строка»): `get($user_id, $key, $default)`,
  `set($user_id, $key, $value)` (запись одной настройки не стирает остальные).
  ORM-модель `Model_Erp100_User_Setting` написана под MySQL и на PostgreSQL не используется.
- `Controller_Erp100_Ui`, адрес `/ui/mode/<simple|pro>` — выбор режима интерфейса
  (простой/профессиональный) и возврат на страницу. Работает для гостя и в демо.
  Логика режима — `Ui_Mode` (`application/classes/Ui/Mode.php`), правила для
  экранов — `application/views/components/README.md`.

## Controller_Erp100_Auth

Контроллер, отвечающий за аутентификацию и регистрацию пользователей в системе.

### Основные возможности:

- **Вход (`action_login`)**:
    - Обработка POST-запросов для входа.
    - Проверка учетных данных и статуса подтверждения email.
    - Перенаправление на предыдущую страницу после успешного входа.
    - Интеграция с модулем `yandexauth` для входа через Яндекс.
- **Выход (`action_logout`)**:
    - Завершение сессии пользователя и перенаправление на главную страницу.
- **Регистрация (`action_register`)**:
    - Обработка POST-запросов для регистрации нового пользователя.
    - Валидация данных (username, email, password, terms).
    - Создание пользователя, присвоение роли 'login'.
    - Генерация токена подтверждения email и отправка письма.
    - Перенаправление на страницу успешной регистрации.
- **Успешная регистрация (`action_success_registration`)**:
    - Отображение страницы с сообщением об успешной регистрации и инструкциями по подтверждению email.
- **Подтверждение email (`action_confirm_email`)**:
    - Проверка токена подтверждения.
    - Активация учетной записи пользователя.
    - Автоматический вход пользователя после подтверждения.
- **Восстановление пароля (`action_forgot_password`)**:
    - Обработка запроса на сброс пароля по email.
    - Генерация токена сброса и отправка письма со ссылкой.
- **Сброс пароля (`action_reset_password`)**:
    - Проверка токена сброса и его срока действия.
    - Установка нового пароля для пользователя.
- **Профиль пользователя (`action_profile`)**:
    - Отображение страницы профиля для авторизованного пользователя.
- **Управление пользователями (`action_index`)**:
    - Доступно только для пользователей с ролью 'admin'.
    - Отображает список всех пользователей.

### Использование:

Контроллер предоставляет стандартные маршруты для аутентификации.

- `/auth/login` - Страница входа
- `/auth/logout` - Выход
- `/auth/register` - Страница регистрации
- `/auth/success_registration` - Страница успешной регистрации
- `/auth/confirm_email/<token>` - Подтверждение email
- `/auth/forgot_password` - Страница восстановления пароля
- `/auth/reset_password/<token>` - Сброс пароля
- `/auth/profile` - Профиль пользователя
- `/auth/index` - Список пользователей (для админов)

---

## Controller_Base

Расширенный базовый контроллер для ERP-системы, наследующий `Controller_Erp100_Base` и добавляющий специфичные для ERP функциональности и рекомендации.

### Принципы: "Просто, Единообразно, Хорошо"

1.  **Доступ и Разрешения (ACL)**:
    -   Использует `$this->can('ability_name', $model)` для проверки разрешений.
    -   Глобальные переменные `current_user` и `current_user_roles` доступны в View.
    -   `$this->_current_user` содержит объект `Model_User`.

2.  **Связи через События (Event Dispatcher)**:
    -   Модули общаются через шину событий: `Event::fire('erp[ID].object.action', $data)`.
    -   Это позволяет модулям Audit, Notify и Storage работать автоматически.

3.  **Хранилище (Storage)**:
    -   Загрузка файлов: `$file_id = Storage::upload($_FILES['doc'], 'erp[ID]_module_name')`.
    -   Получение ссылки: `Storage::url($file_id)`.

4.  **Уведомления (Notify)**:
    -   Отправка уведомлений: `Notify::send($user, 'template_code', ['var' => 'val'])` (почта/TG/push).

5.  **ИИ (LLM)**:
    -   Интеграция с LLM: `Llm_Factory::get()->chat([...])`.

6.  **Интерфейс и Меню**:
    -   `$this->set_title('Заголовок')`: Установка заголовка страницы.
    -   `$this->set_menu([...])`: Формирование навигации. **Важно**: Доступ к пункту меню определяется через ключ `'acl'`.

### Пример стандартного действия (Action):

Демонстрирует рекомендуемый подход к написанию действий контроллера с использованием ACL, ORM, событий и JSON-ответов.

```php
public function action_update() {
   $id = $this->request->param('id');
   $model = ORM::factory('Erp100_Task', $id);      // Используем стандартный ORM
   
   if ( ! $this->can('task.edit', $model)) {       // Проверяем ПРАВО, а не роль
       throw new HTTP_Exception_403('Access Denied');
   }

   $model->values($this->request->post())->save();
   Event::fire('erp100.task.updated', $model);     // Оповещаем систему
   $this->_json_response(['status' => 'ok']);      // Отвечаем JSON'ом (для AJAX)
}
```

### Дополнительные методы:

-   `_json_response($data, $code = 200)`: Удобный метод для отправки JSON-ответов, отключает автоматический рендеринг шаблона.

### Использование:

Все ERP-специфичные контроллеры должны наследовать `Controller_Base` для использования общих принципов и функциональностей.

```php
class Controller_Erp100_Tasks extends Controller_Base {

    public function before()
    {
        parent::before();
        $this->set_title('Управление задачами');
        $this->set_menu([
            [
                'title' => 'Мои задачи',
                'href'  => '/erp100_tasks',
                'icon'  => 'fas fa-tasks',
                'acl'   => 'task.view'
            ],
            [
                'title' => 'Создать задачу',
                'href'  => '/erp100_tasks/create',
                'icon'  => 'fas fa-plus',
                'acl'   => 'task.create'
            ]
        ]);
    }

    public function action_index()
    {
        if (!$this->can('task.view')) {
            throw new HTTP_Exception_403('Доступ запрещен');
        }
        $tasks = ORM::factory('Erp100_Task')->find_all();
        $this->template->content = View::factory('erp100/tasks/list')->set('tasks', $tasks);
    }
}