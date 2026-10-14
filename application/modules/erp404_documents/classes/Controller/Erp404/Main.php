<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер документов и печати (erp404_documents).
 *
 * Держит общее для страниц: меню, flash-сообщения, доступ к сервисам, разбор
 * POST и текст ошибок. Контроллеры остаются тонкими: разбор запроса, проверка
 * прав, вызов сервиса, ответ.
 *
 * @package    Erp404
 * @category   Controllers
 */
class Controller_Erp404_Main extends Controller_Base
{
    /** @var Service_Erp404_Documents|null */
    protected $_documents;

    /** @var Service_Erp404_Templates|null */
    protected $_templates;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Документы и печать';
        $this->_meta['description'] = 'Шаблоны печатных форм, выпуск документов со снимком и версией шаблона, журнал печати и архив.';
        $this->_meta['keywords']    = 'документы, печать, шаблоны, формы, архив, erp';

        $this->set_menu(array(
            array(
                'title' => 'Документы',
                'href'  => '/documents',
                'icon'  => 'fas fa-file-alt',
                'acl'   => 'erp404.view',
            ),
            array(
                'title' => 'Шаблоны форм',
                'href'  => '/documents/templates',
                'icon'  => 'fas fa-file-code',
                'acl'   => 'erp404.view',
            ),
            array(
                'title' => 'Новый документ',
                'href'  => '/documents/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp404.print',
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
            $flash   = $session->get('erp404_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp404_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp404: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp404_flash', array());
            $session->delete('erp404_flash');
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
        $message = ($e instanceof Exception_Erp404_Documents) ? $e->getMessage() : $fallback;

        $this->_flash('error', $message);

        if ( ! $e instanceof Exception_Erp404_Documents AND ! $e instanceof HTTP_Exception)
        {
            Kohana::$log->add(Log::ERROR, 'erp404: :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  Service_Erp404_Documents
     */
    protected function _documents()
    {
        if ($this->_documents === NULL)
        {
            $this->_documents = Service_Erp404_Documents::instance();
        }

        return $this->_documents;
    }

    /**
     * @return  Service_Erp404_Templates
     */
    protected function _templates()
    {
        if ($this->_templates === NULL)
        {
            $this->_templates = Service_Erp404_Templates::instance();
        }

        return $this->_templates;
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
     * Всё тело POST-запроса.
     *
     * @return  array
     */
    protected function _form()
    {
        $post = $this->request->post();

        return is_array($post) ? $post : array();
    }

    /**
     * Адрес возврата на карточку документа.
     *
     * @param   int|string  $id
     * @return  string
     */
    protected function _document_url($id)
    {
        return '/documents/view/'.rawurlencode((string) $id);
    }
}
