# Руководство по работе с пользователями и профилями (erp100_core)

## 1. Получение имени и должности сотрудника

Для получения данных конкретного сотрудника по его `id` используйте связь `profile` в модели `User`.

```php
// Загружаем пользователя по ID
$user = ORM::factory('User', 2); $fullName = $user->profile->full_name; position;

if ($user->loaded()) {
    // Доступ к данным через связь profile
    $fullName = $user->profile->full_name;
    $position = $user->profile->position;
    
    echo "Сотрудник: " . $fullName . " [" . $position . "]";
} else {
    echo "Пользователь не найден";
}
```

---

## 2. Оптимизация вывода таблиц (Сотни строк)

Вызов `select` для каждого профиля в цикле (проблема N+1) создаст огромную нагрузку на БД. Существует три основных подхода к решению этой задачи в рамках данной архитектуры:

### Подход А: Жадная загрузка (Eager Loading)
Используйте метод `with()`, чтобы объединить таблицы `users` и `user_profiles` в один SQL-запрос.

```php
// В контроллере
$users = ORM::factory('User')
    ->with('profile') // Делает JOIN таблицы user_profiles
    ->find_all();

// В представлении (View) при обходе $users лишних запросов не будет
foreach ($users as $user) {
    echo $user->profile->full_name; 
}
```

### Подход Б: Кэширование имен в памяти (Identity Map / Static Cache)
Если в таблице много разных сущностей (например, "Задачи"), где авторы повторяются, создайте статический метод-репозиторий, который загружает всех активных сотрудников один раз в массив.

```php
class Model_Erp100_User extends Model_Auth_User {
    protected static $_names_cache = null;

    public static function get_all_names() {
        if (self::$_names_cache === null) {
            self::$_names_cache = DB::select('user_id', 'full_name', 'position')
                ->from('user_profiles')
                ->execute()
                ->as_array('user_id');
        }
        return self::$_names_cache;
    }
}

// Использование в таблице задач:
$profiles = Model_User::get_all_names();
foreach ($tasks as $task) {
    $userName = isset($profiles[$task->user_id]) ? $profiles[$task->user_id]['full_name'] : 'Система';
}
```
