<?php defined('SYSPATH') or die('No direct script access.');

/**
 * HMVC-виджеты цеха (erp408_shopfloor).
 *
 * Подключаются с других страниц через Request::factory:
 *
 *     echo Request::factory('shopfloor/widget/summary')->execute()->body();
 *     echo Request::factory('shopfloor/widget/order_operations')->query('order_id', 5)->execute()->body();
 *
 * Виджет «маршрут заказа» показывает в карточке производства, что уже сделано
 * в цехе: сколько операций закрыто, сколько годных и брака, где простой.
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Widget extends Controller_Erp408_Main
{
    /** @var bool Виджет не рендерит общий шаблон — только свой фрагмент */
    public $auto_render = FALSE;

    /**
     * Сводка цеха: загрузка на день, работа, простои.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp408.view'))
        {
            return;
        }

        $day = Erp::day($this->_query('day'));

        $this->response->body(View::factory('erp408/widget/summary')
            ->set('board', $this->_operations()->board($day))
            ->set('stats', $this->_operations()->stats(7))
            ->set('day', $day)
            ->render());
    }

    /**
     * Маршрут заказа: операции цеха и их состояние.
     */
    public function action_order_operations()
    {
        if ( ! $this->can('erp408.view'))
        {
            return;
        }

        $order_id = (int) $this->_query('order_id', $this->request->param('id'));

        if ( ! $order_id)
        {
            return;
        }

        $operations = $this->_operations()->for_order($order_id);

        $this->response->body(View::factory('erp408/widget/order_operations')
            ->set('operations', $operations)
            ->set('order_id', $order_id)
            ->set('totals', $this->_totals($operations))
            ->render());
    }

    /**
     * Карточка операции для дашбордов и страниц объектов.
     */
    public function action_operation_card()
    {
        if ( ! $this->can('erp408.view'))
        {
            return;
        }

        $operation = $this->_operations()->get($this->_query('operation_id', $this->request->param('id')));

        if ( ! $operation)
        {
            return;
        }

        $this->response->body(View::factory('erp408/widget/operation_card')
            ->set('operation', $operation)
            ->set('logs', $this->_operations()->worklogs($operation['id'], 10))
            ->render());
    }

    /**
     * Итоги по маршруту: сколько операций закрыто и что сделано.
     *
     * @param   array   $operations
     * @return  array
     */
    protected function _totals(array $operations)
    {
        $totals = array(
            'total'      => count($operations),
            'done'       => 0,
            'in_work'    => 0,
            'planned'    => 0,
            'qty_ok'     => 0.0,
            'qty_scrap'  => 0.0,
            'progress'   => 0,
        );

        $qty_planned = 0.0;

        foreach ($operations as $operation)
        {
            if ($operation['status'] === 'done')
            {
                $totals['done']++;
            }
            elseif ($operation['status'] === 'in_progress' OR $operation['status'] === 'paused')
            {
                $totals['in_work']++;
            }
            elseif ($operation['status'] === 'planned')
            {
                $totals['planned']++;
            }

            $totals['qty_ok']    += (float) $operation['qty_ok'];
            $totals['qty_scrap'] += (float) $operation['qty_scrap'];
            $qty_planned         += (float) $operation['qty_planned'];
        }

        if ($qty_planned > 0)
        {
            $totals['progress'] = (int) min(100, round($totals['qty_ok'] / $qty_planned * 100));
        }
        elseif ($totals['total'] > 0)
        {
            $totals['progress'] = (int) round($totals['done'] / $totals['total'] * 100);
        }

        return $totals;
    }
}
