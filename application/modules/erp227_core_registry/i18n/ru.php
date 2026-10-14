<?php

return [
    // Название модуля и общие термины
    'module_name' => 'Универсальный реестр объектов',
    'module_description' => 'Центральное хранилище объектов, иерархий и связей системы',
    'registry' => 'Реестр',
    'objects' => 'Объекты',
    'object' => 'Объект',

    // Поля основной таблицы (objects)
    'field_id' => 'ID',
    'field_parent_id' => 'Родительский объект',
    'field_type_id' => 'Тип объекта',
    'field_code' => 'Код / Артикул',
    'field_name' => 'Наименование',
    'field_description' => 'Описание',
    'field_unit_id' => 'Единица измерения',
    'field_is_active' => 'Активен',
    'field_revision' => 'Ревизия',
    'field_is_latest' => 'Актуальная версия',
    'field_path' => 'Путь (LTREE)',
    'field_attribute_values' => 'Дополнительные атрибуты',
    'field_created_at' => 'Дата создания',
    'field_updated_at' => 'Дата обновления',
    'field_created_by' => 'Создал',
    'field_updated_by' => 'Обновил',

    // Типы объектов (примеры)
    'type_part' => 'Деталь',
    'type_assembly' => 'Сборочная единица',
    'type_employee' => 'Сотрудник',
    'type_equipment' => 'Оборудование',
    'type_workplace' => 'Рабочее место',
    'type_project' => 'Проект',
    'type_document' => 'Документ',

    // Связи (object_links)
    'links' => 'Связи',
    'link_type' => 'Тип связи',
    'link_bom' => 'Состав (BOM)',
    'link_process' => 'Технологический маршрут',
    'link_assignment' => 'Назначение',
    'link_location' => 'Расположение',
    'link_quantity' => 'Количество',
    'error_circular_dependency' => 'Ошибка: обнаружена циклическая зависимость',

    // Поиск и AI
    'search_placeholder' => 'Поиск по коду или названию...',
    'search_semantic' => 'Интеллектуальный поиск',
    'search_results' => 'Результаты поиска',
    'search_no_results' => 'Ничего не найдено',
    'search_priority_code' => 'Приоритет артикула',

    // Единицы измерения (units)
    'units' => 'Единицы измерения',
    'unit_name' => 'Название ЕИ',
    'unit_symbol' => 'Условное обозначение',
    'unit_is_integer' => 'Только целые значения',
    'error_unit_integer_violation' => 'Данная единица измерения не допускает дробных значений',

    // Иерархия и дерево
    'tree_view' => 'Древовидный вид',
    'expand_all' => 'Развернуть всё',
    'collapse_all' => 'Свернуть всё',
    'move_object' => 'Переместить в структуре',

    // Версионирование
    'version_history' => 'История ревизий',
    'create_new_revision' => 'Создать новую ревизию',
    'restore_revision' => 'Восстановить ревизию',

    // Аудит и события
    'events' => 'События',
    'event_log' => 'Журнал аудита',
    'event_type' => 'Тип события',
    'event_user' => 'Пользователь',
    'event_time' => 'Время события',

    // Настройки типов
    'schema_configuration' => 'Конфигурация схемы атрибутов',
    'ui_configuration' => 'Настройка интерфейса',
    'validation_rules' => 'Правила валидации',
];