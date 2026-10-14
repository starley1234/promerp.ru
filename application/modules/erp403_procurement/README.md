# erp403_procurement — закупки

Отвечает на вопрос «что, у кого и по какой цене куплено, и что из этого уже
приехало на склад». Модуль закрывает разрыв между потребностью и складом:

```
заявка (руками, по минимуму склада или из предложения erp407)
  → согласование
  → заказ поставщику
  → приёмка (документ поставщика + входной контроль)
  → приход на склад (erp401) + запись цены в историю
```

Цепочка производства, в которой модуль занимает место закупщика:

```
техкарты → ПЗ → цех → планирование → [закупки] → качество → документы → себестоимость
```

Модуль не заводит свои справочники: поставщики — контрагенты с ролью
«поставщик» (erp301), номенклатура — реестр erp227, склад — erp401, складские
остатки и минимумы — тоже erp401. Ссылки на чужие модули мягкие (без внешних
ключей): модули включаются и выключаются независимо, а документы закупки
остаются читаемыми.

## Состав модуля

| Слой | Файлы | Что делает |
|------|-------|------------|
| Данные | `migrations_pg/pgMigration_001_*` | 8 таблиц: `erp_403_requests`, `erp_403_request_lines`, `erp_403_orders`, `erp_403_order_lines`, `erp_403_receipts`, `erp_403_receipt_lines`, `erp_403_prices`, `erp_403_suppliers` + последовательности номеров |
| | `migrations_pg/pgMigration_002_*` | демонстрация: условия двух поставщиков, история цен, заявка `ЗК-ГГГГ-900001` на согласовании и черновик `ЗК-ГГГГ-900002` |
| Модели | `classes/Model/Erp403/{Request,RequestLine,Order,OrderLine,Receipt,ReceiptLine,Price,Supplier}.php` | состояния, подписи и цвета, `is_editable()`, `can_order()`, `remaining()`, `progress()`, `is_blocked()` |
| Сервисы | `classes/Service/Erp403/Requests.php` | заявки: создание, правка, позиции, согласование, сбор из минимума склада, нумерация `ЗК-ГГГГ-НННННН` |
| | `classes/Service/Erp403/Suppliers.php` | условия поставщиков, история цен, сравнение и выбор поставщика по позиции |
| | `classes/Service/Erp403/Orders.php` | заказы: создание вручную и из согласованных заявок, отправка (с записью цен), подтверждение, отмена, состояние приёмки |
| | `classes/Service/Erp403/Receipts.php` | приёмки: создание из заказа, входной контроль, проведение (приход в erp401), отмена черновика |
| Экраны | `classes/Controller/Erp403/{Main,Index,Request,Order,Receipt,Supplier,Api,Widget}.php` + `views/erp403/*` (17 видов) | базовый контроллер (меню, flash, доступ к сервисам), доска, дефицит склада, заявки, заказы, приёмки, поставщики, JSON API, 3 виджета |
| Агент | `mcp.php` | 29 инструментов MCP: чтение закупок, подготовка документов, согласование и проведение |
| Переходы | `init.php` | 25 маршрутов `/procurement/*` и описание событий `erp403.*` |

## Как это работает

1. **Заявка** (`draft`) — потребность: позиции «что, сколько и к какому сроку».
   Три источника: руками, автоматически по минимуму склада
   (`Requests::auto_from_low_stock()` — количество до минимума с запасом 20 %,
   округление вверх, открытые заявки не задваиваются) и из планирования erp407
   (`source = erp407`, в `source_ref` — номер предложения плана).
2. **Согласование**: `submitted` → `approved` или `rejected` (с причиной).
   Согласованная заявка — единственный вход в заказ; отклонённая не правится.
3. **Заказ поставщику** (`ЗП-ГГГГ-НННННН`) — `Orders::from_requests()` собирает
   открытые позиции согласованных заявок в заказы по поставщику: позиция без
   поставщика получает рекомендованного (`Suppliers::choose()`), цена — из
   строки заявки или последнюю известную. Заявки, попавшие в заказ, переходят в
   `ordered`.
4. **Отправка** (`send`) фиксирует цены в истории (`source = order`) и
   переводит заказ в `sent`; черновик после этого не правится. Дальше —
   `confirmed` (поставщик взял заказ) или `cancelled` с причиной.
5. **Приёмка** (`ПР-ГГГГ-НННННН`) создаётся из заказа: строки подставляются как
   «то, что заказ ещё не принял». Входной контроль по позиции —
   `pending`/`passed`/`failed`; **проваленный контроль блокирует проведение**.
6. **Проведение** (`post`) — момент, когда закупка становится складом:
   * позиции с номенклатурой уходят приходом в erp401
     (`doc_type = erp403_receipt`, ссылка на приёмку);
   * количества попадают в `qty_received` строк заказа, заказ пересчитывается в
     `partially_received`/`received`;
   * цены приёмки пишутся в историю (`source = receipt`);
   * позиции без номенклатуры пропускаются с предупреждением — приход по ним
     невозможен, пока их не сопоставят.
   Проведённую приёмку отменить нельзя (`already_posted`): обратное движение
   оформляется расходом в модуле склада.

## Выбор поставщика

`Suppliers::compare($item_id, $qty)` считает баллы (0–100+):

| Фактор | Баллы |
|--------|-------|
| Цена относительно лучшей | до 50 |
| Срок поставки (10 дней — 30 баллов, 30 дней — 0) | до 30 |
| Оценка закупщика 1–5 | до 15 |
| Исполнительность (доля приёмок в срок) | до 15 |
| Метка «предпочтительный» | +10 |
| Партия меньше минимальной | −20 |

Первая строка — рекомендация; рядом с ней — «почему» (`reasons`). Если истории
цен по позиции нет, честно возвращается пустой список, а не фиктивный выбор.

## Маршруты

| Адрес | Что открывает |
|-------|----------------|
| `/procurement` | доска: заявки к решению, заказы в работе, приёмки, дефицит |
| `/procurement/low-stock` | дефицит склада: что на минимуме и где уже есть заявка |
| `/procurement/auto-requests` | создать заявки по минимуму склада (POST) |
| `/procurement/requests` | заявки с фильтрами (состояние, источник, просрочка, текст) |
| `/procurement/requests/view/<id>` | карточка заявки: позиции, итог, кнопки переходов |
| `/procurement/requests/edit(/<id>)`, `/save` | форма заявки |
| `/procurement/requests/act/<submit\|approve\|reject\|to_order\|cancel>/<id>` | переходы |
| `/procurement/requests/line/<add\|remove>(/<id>)` | позиции заявки |
| `/procurement/orders` | заказы с фильтрами (состояние, поставщик, просрочка) |
| `/procurement/orders/view/<id>` | карточка: позиции, прогресс приёмки, приёмки, кнопки |
| `/procurement/orders/edit(/<id>)`, `/save` | форма заказа |
| `/procurement/orders/act/<send\|confirm\|cancel\|receipt>/<id>` | переходы |
| `/procurement/receipts` | приёмки с фильтрами |
| `/procurement/receipts/view/<id>` | карточка: позиции, входной контроль, проведение |
| `/procurement/receipts/act/<post\|cancel\|quality>/<id>` | переходы |
| `/procurement/suppliers` | поставщики: условия, оценки, «где цены расходятся» |
| `/procurement/suppliers/view/<id>` | карточка: условия, заказы, история цен |
| `/procurement/suppliers/compare?item_id=…&qty=…` | у кого брать позицию |
| `/procurement/api/<action>` | JSON API (см. ниже) |
| `/procurement/widget/<action>(/<id>)` | виджеты: `summary`, `item_suppliers`, `order_progress` |

JSON API (`/procurement/api/…`) повторяет экраны (24 действия).

Чтение: `stats`, `requests`, `request`, `orders`, `order`, `receipts`,
`receipt`, `suppliers`, `supplier`, `supplier_compare`, `prices`, `low_stock`.

Изменения (нужны права `erp403.manage`, а на утверждение и проведение —
`erp403.approve`): `request_create`, `request_update`, `request_act`,
`request_line_add`, `auto_requests`, `order_create`, `order_act`,
`order_line_add`, `receipt_create`, `receipt_act`, `supplier_save`,
`price_record`. Ответы — `{"success": true, "data": …}`,
ошибки — `{"success": false, "error": {"message", "reason"}}`; `reason`
машинно-читаем: `not_found`, `invalid_transition`, `empty_document`,
`dependency`, `quality_blocked`, `already_posted`, `supplier_required`,
`duplicate_supplier`.

## Права

| Право | Кому | Что разрешает |
|-------|------|----------------|
| `erp403.view` | admin, manager, accountant | заявки, заказы, приёмки, поставщики, цены, дефицит |
| `erp403.manage` | admin, manager | черновики, позиции, отправка заказа, условия поставщика |
| `erp403.approve` | admin, manager | согласование заявок и проведение приёмки на склад |

Права объявлены в `application/config/acl.php`; модуль зарегистрирован в
`application/bootstrap.php` (до миграций — иначе `db:migrate` не увидит таблицы).

## Инструменты агента (MCP)

Чтение (`erp403.view`): `procurement.stats`, `procurement.requests.search`,
`procurement.request.get`, `procurement.orders.search`, `procurement.order.get`,
`procurement.receipts.search`, `procurement.receipt.get`,
`procurement.suppliers.search`, `procurement.suppliers.compare`,
`procurement.prices.history`, `procurement.shortages`.

Подготовка документов (`erp403.manage`): `procurement.request.create`,
`procurement.request.line.add`, `procurement.request.update`,
`procurement.request.submit`, `procurement.auto_requests`,
`procurement.order.create`, `procurement.order.line.add`,
`procurement.order.confirm`, `procurement.receipt.create`,
`procurement.receipt.quality`, `procurement.supplier.save`,
`procurement.price.record`.

Решения (`erp403.approve`): `procurement.request.approve`,
`procurement.request.reject`, `procurement.receipt.post`.

Подтверждение человека (`approval`) требуют шаги, которые нельзя отыграть
назад: `procurement.order.send` (заказ уходит поставщику),
`procurement.order.cancel`, `procurement.receipt.post` (движение товара и
денег), `procurement.receipt.cancel`, `procurement.request.approve`,
`procurement.request.reject`, `procurement.auto_requests` (пакет документов).
Логика простая: агент считает, готовит и показывает — решение принимает
человек.

## События

`erp403.request.created|updated|submitted|approved|rejected|ordered|cancelled|auto_created`,
`erp403.orders.collected`, `erp403.order.created|updated|sent|confirmed|cancelled|received`,
`erp403.receipt.created|quality|posted|cancelled`,
`erp403.price.recorded`, `erp403.supplier.updated`.
Все события уходят в шину erp208 с человекочитаемым `title`, поэтому их можно
подписывать на оповещения (erp217) и журнал (erp119) без доработки модуля.
Каждый переход документа пишется в журнал действий (erp119) с актором.

## Проверка и данные

```
bin/console db:migrate                 # применить миграции (в т.ч. erp403)
bin/test --filter=Erp403               # тесты модуля
bin/console dev:smoke                  # все маршруты, включая /procurement/*
bin/console dev:audit                  # включая проверки MCP, маршрутов и представлений
```

Тесты (`tests/Unit/Erp403ProcurementTest.php`) работают на своих данных
(`ЗК-ТЕСТ-403-*`, `ЗП-ТЕСТ-403-*`, `ПР-ТЕСТ-403-*`, склад `ТЕСТ-403`) и убирают
их за собой; если таблиц нет — пропускаются.

## Ограничения и что дальше

* Валюта хранится у документа, курсов нет: закупка в валюте — это отметка, а не
  пересчёт в рубли.
* Входной контроль — отметка по позиции приёмки; реальные измерения, партии и
  сертификаты — задача erp405_quality.
* Договоры поставщиков (`contract_id`) — мягкая ссылка: модуль договоров
  (erp404_documents) появится позже; сейчас поле есть, но не заполняется.
* Себестоимость материалов считается в erp401/erp409, закупка передаёт только
  количество и цену.
* Планирование партий и кратности закупки — по атрибутам номенклатуры в erp407,
  не в закупке (см. `docs/MODULE_BACKLOG.md`).
