<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 *
 * Базовый контроллер модуля erp208_events.
 * Обрабатывает общие запросы и инициализацию, устанавливает мета-данные и меню.
 */
class Controller_Erp208_Main extends Controller_Base
{
    /**
     * Выполняется перед вызовом action.
     * Устанавливает мета-данные и формирует основное меню модуля.
     */
    public function before()
    {
        parent::before();

        // Установка мета-данных по умолчанию для модуля
        $this->_meta['title'] = 'Управление событиями';
        $this->_meta['description'] = 'Управление и мониторинг системных событий.';
        $this->_meta['keywords'] = 'события, шина, подписчики, издатели, erp';

        // Формирование меню модуля
        $this->set_menu([
            [
                'title' => 'Список событий',
                'href'  => '/erp208_events/list_events',
                'icon'  => 'fas fa-list',
                'acl'   => 'erp208_events.view_events' // Доступ к просмотру списка событий
            ],
            [
                'title' => 'Создать событие',
                'href'  => '/erp208_events/create_event',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp208_events.create_event' // Доступ к созданию событий
            ],
            [
                'title' => 'Подписчики',
                'href'  => '/erp208_events/list_subscribers',
                'icon'  => 'fas fa-users',
                'acl'   => 'erp208_events.view_subscribers' // Доступ к просмотру списка подписчиков
            ],
            // Дополнительные пункты меню, если нужны настройки или другая функциональность
            // [
            //     'title' => 'Настройки',
            //     'href'  => '/erp208_events/settings',
            //     'icon'  => 'fas fa-cog',
            //     'acl'   => 'erp208_events.manage_settings' // Доступ к управлению настройками модуля
            // ]
        ]);
    }

    /**
     * Действие по умолчанию для базового контроллера.
     * Перенаправляет на список событий или отображает общую информацию.
     */
    public function action_index()
    {
        // Перенаправляем на список событий как на главную страницу модуля
        $this->redirect('/erp208_events/list_events');
    }
}