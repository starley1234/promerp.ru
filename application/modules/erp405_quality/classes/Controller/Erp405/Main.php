<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер качества (erp405_quality).
 *
 * Держит общее для страниц: заголовки, меню, flash-сообщения и доступ к
 * сервисам. Контроллеры остаются тонкими: разбор запроса, проверка прав, вызов
 * сервиса, ответ.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Main extends Controller_Base
{
    /** @var Service_Erp405_Inspections|null */
    protected $_inspections;

    /** @var Service_Erp405_DefectCodes|null */
    protected $_codes;

    /** @var Service_Erp405_Stats|null */
    protected $_stats;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Качество';
        $this->_meta['description'] = 'Контроль партий: входной, операционный и итоговый. Несоответствия, карантин, решения по браку и статистика потерь.';
        $this->_meta['keywords']    = 'качество, контроль, брак, дефект, карантин, утиль, erp';

        $this->set_menu(array(
            array(
                'title' => 'Доска',
                'href'  => '/quality',
                'icon'  => 'fas fa-clipboard-check',
                'acl'   => 'erp405.view',
            ),
            array(
                'title' => 'Контроли',
                'href'  => '/quality/inspections',
                'icon'  => 'fas fa-search',
                'acl'   => 'erp405.view',
            ),
            array(
                'title' => 'Карантин',
                'href'  => '/quality/quarantine',
                'icon'  => 'fas fa-box-open',
                'acl'   => 'erp405.view',
            ),
            array(
                'title' => 'Коды дефектов',
                'href'  => '/quality/defects',
                'icon'  => 'fas fa-tags',
                'acl'   => 'erp405.view',
            ),
            array(
                'title' => 'Новый контроль',
                'href'  => '/quality/inspections/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp405.inspect',
            ),
        ));
    }

    /**
     * @param   string $type success|error|warning|info
     * @param   string $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp405_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp405_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp405: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp405_flash', array());
            $session->delete('erp405_flash');
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
     * Показать ошибку сервиса на странице, а не текстом исключения.
     *
     * @param   Exception   $e
     * @param   string      $fallback
     * @return  void
     */
    protected function _flash_error(Exception $e, $fallback = 'Не удалось выполнить действие')
    {
        $message = ($e instanceof Exception_Erp405_Quality) ? $e->getMessage() : $fallback;

        $this->_flash('error', $message);

        if ( ! $e instanceof Exception_Erp405_Quality AND ! $e instanceof HTTP_Exception)
        {
            Kohana::$log->add(Log::ERROR, 'erp405: :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  Service_Erp405_Inspections
     */
    protected function _inspections()
    {
        if ($this->_inspections === NULL)
        {
            $this->_inspections = Service_Erp405_Inspections::instance();
        }

        return $this->_inspections;
    }

    /**
     * @return  Service_Erp405_DefectCodes
     */
    protected function _codes()
    {
        if ($this->_codes === NULL)
        {
            $this->_codes = Service_Erp405_DefectCodes::instance();
        }

        return $this->_codes;
    }

    /**
     * @return  Service_Erp405_Stats
     */
    protected function _stats()
    {
        if ($this->_stats === NULL)
        {
            $this->_stats = Service_Erp405_Stats::instance();
        }

        return $this->_stats;
    }

    /**
     * Значение из тела POST-запроса (без исключения на отсутствующем поле).
     *
     * @param   string $key
     * @return  mixed
     */
    protected function _posted($key)
    {
        $post = $this->request->post();

        return (is_array($post) AND array_key_exists($key, $post)) ? $post[$key] : NULL;
    }

    /**
     * Обычные склады для выбора места партии.
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
            $options = array();

            foreach (Service_Erp401_Stock::instance()->warehouses(TRUE) as $warehouse)
            {
                $options[(int) $warehouse->id] = (string) $warehouse->name.' ('.$warehouse->type.')';
            }

            return $options;
        }
        catch (Exception $e)
        {
            return array();
        }
    }
}
