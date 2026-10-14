<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Шаблоны печатных форм (erp404_documents): список, редактор, проверка.
 *
 * Шаблон — рабочий инструмент, а не настройка разработчика, поэтому редактор
 * живёт в интерфейсе и сам объясняет ошибки: подстановка с опечаткой ловится при
 * сохранении (`check`), а проверка на реальном объекте показывает, что именно
 * подставится до выпуска. Тело шаблона — доверенный HTML (его пишет человек с
 * правом `erp404.manage`), значения из данных при подстановке экранируются.
 *
 * @package    Erp404
 * @category   Controllers
 */
class Controller_Erp404_Template extends Controller_Erp404_Main
{
    /**
     * Список шаблонов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp404.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'ref_type' => $this->_query('ref_type'),
            'kind'     => $this->_query('kind'),
            'term'     => $this->_query('term'),
            'active'   => $this->_query('active'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $rows = array();

        foreach ($this->_templates()->search($filters, $limit, $offset) as $template)
        {
            $rows[] = array('template' => $template, 'usage' => $this->_templates()->usage($template->id));
        }

        $this->template->content = View::factory('erp404/template/index')
            ->set('rows', $rows)
            ->set('total', $this->_templates()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('ref_types', Model_Erp404_Template::ref_types())
            ->set('kinds', Model_Erp404_Template::$kinds)
            ->set('can_manage', $this->can('erp404.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Редактор шаблона: новый или правка существующего.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp404.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $template = NULL;

        if ($id = $this->request->param('id'))
        {
            $template = $this->_templates()->get($id);

            if ( ! $template)
            {
                throw new HTTP_Exception_404('Шаблон не найден');
            }
        }

        $this->template->content = View::factory('erp404/template/edit')
            ->set('template', $template)
            ->set('usage', $template ? $this->_templates()->usage($template->id) : 0)
            ->set('ref_types', Model_Erp404_Template::ref_types())
            ->set('kinds', Model_Erp404_Template::$kinds)
            ->set('sources', Service_Erp404_Sources::instance()->types())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить шаблон (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp404.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id   = $this->_posted('id');
        $data = array(
            'code'      => $this->_posted('code'),
            'name'      => $this->_posted('name'),
            'ref_type'  => $this->_posted('ref_type'),
            'kind'      => $this->_posted('kind'),
            'body'      => $this->_posted('body'),
            'note'      => $this->_posted('note'),
            'is_active' => $this->_posted('is_active'),
        );

        try
        {
            if ($id)
            {
                $template = $this->_templates()->update($id, $data, $this->_current_user_id());
                $this->_flash('success', 'Шаблон '.$template->code.' сохранён, версия '.$template->version.'.');
            }
            else
            {
                $template = $this->_templates()->create($data, $this->_current_user_id());
                $this->_flash('success', 'Шаблон '.$template->code.' создан.');
            }

            $this->redirect('/documents/templates/edit/'.rawurlencode((string) $template->id));
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Шаблон не сохранён');
            $this->redirect($id ? '/documents/templates/edit/'.rawurlencode((string) $id) : '/documents/templates/edit');
        }
    }

    /**
     * Проверка шаблона: что подставится и чего не хватает.
     */
    public function action_check()
    {
        if ( ! $this->can('erp404.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $template = $this->_templates()->get($this->request->param('id'));

        if ( ! $template)
        {
            throw new HTTP_Exception_404('Шаблон не найден');
        }

        $result = array('problems' => array(), 'rendered' => NULL, 'ref_id' => $this->_query('ref_id'));

        try
        {
            $result = $this->_templates()->check($template->id, $this->_query('ref_id'), $this->_current_user_id()) + $result;
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Проверка шаблона не прошла');
        }

        $this->template->content = View::factory('erp404/template/check')
            ->set('template', $template)
            ->set('problems', $result['problems'])
            ->set('rendered', $result['rendered'])
            ->set('ref_id', $result['ref_id'])
            ->set('ref_types', Model_Erp404_Template::ref_types())
            ->set('can_manage', $this->can('erp404.manage'))
            ->set('messages', $this->_flash_messages());
    }
}
