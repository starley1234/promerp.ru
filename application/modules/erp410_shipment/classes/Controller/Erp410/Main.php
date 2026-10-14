<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер продаж (erp410_shipment).
 *
 * Держит общее для всех страниц: заголовки, меню, flash-сообщения и доступ к
 * сервисам. Контроллеры остаются тонкими: разбор запроса, проверка прав,
 * вызов сервиса, ответ.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Main extends Controller_Base
{
    /** @var Service_Erp410_Orders|null */
    protected $_orders;

    /** @var Service_Erp410_Shipments|null */
    protected $_shipments;

    /** @var Service_Erp410_Invoices|null */
    protected $_invoices;

    /** @var Service_Erp410_Customers|null */
    protected $_customers;

    /** @var Service_Erp410_Prices|null */
    protected $_prices;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Продажи';
        $this->_meta['description'] = 'Заказы клиентов, резерв товара, отгрузка со склада, счета и оплата.';
        $this->_meta['keywords']    = 'продажи, заказ клиента, отгрузка, счёт, оплата, резерв, erp';

        $this->set_menu(array(
            array(
                'title' => 'Доска',
                'href'  => '/sales',
                'icon'  => 'fas fa-chart-line',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Заказы',
                'href'  => '/sales/orders',
                'icon'  => 'fas fa-file-invoice',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Отгрузки',
                'href'  => '/sales/shipments',
                'icon'  => 'fas fa-truck-fast',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Счета',
                'href'  => '/sales/invoices',
                'icon'  => 'fas fa-file-invoice-dollar',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Клиенты',
                'href'  => '/sales/customers',
                'icon'  => 'fas fa-handshake',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Прайс',
                'href'  => '/sales/prices',
                'icon'  => 'fas fa-tags',
                'acl'   => 'erp410.view',
            ),
            array(
                'title' => 'Новый заказ',
                'href'  => '/sales/orders/edit',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp410.manage',
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
            $flash   = $session->get('erp410_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp410_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp410: не удалось записать flash — :error', array(':error' => $e->getMessage()));
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
            $flash   = $session->get('erp410_flash', array());
            $session->delete('erp410_flash');
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
        $message = ($e instanceof Exception_Erp410_Shipment) ? $e->getMessage() : $fallback;

        $this->_flash('error', $message);

        if ( ! $e instanceof Exception_Erp410_Shipment AND ! $e instanceof HTTP_Exception)
        {
            Kohana::$log->add(Log::ERROR, 'erp410: :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  Service_Erp410_Orders
     */
    protected function _orders()
    {
        if ($this->_orders === NULL)
        {
            $this->_orders = Service_Erp410_Orders::instance();
        }

        return $this->_orders;
    }

    /**
     * @return  Service_Erp410_Shipments
     */
    protected function _shipments()
    {
        if ($this->_shipments === NULL)
        {
            $this->_shipments = Service_Erp410_Shipments::instance();
        }

        return $this->_shipments;
    }

    /**
     * @return  Service_Erp410_Invoices
     */
    protected function _invoices()
    {
        if ($this->_invoices === NULL)
        {
            $this->_invoices = Service_Erp410_Invoices::instance();
        }

        return $this->_invoices;
    }

    /**
     * @return  Service_Erp410_Customers
     */
    protected function _customers()
    {
        if ($this->_customers === NULL)
        {
            $this->_customers = Service_Erp410_Customers::instance();
        }

        return $this->_customers;
    }

    /**
     * @return  Service_Erp410_Prices
     */
    protected function _prices()
    {
        if ($this->_prices === NULL)
        {
            $this->_prices = Service_Erp410_Prices::instance();
        }

        return $this->_prices;
    }

    /**
     * Клиенты для выпадающих списков.
     *
     * @return  array
     */
    protected function _customer_options()
    {
        $options = array();

        foreach ($this->_customers()->counterparties(array(), 500, 0) as $counterparty)
        {
            $options[] = array(
                'id'   => (int) $counterparty->id,
                'name' => (string) $counterparty->name,
            );
        }

        return $options;
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
     * Номенклатура для подсказок в строках документов.
     *
     * @return  array
     */
    protected function _items($limit = 500)
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return array();
        }

        try
        {
            return Service_Erp401_Stock::instance()->catalog($limit);
        }
        catch (Exception $e)
        {
            return array();
        }
    }
}
