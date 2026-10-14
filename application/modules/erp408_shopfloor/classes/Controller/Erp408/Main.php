<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер цеха (erp408_shopfloor).
 *
 * Задаёт мета-данные, меню модуля и общий помощник flash-сообщений: меню и
 * заголовки одинаковы на всех страницах цеха. Права берутся те же, что
 * объявлены в config/acl.php:
 *
 *  - `erp408.view`     — смотреть загрузку, операции и отчёты;
 *  - `erp408.manage`   — планировать маршруты, вести справочники, отменять;
 *  - `erp408.terminal` — отмечать работу (рабочее место мастера/оператора).
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Цех';
        $this->_meta['description'] = 'Рабочие центры, смены и календарь, задания на смену, отметки операций, выработка и простои.';
        $this->_meta['keywords']    = 'цех, смена, рабочий центр, операция, выработка, простой, терминал, erp';

        $this->set_menu(array(
            array(
                'title' => 'Загрузка',
                'href'  => '/shopfloor',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp408.view',
            ),
            array(
                'title' => 'Операции',
                'href'  => '/shopfloor/operations',
                'icon'  => 'fas fa-tasks',
                'acl'   => 'erp408.view',
            ),
            array(
                'title' => 'Терминал',
                'href'  => '/shopfloor/terminal',
                'icon'  => 'fas fa-tablet-alt',
                'acl'   => 'erp408.terminal',
            ),
            array(
                'title' => 'Центры',
                'href'  => '/shopfloor/centers',
                'icon'  => 'fas fa-industry',
                'acl'   => 'erp408.manage',
            ),
            array(
                'title' => 'Смены и календарь',
                'href'  => '/shopfloor/shifts',
                'icon'  => 'fas fa-calendar-alt',
                'acl'   => 'erp408.manage',
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
            $flash   = $session->get('erp408_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp408_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp408: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp408_flash', array());
            $session->delete('erp408_flash');
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
     * @return  Service_Erp408_Operations
     */
    protected function _operations()
    {
        return Service_Erp408_Operations::instance();
    }

    /**
     * @return  Service_Erp408_WorkCenters
     */
    protected function _centers()
    {
        return Service_Erp408_WorkCenters::instance();
    }

    /**
     * @return  Service_Erp408_Shifts
     */
    protected function _shifts()
    {
        return Service_Erp408_Shifts::instance();
    }

    /**
     * Переходы по операции из интерфейса: одно место обработки ошибок.
     *
     * @param   int|string  $id
     * @param   string      $action
     * @param   array       $context
     * @return  array|null  операция после перехода
     */
    protected function _act($id, $action, array $context = array())
    {
        try
        {
            return $this->_operations()->act($id, $action, $context, $this->_current_user_id());
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());

            return NULL;
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: действие :action не выполнено — :error',
                array(':action' => $action, ':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось выполнить действие. Подробности в журнале.');

            return NULL;
        }
    }
}
