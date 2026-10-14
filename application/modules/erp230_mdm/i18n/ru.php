<?php

return [
    // Общие заголовки и навигация
    'mdm_title' => 'MDM: Единый реестр эталонных данных',
    'mdm_dashboard' => 'Панель управления MDM',
    'mdm_objects' => 'Объекты и изделия',
    'mdm_dictionaries' => 'Управление справочниками',
    'mdm_stewardship' => 'Арбитраж данных (Data Stewardship)',
    
    // Консолидация и Интеграция (Data Integration)
    'integration_sources' => 'Источники данных',
    'integration_xref' => 'Кросс-референсы (Xref)',
    'integration_sync_status' => 'Статус синхронизации',
    'integration_external_id' => 'ID во внешней системе',
    'integration_last_update' => 'Дата последнего обновления',
    'integration_mapping_error' => 'Ошибка сопоставления полей',

    // Золотая запись (Golden Record)
    'golden_record' => 'Золотая запись',
    'golden_record_status' => 'Статус эталона',
    'trust_score' => 'Балл доверия (Trust Score)',
    'attribute_priority' => 'Приоритет атрибута',
    'source_reliability' => 'Надежность источника',
    'master_value' => 'Эталонное значение',
    'conflict_detected' => 'Обнаружен конфликт данных',

    // Арбитраж (Data Stewardship)
    'steward_pending_tasks' => 'Задачи на рассмотрении',
    'steward_resolve_conflict' => 'Разрешить конфликт',
    'steward_manual_override' => 'Ручное переопределение',
    'steward_decision_history' => 'История решений арбитра',
    'steward_assigned_to' => 'Ответственный стюард',

    // Справочники (Dictionary)
    'dict_types_hierarchy' => 'Иерархия типов изделий',
    'dict_uom' => 'Единицы измерения (UOM)',
    'dict_category' => 'Категория',
    'dict_attributes_config' => 'Конфигурация атрибутов',

    // Поиск и Навигация
    'search_global_placeholder' => 'Глобальный поиск (включая семантический)...',
    'search_llm_index' => 'Поиск через LLM-индекс',
    'search_vector_match' => 'Векторное совпадение',
    'bom_visualization' => 'Визуализация структуры (BOM)',
    'bom_tree_view' => 'Дерево состава изделия',

    // Статусы верификации
    'status_verified' => 'Верифицировано',
    'status_unverified' => 'Не верифицировано',
    'status_pending' => 'Ожидает проверки',
    'status_rejected' => 'Отклонено',
    'status_merged' => 'Объединено',

    // Ошибки и уведомления
    'error_duplicate_found' => 'Обнаружен дубликат записи',
    'error_low_trust_score' => 'Низкий уровень доверия к данным для автоматического слияния',
    'error_invalid_uom' => 'Недопустимая единица измерения для данного типа объекта',
    'error_xref_broken' => 'Нарушена связь с внешним источником',
    'success_golden_record_created' => 'Золотая запись успешно сформирована',
    'success_sync_complete' => 'Синхронизация данных завершена успешно',
];