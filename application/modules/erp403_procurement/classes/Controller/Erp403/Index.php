<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Доска закупок (erp403_procurement).
 *
 * Отвечает на вопрос «чем заняться сегодня»: что ждёт согласования, что
 * заказано и не пришло, что просрочено, чего не хватает на складе и где
 * поставщики дают цену лучше.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Index extends Controller_Erp403_Main
{
    /**
     * Сводка: заявки, заказы, приёмки, поставщики.
     */
    public function action_index()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $low_stock = array();

        try
        {
            // erp401 owns stock facts and returns the item as a nested object.
            // Keep the dashboard on erp403's normalized integration contract so
            // the view, API and MCP always receive flat name/code fields.
            $low_stock = $this->_requests()->low_stock_rows(10);
        }
        catch (Exception $e)
        {
            $low_stock = array();
            Kohana::$log->add(Log::WARNING, 'erp403: минимум склада недоступен — :error', array(
                ':error' => $e->getMessage(),
            ));
        }

        $this->template->content = View::factory('erp403/index/index')
            ->set('request_stats', $this->_requests()->stats())
            ->set('order_stats', $this->_orders()->stats())
            ->set('receipt_stats', $this->_receipts()->stats())
            ->set('supplier_stats', $this->_suppliers()->stats())
            ->set('approved_amount', $this->_requests()->approved_amount())
            ->set('submitted', $this->_requests()->search(array('status' => array('submitted')), 10, 0))
            ->set('approved', $this->_requests()->search(array('status' => array('approved')), 10, 0))
            ->set('due_soon', $this->_orders()->due_soon(14, 10))
            ->set('overdue', $this->_orders()->search(array('overdue' => TRUE), 10, 0))
            ->set('low_stock', $low_stock)
            ->set('savings', $this->_suppliers()->savings(5))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Дефицит склада: что опустилось до минимума и что из этого уже заказано.
     */
    public function action_low_stock()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $rows      = array();
        $warehouse = $this->_query('warehouse_id');

        try
        {
            foreach ($this->_requests()->low_stock_rows(200) as $row)
            {
                if ($warehouse AND (int) $row['warehouse_id'] !== (int) $warehouse)
                {
                    continue;
                }

                $rows[] = $row;
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось прочитать остатки склада');
        }

        $this->template->content = View::factory('erp403/index/low_stock')
            ->set('rows', $rows)
            ->set('warehouse_id', $warehouse)
            ->set('warehouses', $this->_warehouses())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Создать заявки по минимуму склада (POST).
     */
    public function action_auto_requests()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/low-stock');
        }

        $post = $this->request->post();

        try
        {
            $result = $this->_requests()->auto_from_low_stock(array(
                'warehouse_id' => ! empty($post['warehouse_id']) ? (int) $post['warehouse_id'] : NULL,
                'need_days'    => ! empty($post['need_days']) ? (int) $post['need_days'] : 14,
            ), $this->_current_user_id());

            if ($result['created'])
            {
                $this->_flash('success', 'Создано заявок: '.count($result['created']).'. Они ждут согласования.');
            }
            else
            {
                $this->_flash('info', $result['skipped']
                    ? 'Новых заявок нет: по всем позициям уже есть открытые заявки или заказы.'
                    : 'Позиций на минимуме нет — заказывать нечего.');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/procurement/low-stock');
    }
}
