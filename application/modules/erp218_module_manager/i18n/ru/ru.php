<?php defined('SYSPATH') OR die('No direct script access.');

return array(
    // Заголовки и основные названия
    'module_manager_title' => 'Менеджер модулей (Реестр)',
    'module_registry'      => 'Реестр модулей',
    'module_details'       => 'Детали модуля',
    'all_modules'          => 'Все модули',

    // Поля таблицы и форм
    'system_name'     => 'Системное имя',
    'display_name'    => 'Название модуля',
    'description'     => 'Описание',
    'version'         => 'Версия',
    'status'          => 'Статус',
    'is_published'    => 'Опубликован',
    'developer_notes' => 'Заметки разработчика',
    'last_update'     => 'Последнее обновление',
    'dependencies'    => 'Зависимости',
    'path'            => 'Путь к директории',

    // Статусы (Enum)
    'status_alpha'      => 'Альфа (Разработка)',
    'status_beta'       => 'Бета (Тестирование)',
    'status_stable'     => 'Стабильный (Production)',
    'status_deprecated' => 'Устаревший',
    'status_archived'   => 'Архив',

    // Стадии готовности (Дополнительно)
    'development' => 'Разработка',
    'testing'     => 'Тестирование',
    'production'  => 'Эксплуатация',

    // Кнопки и действия
    'action_scan'      => 'Сканировать директорию',
    'action_register'  => 'Зарегистрировать',
    'action_edit'      => 'Редактировать',
    'action_save'      => 'Сохранить изменения',
    'action_view_doc'  => 'Просмотр документации',
    'action_refresh'   => 'Обновить метаданные',

    // Сообщения системы
    'msg_scan_success'    => 'Сканирование завершено. Обнаружены новые модули.',
    'msg_save_success'    => 'Метаданные модуля успешно обновлены.',
    'msg_not_found'       => 'Модуль не найден.',
    'msg_llm_processing'  => 'Обработка описания через LLM...',
    'msg_no_dependencies' => 'Зависимости не указаны.',

    // Прочее
    'visibility_on'  => 'Активен',
    'visibility_off' => 'Скрыт',
    'readme_content' => 'Содержимое README.md',
    'semver_format'  => 'Формат SemVer',
);