<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля производства (erp402_production).
 *
 * Задаёт мета-данные, меню модуля и общий помощник flash-сообщений: меню и
 * заголовки одинаковы на всех страницах производства.
 *
 * @package    Erp402
 * @category   Controllers
 */
class Controller_Erp402_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Производство';
        $this->_meta['description'] = 'Производственные заказы: состав изделия, резерв материалов, выпуск продукции.';
        $this->_meta['keywords']    = 'производство, заказ, состав, BOM, материалы, выпуск, erp';

        $this->set_menu(array(
            array(
                'title' => 'Сводка',
                'href'  => '/production',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp402_production.view',
            ),
            array(
                'title' => 'Заказы',
                'href'  => '/production/orders',
                'icon'  => 'fas fa-clipboard-list',
                'acl'   => 'erp402_production.view',
            ),
            array(
                'title' => 'Новый заказ',
                'href'  => '/production/orders/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp402_production.manage',
            ),
        ));
    }

    /**
     * @param   string  $type       success|error|warning|info
     * @param   string  $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp402_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp402_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: не удалось записать flash — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp402_flash', array());
            $session->delete('erp402_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    /**
     * Текущий пользователь (NULL для CLI/гостя).
     *
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Список складов для форм (через сервис склада).
     *
     * @return  array
     */
    protected function _warehouses()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return array();
        }

        try
        {
            return Service_Erp401_Stock::instance()->warehouses(TRUE);
        }
        catch (Exception $e)
        {
            return array();
        }
    }
}
