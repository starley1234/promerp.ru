<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документы: выпуск, печать, архив (erp404_documents).
 *
 * Модуль отвечает на вопрос «что мы отдали на бумагу и чем это подтверждается».
 * Путь документа короткий и намеренно необратимый на каждом шаге:
 *
 *   create()  черновик: шапка и ссылка на объект, правится свободно;
 *   issue()   выпуск: данные собраны у владельца, подстановки раскрыты, снимок
 *             HTML и версия шаблона зафиксированы — с этого момента текст
 *             документа не зависит от правок шаблона;
 *   print()   печать: строка в журнале (`erp_404_prints`) и счётчик копий;
 *   archive() в архив: документ больше не рабочий, но читаемый;
 *   cancel()  отказ: черновик или выпущенный документ с объяснением причины.
 *
 * Номер `ДК-ГГГГ-НННННН` выдаётся при создании и не переиспользуется: отменённый
 * документ остаётся в журнале со своим номером. Печатать можно только выпущенное
 * (`not_issued`) — иначе на бумагу уходил бы черновик с недозаполненными полями.
 *
 * Всё, что модуль обещает наружу, происходит в одном месте: события
 * `erp404.document.*`, запись в журнал erp119 и журнал печати — из этих трёх
 * источников складывается ответ «кто, когда и что напечатал».
 *
 * @package    Erp404
 * @category   Services
 */
class Service_Erp404_Documents
{
    /** @var Service_Erp404_Documents */
    protected static $_instance;

    /** @var string */
    const TABLE = 'erp_404_documents';

    /**
     * @return  Service_Erp404_Documents
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
     * Найти документ по идентификатору или uuid.
     *
     * @param   int|string  $id_or_uuid
     * @return  Model_Erp404_Document|null
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

        $document = ORM::factory('Erp404_Document')->where($ref[0], '=', $ref[1])->find();

        return $document->loaded() ? $document : NULL;
    }

    /**
     * Найти документ по номеру.
     *
     * @param   string  $number
     * @return  Model_Erp404_Document|null
     */
    public function find_by_number($number)
    {
        $number = trim((string) $number);

        if ($number === '')
        {
            return NULL;
        }

        $document = ORM::factory('Erp404_Document')->where('number', '=', $number)->find();

        return $document->loaded() ? $document : NULL;
    }

    /**
     * Список документов.
     *
     * @param   array   $filters    term, status, ref_type, ref_id, template_id, template_code, from, to
     * @param   int     $limit
     * @param   int     $offset
     * @return  ORM_Result
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = $this->apply_filters(ORM::factory('Erp404_Document'), $filters);

        return $query->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all();
    }

    /**
     * Сколько документов подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        return (int) $this->apply_filters(ORM::factory('Erp404_Document'), $filters)->count_all();
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

        if ($status = Arr::get($filters, 'status', NULL))
        {
            $query->where('status', '=', (string) $status);
        }

        if (($ref_type = Arr::get($filters, 'ref_type', NULL)) !== NULL AND $ref_type !== '')
        {
            $query->where('ref_type', '=', (string) $ref_type);
        }

        if (($ref_id = Arr::get($filters, 'ref_id', NULL)) !== NULL AND $ref_id !== '')
        {
            $query->where('ref_id', '=', (string) $ref_id);
        }

        if ($template_id = Arr::get($filters, 'template_id', NULL))
        {
            $query->where('template_id', '=', (int) $template_id);
        }

        if (($template_code = Arr::get($filters, 'template_code', NULL)) !== NULL AND $template_code !== '')
        {
            $query->where('template_code', '=', (string) $template_code);
        }

        if ($from = Arr::get($filters, 'from', NULL))
        {
            $query->where('created_at', '>=', Erp::day($from).' 00:00:00');
        }

        if ($to = Arr::get($filters, 'to', NULL))
        {
            $query->where('created_at', '<=', Erp::day($to).' 23:59:59');
        }

        return $query;
    }

    /**
     * Документы, выпущенные по объекту: «что мы печатали по этому заказу».
     *
     * @param   string  $ref_type
     * @param   string  $ref_id
     * @return  ORM_Result
     */
    public function for_ref($ref_type, $ref_id)
    {
        return ORM::factory('Erp404_Document')
            ->where('ref_type', '=', (string) $ref_type)
            ->where('ref_id', '=', (string) $ref_id)
            ->order_by('created_at', 'DESC')
            ->find_all();
    }

    /**
     * Журнал печати документа.
     *
     * @param   int     $document_id
     * @param   int     $limit
     * @return  ORM_Result
     */
    public function prints($document_id, $limit = 50)
    {
        return ORM::factory('Erp404_Print')
            ->where('document_id', '=', (int) $document_id)
            ->order_by('printed_at', 'DESC')
            ->limit($limit)
            ->find_all();
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Завести черновик.
     *
     * @param   array   $data       title, template_id|template_code, ref_type, ref_id, note
     * @param   int     $user_id
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function create(array $data, $user_id = NULL)
    {
        $template = NULL;

        if ($template_id = Arr::get($data, 'template_id', NULL))
        {
            $template = Service_Erp404_Templates::instance()->get($template_id);
        }
        elseif ($code = trim((string) Arr::get($data, 'template_code', '')))
        {
            $template = Service_Erp404_Templates::instance()->find_by_code($code);
        }

        if ( ! $template)
        {
            throw new Exception_Erp404_Documents(
                'Не найден шаблон: укажите template_id или template_code',
                NULL,
                'not_found'
            );
        }

        if ( ! $template->is_active())
        {
            throw new Exception_Erp404_Documents(
                'Шаблон :code выключен: новые документы по нему не выпускаются',
                array(':code' => $template->code),
                'invalid_transition'
            );
        }

        $title = trim((string) Arr::get($data, 'title', ''));

        if ($title === '')
        {
            $title = $template->name;
        }

        $ref_type = Arr::get($data, 'ref_type', $template->ref_type);
        $ref_id   = Arr::get($data, 'ref_id', NULL);

        if ( ! Service_Erp404_Sources::instance()->supports($ref_type))
        {
            throw new Exception_Erp404_Documents(
                'Тип объекта :type не поддержан: неизвестно, откуда брать данные',
                array(':type' => $ref_type),
                'unknown_ref'
            );
        }

        $document = ORM::factory('Erp404_Document');
        $document->number           = $this->number();
        $document->template_id      = (int) $template->id;
        $document->template_code    = (string) $template->code;
        $document->template_version = NULL;
        $document->ref_type         = (string) $ref_type;
        $document->ref_id           = ($ref_id === NULL OR $ref_id === '') ? NULL : (string) $ref_id;
        $document->title            = $title;
        $document->status           = 'draft';
        $document->payload          = json_encode(array(), JSON_UNESCAPED_UNICODE);
        $document->created_by       = $user_id ? (int) $user_id : NULL;
        $document->note             = Arr::get($data, 'note', NULL);
        $document->search_text      = $document->search_text();
        $document->save();

        $this->_journal($document, 'document_created', array(), $this->payload($document), $user_id, 'Черновик '.$document->number);
        Event::fire('erp404.document.created', $this->payload($document) + array('actor_id' => $user_id));

        return $document;
    }

    /**
     * Править черновик. Выпущенный документ не переписывается: печать — это
     * обещание, и менять текст после неё нельзя.
     *
     * @param   int     $id
     * @param   array   $data
     * @param   int     $user_id
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $document = $this->get($id);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id), 'not_found');
        }

        if ( ! $document->is_editable())
        {
            throw new Exception_Erp404_Documents(
                'Документ :number в состоянии «:status»: правки запрещены, откажитесь от него или выпустите новый',
                array(':number' => $document->number, ':status' => $document->status_label()),
                'invalid_transition'
            );
        }

        $before = $this->payload($document);

        if (array_key_exists('title', $data) AND trim((string) $data['title']) !== '')
        {
            $document->title = trim((string) $data['title']);
        }

        if (array_key_exists('ref_type', $data))
        {
            if ( ! Service_Erp404_Sources::instance()->supports($data['ref_type']))
            {
                throw new Exception_Erp404_Documents(
                    'Тип объекта :type не поддержан',
                    array(':type' => $data['ref_type']),
                    'unknown_ref'
                );
            }

            $document->ref_type = (string) $data['ref_type'];
        }

        if (array_key_exists('ref_id', $data))
        {
            $document->ref_id = ($data['ref_id'] === NULL OR $data['ref_id'] === '') ? NULL : (string) $data['ref_id'];
        }

        if (array_key_exists('note', $data))
        {
            $document->note = $data['note'];
        }

        $document->search_text = $document->search_text();
        $document->save();

        $this->_journal($document, 'document_updated', $before, $this->payload($document), $user_id, 'Черновик '.$document->number);
        Event::fire('erp404.document.updated', $this->payload($document) + array('actor_id' => $user_id));

        return $document;
    }

    /**
     * Выпустить документ: собрать данные, раскрыть подстановки, запомнить снимок.
     *
     * Повторный выпуск выпущенного документа не делается (`invalid_transition`):
     * иначе снимок можно было бы незаметно подменить. Нужен другой текст —
     * отказ и новый документ.
     *
     * @param   int     $id
     * @param   int     $user_id
     * @param   array   $data       payload (данные для универсального шаблона)
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function issue($id, $user_id = NULL, array $data = array())
    {
        $document = $this->get($id);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id), 'not_found');
        }

        if ($document->status !== 'draft')
        {
            throw new Exception_Erp404_Documents(
                'Документ :number уже :status: перевыпуск запрещён',
                array(':number' => $document->number, ':status' => $document->status_label()),
                'invalid_transition'
            );
        }

        $template = $document->template_id ? Service_Erp404_Templates::instance()->get($document->template_id) : NULL;

        if ( ! $template)
        {
            throw new Exception_Erp404_Documents(
                'У документа :number нет шаблона: печатать нечем',
                array(':number' => $document->number),
                'not_found'
            );
        }

        $payload = array();

        if ($document->ref_type !== '')
        {
            $payload['ref'] = Service_Erp404_Sources::instance()->fetch($document->ref_type, $document->ref_id);
        }
        else
        {
            // Универсальный шаблон: данные передаёт вызывающий (форма, агент).
            $custom = Arr::get($data, 'payload', NULL);

            if (is_array($custom))
            {
                $payload = $custom;
            }
            elseif (is_string($custom) AND trim($custom) !== '')
            {
                $decoded = json_decode($custom, TRUE);

                if ( ! is_array($decoded))
                {
                    throw new Exception_Erp404_Documents(
                        'Данные документа не разобраны как JSON: :error',
                        array(':error' => json_last_error_msg()),
                        'invalid_argument'
                    );
                }

                $payload = $decoded;
            }
        }

        $payload['document'] = array(
            'number' => (string) $document->number,
            'title'  => (string) $document->title,
        );
        $payload['meta'] = Service_Erp404_Sources::instance()->meta($user_id);

        $rendered = Service_Erp404_Renderer::instance()->render($template->body, $payload);

        $before = $this->payload($document);

        $document->template_version = (int) $template->version;
        $document->payload          = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $document->rendered         = $rendered;
        $document->status           = 'issued';
        $document->issued_at        = date('Y-m-d H:i:s');
        $document->save();

        $this->_journal($document, 'document_issued', $before, $this->payload($document), $user_id, 'Выпущен '.$document->number);
        Event::fire('erp404.document.issued', $this->payload($document) + array(
            'actor_id'         => $user_id,
            'template_code'    => (string) $template->code,
            'template_version' => (int) $template->version,
        ));

        return $document;
    }

    /**
     * Напечатать документ: запись в журнал печати и счётчик копий.
     *
     * @param   int     $id
     * @param   int     $user_id
     * @param   int     $copies
     * @param   string  $note
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function print_document($id, $user_id = NULL, $copies = 1, $note = NULL)
    {
        $document = $this->get($id);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id), 'not_found');
        }

        if ( ! $document->is_printable())
        {
            throw new Exception_Erp404_Documents(
                'Документ :number в состоянии «:status»: печатать можно только выпущенное',
                array(':number' => $document->number, ':status' => $document->status_label()),
                'not_issued'
            );
        }

        $copies = max(1, (int) $copies);

        $print = ORM::factory('Erp404_Print');
        $print->document_id = (int) $document->id;
        $print->printed_by  = $user_id ? (int) $user_id : NULL;
        $print->copies      = $copies;
        $print->note        = $note;
        $print->save();

        $before = $this->payload($document);

        $document->print_count  = (int) $document->print_count + 1;
        $document->printed_at   = date('Y-m-d H:i:s');
        $document->status       = 'printed';
        $document->save();

        $this->_journal($document, 'document_printed', $before, $this->payload($document), $user_id, 'Печать '.$document->number.' × '.$copies);
        Event::fire('erp404.document.printed', $this->payload($document) + array(
            'actor_id' => $user_id,
            'copies'   => $copies,
        ));

        return $document;
    }

    /**
     * Сдать документ в архив.
     *
     * @param   int     $id
     * @param   int     $user_id
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function archive($id, $user_id = NULL)
    {
        $document = $this->get($id);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id), 'not_found');
        }

        if ( ! $document->is_archivable())
        {
            throw new Exception_Erp404_Documents(
                'Документ :number в состоянии «:status»: в архив уходит только выпущенное',
                array(':number' => $document->number, ':status' => $document->status_label()),
                'invalid_transition'
            );
        }

        $before = $this->payload($document);

        $document->status      = 'archived';
        $document->archived_at = date('Y-m-d H:i:s');
        $document->save();

        $this->_journal($document, 'document_archived', $before, $this->payload($document), $user_id, 'В архив '.$document->number);
        Event::fire('erp404.document.archived', $this->payload($document) + array('actor_id' => $user_id));

        return $document;
    }

    /**
     * Отказаться от документа: номер остаётся занятым, документ — в журнале.
     *
     * @param   int     $id
     * @param   string  $reason
     * @param   int     $user_id
     * @return  Model_Erp404_Document
     * @throws  Exception_Erp404_Documents
     */
    public function cancel($id, $reason = NULL, $user_id = NULL)
    {
        $document = $this->get($id);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id), 'not_found');
        }

        if ( ! $document->is_cancellable())
        {
            throw new Exception_Erp404_Documents(
                'Документ :number в состоянии «:status»: отменить нельзя',
                array(':number' => $document->number, ':status' => $document->status_label()),
                'invalid_transition'
            );
        }

        if (trim((string) $reason) === '')
        {
            throw new Exception_Erp404_Documents('Отмена без причины не принимается: объясните, почему документ не нужен', NULL, 'invalid_argument');
        }

        $before = $this->payload($document);

        $document->status       = 'cancelled';
        $document->cancelled_at = date('Y-m-d H:i:s');
        $document->note         = trim((string) $document->note."\n".'Отмена: '.$reason);
        $document->save();

        $this->_journal($document, 'document_cancelled', $before, $this->payload($document), $user_id, 'Отменён '.$document->number.': '.$reason);
        Event::fire('erp404.document.cancelled', $this->payload($document) + array(
            'actor_id' => $user_id,
            'reason'   => (string) $reason,
        ));

        return $document;
    }

    /**
     * Проверить документ перед выпуском, ничего не сохраняя: какие подстановки
     * найдутся, а какие — нет. Пользуются и человек (кнопка «проверить»), и
     * агент перед вызовом выпуска.
     *
     * @param   int|string  $id_or_uuid
     * @param   int         $user_id
     * @return  array       ['problems' => [...], 'rendered' => string|null]
     * @throws  Exception_Erp404_Documents
     */
    public function preview($id_or_uuid, $user_id = NULL)
    {
        $document = $this->get($id_or_uuid);

        if ( ! $document)
        {
            throw new Exception_Erp404_Documents('Документ :id не найден', array(':id' => $id_or_uuid), 'not_found');
        }

        $template = $document->template_id ? Service_Erp404_Templates::instance()->get($document->template_id) : NULL;

        if ( ! $template)
        {
            throw new Exception_Erp404_Documents(
                'У документа :number нет шаблона',
                array(':number' => $document->number),
                'not_found'
            );
        }

        $data     = array();
        $problems = array();

        if ($document->ref_type !== '')
        {
            try
            {
                $data['ref'] = Service_Erp404_Sources::instance()->fetch($document->ref_type, $document->ref_id);
            }
            catch (Exception_Erp404_Documents $e)
            {
                $problems[] = $e->getMessage();
            }
        }

        $data['document'] = array('number' => (string) $document->number, 'title' => (string) $document->title);
        $data['meta']     = Service_Erp404_Sources::instance()->meta($user_id);

        $problems = array_merge($problems, Service_Erp404_Renderer::instance()->check($template->body, $data));

        $rendered = NULL;

        if ( ! $problems)
        {
            try
            {
                $rendered = Service_Erp404_Renderer::instance()->render($template->body, $data);
            }
            catch (Exception_Erp404_Documents $e)
            {
                $problems[] = $e->getMessage();
            }
        }

        return array('document' => $document, 'template' => $template, 'problems' => $problems, 'rendered' => $rendered);
    }

    // =========================================================================
    //  Данные
    // =========================================================================

    /**
     * Следующий номер документа: `ДК-ГГГГ-НННННН`.
     *
     * @return  string
     */
    public function number()
    {
        $year = date('Y');

        $next = DB::select(array(DB::expr("nextval('erp_404_document_number_seq')"), 'n'))->execute()->get('n');

        return sprintf('ДК-%s-%06d', $year, (int) $next);
    }

    /**
     * Данные документа для интерфейса, API, MCP и журнала.
     *
     * @param   Model_Erp404_Document   $document
     * @return  array
     */
    public function payload(Model_Erp404_Document $document)
    {
        return array(
            'id'               => (int) $document->id,
            'uuid'             => (string) $document->uuid,
            'number'           => (string) $document->number,
            'title'            => (string) $document->title,
            'status'           => (string) $document->status,
            'status_label'     => $document->status_label(),
            'ref_type'         => (string) $document->ref_type,
            'ref_id'           => $document->ref_id === NULL ? NULL : (string) $document->ref_id,
            'template_code'    => $document->template_code === NULL ? NULL : (string) $document->template_code,
            'template_id'      => $document->template_id === NULL ? NULL : (int) $document->template_id,
            'template_version' => $document->template_version === NULL ? NULL : (int) $document->template_version,
            'print_count'      => (int) $document->print_count,
            'issued_at'        => $document->issued_at === NULL ? NULL : (string) $document->issued_at,
            'printed_at'       => $document->printed_at === NULL ? NULL : (string) $document->printed_at,
            'created_at'       => (string) $document->created_at,
        );
    }

    /**
     * Сводка: сколько выпущено, напечатано и ждёт выпуска.
     *
     * @param   array   $filters    from, to, ref_type
     * @return  array
     */
    public function stats(array $filters = array())
    {
        $query = DB::select(
            DB::expr("COUNT(*) AS total"),
            DB::expr("COUNT(*) FILTER (WHERE status = 'draft') AS drafts"),
            DB::expr("COUNT(*) FILTER (WHERE status IN ('issued', 'printed')) AS issued"),
            DB::expr("COUNT(*) FILTER (WHERE status = 'archived') AS archived"),
            DB::expr("COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled"),
            DB::expr("COALESCE(SUM(print_count), 0) AS print_runs")
        )->from(self::TABLE);

        if ($from = Arr::get($filters, 'from', NULL))
        {
            $query->where('created_at', '>=', Erp::day($from).' 00:00:00');
        }

        if ($to = Arr::get($filters, 'to', NULL))
        {
            $query->where('created_at', '<=', Erp::day($to).' 23:59:59');
        }

        if (($ref_type = Arr::get($filters, 'ref_type', NULL)) !== NULL AND $ref_type !== '')
        {
            $query->where('ref_type', '=', (string) $ref_type);
        }

        $row = $query->execute()->current();

        return array(
            'total'      => (int) $row['total'],
            'drafts'     => (int) $row['drafts'],
            'issued'     => (int) $row['issued'],
            'archived'   => (int) $row['archived'],
            'cancelled'  => (int) $row['cancelled'],
            'print_runs' => (int) $row['print_runs'],
        );
    }

    /**
     * Сколько документов выпущено по каждому типу объекта — «что мы печатаем».
     *
     * @param   array   $filters    from, to
     * @param   int     $limit
     * @return  array
     */
    public function by_ref_type(array $filters = array(), $limit = 10)
    {
        $query = DB::select('ref_type', DB::expr('COUNT(*) AS docs'), DB::expr('COALESCE(SUM(print_count), 0) AS print_runs'))
            ->from(self::TABLE)
            ->group_by('ref_type')
            ->order_by(DB::expr('COUNT(*)'), 'DESC')
            ->limit($limit);

        if ($from = Arr::get($filters, 'from', NULL))
        {
            $query->where('created_at', '>=', Erp::day($from).' 00:00:00');
        }

        if ($to = Arr::get($filters, 'to', NULL))
        {
            $query->where('created_at', '<=', Erp::day($to).' 23:59:59');
        }

        $rows = $query->execute()->as_array();

        $types = Model_Erp404_Template::ref_types();

        foreach ($rows as $index => $row)
        {
            $rows[$index]['docs']       = (int) $row['docs'];
            $rows[$index]['print_runs'] = (int) $row['print_runs'];
            $rows[$index]['label']      = isset($types[$row['ref_type']]) ? $types[$row['ref_type']] : (string) $row['ref_type'];
        }

        return $rows;
    }

    /**
     * Запись в журнал действий (erp119) — мягко: журнал может быть выключен.
     *
     * @param   Model_Erp404_Document   $document
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   int|null                $user_id
     * @param   string|null             $message
     * @return  void
     */
    protected function _journal(Model_Erp404_Document $document, $action, array $before, array $after, $user_id = NULL, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('document', (string) $document->uuid, $action, $before, $after, array(
                'module'       => 'erp404',
                'entity_label' => (string) $document->number,
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
