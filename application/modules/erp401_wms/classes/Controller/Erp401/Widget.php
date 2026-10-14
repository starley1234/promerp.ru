<?php defined('SYSPATH') or die('No direct script access.');

/**
 * HMVC-виджеты складского учёта (erp401_wms).
 *
 * Виджеты подключаются с других страниц через Request::factory:
 *
 *     echo Request::factory('warehouse/widget/low_stock')->execute()->body();
 *     echo Request::factory('warehouse/widget/item_card')->query('item_id', 3)->execute()->body();
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Widget extends Controller_Erp401_Main
{
    /**
     * Виджет не рендерит общий шаблон — только свой фрагмент.
     *
     * @var bool
     */
    public $auto_render = FALSE;

    /**
     * Позиции ниже минимума. Параметр limit.
     */
    public function action_low_stock()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            return;
        }

        $limit = (int) $this->_query('limit', 10);
        $rows  = Service_Erp401_Stock::instance()->low_stock($limit ?: 10);

        $this->response->body(View::factory('erp401/widget/low_stock')
            ->set('rows', $rows)
            ->render());
    }

    /**
     * Остатки конкретной позиции по складам. Параметр item_id.
     * Используется в карточке объекта реестра erp227.
     */
    public function action_item_card()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            return;
        }

        $item_id = (int) $this->_query('item_id', $this->request->param('id'));

        if ($item_id <= 0)
        {
            return;
        }

        $stock = Service_Erp401_Stock::instance();
        $rows  = $stock->balances(array('item_id' => $item_id), 50, 0);

        $this->response->body(View::factory('erp401/widget/item_card')
            ->set('item', $stock->item($item_id))
            ->set('rows', $rows['rows'])
            ->set('total', $stock->total_qty($item_id))
            ->render());
    }

    /**
     * Компактная сводка для дашборда: позиций, единиц, зарезервировано, минимумов.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp401_wms.view_dashboard'))
        {
            return;
        }

        $stock = Service_Erp401_Stock::instance();

        $totals = DB::select(
                array(DB::expr('COUNT(*)'), 'positions'),
                array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty'),
                array(DB::expr('COALESCE(SUM(reserved), 0)'), 'reserved')
            )
            ->from('erp_401_balances')
            ->execute()
            ->current();

        $this->response->body(View::factory('erp401/widget/summary')
            ->set('positions', (int) $totals['positions'])
            ->set('qty', (float) $totals['qty'])
            ->set('reserved', (float) $totals['reserved'])
            ->set('low_count', count($stock->low_stock(100)))
            ->render());
    }
}
