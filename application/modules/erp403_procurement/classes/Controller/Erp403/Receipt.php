<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приёмки (erp403_procurement): список, карточка, правка позиций, входной
 * контроль и проведение (приход на склад erp401).
 *
 * Контроллер тонкий: правила живут в `Service_Erp403_Receipts`.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Receipt extends Controller_Erp403_Main
{
    /**
     * Список приёмок с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'supplier_id' => $this->_query('supplier_id'),
            'search'      => $this->_query('search'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp403/receipt/index')
            ->set('rows', $this->_receipts()->search($filters, $limit, $offset))
            ->set('total', $this->_receipts()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp403_Receipt::$statuses)
            ->set('suppliers', $this->_supplier_options())
            ->set('stats', $this->_receipts()->stats())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка приёмки: позиции, контроль, проведение.
     */
    public function action_view()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $receipt = $this->_receipts()->get($this->request->param('id'));

        if ( ! $receipt)
        {
            throw new HTTP_Exception_404('Приёмка не найдена');
        }

        $order = $receipt->order_id ? $this->_orders()->get($receipt->order_id) : NULL;

        $this->template->content = View::factory('erp403/receipt/view')
            ->set('receipt', $receipt)
            ->set('lines', $this->_receipts()->lines($receipt->id))
            ->set('order', $order)
            ->set('supplier', $receipt->supplier_id ? $this->_suppliers()->counterparty($receipt->supplier_id) : NULL)
            ->set('quality_statuses', Model_Erp403_ReceiptLine::$quality_statuses)
            ->set('warehouses', $this->_warehouses())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('can_approve', $this->can('erp403.approve'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Переход состояния: post | cancel | quality.
     */
    public function action_act()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/receipts');
        }

        $act     = (string) $this->request->param('act');
        $id      = (int) $this->request->param('id');
        $post    = $this->request->post();
        $user_id = $this->_current_user_id();

        try
        {
            if ($act === 'post')
            {
                if ( ! $this->can('erp403.approve'))
                {
                    throw new HTTP_Exception_403('Доступ запрещен');
                }

                if ( ! empty($post['warehouse_id']))
                {
                    $this->_receipts()->update($id, array('warehouse_id' => (int) $post['warehouse_id']));
                }

                $result = $this->_receipts()->post($id, $user_id);

                $this->_flash('success', 'Приёмка '.$result['receipt']->number.' проведена: приход оформлен по '.count($result['posted']).' позициям');

                foreach ($result['skipped'] as $row)
                {
                    $this->_flash('warning', $row['name'].': '.$row['reason']);
                }
            }
            elseif ($act === 'cancel')
            {
                $receipt = $this->_receipts()->cancel($id, isset($post['reason']) ? $post['reason'] : NULL, $user_id);
                $this->_flash('warning', 'Приёмка '.$receipt->number.' отменена');
            }
            elseif ($act === 'quality')
            {
                $line = $this->_receipts()->set_quality(
                    (int) $post['line_id'],
                    (string) $post['quality_status'],
                    isset($post['note']) ? $post['note'] : NULL
                );

                $this->_flash('success', 'Позиция «'.$line->name.'»: '.$line->quality_label());
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
            $this->_flash_error($e, 'Не удалось провести приёмку');
        }

        $this->redirect('/procurement/receipts/view/'.$id);
    }

    /**
     * Позиции приёмки: add | update | remove.
     */
    public function action_line()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/receipts');
        }

        $action     = (string) $this->request->param('action');
        $id         = (int) $this->request->param('id');
        $post       = $this->request->post();
        $receipt_id = ! empty($post['receipt_id']) ? (int) $post['receipt_id'] : NULL;

        try
        {
            if ($action === 'add')
            {
                if ( ! $receipt_id)
                {
                    throw new Exception_Erp403_Procurement('Не указана приёмка', NULL, 'not_found');
                }

                $line = $this->_receipts()->add_line($receipt_id, $post);

                $this->_flash('success', 'Позиция «'.$line->name.'» добавлена');
                $receipt_id = (int) $line->receipt_id;
            }
            elseif ($action === 'update')
            {
                $line = $this->_receipts()->update_line($id, $post);

                $receipt_id = (int) $line->receipt_id;
                $this->_flash('success', 'Позиция обновлена');
            }
            elseif ($action === 'remove')
            {
                $line = $this->_receipts()->line($id);
                $receipt_id = $line ? (int) $line->receipt_id : $receipt_id;

                $this->_receipts()->remove_line($id);
                $this->_flash('warning', 'Позиция удалена');
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
            $this->_flash_error($e, 'Не удалось изменить позицию');
        }

        $this->redirect($receipt_id ? '/procurement/receipts/view/'.$receipt_id : '/procurement/receipts');
    }
}
