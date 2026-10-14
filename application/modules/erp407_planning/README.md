# erp407_planning — планирование потребностей (MRP-минимум)

Отвечает на вопрос «что, сколько и когда закупить или выпустить». Модуль не
заводит свои справочники и заказы: он берёт спрос (программу и заказы
производства), состав (техкарты erp228), покрытие (склад erp401, незавершёнку
erp402) и выдаёт дефициты с датами заказа, а решения оформляет предложениями,
которые уходят в исполнение в соседние модули.

Цепочка производства, в которой модуль занимает место планировщика:

```
техкарты → ПЗ → цех → [планирование] → закупки → качество → документы → себестоимость
```

## Состав модуля

| Слой | Файлы | Что делает |
|------|-------|------------|
| Данные | `migrations_pg/pgMigration_001_*` | 4 таблицы: `erp_407_programs`, `erp_407_program_lines`, `erp_407_requirements`, `erp_407_proposals` + последовательности номеров |
| Модели | `classes/Model/Erp407/Program.php`, `ProgramLine.php`, `Requirement.php`, `Proposal.php` | состояния, подписи и цвета, `is_editable()`, `coverage()`, `days_to_order()` |
| Сервисы | `classes/Service/Erp407/Programs.php` | программы и строки: создание, правка, утверждение, закрытие, сбор из заказов, нумерация |
| | `classes/Service/Erp407/Mrp.php` | расчёт потребности: разузлование состава, покрытие, дефицит, дата заказа, снимок результата |
| | `classes/Service/Erp407/Proposals.php` | предложения: создание из расчёта, утверждение, передача в исполнение, закрытие |
| Экраны | `classes/Controller/Erp407/{Index,Program,Proposal,Api,Widget}.php` + `views/erp407/*` | доска, дефициты, программы, предложения, JSON API, виджеты |
| Агент | `mcp.php` | 14 инструментов MCP: чтение плана, расчёт, предложения, передача в исполнение |
| Переходы | `init.php` | маршруты `/planning/*` и описание событий `erp407.*` |

## Как это работает

1. **Программа** (`draft`) — это спрос: строки «изделие × количество × срок».
   Строки можно ввести руками, собрать из открытых заказов производства
   (`fill_from_orders`) или позже — из ПЗ. Номер программы выдаёт
   последовательность: `ПП-ГГГГ-НННННН`.
2. **Утверждение** (`approved`) фиксирует план: утвердить пустую программу
   нельзя, а расчёт по закрытой программе запрещён.
3. **Расчёт** (`erp407.mrp.calculated`) идёт по строкам:
   * изделие с техкартой разузловывается по составу (`Service_Erp402_Bom`) до
     3 уровней;
   * покрытие = остаток склада (`Service_Erp401_Stock`) + незавершёнка
     (заказы erp402 в работе) + уже размещённые предложения (`approved`,
     `exported`);
   * дефицит = спрос − покрытие, количество заказа округляется по атрибутам
     номенклатуры (`min_order_qty`, `order_multiple`, `round_to`);
   * дата заказа = срок − срок поставки (`lead_time_days`,
     `purchase_lead_time_days`/`production_lead_time_days`, по умолчанию 7 дней
     для закупки и 5 для выпуска); позиции с прошедшей датой помечаются `late`.
   Результат сохраняется снимком с `run_uuid`, поэтому перерасчёт не стирает
   историю, а экраны показывают именно последний прогон.
4. **Предложения** создаются из дефицитов одной кнопкой
   (`Proposals::create_from_requirements`): по каждой позиции не больше одного
   открытого предложения, повторный запуск не задваивает. Номер — `ПЗ-ГГГГ-НННННН`.
5. **Исполнение**: утверждённое предложение передаётся в работу и запоминает
   ссылку `target_ref`:
   * `produce` → заказ производства `erp402:<id>` (черновик: состав и маршрут
     цеха появляются при обычном запуске заказа);
   * `purchase` → заявка `erp403:<id>`, когда модуль закупок подключён; пока его
     нет — `waiting:erp403` и предупреждение в ответе, то есть очередь видна, а
     система не делает вид, что заявка создана.

## Маршруты

| Адрес | Что открывает |
|-------|----------------|
| `/planning` | доска: последний расчёт, дефициты, предложения без решения |
| `/planning/deficits` | дефициты с фильтрами по программе и способу обеспечения |
| `/planning/programs` | программы с фильтрами и состоянием |
| `/planning/programs/view/<id>` | карточка: строки, потребность, предложения, кнопки действий |
| `/planning/programs/edit(/<id>)` | форма программы |
| `/planning/programs/calculate/<id>` | запуск расчёта потребности |
| `/planning/programs/fill/<id>` | собрать строки из открытых заказов |
| `/planning/programs/act/<approve\|close\|delete>/<id>` | переходы состояния программы |
| `/planning/programs/line/<add\|remove>(/<id>)` | строки программы |
| `/planning/proposals` | предложения: очередь решений и история передачи |
| `/planning/proposals/create`, `act/<approve\|export\|complete\|cancel>/<id>` | создание из дефицитов и переходы |
| `/planning/api/<action>` | JSON API (см. ниже) |
| `/planning/widget/<action>` | виджеты для дашбордов и карточек |

JSON API (`/planning/api/…`) повторяет экраны: `stats`, `programs`, `program`,
`program_create`, `program_update`, `program_line_add`, `program_act`,
`calculate`, `calculate_orders`, `requirements`, `proposals`,
`proposals_create`, `proposal_act`. Ответы — `{"success": true, "data": …}`,
ошибки — `{"success": false, "error": {"message", "reason"}}`, где `reason`
машинно-читаем (`not_found`, `invalid_transition`, `no_demand`, `dependency`,
`proposal_locked`, `export_failed`).

## Права

| Право | Кому | Что разрешает |
|-------|------|----------------|
| `erp407.view` | admin, manager, accountant | программы, расчёт, дефициты, предложения |
| `erp407.manage` | admin, manager | правка программ, строк и предложений |
| `erp407.plan` | admin, manager | расчёт, утверждение, создание предложений |
| `erp407.approve` | admin, manager | передача предложений в исполнение |

Права объявлены в `application/config/acl.php`; модуль зарегистрирован в
`application/bootstrap.php` (до миграций — иначе `db:migrate` не увидит таблицы).

## Инструменты агента (MCP)

Чтение (`erp407.view`): `planning.stats`, `planning.programs.search`,
`planning.program.get`, `planning.deficits`, `planning.proposals.search`.

Планирование (`erp407.plan`): `planning.program.create`,
`planning.program.line.add` (`erp407.manage`), `planning.mrp.calculate`,
`planning.proposals.create`, `planning.proposal.create` (`erp407.manage`),
`planning.proposal.approve`, `planning.proposal.cancel`.

Деструктивные и порождающие действия требуют подтверждения человека
(`approval`): `planning.program.approve`, `planning.proposal.export`,
`planning.proposal.cancel`. Логика простая: агент считает, предлагает и
показывает — заказ в производстве или заявку в закупке порождает человек.

## События

`erp407.program.created|updated|approved|closed|deleted`,
`erp407.mrp.calculated`, `erp407.proposal.created|approved|exported|done|cancelled`.
Все события уходят в шину erp208 с человекочитаемым `title`, поэтому их можно
подписывать на оповещения (erp217) и журнал (erp119) без доработки модуля.

## Проверка и данные

```
bin/console db:migrate                 # применить миграции (в т.ч. erp407)
bin/test --filter=Erp407               # 12 тестов модуля
bin/console dev:smoke                  # все маршруты, включая /planning/*
bin/console dev:audit                  # включая проверку MCP и маршрутов
```

Тесты (`tests/Unit/Erp407PlanningTest.php`) работают на своих данных
(`ПП-ТЕСТ-407-*`, `ПО-ТЕСТ-407-*`) и убирают их за собой; если таблиц нет —
пропускаются.

## Ограничения и что дальше

* Расчёт — MRP-минимум: один склад покрытия за раз, не более 3 уровней состава,
  без выравнивания загрузки рабочих центров по календарю.
* Реального модуля закупок (erp403) пока нет: предложения типа `purchase`
  встают в очередь `waiting:erp403` и передаются, когда модуль появится.
* План не учитывает партии и кратность закупки глубже, чем атрибуты
  номенклатуры (`min_order_qty`, `order_multiple`, `round_to`).
* Прогноз спроса, сезонность и оптимизация по стоимости — вне текущего объёма
  (см. `docs/MODULE_BACKLOG.md`).
