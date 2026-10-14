# API Documentation: MDM / Digital Twin (ERP230)

Данный файл содержит описание REST API и перечень методов ключевых моделей модуля `erp230_mdm`. Используется как контекст для генерации кода и понимания архитектуры системы.

## 1. REST API Эндпоинты

Система использует разделение на основной контроллер и специализированные контроллеры для различных доменов данных.

### 1.1. Основной контроллер (Controller_Erp230_Api)
Базовый URL: `/mdm/api/` или `/erp230_api/`

#### Objects (Мастер-записи)
*   `GET /objects` — Список объектов с фильтрами (`type`, `org`, `state`, `q`) и пагинацией.
*   `GET /object/<id>?with=properties` — Детальная информация об объекте.
*   `POST /object` — Создание новой мастер-записи.
*   `PATCH /object/<id>` — Обновление полей (`master_code`, `org_id`, `type_id`).
*   `DELETE /object/<id>` — Мягкое удаление (архивация).
*   `POST /object/<id>/state` — Смена жизненного цикла через `transition_to`.
*   `POST /object/<id>/merge` — Слияние дублей (перенос Xref, свойств и связей в выжившую запись).

#### Properties (Атрибуты и Time-travel)
*   `GET /properties/<id>` — Актуальный набор свойств (Golden Record).
*   `POST /properties/<id>` — Пакетная запись свойств с использованием Trust Score.
*   `GET /attribute_history/<id>?key=<key>` — История изменений конкретного атрибута.

#### Xref (Кросс-референсы)
*   `GET /resolve?source=<src>&remote=<id>` — Поиск мастер-записи по внешнему ID.
*   `GET /xref/<id>` — Список всех внешних привязок объекта.

#### Consolidate (Интеграция)
*   `POST /consolidate` — Прием данных из внешних систем (автоматический поиск по Xref и обновление свойств).

#### Twin & Telemetry (Цифровой двойник)
*   `POST /telemetry/<id>` — Прием данных с датчиков (записываются как свойства с источником `sensor`).
*   `GET /twin/<id>` — Паспорт двойника (данные + состояние).
*   `GET /twin/<id>/state?at=<timestamp>` — Срез состояния объекта на любой момент времени.

#### Search & Dictionaries
*   `GET /search?q=&type=text|semantic` — Поиск (текстовый или векторный семантический).
*   `GET /dictionary/<name>` — Получение справочников (`org_units`, `sources`, `uom`).

---

### 1.2. Структура и BOM (Controller_Erp230_Api_Structure)
Базовый URL: `/mdm/structure/` или `/erp230_api/structure/`

*   `GET /bom/<id>?type=ebom` — Прямые дочерние связи.
*   `GET /bom_tree/<id>?type=ebom&depth=10` — Полное дерево структуры (рекурсивный обход, CTE).
*   `GET /where_used/<id>?type=ebom` — Обратный обход (где используется компонент).
*   `POST /create_link` — Создание связи между объектами (с проверкой на циклы).
*   `DELETE /delete_link/<id>` — Закрытие связи (установка верхней границы периода валидности).

---

### 1.3. Снимки и Аудит (Controller_Erp230_Api_Baselines)
Базовый URL: `/mdm/baselines/` или `/erp230_api/baselines/`

*   `POST /snapshot` — Создание неизменяемого снимка состояния (hash-chain).
*   `GET /list/<id>` — Список всех снимков объекта.
*   `GET /details/<baseline_id>` — Получение JSON-содержимого конкретного снимка.
*   `GET /verify/<id>` — Проверка целостности цепочки хешей объекта.

---

### 1.4. Метаданные и Типы (Controller_Erp230_Api_Type)
Базовый URL: `/mdm/type/` или `/erp230_api/type/`

*   `GET /list` — Иерархический список всех типов объектов.
*   `GET /schema/<id>` — Получение JSON-схемы валидации для типа.
*   `POST /create` — Регистрация нового типа данных.
*   `PATCH /update/<id>` — Обновление метаданных и схем типа.

---

### 1.5. Реестр кодов (Controller_Erp230_Api_Codes)
Базовый URL: `/mdm/codes/` или `/erp230_api/codes/`

*   `GET /check?master_code=...&type_id=...&org_id=...` — Проверка уникальности бизнес-ключа.
*   `GET /resolve?master_code=...&type_id=...&org_id=...` — Получение UUID объекта по кодам.
*   `GET /preview?code=...&type_id=...` — Предпросмотр нормализации кода по политике типа.
*   `GET /next?type_id=...&org_id=...` — Генерация следующего свободного кода (для `mode=auto`).
*   `GET /policy?type_id=...` — Получение правил формирования кода для типа.
*   `POST /validate` — Валидация кода (паттерн + уникальность) перед сохранением.
*   `GET /list` — Реестр всех зарегистрированных кодов.

---

## 2. Методы моделей (Model_Erp230_*)

### Model_Erp230_Object
*   `registry(filters, limit, offset)`: Возвращает список объектов с расчитанным `display_name`.
*   `display_name()`: Формирует имя на основе типа и ключевых атрибутов.
*   `current_properties()`: Возвращает Golden Record с учетом Trust Score.
*   `transition_to(new_state)`: Управляет жизненным циклом.
*   `bom_tree(link_type, depth)`: Рекурсивный запрос (CTE) для получения дерева.
*   `where_used(link_type, depth)`: Поиск вхождений вверх по иерархии.

### Model_Erp230_Property
*   `upsert_safe(object_id, key, value, source_id, uom)`: Запись свойства с проверкой Trust Score и закрытием старых периодов `valid_period`.

### Model_Erp230_Baseline
*   `verify_chain(object_id)`: Пересчет хешей и сверка с `prev_hash`.
*   `is_chain_valid(object_id)`: Быстрая проверка валидности цепочки.

### Model_Erp230_Code
*   `code_exists(...)` / `get_object_id(...)`: Статические методы для работы с реестром бизнес-ключей.

---

## 3. Сервисный слой (Service_Erp230_*)

### Service_Erp230_Consolidation
*   `consolidate(source_id, remote_id, payload)`: Логика сопоставления данных и вызов `upsert_safe`.
*   `create_snapshot(object_id, actor_id, code)`: Формирование JSON-среза и запись нового звена в `object_baselines`.

### Service_Erp230_CodeGenerator
*   `normalize(code, policy)`: Приведение кода к каноническому виду (регистр, спецсимволы).
*   `allocate(type_id, org_id)`: Резервирование и выдача нового серийного номера.
*   `validate_pattern(code, policy)`: Проверка соответствия кода регулярному выражению типа.

---

## 4. Безопасность (RLS)
Система использует PostgreSQL Row Level Security. При авторизации через `auth.login_success` устанавливаются переменные сессии:
*   `app.current_user_id`
*   `app.role` (viewer, editor, admin)