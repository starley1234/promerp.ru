# erp301_counterparty — контрагенты (Master Data)

Модуль ведёт «коммерческую сторону» производства: кто поставляет материалы и
комплектующие, кто покупает готовую продукцию, кто перевозит и кто выполняет
работы. Модуль **не владеет** номенклатурой (реестр `erp227`), файлами
(`erp212`) и не считает себестоимость — он источник справочных данных для
закупок, производства, отгрузки и документов.

## Возможности

| Раздел | Что умеет |
| --- | --- |
| Сводка `/counterparties` | количество по ролям и статусам, договоры, истекающие в 30 дней |
| Список `/counterparties/list` | поиск по наименованию/коду/ИНН, фильтры по роли и статусу, постраничный вывод |
| Карточка `/counterparties/view/<id>` | общие сведения, реквизиты для документов, контактные лица, договоры |
| Форма `/counterparties/edit/<id>` | создание и правка контрагента, роли (поставщик/покупатель/перевозчик/подрядчик) |
| Договоры `/counterparties/contracts` | список с фильтрами; создание из карточки, расторжение |
| API `/counterparties/api/*` | `search`, `suggest`, `card` — JSON для других модулей и автодополнения |

Экспорт/импорт Excel — следующий шаг (см. «Чего пока нет»).

## Структура

```
classes/Controller/Erp301/{Main,Index,Counterparty,Contract,Api}.php
classes/Model/Erp301/{Counterparty,Contact,Requisite,Contract}.php
classes/Service/Erp301/Counterparties.php
classes/Task/Erp301/Contracts/Actualize.php
views/erp301/{index,counterparty,contract,partials}/
migrations_pg/pgMigration_001_Erp301_Counterparty_CreateTables.php
migrations_pg/pgMigration_002_Erp301_Counterparty_Seed.php
migrations_pg/pgMigration_003_Erp301_Counterparty_SearchText.php
```

Таблицы: `erp_301_counterparties`, `erp_301_contacts`, `erp_301_requisites`,
`erp_301_contracts`. Договоры ссылаются на скан через `file_id` →
`erp_212_files` (модуль файлы не дублирует).

## Правила модуля

1. **Запись только через сервис** `Service_Erp301_Counterparties` — контроллеры
   тонкие, задачи вызывают те же методы.
2. **Мягкое удаление** (`is_deleted`): контрагент уходит в архив вместе с
   контактами, реквизитами и договорами, история сохраняется.
3. **Уникальность** проверяется моделью до записи: код (всегда), ИНН (среди
   неудалённых), номер договора (внутри контрагента).
4. **События**: `erp301.counterparty.{created,updated,deleted}`,
   `erp301.contract.{created,updated,terminated}` — подписчики в других модулях
   получают обновление без прямых вызовов.
5. **Один основной контакт** и признак «основные реквизиты» на контрагента.
6. **Поиск — по колонке `search_text`** (миграция 003): кластер PostgreSQL создан
   с локалью `C`, поэтому `ILIKE` и `lower()` не складывают регистр кириллицы.
   Значение собирается в PHP (`mb_strtolower`) из наименования, полного имени,
   кода и ИНН и обновляется вместе с записью; запрос — `LIKE '%терм%'`.

## Задачи

```bash
bin/console erp301:contracts:actualize             # истёкшие договоры → expired
bin/console erp301:contracts:actualize --dry-run   # только показать
bin/console erp301:contracts:actualize --notice    # + напоминания (30 дней)
```

Расписание: `0 6 * * * cd /srv/promerp && bin/console erp301:contracts:actualize --notice`

## API

```bash
GET /counterparties/api/search?term=ромашка&role=supplier&status=active&limit=25
GET /counterparties/api/suggest?term=ром
GET /counterparties/api/card/12
```

Ответ: `{"status":"ok","total":N,"data":[…]}`; при ошибке —
`{"status":"error","message":"…"}` с HTTP-кодом 400/403/404.

## Тесты

`tests/Unit/Erp301CounterpartyTest.php` — 20 тестов сервиса на реальной БД:
создание/правка/архивирование, уникальность кода и ИНН, валидация ИНН,
форматирование ролей, поиск и фильтры, контакты (единственный основной),
реквизиты (проверка БИК и счёта), договоры (уникальный номер, период, суммы,
расторжение, актуализация статусов), события.

```bash
bin/test --filter=Erp301
```

Если таблицы модуля отсутствуют, тесты пропускаются — сначала примените
миграции: `bin/console db:migrate --module=erp301_counterparty`.

## Чего пока нет (следующие шаги)

* Импорт контрагентов из Excel и проверка ИНН по внешним сервисам.
* Загрузка сканов договоров в `erp212` прямо из карточки (поле `file_id` уже есть).
* История изменений в интерфейсе (события пишутся, но отдельного журнала нет).
* Роли контрагента в отдельной таблице, если потребуется N-сторонняя типизация.
