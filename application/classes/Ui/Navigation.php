<?php defined('SYSPATH') or die('No direct script access.');

/**
 * One role-aware navigation catalogue for sidebar, mobile drawer and Ctrl+K.
 * New operator modules must be registered here once; the three surfaces cannot
 * silently drift apart.
 *
 * Режим интерфейса (Ui_Mode): пункты со знаком `pro` — для профессионального
 * режима. В простом они скрыты во всех трёх местах сразу, потому что каталог
 * один. Скрытие — не защита: права по-прежнему решает ACL.
 */
class Ui_Navigation
{
    /**
     * Меню по ролям и режиму интерфейса.
     *
     * @param   array   $roles
     * @param   string  $mode   simple — без пунктов `pro`; pro — все (по умолчанию,
     *                          чтобы вызовы без режима работали как прежде)
     * @return  array
     */
    public static function categories(array $roles, $mode = Ui_Mode::PRO)
    {
        $catalogue = array(
            'main'=>array('title'=>'Основное','items'=>array(
                self::item('Рабочее место','/','fas fa-house','/','login',TRUE),
            )),
            'production'=>array('title'=>'Производство и цех','items'=>array(
                self::item('Цех & Смены','/shopfloor','fas fa-industry','/shopfloor','erp408.view'),
                self::item('Заказы на выпуск','/production/orders','fas fa-gears','/production','erp402_production.view'),
                self::item('Технологические карты','/tech_cards','fas fa-diagram-project','/tech_cards','erp228.view'),
                self::item('Составы EBOM / MBOM','/product-structures','fas fa-sitemap','/product-structures','erp231.view',FALSE,TRUE),
                self::item('Планирование (MRP)','/planning','fas fa-calendar-days','/planning','erp407.view',FALSE,TRUE),
                self::item('Себестоимость','/costing','fas fa-calculator','/costing','erp409.view',FALSE,TRUE),
                self::item('Склад & Остатки','/warehouse/balances','fas fa-boxes-stacked','/warehouse','erp401_wms.view_stock'),
                self::item('Партии & Прослеживаемость','/traceability','fas fa-code-branch','/traceability','erp412.view',FALSE,TRUE),
                self::item('Контроль качества','/quality','fas fa-clipboard-check','/quality','erp405.view'),
                self::item('QR-маркировка','/qr','fas fa-qrcode','/qr','erp199.view',FALSE,TRUE),
            )),
            'supply'=>array('title'=>'Снабжение и продажи','items'=>array(
                self::item('Закупки & Поставки','/procurement','fas fa-truck-ramp-box','/procurement','erp403.view'),
                self::item('Продажи (CRM)','/crm','fas fa-bullseye','/crm','erp221.view'),
                self::item('Контрагенты','/counterparties','fas fa-handshake','/counterparties','erp301.view'),
                self::item('Документы & Печать','/documents','fas fa-file-lines','/documents','erp404.view'),
                self::item('Интеграции с 1С / файлами','/integration','fas fa-right-left','/integration','erp802.view',FALSE,TRUE),
            )),
            'analytics'=>array('title'=>'Аналитика и AI','items'=>array(
                self::item('Аналитика & OEE','/analytics','fas fa-chart-line','/analytics','erp801.view'),
                self::item('Документация & Схемы','/docs','fas fa-book-open','/docs','erp812.view',FALSE,TRUE),
                self::item('Демо-стенд','/demo','fas fa-play','/demo','erp811.view'),
                self::item('MCP / Инструменты AI','/docs/mcp','fas fa-robot','/docs/mcp','erp804_mcp.view',FALSE,TRUE),
                self::item('Эталон интерфейса','/pages/required_design_sample','fas fa-palette','/pages/required_design_sample','erp812.view',TRUE),
            )),
            'admin'=>array('title'=>'Администрирование','items'=>array(
                self::item('Пользователи & Доступ','/access/users','fas fa-users-gear','/access','erp100.users'),
                self::item('Сотрудники & Подразделения','/workforce','fas fa-people-group','/workforce','erp310.view'),
                self::item('Журнал аудита','/audit/journal','fas fa-clock-rotate-left','/audit','erp119_audit.view',FALSE,TRUE),
                self::item('Файловое хранилище','/erp212','fas fa-folder-tree','/erp212','erp212_storage.view_files',FALSE,TRUE),
                self::item('Реестр модулей','/erp218','fas fa-cubes','/erp218','module_manager.view',FALSE,TRUE),
                self::item('Справочники (MDM)','/erp230_dictionaries','fas fa-database','/erp230','mdm.dictionary.view',FALSE,TRUE),
                self::item('Миграция БД','/migrate_pg','fas fa-database','/migrate_pg','erp100.users',FALSE,TRUE),
            )),
        );

        foreach ($catalogue as $key=>$category)
        {
            $items=array();
            foreach ($category['items'] as $item) if(self::allowed($roles,$item['acl']) AND self::shown($item,$mode)) $items[]=$item;
            if($items) $catalogue[$key]['items']=$items; else unset($catalogue[$key]);
        }
        return $catalogue;
    }

    /** Ctrl+K uses exactly the links visible in navigation, plus safe subpages. */
    public static function palette(array $roles, $mode = Ui_Mode::PRO)
    {
        $items=array();
        foreach(self::categories($roles,$mode) as $category) foreach($category['items'] as $item) $items[]=$item;
        $extra=array(
            self::item('Бизнес-процессы (BPMN)','/docs/processes','fas fa-diagram-project','/docs/processes','erp812.view',FALSE,TRUE),
            self::item('Каталог модулей системы','/docs/modules','fas fa-cubes','/docs/modules','erp812.view',FALSE,TRUE),
            self::item('Архитектура PromERP','/docs/architecture','fas fa-sitemap','/docs/architecture','erp812.view',FALSE,TRUE),
            self::item('Мой профиль','/auth/profile','fas fa-user-circle','/auth/profile','login'),
        );
        foreach($extra as $item) if(self::allowed($roles,$item['acl']) AND self::shown($item,$mode)) $items[]=$item;
        return $items;
    }

    public static function allowed(array $roles,$acl)
    {
        if(in_array('admin',$roles,TRUE)) return TRUE;
        if($acl==='' OR $acl==='all') return TRUE;
        if(in_array($acl,$roles,TRUE)) return TRUE;
        return ACL::check($roles,$acl);
    }

    /**
     * Меню для посетителя, который ещё не вошёл.
     *
     * Обычный каталог фильтруется по правам, и у гостя не остаётся ни одного
     * пункта: боковая панель превращалась в пустой столбец с заголовком сверху
     * и кнопкой входа внизу. Здесь — те разделы, которые открыты анониму и
     * действительно помогают понять систему до регистрации.
     *
     * Список намеренно короткий: посетителю нужен путь «понять → посмотреть»,
     * а не копия рабочего меню, половина которого отдаст 403.
     *
     * @return  array
     */
    public static function guest_categories()
    {
        return array(
            'about' => array('title' => 'Знакомство', 'items' => array(
                self::item('Главная', '/', 'fas fa-house', '/', '', TRUE),
                self::item('Возможности', '/about', 'fas fa-circle-info', '/about', ''),
                self::item('Документация', '/docs', 'fas fa-book', '/docs', ''),
                self::item('Демо-стенд', '/demo', 'fas fa-play', '/demo', ''),
            )),
            'legal' => array('title' => 'Правовое', 'items' => array(
                self::item('Условия использования', '/terms', 'fas fa-file-contract', '/terms', ''),
                self::item('Конфиденциальность', '/privacy', 'fas fa-user-shield', '/privacy', ''),
            )),
        );
    }

    /** Виден ли пункт в этом режиме: пункты `pro` скрыты в простом. */
    protected static function shown(array $item,$mode)
    {
        return $mode!==Ui_Mode::SIMPLE OR empty($item['pro']);
    }

    protected static function item($title,$href,$icon,$prefix,$acl,$exact=FALSE,$pro=FALSE)
    {
        return array('title'=>$title,'href'=>$href,'icon'=>$icon,'prefix'=>$prefix,'acl'=>$acl,'exact'=>$exact,'pro'=>$pro);
    }
}
