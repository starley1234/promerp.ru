<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля контрагентов (erp301_counterparty).
 *
 * Задаёт мета-данные, меню модуля и общий помощник flash-сообщений.
 *
 * @package    Erp301
 * @category   Controllers
 */
class Controller_Erp301_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Контрагенты';
        $this->_meta['description'] = 'Поставщики, покупатели, перевозчики и подрядчики: реквизиты, контакты, договоры.';
        $this->_meta['keywords']    = 'контрагенты, поставщики, покупатели, договоры, реквизиты, erp';

        $this->set_menu(array(
            array(
                'title' => 'Сводка',
                'href'  => '/counterparties',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp301.view',
            ),
            array(
                'title' => 'Контрагенты',
                'href'  => '/counterparties/list',
                'icon'  => 'fas fa-address-book',
                'acl'   => 'erp301.view',
            ),
            array(
                'title' => 'Договоры',
                'href'  => '/counterparties/contracts',
                'icon'  => 'fas fa-file-signature',
                'acl'   => 'erp301.view',
            ),
            array(
                'title' => 'Добавить контрагента',
                'href'  => '/counterparties/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp301.manage',
            ),
        ));
    }

    /**
     * @param string $type    success|error|warning|info
     * @param string $message
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp301_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp301_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp301: не удалось записать flash — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp301_flash', array());
            $session->delete('erp301_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    /**
     * Текущий пользователь (или NULL для CLI/гостя).
     *
     * @return int|null
     */
    protected function _user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return $user ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }
}
