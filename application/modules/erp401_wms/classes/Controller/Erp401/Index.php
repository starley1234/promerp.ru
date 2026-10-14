<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сводка складского учёта (erp401_wms).
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Index extends Controller_Erp401_Main
{
    /**
     * Дашборд склада: складские итоги, минимумы, последние движения.
     */
    public function action_index()
    {
        if ( ! $this->can('erp401_wms.view_dashboard'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stock = Service_Erp401_Stock::instance();

        // Итоги по складам
        $warehouses = $stock->warehouses(TRUE);

        $totals = DB::select(
                array(DB::expr('COUNT(*)'), 'positions'),
                array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty'),
                array(DB::expr('COALESCE(SUM(reserved), 0)'), 'reserved')
            )
            ->from('erp_401_balances')
            ->execute()
            ->current();

        $low_stock = $stock->low_stock(10);

        $recent = $stock->movements(array(), 10, 0);

        $this->template->content = View::factory('erp401/index/index')
            ->set('warehouses', $warehouses)
            ->set('warehouses_count', count($warehouses))
            ->set('positions', (int) $totals['positions'])
            ->set('total_qty', (float) $totals['qty'])
            ->set('total_reserved', (float) $totals['reserved'])
            ->set('low_stock', $low_stock)
            ->set('recent', $recent['rows']);
    }
}
