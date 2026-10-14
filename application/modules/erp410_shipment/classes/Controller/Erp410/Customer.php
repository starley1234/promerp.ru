<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Клиенты продаж (erp410_shipment): условия, задолженность, заказы и прайс.
 *
 * Покупатели не заводятся здесь: это контрагенты erp301. Модуль показывает их
 * с торговой стороны и хранит только профиль продажи.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Customer extends Controller_Erp410_Main
{
    /**
     * Список клиентов с условиями и задолженностью.
     */
    public function action_index()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'search'       => $this->_query('search'),
            'active'       => $this->_query('active'),
            'with_profile' => $this->_query('with_profile'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp410/customer/index')
            ->set('rows', $this->_customers()->search($filters, $limit, $offset))
            ->set('total', $this->_customers()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('stats', $this->_customers()->stats())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка клиента: условия, заказы, счета и цены.
     */
    public function action_view()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $customer_id = (int) $this->request->param('id');
        $profile     = $this->_customers()->profile($customer_id, FALSE);

        $this->template->content = View::factory('erp410/customer/view')
            ->set('customer_id', $customer_id)
            ->set('customer', $this->_customers()->name($customer_id))
            ->set('profile', $profile)
            ->set('credit', $this->_customers()->credit_check($customer_id, 0))
            ->set('exposure', $this->_customers()->credit_exposure($customer_id))
            ->set('orders', $this->_orders()->search(array('customer_id' => $customer_id), 10, 0))
            ->set('order_stats', $this->_orders()->stats())
            ->set('invoices', $this->_invoices()->search(array('customer_id' => $customer_id), 10, 0))
            ->set('prices', $this->_prices()->for_customer($customer_id, 20))
            ->set('items', $this->_items())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить условия клиента.
     */
    public function action_save()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/customers');
        }

        $post        = $this->request->post();
        $customer_id = ! empty($post['customer_id']) ? (int) $post['customer_id'] : 0;

        try
        {
            $this->_customers()->save($customer_id, $post, $this->_current_user_id());

            $this->_flash('success', 'Условия клиента сохранены');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить условия клиента');
        }

        $this->redirect($customer_id ? '/sales/customers/view/'.$customer_id : '/sales/customers');
    }
}
