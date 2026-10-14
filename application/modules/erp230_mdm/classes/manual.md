# Manual: организация добавления новых элементов в MDM (erp230_mdm)

Документ описывает, как вводить новые мастер-записи (объекты), как формировать master_code, как не плодить дубли, нужны ли карты данных (типы), что держать в модуле, а что вынести, и пофайловый план изменений.

Версия контекста: v5.3 / код сервисов 1.1.x–2.4.x.

---

## 1. Цель и границы

Добавление элемента = появление (или нахождение) одной «золотой записи» в `objects` + набор свойств в EAV (`object_properties`) + при необходимости xref на внешнюю систему + актуализация `object_state`.

Каналы ввода:

1. UI: `Controller_Erp230_Objects` (ручной ввод, source_id = `manual`).
2. REST API: `POST /object`, `POST /properties/<id>`, `POST /consolidate`.
3. Интеграция: `Service_Erp230_Consolidation::consolidate()` (PLM/ERP/MES и т.д.).

Все каналы должны сходиться к одним правилам: уникальность бизнес-ключа, типы/схемы, Trust Score, жизненный цикл Draft → Active.

---

## 2. Карта процесса «новый элемент»

### 2.1. Перед созданием (обязательный чеклист)

1. Определить тип (`type_id`) из дерева `types` (ltree). Тип задаёт JSON Schema обязательных/рекомендуемых атрибутов.
2. Определить организацию (`org_id` / `org_path`) — от неё зависят RLS и область уникальности кода.
3. Сформировать или получить `master_code` (см. §3).
4. Проверить уникальность: `GET /mdm/codes/check?master_code=&type_id=&org_id=` (уже дергается из `views/erp230/objects/form.php`).
5. Проверить дубли по смыслу: текстовый/семантический поиск, xref (`GET /resolve?source=&remote=`), при ручном вводе — поиск по name/артикулу в реестре.
6. Минимальный набор свойств по схеме типа (как минимум `name`).
7. Создать объект в `draft`, наполнить свойства через `upsert_safe` / `upsert_property`, провалидировать, перевести в `active` через `transition_to` (не через «голое» UPDATE state в форме edit).

### 2.2. Два сценария создания

**A. Ручной (UI/API object create)**  
- Создаётся строка в `objects` (state=draft).  
- Свойства пишутся источником `manual` (trust=100).  
- Код проверяется до submit и ещё раз на уровне БД/модели.

**B. Интеграционный (consolidate)**  
Уже реализован в `Service_Erp230_Consolidation`:

1. Найти xref (source_id + remote_id).  
2. Иначе найти по `(master_code, type_id, org_id)` среди не удалённых.  
3. Иначе create draft + INSERT в `object_xref`.  
4. Если state=merged — перейти на `merged_into`.  
5. Пакетный `upsert_safe` по properties.  
6. При изменениях — `refresh_object_state`, mark_for_reindex, event.

Правило: внешние системы не должны обходить consolidate «сырым» INSERT в objects, иначе появятся дубли без xref.

### 2.3. После создания

- Draft виден ограниченно; в глобальном поиске/BOM как эталон не использовать.  
- Draft → Active: валидация схемы типа, UOM, обязательных полей; автоматический первый baseline (по бизнес-логике §4 документации).  
- Дальнейшие правки атрибутов — только через Trust Score, не прямым UPDATE EAV.

---

## 3. Master-code: как создавать и кто владеет правилом

### 3.1. Смысл

`master_code` — человекочитаемый бизнес-ключ внутри области `(type_id, org_id)` (и при необходимости более узкого контура). UUID `objects.id` — суррогат; потребителям и поиску дублей нужен master_code + type + org.

Реестр: API Codes (`Controller_Erp230_Api_Codes`) — check / resolve / list. В UI форма уже вызывает check при blur/change.

### 3.2. Стратегии генерации (рекомендуемый порядок)

| Стратегия | Когда | Плюсы | Минусы |
|-----------|--------|-------|--------|
| Внешний канонический код (PLM/обозначение) | Есть ведущая PLM | Совпадает с конструкторским обозначением | Нужна нормализация и маппинг |
| Шаблон по типу | Ручной ввод, нет внешнего кода | Предсказуемость | Нужен счётчик/серия на org+type |
| Временный код + замена | Черновик «с колен» | Не блокирует ввод | Смена кода только пока draft / под контролем admin |

Рекомендация для модуля:

1. Ввести **политику кода на типе** (в `types.schema` или отдельном поле метаданных типа), например:  
   `code_policy`: `{ "pattern": "MAT-{ORG}-{SEQ:6}", "source": "auto|external|manual", "normalize": "upper_trim" }`.  
2. Нормализация до проверки уникальности: trim, upper (или единый Unicode NFKC), схлопывание пробелов, запрет кириллицы/латиницы вперемешку по правилам НСИ.  
3. Автонумерация — **одна** точка в БД (sequence/таблица `code_series`), не в PHP «на глаз».  
4. Резервирование кода при создании draft (как в документации §2.1 ЖЦ) — чтобы два редактора не взяли один номер.

### 3.3. Чего не делать

- Не генерировать master_code из UUID.  
- Не делать глобально уникальный код «на всю базу», если бизнес-уникальность — в рамках org+type (иначе ложные отказы между ДО).  
- Не менять master_code у Active без процедуры (извещение / baseline / admin): ломаются xref потребителей.  
- Не полагаться только на JS-check в форме — только UX; истина в БД unique/constraint + `Model_Erp230_Code`.

### 3.4. Минимальная реализация автокода (куда класть)

- **В модуле:** `Service_Erp230_CodeGenerator` (новый) + использование из Objects UI/API и опционально из Consolidation, если payload без master_code и policy=auto.  
- **В БД (вне «тонкого» PHP, но в миграциях модуля):** функция/таблица серий, unique index на `(master_code, type_id, org_id) WHERE deleted_at IS NULL AND state <> 'merged'`.  
- **Не выносить** генерацию в каждый коннектор PLM/ERP — коннектор либо присылает свой код, либо вызывает API consolidate/create.

---

## 4. Как избежать дублирования при вводе

Дубль = две золотые записи об одном физическом/учётном объекте. Лечится профилактикой на входе и процедурой merge на выходе.

### 4.1. Слои защиты (все нужны)

**Слой 1 — жёсткий ключ**  
Уникальность `(master_code, type_id, org_id)` для живых записей.  
API: `codes/check` до create; модель/БД — при save.

**Слой 2 — внешний ключ (xref)**  
`object_xref (source_id, remote_id) → object_id`.  
Повторная загрузка из PLM/ERP всегда попадаёт в ту же запись (логика consolidate).  
Никогда не создавать второй объект, если xref уже есть (в т.ч. на merged — редирект на survivor).

**Слой 3 — натуральные ключи в свойствах**  
В схеме типа объявить `identity_keys` (например `gost`, `draw_no`, `manufacturer_pn`):  
при create/consolidate искать кандидатов по `object_state` (GIN) в той же org/type.  
При совпадении — не создавать тихо новый, а вернуть 409 / UI «возможный дубль» с ссылками.

**Слой 4 — мягкий поиск**  
- Полнотекст: `object_state.search` (уже в фильтрах GoldenRecord).  
- Семантика: embeddings + `Model_Erp230_Search` (кнопка/шаг мастера «Похожие»).  
Порог: для блокировки не использовать семантику alone; только предупреждение + steward.

**Слой 5 — merge**  
`POST /object/<id>/merge` и state=merged, `merged_into`.  
Свойства/связи/xref переносятся на победителя; проигравший терминален.  
UI создания не должен позволять «просто завести ещё один» после предупреждения без роли steward/admin.

### 4.2. Правила для оператора (UI)

1. Сначала поиск в реестре (код, имя, семантический).  
2. Выбор типа и org → ввод кода → дождаться check.  
3. Если код занят — открыть существующую карточку, не плодить.  
4. Если код свободен, но найден похожий по name/чертежу — показать панель кандидатов; создание только с явным confirm.  
5. После создания — наполнение по схеме; публикация в Active отдельно.

### 4.3. Правила для интеграций

1. Только `consolidate` или эквивалент с тем же order of lookup: xref → business key → create.  
2. Стабильные source_id в справочнике `sources` с адекватным trust.  
3. Идемпотентность: повтор того же payload не создаёт второй object.  
4. При конфликте master_code с другим xref — инцидент в очередь steward, не silent second record.

### 4.4. Что добавить в код для антидублей

Сейчас: consolidate (xref + master_code), UI check кода, merge API (по README).  
Пробелы: нет единого pre-create «duplicate guard» с identity_keys и похожими; create в UI идёт в `$object->save()` без сервиса; нет серверного enforce reserve code.

Нужен единый сервис входа: `Service_Erp230_ObjectFactory` (create_manual / create_from_payload), который вызывают и UI, и API.

---

## 5. Как упростить добавление новых элементов

### 5.1. Мастер создания (wizard), не одна плоская форма

Шаги:

1. Тип (с подсказкой по дереву types).  
2. Организация (по умолчанию — org пользователя из RLS-контекста).  
3. Поиск «уже существует?» (q + semantic + identity).  
4. Код (auto по policy или ручной + live check).  
5. Атрибуты **по схеме типа** (обязательные поля сразу, не только свободные attr_key[]).  
6. Черновик сохранён → опционально «Отправить на публикацию».

Форма `views/erp230/objects/form.php` уже имеет datalist по schema и check кода — развивать её в schema-driven fields, а не в бесконечные ручные ключи.

### 5.2. Schema-driven UI

- `types.schema` (JSON Schema) — источник правды для полей, required, enum, uom.  
- При смене type_id — AJAX `GET /mdm/type/schema/<id>` и перерисовка полей.  
- Ключ `name` — обязателен и закреплён (уже заложен в form).  
- Единицы — select из dictionary uom, не свободный текст где возможно.

### 5.3. Шаблоны и копирование

- «Создать от образца»: copy type/org + набор ключей без значений или с безопасными дефолтами.  
- Не копировать master_code и xref.

### 5.4. Пакетная загрузка

- Отдельный use-case: CSV/JSON → очередь → consolidate/factory построчно.  
- Не в UI-контроллере Objects; сервис + job (можно вне модуля — §7).  
- Отчёт: created / linked / rejected_low_trust / duplicate_suspect.

### 5.5. Значения по умолчанию и org

- `org_id` по умолчанию = org редактора.  
- state при create всегда `draft` на сервере (игнорировать попытки POST state=active из формы create без права publish).  
- source для UI всегда `manual`.

### 5.6. Один write-path для свойств

UI сейчас: `Controller_Erp230_Objects::_save_attributes` → `upsert_safe`.  
GoldenRecord: `update_attribute` → `upsert_safe` + refresh + event.  
Упрощение: UI/API properties делегируют в `Service_Erp230_GoldenRecord::update_attribute` (или batch-метод), чтобы refresh/audit/event не расходились. Consolidation может вызывать batch на том же слое.

---

## 6. Карты данных (типы элементов): нужно ли, как и где хранить

### 6.1. Нужно ли

**Да, обязательно.** Без типов MDM превращается в свалку ключей: нельзя валидировать обязательность, строить формы, задавать policy кода, фильтровать реестр по иерархии, наследовать правила.

Тип = класс объекта НСИ (деталь, сборка, материал, инструмент, документ…), не «каждый артикул».

### 6.2. Где хранить

| Что | Где | Комментарий |
|-----|-----|-------------|
| Идентификатор типа, дерево, display_name | Таблица `types` (ltree `path`) | Уже есть; UI `erp230_types`, API Type |
| Карта атрибутов, required, типы значений, enum | `types.schema` (JSON Schema) | Редактор: `views/erp230/types/card.php` |
| Политика master_code, identity_keys, ui hints | Расширение schema (`x-mdm`) или jsonb `types.meta` | Предпочтительно не плодить колонки без нужды |
| Иерархия «все материалы» | path ltree | Фильтры registry уже используют `<@` |
| Версионирование схемы | Опционально baselines/meta вне объекта | Менять schema осторожно: новые required не должны ломать старые Active без миграции данных |

### 6.3. Как проектировать schema

Минимальный каркас:

```json
{
  "type": "object",
  "required": ["name"],
  "properties": {
    "name": { "type": "string", "title": "Наименование", "minLength": 1 },
    "draw_no": { "type": "string", "title": "Обозначение" },
    "mass": { "type": "number", "title": "Масса", "x-uom": "kg" }
  },
  "x-mdm": {
    "identity_keys": ["draw_no"],
    "code_policy": { "mode": "manual", "normalize": "upper_trim" },
    "display_name_keys": ["name"]
  }
}
```

Правила:

- Ключи свойств — `snake_case`, regex как в GoldenRecord: `^[a-z][a-z0-9_.]*$`.  
- Не дублировать master_code/type_id/org_id как EAV без причины.  
- Общие атрибуты — на родительском типе; уточнения — на дочернем (документировать merge schema: child overrides).  
- Тяжелую отраслевую классификацию (ОКПД2 и т.д.) можно держать как значение свойства или связь, но справочник кодов классификатора — в dictionary, не копипаста в каждый schema.

### 6.4. Управление типами — в модуле

CRUD типов, просмотр дерева, редактирование schema — часть erp230_mdm (уже API Type + views types).  
Не выносить «карту данных» в Excel без обратного импорта в `types`.  
Права: admin/steward на изменение schema; editor — только объекты.

---

## 7. Что делать в модуле erp230_mdm, а что вынести

### 7.1. Оставить / развивать внутри модуля

- Модель данных MDM: objects, properties, object_state, types, org_units, sources, xref, links, baselines, codes.  
- Trust Score, upsert, golden record, merge, lifecycle transition_to.  
- RLS-контекст (установка app.current_user_id / app.role при логине — хук приложения, политики — в БД модуля).  
- REST API MDM и UI реестра/карточки/типов/BOM.  
- Сервисы: Consolidation, GoldenRecord, (новые) ObjectFactory, CodeGenerator, DuplicateGuard.  
- Валидация по types.schema при create/publish.  
- Снимки и verify chain.

### 7.2. Вынести за пределы модуля (интеграции и платформа)

- Коннекторы к конкретным PLM/ERP/MES (маппинг их XML/JSON → payload consolidate): отдельные модули или middleware, зависимость **только** от API/Service consolidate.  
- Очереди, retry, ETL-оркестрация (Rabbit/Cron/Kubernetes jobs).  
- LLM/эмбеддинги: вызов платформенного `Llm::get_embedding`; модуль только хранит vectors и ищет.  
- Печать/отчёты/портал поставщика.  
- Глобальный IAM: пользователи, SSO; модуль потребляет user_id, role, org_path.  
- Хранение бинарников чертежей (S3/files) — в MDM только ссылки-свойства или отдельные entity, если появятся.

### 7.3. Граница «тонкий контроллер»

Как уже сделано в Objects/Structure: контроллер — права, PRG, view; домен — service/model.  
Новые правила создания/дублей **не** добавлять копипастой в `action_create` и в Api — только в сервис.

---

## 8. Целевой write-path (канон)

```
UI / API / Connector
        │
        ▼
Service_Erp230_ObjectFactory
   ├─ normalize master_code
   ├─ CodeGenerator (если auto)
   ├─ DuplicateGuard (codes, xref, identity_keys, optional semantic)
   ├─ create objects (draft) | return existing
   ├─ xref if needed
   └─ batch properties → GoldenRecord/Property::upsert_safe
        │
        ▼
refresh_object_state → (optional) reindex
        │
        ▼
transition_to(active) → validation schema → baseline
```

Consolidation должен либо вызывать Factory, либо разделить: Factory = create/find, Consolidation = find + upsert (рефакторинг без изменения внешнего контракта API).

---

## 9. Пофайловый план: создать / изменить / не трогать

Пути относительно `application/modules/erp230_mdm/`.

### 9.1. Создать

**classes/Service/Erp230/ObjectFactory.php**  
- `create(array $payload, array $options): Model_Erp230_Object`  
- options: `source_id`, `remote_id`, `allow_existing`, `actor_id`  
- Транзакция, normalize, guard, create, optional xref, batch attributes  
- Единая точка для UI create и API POST /object  

**classes/Service/Erp230/CodeGenerator.php**  
- `normalize($code, $policy)`  
- `allocate($type_id, $org_id, $policy): string`  
- `validate_pattern($code, $policy)`  
- Чтение policy из type schema/meta  

**classes/Service/Erp230/DuplicateGuard.php**  
- `assert_unique_code(...)`  
- `find_by_xref / find_by_code`  
- `find_by_identity_keys(type_id, org_id, properties)`  
- `suggest_similar(q, type_id, org_id)` — обёртка search  
- Возврат структурированного результата для UI (block | warn | ok)  

**classes/Service/Erp230/SchemaValidator.php**  
- Валидация properties против `types.schema` (required, types, enums)  
- Использовать при publish и опционально при create  
- Не дублировать JSON Schema engine в каждом контроллере  

**classes/Model/Erp230/CodeSeries.php** (если серии в ORM)  
- Либо тонкая обёртка над SQL `next_code(type_id, org_id)`  

**migrations/** (или ваш каталог SQL модуля)  
- Unique index на живые коды  
- Таблица/sequence `code_series`  
- При необходимости: `types.meta jsonb`  
- Функция reserve/next master_code  

**views/erp230/objects/create_wizard.php** (или поэтапно развивать form.php)  
- Шаг поиска дублей, schema-fields, confirm suspects  

**views/erp230/objects/partials/duplicate_panel.php**  
- Список кандидатов (code, name, link to view)  

**views/erp230/objects/partials/schema_fields.php**  
- Рендер input’ов из schema.properties  

**documentation/06_добавление_элементов_и_антидубли.md**  
- Краткая выжимка этого manual для команды  

**tests/** (если приняты в проекте)  
- Factory: create + second create same code → same id / exception  
- consolidate idempotency  
- guard identity_keys  

### 9.2. Изменить

**classes/Controller/Erp230/Objects.php**  
- `action_create`: не голый `ORM::factory->values->save`; вызов `ObjectFactory::create` с properties из post  
- Перед save — обработка результата DuplicateGuard (ошибки в form / redirect)  
- `_save_attributes`: делегировать batch в GoldenRecord (новый метод `update_attributes_batch`)  
- `action_create` GET: отдавать default org, пустой schema; при type_id в query — schema  
- Опционально endpoint/action `action_check_duplicates` JSON для мастера  
- state на create принудительно draft  

**classes/Controller/Erp230/Api.php** (и связанные Api_* по факту дерева)  
- POST /object → ObjectFactory  
- POST /consolidate → как сейчас, но find/create через Factory  
- GET codes/check — без изменения контракта; внутри Code + Guard  

**classes/Service/Erp230/Consolidation.php**  
- Заменить блок «find by code or create + xref» на Factory  
- Оставить trust-цикл properties; refresh/reindex  
- Явная обработка «код занят другим объектом без этого xref»  

**classes/Service/Erp230/GoldenRecord.php**  
- Добавить `update_attributes_batch($object_id, array $props, $source_id)`  
- `update_attribute` использовать из batch  
- Метод `validate_for_publish($object_id)` → SchemaValidator  

**classes/Model/Erp230/Object.php** (по README: transition_to, display_name, registry)  
- В `transition_to('active')`: SchemaValidator + first baseline через Consolidation::create_snapshot  
- filters/create rules: запрет смены master_code в active без admin (rules/filters ORM)  
- Уникальность кода — в rules() callback через Model_Erp230_Code  

**classes/Model/Erp230/Code.php**  
- Расширить code_exists/get_object_id с учётом exclude object_id (для edit)  
- Метод normalize  

**classes/Model/Erp230/Type.php**  
- Хелперы: `schema_array()`, `code_policy()`, `identity_keys()`, `merged_schema()` если наследование  

**classes/Controller/Erp230/Api/Type.php** (или Type controller)  
- Отдача x-mdm вместе со schema  
- При POST/PATCH schema — валидация что JSON Schema parseable  

**classes/Controller/Erp230/Types.php** (UI типов, если есть)  
- Поля/подсказки для identity_keys и code_policy (хотя бы через JSON как сейчас + документация в card)  

**views/erp230/types/card.php**  
- Подсказка по x-mdm (identity_keys, code_policy) в footer JSON editor  
- Не обязательно GUI для x-mdm на первом шаге  

**views/erp230/objects/form.php**  
- При смене type_id — fetch schema и перестройка обязательных полей  
- Блок «возможные дубли» перед submit  
- Кнопка «сгенерировать код» если policy=auto  
- checkCode: учитывать exclude self id (уже частично) и org  
- Отключить submit пока check = occupied  
- CSRF уже есть — оставить  

**README_API.md**  
- Описать ObjectFactory-поведение, коды 409 duplicate, identity, consolidate idempotency  
- Параметры auto master_code  

**documentation/01_бизнес_логика_и_обзор.md**  
- Короткая отсылка к процессу onboarding элемента и типам как картам данных  

**documentation/04_состояния_и_жизненный_цикл.md**  
- Уточнить: create только draft; active только после schema validation  

### 9.3. Трогать точечно / по необходимости

**classes/Controller/Erp230/Structure.php**  
- При add_link: нельзя линковать archived/merged; уже косвенно через модель — проверить правила  
- Не смешивать с create object  

**classes/Model/Erp230/Property.php**  
- upsert_safe — канон; не менять контракт статусов ok / rejected_low_trust  
- При необходимости batch SQL — только здесь  

**classes/Model/Erp230/Search.php**  
- Метод для suggest_similar, если нет  

**init.php / routing**  
- Роуты wizard, JSON check_duplicates, codes/generate  

**config/**  
- Дефолты: manual source id, лимиты suggest, порог semantic distance только для warn  

### 9.4. Не раздувать и не дублировать

- Не копировать create_snapshot из Consolidation в другие места (GoldenRecord уже проксирует).  
- Не писать свойства в обход upsert_safe.  
- Не добавлять вторую таблицу «карт данных» параллельно `types` — только schema/meta.  
- Не реализовывать коннекторы SAP/Teamcenter внутри erp230_mdm.  
- Не ослаблять RLS ради удобства импорта.

---

## 10. Порядок внедрения (практический)

**Итерация 1 — безопасность от дублей по коду**  
SQL unique + Model_Erp230_Code exclude self + Factory только find/create by code + перевод Objects::action_create и API на Factory.  
Быстрый эффект, мало UI.

**Итерация 2 — типы как карты**  
SchemaValidator на transition_to(active); form подтягивает schema по type; документация x-mdm.

**Итерация 3 — identity_keys + панель похожих**  
DuplicateGuard + partial UI + 409 в API.

**Итерация 4 — авто master_code**  
CodeGenerator + series + кнопка в форме.

**Итерация 5 — упрощение UX**  
Wizard, copy-from, batch import job вне ядра (вызов API).

**Итерация 6 — рефакторинг Consolidation → Factory**  
Идемпотентность и единый путь, регресс-тесты integrate.

---

## 11. Операционные правила для ролей

| Роль | Создание draft | Публикация active | Менять types/schema | Merge дублей | Trust override |
|------|----------------|-------------------|---------------------|--------------|----------------|
| viewer | нет | нет | нет | нет | нет |
| editor | да в своей org | по политике (да/нет) | нет | нет | нет (manual=100 уже сильный) |
| admin/steward | да | да | да | да | expert override |

Рекомендация: publish (draft→active) и merge — право `mdm.steward.edit` или отдельное `mdm.object.publish`, чтобы editor наполнял черновики, а эталон подтверждал data steward.

---

## 12. Критерии готовности (Definition of Done)

1. Повторный consolidate с тем же source+remote не создаёт второй objects.id.  
2. Повторный create с тем же master_code+type+org возвращает ошибку или существующий объект (по контракту), никогда тихий дубль.  
3. UI не даёт submit занятого кода.  
4. Active невозможен при пустом required из schema.  
5. Все ручные атрибуты идут с source `manual` и видны в lineage.  
6. Смена типа/схемы не ломает чтение старых object_state (обратная совместимость ключей).  
7. Документирован один write-path; контроллеры тонкие.

---

## 13. Краткие ответы на вопросы постановки

**Как организовать добавление новых элементов?**  
Через единый ObjectFactory: тип → org → код → anti-dupe → draft → свойства по schema → publish. UI, API и интеграции только как фасады.

**Как делать master-code?**  
Нормализация + уникальность в (type, org); policy на типе; auto через series в БД или канонический код из PLM; резерв при draft; не из UUID.

**Как избежать дублей?**  
xref → unique code → identity_keys → soft search → merge; единый lookup order в consolidate/factory.

**Как упростить процесс?**  
Schema-driven форма, wizard с поиском похожих, auto-code, copy-from, batch через API, один batch upsert с refresh.

**Нужны ли карты данных?**  
Да — это `types` + `types.schema` (+ x-mdm). Хранить в БД MDM, редактировать в модуле (UI types/API), не в обход в файлах коннекторов.

**Что в модуле / что вне?**  
В модуле: домен MDM, типы, коды, guard, golden record, API/UI.  
Вне: коннекторы СУ, очереди ETL, IAM, LLM-инфраструктура, бинарные архивы.

---

## 14. Приложение: контракт payload создания (целевой)

```json
{
  "type_id": "material_raw",
  "org_id": "uuid-or-code",
  "master_code": "MAT-001234",
  "master_code_mode": "manual|auto",
  "properties": {
    "name": { "value": "Лист 2мм", "uom": null },
    "mass": { "value": 15.42, "uom": "kg" }
  },
  "source_id": "manual",
  "remote_id": null
}
```

Ответы: 201 created | 200 existing (если allow_existing) | 409 conflict `{ "code": "duplicate_master_code"|"duplicate_identity"|"duplicate_xref", "candidates": [...] }`.

Этого контракта должны придерживаться POST /object, UI factory и внешние коннекторы (через consolidate с тем же смыслом полей).