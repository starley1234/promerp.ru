<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Справочник кодов дефектов (erp405_quality).
 *
 * Экран нужен не «для полноты»: от того, назван ли дефект одним кодом, зависит
 * вся статистика качества. Поэтому справочник правится здесь же, где смотрят
 * статистику, а не в базе.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Defect extends Controller_Erp405_Main
{
    /**
     * Список кодов с частотой использования.
     */
    public function action_index()
    {
        if ( ! $this->can('erp405.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'term'        => $this->_query('term'),
            'category'    => $this->_query('category'),
            'severity'    => $this->_query('severity'),
            'active_only' => ($this->_query('all') === NULL OR $this->_query('all') === ''),
        );

        $rows = array();

        foreach ($this->_codes()->search($filters, 200, 0) as $code)
        {
            $rows[] = array(
                'code'  => $code,
                'usage' => $this->_codes()->usage((int) $code->id),
            );
        }

        $this->template->content = View::factory('erp405/defect/index')
            ->set('rows', $rows)
            ->set('filters', $filters)
            ->set('categories', Model_Erp405_DefectCode::$categories)
            ->set('severities', Model_Erp405_DefectCode::$severities)
            ->set('can_manage', $this->can('erp405.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма кода дефекта.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp405.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id   = (int) $this->request->param('id');
        $code = $id ? $this->_codes()->find($id) : NULL;

        if ($id AND ! $code)
        {
            throw new HTTP_Exception_404('Код дефекта не найден');
        }

        $this->template->content = View::factory('erp405/defect/edit')
            ->set('code', $code)
            ->set('categories', Model_Erp405_DefectCode::$categories)
            ->set('severities', Model_Erp405_DefectCode::$severities)
            ->set('usage', $code ? $this->_codes()->usage((int) $code->id) : 0)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить код дефекта (создание или правка) и вернуться к списку.
     */
    public function action_save()
    {
        if ( ! $this->can('erp405.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/defects');
        }

        $id   = (int) $this->_posted('id');
        $data = array(
            'code'        => $this->_posted('code'),
            'name'        => $this->_posted('name'),
            'category'    => $this->_posted('category'),
            'severity'    => $this->_posted('severity'),
            'description' => $this->_posted('description'),
        );

        try
        {
            if ($id)
            {
                $this->_codes()->update($id, $data, $this->_current_user_id());
                $this->_flash('success', 'Код дефекта обновлён');
            }
            else
            {
                $code = $this->_codes()->create($data, $this->_current_user_id());
                $this->_flash('success', 'Код дефекта '.$code->code.' добавлен');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);

            $this->redirect($id ? '/quality/defects/edit/'.$id : '/quality/defects/edit');
        }

        $this->redirect('/quality/defects');
    }
}
