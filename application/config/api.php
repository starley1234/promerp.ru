<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контракт JSON API: какие действия только читают.
 *
 * Зачем этот файл. У изменяющих действий API есть метод: их вызывают POST-ом
 * (PUT/DELETE — по смыслу). Действия чтения — GET-ом. По имени действия это
 * свойство не угадывается: `request` читает одну заявку, `request_create` её
 * создаёт, и любой префиксный разбор здесь ошибается. Поэтому список явный, а
 * по умолчанию действие считается **изменяющим** — так безопаснее: забытый в
 * списке `stats` отвечает 405 и это видно, а забытый `delete` ничего не удалит
 * по GET-запросу из ссылки или краулера.
 *
 * Кто этим пользуется:
 *  * `Controller_Base::execute()` — отдаёт 405 `method_not_allowed`, если
 *    изменяющее действие вызвано GET-ом (внутренние вызовы не затронуты);
 *  * `Service_Dev_Smoke::api_urls()` — дымовой прогон ходит **только** по этим
 *    действиям: изменяющие вызовы «на всякий случай» создавали бы документы;
 *  * `dev:audit --check=api_read_only` — сверяет список с кодом: записи без
 *    действия в коде (устаревшие) и записи, чьё действие читает POST-данные
 *    (значит, оно изменяющее и ему здесь не место).
 *
 * Правило для нового модуля: добавили действие чтения — впишите его сюда; не
 * вписали — API ответит 405, и это заметит сначала дымовой прогон, а потом
 * человек (лучше, чем тихо выполнить изменение по GET).
 *
 * @package    Erp
 * @category   Config
 */
return [

    /**
     * Имена действий (без префикса `action_`), которые ничего не меняют.
     * Сравнение точное: `request` не распространяется на `request_create`.
     */
    'read_only_actions' => [

        // ядро и платформа
        'me', 'roles', 'user', 'users', 'access',
        'employees', 'employee', 'history', 'positions', 'departments', 'staffing', 'reference',
        'status', 'inventory', 'orphans',
        'get_event', 'get_events',
        'stats', 'settings', 'search', 'view',
        'entity', 'partitions',
        'get_message_body', 'notification_status', 'unread_count',
        'get_metadata', 'list',
        'get_module_details', 'get_stats',
        'check', 'groups', 'links', 'menu',
        'collection', 'get', 'get_children', 'types',
        'data', 'suggest',

        // QR-маркировка (erp199_sqr): что показать по отсканированному коду,
        // поиск цели для привязки, каталог сценариев и реестры наклеек.
        // Действия, которые что-то записывают (bind, register, scan, photo,
        // save, moderate, inventory_open, inventory_close, inventory_scan),
        // здесь не перечислены намеренно — они обязаны быть POST-ом.
        'resolve', 'targets', 'scenarios', 'tokens', 'token', 'activity',

        // Продажи (erp221_crm): доска и список сделок, карточка сделки и клиента,
        // справочники воронки, лента работы, задачи, аналитика и база знаний.
        // Записи в сделку — только POST: deal_save, deal_stage, deal_delete,
        // activity_save, activity_delete, client_save, contact_save, ai_run.
        'board', 'deals', 'deal', 'clients', 'client', 'stages', 'journal',
        'tasks', 'analytics', 'kb',

        // данные и операции
        'attribute_history', 'dictionary', 'object', 'objects',
        'properties', 'state', 'state_at', 'xref',
        'card',
        'balances', 'items', 'movements', 'stock',
        'options', 'requirements',
        'board', 'centers', 'operation', 'operations', 'queue', 'shifts',
        'terminal', 'program', 'programs', 'proposals',
        // демонстрационный стенд (erp811_demo): состояние, сценарий, тур, состав
        'counts', 'runs', 'scenario', 'tour',
        'low_stock', 'order', 'orders', 'prices', 'receipt', 'receipts',
        'request', 'requests', 'supplier', 'supplier_compare', 'suppliers',

        // агентский слой (ключи агентов читаются, но выдаются только POST-ом)
        'call', 'calls', 'info', 'keys', 'tools',

        // качество (erp405_quality)
        'inspections', 'inspection', 'pending', 'codes', 'code', 'by_cause',

        // документы и печать (erp404_documents)
        'documents', 'document', 'prints', 'templates', 'template',
        'template_check', 'preview',

        // аналитика и витрины (erp801_analytics)
        'summary', 'sources', 'deficit', 'abc', 'production', 'quality',
        'purchases', 'snapshots', 'snapshot', 'settings', 'export',
    ],
];
