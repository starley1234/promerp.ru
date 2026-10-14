Инструкция по реализации системы Push-уведомлений и страницы просмотра

### 1. Доработка API (Controller_Erp217_Api)
Добавьте методы для управления статусом прочтения в `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Controller/Erp217/Api.php`:

```php
/**
 * Сбросить счетчик уведомлений для текущего пользователя
 * POST /erp217_api/mark_all_read
 */
public function action_mark_all_read()
{
    Service_Erp217_Push::reset_unread_count($this->_current_user->id);
    $this->response->body(json_encode(['status' => 'success']));
}

/**
 * Пометить конкретное уведомление как прочитанное
 * POST /erp217_api/mark_read/<id>
 */
public function action_mark_read()
{
    $id = $this->request->param('id');
    $notification = ORM::factory('Erp217_Notification', $id);

    if ($notification->loaded() && $notification->recipient_id == $this->_current_user->id) {
        // Для реализации прочтения конкретного сообщения добавьте поле is_read в таблицу erp_217_notification
        // Либо используйте существующий механизм счетчика, если логика только общая
        $notification->status = 'read'; 
        $notification->save();
        $this->response->body(json_encode(['status' => 'success']));
    } else {
        $this->_json_error('Not found', 404);
    }
}
```

### 2. Реализация страницы уведомлений (Controller_Erp217_Index)
В файле `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Controller/Erp217/Index.php` измените метод `action_index`:

```php
public function action_index()
{
    $this->set_title(__('Мои уведомления'));
    
    // Сбрасываем счетчик при заходе на страницу
    // Service_Erp217_Push::reset_unread_count($this->_current_user->id);

    $notifications = ORM::factory('Erp217_Notification')
        ->where('recipient_id', '=', $this->_current_user->id)
        ->where('channel', '=', 'push')
        ->order_by('created_at', 'DESC')
        ->limit(50)
        ->find_all();

    $this->template->content = View::factory('erp217/notifications/list')
        ->set('notifications', $notifications);
}
```

### 3. Фронтенд: Интеграция в Layout
В файле `/home/c/cs11853/test.promerp.ru/application/views/template_tailwindcss.php` добавьте скрипт для работы "колокольчика":

```javascript
function updateNotificationBadge() {
    $.get('/erp217_api/unread_count', function(data) {
        const badge = $('#notification-badge');
        if (data.count > 0) {
            badge.text(data.count).show();
        } else {
            badge.hide();
        }
    });
}

$(document).ready(function() {
    updateNotificationBadge();
    setInterval(updateNotificationBadge, 300000); // Обновлять каждые 300 сек
});
```

### 4. Логика "Прочитано всё"
На странице `/erp217_index` (View `erp217/notifications/list`) добавьте кнопку:

```html
<button onclick="markAllAsRead()" class="btn-secondary">Прочитать всё</button>

<script>
function markAllAsRead() {
    $.post('/erp217_api/mark_all_read', function() {
        location.reload(); // Перезагружаем страницу для обновления статусов
    });
}

// Для прочтения при клике на уведомление
$('.notification-item').on('click', function() {
    const id = $(this).data('id');
    $.post('/erp217_api/mark_read/' + id);
    $(this).addClass('opacity-50'); // Визуальный эффект
});
</script>
```

### 5. Маршрутизация (Route)
Убедитесь, что в `init.php` модуля прописан роут для страницы уведомлений:
`Route::set('erp217_index', 'erp217_index(/<action>)')->defaults(['controller' => 'Index', 'directory' => 'Erp217']);`