<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Счета (erp410_shipment): список, карточка, выставление, оплата и отмена.
 *
 * @package    Erp410
 * @category   Controllers
 */
class Controller_Erp410_Invoice extends Controller_Erp410_Main
{
    /**
     * Список счетов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'customer_id' => $this->_query('customer_id'),
            'order_id'    => $this->_query('order_id'),
            'search'      => $this->_query('search'),
            'overdue'     => $this->_query('overdue'),
            'unpaid'      => $this->_query('unpaid'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp410/invoice/index')
            ->set('rows', $this->_invoices()->search($filters, $limit, $offset))
            ->set('total', $this->_invoices()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp410_Invoice::$statuses)
            ->set('customers', $this->_customer_options())
            ->set('stats', $this->_invoices()->stats())
            ->set('can_invoice', $this->can('erp410.invoice'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка счёта: позиции, оплата, документы-основания.
     */
    public function action_view()
    {
        if ( ! $this->can('erp410.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $invoice = $this->_invoices()->get($this->request->param('id'));

        if ( ! $invoice)
        {
            throw new HTTP_Exception_404('Счёт не найден');
        }

        $this->template->content = View::factory('erp410/invoice/view')
            ->set('invoice', $invoice)
            ->set('lines', $this->_invoices()->lines($invoice->id))
            ->set('order', $invoice->order_id ? $this->_orders()->get($invoice->order_id) : NULL)
            ->set('shipment', $invoice->shipment_id ? $this->_shipments()->get($invoice->shipment_id) : NULL)
            ->set('customer', $invoice->customer_id ? $this->_customers()->name($invoice->customer_id) : NULL)
            ->set('can_invoice', $this->can('erp410.invoice'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Переход: issue | pay | cancel.
     */
    public function action_act()
    {
        if ( ! $this->can('erp410.invoice'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/sales/invoices');
        }

        $act     = (string) $this->request->param('act');
        $id      = (int) $this->request->param('id');
        $post    = $this->request->post();
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'issue')
            {
                $invoice = $this->_invoices()->issue($id, $user_id);
                $this->_flash('success', 'Счёт '.$invoice->number.' выставлен клиенту');
            }
            elseif ($act === 'pay')
            {
                $invoice = $this->_invoices()->register_payment($id, isset($post['amount']) ? $post['amount'] : 0, $user_id);

                $this->_flash(
                    ((string) $invoice->status === 'paid') ? 'success' : 'info',
                    ((string) $invoice->status === 'paid')
                        ? 'Счёт '.$invoice->number.' оплачен'
                        : 'Оплата принята, остаток '.number_format($invoice->outstanding(), 2, '.', ' ').' ₽'
                );
            }
            elseif ($act === 'cancel')
            {
                $invoice = $this->_invoices()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Счёт '.$invoice->number.' отменён');
            }
            else
            {
                throw new HTTP_Exception_404('Неизвестное действие');
            }
        }
        catch (HTTP_Exception $e)
        {
            throw $e;
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось изменить счёт');
        }

        $this->redirect('/sales/invoices/view/'.$id);
    }
}
