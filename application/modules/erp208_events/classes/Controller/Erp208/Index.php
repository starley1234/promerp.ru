<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.2
 * @author AI
 */
class Controller_Erp208_Index extends Controller_Erp208_Main {

    /**
     * Главная страница модуля. Отображает дашборд с виджетами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp208_events.view_dashboard'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        // Получаем общее количество событий
        $total_events = ORM::factory('Erp208_Event')->count_all();

        // Получаем количество активных подписок
        // Для PostgreSQL используем TRUE вместо 1 для boolean полей
        $active_subscribers = ORM::factory('Erp208_Subscriber')
            ->where('is_active', '=', TRUE)
            ->count_all();

        // Получаем последние 10 событий
        $latest_events = ORM::factory('Erp208_Event')
            ->order_by('created_at', 'DESC')
            ->limit(10)
            ->find_all();

        $this->template->content = View::factory('erp208/index')
            ->set('total_events', $total_events)
            ->set('active_subscribers', $active_subscribers)
            ->set('latest_events', $latest_events);
    }
}