<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Остатки на складах (erp401_wms).
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Balance extends Controller_Erp401_Main
{
    /**
     * Таблица остатков с фильтрами по складу и признаку «ниже минимума».
     */
    public function action_index()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stock = Service_Erp401_Stock::instance();

        $filters = array(
            'warehouse_id' => $this->request->param('id', $this->request->query('warehouse_id')),
            'low_only'     => (bool) $this->request->query('low_only'),
        );

        $page    = max(1, (int) $this->request->query('page'));
        $limit   = 50;
        $result  = $stock->balances($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp401/balance/index')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('filters', $filters)
            ->set('warehouses', $stock->warehouses(TRUE));
    }

    /**
     * Задать неснижаемый минимум по позиции.
     * POST: warehouse_id, item_id, min_qty
     */
    public function action_set_min()
    {
        if ( ! $this->can('erp401_wms.manage_warehouses'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/warehouse/balances');
        }

        $post         = $this->request->post();
        $warehouse_id = (int) Arr::get($post, 'warehouse_id');
        $item_id      = (int) Arr::get($post, 'item_id');
        $min_qty      = (float) str_replace(',', '.', (string) Arr::get($post, 'min_qty', 0));

        $balance = Service_Erp401_Stock::instance()->balance($warehouse_id, $item_id);

        if ($balance->loaded())
        {
            $balance->min_qty = max(0, $min_qty);
            $balance->save();

            $this->_flash('success', 'Минимум по позиции обновлён');
        }
        else
        {
            $this->_flash('error', 'Позиция не найдена на складе');
        }

        $this->redirect($this->request->referrer('/warehouse/balances'));
    }
}
