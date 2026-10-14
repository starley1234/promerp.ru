<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Прайс продажи (erp410_shipment): базовая цена и цены клиентов, история.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Price extends Controller_Erp410_Main
{
    /**
     * Список позиций с ценами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $customers = $this->_customer_options();
        $item_id   = (int) $this->_query('item_id');
        $customer_id = (int) $this->_query('customer_id');

        $rows = array();
        $history = array();

        if ($item_id)
        {
            $history = $this->_prices()->history($item_id, 50);
        }

        foreach ($this->_items() as $item)
        {
            $price = $this->_prices()->price_for((int) $item['id'], $customer_id ?: NULL);

            $rows[] = array(
                'item'  => $item,
                'price' => $price,
            );
        }

        $this->template->content = View::factory('erp410/price/index')
            ->set('rows', $rows)
            ->set('item_id', $item_id)
            ->set('customer_id', $customer_id)
            ->set('customers', $customers)
            ->set('history', $history)
            ->set('items', $this->_items())
            ->set('stats', $this->_prices()->stats())
            ->set('can_manage', $this->can('erp410.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Записать цену (базовую или клиента).
     */
    public function action_save()
    {
        if ( ! $this->can('erp410.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/prices');
        }

        $post = $this->request->post();

        try
        {
            $this->_prices()->record(array(
                'item_id'     => isset($post['item_id']) ? $post['item_id'] : NULL,
                'customer_id' => isset($post['customer_id']) ? $post['customer_id'] : NULL,
                'price'       => isset($post['price']) ? $post['price'] : 0,
                'discount'    => isset($post['discount']) ? $post['discount'] : 0,
                'source'      => 'manual',
                'valid_from'  => isset($post['valid_from']) ? $post['valid_from'] : NULL,
            ), $this->_current_user_id());

            $this->_flash('success', 'Цена записана');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось записать цену');
        }

        $this->redirect('/sales/prices');
    }
}
