<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис технологических карт (erp228_tech_cards) — вся бизнес-логика модуля.
 *
 * Правила модуля:
 *   * данные меняются только через сервис (контроллеры и задачи — тонкие обёртки);
 *   * удаление мягкое (is_deleted), версии документов сохраняются в истории;
 *   * актуальная версия документа у карты ровно одна (гарантия — частичный
 *     уникальный индекс в БД плюс транзакция в save_document());
 *   * поиск идёт по колонке search_text: кластер PostgreSQL создан с локалью C,
 *     поэтому lower()/ILIKE не складывают регистр кириллицы;
 *   * каждое значимое действие публикует событие erp228.* и пишет строку в лог.
 *
 * Примеры:
 *
 *     $service = Service_Erp228_TechCards::instance();
 *
 *     $card = $service->create(array(
 *         'title'           => 'Кронштейн датчика',
 *         'source_code'     => 'cube([60, 40, 35]);',
 *         'production_type' => 'additive',
 *         'target_type'     => 'parametric',
 *     ), $user_id);
 *
 *     $service->generate($card->id, $user_id);      // LLM или офлайн-шаблон
 *     $page = $service->search(array('term' => 'кронштейн'));
 *
 * @package    Erp228
 * @category   Services
 */
class Service_Erp228_TechCards
{
    /** @var Service_Erp228_TechCards */
    protected static $_instance;

    /**
     * @return Service_Erp228_TechCards
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // -------------------------------------------------------------------------
    //  Чтение
    // -------------------------------------------------------------------------

    /**
     * Карта по числовому id или публичному uuid.
     *
     * @param  int|string $id_or_uuid
     * @param  bool       $with_deleted
     * @return Model_Erp228_TechCard
     * @throws Kohana_Exception  если карта не найдена
     */
    public function get($id_or_uuid, $with_deleted = FALSE)
    {
        $query = ORM::factory('Erp228_TechCard');

        // Идентификатор бывает числовым или uuid; всё остальное — «не найдено».
        // Без проверки формата СУБД падает на приведении к uuid, а без явного
        // отказа `find()` вернул бы первую карту из таблицы.
        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            throw new Kohana_Exception('Технологическая карта #:id не найдена', array(
                ':id' => $id_or_uuid,
            ));
        }

        $query->where($ref[0], '=', $ref[1]);

        if ( ! $with_deleted)
        {
            $query->where('is_deleted', '=', 0);
        }

        $card = $query->find();

        if ( ! $card->loaded())
        {
            throw new Kohana_Exception('Технологическая карта #:id не найдена', array(
                ':id' => $id_or_uuid,
            ));
        }

        return $card;
    }

    /**
     * Поиск карт с фильтрами и постранично.
     *
     * @param  array $filters  term, status, production_type, target_type, include_deleted
     * @param  int   $limit
     * @param  int   $offset
     * @return array  ['rows' => ORM|Model_Erp228_TechCard[], 'total' => int]
     */
    public function search(array $filters = array(), $limit = 20, $offset = 0)
    {
        $total = (int) $this->_search_query($filters)->count_all();

        $rows = $this->_search_query($filters)
            ->order_by('created_at', 'DESC')
            ->limit(max((int) $limit, 1))
            ->offset(max((int) $offset, 0))
            ->find_all();

        return array(
            'rows'  => $rows,
            'total' => $total,
        );
    }

    /**
     * Подсказки для автодополнения (до 10 карт).
     *
     * @param  string $term
     * @return array
     */
    public function suggest($term)
    {
        $result = array();

        foreach ($this->search(array('term' => $term), 10, 0)['rows'] as $card)
        {
            $result[] = array(
                'id'    => (int) $card->id,
                'label' => $card->title,
                'hint'  => $card->status_label().' · '.$card->production_label(),
            );
        }

        return $result;
    }

    /**
     * Сводка по модулю для панели и виджетов.
     *
     * @return array
     */
    public function stats()
    {
        $by_status = array_fill_keys(array_keys(Model_Erp228_TechCard::$statuses), 0);

        $rows = DB::select('status', array(DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_cards')
            ->where('is_deleted', '=', 0)
            ->group_by('status')
            ->execute();

        foreach ($rows as $row)
        {
            $by_status[$row['status']] = (int) $row['cnt'];
        }

        $by_type = array_fill_keys(array_keys(Model_Erp228_TechCard::$production_types), 0);

        $rows = DB::select('production_type', array(DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_cards')
            ->where('is_deleted', '=', 0)
            ->group_by('production_type')
            ->execute();

        foreach ($rows as $row)
        {
            $by_type[$row['production_type']] = (int) $row['cnt'];
        }

        $documents = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_documents')
            ->execute()
            ->get('cnt', 0);

        $operations = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_operations')
            ->execute()
            ->get('cnt', 0);

        $cost = DB::select(array(DB::expr('COALESCE(SUM(cost_amount), 0)'), 'total'))
            ->from('erp_228_tech_operations')
            ->execute()
            ->get('total', 0);

        return array(
            'total'      => array_sum($by_status),
            'by_status'  => $by_status,
            'by_type'    => $by_type,
            'ready'      => isset($by_status['ready']) ? $by_status['ready'] : 0,
            'documents'  => (int) $documents,
            'operations' => (int) $operations,
            'cost_total' => (float) $cost,
        );
    }

    /**
     * История версий документа по карте (свежие сверху).
     *
     * @param  int $card_id
     * @return ORM|Model_Erp228_TechDocument[]
     */
    public function versions($card_id)
    {
        return ORM::factory('Erp228_TechDocument')
            ->where('tech_card_id', '=', (int) $card_id)
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->find_all();
    }

    /**
     * Актуальный документ карты или NULL.
     *
     * @param  int $card_id
     * @return Model_Erp228_TechDocument|null
     */
    public function actual_document($card_id)
    {
        $document = ORM::factory('Erp228_TechDocument')
            ->where('tech_card_id', '=', (int) $card_id)
            ->where('is_actual', '=', 1)
            ->find();

        return $document->loaded() ? $document : NULL;
    }

    /**
     * Операции актуального техпроцесса карты.
     *
     * Возвращает маршрут массивами, а не ORM-объектами: производства
     * (`erp402`) и цех (`erp408`) не должны зависеть от модели чужого модуля.
     *
     * @param   int     $card_id
     * @return  array   sort_order, name, description, duration_sec, cost_amount
     */
    public function operations($card_id)
    {
        $document = $this->actual_document($card_id);

        if ( ! $document)
        {
            return array();
        }

        $result = array();

        $rows = ORM::factory('Erp228_TechOperation')
            ->where('document_id', '=', (int) $document->id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->find_all();

        foreach ($rows as $row)
        {
            $result[] = array(
                'sort_order'   => (int) $row->sort_order,
                'name'         => (string) $row->op_name,
                'description'  => (string) $row->op_description,
                'duration_sec' => (int) $row->duration_sec,
                'cost_amount'  => (float) $row->cost_amount,
            );
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    //  Запись
    // -------------------------------------------------------------------------

    /**
     * Создать карту.
     *
     * @param  array    $data
     * @param  int|null $user_id
     * @return Model_Erp228_TechCard
     * @throws ORM_Validation_Exception
     */
    public function create(array $data, $user_id = NULL)
    {
        $values = $this->_prepare($data);

        $values['uuid']       = Model_Erp228_TechCard::make_uuid();
        $values['status']     = isset($values['status']) ? $values['status'] : 'draft';
        $values['created_by'] = $this->_user_id($user_id);
        $values['is_deleted'] = 0;
        $values['search_text'] = $this->_search_text($values);

        // create() без аргумента сам собирает Validation из rules() модели
        // (uuid и search_text в правилах не участвуют — они служебные)
        $card = ORM::factory('Erp228_TechCard')->values($values);
        $card->create();

        $this->_event('erp228.card.created', $card, $user_id);

        $this->_journal('tech_card', $card->uuid, 'create', array(), $card->as_array(), array(
            'entity_label' => $card->title,
            'message'      => 'Создана техкарта',
        ));

        return $card;
    }

    /**
     * Изменить карту (передаются только изменяемые поля).
     *
     * @param  int      $id
     * @param  array    $data
     * @param  int|null $user_id
     * @return Model_Erp228_TechCard
     * @throws ORM_Validation_Exception
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $card = $this->get($id);

        $before = $card->as_array();

        $values = $this->_prepare($data, $card);

        // Публичный идентификатор и автор не меняются
        unset($values['uuid'], $values['created_by'], $values['status']);

        $values['search_text'] = $this->_search_text($values, $card);

        $card->values($values)->update();

        $this->_event('erp228.card.updated', $card, $user_id);

        $this->_journal('tech_card', $card->uuid, 'update', $before, $card->as_array(), array(
            'entity_label' => $card->title,
            'message'      => 'Изменена техкарта',
        ));

        return $card;
    }

    /**
     * Мягкое удаление карты вместе с документами (история остаётся в БД).
     *
     * @param  int      $id
     * @param  int|null $user_id
     * @return void
     */
    public function delete($id, $user_id = NULL)
    {
        $card = $this->get($id);

        $before = $card->as_array();

        $card->is_deleted = 1;
        $card->save();

        $this->_event('erp228.card.deleted', $card, $user_id);

        $this->_journal('tech_card', $card->uuid, 'delete', $before, $card->as_array(), array(
            'entity_label' => $card->title,
            'message'      => 'Техкарта отправлена в архив',
        ));
    }

    /**
     * Сменить статус генерации.
     *
     * @param  int      $id
     * @param  string   $status
     * @param  string   $error_msg
     * @param  int|null $user_id
     * @return Model_Erp228_TechCard
     */
    public function set_status($id, $status, $error_msg = NULL, $user_id = NULL)
    {
        if ( ! isset(Model_Erp228_TechCard::$statuses[$status]))
        {
            throw new Kohana_Exception('Неизвестный статус техкарты: :status', array(':status' => $status));
        }

        $card = $this->get($id);

        $before = array('status' => $card->status, 'error_msg' => $card->error_msg);

        $card->status    = $status;
        $card->error_msg = ($status === 'error') ? (string) $error_msg : NULL;
        $card->search_text = $this->_search_text(array('status' => $status), $card);
        $card->save();

        $this->_event('erp228.card.status', $card, $user_id);

        $this->_journal('tech_card', $card->uuid, 'status', $before, array(
            'status'    => $card->status,
            'error_msg' => $card->error_msg,
        ), array(
            'entity_label' => $card->title,
            'message'      => 'Статус техкарты: '.(isset(Model_Erp228_TechCard::$statuses[$status]) ? Model_Erp228_TechCard::$statuses[$status] : $status),
        ));

        return $card;
    }

    /**
     * Сохранить новую версию документа и её операции.
     *
     * Предыдущий актуальный документ уходит в историю (is_actual = 0) — это
     * и «версионность», и защита от двух одновременно актуальных версий.
     *
     * @param  int      $card_id
     * @param  array    $document    content_markdown, structured_data, llm_model, tokens_total, gen_time_ms
     * @param  array    $operations  список шагов
     * @param  int|null $user_id
     * @return Model_Erp228_TechDocument
     */
    public function save_document($card_id, array $document, array $operations = array(), $user_id = NULL)
    {
        $card = $this->get($card_id);

        $db = Database::instance();

        $db->begin();

        try
        {
            DB::update('erp_228_tech_documents')
                ->set(array('is_actual' => 0))
                ->where('tech_card_id', '=', (int) $card->id)
                ->where('is_actual', '=', 1)
                ->execute();

            $doc = ORM::factory('Erp228_TechDocument')->values(array(
                'tech_card_id'     => (int) $card->id,
                'is_actual'        => 1,
                'content_markdown' => isset($document['content_markdown']) ? (string) $document['content_markdown'] : '',
                'structured_data'  => json_encode(
                    isset($document['structured_data']) ? $document['structured_data'] : array(),
                    JSON_UNESCAPED_UNICODE
                ),
                'llm_model'    => isset($document['llm_model']) ? (string) $document['llm_model'] : NULL,
                'tokens_total' => isset($document['tokens_total']) ? (int) $document['tokens_total'] : 0,
                'gen_time_ms'  => isset($document['gen_time_ms']) ? (int) $document['gen_time_ms'] : 0,
            ));

            $doc->create();

            $sort = 10;

            foreach ($operations as $operation)
            {
                ORM::factory('Erp228_TechOperation')->values(array(
                    'document_id'    => (int) $doc->id,
                    'sort_order'     => isset($operation['sort_order']) ? (int) $operation['sort_order'] : $sort,
                    'op_name'        => isset($operation['op_name']) ? (string) $operation['op_name'] : '',
                    'op_description' => isset($operation['op_description']) ? (string) $operation['op_description'] : NULL,
                    'duration_sec'   => isset($operation['duration_sec']) ? (int) $operation['duration_sec'] : 0,
                    'cost_amount'    => isset($operation['cost_amount']) ? (float) $operation['cost_amount'] : 0,
                    'params'         => json_encode(
                        isset($operation['params']) ? $operation['params'] : array(),
                        JSON_UNESCAPED_UNICODE
                    ),
                ))->create();

                $sort += 10;
            }
        }
        catch (Exception $e)
        {
            $db->rollback();

            throw $e;
        }

        $db->commit();

        $this->_event('erp228.card.document', $doc, $user_id);

        return $doc;
    }

    /**
     * Сгенерировать техпроцесс по карте (LLM или офлайн-шаблон).
     *
     * @param  int      $id
     * @param  int|null $user_id
     * @return Model_Erp228_TechDocument
     */
    public function generate($id, $user_id = NULL)
    {
        $card = $this->get($id);

        return Service_Erp228_LlmProcessor::instance()->generate($card, $user_id);
    }

    // -------------------------------------------------------------------------
    //  Внутреннее
    // -------------------------------------------------------------------------

    /**
     * Запрос поиска (строится заново: count_all() и выборка не делят состояние).
     *
     * @param  array $filters
     * @return ORM
     */
    protected function _search_query(array $filters)
    {
        $query = ORM::factory('Erp228_TechCard');

        if (empty($filters['include_deleted']))
        {
            $query->where('is_deleted', '=', 0);
        }

        if ( ! empty($filters['status']) AND isset(Model_Erp228_TechCard::$statuses[$filters['status']]))
        {
            $query->where('status', '=', $filters['status']);
        }

        if ( ! empty($filters['production_type']) AND isset(Model_Erp228_TechCard::$production_types[$filters['production_type']]))
        {
            $query->where('production_type', '=', $filters['production_type']);
        }

        if ( ! empty($filters['target_type']) AND isset(Model_Erp228_TechCard::$target_types[$filters['target_type']]))
        {
            $query->where('target_type', '=', $filters['target_type']);
        }

        if ( ! empty($filters['term']))
        {
            $query->where('search_text', 'LIKE', '%'.$this->_lower($filters['term']).'%');
        }

        return $query;
    }

    /**
     * Нормализация входных данных карты (общая для create и update).
     *
     * @param  array                  $data
     * @param  Model_Erp228_TechCard  $card
     * @return array
     */
    protected function _prepare(array $data, $card = NULL)
    {
        $fields = array(
            'title', 'source_code', 'production_type', 'target_type',
            'target_id', 'status', 'error_msg',
        );

        $values = array();

        foreach ($fields as $field)
        {
            if (array_key_exists($field, $data))
            {
                $values[$field] = $data[$field];
            }
        }

        if (array_key_exists('input_params', $data) OR array_key_exists('params', $data))
        {
            $params = array_key_exists('input_params', $data) ? $data['input_params'] : $data['params'];

            if (is_string($params))
            {
                $decoded = json_decode($params, TRUE);
                $params  = is_array($decoded) ? $decoded : array();
            }

            $values['input_params'] = json_encode((array) $params, JSON_UNESCAPED_UNICODE);
        }

        if (isset($values['production_type']) AND ! isset(Model_Erp228_TechCard::$production_types[$values['production_type']]))
        {
            $values['production_type'] = 'additive';
        }

        if (isset($values['target_type']) AND ! isset(Model_Erp228_TechCard::$target_types[$values['target_type']]))
        {
            $values['target_type'] = 'manual';
        }

        if (array_key_exists('target_id', $values) AND $values['target_id'] !== NULL)
        {
            $values['target_id'] = (int) $values['target_id'];
        }

        return $values;
    }

    /**
     * Текст для поиска: наименование, тип производства, статус, автор-ссылка.
     *
     * @param  array                 $values
     * @param  Model_Erp228_TechCard $card
     * @return string
     */
    protected function _search_text(array $values, $card = NULL)
    {
        $pick = function ($field) use ($values, $card) {
            if (array_key_exists($field, $values))
            {
                return $values[$field];
            }

            return $card ? $card->$field : NULL;
        };

        $parts = array(
            $pick('title'),
            $pick('target_type'),
            $pick('production_type'),
        );

        $status = $pick('status');

        if ($status AND isset(Model_Erp228_TechCard::$statuses[$status]))
        {
            $parts[] = Model_Erp228_TechCard::$statuses[$status];
        }

        return $this->_lower(implode(' ', array_filter($parts)));
    }

    /**
     * @param  mixed $value
     * @return string
     */
    protected function _lower($value)
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    /**
     * @param  int|null $user_id
     * @return int|null
     */
    protected function _user_id($user_id = NULL)
    {
        if ($user_id !== NULL)
        {
            return (int) $user_id;
        }

        return NULL;
    }

    /**
     * Публикация события модуля.
     *
     * @param  string $name
     * @param  ORM    $object
     * @param  int|null $user_id
     * @return void
     */
    /**
     * Записать изменение в журнал действий (erp119_audit), если он включён.
     *
     * Сбой журнала не должен ломать бизнес-операцию: ошибки записи
     * обрабатываются внутри журнала (уходят в файл-страховку), а здесь
     * остаётся только страховка от отсутствия модуля.
     *
     * @param  string     $entity_type
     * @param  string     $entity_id
     * @param  string     $action
     * @param  array      $before
     * @param  array      $after
     * @param  array      $options
     * @return void
     */
    protected function _journal($entity_type, $entity_id, $action, array $before, array $after, array $options = array())
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            $options['module'] = isset($options['module']) ? $options['module'] : 'erp228';

            Service_Erp119_Journal::instance()->change($entity_type, $entity_id, $action, $before, $after, $options);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp228: запись в журнал не удалась — :error', array(
                ':error' => $e->getMessage(),
            ));
        }
    }

    protected function _event($name, $object, $user_id = NULL)
    {
        $payload = array(
            'event'   => $name,
            'id'      => (int) $object->id,
            'user_id' => $this->_user_id($user_id),
            'at'      => date('Y-m-d H:i:s'),
        );

        if ($object instanceof Model_Erp228_TechCard)
        {
            $payload['uuid']   = $object->uuid;
            $payload['title']  = $object->title;
            $payload['status'] = $object->status;
        }
        elseif ($object instanceof Model_Erp228_TechDocument)
        {
            $payload['tech_card_id'] = (int) $object->tech_card_id;
            $payload['is_actual']    = (int) $object->is_actual;
        }

        try
        {
            Event::fire($name, $payload);
        }
        catch (Exception $e)
        {
            // Сбой подписчика не должен ломать бизнес-операцию
            Kohana::$log->add(Log::WARNING, 'erp228: подписчик события :event упал — :error', array(
                ':event' => $name,
                ':error' => $e->getMessage(),
            ));
        }

        Kohana::$log->add(Log::INFO, 'erp228: :event', array(
            ':event' => $name.': '.json_encode($payload, JSON_UNESCAPED_UNICODE),
        ));
    }
}
