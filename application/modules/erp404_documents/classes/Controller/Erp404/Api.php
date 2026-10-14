<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * JSON API документов и печати (erp404_documents): `/documents/api/<action>(/<id>)`.
 *
 * Повторяет экраны один в один, чтобы агент и внешний воркер видели то же, что
 * человек: сводку, документы, снимок выпущенного документа, журнал печати,
 * шаблоны форм, предварительный просмотр.
 *
 * Права разведены по шагам процесса:
 *   * чтение (сводка, документы, шаблоны, журнал печати, предпросмотр) — `erp404.view`;
 *   * завести черновик, выпустить, напечатать, сдать в архив, отменить — `erp404.print`;
 *   * вести шаблоны форм — `erp404.manage`.
 *
 * Метод запроса — часть контракта (см. `application/config/api.php`): изменяющие
 * действия вызываются POST-ом, GET на них получает 405 `method_not_allowed` от
 * ядра, ещё до тела действия.
 *
 * @package    Erp404
 * @category   Controllers
 */
class Controller_Erp404_Api extends Controller_Base
{
    /** @var Service_Erp404_Documents|null */
    protected $_documents;

    /** @var Service_Erp404_Templates|null */
    protected $_templates;

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Сводка: сколько документов ждёт выпуска, сколько напечатано.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'from'     => $this->_query('from'),
            'to'       => $this->_query('to'),
            'ref_type' => $this->_query('ref_type'),
        );

        $this->_json_ok(array(
            'summary'   => $this->_documents()->stats($filters),
            'by_ref_type' => $this->_documents()->by_ref_type($filters),
        ));
    }

    /**
     * Список документов с фильтрами.
     */
    public function action_documents()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'status'        => $this->_query('status'),
            'ref_type'      => $this->_query('ref_type'),
            'ref_id'        => $this->_query('ref_id'),
            'template_code' => $this->_query('template_code'),
            'term'          => $this->_query('term'),
            'from'          => $this->_query('from'),
            'to'            => $this->_query('to'),
        );

        $rows = array();

        foreach ($this->_documents()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $document)
        {
            $rows[] = $this->_document_row($document);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_documents()->count($filters)));
    }

    /**
     * Документ целиком: шапка, снимок выпуска, журнал печати.
     */
    public function action_document()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $document = $this->_documents()->get($this->_query('id', $this->request->param('id')));

        if ( ! $document)
        {
            $this->_json_fail('Документ не найден', 'not_found', 404);
            return;
        }

        $prints = array();

        foreach ($this->_documents()->prints($document->id, 100) as $print)
        {
            $prints[] = $this->_print_row($print);
        }

        $data = array(
            'document' => $this->_document_row($document),
            'prints'   => $prints,
        );

        if ($this->_query('rendered') !== NULL AND $this->_query('rendered') !== '' AND $this->_query('rendered') !== '0')
        {
            $data['rendered'] = $document->rendered === NULL ? NULL : (string) $document->rendered;
            $data['payload']  = $document->payload_array();
        }

        $this->_json_ok($data);
    }

    /**
     * Журнал печати: кто и сколько раз печатал.
     */
    public function action_prints()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $document = $this->_documents()->get($this->_query('id', $this->request->param('id')));

        if ( ! $document)
        {
            $this->_json_fail('Документ не найден', 'not_found', 404);
            return;
        }

        $rows = array();

        foreach ($this->_documents()->prints($document->id, (int) $this->_query('limit', 50)) as $print)
        {
            $rows[] = $this->_print_row($print);
        }

        $this->_json_ok(array('document' => $this->_document_row($document), 'rows' => $rows));
    }

    /**
     * Шаблоны печатных форм.
     */
    public function action_templates()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'ref_type' => $this->_query('ref_type'),
            'kind'     => $this->_query('kind'),
            'term'     => $this->_query('term'),
            'active'   => $this->_query('active'),
            'code'     => $this->_query('code'),
        );

        $rows = array();

        foreach ($this->_templates()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $template)
        {
            $rows[] = $this->_template_row($template);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_templates()->count($filters)));
    }

    /**
     * Шаблон целиком: тело формы и список подстановок.
     */
    public function action_template()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $template = $this->_templates()->get($this->_query('id', $this->request->param('id')));

        if ( ! $template)
        {
            $this->_json_fail('Шаблон не найден', 'not_found', 404);
            return;
        }

        $this->_json_ok(array('template' => $this->_template_row($template, TRUE)));
    }

    /**
     * Предпросмотр: что подставится в документе (без сохранения).
     */
    public function action_preview()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $document = $this->_documents()->get($this->_query('id', $this->request->param('id')));

        if ( ! $document)
        {
            $this->_json_fail('Документ не найден', 'not_found', 404);
            return;
        }

        try
        {
            $preview = $this->_documents()->preview($document->id, $this->_current_user_id());
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'document' => $this->_document_row($document),
            'problems' => $preview['problems'],
            'rendered' => $preview['rendered'],
            'ready'    => ! $preview['problems'],
        ));
    }

    /**
     * Проверка шаблона (в том числе на данных реального объекта).
     */
    public function action_template_check()
    {
        if ( ! $this->can('erp404.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $template = $this->_templates()->get($this->_query('id', $this->request->param('id')));

        if ( ! $template)
        {
            $this->_json_fail('Шаблон не найден', 'not_found', 404);
            return;
        }

        try
        {
            $result = $this->_templates()->check($template->id, $this->_query('ref_id'), $this->_current_user_id());
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'template' => $this->_template_row($template),
            'problems' => $result['problems'],
            'rendered' => $result['rendered'],
            'ready'    => ! $result['problems'],
        ));
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Завести черновик.
     */
    public function action_create()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $document = $this->_documents()->create(array(
                'template_id'   => Arr::get($post, 'template_id'),
                'template_code' => Arr::get($post, 'template_code'),
                'title'         => Arr::get($post, 'title'),
                'ref_type'      => Arr::get($post, 'ref_type'),
                'ref_id'        => Arr::get($post, 'ref_id'),
                'note'          => Arr::get($post, 'note'),
            ), $this->_current_user_id());
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('document' => $this->_document_row($document)), 201);
    }

    /**
     * Поправить черновик.
     */
    public function action_update()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        if ( ! Arr::get($post, 'id'))
        {
            $this->_json_fail('Не указан документ (id)', 'invalid_argument', 400);
            return;
        }

        try
        {
            $document = $this->_documents()->update(Arr::get($post, 'id'), array(
                'title'    => Arr::get($post, 'title'),
                'ref_type' => Arr::get($post, 'ref_type'),
                'ref_id'   => Arr::get($post, 'ref_id'),
                'note'     => Arr::get($post, 'note'),
            ), $this->_current_user_id());
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('document' => $this->_document_row($document)));
    }

    /**
     * Выпустить документ: собрать данные, раскрыть подстановки, зафиксировать снимок.
     */
    public function action_issue()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $document = $this->_documents()->issue(
                Arr::get($post, 'id', $this->request->param('id')),
                $this->_current_user_id(),
                array('payload' => Arr::get($post, 'payload'))
            );
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'document' => $this->_document_row($document),
            'rendered' => (string) $document->rendered,
        ));
    }

    /**
     * Отметить печать: строка в журнале печати и счётчик копий.
     */
    public function action_print()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $document = $this->_documents()->print_document(
                Arr::get($post, 'id', $this->request->param('id')),
                $this->_current_user_id(),
                Arr::get($post, 'copies', 1),
                Arr::get($post, 'note')
            );
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array(
            'document' => $this->_document_row($document),
            'printed'  => TRUE,
        ));
    }

    /**
     * Сдать документ в архив.
     */
    public function action_archive()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $document = $this->_documents()->archive(
                Arr::get($post, 'id', $this->request->param('id')),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('document' => $this->_document_row($document)));
    }

    /**
     * Отменить документ: номер остаётся занятым, причина обязательна.
     */
    public function action_cancel()
    {
        if ( ! $this->can('erp404.print'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $document = $this->_documents()->cancel(
                Arr::get($post, 'id', $this->request->param('id')),
                Arr::get($post, 'reason'),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('document' => $this->_document_row($document)));
    }

    /**
     * Сохранить шаблон формы (завести или поправить).
     */
    public function action_template_save()
    {
        if ( ! $this->can('erp404.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();
        $data = array(
            'code'      => Arr::get($post, 'code'),
            'name'      => Arr::get($post, 'name'),
            'ref_type'  => Arr::get($post, 'ref_type'),
            'kind'      => Arr::get($post, 'kind'),
            'body'      => Arr::get($post, 'body'),
            'note'      => Arr::get($post, 'note'),
        );

        if (array_key_exists('is_active', (array) $post))
        {
            $data['is_active'] = Arr::get($post, 'is_active');
        }

        try
        {
            if ($id = Arr::get($post, 'id'))
            {
                $template = $this->_templates()->update($id, $data, $this->_current_user_id());
                $code     = 200;
            }
            else
            {
                $template = $this->_templates()->create($data, $this->_current_user_id());
                $code     = 201;
            }
        }
        catch (Exception_Erp404_Documents $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('template' => $this->_template_row($template)), $code);
    }

    // =========================================================================
    //  Виды строк
    // =========================================================================

    /**
     * @param   Model_Erp404_Document   $document
     * @return  array
     */
    public function _document_row($document)
    {
        return array(
            'id'               => (int) $document->id,
            'uuid'             => (string) $document->uuid,
            'number'           => (string) $document->number,
            'title'            => (string) $document->title,
            'status'           => (string) $document->status,
            'status_label'     => $document->status_label(),
            'ref_type'         => (string) $document->ref_type,
            'ref_type_label'   => Model_Erp404_Template::ref_type_label($document->ref_type),
            'ref_id'           => $document->ref_id === NULL ? NULL : (string) $document->ref_id,
            'template_id'      => $document->template_id === NULL ? NULL : (int) $document->template_id,
            'template_code'    => $document->template_code === NULL ? NULL : (string) $document->template_code,
            'template_version' => $document->template_version === NULL ? NULL : (int) $document->template_version,
            'print_count'      => (int) $document->print_count,
            'has_snapshot'     => ($document->rendered !== NULL AND $document->rendered !== ''),
            'issued_at'        => $document->issued_at === NULL ? NULL : (string) $document->issued_at,
            'printed_at'       => $document->printed_at === NULL ? NULL : (string) $document->printed_at,
            'archived_at'      => $document->archived_at === NULL ? NULL : (string) $document->archived_at,
            'cancelled_at'     => $document->cancelled_at === NULL ? NULL : (string) $document->cancelled_at,
            'editable'         => $document->is_editable(),
            'printable'        => $document->is_printable(),
            'note'             => $document->note ? (string) $document->note : NULL,
            'created_at'       => $document->created_at ? (string) $document->created_at : NULL,
        );
    }

    /**
     * @param   Model_Erp404_Template   $template
     * @param   bool                    $with_body
     * @return  array
     */
    public function _template_row($template, $with_body = FALSE)
    {
        $row = array(
            'id'           => (int) $template->id,
            'uuid'         => (string) $template->uuid,
            'code'         => (string) $template->code,
            'name'         => (string) $template->name,
            'ref_type'     => (string) $template->ref_type,
            'ref_type_label' => Model_Erp404_Template::ref_type_label($template->ref_type),
            'kind'         => (string) $template->kind,
            'kind_label'   => $template->kind_label(),
            'version'      => (int) $template->version,
            'active'       => $template->is_active(),
            'placeholders' => $template->placeholders(),
            'rows'         => $template->row_blocks(),
            'used'         => $this->_templates()->usage($template->id),
            'updated_at'   => $template->updated_at ? (string) $template->updated_at : NULL,
        );

        if ($with_body)
        {
            $row['body'] = (string) $template->body;
            $row['note'] = $template->note ? (string) $template->note : NULL;
        }

        return $row;
    }

    /**
     * @param   Model_Erp404_Print  $print
     * @return  array
     */
    public function _print_row($print)
    {
        return array(
            'id'          => (int) $print->id,
            'document_id' => (int) $print->document_id,
            'printed_by'  => $print->printed_by === NULL ? NULL : (int) $print->printed_by,
            'printed_by_name' => $print->printed_by_name(),
            'copies'      => $print->copies(),
            'note'        => $print->note ? (string) $print->note : NULL,
            'printed_at'  => (string) $print->printed_at,
        );
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @return  Service_Erp404_Documents
     */
    protected function _documents()
    {
        if ($this->_documents === NULL)
        {
            $this->_documents = Service_Erp404_Documents::instance();
        }

        return $this->_documents;
    }

    /**
     * @return  Service_Erp404_Templates
     */
    protected function _templates()
    {
        if ($this->_templates === NULL)
        {
            $this->_templates = Service_Erp404_Templates::instance();
        }

        return $this->_templates;
    }

    /**
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }
}
