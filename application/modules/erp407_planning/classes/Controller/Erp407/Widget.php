<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Виджеты планирования (erp407_planning): сводка для дашбордов, дефициты по
 * номенклатуре и предложения по программе.
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Widget extends Controller_Erp407_Main
{
    /** @var bool Виджет не рендерит общий шаблон — только свой фрагмент */
    public $auto_render = FALSE;

    /**
     * Сводка: программы, дефициты последнего расчёта, предложения в очереди.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp407.view'))
        {
            return;
        }

        $program = ORM::factory('Erp407_Program')
            ->where('run_uuid', 'IS NOT', NULL)
            ->order_by('run_at', 'DESC')
            ->find();

        $deficits = array();
        $summary  = array('deficit' => 0, 'late' => 0, 'shortage' => 0.0, 'coverage' => 100.0);

        if ($program->loaded())
        {
            $deficits = $this->_mrp()->requirements($program, array('only_deficit' => TRUE), 5);

            $summary = $this->_mrp()->summarize(array_map(function ($row) {
                return $row->as_array();
            }, $deficits));
        }

        $this->response->body(View::factory('erp407/widget/summary')
            ->set('stats', $this->_mrp()->stats())
            ->set('proposal_stats', $this->_proposals_service()->stats())
            ->set('program', $program->loaded() ? $program : NULL)
            ->set('deficits', $deficits)
            ->set('summary', $summary)
            ->render());
    }

    /**
     * Дефициты по позиции номенклатуры: нужно ли заказывать и когда.
     */
    public function action_item_deficits()
    {
        if ( ! $this->can('erp407.view'))
        {
            return;
        }

        $item_id = (int) $this->_query('item_id', $this->request->param('id'));

        if ( ! $item_id)
        {
            return;
        }

        $rows = DB::select()
            ->from('erp_407_requirements')
            ->where('item_id', '=', $item_id)
            ->order_by('created_at', 'DESC')
            ->limit(20)
            ->execute()
            ->as_array();

        $this->response->body(View::factory('erp407/widget/item_deficits')
            ->set('rows', $rows)
            ->set('item_id', $item_id)
            ->render());
    }

    /**
     * Предложения программы: очередь решений.
     */
    public function action_program_proposals()
    {
        if ( ! $this->can('erp407.view'))
        {
            return;
        }

        $program_id = (int) $this->_query('program_id', $this->request->param('id'));

        if ( ! $program_id)
        {
            return;
        }

        $this->response->body(View::factory('erp407/widget/program_proposals')
            ->set('proposals', $this->_proposals_service()->by_status($program_id))
            ->set('program_id', $program_id)
            ->render());
    }
}
