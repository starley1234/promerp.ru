<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Рабочие центры и участки (erp408_shopfloor).
 *
 * Справочник ведёт мастер или технолог: где стоит оборудование, сколько оно
 * делает за час и сколько стоит нормо-час. Стоимость нужна себестоимости
 * (erp409), производительность — планированию загрузки. Центр, по которому
 * уже есть операции, не удаляется — только выключается.
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Centers extends Controller_Erp408_Main
{
    /**
     * GET /shopfloor/centers?term=&kind=&active=
     */
    public function action_index()
    {
        if ( ! $this->can('erp408.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $query   = $this->request->query();
        $filters = array(
            'term'   => isset($query['term']) ? $query['term'] : NULL,
            'kind'   => isset($query['kind']) ? $query['kind'] : NULL,
            'active' => isset($query['active']) ? $query['active'] : NULL,
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 50;
        $result = $this->_centers()->search($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp408/centers/index')
            ->set('centers', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('pages', $result['pages'])
            ->set('filters', $filters)
            ->set('statistics', $this->_centers()->stats())
            ->set('kinds', Model_Erp408_WorkCenter::$kinds)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * GET /shopfloor/centers/edit(/<id>)
     */
    public function action_edit()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id     = $this->request->param('id');
        $center = $id ? $this->_centers()->get($id) : NULL;

        if ($id AND ! $center)
        {
            $this->_flash('error', 'Рабочий центр не найден');
            $this->redirect('/shopfloor/centers');
            return;
        }

        $this->template->content = View::factory('erp408/centers/form')
            ->set('center', $center)
            ->set('kinds', Model_Erp408_WorkCenter::$kinds)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * POST /shopfloor/centers/save
     */
    public function action_save()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $post = $this->request->post();
        $id   = isset($post['id']) ? (int) $post['id'] : 0;

        try
        {
            if ($id)
            {
                $center = $this->_centers()->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Рабочий центр обновлён');
            }
            else
            {
                $center = $this->_centers()->create($post, $this->_current_user_id());
                $this->_flash('success', 'Рабочий центр создан');
            }

            $this->redirect('/shopfloor/centers');
            return;
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: центр не сохранён — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось сохранить рабочий центр. Подробности в журнале.');
        }

        $this->redirect($id ? '/shopfloor/centers/edit/'.$id : '/shopfloor/centers/edit');
    }

    /**
     * POST /shopfloor/centers/toggle/<id> — включить или выключить центр.
     */
    public function action_toggle()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id     = $this->request->param('id');
        $center = $this->_centers()->get($id);

        if ( ! $center)
        {
            $this->_flash('error', 'Рабочий центр не найден');
            $this->redirect('/shopfloor/centers');
            return;
        }

        try
        {
            $updated = $this->_centers()->set_active($id, ! $center['is_active'], $this->_current_user_id());

            $this->_flash('success', $updated['is_active']
                ? 'Центр '.$updated['code'].' включён'
                : 'Центр '.$updated['code'].' выключен: новые операции на него не назначаются');
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/centers');
    }

    /**
     * POST /shopfloor/centers/delete/<id>
     */
    public function action_delete()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = $this->request->param('id');

        try
        {
            $this->_centers()->delete($id);
            $this->_flash('success', 'Рабочий центр удалён');
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/centers');
    }
}
