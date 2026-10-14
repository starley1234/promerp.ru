# Модуль YandexAuth для Kohana 3.3

Этот модуль предоставляет функциональность для аутентификации пользователей через Яндекс.OAuth в фреймворке Kohana 3.3.

## Особенности

*   **Авторизация через Яндекс.OAuth**: Позволяет пользователям входить на ваш сайт, используя свои учетные записи Яндекс.
*   **Получение информации о пользователе**: После успешной авторизации модуль получает базовую информацию о пользователе из Яндекс.
*   **Интеграция с ORM**: Создает или обновляет записи пользователей в вашей базе данных, связывая их с данными из Яндекс.
*   **Поддержка `User` и `User_Oauth` моделей**: Разделяет основную информацию о пользователе (`User`) и специфичные данные OAuth (`User_Oauth`).
*   **Перенаправление после входа**: Сохраняет URL, с которого пользователь инициировал вход, и перенаправляет его туда после успешной авторизации.

## Установка

1.  **Клонируйте репозиторий** или скопируйте файлы модуля в директорию `application/modules/yandexauth`.

2.  **Включите модуль** в вашем файле `application/bootstrap.php`:

    ```php
    Kohana::modules(array(
        // ... другие модули
        'yandexauth' => MODPATH.'yandexauth', // Включить модуль YandexAuth
    ));
    ```

3.  **Настройте базу данных**:
    Убедитесь, что у вас есть таблицы `users` и `user_oauths` (или аналогичные, если вы используете другие имена моделей).
    Пример структуры для `user_oauths`:

    ```sql
    CREATE TABLE `user_oauths` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) unsigned NOT NULL,
      `provider` varchar(50) NOT NULL,
      `provider_id` varchar(255) NOT NULL,
      `access_token` text,
      `first_name` varchar(255) DEFAULT NULL,
      `last_name` varchar(255) DEFAULT NULL,
      `email` varchar(255) DEFAULT NULL,
      `sex` varchar(10) DEFAULT NULL,
      `birthday` date DEFAULT NULL,
      `avatar_id` varchar(255) DEFAULT NULL,
      `created_at` datetime DEFAULT NULL,
      `updated_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `provider_id_unique` (`provider`,`provider_id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `user_oauths_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ```

4.  **Создайте файл конфигурации** `application/config/yandex_oauth.php`:

    ```php
    <?php defined('SYSPATH') or die('No direct script access.');

    return array(
        'client_id'         => 'ВАШ_CLIENT_ID_ЯНДЕКС',
        'redirect_uri'      => 'http://test.promerp.ru/yandexauth/callback', // Укажите ваш домен
        'token_page_origin' => 'http://test.promerp.ru', // Укажите ваш домен
    );
    ```
    *   `client_id`: Получите его в [Яндекс.OAuth](https://oauth.yandex.ru/).
    *   `redirect_uri`: URL, на который Яндекс перенаправит пользователя после авторизации. Должен совпадать с тем, что вы указали в настройках приложения Яндекс.
    *   `token_page_origin`: Домен вашего сайта, используется для безопасности при передаче токена между страницами.

5.  **Настройте маршруты** в `application/bootstrap.php` (или в файле маршрутов модуля, если он у вас есть):

    ```php
    Route::set('yandexauth', 'yandexauth(/<action>)')
        ->defaults(array(
            'controller' => 'yandexauth',
            'action'     => 'login',
        ));
    ```

## Использование

1.  **Ссылка для входа через Яндекс**:
    Разместите на вашем сайте ссылку, ведущую на страницу входа через Яндекс:

    ```html
    <a href="/yandexauth/login">Войти через Яндекс</a>
    ```

2.  **Страница входа (`yandexauth/login`)**:
    Эта страница отображает кнопку или ссылку для начала процесса авторизации Яндекс. Она использует `client_id` и `redirect_uri` из конфигурации.

3.  **Страница обратного вызова (`yandexauth/callback`)**:
    Это `redirect_uri`, указанный в настройках Яндекс.OAuth. После успешной авторизации на Яндексе, пользователь будет перенаправлен сюда. Эта страница получает OAuth-токен и передает его на сервер для получения информации о пользователе.

4.  **Получение информации о пользователе (`yandexauth/get_user_info`)**:
    Это AJAX-эндпоинт, который принимает OAuth-токен, отправляет запрос к API Яндекс для получения данных пользователя, создает/обновляет записи в таблицах `users` и `user_oauths` и авторизует пользователя в системе.

5.  **Профиль пользователя (`yandexauth/profile`)**:
    После успешного входа, пользователь будет перенаправлен на эту страницу. Если в сессии был сохранен `login_redirect`, пользователь будет перенаправлен на исходную страницу.

6.  **Выход (`yandexauth/logout`)**:
    Разлогинивает пользователя из системы и удаляет связанные с Яндекс.OAuth данные из сессии.

## Пример потока авторизации

1.  Пользователь нажимает "Войти через Яндекс" на вашем сайте (ссылка на `/yandexauth/login`).
2.  Контроллер `Yandexauth::action_login()` отображает страницу с кнопкой, которая перенаправляет пользователя на страницу авторизации Яндекс.
3.  Пользователь авторизуется на Яндексе и дает разрешение вашему приложению.
4.  Яндекс перенаправляет пользователя обратно на `redirect_uri` (например, `/yandexauth/callback`) с OAuth-токеном в URL-фрагменте.
5.  Страница `/yandexauth/callback` использует JavaScript для извлечения токена и отправляет его AJAX-запросом на `/yandexauth/get_user_info`.
6.  Контроллер `Yandexauth::action_get_user_info()`:
    *   Получает токен.
    *   Отправляет запрос к `https://login.yandex.ru/info` для получения данных пользователя.
    *   Находит или создает запись пользователя в таблице `users`.
    *   Находит или создает запись в таблице `user_oauths`, связывая ее с пользователем и сохраняя данные из Яндекс.
    *   Авторизует пользователя в Kohana.
    *   Возвращает JSON-ответ с информацией о статусе и URL для перенаправления.
7.  JavaScript на странице `callback` получает JSON-ответ и перенаправляет пользователя на `/yandexauth/profile` или на сохраненный `login_redirect` URL.

## Модели

Модуль предполагает наличие следующих моделей:

*   **`Model_User`**: Стандартная модель пользователя Kohana Auth.
*   **`Model_User_Oauth`**: Модель для хранения специфичных данных OAuth-провайдера.

    ```php
    // application/classes/Model/User/Oauth.php
    class Model_User_Oauth extends ORM {

        protected $_table_name = 'user_oauths'; // Или ваше имя таблицы

        protected $_belongs_to = array(
            'user' => array(
                'model'       => 'User',
                'foreign_key' => 'user_id',
            ),
        );

        protected $_table_columns = array(
            'id'           => array('type' => 'int'),
            'user_id'      => array('type' => 'int'),
            'provider'     => array('type' => 'string'),
            'provider_id'  => array('type' => 'string'),
            'access_token' => array('type' => 'string'),
            'first_name'   => array('type' => 'string'),
            'last_name'    => array('type' => 'string'),
            'email'        => array('type' => 'string'),
            'sex'          => array('type' => 'string'),
            'birthday'     => array('type' => 'string'),
            'avatar_id'    => array('type' => 'string'),
            'created_at'   => array('type' => 'datetime'),
            'updated_at'   => array('type' => 'datetime'),
        );

        public function rules()
        {
            return array(
                'user_id' => array(
                    array('not_empty'),
                    array('digit'),
                ),
                'provider' => array(
                    array('not_empty'),
                    array('max_length', array(':value', 50)),
                ),
                'provider_id' => array(
                    array('not_empty'),
                    array('max_length', array(':value', 255)),
                ),
                // ... другие правила валидации
            );
        }

        public function filters()
        {
            return array(
                'created_at' => array(
                    array(function($value) { return date('Y-m-d H:i:s'); }, array(':value')),
                ),
                'updated_at' => array(
                    array(function($value) { return date('Y-m-d H:i:s'); }, array(':value')),
                ),
            );
        }

        public function save(Validation $validation = NULL)
        {
            if ($this->loaded()) {
                $this->updated_at = date('Y-m-d H:i:s');
            } else {
                $this->created_at = date('Y-m-d H:i:s');
                $this->updated_at = date('Y-m-d H:i:s');
            }
            return parent::save($validation);
        }
    }
    ```

## Представления (Views)

Модуль использует следующие представления:

*   `application/modules/yandexauth/views/yandexauth/login.php`
*   `application/modules/yandexauth/views/yandexauth/callback.php`

Убедитесь, что эти файлы существуют и содержат необходимую логику для взаимодействия с Яндекс.OAuth.

### Пример `yandexauth/login.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Вход через Яндекс</title>
</head>
<body>
    <h1>Войти через Яндекс</h1>
    <p>Нажмите кнопку ниже, чтобы авторизоваться через Яндекс.</p>
    <a href="https://oauth.yandex.ru/authorize?response_type=token&client_id=<?php echo $client_id; ?>&redirect_uri=<?php echo urlencode($redirect_uri); ?>">
        <button>Войти через Яндекс</button>
    </a>
    <p>
        <small>После авторизации вы будете перенаправлены на страницу: <?php echo $redirect_uri; ?></small>
    </p>
</body>
</html>
```

### Пример `yandexauth/callback.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Обработка авторизации Яндекс</title>
    <script>
        // Функция для извлечения параметров из URL-фрагмента
        function getHashParams() {
            var hashParams = {};
            var e,
                a = /\+/g,  // Regex for replacing addition symbol with a space
                r = /([^&;=]+)=?([^&;]*)/g,
                d = function (s) { return decodeURIComponent(s.replace(a, " ")); },
                q = window.location.hash.substring(1);

            while (e = r.exec(q))
               hashParams[d(e[1])] = d(e[2]);

            return hashParams;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var params = getHashParams();
            var oauth_token = params['access_token'];
            var token_type = params['token_type']; // Обычно 'bearer'
            var expires_in = params['expires_in']; // Время жизни токена в секундах

            if (oauth_token) {
                console.log('OAuth Token:', oauth_token);
                console.log('Token Type:', token_type);
                console.log('Expires In:', expires_in);

                // Отправляем токен на сервер для получения информации о пользователе
                fetch('/yandexauth/get_user_info', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'oauth_token=' + encodeURIComponent(oauth_token)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        console.log('User Info:', data.user_info);
                        alert('Вы успешно вошли через Яндекс!');
                        // Перенаправляем пользователя на страницу профиля или на исходную страницу
                        window.location.href = data.redirect || '/';
                    } else {
                        console.error('Error:', data.message);
                        alert('Ошибка при получении данных пользователя: ' + data.message);
                        window.location.href = '/yandexauth/login'; // Вернуть на страницу входа
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Произошла ошибка сети при получении данных пользователя.');
                    window.location.href = '/yandexauth/login'; // Вернуть на страницу входа
                });

            } else {
                console.error('OAuth token not found in URL fragment.');
                alert('Ошибка: Токен авторизации не найден.');
                window.location.href = '/yandexauth/login'; // Вернуть на страницу входа
            }
        });
    </script>
</head>
<body>
    <h1>Обработка авторизации...</h1>
    <p>Пожалуйста, подождите, пока мы обрабатываем вашу авторизацию через Яндекс.</p>
</body>
</html>