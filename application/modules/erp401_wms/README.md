# Складской учёт (erp401_wms)

**ID модуля:** 401
**Слаг:** `wms`
**Слой:** Operations (диапазон 400–599)
**Префикс таблиц:** `erp_401_*`
**Статус:** реализован, миграции написаны, требует PHP 8.1+ и PostgreSQL 13+

---

## 1. Назначение

Модуль ведёт количественный учёт номенклатуры по складам: остатки, движения,
резервы и контроль минимумов. Это первый модуль слоя Operations — в системе до
него не было ни складов, ни операций, ни документооборота.

Модуль **не владеет номенклатурой**: позиции берутся из реестра объектов
`erp227` (поле `item_id` → `erp_227_objects.id`). Единицы измерения — из
`erp_227_units` через сервис реестра.

### Возможности

| Функция | Описание |
|---|---|
| **Склады** | Справочник складов с типами: основной, производственный, транзитный, карантин, списание. Закрыть склад с остатками нельзя |
| **Остатки** | Одна строка на пару (склад, позиция): количество, резерв, неснижаемый минимум |
| **Движения** | Приход, расход, перемещение, корректировка. Журнал append-only, транзакционный, с остатками «до/после» |
| **Резервы** | Заморозка свободного остатка под документ-потребитель; жизненный цикл `active → consumed \| released` |
| **Минимумы** | Контроль падения остатка ниже минимума с событием `erp401.stock_low` и уведомлением ответственных |
| **API** | JSON API для интеграций и виджетов |
| **Виджеты** | HMVC-виджеты для встраивания в дашборды и карточки объектов |

---

## 2. Структура базы данных

Миграции: `migrations_pg/pgMigration_001_Erp401_Wms_CreateTables.php`
и `migrations_pg/pgMigration_002_Erp401_Wms_Seed.php` (демоданные).

### `erp_401_warehouses` — склады
`id`, `code` (уник.), `name`, `type` (`main|production|transit|quarantine|scrap`),
`object_id`, `is_active`, `address`, `description`, `created_by`, `created_at`, `updated_at`.

### `erp_401_balances` — остатки
`id`, `warehouse_id` (FK), `item_id` (→ реестр erp227), `uom_code`, `qty`,
`reserved`, `min_qty`, `updated_at`.
Ограничения: уникальность `(warehouse_id, item_id)`, `qty >= 0`, `reserved >= 0`.

### `erp_401_movements` — журнал движений
`id`, `type` (`receipt|issue|transfer|adjust`), `item_id`, `uom_code`,
`warehouse_id`, `warehouse_to_id`, `qty`, `balance_before`, `balance_after`,
`status` (`draft|posted|void`), `doc_type`, `doc_id`, `note`, `performed_at`,
`created_by`, `created_at`.

### `erp_401_reservations` — резервы
`id`, `warehouse_id`, `item_id`, `qty`, `status` (`active|released|consumed`),
`ref_type`, `ref_id`, `expires_at`, `created_by`, `created_at`, `released_at`.

---

## 3. Архитектура

Бизнес-логика — только в `Service_Erp401_Stock`. Прямая работа с моделями
`Model_Erp401_Balance` из других модулей запрещена.

```
Controller_Erp401_Movement ─┐
Controller_Erp401_Api      ─┼─→ Service_Erp401_Stock ─→ erp_401_* (БД)
Controller_Erp401_Widget   ─┘         │
                                      ├─→ Service_Erp227_Registry (номенклатура, единицы)
                                      ├─→ Service_Erp208_EventBus (события)
                                      └─→ Event::fire() (локальная шина)
```

### Ключевые правила сервиса

* Все изменения остатков идут в транзакции (`Database::begin/commit/rollback`).
* Строка остатка блокируется `SELECT ... FOR UPDATE` — параллельные списания не
  приводят к уходу в минус.
* Расход и перемещение доступны только в пределах свободного остатка
  (`qty - reserved`); попытка затронуть резерв отклоняется с
  `Exception_Erp401_Stock` / `insufficient_stock`.
* Корректировка не может уменьшить физический остаток ниже суммы резервов.
* Частичное снятие/списание резерва оставляет остаток резерва активным; переход
  в терминальный статус происходит только при полном закрытии. Изменение
  блокирует строку резерва и остатка в транзакции.
* Каждая операция пишет строку в журнал движений: это единственный источник
  правды для пересчёта остатков.
* Сторнирование — отдельной записью, строки журнала не редактируются.

### Публичное API сервиса

```php
$stock = Service_Erp401_Stock::instance();

// Склады
$stock->warehouses($active_only = TRUE);
$stock->create_warehouse(['code' => 'WH-2', 'name' => 'Склад №2', 'type' => 'main']);
$stock->deactivate_warehouse($id);          // бросит исключение, если есть остатки

// Остатки (чтение)
$stock->qty($warehouse_id, $item_id);       // физический остаток
$stock->available($warehouse_id, $item_id); // свободный (минус резервы)
$stock->total_qty($item_id);                // по всем складам
$stock->low_stock(10);                      // позиции ниже минимума

// Движения (запись)
$stock->receive($warehouse_id, $item_id, 100, ['uom_code' => 'pcs', 'doc_type' => 'order', 'doc_id' => 17]);
$stock->issue($warehouse_id, $item_id, 20);   // нельзя уйти в минус
$stock->transfer($from_id, $to_id, $item_id, 15);
$stock->adjust($warehouse_id, $item_id, 42, ['note' => 'По инвентаризации']);

// Резервы
$reservation = $stock->reserve($warehouse_id, $item_id, 10, ['ref_type' => 'order', 'ref_id' => 17]);
$stock->release_reservation($reservation->id);  // снять
$stock->consume_reservation($reservation->id);  // списать (создаёт расход)

// Номенклатура (через реестр erp227)
$stock->catalog();            // корневые объекты реестра
$stock->search_items('STM');  // полнотекстовый поиск
$stock->item($item_id);       // {id, code, name}
$stock->units();              // справочник единиц измерения
```

---

## 4. События

| Событие | Когда |
|---|---|
| `erp401.warehouse_created` | Создан склад |
| `erp401.stock_received` | Приход |
| `erp401.stock_issued` | Расход |
| `erp401.stock_transferred` | Перемещение |
| `erp401.stock_adjusted` | Корректировка |
| `erp401.stock_low` | Остаток <= минимума |
| `erp401.reservation_created` | Создан резерв |
| `erp401.reservation_released` | Резерв снят |
| `erp401.reservation_consumed` | Резерв списан |

События публикуются в шину `erp208` (если модуль подключён) и в локальную шину
`Event`. Подписчик на `erp401.stock_low` регистрируется миграцией 002 в таблице
`erp_208_event_subscribers` → `Service_Erp401_Notifications::on_stock_low`,
который рассылает уведомления через центр уведомлений `erp217` пользователям с
ролью `401_stock_manager` или `admin`.

Пример подписки из другого модуля:

```php
// init.php модуля-потребителя
Event::listen('erp401.stock_low', function ($payload) {
    // $payload: warehouse_id, item_id, qty, min_qty, uom_code
});
```

---

## 5. Маршруты

| Метод | URL | Назначение |
|---|---|---|
| GET | `/warehouse` | Сводка (дашборд) |
| GET | `/warehouse/balances` | Остатки с фильтрами |
| POST | `/warehouse/balances/set_min` | Задать минимум позиции |
| GET | `/warehouse/movements` | Журнал движений |
| GET | `/warehouse/movements/create` | Форма операции |
| POST | `/warehouse/movements/post` | Провести приход/расход/перемещение |
| GET | `/warehouse/warehouses` | Склады |
| GET/POST | `/warehouse/warehouses/edit[/id]`, `/warehouse/warehouses/save` | Форма и сохранение склада |
| GET | `/warehouse/reservations` | Резервы |
| POST | `/warehouse/reservations/create` | Создать резерв |
| GET | `/warehouse/reservations/release/<id>`, `/consume/<id>` | Снять / списать резерв |
| GET | `/warehouse/api/<action>` | JSON API: `balances`, `movements`, `items`, `stock`, `receive`, `issue`, `transfer`, `reserve` |
| GET | `/warehouse/widget/<action>` | HMVC-виджеты: `low_stock`, `item_card`, `summary` |

### Примеры API

```bash
# Остатки ниже минимума
curl -b cookies.txt 'http://localhost:8080/warehouse/api/balances?low_only=1'

# Приход 100 шт. позиции 3 на склад 2
curl -b cookies.txt -X POST 'http://localhost:8080/warehouse/api/receive' \
  -d 'warehouse_id=2&item_id=3&qty=100&uom_code=pcs&doc_type=order&doc_id=17'
```

Ответ: `{"success":true,"data":{...}}`; при ошибке бизнес-правила —
HTTP 422 и `{"success":false,"error":{"message":"...","reason":"insufficient_stock"}}`.

### Встраивание виджета

```php
echo Request::factory('warehouse/widget/low_stock')->execute()->body();
echo Request::factory('warehouse/widget/item_card')->query('item_id', $object_id)->execute()->body();
```

---

## 6. Права доступа

Права описаны в `application/config/acl.php` (шаблон — `application/config.dist/acl.php`):

| Право | Роли |
|---|---|
| `erp401_wms.view_dashboard` | admin, 401_stock_manager, 401_stock_viewer |
| `erp401_wms.view_stock` | admin, 401_stock_manager, 401_stock_viewer |
| `erp401_wms.manage_movements` | admin, 401_stock_manager |
| `erp401_wms.reserve` | admin, 401_stock_manager |
| `erp401_wms.manage_warehouses` | admin, 401_stock_manager |

Роли `401_stock_manager` и `401_stock_viewer` создаются миграцией 001.
Роль `admin` получает все права автоматически.

---

## 7. MCP для аудита

Read-only ключ агента получает три инструмента из `mcp.php`:

- `warehouse.list` — склады;
- `warehouse.stock.list` — остатки, резерв и доступное количество;
- `warehouse.movements.list` — журнал движений с фильтрами.

Все используют существующий `Service_Erp401_Stock` и право
`erp401_wms.view_stock`; отдельной логики чтения для агента нет.

## 8. Установка

1. Добавить модуль в `application/bootstrap.php`:
   ```php
   'erp401_wms' => APPPATH.'modules/erp401_wms',
   ```
2. Применить миграции:
   ```bash
   php public_html/index.php --task=db:migrate
   # или для Windows-терминала без экранирования:
   php public_html/index.php --task=db:migrate
   ```
3. Выдать пользователям роль `401_stock_manager`.

Откат: `php public_html/index.php --task=db:migrate --down=1` (с флагом подтверждения).

---

## 9. Ограничения и планы

* Партионный (batch) и серийный учёт не реализован — только количественный.
* Себестоимость и складская оценка (FIFO/средневзвешенная) — в планах.
* Инвентаризация с расчётом расхождений — отдельным документом в слое Operations.
* Резервы пока не имеют авто-освобождения по `expires_at` — нужен планировщик.
