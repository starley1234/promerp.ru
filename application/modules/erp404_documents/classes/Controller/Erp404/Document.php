<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документы и печать (erp404_documents): список, карточка, выпуск, печать.
 *
 * Экраны повторяют путь сервиса: черновик → выпуск (снимок и версия шаблона
 * зафиксированы) → печать (журнал печати и счётчик) → архив. Правки после
 * выпуска экран не предлагает: бумага задним числом не меняется.
 *
 * Права разведены по шагам:
 *   * смотреть документы, шаблоны и журнал печати — `erp404.view`;
 *   * завести черновик, выпустить и напечатать — `erp404.print`;
 *   * вести шаблоны форм — `erp404.manage`.
 *
 * @package    Erp404
 * @category   Controllers
 */
class Controller_Erp404_Document extends Controller_Erp404_Main
{
    /**
     * Список документов: доска со сводкой и фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp404.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'status'      => $this->_query('status'),
            'ref_type'    => $this->_query('ref_type'),
            'ref_id'      => $this->_query('ref_id'),
            'template_code' => $this->_query('template_code'),
            'term'        => $this->_query('term'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp404/document/index')
            ->set('rows', $this->_documents()->search($filters, $limit, $offset))
            ->set('total', $this->_documents()->count($filters))
            ->set('stats', $this->_documents()->stats())
            ->set('by_type', $this->_documents()->by_ref_type())
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('ref_types', Model_Erp404_Template::ref_types())
            ->set('statuses', Model_Erp404_Document::$statuses)
            ->set('can_print', $this->can('erp404.print'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка документа: снимок, журнал печати, доступные шаги.
     */
    public function action_view()
    {
        if ( ! $this->can('erp404.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $document = $this->_documents()->get($this->request->param('id'));

        if ( ! $document)
        {
            throw new HTTP_Exception_404('Документ не найден');
        }

        $template = $document->template_id ? $this->_templates()->get($document->template_id) : NULL;
        $problems = array();

        if ($document->is_editable())
        {
            // Что мешает выпуску — видно до нажатия, а не после.
            $preview  = $this->_documents()->preview($document->id, $this->_current_user_id());
            $problems = $preview['problems'];
        }

        $this->template->content = View::factory('erp404/document/view')
            ->set('document', $document)
            ->set('template', $template)
            ->set('prints', $this->_documents()->prints($document->id))
            ->set('problems', $problems)
            ->set('can_print', $this->can('erp404.print'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма черновика: новый документ или правка существующего.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp404.print'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $document = NULL;

        if ($id = $this->request->param('id'))
        {
            $document = $this->_documents()->get($id);

            if ( ! $document)
            {
                throw new HTTP_Exception_404('Документ не найден');
            }

            if ( ! $document->is_editable())
            {
                $this->_flash('warning', 'Документ '.$document->number.' уже выпущен: правки запрещены, заведите новый.');
                $this->redirect($this->_document_url($document->id));
            }
        }

        $this->template->content = View::factory('erp404/document/edit')
            ->set('document', $document)
            ->set('templates', $this->_templates()->search(array('active' => TRUE), 100, 0))
            ->set('preselect', $this->_query('template_code'))
            ->set('preselect_ref_type', $this->_query('ref_type'))
            ->set('preselect_ref_id', $this->_query('ref_id'))
            ->set('ref_types', Model_Erp404_Template::ref_types())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить черновик (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp404.print'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id   = $this->_posted('id');
        $data = array(
            'title'       => $this->_posted('title'),
            'ref_type'    => $this->_posted('ref_type'),
            'ref_id'      => $this->_posted('ref_id'),
            'note'        => $this->_posted('note'),
        );

        try
        {
            if ($id)
            {
                $document = $this->_documents()->update($id, $data, $this->_current_user_id());
                $this->_flash('success', 'Черновик '.$document->number.' сохранён.');
            }
            else
            {
                $data['template_id']   = $this->_posted('template_id');
                $data['template_code'] = $this->_posted('template_code');

                $document = $this->_documents()->create($data, $this->_current_user_id());
                $this->_flash('success', 'Черновик '.$document->number.' создан: проверьте данные и выпускайте.');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить документ');
            $this->redirect($id ? '/documents/edit/'.rawurlencode((string) $id) : '/documents/edit');
        }

        $this->redirect($this->_document_url($document->id));
    }

    /**
     * Шаги документа (POST): выпуск, печать, архив, отмена.
     */
    public function action_act()
    {
        if ( ! $this->can('erp404.print'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $act      = (string) $this->request->param('act');
        $id       = $this->request->param('id');
        $user_id  = $this->_current_user_id();
        $document = $this->_documents()->get($id);

        if ( ! $document)
        {
            throw new HTTP_Exception_404('Документ не найден');
        }

        try
        {
            switch ($act)
            {
                case 'issue':
                    $document = $this->_documents()->issue($document->id, $user_id, array(
                        'payload' => $this->_posted('payload'),
                    ));
                    $this->_flash('success', 'Документ '.$document->number.' выпущен: снимок и версия шаблона зафиксированы.');
                break;

                case 'print':
                    $document = $this->_documents()->print_document(
                        $document->id,
                        $user_id,
                        $this->_posted('copies'),
                        $this->_posted('note')
                    );
                    $this->_flash('success', 'Печать '.$document->number.' отмечена в журнале. Форма открыта в новой вкладке.');
                    $this->redirect('/documents/print/'.rawurlencode((string) $document->id));
                break;

                case 'archive':
                    $document = $this->_documents()->archive($document->id, $user_id);
                    $this->_flash('success', 'Документ '.$document->number.' сдан в архив.');
                break;

                case 'cancel':
                    $document = $this->_documents()->cancel($document->id, $this->_posted('reason'), $user_id);
                    $this->_flash('warning', 'Документ '.$document->number.' отменён. Номер остался занятым — это видно в журнале.');
                break;

                default:
                    $this->_flash('error', 'Неизвестный шаг «'.$act.'»: документ не изменён.');
                break;
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось выполнить шаг «'.$act.'»');
        }

        $this->redirect($this->_document_url($document->id));
    }

    /**
     * Печатная форма: снимок документа без макета системы — то, что уходит на бумагу.
     */
    public function action_print_page()
    {
        if ( ! $this->can('erp404.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $document = $this->_documents()->get($this->request->param('id'));

        if ( ! $document)
        {
            throw new HTTP_Exception_404('Документ не найден');
        }

        if ($document->rendered === NULL OR $document->rendered === '')
        {
            $this->_flash('warning', 'У документа '.$document->number.' нет снимка: сначала выпустите его.');
            $this->redirect($this->_document_url($document->id));
        }

        $this->auto_render = FALSE;

        $this->response->headers('Content-Type', 'text/html; charset=utf-8');
        $this->response->body((string) View::factory('erp404/document/print')
            ->set('document', $document)
            ->set('body', (string) $document->rendered));
    }
}
