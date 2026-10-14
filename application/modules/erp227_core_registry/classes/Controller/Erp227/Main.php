<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля Core Registry (Универсальный реестр объектов).
 * 
 * Обеспечивает инициализацию интерфейса, проверку прав доступа (ACL) 
 * и формирование единого меню модуля.
 * 
 * @package Erp227
 * @version 1.0.0
 */
abstract class Controller_Erp227_Main extends Controller_Base {

    /**
     * @var string Основной шаблон модуля
     */
    // public $template = 'erp227/layout/main';

    /**
     * Инициализация модуля: проверка прав, заголовки и меню.
     */
    public function before()
    {
        parent::before();

        // 1. Базовая проверка прав доступа к модулю
        if ( ! $this->can('core_registry.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр реестра объектов.');
        }

        // 2. Установка мета-данных по умолчанию
        $this->set_title('Универсальный реестр объектов');
        $this->set_description('Единая база сущностей, иерархий и связей ERP системы');

        // 3. Инициализация меню модуля (Просто и Единообразно)
        $this->_init_module_menu();

        // 4. Загрузка общих конфигураций для View
        $config = Kohana::$config->load('erp227_core_registry');
        View::set_global('erp227_config', $config);
    }

    /**
     * Формирование структуры меню модуля с учетом ACL.
     */
    protected function _init_module_menu()
    {
        $menu = [
            [
                'title' => 'Реестр объектов',
                'href'  => '/erp227_index',
                'icon'  => 'fas fa-database',
                'acl'   => 'core_registry.view',
            ],
            [
                'title' => 'Связи и BOM',
                'href'  => '/erp227_links',
                'icon'  => 'fas fa-project-diagram',
                'acl'   => 'core_registry.view',
            ],
            [
                'title' => 'Настройка типов',
                'href'  => '/erp227_types',
                'icon'  => 'fas fa-vials',
                'acl'   => 'core_registry.admin_types',
            ],
            [
                'title' => 'Ед. измерения',
                'href'  => '/erp227_units',
                'icon'  => 'fas fa-balance-scale',
                'acl'   => 'core_registry.view',
            ],
        ];

        $this->set_menu($menu);
    }

    /**
     * Хелпер для получения сервиса реестра.
     * 
     * @return Service_Erp227_Registry
     */
    protected function registry_service()
    {
        return Service_Erp227_Registry::instance();
    }

    /**
     * Хелпер для получения сервиса поиска.
     * 
     * @return Service_Erp227_Search
     */
    protected function search_service()
    {
        return Service_Erp227_Search::instance();
    }
}