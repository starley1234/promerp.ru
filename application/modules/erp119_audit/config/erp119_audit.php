<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Настройки журнала действий (erp119_audit).
 *
 * Копия файла в `application/config/erp119_audit.php` перекрывает эти значения
 * (каскад конфигов Konine) — на боевом контуре так задают срок хранения.
 */
return array(

    // -------------------------------------------------------------------------
    // Интерфейс
    // -------------------------------------------------------------------------
    'items_per_page'     => 30,   // записей на странице ленты
    'recent_limit'       => 8,    // записей в виджете «последние действия»
    'default_period_days'=> 30,   // период сводки по умолчанию
    'export_limit'       => 100000,

    // Часовой пояс для календарных фильтров; NULL — пояс приложения.
    // Время в журнале хранится с зоной (TIMESTAMPTZ), поэтому «по дату»
    // означает конец календарного дня именно в этом поясе.
    'timezone' => NULL,

    // -------------------------------------------------------------------------
    // Хранение и обслуживание секций (erp_119_audit_log_ГГГГ_ММ)
    // -------------------------------------------------------------------------
    'storage' => array(
        'months_back'      => 6,   // сколько месяцев назад держать готовыми
        'months_ahead'     => 3,   // сколько месяцев вперёд создавать заранее
        'retention_months' => 36,  // срок хранения; NULL — хранить бессрочно
    ),

    // -------------------------------------------------------------------------
    // Что и как пишем
    // -------------------------------------------------------------------------
    // Длины строк обрезаются, чтобы одна запись не ломала вставку
    'lengths' => array(
        'module'       => 32,
        'entity_type'  => 64,
        'entity_id'    => 64,
        'entity_label' => 255,
        'action'       => 32,
        'actor_name'   => 128,
        'actor_ip'     => 45,
        'request_id'   => 64,
        'route'        => 255,
        'message'      => 2000,
    ),

    // Максимум полей «до/после» в одной записи (остальное — пометкой «*»)
    'max_changes' => 50,

    // Секреты в журнал не попадают: ни в значениях, ни в JSON-структурах
    'redact' => array(
        'mask'    => '***',
        'keys'    => array('password', 'password_confirm', 'cvv', 'pin', 'secret', 'api_key', 'private_key', 'card_number'),
        // Правило намеренно широкое: tokenizer_name тоже попадёт под маску —
        // «лишняя» маска дешевле утечки
        'pattern' => '/(password|passwd|secret|token|api[_-]?key|private[_-]?key|cvv|card[_-]?number|pin[_-]?code)/i',
    ),

    // Поля-идентификаторы (по порядку) и поля «человеческого» имени объекта
    'id_fields'    => array('uuid', 'id', 'object_id', 'entity_id', 'document_id', 'card_id', 'order_id', 'contract_id', 'counterparty_id', 'user_id'),
    'label_fields' => array('title', 'name', 'label', 'number', 'code', 'fio', 'username', 'email'),

    // Важность действия (подсветка и фильтр «только важное»)
    'severity' => array(
        'create'      => 'info',
        'update'      => 'info',
        'send'        => 'info',
        'read'        => 'notice',
        'status'      => 'notice',
        'approve'     => 'notice',
        'login'       => 'notice',
        'logout'      => 'info',
        'export'      => 'notice',
        'maintenance' => 'critical',
        'delete'      => 'warning',
        'archive'     => 'warning',
        'fail'        => 'warning',
    ),
    'severity_default' => 'info',

    // Приоритет важности из тела события: payload['severity'] перебивает таблицу
    'severity_levels' => array('info', 'notice', 'warning', 'critical'),

    // -------------------------------------------------------------------------
    // Разбор событий шины (erp208_events)
    // -------------------------------------------------------------------------
    // Префиксы событий, которые в журнал не переносятся: сам журнал, шина и
    // миграции — это служебный шум, а не действия с данными
    'ignore_events' => array(
        'erp119',
        'erp208',
        'erp111',
    ),

    // Явные правила: имя события → модуль, сущность, действие.
    // Всё остальное разбирается по конвенции `<модуль>.<сущность>.<действие>`.
    'event_rules' => array(
        'erp228.card.created'  => array('module' => 'erp228', 'entity_type' => 'tech_card', 'action' => 'create'),
        'erp228.card.updated'  => array('module' => 'erp228', 'entity_type' => 'tech_card', 'action' => 'update'),
        'erp228.card.deleted'  => array('module' => 'erp228', 'entity_type' => 'tech_card', 'action' => 'delete'),
        'erp228.card.status'   => array('module' => 'erp228', 'entity_type' => 'tech_card', 'action' => 'status'),
        'erp228.card.document' => array('module' => 'erp228', 'entity_type' => 'tech_document', 'action' => 'create'),
        'erp228.card.viewed'   => array('module' => 'erp228', 'entity_type' => 'tech_card', 'action' => 'read'),

        // События остальных модулей: явное правило нужно там, где имя сущности
        // в журнале должно быть понятнее сегмента события, и там, где
        // идентификатор лежит в «своём» поле (movement_id, deal_id).
        'erp301.counterparty.created'   => array('module' => 'erp301', 'entity_type' => 'counterparty', 'action' => 'create'),
        'erp301.contract.created'       => array('module' => 'erp301', 'entity_type' => 'contract', 'action' => 'create'),
        'erp401.stock_received'         => array('module' => 'erp401', 'entity_type' => 'movement', 'action' => 'create', 'id_field' => 'movement_id'),
        'erp401.stock_issued'           => array('module' => 'erp401', 'entity_type' => 'movement', 'action' => 'create', 'id_field' => 'movement_id'),
        'erp401.reservation_created'    => array('module' => 'erp401', 'entity_type' => 'stock_reservation', 'action' => 'create', 'id_field' => 'reservation_id'),
        'erp401.reservation_consumed'   => array('module' => 'erp401', 'entity_type' => 'stock_reservation', 'action' => 'consume', 'id_field' => 'reservation_id'),
        'erp401.reservation_released'   => array('module' => 'erp401', 'entity_type' => 'stock_reservation', 'action' => 'release', 'id_field' => 'reservation_id'),
        'erp401.stock_adjusted'         => array('module' => 'erp401', 'entity_type' => 'movement', 'action' => 'update', 'id_field' => 'movement_id'),
        'erp401.stock_transferred'      => array('module' => 'erp401', 'entity_type' => 'movement', 'action' => 'create', 'id_field' => 'movement_id'),
        'erp230.object.created'         => array('module' => 'erp230', 'entity_type' => 'dictionary', 'action' => 'create'),
        'erp230.baseline.created'       => array('module' => 'erp230', 'entity_type' => 'baseline', 'action' => 'create'),
        'erp221.deal.stage_changed.api' => array('module' => 'erp221', 'entity_type' => 'deal', 'action' => 'update', 'id_field' => 'deal_id'),
    ),

    // Глагол события → действие журнала (то, что не выводится правилом «-ed → -е»)
    'verbs' => array(
        'created'        => 'create',
        'updated'        => 'update',
        'saved'          => 'update',
        'changed'        => 'update',
        'status_changed' => 'update',
        'deleted'        => 'delete',
        'removed'        => 'delete',
        'archived'       => 'delete',
        'failed'         => 'fail',
        'sent'           => 'send',
        'approved'       => 'approve',
        'viewed'         => 'read',
        'dashboard_viewed' => 'read',
        'published'      => 'publish',
        'logged_in'      => 'login',
        'logged_out'     => 'logout',
    ),

    // Категории «старых» вызовов Audit::log() → модуль журнала
    'category_modules' => array(
        'module_manager' => 'erp218',
        'module'         => 'erp218',
        'notification'   => 'erp217',
        'email'          => 'erp217',
        'telegram'       => 'erp217',
        'max'            => 'erp217',
        'file'           => 'erp212',
        'stock'          => 'erp401',
        'counterparty'   => 'erp301',
    ),

    // Человеческие названия модулей (для интерфейса, если нет настройки аудита)
    'module_labels' => array(
        'erp100'  => 'Пользователи и роли',
        'erp119'  => 'Журнал действий',
        'erp208'  => 'Шина событий',
        'erp212'  => 'Файлы',
        'erp217'  => 'Уведомления',
        'erp218'  => 'Менеджер модулей',
        'erp227'  => 'Реестр объектов',
        'erp228'  => 'Технологические карты',
        'erp230'  => 'Мастер-данные',
        'erp301'  => 'Контрагенты',
        'erp401'  => 'Склад',
        'app'     => 'Приложение',
    ),

    // -------------------------------------------------------------------------
    // Страховка: если запись в БД не прошла, данные уходят в файл (JSON-строки),
    // а задача `erp119:sync` переносит их в БД при следующем запуске
    // -------------------------------------------------------------------------
    'fallback' => array(
        'enabled'     => TRUE,
        'file'        => 'audit-fallback.log',
        'limit_bytes' => 5242880,
        // TRUE — сбой записи считается ошибкой операции (тесты, контуры,
        // где потеря записи аудита недопустима). По умолчанию журнал молчит.
        'throw'       => FALSE,
    ),
);
