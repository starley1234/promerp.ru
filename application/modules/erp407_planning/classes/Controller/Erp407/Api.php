<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API планирования (erp407_planning).
 *
 * Точка входа для машин (интеграции, внешние воркеры, агенты):
 * программы, расчёт потребности, дефициты, предложения и их переходы.
 * Ответ об ошибке обязан останавливать выполнение: Konine не прерывает запрос
 * после `before()` и не выходит из действия после отправки ответа, поэтому
 * после `_json_fail()` стоит `return;`, а в `before()` — `_json_halt()`
 * (следит `dev:audit --check=json_guards`).
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Api extends Controller_Erp407_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;

        if ( ! $this->request->is_initial())
        {
            return;
        }

        $token = (string) $this->request->query('token');

        if ($token !== '' AND defined('MIGRATIONS_TOKEN') AND $token === MIGRATIONS_TOKEN)
        {
            return;
        }

        if ( ! $this->can('erp407.view'))
        {
            $this->_json_halt('Доступ запрещен', 'forbidden', 403);
        }
    }

    /**
     * Сводка планирования.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'planning'  => $this->_mrp()->stats(),
            'proposals' => $this->_proposals_service()->stats(),
        ));
    }

    /**
     * Список программ.
     */
    public function action_programs()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status' => $this->_query('status'),
            'search' => $this->_query('search'),
        );

        $rows = array();

        foreach ($this->_programs()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $program)
        {
            $rows[] = $this->_program_row($program);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_programs()->count($filters)));
    }

    /**
     * Программа целиком: строки, последний расчёт, предложения.
     */
    public function action_program()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $program = $this->_programs()->get($this->_query('id', $this->request->param('id')));

        if ( ! $program)
        {
            $this->_json_fail('Программа не найдена', 'not_found', 404);
            return;
        }

        $lines = array();

        foreach ($this->_programs()->lines($program->id) as $line)
        {
            $lines[] = array(
                'id'           => (int) $line->id,
                'line_no'      => (int) $line->line_no,
                'item_id'      => $line->item_id ? (int) $line->item_id : NULL,
                'name'         => (string) $line->name,
                'code'         => $line->code ? (string) $line->code : NULL,
                'qty'          => (float) $line->qty,
                'uom_code'     => (string) $line->uom_code,
                'due_date'     => $line->due_date ? (string) $line->due_date : NULL,
                'tech_card_id' => $line->tech_card_id ? (int) $line->tech_card_id : NULL,
                'status'       => (string) $line->status,
                'status_label' => $line->status_label(),
            );
        }

        $this->_json_ok(array(
            'program'      => $this->_program_row($program),
            'lines'        => $lines,
            'requirements' => $this->_requirement_rows($program),
            'proposals'    => $this->_proposal_rows($this->_proposals_service()->search(array('program_id' => $program->id), 200)),
        ));
    }

    /**
     * Создать программу.
     */
    public function action_program_create()
    {
        if ( ! $this->can('erp407.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $program = $this->_programs()->create($this->_posted(array(
                'title', 'period_from', 'period_to', 'horizon_days', 'note',
            )), $this->_current_user_id());
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($this->_program_row($program), 201);
    }

    /**
     * Изменить программу.
     */
    public function action_program_update()
    {
        if ( ! $this->can('erp407.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $program = $this->_programs()->update(
                $this->_posted(array('id')),
                $this->_posted(array('title', 'period_from', 'period_to', 'horizon_days', 'note')),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($this->_program_row($program));
    }

    /**
     * Добавить строку программы.
     */
    public function action_program_line_add()
    {
        if ( ! $this->can('erp407.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $line = $this->_programs()->add_line(
                $this->_posted(array('program_id')),
                $this->_posted(array('item_id', 'name', 'code', 'qty', 'uom_code', 'due_date', 'tech_card_id', 'note')),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'id'      => (int) $line->id,
            'line_no' => (int) $line->line_no,
            'name'    => (string) $line->name,
            'qty'     => (float) $line->qty,
        ), 201);
    }

    /**
     * Переход состояния программы (approve, close, delete).
     */
    public function action_program_act()
    {
        if ( ! $this->can('erp407.plan'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act = (string) $this->_posted(array('act'));
        $id  = $this->_posted(array('id'));

        try
        {
            switch ($act)
            {
                case 'approve':
                    $program = $this->_programs()->approve($id, $this->_current_user_id());

                    $this->_json_ok($this->_program_row($program));
                    return;

                case 'close':
                    $program = $this->_programs()->close($id, $this->_current_user_id());

                    $this->_json_ok($this->_program_row($program));
                    return;

                case 'delete':
                    if ( ! $this->can('erp407.manage'))
                    {
                        $this->_json_fail('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $this->_programs()->delete($this->_programs()->get($id), $this->_current_user_id());

                    $this->_json_ok(array('deleted' => TRUE));
                    return;
            }
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_fail('Неизвестное действие: '.$act, 'invalid_transition', 400);
    }

    /**
     * Расчёт потребности по программе.
     */
    public function action_calculate()
    {
        if ( ! $this->can('erp407.plan'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $result = $this->_mrp()->calculate($this->_posted(array('id')), array(
                'warehouse_id' => $this->_posted(array('warehouse_id')),
                'max_levels'   => $this->_posted(array('max_levels')),
            ));
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'run_uuid' => $result['run_uuid'],
            'summary'  => $result['summary'],
            'rows'     => $result['rows'],
            'warnings' => $result['warnings'],
        ));
    }

    /**
     * Расчёт по открытым заказам производства (без сохранения снимка).
     */
    public function action_calculate_orders()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $result = $this->_mrp()->calculate_for_orders(array(
                'warehouse_id' => $this->_posted(array('warehouse_id')),
            ));
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'summary'  => $result['summary'],
            'rows'     => $result['rows'],
            'warnings' => $result['warnings'],
        ));
    }

    /**
     * Потребность последнего расчёта программы (или дефициты по всем программам).
     */
    public function action_requirements()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $program = $this->_programs()->get($this->_query('program_id'));

        if ( ! $program)
        {
            $this->_json_fail('Программа не найдена', 'not_found', 404);
            return;
        }

        $filters = array(
            'only_deficit' => (bool) $this->_query('only_deficit'),
            'supply_type'  => $this->_query('kind'),
        );

        $requirements = $this->_mrp()->requirements($program, $filters);

        $this->_json_ok(array(
            'run_uuid' => $program->run_uuid ? (string) $program->run_uuid : NULL,
            'rows'     => $this->_requirement_rows($program, $filters),
            'summary'  => $this->_mrp()->summarize(array_map(function ($row) {
                return $row->as_array();
            }, $requirements)),
        ));
    }

    /**
     * Предложения: список.
     */
    public function action_proposals()
    {
        if ( ! $this->can('erp407.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'     => $this->_query('status'),
            'kind'       => $this->_query('kind'),
            'program_id' => $this->_query('program_id'),
            'item_id'    => $this->_query('item_id'),
        );

        $this->_json_ok(array(
            'rows'  => $this->_proposal_rows($this->_proposals_service()->search($filters, (int) $this->_query('limit', 50), (int) $this->_query('offset', 0))),
            'total' => $this->_proposals_service()->count($filters),
            'stats' => $this->_proposals_service()->stats(),
        ));
    }

    /**
     * Создать предложения по дефицитам программы.
     */
    public function action_proposals_create()
    {
        if ( ! $this->can('erp407.plan'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $result = $this->_proposals_service()->create_from_requirements(
                $this->_posted(array('program_id')),
                array('only_late' => (bool) $this->_posted(array('only_late'))),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok($result, 201);
    }

    /**
     * Переход состояния предложения: approve, export, complete, cancel.
     */
    public function action_proposal_act()
    {
        if ( ! $this->can('erp407.plan'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $act = (string) $this->_posted(array('act'));
        $id  = $this->_posted(array('id'));

        try
        {
            switch ($act)
            {
                case 'approve':
                    $this->_json_ok($this->_proposal_row($this->_proposals_service()->approve($id, $this->_current_user_id())));
                    return;

                case 'export':
                    if ( ! $this->can('erp407.approve'))
                    {
                        $this->_json_fail('Доступ запрещен', 'forbidden', 403);
                        return;
                    }

                    $result = $this->_proposals_service()->export($id, $this->_current_user_id());

                    $this->_json_ok(array(
                        'proposal' => $this->_proposal_row($result['proposal']),
                        'target'   => $result['target'],
                        'warning'  => $result['warning'],
                    ));
                    return;

                case 'complete':
                    $this->_json_ok($this->_proposal_row($this->_proposals_service()->complete($id, $this->_current_user_id())));
                    return;

                case 'cancel':
                    $this->_json_ok($this->_proposal_row($this->_proposals_service()->cancel($id, $this->_posted(array('reason')), $this->_current_user_id())));
                    return;
            }
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_fail('Неизвестное действие: '.$act, 'invalid_transition', 400);
    }

    // ------------------------------------------------------------------ данные --

    /**
     * @param   Model_Erp407_Program    $program
     * @return  array
     */
    protected function _program_row(Model_Erp407_Program $program)
    {
        return array(
            'id'           => (int) $program->id,
            'uuid'         => (string) $program->uuid,
            'number'       => (string) $program->number,
            'title'        => (string) $program->title,
            'status'       => (string) $program->status,
            'status_label' => $program->status_label(),
            'period_from'  => (string) $program->period_from,
            'period_to'    => (string) $program->period_to,
            'horizon_days' => (int) $program->horizon_days,
            'source'       => (string) $program->source,
            'run_at'       => $program->run_at ? (string) $program->run_at : NULL,
            'days_left'    => $program->days_left(),
            'note'         => $program->note ? (string) $program->note : NULL,
        );
    }

    /**
     * @param   Model_Erp407_Program    $program
     * @param   array                   $filters
     * @return  array
     */
    protected function _requirement_rows(Model_Erp407_Program $program, array $filters = array())
    {
        $rows = array();

        foreach ($this->_mrp()->requirements($program, $filters, 500) as $requirement)
        {
            $rows[] = array(
                'id'             => (int) $requirement->id,
                'name'           => (string) $requirement->name,
                'code'           => $requirement->code ? (string) $requirement->code : NULL,
                'item_id'        => $requirement->item_id ? (int) $requirement->item_id : NULL,
                'uom_code'       => (string) $requirement->uom_code,
                'supply_type'    => (string) $requirement->supply_type,
                'supply_label'   => $requirement->supply_label(),
                'demand_qty'     => (float) $requirement->demand_qty,
                'stock_qty'      => (float) $requirement->stock_qty,
                'reserved_qty'   => (float) $requirement->reserved_qty,
                'wip_qty'        => (float) $requirement->wip_qty,
                'on_order_qty'   => (float) $requirement->on_order_qty,
                'covered_qty'    => (float) $requirement->covered_qty,
                'deficit_qty'    => (float) $requirement->deficit_qty,
                'order_qty'      => (float) $requirement->order_qty,
                'lead_time_days' => (int) $requirement->lead_time_days,
                'due_date'       => $requirement->due_date ? (string) $requirement->due_date : NULL,
                'order_date'     => $requirement->order_date ? (string) $requirement->order_date : NULL,
                'late'           => $requirement->is_late(),
                'level'          => (int) $requirement->level,
                'parent_name'    => $requirement->parent_name ? (string) $requirement->parent_name : NULL,
                'coverage'       => $requirement->coverage(),
            );
        }

        return $rows;
    }

    /**
     * @param   array   $proposals
     * @return  array
     */
    protected function _proposal_rows(array $proposals)
    {
        $rows = array();

        foreach ($proposals as $proposal)
        {
            $rows[] = $this->_proposal_row($proposal);
        }

        return $rows;
    }

    /**
     * @param   Model_Erp407_Proposal   $proposal
     * @return  array
     */
    protected function _proposal_row(Model_Erp407_Proposal $proposal)
    {
        return array(
            'id'            => (int) $proposal->id,
            'uuid'          => (string) $proposal->uuid,
            'number'        => (string) $proposal->number,
            'name'          => (string) $proposal->name,
            'code'          => $proposal->code ? (string) $proposal->code : NULL,
            'item_id'       => $proposal->item_id ? (int) $proposal->item_id : NULL,
            'kind'          => (string) $proposal->kind,
            'kind_label'    => $proposal->kind_label(),
            'qty'           => (float) $proposal->qty,
            'uom_code'      => (string) $proposal->uom_code,
            'status'        => (string) $proposal->status,
            'status_label'  => $proposal->status_label(),
            'priority'      => (int) $proposal->priority,
            'due_date'      => $proposal->due_date ? (string) $proposal->due_date : NULL,
            'order_date'    => $proposal->order_date ? (string) $proposal->order_date : NULL,
            'days_to_order' => $proposal->days_to_order(),
            'program_id'    => (int) $proposal->program_id,
            'target_ref'    => $proposal->target_ref ? (string) $proposal->target_ref : NULL,
            'note'          => $proposal->note ? (string) $proposal->note : NULL,
        );
    }

    /**
     * Значения из POST (или GET, если POST пуст) по списку ключей.
     *
     * @param   array   $keys
     * @return  array
     */
    protected function _posted(array $keys)
    {
        $data = array();

        foreach ($keys as $key)
        {
            $value = $this->request->post($key);

            if ($value === NULL OR $value === '')
            {
                $value = $this->request->query($key);
            }

            if ($value !== NULL)
            {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
