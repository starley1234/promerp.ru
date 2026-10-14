# Мои ссылки (erp118_links)

"Мои ссылки" предоставляет пользователям возможность сохранять и управлять персональными ссылками в системе. Эти ссылки могут быть отображены в виде карточек на главной странице модуля, а также некоторые из них могут быть добавлены в основное меню пользователя для быстрого доступа.

## Функциональность

*   **Сохранение и удаление ссылок:** Пользователи могут сохранять любые URL-адреса с заголовком, описанием и выбором иконки. Существует также возможность быстрого сохранения/удаления текущей страницы.
*   **Отображение ссылок:** Сохраненные ссылки отображаются в виде удобных карточек с возможностью настройки цвета и иконки.
*   **Управление ссылками:** На отдельной странице управления пользователи могут редактировать, удалять, изменять порядок и группировать свои ссылки.
*   **Интеграция с меню:** Любая ссылка может быть помечена как "пункт меню" и будет автоматически добавлена в боковое меню пользователя.
*   **Автоматическое определение иконок:** Модуль пытается автоматически определить подходящую иконку для ссылки на основе ее URL.
*   **API для ссылок:** Предоставляется API-метод для получения всех ссылок пользователя.

## Установка

1.  **Применить миграцию:**
    Выполните миграцию для создания таблицы `lib_118_links`:
    ```bash
    php minion migrate --up
    ```
    (или вручную выполните SQL-код из файла `migrations/Migration_Erp_118_001_Links_Table.php`)

2.  **Добавить в `bootstrap.php`:**
    Убедитесь, что модуль включен в вашем файле `application/bootstrap.php`:
    ```php
    Kohana::modules(array(
        // ... другие модули
        'erp118_links' => MODPATH.'erp118_links', // Модуль "Мои ссылки"
    ));
    ```

## Использование

### Главная страница модуля

Перейдите по адресу `/erp118_links` для просмотра всех сохраненных ссылок в виде карточек.

### Сохранение/удаление ссылки (AJAX)

Для сохранения или удаления ссылки с текущей страницы можно использовать AJAX-запрос к `/erp118_links/toggle_save`.

**Пример JavaScript для сохранения/удаления:**

```javascript
function toggleSaveLink(url, title, description, isMenuItem, iconClass) {
    fetch('/erp118_links/toggle_save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            url: url,
            title: title,
            description: description,
            is_menu_item: isMenuItem ? 1 : 0,
            icon_class: iconClass
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log(data.message);
            // Обновить UI, например, изменить иконку "сохранить"
        } else {
            console.error('Ошибка:', data.message);
        }
    })
    .catch(error => console.error('Ошибка сети:', error));
}

// Пример использования:
// toggleSaveLink(window.location.href, document.title, 'Описание текущей страницы', true, 'fas fa-star');
```

### Проверка статуса ссылки (AJAX)

Для проверки, сохранена ли текущая ссылка, можно использовать AJAX-запрос к `/erp118_links/check_link_status`.

**Пример JavaScript для проверки статуса:**

```javascript
function checkLinkStatus(url) {
    fetch(`/erp118_links/check_link_status?url=${encodeURIComponent(url)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Ссылка сохранена:', data.is_saved);
            // Обновить UI, например, показать соответствующую иконку
        } else {
            console.error('Ошибка:', data.message);
        }
    })
    .catch(error => console.error('Ошибка сети:', error));
}

// Пример использования:
// checkLinkStatus(window.location.href);
```

### Страница управления ссылками

Перейдите по адресу `/erp118_links/manage` для доступа к интерфейсу управления ссылками. Здесь вы можете:
*   Редактировать заголовок, описание, цвет, иконку и группу каждой ссылки.
*   Отметить ссылку как "пункт меню".
*   Удалять ссылки.
*   Изменять порядок ссылок перетаскиванием (drag-and-drop).

### API для получения ссылок

Для получения всех ссылок пользователя в формате JSON можно использовать `/erp118_links/get_links_api`.

**Пример JavaScript для получения ссылок:**

```javascript
fetch('/erp118_links/get_links_api')
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Ссылки пользователя:', data.links);
            // Обработать полученные ссылки
        } else {
            console.error('Ошибка API:', data.message);
        }
    })
    .catch(error => console.error('Ошибка сети:', error));
```

## Структура модуля

*   `classes/Controller/Erp118/Links.php`: Основной контроллер модуля, обрабатывающий запросы, связанные со ссылками.
*   `views/erp118/links/cards_tailwindcss.php`: Представление для отображения ссылок в виде карточек.
*   `views/erp118/links/manage_tailwindcss.php`: Представление для страницы управления ссылками.
*   `migrations/Migration_Erp_118_001_Links_Table.php`: Миграция для создания таблицы `lib_118_links`.
*   `media/js/erp118_links.js`: (Предполагается, что здесь будет находиться JS-код для управления ссылками на странице `manage`).
*   `config/erp118_links.php`: (Предполагается, что здесь будут настройки модуля, если они потребуются).

## Зависимости

*   Kohana Framework
*   ORM (для работы с базой данных)
*   `Controller_Base` (базовый контроллер вашей системы)
*   Font Awesome (для иконок)
*   Tailwind CSS (для стилизации представлений)
*   Sortable.js (для перетаскивания элементов на странице управления)