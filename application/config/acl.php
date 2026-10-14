<?php defined('SYSPATH') or die('No direct script access.');

return [
    // Формат: 'право' => ['роли', 'которые', 'могут']
    
    // Глобальные права
    'admin.access'    => ['admin'],

    // Роль `demo` — гость демонстрационного стенда (модуль erp811_demo).
    // Ей выданы только права чтения: витрины, склад, заказы, цех, качество,
    // документы, справочники. Изменяющих прав у роли нет ни одного, и это
    // осознанно: демо-режим не должен менять данные даже при ошибке в
    // HTTP-защите. Права перечислены ниже, в списках своих модулей.
	
	// Модуль Файлы (erp212_storage)
	
	'erp212_storage.view_public_files'     => ['login'],
    'erp212_storage.view_files' => ['admin', 'manager', 'accountant', 'demo'],
    'erp212_storage.upload'    		=> ['admin', 'manager'],
    'erp212_storage.delete_file'   	=> ['admin'], // Только admin
    'erp212_storage.*'        		=> ['admin'], // Админ может всё
    
    // Модуль «Контрагенты» (erp301_counterparty).
    // Роль demo видит справочник: контрагенты входят в демонстрационный сценарий
    // erp811_demo. Ключи модуля объявляются один раз — дубль блока с более узким
    // набором ролей молча отбирает у demo доступ (это уже случалось).
    'erp301.view' => ['admin', 'manager', 'accountant', 'demo'],
    'erp301.manage'  => ['admin', 'manager'],
    'erp301.delete'  => ['admin'],
    'erp301.*'       => ['admin'],

    // Модуль «Сотрудники и оргструктура» (erp310_workforce)
    // demo — гость публичного стенда (erp811_demo) читает оргструктуру; данные там вымышленные.
    'erp310.view'   => ['admin', 'manager', 'director', 'demo'],
    'erp310.manage' => ['admin'],
    'erp310.*'      => ['admin'],

    // Модуль «Технологические карты» (erp228_tech_cards)
    'erp228.view' => ['admin', 'manager', 'accountant', 'demo'],
    'erp228.manage'  => ['admin', 'manager'],
    'erp228.delete'  => ['admin'],
    'erp228.*'       => ['admin'],

    // Модуль «События» (erp208_events)
    'erp208_events.view_dashboard' => ['admin', 'manager', 'accountant', 'demo'],
    'erp208_events.view_events' => ['admin', 'manager', 'accountant', 'demo'],
    'erp208_events.view_event' => ['admin', 'manager', 'accountant', 'demo'],
    'erp208_events.list_events' => ['admin', 'manager', 'accountant', 'demo'],
    'erp208_events.view_latest_events_widget' => ['admin', 'manager', 'accountant', 'demo'],
    'erp208_events.create_event'              => ['admin', 'manager'],
    'erp208_events.edit_event'                => ['admin', 'manager'],
    'erp208_events.delete_event'              => ['admin'],
    'erp208_events.publish_event'             => ['admin', 'manager'],
    'erp208_events.publish_event_widget'      => ['admin', 'manager'],
    'erp208_events.list_subscribers'          => ['admin', 'manager'],
    'erp208_events.manage_subscription'       => ['admin', 'manager'],
    'erp208_events.manage_subscriptions'      => ['admin', 'manager'],
    'erp208_events.delete_subscription'       => ['admin'],
    'erp208_events.*'                         => ['admin'],

    // Модуль «Центр уведомлений» (erp217_notification_center)
    'erp217_notification_center.view' => ['admin', 'manager', 'accountant', 'login', 'demo'],
    'erp217_notification_center.edit'   => ['admin', 'manager'],
    'erp217_notification_center.delete' => ['admin'],
    'erp217_notification_center.*'      => ['admin'],

    // Модуль «Менеджер модулей» (erp218_module_manager)
    'module_manager.view'               => ['admin', 'manager'],
    'module_manager.edit_notes'         => ['admin', 'manager'],
    'module_manager.edit_metadata'      => ['admin'],
    'module_manager.edit_attributes'    => ['admin'],
    'module_manager.manage_settings'    => ['admin'],
    'module_manager.manage_status'      => ['admin'],
    'module_manager.manage_dependencies' => ['admin'],
    'module_manager.add_version_history' => ['admin'],
    'module_manager.restore'            => ['admin'],
    'module_manager.delete'             => ['admin'],
    'module_manager.scan'               => ['admin'],
    'module_manager.scan_directory'     => ['admin'],
    'module_manager.ai_analyze'         => ['admin'],
    'module_manager.analyze_llm'        => ['admin'],
    'module_manager.reset_llm_analysis' => ['admin'],
    'module_manager.*'                  => ['admin'],

    // Модуль «Реестр» (erp227_core_registry)
    'core_registry.view' => ['admin', 'manager', 'accountant', 'demo'],
    'core_registry.create'       => ['admin', 'manager'],
    'core_registry.edit'         => ['admin', 'manager'],
    'core_registry.link'         => ['admin', 'manager'],
    'core_registry.delete'       => ['admin'],
    'core_registry.manage_types' => ['admin'],
    'core_registry.units.view' => ['admin', 'manager', 'accountant', 'demo'],
    'core_registry.units.delete' => ['admin'],
    'core_registry.*'            => ['admin'],

    // Модуль «QR-маркировка» (erp199_sqr) — наклейки, свободные объекты,
    // полевые сценарии и инвентаризация. Роль demo видит только реестры:
    // сканирование и всё, что пишет данные, ей не выдано осознанно.
    // Тонкий клиент (приложение, терминал) ходит не под ролью, а под ключом
    // устройства из erp804_mcp — его права-скоупы проверяются отдельно.
    'erp199.view'                 => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.tokens.view'          => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.tokens.bind'          => ['admin', 'manager'],
    'erp199.tokens.issue'         => ['admin', 'manager'],
    'erp199.tokens.manage'        => ['admin', 'manager'],
    'erp199.objects.view'         => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.objects.create'       => ['admin', 'manager', 'worker'],
    'erp199.objects.edit'         => ['admin', 'manager'],
    'erp199.objects.moderate'     => ['admin', 'manager'],
    'erp199.scan'                 => ['admin', 'manager', 'accountant', 'worker'],
    'erp199.inventories.view'     => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.inventories.manage'   => ['admin', 'manager'],
    'erp199.scenarios.view'       => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.scenarios.manage'     => ['admin'],
    'erp199.types.view'           => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.types.manage'         => ['admin'],
    'erp199.activity.view'        => ['admin', 'manager', 'accountant', 'demo'],
    'erp199.*'                    => ['admin'],

    // Версионируемые составы изделия (erp231_product_structure).
    'erp412.view'    => ['admin', 'manager', 'accountant', 'demo'],
    'erp412.manage'  => ['admin', 'manager'],
    'erp412.quality' => ['admin', 'manager'],
    'erp412.recall'  => ['admin', 'manager'],

    'erp802.view'    => ['admin', 'manager', 'accountant', 'demo'],
    'erp802.import'  => ['admin', 'manager'],
    'erp802.retry'   => ['admin', 'manager'],

    'erp231.view'    => ['admin', 'manager', 'accountant', 'demo'],
    'erp231.manage'  => ['admin', 'manager'],
    'erp231.approve' => ['admin', 'manager'],
    'erp231.*'       => ['admin'],

    // Модуль «Мастер-данные» (erp230_mdm)
    'mdm.object.view' => ['admin', 'manager', 'accountant', 'demo'],
    'mdm.object.create'      => ['admin', 'manager'],
    'mdm.object.edit'        => ['admin', 'manager'],
    'mdm.object.delete'      => ['admin'],
    'mdm.dictionary.view' => ['admin', 'manager', 'accountant', 'demo'],
    'mdm.dictionary.edit'    => ['admin'],
    'mdm.steward.edit'       => ['admin', 'manager'],
    'mdm.baseline.create'    => ['admin', 'manager'],
    'mdm.audit.integrity'    => ['admin'],
    'mdm.admin.all'          => ['admin'],
    'mdm.*'                  => ['admin'],

    // Модуль «Журнал действий» (erp119_audit)
    'erp119_audit.view' => ['admin', 'manager', 'accountant', 'demo'],
    'erp119_audit.export' => ['admin', 'manager'],
    'erp119_audit.manage' => ['admin'],
    'erp119_audit.*'      => ['admin'],

    // Модуль «Складской учёт» (erp401_wms)
    'erp401_wms.view_dashboard' => ['admin', 'manager', 'accountant', 'demo'],
    'erp401_wms.view_stock' => ['admin', 'manager', 'accountant', 'demo'],
    'erp401_wms.manage_movements'   => ['admin', 'manager'],
    'erp401_wms.manage_warehouses'  => ['admin', 'manager'],
    'erp401_wms.reserve'            => ['admin', 'manager'],
    'erp401_wms.*'                  => ['admin'],

    // Модуль «Производство» (erp402_production)
    'erp402_production.view' => ['admin', 'manager', 'accountant', 'demo'],
    'erp402_production.manage'    => ['admin', 'manager'],
    'erp402_production.plan'      => ['admin', 'manager'],
    'erp402_production.complete'  => ['admin', 'manager'],
    'erp402_production.*'         => ['admin'],

    // Модуль «Цех» (erp408_shopfloor) — рабочие центры, смены, календарь,
    // операции заказов и терминал исполнителя.
    'erp408.view' => ['admin', 'manager', 'accountant', 'demo'], // загрузка цеха, операции, отчёты
    'erp408.manage'    => ['admin', 'manager'],               // маршруты, центры, смены, календарь
    'erp408.terminal'  => ['admin', 'manager', 'accountant'], // отметки по операциям (рабочее место)
    'erp408.*'         => ['admin'],

    // Себестоимость: чтение расчётов и человеческое утверждение версии.
    'erp409.view'      => ['admin', 'manager', 'accountant', 'demo'],
    'erp409.calculate' => ['admin', 'manager', 'accountant'],
    'erp409.approve'   => ['admin', 'manager'],
    'erp409.*'         => ['admin'],

    // Модуль «Планирование потребностей» (erp407_planning) — производственная
    // программа, расчёт потребности, дефициты, предложения закупки и выпуска.
    'erp407.view' => ['admin', 'manager', 'accountant', 'demo'], // программы, расчёт, предложения
    'erp407.manage'    => ['admin', 'manager'],               // правка программ, строк и предложений
    'erp407.plan'      => ['admin', 'manager'],               // утверждение программ и предложений
    'erp407.approve'   => ['admin', 'manager'],               // передача предложений в исполнение
    'erp407.*'         => ['admin'],

    // Модуль «Закупки» (erp403_procurement) — заявка, согласование, заказ
    // поставщику, приёмка с входным контролем и приход на склад.
    // Модуль «Документы и печать» (erp404_documents) — шаблоны форм, выпуск
    // документов, журнал печати.
    'erp404.view' => ['admin', 'manager', 'accountant', 'worker', 'demo'], // документы, шаблоны, журнал печати
    'erp404.print'     => ['admin', 'manager', 'accountant', 'worker'], // выпуск и печать
    'erp404.manage'    => ['admin', 'manager'],                         // шаблоны форм
    'erp404.*'         => ['admin'],

    // Модуль «Качество» (erp405_quality) — контроль партий, карантин, решения
    'erp405.view' => ['admin', 'manager', 'accountant', 'worker', 'demo'], // контроль, дефекты, статистика
    'erp405.inspect'   => ['admin', 'manager', 'worker'],               // завести контроль, записать дефекты
    'erp405.decide'    => ['admin', 'manager'],                         // решение по браку, карантин, утиль
    'erp405.manage'    => ['admin', 'manager'],                         // справочник кодов дефектов
    'erp405.*'         => ['admin'],

    'erp403.view' => ['admin', 'manager', 'accountant', 'demo'], // заявки, заказы, приёмки, поставщики, цены
    'erp403.manage'    => ['admin', 'manager'],               // черновики, позиции, отправка заказа, условия поставщика
    'erp403.approve'   => ['admin', 'manager'],               // согласование заявок и проведение приёмки
    'erp403.*'         => ['admin'],

    // Модуль «Отгрузка и продажи» (erp410_shipment) — заказ клиента, резерв и
    // сборка отгрузки, списание со склада, счёт и оплата. Отгрузка трогает
    // склад и деньги, поэтому право на неё — отдельное.
    'erp410.view'      => ['admin', 'manager', 'accountant', 'demo'], // заказы, отгрузки, счета, клиенты, прайс
    'erp410.manage'    => ['admin', 'manager'],               // заказы, позиции, сборка отгрузки, прайс, условия клиента
    'erp410.ship'      => ['admin', 'manager'],               // проведение отгрузки: расход со склада
    'erp410.invoice'   => ['admin', 'manager', 'accountant'], // выставление счетов и приём оплаты
    'erp410.*'         => ['admin'],

    // Модуль «Аналитика и витрины» (erp801_analytics) — сводка KPI,
    // оборачиваемость запасов с ABC/XYZ, OEE и простои, качество, закупки,
    // снимки отчётов и пороги витрин.
    'erp801.view' => ['admin', 'manager', 'accountant', 'worker', 'demo'], // витрины, снимки, пороги
    'erp801.export'    => ['admin', 'manager', 'accountant'],           // выгрузка во внешний файл
    'erp801.manage'    => ['admin', 'manager'],                         // пороги, удаление снимков
    'erp801.*'         => ['admin'],

    // Модуль «Демонстрационный стенд» (erp811_demo) — показ работающей системы
    // на вымышленном производстве в отдельной базе. Право одно и только на
    // чтение: стенд по построению не принимает изменений, а вход в демо-режим
    // открыт на странице /demo и не относится к правам данных.
    'erp811.view' => ['admin', 'manager', 'accountant', 'worker', 'demo'],
    'erp811.*'         => ['admin'],

    // Модуль «Интерактивная документация» (erp812_docs)
    'erp812.view' => ['admin', 'manager', 'accountant', 'worker', 'demo', 'login'],
    'erp812.*'    => ['admin'],

    // Модуль «MCP-сервер» (erp804_mcp) — доступ LLM-агентов к системе.
    // Права модуля — это же и «права ключа»: ключ агента может нести любые
    // права системы, но выдаются они здесь, как и права людей.
    'erp804_mcp.view'     => ['admin', 'manager'],
    'erp804_mcp.call'     => ['admin', 'manager'],
    'erp804_mcp.manage'   => ['admin'],
    'erp804_mcp.approve'  => ['admin', 'manager'],
    'erp804_mcp.*'        => ['admin'],

    // Модуль «Ядро» (erp100_base) — пользователи и роли.
    'erp100.users'        => ['admin'],
    'erp100.roles'        => ['admin'],
    'erp100.*'            => ['admin'],

    // Модуль «Миграции» (erp111_migrations_postgresql) — состояние схемы БД.
    'erp111.view'         => ['admin'],
    'erp111.*'            => ['admin'],

    // Модуль «Ссылки» (erp118_links) — личные закладки и меню.
    // Право «login» означает: у каждого пользователя свои ссылки, чужие недоступны.
    'erp118.links'        => ['login'],
    'erp118.*'            => ['admin'],

    // Модуль «Продажи» (erp221_crm) — воронка сделок, лента работы, напоминания
    // и профиль продаж поверх контрагента erp301. Своего справочника клиентов
    // модуль не ведёт, поэтому права на клиентов объявлены здесь один раз.
    // Роль demo видит продажи только для чтения (демонстрационный стенд erp811).
    'erp221.view'             => ['admin', 'manager', 'accountant', 'demo'],
    'erp221.deals.manage'     => ['admin', 'manager'],
    'erp221.deals.delete'     => ['admin'],
    'erp221.clients.view'     => ['admin', 'manager', 'accountant', 'demo'],
    'erp221.clients.manage'   => ['admin', 'manager'],
    'erp221.stages.view'      => ['admin', 'manager', 'accountant', 'demo'],
    'erp221.stages.manage'    => ['admin'],
    'erp221.analytics.view'   => ['admin', 'manager', 'accountant', 'demo'],
    'erp221.kb.view'          => ['admin', 'manager', 'accountant', 'demo'],
    'erp221.kb.manage'        => ['admin', 'manager'],
    'erp221.ai'               => ['admin', 'manager'],
    'erp221.*'                => ['admin'],
    
    // Модуль Tasks (Задачи)
    'task.create'     => ['login'], // Любой залогиненный может создать
    'task.manage'     => ['admin', 'manager'],
];