<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Справочник шаблонов печатных форм (erp404_documents).
 *
 * Шаблон — это рабочий инструмент, а не настройка разработчика: текст заказа
 * поставщику меняется вместе с требованиями заказчика, и меняет его человек в
 * интерфейсе. Поэтому справочник живёт по обычным правилам проекта:
 *
 *  * код шаблона уникален и не меняется — на него ссылаются автопечать и агенты;
 *  * тело шаблона проверяется (`check()`) до сохранения: подстановка с опечаткой
 *    не должна дойти до бумаги;
 *  * шаблон выключается, а не удаляется, если по нему уже что-то печатали —
 *    иначе старые документы потеряют расшифровку;
 *  * при правке тела версия растёт, а выпущенные документы запоминают свою.
 *
 * @package    Erp404
 * @category   Services
 */
class Service_Erp404_Templates
{
    /** @var Service_Erp404_Templates */
    protected static $_instance;

    /** @var string */
    const TABLE = 'erp_404_templates';

    /**
     * @return  Service_Erp404_Templates
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Найти шаблон по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp404_Template|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $template = ORM::factory('Erp404_Template')->where($ref[0], '=', $ref[1])->find();

        return $template->loaded() ? $template : NULL;
    }

    /**
     * Найти шаблон по коду (автопечать и агенты зовут его кодом).
     *
     * @param   string  $code
     * @return  Model_Erp404_Template|null
     */
    public function find_by_code($code)
    {
        $code = trim((string) $code);

        if ($code === '')
        {
            return NULL;
        }

        $template = ORM::factory('Erp404_Template')->where('code', '=', $code)->find();

        return $template->loaded() ? $template : NULL;
    }

    /**
     * Список шаблонов.
     *
     * @param   array   $filters    term, ref_type, kind, active, code
     * @param   int     $limit
     * @param   int     $offset
     * @return  ORM_Result
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = $this->apply_filters(ORM::factory('Erp404_Template'), $filters);

        return $query->order_by('ref_type', 'ASC')
            ->order_by('code', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->find_all();
    }

    /**
     * Сколько шаблонов подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        return (int) $this->apply_filters(ORM::factory('Erp404_Template'), $filters)->count_all();
    }

    /**
     * Применить фильтры к выборке.
     *
     * @param   ORM     $query
     * @param   array   $filters
     * @return  ORM
     */
    public function apply_filters($query, array $filters)
    {
        if ($term = trim((string) Arr::get($filters, 'term', '')))
        {
            $query->where('search_text', 'ILIKE', '%'.$term.'%');
        }

        if (($ref_type = Arr::get($filters, 'ref_type', NULL)) !== NULL AND $ref_type !== '')
        {
            $query->where('ref_type', '=', (string) $ref_type);
        }

        if ($kind = Arr::get($filters, 'kind', NULL))
        {
            $query->where('kind', '=', (string) $kind);
        }

        if (($code = Arr::get($filters, 'code', '')) !== '')
        {
            $query->where('code', '=', (string) $code);
        }

        if (($active = Arr::get($filters, 'active', NULL)) !== NULL AND $active !== '')
        {
            $query->where('is_active', '=', Erp::bool($active) ? 1 : 0);
        }

        return $query;
    }

    /**
     * Активные шаблоны для типа объекта — то, что предлагается при выпуске.
     *
     * @param   string  $ref_type
     * @return  ORM_Result
     */
    public function for_ref_type($ref_type)
    {
        return ORM::factory('Erp404_Template')
            ->where('ref_type', '=', (string) $ref_type)
            ->where('is_active', '=', 1)
            ->order_by('code', 'ASC')
            ->find_all();
    }

    /**
     * Сколько документов выпущено по шаблону.
     *
     * @param   int     $template_id
     * @return  int
     */
    public function usage($template_id)
    {
        return (int) DB::select(array(DB::expr('COUNT(*)'), 'c'))
            ->from('erp_404_documents')
            ->where('template_id', '=', (int) $template_id)
            ->execute()
            ->get('c');
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Завести шаблон.
     *
     * @param   array   $data
     * @param   int     $user_id
     * @return  Model_Erp404_Template
     * @throws  Exception_Erp404_Documents
     */
    public function create(array $data, $user_id = NULL)
    {
        $code = trim((string) Arr::get($data, 'code', ''));
        $name = trim((string) Arr::get($data, 'name', ''));

        if ($code === '' OR $name === '')
        {
            throw new Exception_Erp404_Documents('Шаблону нужны код и название', NULL, 'invalid_argument');
        }

        if ($this->find_by_code($code))
        {
            throw new Exception_Erp404_Documents('Шаблон с кодом :code уже есть', array(':code' => $code), 'duplicate_code');
        }

        $template = ORM::factory('Erp404_Template');
        $template->code       = $code;
        $template->name       = $name;
        $template->ref_type   = (string) Arr::get($data, 'ref_type', '');
        $template->kind       = (string) Arr::get($data, 'kind', 'print');
        $template->body       = (string) Arr::get($data, 'body', '');
        $template->version    = 1;
        $template->is_active  = array_key_exists('is_active', $data) ? (Erp::bool($data['is_active']) ? 1 : 0) : 1;
        $template->note       = Arr::get($data, 'note', NULL);
        $template->created_by = $user_id ? (int) $user_id : NULL;

        $this->_validate($template);
        $template->search_text = $template->search_text();
        $template->save();

        $this->_journal($template, 'template_created', array(), $this->payload($template), $user_id, 'Шаблон '.$template->code);
        Event::fire('erp404.template.saved', $this->payload($template) + array('actor_id' => $user_id, 'created' => TRUE));

        return $template;
    }

    /**
     * Изменить шаблон. Правка тела поднимает версию: по ней выпущенные
     * документы помнят, чем их печатали.
     *
     * @param   int     $id
     * @param   array   $data
     * @param   int     $user_id
     * @return  Model_Erp404_Template
     * @throws  Exception_Erp404_Documents
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $template = $this->get($id);

        if ( ! $template)
        {
            throw new Exception_Erp404_Documents('Шаблон :id не найден', array(':id' => $id), 'not_found');
        }

        $before = $this->payload($template);
        $body_changed = FALSE;

        if (array_key_exists('name', $data))
        {
            $template->name = trim((string) $data['name']);
        }

        if (array_key_exists('ref_type', $data) AND (string) $data['ref_type'] !== (string) $template->ref_type)
        {
            if ($this->usage($template->id) > 0)
            {
                throw new Exception_Erp404_Documents(
                    'Шаблон уже использован: тип объекта менять нельзя — заведите новый шаблон',
                    NULL,
                    'in_use'
                );
            }

            $template->ref_type = (string) $data['ref_type'];
        }

        if (array_key_exists('kind', $data))
        {
            $template->kind = (string) $data['kind'];
        }

        if (array_key_exists('body', $data) AND (string) $data['body'] !== (string) $template->body)
        {
            $template->body    = (string) $data['body'];
            $body_changed      = TRUE;
        }

        if (array_key_exists('is_active', $data))
        {
            $template->is_active = Erp::bool($data['is_active']) ? 1 : 0;
        }

        if (array_key_exists('note', $data))
        {
            $template->note = $data['note'];
        }

        $this->_validate($template);

        if ($body_changed)
        {
            $template->version = (int) $template->version + 1;
        }

        $template->search_text = $template->search_text();
        $template->save();

        $this->_journal($template, 'template_updated', $before, $this->payload($template), $user_id, 'Шаблон '.$template->code);
        Event::fire('erp404.template.saved', $this->payload($template) + array('actor_id' => $user_id, 'created' => FALSE));

        return $template;
    }

    /**
     * Выключить шаблон: новые документы по нему не выпускаются.
     *
     * @param   int     $id
     * @param   int     $user_id
     * @return  Model_Erp404_Template
     * @throws  Exception_Erp404_Documents
     */
    public function deactivate($id, $user_id = NULL)
    {
        return $this->update($id, array('is_active' => FALSE), $user_id);
    }

    /**
     * Проверить шаблон против данных: то, что видит пользователь в редакторе.
     *
     * @param   int|string  $id_or_uuid
     * @param   string      $ref_id     объект для проверки (необязательно)
     * @return  array       ['problems' => [...], 'rendered' => строка|null]
     * @throws  Exception_Erp404_Documents
     */
    public function check($id_or_uuid, $ref_id = NULL, $user_id = NULL)
    {
        $template = $this->get($id_or_uuid);

        if ( ! $template)
        {
            throw new Exception_Erp404_Documents('Шаблон :id не найден', array(':id' => $id_or_uuid), 'not_found');
        }

        $renderer = Service_Erp404_Renderer::instance();

        $data = array();

        if ($template->ref_type !== '' AND $ref_id !== NULL)
        {
            $data['ref'] = Service_Erp404_Sources::instance()->fetch($template->ref_type, $ref_id);
        }

        $problems = $renderer->check($template->body, $data);
        $rendered = NULL;

        if ( ! $problems AND $data)
        {
            $data['document'] = array('number' => 'ПРОВЕРКА', 'title' => $template->name);
            $data['meta']     = Service_Erp404_Sources::instance()->meta($user_id);

            try
            {
                $rendered = $renderer->render($template->body, $data);
            }
            catch (Exception_Erp404_Documents $e)
            {
                $problems[] = $e->getMessage();
            }
        }

        return array('template' => $template, 'problems' => $problems, 'rendered' => $rendered);
    }

    /**
     * Данные шаблона для интерфейса, API и журнала.
     *
     * @param   Model_Erp404_Template   $template
     * @return  array
     */
    public function payload(Model_Erp404_Template $template)
    {
        return array(
            'id'         => (int) $template->id,
            'uuid'       => (string) $template->uuid,
            'code'       => (string) $template->code,
            'name'       => (string) $template->name,
            'ref_type'   => (string) $template->ref_type,
            'kind'       => (string) $template->kind,
            'version'    => (int) $template->version,
            'is_active'  => $template->is_active(),
            'placeholders' => $template->placeholders(),
            'rows'       => $template->row_blocks(),
        );
    }

    /**
     * Проверка шаблона перед сохранением.
     *
     * @param   Model_Erp404_Template   $template
     * @return  void
     * @throws  Exception_Erp404_Documents
     */
    protected function _validate(Model_Erp404_Template $template)
    {
        if (trim((string) $template->name) === '')
        {
            throw new Exception_Erp404_Documents('У шаблона должно быть название', NULL, 'invalid_argument');
        }

        if (trim((string) $template->body) === '')
        {
            throw new Exception_Erp404_Documents('Тело шаблона пустое: печатать нечего', NULL, 'empty_template');
        }

        if ( ! isset(Model_Erp404_Template::$kinds[$template->kind]))
        {
            throw new Exception_Erp404_Documents('Неизвестный вид формы: :kind', array(':kind' => $template->kind), 'invalid_argument');
        }

        if ( ! Service_Erp404_Sources::instance()->supports($template->ref_type))
        {
            throw new Exception_Erp404_Documents(
                'Тип объекта :type не поддержан: неизвестно, откуда брать данные',
                array(':type' => $template->ref_type),
                'unknown_ref'
            );
        }

        $problems = Service_Erp404_Renderer::instance()->check($template->body);

        if ($problems)
        {
            throw new Exception_Erp404_Documents('Шаблон не сохранён: :problem', array(':problem' => $problems[0]), 'unknown_placeholder');
        }
    }

    /**
     * Запись в журнал действий (erp119) — мягко: журнал может быть выключен.
     *
     * @param   Model_Erp404_Template   $template
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   int|null                $user_id
     * @param   string|null             $message
     * @return  void
     */
    protected function _journal(Model_Erp404_Template $template, $action, array $before, array $after, $user_id = NULL, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('document_template', (string) $template->uuid, $action, $before, $after, array(
                'module'       => 'erp404',
                'entity_label' => (string) $template->code,
                'message'      => $message,
                'actor_id'     => $user_id,
                'force'        => TRUE,
            ));
        }
        catch (Exception $e)
        {
            // журнал действий не должен ломать печать
        }
    }
}
