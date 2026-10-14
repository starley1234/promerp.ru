# erp412_traceability — партии, serial и genealogy

Модуль отвечает на два пилотных вопроса дискретного производства:

1. из каких входных партий получен конкретный выпуск;
2. какие выпуски затронуты дефектной или отозванной партией поставщика.

## Граница ответственности

`erp401_wms` остаётся единственным владельцем количественных складских движений и общего остатка. `erp412` хранит **идентичность** lot/serial и allocations проведённых движений по партиям. Поле `qty_trace` — контрольная сумма allocations, а не второй бухгалтерский остаток.

Мягкие ссылки связывают партии с номенклатурой `erp227`, движениями `erp401`, заказами `erp402`, операциями `erp408` и контролями `erp405`. Внешние модули не получают FK на таблицы traceability.

## Модель

- `erp_412_lots`: lot/serial, номенклатура, источник, поставщик, даты и статусы `available/quarantine/consumed/scrapped/recalled`.
- `erp_412_allocations`: разложение WMS movement на `receipt/issue/transfer_out/transfer_in/adjust_*`; сумма одного направления не может превысить движение WMS.
- `erp_412_genealogy`: ориентированное происхождение `input → output`, relation `consume/split/merge/rework/substitute`, заказ, операция и количества. Циклы запрещены сервисом.
- `erp_412_quality_links`: связь партии и контроля качества.
- `erp_412_events`: append-only история статусов, allocations и связей. Сами allocations и genealogy также append-only; исправление выполняется компенсирующим фактом, а не переписыванием прошлого.

При `erp401.stock_received` автоматически создаётся партия с идемпотентным source key `erp401_movement/<id>` и полный receipt allocation. Это сразу покрывает закупочную приёмку и выпуск `erp402`, потому что оба проводят WMS receipt. Расход и transfer требуют осознанного выбора lot; заблокированную, recalled или quarantined партию расходовать нельзя.

Связанный контроль `erp405` автоматически переводит партию в карантин, разрешает или списывает по соответствующим событиям. Статусный переход всегда требует причины. Durable-факты lot creation, genealogy, quarantine и recall помещаются в outbox `erp208`.

## Интерфейсы

- `/traceability` — поиск по партии, serial, коду/названию и создание со smart-выбором номенклатуры.
- `/traceability/view/<uuid>` — карточка, распределение по складам, genealogy, качество, downstream impact и smart-форма фактического материала выпуска.
- `/traceability/label/<uuid>` — офлайн-печатная этикетка 100×55 мм с настоящим QR, human-readable номером и UUID fallback. QR ведёт на карточку; библиотека `qrcode-generator` закреплена npm lock-файлом, собирается локально и не использует CDN.
- JSON API `/traceability/api`: `lots`, `get`, `genealogy`, POST `create`, `from_movement`, `allocate`, `link`, `production`, `quality`, `transition`.
- ACL: `erp412.view`, `erp412.manage`, `erp412.quality`, `erp412.recall`.
- Миграция запускается общей административной системой `/migrate_pg`.

## MCP и AI boundary

Read-only tools: `traceability.lots`, `traceability.genealogy`, `traceability.affected_outputs`. Изменения (`create_lot`, `link`, `production_record`, `status`, `recall`) требуют approval; recall дополнительно помечен destructive. Для производственного факта агент сначала находит обе партии и заказ, затем предлагает `production_record`: сервис сам сверяет изделие, проведённое списание WMS и доступную lot allocation; повтор того же факта идемпотентен. Агент обязан передавать найденный UUID, а не угадывать внутренний ID.

AI объясняет impact и предлагает действие, но не становится источником истины. Genealogy создаётся только по фактическому материальному потреблению, подтверждённому оператором или интеграционным контрактом.
