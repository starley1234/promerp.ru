<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Доска планирования (erp407_planning).
 *
 * Отвечает на вопрос «чем заняться сегодня»: что считает последний расчёт,
 * где дефицит, что просрочено по дате заказа, какие предложения ждут решения.
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Index extends Controller_Erp407_Main
{
    /**
     * Сводка: программы, дефициты последнего прогона, просроченные предложения.
     */
    public function action_index()
    {
        if ( ! $this->can('erp407.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stats  = $this->_mrp()->stats();
        $latest = $this->_latest_requirements(10);

        $this->template->content = View::factory('erp407/index/index')
            ->set('stats', $stats)
            ->set('deficits', $latest['rows'])
            ->set('deficit_summary', $latest['summary'])
            ->set('programs', $this->_programs()->search(array(), 5, 0))
            ->set('proposals', $this->_proposals_service()->search(array('status' => array('new', 'approved')), 10, 0))
            ->set('proposal_stats', $this->_proposals_service()->stats())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Только дефициты: список позиций, которых не хватает.
     */
    public function action_deficits()
    {
        if ( ! $this->can('erp407.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $program_id = $this->_query('program_id');
        $kind       = $this->_query('kind');

        $rows    = array();
        $summary = array('deficit' => 0, 'late' => 0, 'shortage' => 0.0, 'coverage' => 100.0);

        if ($program_id)
        {
            $program = $this->_programs()->get($program_id);

            if ($program)
            {
                $rows = $this->_mrp()->requirements($program, array(
                    'only_deficit' => TRUE,
                    'supply_type'  => $kind,
                ), 500);

                $summary = $this->_mrp()->summarize(array_map(function ($row) {
                    return $row->as_array();
                }, $rows));
            }
        }
        else
        {
            $latest  = $this->_latest_requirements(200);
            $rows    = $latest['rows'];
            $summary = $latest['summary'];
        }

        $this->template->content = View::factory('erp407/index/deficits')
            ->set('rows', $rows)
            ->set('summary', $summary)
            ->set('program_id', $program_id)
            ->set('kind', $kind)
            ->set('programs', $this->_programs()->search(array(), 50, 0))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Дефициты последнего выполненного расчёта (по всем программам).
     *
     * @param   int     $limit
     * @return  array   rows, summary
     */
    protected function _latest_requirements($limit = 10)
    {
        $latest = ORM::factory('Erp407_Program')
            ->where('run_uuid', 'IS NOT', NULL)
            ->order_by('run_at', 'DESC')
            ->find();

        if ( ! $latest->loaded())
        {
            return array('rows' => array(), 'summary' => array('deficit' => 0, 'late' => 0, 'shortage' => 0.0, 'coverage' => 100.0));
        }

        $rows = $this->_mrp()->requirements($latest, array('only_deficit' => TRUE), $limit);

        $summary = $this->_mrp()->summarize(array_map(function ($row) {
            return $row->as_array();
        }, $rows));

        return array('rows' => $rows, 'summary' => $summary);
    }
}
