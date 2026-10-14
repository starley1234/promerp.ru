# Документация PromERP: что читать и когда

Указатель по `docs/`. Правило одно: **читать только то, что нужно для текущей
задачи**. Часть документов — исторические (спринты, ревью прошлых веток): они
объясняют решения, но не описывают текущее состояние.

## Минимум для агента

| Шаг | Документ | Зачем |
| :-- | :--- | :--- |
| 1 | [`AGENTS.md`](../AGENTS.md) §0, §3 | правила, которые нельзя нарушать |
| 2 | [`docs/AGENT_WORKFLOW.md`](AGENT_WORKFLOW.md) | цикл работы по одному модулю |
| 3 | `bin/console module:context --module=X` | контекст конкретного модуля |
| 4 | `README.md` модуля | контракт и эксплуатация модуля |

Дальше — по необходимости, а не «для общего развития».

## Рабочие документы (источники истины)

| Документ | Когда читать | Объём |
| :--- | :--- | :--- |
| [`AGENT_WORKFLOW.md`](AGENT_WORKFLOW.md) | перед правкой любого модуля | 1 экран |
| [`MODULE_MAP.md`](MODULE_MAP.md) | найти модуль-владельца и соседей (генерируется) | таблица |
| [`MODULE_GUIDE.md`](MODULE_GUIDE.md) | создание модуля, каркас, соглашения | 2 экрана |
| [`MODULE_BACKLOG.md`](MODULE_BACKLOG.md) | что делать дальше по модулям, оценки | 3 экрана |
| [`DEVELOPMENT.md`](DEVELOPMENT.md) | среда, команды, отладка, регламент | 3 экрана |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | общая схема и решения по модулям (§6–§16) | карта + модуль |
| [`PRODUCT_STRATEGY_RUSSIA_2026.md`](PRODUCT_STRATEGY_RUSSIA_2026.md) | продуктовые приоритеты и рынок | большой |
| [`PROJECT_STATE_AND_PLAN_2026-09-17.md`](PROJECT_STATE_AND_PLAN_2026-09-17.md) | зрелость контура и план доработок | 2 экрана |
| [`DEVELOPMENT_PLAN_2026.md`](DEVELOPMENT_PLAN_2026.md) | план на 12 недель до пилота | 2 экрана |
| [`DEPLOY_AND_ROADMAP.md`](DEPLOY_AND_ROADMAP.md) | путь в продакшен, эксплуатация | 3 экрана |
| [`IMPROVEMENTS.md`](IMPROVEMENTS.md) | реестр технического долга (что закрыто, что нет) | большой |
| [`REVIEW_2026-10-02.md`](REVIEW_2026-10-02.md) | разбор 2026-10-02: что исправлено, что осталось, предложения | 2 экрана |
| [`PLAN_2026-10.md`](PLAN_2026-10.md) | план работ: этапы, критерии, что нужно решить | 2 экрана |
| [`MCP.md`](MCP.md) | агентский протокол, ключи, discovery | 3 экрана |
| [`MCP_AGENT_PLAYBOOKS.md`](MCP_AGENT_PLAYBOOKS.md) | сценарии QA и настройки через MCP | 2 экрана |
| [`FRONTEND_STATUS.md`](FRONTEND_STATUS.md) | измеряемый UI-долг | 1 экран |
| [`ACCESS_SCOPE_ARCHITECTURE.md`](ACCESS_SCOPE_ARCHITECTURE.md) | роли, области доступа, scope-фильтры | 1 экран |
| [`EBOM_MBOM_ARCHITECTURE.md`](EBOM_MBOM_ARCHITECTURE.md) | составы изделия и ревизии | 1 экран |
| [`LLM_ASSISTED_DATA_ENTRY.md`](LLM_ASSISTED_DATA_ENTRY.md) | AI-ввод данных, черновики и подтверждение | 1 экран |
| [`SIMPLE_ADVANCED_MODES_AND_AGENT_TESTING.md`](SIMPLE_ADVANCED_MODES_AND_AGENT_TESTING.md) | простой/расширенный режимы, тест агентом | 2 экрана |

## Генерируемое (руками не править)

| Что | Как обновить | Проверка |
| :--- | :--- | :--- |
| Таблица ERP-модулей в корневом `README.md` | `make docs` | `make docs-check` |
| Инвентарь модулей в [`ARCHITECTURE.md`](ARCHITECTURE.md) §2 | `make docs` | `make docs-check` |
| [`MODULE_MAP.md`](MODULE_MAP.md) | `make docs` | `make docs-check` |

`make docs-check` входит в `make check`; тест `DocsSyncTest` падает, если
сгенерированный блок разошёлся с кодом.

## Архив: спринты, ревью, outlook

Эти документы — история решений. Читать стоит только при разборе «почему так
сделано»; текущее состояние они не описывают.

| Документ | О чём |
| :--- | :--- |
| [`ARCHITECTURE_REVIEW.md`](ARCHITECTURE_REVIEW.md) | разбор архитектуры на сентябрь 2026 |
| [`FRONTEND_REVIEW.md`](FRONTEND_REVIEW.md) | разбор интерфейса (2026-09-16) |
| [`PRODUCT_OUTLOOK.md`](PRODUCT_OUTLOOK.md) | мнение о перспективах (исторический outlook) |
| [`SPRINT_2026-09-15.md`](SPRINT_2026-09-15.md), `SPRINT_S2_*`…`SPRINT_S6_*`, `SPRINT_ERP409_COSTING.md` | отчёты спринтов по модулям |
