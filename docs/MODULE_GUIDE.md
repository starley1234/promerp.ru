# Как создавать новые модули (соглашения PromERP)

## 1. Идентификация

| Сущность | Правило | Пример |
| :--- | :--- | :--- |
| Каталог | `application/modules/erpNNN_slug/` | `erp401_tasks` |
| ID | трёхзначный номер; диапазоны: 100–199 ядро, 200–299 платформа/данные, 300–399 сущности, 400–599 процессы, 800–899 аналитика | `401` |
| Слаг | `a-z0-9_` | `tasks` |
| Классы | `<Layer>_ErpNNN_<Name>` | `Controller_Erp401_Main`, `Service_Erp401_Task`, `Model_Erp401_Task` |
| Таблицы | `erp_NNN_*` | `erp_401_tasks` |
| Роуты | префикс `erpNNN` | `/erp401/main/index` |
| Views | `views/erpNNN/<controller>/<action>.php` | `views/erp401/main/index.php` |
| Миграции | `migrations_pg/pgMigration_0NN_ErpNNN_*.php` | `pgMigration_001_Erp401_CreateTables` |
| Права | `erpNNN_<slug>.<ability>` | `erp401_tasks.create` |

## 2. Быстрый старт: генератор

```bash
make new-module id=401 slug=tasks name="Задачи"
# или
bin/console module:scaffold --id=401 --slug=tasks --name="Задачи"
```

Генератор создаёт:

```
application/modules/erp401_tasks/
├── README.md                                  # паспорт модуля (обязателен)
├── init.php                                   # Route::set + подписки на события
├── classes/Controller/Erp401/Main.php
├── classes/Service/Erp401/Example.php         # бизнес-логика
├── classes/Model/Erp401/Example.php           # ORM
├── migrations_pg/pgMigration_001_Erp401_CreateTables.php
└── views/erp401/main/index.php
```

Дальше вручную:

1. Подключить модуль в `application/bootstrap.php`:
   ```php
   'erp401_tasks' => APPPATH.'modules/erp401_tasks',
   ```
2. `bin/console db:migrate --module=erp401_tasks`
3. `bin/console module:context --module=erp401_tasks` — проверить, что карточка
   модуля собирается (она собирается из кода и не требует ручных реестров).
4. Заполнить README (назначение, API сервиса, таблицы, события, права).
5. Запустить проверки только этого модуля:
   ```bash
   make check-module module=erp401_tasks
   ```
6. Обновить сгенерированную документацию: `make docs` (карта модулей, таблицы в
   README и ARCHITECTURE обновятся сами).

## 3. Каркас модуля

### init.php — роуты и подписки

```php
<?php defined('SYSPATH') or die('No direct access.');

Route::set('erp401', 'erp401(/<controller>(/<action>(/<id>)))', ['id' => '[0-9]+'])
    ->defaults([
        'directory'  => 'Erp401',
        'controller' => 'Main',
        'action'     => 'index',
    ]);

// Подписки на события других модулей (шина erp208)
// Service_Erp208_EventBus::instance()->listen('erp221.deal.created', function ($payload) { ... });
```

### Контроллер

```php
class Controller_Erp401_Main extends Controller_Erp100_Base
{
    public function action_index()
    {
        $this->set_title('Задачи');

        $this->template->content = View::factory('erp401/main/index')
            ->set('items', Service_Erp401_Task::get_all());
    }
}
```

### Сервис (единственная точка доступа к данным модуля)

```php
class Service_Erp401_Task
{
    public static function get_all() { /* ORM */ }

    public static function create(array $data)
    {
        $task = ORM::factory('Erp401_Task')->values($data)->save();

        // Публикуем событие — остальные модули реагируют сами
        Service_Erp208_EventBus::instance()->publish('erp401.task.created', [
            'task_id' => $task->id,
        ]);

        return $task;
    }
}
```

### Модель

```php
class Model_Erp401_Task extends ORM
{
    protected $_table_name = 'erp_401_tasks';

    // Колонки прописываем явно: экономим запросы к information_schema
    protected $_table_columns = [...];

    public function rules() { return [...]; }
}
```

### Миграция

```php
class pgMigration_001_Erp401_CreateTables extends pgMigration
{
    public function up()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_401_tasks (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_401_tasks CASCADE;");
    }
}
```

## 4. Обязательные правила

1. **Чужие данные — только через Service.**
   ```php
   // правильно
   $tasks = Service_Erp401_Task::get_all();
   // неправильно
   $tasks = ORM::factory('Erp401_Task')->find_all();
   ```
2. **События вместо прямых вызовов.** Модуль не должен знать о существовании других
   модулей: публикуйте `erpNNN.<событие>` в шину erp208.
3. **Все таблицы с префиксом** `erp_NNN_`.
4. **Миграции идемпотентны** (`IF NOT EXISTS`) и транзакционны (за этим следит раннер).
5. **Не меняйте файлы ядра и чужих модулей** — перекрывайте их в `application/classes`
   (каскад Kohana).
6. **README.md модуля** — обязателен: назначение, публичное API, таблицы, события, права.
7. **Права доступа** описываются в README и проверяются через `$this->can(...)`.
8. **PHP 8.4** (минимум 8.1): не передавайте `null` в строковые функции, не создавайте
   динамические свойства — в CI это проверяется (`STRICT_DEPRECATIONS=1`).
9. **Модуль должен быть читаем агентом.** Первые 2–3 абзаца README — это то, что
   попадёт в карточку `module:context` и в `docs/MODULE_MAP.md`: пишите их как
   ответ на вопрос «что делает модуль и что он НЕ делает».
10. **Не заводите реестров модуля руками** (списки в конфигах, таблицах, README
    других модулей): состав берётся из `bootstrap.php` и диска, а карточка — из кода.
    Пример обратного — таблица модулей в корневом README: она генерируется (`make docs`),
    руками её не правят.
11. **Комментарии — на русском.** Docblock, пояснения и сообщения пользователю
    пишем по-русски; латиница — только в идентификаторах, ключах и именах
    внешних пакетов. Наследный код фреймворка не переводим.
12. **Поля формы не «скачут» по вертикали.** Строка фильтров выравнивается по
    верху (`items-start`), кнопка и ссылка рядом получают `mt-6`, подпись поля —
    класс `field-label`: подсказка под полем добавляет ему строку, и при
    выравнивании по низу (`items-end`) поле уезжает вверх относительно соседнего
    селекта. Правило проверяет `dev:audit` (раздел `ui_components`).

## 5. Чек-лист перед PR

- [ ] `make check-module module=erpNNN_slug` — линт, аудит, smoke и тесты модуля зелёные
- [ ] `bin/console module:context --module=erpNNN_slug` — карточка собирается
  (в ней видны таблицы, роуты, права, события, MCP, тесты)
- [ ] `bin/console db:migrate --status` — миграции применены
- [ ] `bin/console module:list` — модуль виден, подключён, есть README
- [ ] `make docs` — сгенерированная документация обновлена
- [ ] Страница модуля открывается под обычным пользователем и под админом
- [ ] Нет прямых запросов к чужим таблицам (в карточке нет строки «чужие в SQL»)
- [ ] События и MCP-инструменты задокументированы в README модуля
- [ ] `make check` — полный гейт перед коммитом


## 6. README модуля: обязательный минимум

Карточка `module:context`, `docs/MODULE_MAP.md` и читающий человек берут из
README одно и то же. Держите его в таком виде:

```markdown
# erp401_wms — Складской учёт

## Назначение

Модуль ведёт количественный учёт номенклатуры по складам: остатки, движения,
резервы и контроль минимумов. Номенклатуру НЕ ведёт — она у реестра erp227.

## Состав
| Слой | Класс | Назначение |
|---|---|---|
| Контроллер | `Controller_Erp401_Api` | JSON API для UI и MCP |
| Сервис | `Service_Erp401_Stock` | единственный вход в данные модуля |
| Модель | `Model_Erp401_Balance` | ORM над `erp_401_balances` |
| Миграции | `migrations_pg/pgMigration_001_Erp401_Wms_CreateTables` | схема |

## Таблицы
## События (публикует / слушает)
## Права (`application/config.dist/acl.php`)
## HTTP / JSON API / MCP
## Зависимости: от кого и кто от нас
## Как проверить
bin/console module:context --module=erp401_wms
make check-module module=erp401_wms
```

Первые 200 символов раздела «Назначение» попадают в карточку модуля и в карту
`docs/MODULE_MAP.md` — проверьте, что фраза осмысленна без контекста.

---

## Эталон

Живой пример всех соглашений — модуль `erp401_wms` (складской учёт):
сервис с транзакциями (`SELECT … FOR UPDATE`), чужие данные только через сервисы владельца,
идемпотентные PG-миграции, события `erp401.*`, права в `acl.php`, HMVC-виджеты,
тесты с пропуском при недоступной БД.
