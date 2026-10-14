<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер закупок (erp403_procurement).
 *
 * Держит общее для всех страниц: заголовки, меню, flash-сообщения и доступ к
 * сервисам. Контроллеры остаются тонкими: разбор запроса, проверка прав,
 * вызов сервиса, ответ.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Main extends Controller_Base
{
    /** @var Service_Erp403_Requests|null */
    protected $_requests;

    /** @var Service_Erp403_Orders|null */
    protected $_orders;

    /** @var Service_Erp403_Receipts|null */
    protected $_receipts;

    /** @var Service_Erp403_Suppliers|null */
    protected $_suppliers;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Закупки';
        $this->_meta['description'] = 'Заявки на закупку, заказы поставщикам, приёмка на склад, история цен и условия поставщиков.';
        $this->_meta['keywords']    = 'закупки, заявка, заказ поставщику, приёмка, поставщик, цены, erp';

        $this->set_menu(array(
            array(
                'title' => 'Доска',
                'href'  => '/procurement',
                'icon'  => 'fas fa-shopping-cart',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Заявки',
                'href'  => '/procurement/requests',
                'icon'  => 'fas fa-file-alt',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Заказы',
                'href'  => '/procurement/orders',
                'icon'  => 'fas fa-file-signature',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Приёмки',
                'href'  => '/procurement/receipts',
                'icon'  => 'fas fa-truck-loading',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Поставщики',
                'href'  => '/procurement/suppliers',
                'icon'  => 'fas fa-industry',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Дефицит склада',
                'href'  => '/procurement/low-stock',
                'icon'  => 'fas fa-exclamation-triangle',
                'acl'   => 'erp403.view',
            ),
            array(
                'title' => 'Новая заявка',
                'href'  => '/procurement/requests/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp403.manage',
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
            $flash   = $session->get('erp403_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp403_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp403_flash', array());
            $session->delete('erp403_flash');
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
        $message = ($e instanceof Exception_Erp403_Procurement) ? $e->getMessage() : $fallback;

        $this->_flash('error', $message);

        if ( ! $e instanceof Exception_Erp403_Procurement AND ! $e instanceof HTTP_Exception)
        {
            Kohana::$log->add(Log::ERROR, 'erp403: :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  Service_Erp403_Requests
     */
    protected function _requests()
    {
        if ($this->_requests === NULL)
        {
            $this->_requests = Service_Erp403_Requests::instance();
        }

        return $this->_requests;
    }

    /**
     * @return  Service_Erp403_Orders
     */
    protected function _orders()
    {
        if ($this->_orders === NULL)
        {
            $this->_orders = Service_Erp403_Orders::instance();
        }

        return $this->_orders;
    }

    /**
     * @return  Service_Erp403_Receipts
     */
    protected function _receipts()
    {
        if ($this->_receipts === NULL)
        {
            $this->_receipts = Service_Erp403_Receipts::instance();
        }

        return $this->_receipts;
    }

    /**
     * @return  Service_Erp403_Suppliers
     */
    protected function _suppliers()
    {
        if ($this->_suppliers === NULL)
        {
            $this->_suppliers = Service_Erp403_Suppliers::instance();
        }

        return $this->_suppliers;
    }

    /**
     * Склады для выпадающих списков.
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

    /**
     * Поставщики для выпадающих списков.
     *
     * @return  array
     */
    protected function _supplier_options()
    {
        $options = array();

        foreach ($this->_suppliers()->counterparties(array(), 500, 0) as $counterparty)
        {
            $options[] = array(
                'id'   => (int) $counterparty->id,
                'name' => (string) $counterparty->name,
            );
        }

        return $options;
    }
}
