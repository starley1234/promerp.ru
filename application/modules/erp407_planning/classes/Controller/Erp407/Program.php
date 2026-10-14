<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Производственные программы (erp407_planning): список, карточка, строки,
 * расчёт потребности и переходы состояний.
 *
 * Контроллер тонкий: правила (кто может править, когда считается расчёт, что
 * попадает в журнал) живут в сервисах `Service_Erp407_Programs` и
 * `Service_Erp407_Mrp`.
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Program extends Controller_Erp407_Main
{
    /**
     * Список программ с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp407.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'search'      => $this->_query('search'),
            'period_from' => $this->_query('period_from'),
            'period_to'   => $this->_query('period_to'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp407/program/index')
            ->set('rows', $this->_programs()->search($filters, $limit, $offset))
            ->set('total', $this->_programs()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp407_Program::$statuses)
            ->set('stats', $this->_mrp()->stats())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка программы: строки, последний расчёт и предложения.
     */
    public function action_view()
    {
        if ( ! $this->can('erp407.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $program = $this->_programs()->get($this->request->param('id'));

        if ( ! $program)
        {
            throw new HTTP_Exception_404('Программа не найдена');
        }

        $requirements = $this->_mrp()->requirements($program, array(), 500);

        $summary = $this->_mrp()->summarize(array_map(function ($row) {
            return $row->as_array();
        }, $requirements));

        $this->template->content = View::factory('erp407/program/view')
            ->set('program', $program)
            ->set('lines', $this->_programs()->lines($program->id))
            ->set('requirements', $requirements)
            ->set('summary', $summary)
            ->set('proposals', $this->_proposals_service()->by_status($program->id))
            ->set('proposal_stats', $this->_proposals_service()->stats())
            ->set('can_manage', $this->can('erp407.manage'))
            ->set('can_plan', $this->can('erp407.plan'))
            ->set('can_approve', $this->can('erp407.approve'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма программы (создание и правка).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $program = $this->_programs()->get($this->request->param('id'));

        if ($this->request->param('id') AND ! $program)
        {
            throw new HTTP_Exception_404('Программа не найдена');
        }

        $this->template->content = View::factory('erp407/program/form')
            ->set('program', $program)
            ->set('statuses', Model_Erp407_Program::$statuses)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить программу (создание или правка).
     */
    public function action_save()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/planning/programs');
        }

        $data = array(
            'title'        => (string) $this->request->post('title'),
            'period_from'  => (string) $this->request->post('period_from'),
            'period_to'    => (string) $this->request->post('period_to'),
            'horizon_days' => (int) $this->request->post('horizon_days'),
            'note'         => (string) $this->request->post('note'),
        );

        $id = $this->request->post('id');

        try
        {
            if ($id)
            {
                $program = $this->_programs()->update($id, $data, $this->_current_user_id());
                $this->_flash('success', 'Программа '.$program->number.' сохранена');
            }
            else
            {
                $program = $this->_programs()->create($data, $this->_current_user_id());
                $this->_flash('success', 'Программа '.$program->number.' создана — добавьте строки и рассчитайте потребность');
            }

            $this->redirect('/planning/programs/view/'.$program->id);
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect($id ? '/planning/programs/edit/'.$id : '/planning/programs/edit');
        }
    }

    /**
     * Переходы состояний программы: approve, close, delete.
     */
    public function action_act()
    {
        if ( ! $this->can('erp407.plan'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $act     = (string) $this->request->param('act');
        $id      = $this->request->param('id');
        $user_id = $this->_current_user_id();

        try
        {
            switch ($act)
            {
                case 'approve':
                    $program = $this->_programs()->approve($id, $user_id);
                    $this->_flash('success', 'Программа '.$program->number.' утверждена');
                    break;

                case 'close':
                    $program = $this->_programs()->close($id, $user_id);
                    $this->_flash('success', 'Программа '.$program->number.' закрыта');
                    break;

                case 'delete':
                    if ( ! $this->can('erp407.manage'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $program = $this->_programs()->get($id);
                    $this->_programs()->delete($program, $user_id);
                    $this->_flash('success', 'Программа удалена');
                    $this->redirect('/planning/programs');
                    return;

                default:
                    throw new Exception_Erp407_Planning('Неизвестное действие: '.$act, NULL, 'invalid_transition');
            }

            $this->redirect('/planning/programs/view/'.$program->id);
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/planning/programs/view/'.$id);
        }
    }

    /**
     * Расчёт потребности по программе.
     */
    public function action_calculate()
    {
        if ( ! $this->can('erp407.plan'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = $this->request->param('id');

        try
        {
            $result = $this->_mrp()->calculate($id);

            $message = 'Расчёт готов: строк '.count($result['rows']).', дефицитов '.$result['summary']['deficit'];

            if ($result['summary']['late'] > 0)
            {
                $message .= ' (просрочено по дате заказа: '.$result['summary']['late'].')';
            }

            $this->_flash('success', $message);

            foreach (array_slice($result['warnings'], 0, 3) as $warning)
            {
                $this->_flash('warning', $warning);
            }
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/planning/programs/view/'.$id);
    }

    /**
     * Собрать строки программы из открытых заказов производства.
     */
    public function action_fill()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = $this->request->param('id');

        try
        {
            $result = $this->_programs()->fill_from_orders($id, $this->_current_user_id());

            $this->_flash('success', 'Добавлено строк: '.$result['added'].', пропущено: '.$result['skipped']);
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/planning/programs/view/'.$id);
    }

    /**
     * Строки программы: добавление, правка, удаление.
     */
    public function action_line()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $action = (string) $this->request->param('action');
        $id     = $this->request->param('id');

        try
        {
            switch ($action)
            {
                case 'add':
                    $program_id = (int) $this->request->post('program_id');

                    $this->_programs()->add_line($program_id, array(
                        'name'         => (string) $this->request->post('name'),
                        'qty'          => (float) $this->request->post('qty'),
                        'uom_code'     => (string) $this->request->post('uom_code'),
                        'due_date'     => (string) $this->request->post('due_date'),
                        'code'         => (string) $this->request->post('code'),
                        'tech_card_id' => $this->request->post('tech_card_id'),
                    ), $this->_current_user_id());

                    $this->_flash('success', 'Строка добавлена');

                    $this->redirect('/planning/programs/view/'.$program_id);
                    return;

                case 'remove':
                    $line = $this->_programs()->line($id);

                    if ( ! $line)
                    {
                        throw new Exception_Erp407_Planning('Строка не найдена', NULL, 'not_found');
                    }

                    $program_id = (int) $line->program_id;

                    $this->_programs()->remove_line($id);

                    $this->_flash('success', 'Строка удалена');

                    $this->redirect('/planning/programs/view/'.$program_id);
                    return;

                default:
                    throw new Exception_Erp407_Planning('Неизвестное действие со строкой: '.$action, NULL, 'invalid_transition');
            }
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/planning/programs');
        }
    }
}
