<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля erp401_wms (складской учёт).
 *
 * Задаёт мета-данные и меню модуля. Все остальные контроллеры наследуются
 * от него, чтобы меню и заголовки были одинаковыми на всех страницах склада.
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Складской учёт';
        $this->_meta['description'] = 'Остатки, движения и резервы номенклатуры по складам.';
        $this->_meta['keywords']    = 'склад, остатки, приход, расход, перемещение, резерв, erp';

        $this->set_menu(array(
            array(
                'title' => 'Сводка',
                'href'  => '/warehouse',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp401_wms.view_dashboard',
            ),
            array(
                'title' => 'Остатки',
                'href'  => '/warehouse/balances',
                'icon'  => 'fas fa-boxes',
                'acl'   => 'erp401_wms.view_stock',
            ),
            array(
                'title' => 'Движения',
                'href'  => '/warehouse/movements',
                'icon'  => 'fas fa-exchange-alt',
                'acl'   => 'erp401_wms.view_stock',
            ),
            array(
                'title' => 'Провести операцию',
                'href'  => '/warehouse/movements/create',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp401_wms.manage_movements',
            ),
            array(
                'title' => 'Резервы',
                'href'  => '/warehouse/reservations',
                'icon'  => 'fas fa-lock',
                'acl'   => 'erp401_wms.reserve',
            ),
            array(
                'title' => 'Склады',
                'href'  => '/warehouse/warehouses',
                'icon'  => 'fas fa-warehouse',
                'acl'   => 'erp401_wms.manage_warehouses',
            ),
        ));
    }

    /**
     * Добавить flash-сообщение (показывается один раз на следующей странице).
     *
     * @param string $type    success|error|warning|info
     * @param string $message
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp401_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp401_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp401: не удалось записать flash — :error',
                array(':error' => $e->getMessage()));
        }
    }

    /**
     * Выбрать и очистить накопленные flash-сообщения.
     *
     * @return array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp401_flash', array());
            $session->delete('erp401_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    public function action_index()
    {
        $this->redirect('/warehouse/balances');
    }
}
