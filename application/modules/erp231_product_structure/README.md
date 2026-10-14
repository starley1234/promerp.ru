# erp231_product_structure — EBOM/MBOM

Операционный владелец версионируемых составов изделия. Полный архитектурный
контракт: [`docs/EBOM_MBOM_ARCHITECTURE.md`](../../../docs/EBOM_MBOM_ARCHITECTURE.md).

## Реализованный первый вертикальный срез

- отдельные ревизии `ebom`, `mbom`, `service` по изделию и площадке;
- строки-дерево, нормы, отходы, make/buy/phantom/consumable, операция и JSON
  применяемости/альтернатив;
- детерминированная проверка самоссылок и межуровневых циклов;
- жизненный цикл draft → review → approved → obsolete;
- неизменяемость строк утверждённой ревизии также защищена триггером БД;
- копирование утверждённой EBOM в MBOM с явным mapping строк;
- ECR/ECO и impact JSON зарезервированы в нормализованной схеме;
- операторский UI `/product-structures` со smart-выбором изделия/компонента,
  списком ревизий, строками, проверкой и lifecycle-действиями;
- MCP read/validate/draft инструменты; approve всегда проходит через approval.

## Границы

Номенклатура принадлежит `erp227`; техмаршрут — `erp228`; остатки — `erp401`.
`erp230` может быть staging внешнего PLM, но не является runtime-зависимостью.
Утверждённый состав нельзя исправить: создаётся следующая ревизия.

## Следующий срез

Mapping workbench, импорт staging, production-order snapshot в `erp402`,
diff ревизий и impact ECR/ECO. Пока
snapshot не подключён, нельзя заявлять полный production-ready EBOM/MBOM flow.

## Проверка

```bash
bin/console db:migrate
bin/test --filter=Erp231ProductStructureTest
```
