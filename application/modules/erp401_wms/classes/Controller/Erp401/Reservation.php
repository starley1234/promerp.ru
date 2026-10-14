<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Резервы номенклатуры под заказы и производство (erp401_wms).
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Reservation extends Controller_Erp401_Main
{
    /**
     * Список резервов.
     */
    public function action_index()
    {
        if ( ! $this->can('erp401_wms.reserve'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stock = Service_Erp401_Stock::instance();

        $filters = array(
            'warehouse_id' => $this->request->query('warehouse_id'),
            'status'       => $this->_query('status', 'active'),
            'ref_type'     => $this->request->query('ref_type'),
        );

        $page   = max(1, (int) $this->request->query('page'));
        $limit  = 50;
        $result = $stock->reservations($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp401/reservation/index')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('filters', $filters)
            ->set('statuses', Model_Erp401_Reservation::$statuses)
            ->set('warehouses', $stock->warehouses(TRUE))
            ->set('catalog', $stock->catalog())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Создать резерв (POST): warehouse_id, item_id, qty, ref_type, ref_id, expires_at
     */
    public function action_create()
    {
        if ( ! $this->can('erp401_wms.reserve'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/warehouse/reservations');
        }

        $post = $this->request->post();

        try
        {
            $reservation = Service_Erp401_Stock::instance()->reserve(
                (int) Arr::get($post, 'warehouse_id'),
                (int) Arr::get($post, 'item_id'),
                str_replace(',', '.', (string) Arr::get($post, 'qty', '0')),
                array(
                    'ref_type'   => Arr::get($post, 'ref_type'),
                    'ref_id'     => Arr::get($post, 'ref_id') ? (int) Arr::get($post, 'ref_id') : NULL,
                    'expires_at' => Arr::get($post, 'expires_at') ? (string) Arr::get($post, 'expires_at') : NULL,
                )
            );

            $this->_flash('success', 'Резерв #'.$reservation->id.' создан');
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp401: ошибка создания резерва — :error',
                array(':error' => $e->getMessage()));

            $this->_flash('error', 'Не удалось создать резерв. Подробности в журнале.');
        }

        $this->redirect('/warehouse/reservations');
    }

    /**
     * Снять резерв.
     */
    public function action_release()
    {
        if ( ! $this->can('erp401_wms.reserve'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        try
        {
            $reservation = Service_Erp401_Stock::instance()->release_reservation($id);
            $this->_flash('success', 'Резерв #'.$reservation->id.' снят');
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/warehouse/reservations');
    }

    /**
     * Списать резерв (превратить в расход).
     */
    public function action_consume()
    {
        if ( ! $this->can('erp401_wms.manage_movements'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        try
        {
            $reservation = Service_Erp401_Stock::instance()->consume_reservation($id);
            $this->_flash('success', 'Резерв #'.$reservation->id.' списан со склада');
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/warehouse/reservations');
    }
}
