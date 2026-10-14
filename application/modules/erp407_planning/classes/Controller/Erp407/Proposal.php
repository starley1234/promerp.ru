<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Предложения плана (erp407_planning): список, ручное создание, правка,
 * утверждение, передача в исполнение и закрытие.
 *
 * Передача в исполнение для производства создаёт заказ erp402, для закупки —
 * заявку erp403 (когда модуль подключён). Это делает сервис
 * `Service_Erp407_Proposals`; контроллер лишь показывает результат.
 *
 * @package    Erp407
 * @category   Controllers
 */
class Controller_Erp407_Proposal extends Controller_Erp407_Main
{
    /**
     * Список предложений.
     */
    public function action_index()
    {
        if ( ! $this->can('erp407.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'     => $this->_query('status'),
            'kind'       => $this->_query('kind'),
            'program_id' => $this->_query('program_id'),
            'search'     => $this->_query('search'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp407/proposal/index')
            ->set('rows', $this->_proposals_service()->search($filters, $limit, $offset))
            ->set('total', $this->_proposals_service()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('statuses', Model_Erp407_Proposal::$statuses)
            ->set('kinds', Model_Erp407_Proposal::$kinds)
            ->set('stats', $this->_proposals_service()->stats())
            ->set('can_plan', $this->can('erp407.plan'))
            ->set('can_approve', $this->can('erp407.approve'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма предложения (создание и правка).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $proposal = $this->_proposals_service()->get($this->request->param('id'));

        if ($this->request->param('id') AND ! $proposal)
        {
            throw new HTTP_Exception_404('Предложение не найдено');
        }

        $this->template->content = View::factory('erp407/proposal/form')
            ->set('proposal', $proposal)
            ->set('kinds', Model_Erp407_Proposal::$kinds)
            ->set('priorities', Model_Erp407_Proposal::$priorities)
            ->set('programs', $this->_programs()->search(array(), 50, 0))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить предложение.
     */
    public function action_save()
    {
        if ( ! $this->can('erp407.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id   = $this->request->post('id');
        $data = array(
            'program_id' => $this->request->post('program_id'),
            'kind'       => (string) $this->request->post('kind'),
            'name'       => (string) $this->request->post('name'),
            'qty'        => (float) $this->request->post('qty'),
            'uom_code'   => (string) $this->request->post('uom_code'),
            'due_date'   => (string) $this->request->post('due_date'),
            'order_date' => (string) $this->request->post('order_date'),
            'priority'   => (int) $this->request->post('priority'),
            'note'       => (string) $this->request->post('note'),
        );

        try
        {
            if ($id)
            {
                $proposal = $this->_proposals_service()->update($id, $data);
                $this->_flash('success', 'Предложение '.$proposal->number.' сохранено');
            }
            else
            {
                $proposal = $this->_proposals_service()->create($data, $this->_current_user_id());
                $this->_flash('success', 'Предложение '.$proposal->number.' создано');
            }

            $this->redirect('/planning/proposals');
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect($id ? '/planning/proposals/edit/'.$id : '/planning/proposals/edit');
        }
    }

    /**
     * Завести предложения по дефицитам программы (одной кнопкой).
     */
    public function action_create()
    {
        if ( ! $this->can('erp407.plan'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $program_id = (int) $this->request->post('program_id');
        $only_late  = (bool) $this->request->post('only_late');

        try
        {
            $result = $this->_proposals_service()->create_from_requirements($program_id, array(
                'only_late' => $only_late,
            ), $this->_current_user_id());

            if ($result['created'])
            {
                $this->_flash('success', 'Создано предложений: '.$result['created'].' (уже было открытых: '.$result['skipped'].')');
            }
            else
            {
                $this->_flash('info', 'Новых предложений нет: по всем дефицитам уже есть открытые предложения ('.$result['skipped'].')');
            }

            $this->redirect('/planning/proposals?program_id='.$program_id);
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/planning/programs/view/'.$program_id);
        }
    }

    /**
     * Действия над предложением: approve, export, complete, cancel.
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
                    $proposal = $this->_proposals_service()->approve($id, $user_id);
                    $this->_flash('success', 'Предложение '.$proposal->number.' утверждено');
                    break;

                case 'export':
                    if ( ! $this->can('erp407.approve'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $result = $this->_proposals_service()->export($id, $user_id);

                    $this->_flash('success', 'Предложение '.$result['proposal']->number.' передано: '.$result['target']);

                    if ($result['warning'])
                    {
                        $this->_flash('warning', $result['warning']);
                    }
                    break;

                case 'complete':
                    $proposal = $this->_proposals_service()->complete($id, $user_id);
                    $this->_flash('success', 'Предложение '.$proposal->number.' исполнено');
                    break;

                case 'cancel':
                    $proposal = $this->_proposals_service()->cancel($id, (string) $this->request->post('reason'), $user_id);
                    $this->_flash('success', 'Предложение '.$proposal->number.' отклонено');
                    break;

                default:
                    throw new Exception_Erp407_Planning('Неизвестное действие: '.$act, NULL, 'invalid_transition');
            }

            $this->redirect('/planning/proposals');
        }
        catch (Exception_Erp407_Planning $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/planning/proposals');
        }
    }
}
