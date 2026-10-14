<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля планирования (erp407_planning).
 *
 * Держит общее для всех страниц: заголовки, меню, flash-сообщения и доступ к
 * сервисам. Контроллеры остаются тонкими: разбор запроса, проверка прав,
 * вызов сервиса, ответ.
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Main extends Controller_Base
{
    /** @var Service_Erp407_Programs|null */
    protected $_programs;

    /** @var Service_Erp407_Mrp|null */
    protected $_mrp;

    /** @var Service_Erp407_Proposals|null */
    protected $_proposals;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Планирование';
        $this->_meta['description'] = 'Производственная программа, расчёт потребности, дефициты и предложения закупки и выпуска.';
        $this->_meta['keywords']    = 'планирование, MRP, потребность, дефицит, программа, закупка, erp';

        $this->set_menu(array(
            array(
                'title' => 'Доска',
                'href'  => '/planning',
                'icon'  => 'fas fa-calendar-alt',
                'acl'   => 'erp407.view',
            ),
            array(
                'title' => 'Дефициты',
                'href'  => '/planning/deficits',
                'icon'  => 'fas fa-exclamation-triangle',
                'acl'   => 'erp407.view',
            ),
            array(
                'title' => 'Программы',
                'href'  => '/planning/programs',
                'icon'  => 'fas fa-clipboard-check',
                'acl'   => 'erp407.view',
            ),
            array(
                'title' => 'Предложения',
                'href'  => '/planning/proposals',
                'icon'  => 'fas fa-tasks',
                'acl'   => 'erp407.view',
            ),
            array(
                'title' => 'Новая программа',
                'href'  => '/planning/programs/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp407.manage',
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
            $flash   = $session->get('erp407_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp407_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp407: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp407_flash', array());
            $session->delete('erp407_flash');
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
     * @return  Service_Erp407_Programs
     */
    protected function _programs()
    {
        if ($this->_programs === NULL)
        {
            $this->_programs = Service_Erp407_Programs::instance();
        }

        return $this->_programs;
    }

    /**
     * @return  Service_Erp407_Mrp
     */
    protected function _mrp()
    {
        if ($this->_mrp === NULL)
        {
            $this->_mrp = Service_Erp407_Mrp::instance();
        }

        return $this->_mrp;
    }

    /**
     * @return  Service_Erp407_Proposals
     */
    protected function _proposals_service()
    {
        if ($this->_proposals === NULL)
        {
            $this->_proposals = Service_Erp407_Proposals::instance();
        }

        return $this->_proposals;
    }
}
