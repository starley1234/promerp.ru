# manual_for_file.md — пофайловый план внедрения ObjectFactory / антидубли / schema

Контекст: модуль `application/modules/erp230_mdm/`.  
Опирается на `classes/manual.md` (v5.3) и фактический код сервисов/контроллеров/моделей.  
Документ самодостаточен: при правке файла открывать этот раздел + сам файл.

Канонический write-path (цель):

```
UI / API / Connector
  → Service_Erp230_ObjectFactory
      normalize master_code
      CodeGenerator (auto)
      DuplicateGuard (code, xref, identity_keys, similar)
      create draft | return existing
      xref при необходимости
      batch properties → GoldenRecord
  → refresh_object_state
  → transition_to(active) → SchemaValidator → baseline
```

Имена классов Kohana: файл `classes/Service/Erp230/ObjectFactory.php` → `Service_Erp230_ObjectFactory`.

---

## A. СОЗДАТЬ

### A.1. `classes/Service/Erp230/ObjectFactory.php` (новый)

Singleton по образцу `Service_Erp230_GoldenRecord` / `Consolidation`.

Публичный API:

```php
public static function instance(): Service_Erp230_ObjectFactory

/**
 * @param array $payload  type_id, org_id, master_code?, master_code_mode?, properties?, source_id?, remote_id?
 * @param array $options  source_id, remote_id, allow_existing (bool), actor_id, skip_properties (bool)
 * @return Model_Erp230_Object
 * @throws Kohana_Exception|Validation_Exception|ORM_Validation_Exception
 *         при дубле — код/данные для 409: duplicate_master_code|duplicate_identity|duplicate_xref
 */
public function create(array $payload, array $options = [])
```

Алгоритм `create` (одна транзакция `Database::instance('mdm')`):

1. Валидация: `type_id`, `org_id` обязательны; `properties` — array если переданы.
2. `source_id` = options/payload или `'manual'`; `remote_id` из options/payload.
3. Если есть `source_id` + `remote_id` — `DuplicateGuard::find_by_xref`.  
   - Найден и state=merged → перейти на `merged_into`.  
   - Найден → если `allow_existing` — использовать; иначе конфликт `duplicate_xref`.
4. `CodeGenerator::normalize` для master_code; если mode=auto или код пуст и policy=auto — `allocate`.
5. `DuplicateGuard::assert_unique_code` / `find_by_code` (type_id, org_id, exclude null).  
   - Есть объект: `allow_existing` → return existing; иначе throw conflict `duplicate_master_code`.
6. Опционально `find_by_identity_keys` по properties и schema type — при hit: warn в options или block (409 `duplicate_identity`) в зависимости от `options['identity_mode']` = `block|warn|skip` (default для API: block если keys заданы и совпали; для UI можно warn).
7. Создать `ORM::factory('Erp230_Object')->values([... type_id, org_id, master_code, state=>'draft'])->create()`.  
   **Всегда state=draft**, игнорировать payload state на create.
8. Если remote_id — INSERT в `object_xref` через `DB::query` (как в Consolidation; ORM Xref read-only).
9. Properties: привести к списку `{key, value, uom}`; вызвать `GoldenRecord::update_attributes_batch` (после добавления метода) **внутри той же транзакции** — либо напрямую `Property::upsert_safe` + один `refresh_object_state` в конце, чтобы не открывать вложенный begin в GoldenRecord.  
   **Важно:** сейчас `GoldenRecord::update_attribute` сам делает begin/commit — для batch из Factory нужен либо флаг `options['external_transaction']`, либо batch-метод без собственной транзакции, либо Factory не оборачивает properties в свой begin после create (разделить: create+xref в транзакции, properties отдельным batch). Рекомендация: batch-метод **без** собственной транзакции; транзакцию держит вызывающий (Factory/Consolidation/controller).
10. Event `erp230.object.created` при реально новом; commit; return object.

Вспомогательно:

```php
public function find_or_create_for_consolidate($source_id, $remote_id, array $payload): Model_Erp230_Object
```

— только find xref → code → create draft + xref, **без** properties (properties оставляет Consolidation). Нужен для рефакторинга Consolidation.

Не дублировать: create_snapshot, merge, transition_to.

---

### A.2. `classes/Service/Erp230/CodeGenerator.php` (новый)

```php
public static function instance()

/** Нормализация: trim, upper_trim / nfkc по policy */
public function normalize($code, array $policy = []): string

/** Проверка pattern из policy (regex или простой шаблон) */
public function validate_pattern($code, array $policy): bool

/**
 * Выделить следующий код для type+org.
 * Читает policy из Type::code_policy($type_id).
 * mode auto → CodeSeries/SQL next; manual → throw если пусто.
 */
public function allocate($type_id, $org_id, array $policy = null): string

/** policy из типа или default: mode=manual, normalize=upper_trim */
public function policy_for_type($type_id): array
```

Default policy если в schema нет `x-mdm.code_policy`:

```json
{ "mode": "manual", "normalize": "upper_trim" }
```

normalize `upper_trim`: `trim` + схлопывание внутренних пробелов + `mb_strtoupper`.  
Не генерировать код из UUID.

Пока нет таблицы серий — `allocate` может:  
(а) бросать «auto code not configured», или  
(б) временный формат `{TYPE}-{ORG}-{seq}` через `Model_Erp230_CodeSeries` / raw SQL.  
Полноценно — после миграции §A.5.

---

### A.3. `classes/Service/Erp230/DuplicateGuard.php` (новый)

```php
public static function instance()

/** @return Model_Erp230_Object|null */
public function find_by_xref($source_id, $remote_id)

/** @return Model_Erp230_Object|null  среди deleted_at IS NULL, не обязательно exclude merged — caller редиректит merged */
public function find_by_code($master_code, $type_id, $org_id)

/**
 * @param string|null $exclude_object_id  для edit
 * @throws / returns result
 */
public function assert_unique_code($master_code, $type_id, $org_id, $exclude_object_id = null): void

/**
 * identity_keys из Type; поиск по object_state.attributes.
 * @return array list of candidates [id, master_code, name, ...]
 */
public function find_by_identity_keys($type_id, $org_id, array $properties)

/**
 * Обёртка: fulltext object_state + optional semantic Model_Erp230_Search
 * @return array { status: ok|warn|block, candidates: [], code: ?string }
 */
public function check(array $context): array
// context: master_code, type_id, org_id, properties, source_id, remote_id, exclude_object_id, q?

public function suggest_similar($q, $type_id = null, $org_id = null, $limit = 10): array
```

Реализация code: опираться на `Model_Erp230_Code::code_exists` / `get_object_id` + exclude.  
Сейчас `code_exists` **без** exclude — сначала расширить Code (§B.6), затем Guard.

identity_keys: для каждого key из policy значение из `$properties[$k]`; SQL вида:

```sql
SELECT o.id, o.master_code, ...
FROM objects o
JOIN object_state s ON s.object_id = o.id
WHERE o.type_id = :t AND o.org_id = :org AND o.deleted_at IS NULL
  AND o.state <> 'merged'
  AND s.attributes->>:key = :val
```

(несколько keys — AND или по политике «любой»; default AND для всех непустых identity).

suggest_similar: не block alone; только candidates для UI.

Структура результата для UI/API:

```php
[
  'status' => 'ok'|'warn'|'block',
  'reason' => 'duplicate_master_code'|'duplicate_xref'|'duplicate_identity'|null,
  'candidates' => [ ['id'=>..., 'master_code'=>..., 'name'=>..., 'match'=>...], ... ],
]
```

---

### A.4. `classes/Service/Erp230/SchemaValidator.php` (новый)

```php
public static function instance()

/**
 * @param array|string $schema  types.schema
 * @param array $properties     key => value (уже скаляры)
 * @return array ['ok' => bool, 'errors' => [key => message]]
 */
public function validate(array $properties, $schema): array

/** Достаёт schema объекта через type_id / ORM Type */
public function validate_object($object_id): array

/**
 * required из schema; type check string/number/boolean/enum;
 * key format ^[a-z][a-z0-9_.]*$
 * Не обязателен полный JSON Schema draft-07 engine — минимально:
 *  - required[]
 *  - properties[key].type
 *  - properties[key].enum
 *  - minLength для string
 */
```

Вызов: `Object::transition_to('active')` и опционально Factory при create (только soft-warn).

---

### A.5. `classes/Model/Erp230/CodeSeries.php` (новый, опционально с миграцией)

Таблица (миграция SQL в каталоге модуля, рядом с `БД_онтология_v5.4.sql` или `migrations/`):

```sql
CREATE TABLE IF NOT EXISTS code_series (
  type_id text NOT NULL REFERENCES types(id),
  org_id  text NOT NULL REFERENCES org_units(id),
  last_value bigint NOT NULL DEFAULT 0,
  prefix text,
  pad int NOT NULL DEFAULT 6,
  PRIMARY KEY (type_id, org_id)
);

-- next in transaction:
-- UPDATE code_series SET last_value = last_value + 1
-- WHERE type_id=:t AND org_id=:o RETURNING last_value;
```

Модель: метод `next($type_id, $org_id): int` через raw SQL, без ORM PK- sorcery.

Дополнительно в миграции (если ещё нет на «живых» объектах — в v5.4 уже есть `object_codes` PK и `uk_object_codes_upper`):

- Не дублировать unique на `objects` отдельно — реестр `object_codes` уже синхронизируется триггерами `tg_object_set_defaults` / `tg_object_sync_codes`.
- При необходимости partial unique на objects — **не** обязателен, если все create идут через триггер codes.
- Опционально: `ALTER TABLE types ADD COLUMN IF NOT EXISTS meta jsonb DEFAULT '{}'`.

---

### A.6. Views (новые partials)

**`views/erp230/objects/partials/duplicate_panel.php`**

- Вход: `$candidates` (array).
- Список: master_code, name, link `erp230_objects/view/<id>`, причина match.
- Пустой candidates — не рендерить.

**`views/erp230/objects/partials/schema_fields.php`**

- Вход: `$schema`, `$values` (key=>value).
- Рендер required/properties: input/select по type/enum; name закреплён.
- Имена полей согласовать с формой: либо `attr_key[]`/`attr_value[]`, либо `properties[name]` — **оставить attr_*** для совместимости с `_save_attributes`, schema_fields только генерирует те же имена.

**`views/erp230/objects/create_wizard.php`** — не обязателен в итерации 1; можно отложить. Если делать: шаги type → org → search → code → attrs.

---

### A.7. `documentation/06_добавление_элементов_и_антидубли.md` (новый)

Краткая выжимка manual.md §§2–4, 8, 14 + ссылка на этот manual_for_file. Не копировать весь текст.

---

## B. ИЗМЕНИТЬ

### B.1. `classes/Service/Erp230/GoldenRecord.php`

**Сейчас:** `update_attribute` (валидация, begin, upsert_safe, refresh, event, audit, commit); `rebuild_golden_record`; `create_snapshot` (прокси Consolidation); `verify_integrity`; `get_registry_list`.

**Добавить:**

1. `update_attributes_batch($object_id, array $props, $source_id, array $options = [])`  
   - `$props`: список `['key'=>, 'value'=>, 'uom'=>]` или map key => ['value'=>,'uom'=>].  
   - Без вложенного begin, если `$options['use_transaction'] === false`; default true для самостоятельного вызова из API.  
   - Цикл upsert_safe; один refresh_object_state если был хотя бы один ok; event summary; return `['ok'=>n, 'rejected'=>[keys], 'total'=>n]`.  
   - `update_attribute` можно переписать как batch из одного ключа **или** оставить и вызывать из batch по одному — лучше batch содержит цикл, `update_attribute` вызывает batch из 1 элемента с use_transaction true (осторожно с двойным refresh — приемлемо).

2. `validate_for_publish($object_id): array`  
   - Собрать current properties (Object::current_properties или state).  
   - `SchemaValidator::validate_object`.  
   - return errors.

**Не менять:** контракт `update_attribute` для Stewardship (source steward); `create_snapshot` прокси.

**Замечание по транзакциям:** Stewardship и API properties должны продолжить работать. При вызове batch из Consolidation (уже в транзакции) — `use_transaction => false` и без commit внутри.

---

### B.2. `classes/Service/Erp230/Consolidation.php`

**Сейчас в `consolidate`:** валидация payload (type_id, org_id, master_code, properties) → begin → xref find → else find by master_code+type+org → else create draft → INSERT xref → merged redirect → foreach upsert_safe → refresh/reindex → commit → event.

**Изменить блок find/create (шаги 2–3):**

Заменить на:

```php
$object = Service_Erp230_ObjectFactory::instance()
    ->find_or_create_for_consolidate($source_id, $remote_id, $payload);
// properties loop как сейчас ИЛИ GoldenRecord::update_attributes_batch(..., ['use_transaction'=>false])
```

Либо полный `Factory::create` с properties и `allow_existing=true` + source/remote — тогда убрать дублирующий loop; refresh/reindex оставить если batch не рефрешит при no-op.

**Явная обработка:** если master_code занят **другим** object_id без этого xref — не silent attach: throw/инцидент (Factory должен отличить «тот же объект» vs «чужой код»). Сейчас код при find by code привязывает xref к найденному — это OK для consolidate (idempotent link). Сохранить поведение: find by code → add xref. Не создавать второй object.

**Валидация:** master_code может стать optional если policy=auto — ослабить rule not_empty когда Factory allocate; либо Consolidation по-прежнему требует master_code (итерация 1 — не ослаблять).

**Не трогать:** `create_snapshot`.

---

### B.3. `classes/Controller/Erp230/Objects.php`

**Сейчас `action_create` POST:**  
`$object->values($post, ['type_id','org_id','master_code','state'])->save()` + `_save_attributes`.

**Заменить POST-ветку на:**

```php
$payload = [
  'type_id'     => Arr::get($post, 'type_id'),
  'org_id'      => Arr::get($post, 'org_id'),
  'master_code' => Arr::get($post, 'master_code'),
  'master_code_mode' => Arr::get($post, 'master_code_mode', 'manual'),
  'properties'  => /* из attr_key/attr_value/attr_uom через хелпер */,
  'source_id'   => self::MANUAL_SOURCE,
];
$object = Service_Erp230_ObjectFactory::instance()->create($payload, [
  'source_id' => self::MANUAL_SOURCE,
  'allow_existing' => false,
  'actor_id' => /* current user id if available */,
]);
```

- state с формы **не** принимать (Factory → draft).
- Ошибки дубля: ловить, класть в `$errors['_external']` / `master_code`, не redirect; показать form + duplicate candidates если есть.
- GET: default org если есть в user; `schema` по query type_id; передать `candidates` пустой.

**`_save_attributes`:** делегировать в `GoldenRecord::update_attributes_batch($object->id, $normalized, self::MANUAL_SOURCE)` вместо прямого `Property::upsert_safe`. Сохранить return shape `ok/rejected/total` для `_flash_attribute_result`. Coerce value оставить в контроллере или перенести в сервис.

**`action_edit`:**  
- values без безконтрольной смены master_code в active (модель rules) — см. Object.  
- attributes через batch GoldenRecord.  
- При смене кода — Guard assert_unique_code exclude self.

**Добавить (опционально итерация 3):**  
`action_check_duplicates` GET/POST JSON: q, type_id, org_id, master_code, properties → DuplicateGuard::check → `_json_response`.

**`action_state`:** без изменений снаружи; усиление внутри `transition_to` (модель).

**Не раздувать:** search, delete, index — без изменений по Factory.

---

### B.4. API-контроллер объектов / consolidate

Файлы по факту дерева: `classes/Controller/Erp230/Api.php` и при наличии `Api/Codes.php`, `Api/Type.php` (в init: directory Erp230/Api).

**POST /object (create):**  
не ORM values/save напрямую → `ObjectFactory::create`  
Ответы: 201 + body; 409 + `{ code, candidates }` при дубле; 422 validation.

**POST /consolidate:**  
как сейчас снаружи; внутри Consolidation уже Factory.

**POST /properties/<id>:**  
`GoldenRecord::update_attributes_batch`.

**GET codes/check:**  
контракт не менять; внутри при наличии — Code::code_exists + optional exclude_object_id query param (для edit формы).

**GET type/schema/<id>:**  
отдавать schema включая `x-mdm` (identity_keys, code_policy) — не вырезать.

Если Api.php в поставке контекста нет — править тот файл, где реально `action_object` / create; сверить README_API.md.

---

### B.5. `classes/Model/Erp230/Object.php`

**Сейчас `transition_to`:** матрица состояний, запрет merged, save.

**Добавить в ветку перехода на `active` (до save):**

```php
$errors = Service_Erp230_GoldenRecord::instance()->validate_for_publish($this->id);
if ( ! empty($errors['errors'])) {
  throw new Kohana_Exception('Schema validation failed: :e', [':e' => json_encode($errors['errors'])]);
}
// после успешного save на active — first baseline, если ещё нет baselines:
// Service_Erp230_Consolidation::instance()->create_snapshot($this->id, $actor, 'V1.0');
// actor: из параметра transition_to($state, $actor_id = null) — расширить сигнатуру опционально
```

Сигнатуру `transition_to($state, $actor_id = null)` расширить; baseline только при draft→active и если baseline count=0 (или всегда snapshot на publish — по documentation/04).

**filters/rules:**

- При update: если loaded && state==='active' && master_code changed → error unless admin (callback rule).  
  Реализация: в `rules()` callback или `before_update` проверка `_original_values['master_code']`.
- Уникальность кода: callback rule → `Model_Erp230_Code::code_exists` с exclude `$this->id` при loaded.

**Не переносить** сюда BOM/SQL — уже ок.

---

### B.6. `classes/Model/Erp230/Сode.php` (файл с кириллической «С» в имени — **не переименовывать** без миграции автолоада; класс `Model_Erp230_Code`)

**Сейчас:** `get_object_id`, `code_exists` без exclude.

**Изменить:**

```php
public static function code_exists($master_code, $type_id, $org_id, $exclude_object_id = null)
public static function get_object_id($master_code, $type_id, $org_id)
public static function normalize($master_code, $mode = 'upper_trim') // или только в CodeGenerator
```

В `code_exists`: если exclude задан — `->where('object_id', '!=', $exclude_object_id)`.

Регистр: как сейчас UPPER / uk_object_codes_upper.

---

### B.7. `classes/Model/Erp230/Type.php`

**Сейчас:** id, parent_id, display_name, path, schema — без хелперов.

**Добавить методы:**

```php
/** @return array */
public function schema_array()
{
  $s = $this->schema;
  if (is_array($s)) return $s;
  if (is_string($s) && $s !== '') {
    $d = json_decode($s, TRUE);
    return (json_last_error() === JSON_ERROR_NONE && is_array($d)) ? $d : [];
  }
  return [];
}

/** @return array  x-mdm.code_policy или default */
public function code_policy()

/** @return array  list of keys from x-mdm.identity_keys */
public function identity_keys()

/**
 * Опционально: merge schema с parent chain (child overrides properties/required).
 * Итерация 2 — можно stub = schema_array() only.
 */
public function merged_schema()
```

Чтение x-mdm:

```php
$schema = $this->schema_array();
$x = Arr::get($schema, 'x-mdm', []);
```

---

### B.8. `classes/Controller/Erp230/Types.php` + `views/erp230/types/card.php`

**Types.php:** при POST schema — json_decode check (уже есть); после save cache bust `erp230_mdm_types_all` (уже).  
Добавить: не обязательно GUI; при желании flash «поддерживается x-mdm.identity_keys, code_policy».

**card.php:** в подвале JSON editor — HTML-комментарий или help block с примером:

```json
"x-mdm": {
  "identity_keys": ["draw_no"],
  "code_policy": { "mode": "manual", "normalize": "upper_trim" },
  "display_name_keys": ["name"]
}
```

**Параллельно** `Controller_Erp230_Dictionaries` (`action_get_schema`, type_save):  
`get_schema` уже отдаёт schema as-is — x-mdm включён.  
При `_normalize_schema` — OK.  
Можно в JSON response добавить отдельно `x_mdm` key для удобства UI objects form — опционально.

---

### B.9. `views/erp230/objects/form.php`

**Сейчас:** master_code + type + org; datalist schema keys; attr rows; JS checkCode → `erp230_api/codes/check`; нет блокировки submit; нет смены schema при type change (только datalist с серверного schema); нет duplicate panel; нет auto-code button.

**Изменить:**

1. **JS type_id change:**  
   `fetch` schema — URL: `erp230_dictionaries/get_schema?id=` или `erp230_api/type/schema/<id>` (что реально роутится).  
   Перестроить datalist; опционально подставить missing required keys как empty rows (name уже есть).

2. **checkCode:**  
   - учитывать org_id (уже);  
   - exclude `object.id` в edit (уже сравнивает object_id);  
   - query `exclude_object_id` если API поддержит;  
   - при `exists` — `submitBtn.disabled = true`; иначе enable;  
   - class red/green на msg (частично есть).

3. **Перед submit (create):**  
   optional fetch check_duplicates; показать `#duplicate-panel` (include partial).  
   Confirm если warn.

4. **Кнопка «Сгенерировать код»** (если policy mode=auto):  
   видна после type+org; POST/GET generate endpoint (итерация 4) или client hint.  
   Итерация 1 — можно скрыть.

5. **Поле state на create** — если появится в форме, убрать; сейчас state в values контроллера — убрать на сервере.

6. **CSRF** — оставить.

7. Include:

```php
echo View::factory('erp230/objects/partials/duplicate_panel')->set('candidates', isset($candidates) ? $candidates : []);
```

8. org change → re-checkCode (сейчас только type+blur code).

---

### B.10. `init.php`

**Сейчас роуты:** mdm_api, erp230_api, baselines|structure|type|codes, objects в base regex `(index|stewardship|dictionary|widget|main|objects)`.

**Добавить при появлении actions:**

- objects/check_duplicates, objects/generate_code — покрываются `erp230_<controller>/<action>` если controller objects уже в списке — **OK без изменения** для `erp230_objects/check_duplicates`.
- Расширить regex base если нужны `types|sources|structure|dictionaries|stewardship` — **сейчас** types/sources/structure/dictionaries могут идти другим роутом; не ломать.  
  Проверить: `erp230_types`, `erp230_sources`, `erp230_structure`, `erp230_dictionaries` — если 404, добавить в pattern:

```php
'controller' => '(index|stewardship|dictionary|dictionaries|widget|main|objects|types|sources|structure)',
```

(только если в проекте реально 404; в коде контроллеры есть.)

RLS auth.login_success — **не трогать**.

---

### B.11. `README_API.md`

Добавить секции:

- POST /object → ObjectFactory; always draft; 409 codes: `duplicate_master_code`, `duplicate_identity`, `duplicate_xref`; body candidates.
- POST /consolidate idempotency: same source+remote → same object_id; order xref → code → create.
- GET /codes/check optional `exclude_object_id`.
- GET /type/schema — x-mdm.
- Optional POST /codes/generate {type_id, org_id}.
- Payload target (§14 manual.md).

---

### B.12. Документация

**`documentation/01_бизнес_логика_и_обзор.md`**  
Короткий абзац в §6: onboarding элемента через ObjectFactory; типы = карты данных (schema + x-mdm); ссылка на `06_...` и manual.

**`documentation/04_состояния_и_жизненный_цикл.md`**  
§2.1: create только draft на сервере.  
§2.2: Active только после SchemaValidator; first baseline при publish.

**`documentation/09_справочники_нси_и_типы.md`**  
§2: описать x-mdm (identity_keys, code_policy).

---

## C. ТОЧЕЧНО / ПО НЕОБХОДИМОСТИ

### C.1. `classes/Controller/Erp230/Structure.php`

В `action_add_link` после load child: если `$child_obj->state` in `archived`,`merged` — notice error, redirect без save.  
Аналогично parent archived/merged — запрет.  
Модель Link цикл — не дублировать.

### C.2. `classes/Model/Erp230/Property.php`

**Не менять** контракт `upsert_safe` → `'ok'|'rejected_low_trust'`.  
Batch SQL — только если профайлинг потребует; не в итерации 1.

### C.3. `classes/Model/Erp230/Search.php`

Добавить convenience:

```php
public function suggest_for_guard($q, $type_id, $org_id, $limit = 10)
```

fulltext по object_state.search + filter type/org; semantic — optional separate.  
DuplicateGuard::suggest_similar вызывает это.

### C.4. `classes/Controller/Erp230/Stewardship.php`

Оставить `update_attribute` / `rebuild` / `create_snapshot`.  
После batch API — resolve может использовать batch для properties — опционально.  
**Не** переводить на ObjectFactory.

### C.5. `classes/Controller/Erp230/Sources.php` / Dictionaries / Baseline model / Link / Main / Xref / Uom / OrgUnit / Dictionary

**Не трогать** для Factory-эпика, кроме schema get (Dictionaries уже ок) и Source trust (ручной manual должен существовать в `sources`!).

**Критично:** в `БД_онтология_v5.4.sql` seed sources: только `plm`, `ai`.  
UI Objects пишет `source_id = 'manual'`. Stewardship — `steward`.  
**Добавить в миграцию/seed:**

```sql
INSERT INTO sources(id, name, kind, trust) VALUES
  ('manual',  'Ручной ввод', 'manual', 100),
  ('steward', 'Data Steward', 'manual', 100),
  ('sensor',  'Telemetry', 'sensor', 40)
ON CONFLICT (id) DO NOTHING;
```

Иначе upsert_property падает на FK / null trust.

### C.6. `classes/Controller/Erp230/Objects.php` — registry filters

GoldenRecord `_apply_registry_filters` uses `Model_Erp230_Dictionary` for types/org — OK. Не часть Factory.

### C.7. Имя файла Code

На диске: `Model/Erp230/Сode.php` (кириллическая С).  
Автолоад Kohana ищет `Code.php` (латиница) — **проверить на среде**. Если класс не грузится — добавить латинский `Code.php` (копия/rename) и убрать кириллический файл. Это блокер для codes/check и Guard.

---

## D. НЕ ДЕЛАТЬ

- Не копировать `create_snapshot` из Consolidation в GoldenRecord/Object (прокси уже есть).
- Не писать properties в обход `upsert_safe`.
- Не создавать вторую таблицу «карт данных» параллельно `types`.
- Не внедрять коннекторы PLM/SAP внутрь erp230_mdm.
- Не ослаблять RLS.
- Не генерировать master_code из UUID.
- Не менять master_code у active без admin/процедуры.
- Не делать silent duplicate при занятом коде в UI create.
- Не добавлять state=active на create из POST body.

---

## E. ПОРЯДОК ВНЕДРЕНИЯ (что в каком файле по итерациям)

### Итерация 1 — дубли по коду + единый create

1. Seed sources: manual, steward (SQL).  
2. Починить `Model_Erp230_Code` filename + `code_exists($..., $exclude)`.  
3. Создать `DuplicateGuard` (xref + code).  
4. Создать `ObjectFactory` (без identity/auto).  
5. `Objects::action_create` → Factory; state draft.  
6. API POST /object → Factory.  
7. form.php: disable submit if code occupied; org change recheck.  
8. README_API 409.  

DoD: повторный create same code → error; UI не submit occupied.

### Итерация 2 — schema publish

1. `SchemaValidator`.  
2. `Type::schema_array/code_policy/identity_keys`.  
3. `GoldenRecord::validate_for_publish`.  
4. `Object::transition_to('active')` + validation + first baseline.  
5. form: fetch schema on type change.  
6. Docs 04, 09.  

DoD: active невозможен без required (name и schema.required).

### Итерация 3 — identity + similar

1. DuplicateGuard identity + suggest.  
2. partials/duplicate_panel; form confirm.  
3. API 409 duplicate_identity.  
4. `action_check_duplicates`.  

### Итерация 4 — auto master_code

1. SQL code_series + CodeSeries model.  
2. CodeGenerator.allocate.  
3. Factory branch auto.  
4. Кнопка в form; API codes/generate.  

### Итерация 5 — UX

1. wizard optional; copy-from; batch import вне модуля через API.  

### Итерация 6 — Consolidation → Factory

1. Consolidation find/create через Factory.  
2. properties через batch use_transaction=false.  
3. Регресс: consolidate twice same xref → one id.  

---

## F. КОНТРАКТЫ ДЛЯ ПРАВКИ КОДА

### F.1. Payload create (UI/API → Factory)

```json
{
  "type_id": "material_raw",
  "org_id": "HOLDING",
  "master_code": "MAT-001234",
  "master_code_mode": "manual",
  "properties": {
    "name": { "value": "Лист 2мм", "uom": null }
  },
  "source_id": "manual",
  "remote_id": null
}
```

UI form сейчас шлёт flat post + attr_key[] — маппинг в контроллере:

```php
properties['name'] = ['value' => ..., 'uom' => ...];
// из параллельных массивов attr_*
```

### F.2. Ответы

| HTTP | Когда |
|------|--------|
| 201 | создан новый |
| 200 | allow_existing, вернулся существующий |
| 409 | duplicate_* + candidates |
| 422 | validation schema/fields |

### F.3. Lookup order (везде одинаковый)

1. xref (source_id + remote_id)  
2. master_code + type_id + org_id (живые, не deleted)  
3. identity_keys (если настроены)  
4. create draft  
5. soft similar — только UI warn  

### F.4. Источники trust (ожидаемые)

| id | trust | use |
|----|-------|-----|
| manual | 100 | UI Objects |
| steward | 100 | Stewardship resolve |
| plm | 90 | consolidate |
| ai | 30 | AI |
| sensor | ~40 | telemetry |

---

## G. ЧЕКЛИСТ ПО ФАЙЛУ (кратко)

| Файл | Действие |
|------|----------|
| `classes/Service/Erp230/ObjectFactory.php` | CREATE |
| `classes/Service/Erp230/CodeGenerator.php` | CREATE |
| `classes/Service/Erp230/DuplicateGuard.php` | CREATE |
| `classes/Service/Erp230/SchemaValidator.php` | CREATE |
| `classes/Model/Erp230/CodeSeries.php` | CREATE (итер.4) |
| `classes/Service/Erp230/GoldenRecord.php` | ADD batch + validate_for_publish; txn flag |
| `classes/Service/Erp230/Consolidation.php` | REPLACE find/create → Factory (итер.6; можно раньше) |
| `classes/Controller/Erp230/Objects.php` | create→Factory; _save_attributes→batch; optional check_duplicates |
| `classes/Controller/Erp230/Api.php` (+ Api/*) | POST object/properties/consolidate |
| `classes/Model/Erp230/Object.php` | transition_to active validation+baseline; lock master_code active |
| `classes/Model/Erp230/Сode.php` / `Code.php` | exclude + normalize; fix filename |
| `classes/Model/Erp230/Type.php` | schema_array, code_policy, identity_keys |
| `classes/Model/Erp230/Search.php` | suggest_for_guard optional |
| `classes/Model/Erp230/Property.php` | не трогать контракт |
| `classes/Controller/Erp230/Structure.php` | ban link archived/merged |
| `classes/Controller/Erp230/Types.php` | help x-mdm |
| `classes/Controller/Erp230/Dictionaries.php` | optional x_mdm in get_schema |
| `classes/Controller/Erp230/Stewardship.php` | не обязательно |
| `classes/Controller/Erp230/Sources.php` | не обязательно |
| `views/erp230/objects/form.php` | schema fetch, submit lock, duplicates, org recheck |
| `views/erp230/objects/partials/duplicate_panel.php` | CREATE |
| `views/erp230/objects/partials/schema_fields.php` | CREATE |
| `views/erp230/types/card.php` | подсказка x-mdm |
| `init.php` | routes controllers regex if needed |
| `README_API.md` | 409, Factory, x-mdm |
| SQL seed/migration | sources manual/steward; code_series; types.meta? |
| `documentation/01_*.md`, `04_*.md`, `09_*.md` | уточнения |
| `documentation/06_добавление_элементов_и_антидубли.md` | CREATE short |
| `classes/Model/Erp230/Xref.php` | не открывать ORM write |
| `classes/Model/Erp230/Baseline.php` | не трогать |
| `classes/Model/Erp230/Link.php` | не трогать цикл |
| `classes/Model/Erp230/Main.php` | не трогать |

---

## H. DEFINITION OF DONE (регресс)

1. consolidate ×2 same source+remote → один objects.id, один xref.  
2. UI/API create ×2 same master_code+type+org → 409/ошибка формы, не второй row.  
3. form submit заблокирован при occupied code.  
4. transition draft→active без required из schema → отказ.  
5. Ручные attr → source `manual`, видны в properties lineage.  
6. upsert_safe rejected_low_trust не перетирает PLM.  
7. Контроллеры без копипасты create-логики — только Factory/GoldenRecord.  
8. FK sources: manual/steward существуют.