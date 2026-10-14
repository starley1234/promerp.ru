# Карта модулей PromERP

> Файл сгенерирован командой `make docs` (`bin/console module:docs`) из кода. Руками не правим: состав и метрики берутся из самих модулей.

## Как этим пользоваться

- Контекст одного модуля (контракт, таблицы, роуты, права, события, MCP, тесты):
  `bin/console module:context --module=erp401_wms` — карточка на один экран.
- Проверки только этого модуля: `make check-module module=erp401_wms`.
- Правила и Definition of Done: `AGENTS.md`, порядок работы — `docs/AGENT_WORKFLOW.md`.

## Включённые ERP-модули (31)

| ID | Модуль | Строк | PG | Ключевые таблицы (владелец) | MCP | Тесты | Назначение |
| :--: | :--- | --: | --: | :--- | --: | :--- | :--- |
| 100 | [`erp100_base`](application/modules/erp100_base/README.md) | 2880 | 5 | `contact_messages`, `erp_100_api_idempotency`, `erp_100_user_scopes`, `roles`, `roles_users`, `… ещё 6` | 6 | 18 | Базовый модуль для информационной системы (erp100_core), предоставляющий основные контроллеры, утилиты и общую структуру для других модулей. |
| 111 | [`erp111_migrations_postgresql`](application/modules/erp111_migrations_postgresql/README.md) | 1239 | 0 | — | 0 | 6 | Модуль находит migrations_pg/pgMigration_*.php во всех подключённых модулях, ведёт отдельный реестр migrations_pg в каждой группе БД и применяет каждую миграцию в транзакции. |
| 118 | [`erp118_links`](application/modules/erp118_links/README.md) | 1723 | 1 | `erp_118_links`, `lib_118_links` | 0 | 0 | "Мои ссылки" предоставляет пользователям возможность сохранять и управлять персональными ссылками в системе. Эти ссылки могут быть отображены в виде карточек на главной странице модуля, а также… |
| 119 | [`erp119_audit`](application/modules/erp119_audit/README.md) | 6284 | 4 | `erp_119_audit_cursors`, `erp_119_audit_log`, `erp_119_audit_log_`, `erp_119_audit_log_2026_09`, `erp_119_audit_log_immutable`, `… ещё 1` | 3 | 35 | Отвечает на вопрос «кто, когда и что изменил» — требование производственного аудита: партия, норма расхода, производственный заказ должны иметь историю. |
| 199 | [`erp199_sqr`](application/modules/erp199_sqr/README.md) | 8191 | 1 | `erp_199_activity`, `erp_199_inventories`, `erp_199_inventory_items`, `erp_199_media`, `erp_199_objects`, `… ещё 4` | 6 | 17 | Модуль связывает запись в системе с предметом в физическом мире: наклейка с QR-кодом на изделии, партии, заказе, оборудовании или сотруднике, а сервер решает, что показать тому, кто этот код… |
| 208 | [`erp208_events`](application/modules/erp208_events/README.md) | 3457 | 3 | `erp_208_consumer_receipts`, `erp_208_event_subscribers`, `erp_208_events`, `erp_208_outbox` | 3 | 12 | Модуль Система Событий (erp208_events) — это центральное связующее звено (Event Bus) ERP-системы. Он реализует паттерн "Издатель-Подписчик" (Pub/Sub), обеспечивая слабую связанность (Loose Coupling)… |
| 212 | [`erp212_file_storage`](application/modules/erp212_file_storage/README.md) | 5062 | 2 | `erp_212_file_links`, `erp_212_file_permissions`, `erp_212_files`, `erp_212_storage_backends` | 0 | 0 | Модуль Erp212_Storage предоставляет функциональность для работы с файловым хранилищем в системе Erp212. Он включает в себя API для загрузки, получения метаданных и удаления файлов, а также контролле�… |
| 217 | [`erp217_notification_center`](application/modules/erp217_notification_center/README.md) | 5475 | 3 | `erp_217_entity_subscriptions`, `erp_217_event_types`, `erp_217_logs`, `erp_217_notification`, `erp_217_statistics`, `… ещё 3` | 0 | 0 | Модуль предназначен для централизованного управления уведомлениями пользователей через различные каналы (Email, Telegram, Push, Max). Система работает на основе событийной модели: модули генерируют… |
| 218 | [`erp218_module_manager`](application/modules/erp218_module_manager/README.md) | 8066 | 2 | `erp_218_dependencies`, `erp_218_llm_analysis`, `erp_218_modules`, `erp_218_version_history` | 0 | 0 | Module Manager — это централизованная система учета, инвентаризации и управления метаданными внутренних модулей ERP-системы на базе Kohana 3.3. |
| 221 | [`erp221_crm`](application/modules/erp221_crm/README.md) | 8521 | 1 | `erp_221_activities`, `erp_221_activities_history`, `erp_221_ai_analysis`, `erp_221_client_profiles`, `erp_221_contact_profiles`, `… ещё 6` | 6 | 23 | Модуль ведёт сделку от первого обращения до решения: где она стоит в воронке, сколько денег в работе и что менеджер уже сделал. Клиент при этом остаётся клиентом системы, а не «клиентом CRM»… |
| 227 | [`erp227_core_registry`](application/modules/erp227_core_registry/README.md) | 5989 | 2 | `erp_227_embeddings`, `erp_227_events`, `erp_227_object_links`, `erp_227_object_types`, `erp_227_objects`, `… ещё 2` | 0 | 0 | Универсальный реестр объектов ERP системы. Модуль предоставляет централизованное хранилище для любых сущностей (оборудование, ТМЦ, услуги, сотрудники) с поддержкой версионности, иерархии и гибридного… |
| 228 | [`erp228_tech_cards`](application/modules/erp228_tech_cards/README.md) | 3713 | 3 | `erp_228_tech_cards`, `erp_228_tech_documents`, `erp_228_tech_operations` | 0 | 19 | Модуль превращает исходник изделия (параметрическая модель, код OpenSCAD или описание) в техпроцесс: последовательность операций с нормами времени, стоимостью и технологическими параметрами. Это… |
| 230 | [`erp230_mdm`](application/modules/erp230_mdm/README.md) | 15984 | 3 | `baselines`, `code_series`, `object_codes`, `object_embeddings`, `object_links`, `… ещё 9` | 0 | 2 | Модуль Master Data Management (MDM) предназначен для консолидации, очистки и верификации мастер-данных по изделиям и компонентам холдинга. Система обеспечивает создание «Золотой записи» (Golden… |
| 231 | [`erp231_product_structure`](application/modules/erp231_product_structure/README.md) | 441 | 1 | `erp_231_changes`, `erp_231_guard_approved`, `erp_231_lines`, `erp_231_lines_immutable`, `erp_231_mappings`, `… ещё 1` | 6 | 4 | Операционный владелец версионируемых составов изделия. Полный архитектурный контракт: [docs/EBOM_MBOM_ARCHITECTURE.md](../../../docs/EBOM_MBOM_ARCHITECTURE.md). |
| 301 | [`erp301_counterparty`](application/modules/erp301_counterparty/README.md) | 3955 | 4 | `erp_301_contacts`, `erp_301_contracts`, `erp_301_counterparties`, `erp_301_requisites`, `if` | 2 | 39 | Модуль ведёт «коммерческую сторону» производства: кто поставляет материалы и комплектующие, кто покупает готовую продукцию, кто перевозит и кто выполняет работы. Модуль не владеет номенклатурой… |
| 310 | [`erp310_workforce`](application/modules/erp310_workforce/README.md) | 1556 | 5 | `erp_310_assignments`, `erp_310_departments`, `erp_310_employees`, `erp_310_positions`, `erp_310_staff_units` | 8 | 19 | Модуль разделяет три понятия, которые нельзя хранить одной записью: |
| 401 | [`erp401_wms`](application/modules/erp401_wms/README.md) | 4556 | 3 | `erp_401_balances`, `erp_401_movements`, `erp_401_reservations`, `erp_401_warehouses` | 3 | 18 | Модуль ведёт количественный учёт номенклатуры по складам: остатки, движения, резервы и контроль минимумов. Это первый модуль слоя Operations — в системе до него не было ни складов, ни операций, ни… |
| 402 | [`erp402_production`](application/modules/erp402_production/README.md) | 5255 | 3 | `erp_402_order_items`, `erp_402_orders` | 19 | 30 | Модуль ведёт производственные заказы: состав изделия из технологической карты, резерв и списание материалов, выпуск готовой продукции на склад. |
| 403 | [`erp403_procurement`](application/modules/erp403_procurement/README.md) | 11174 | 3 | `erp_403_order_lines`, `erp_403_orders`, `erp_403_prices`, `erp_403_receipt_lines`, `erp_403_receipts`, `… ещё 3` | 29 | 16 | Отвечает на вопрос «что, у кого и по какой цене куплено, и что из этого уже приехало на склад». Модуль закрывает разрыв между потребностью и складом: |
| 404 | [`erp404_documents`](application/modules/erp404_documents/README.md) | 5991 | 5 | `erp_404_documents`, `erp_404_prints`, `erp_404_templates` | 13 | 11 | Модуль отвечает на вопрос «что мы отдали на бумагу и чем это подтверждается»: шаблоны печатных форм, выпуск документов, журнал печати, архив. |
| 405 | [`erp405_quality`](application/modules/erp405_quality/README.md) | 6294 | 3 | `erp_405_decisions`, `erp_405_defect_codes`, `erp_405_defects`, `erp_405_inspections` | 19 | 18 | Отвечает на вопрос «годится ли эта партия и что делать с браком» — и доводит ответ до склада. Модуль закрывает разрыв между «пришло/сделали» и «можно пускать в дело»: |
| 407 | [`erp407_planning`](application/modules/erp407_planning/README.md) | 6596 | 3 | `erp_407_program_lines`, `erp_407_programs`, `erp_407_proposals`, `erp_407_requirements` | 15 | 14 | Отвечает на вопрос «что, сколько и когда закупить или выпустить». Модуль не заводит свои справочники и заказы: он берёт спрос (программу и заказы производства), состав (техкарты erp228), покрытие… |
| 408 | [`erp408_shopfloor`](application/modules/erp408_shopfloor/README.md) | 7870 | 3 | `erp_408_calendar`, `erp_408_operations`, `erp_408_shifts`, `erp_408_work_centers`, `erp_408_worklogs` | 19 | 22 | Модуль закрывает разрыв между «заказ в производстве» (erp402) и «сколько реально сделано у станка». Он отвечает на вопросы мастера и начальника цеха: |
| 409 | [`erp409_costing`](application/modules/erp409_costing/README.md) | 134 | 1 | `erp_409_calculations`, `erp_409_lines` | 2 | 3 | Модуль считает версионируемый план/факт производственного заказа и не является бухгалтерской книгой. |
| 410 | [`erp410_shipment`](application/modules/erp410_shipment/README.md) | 11060 | 3 | `erp_410_customers`, `erp_410_invoice_lines`, `erp_410_invoices`, `erp_410_order_lines`, `erp_410_orders`, `… ещё 3` | 14 | 31 | Отвечает на вопрос «что и по какой цене продано клиенту, что из этого уже уехало со склада и какие деньги за это пришли». Модуль закрывает конец производственного контура: |
| 412 | [`erp412_traceability`](application/modules/erp412_traceability/README.md) | 330 | 3 | `erp_412_allocations`, `erp_412_events`, `erp_412_events_immutable`, `erp_412_genealogy`, `erp_412_lots`, `… ещё 2` | 8 | 10 | Модуль отвечает на два пилотных вопроса дискретного производства: |
| 801 | [`erp801_analytics`](application/modules/erp801_analytics/README.md) | 7366 | 1 | `erp_801_settings`, `erp_801_snapshots` | 15 | 15 | Модуль отвечает на вопрос «что происходит с заводом и что с этим делать»: оборачиваемость и покрытие запасов, ABC/XYZ, OEE и Парето простоев, Парето брака, просрочка закупок, снимки отчётов и пороги… |
| 802 | [`erp802_integration`](application/modules/erp802_integration/README.md) | 334 | 1 | `erp_802_mappings`, `erp_802_package_rows`, `erp_802_packages`, `erp_802_sources` | 5 | 5 | Модуль принимает ограниченные по размеру CSV/JSON-пакеты, ведёт неизменяемый журнал и связывает внешние идентификаторы с UUID PromERP. Первая намеренно узкая граница — номенклатура erp227; это основа… |
| 804 | [`erp804_mcp`](application/modules/erp804_mcp/README.md) | 4994 | 1 | `erp_804_mcp_calls`, `erp_804_mcp_keys` | 7 | 48 | Модуль даёт LLM-агентам (Claude Desktop, Cursor, Continue, собственные агенты) и внешним программам доступ к сервисам системы по протоколу [MCP](https://modelcontextprotocol.io) — «Model Context… |
| 811 | [`erp811_demo`](application/modules/erp811_demo/README.md) | 9314 | 1 | `erp_811_demo` | 5 | 47 | Внедрение производственного контура «с нуля» на живом заводе идёт месяцами: пока цех не наполнит систему данными, показать нечего — на экранах пусто, а решения принимать не на чем. Поэтому система… |
| 812 | [`erp812_docs`](application/modules/erp812_docs/README.md) | 2941 | 0 | — | 3 | 10 | Модуль предоставляет визуальную интерактивную архитектурную и пользовательскую документацию PromERP: |

## Ядро и служебные модули (13)

Это каркас (Konine/Kohana), библиотеки и сервисные модули — не доменные. Их таблицы и сервисы доменным модулям недоступны напрямую.

| Модуль | Статус | Строк | Назначение |
| :--- | :--: | --: | :--- |
| `PostgreSQL` | включён | 385 | — |
| `PostgreSQL_PDO` | выключен | 136 | — |
| `auth` | включён | 318 | Kohana auth module --- |
| `cache` | включён | 4724 | Kohana Cache library ==================== |
| `cookie_consent` | включён | 198 | Модуль для Kohana 3.3, который реализует всплывающее окно с уведомлением о файлах cookie, соответствующее требованиям законодательства о конфиденциальности данных. Окно появляется при первом… |
| `database` | включён | 4609 | — |
| `email` | включён | 278 | — |
| `encrypt` | включён | 470 | — |
| `llm` | включён | 2141 | Универсальный сервис для работы с Large Language Models (OpenAI, OpenRouter, Local LLM и др.) через единый интерфейс. |
| `minion` | включён | 945 | Minion is a framework for running tasks via the CLI. |
| `orm` | включён | 4462 | — |
| `pagination` | включён | 560 | Pagination module for Koseven |
| `yandexauth` | выключен | 375 | Этот модуль предоставляет функциональность для аутентификации пользователей через Яндекс.OAuth в фреймворке Kohana 3.3. |
