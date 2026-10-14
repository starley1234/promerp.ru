<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Свободные объекты модуля (erp199_sqr).
 *
 * Это объекты, для которых в системе нет владельца: оснастка, тара, бумажный
 * документ, «что-то на фотографии». Всё, что относится к номенклатуре, партиям,
 * заказам и сотрудникам, живёт в своих модулях — здесь только ссылка на них
 * (см. `Service_Erp199_Targets`).
 *
 * Распознавание фото (Vision) необязательно: если LLM-модуль недоступен или
 * вернул мусор, объект всё равно создаётся — в статусе `draft`, с тем, что
 * оператор написал руками, и попадает на модерацию.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Objects
{
    /** @var Service_Erp199_Objects|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Objects
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Создать свободный объект.
     *
     * @param   array   $data   type_code, name, brand, model, serial_number, description, attributes
     * @param   integer $actor
     * @param   string  $source manual|ai|scan|import
     * @return  array
     */
    public function create(array $data, $actor = NULL, $source = 'manual')
    {
        $name = trim((string) Arr::get($data, 'name', ''));

        if ($name === '')
        {
            throw new Kohana_Exception('Укажите название объекта');
        }

        $type_id = $this->resolve_type(Arr::get($data, 'type_code', Arr::get($data, 'type')));

        $attributes = Arr::get($data, 'attributes', array());

        if ( ! is_array($attributes))
        {
            $attributes = array();
        }

        $result = DB::insert('erp_199_objects', array(
                'type_id', 'status', 'source', 'name', 'brand', 'model', 'serial_number',
                'description', 'attributes', 'specs', 'ai_confidence', 'ai_raw',
                'base_lat', 'base_lon', 'created_by', 'updated_by',
            ))
            ->values(array(
                $type_id,
                'draft',
                in_array($source, array('manual', 'ai', 'scan', 'import'), TRUE) ? $source : 'manual',
                mb_substr($name, 0, 255),
                Arr::get($data, 'brand') ?: NULL,
                Arr::get($data, 'model') ?: NULL,
                Arr::get($data, 'serial_number') ?: NULL,
                Arr::get($data, 'description') ?: NULL,
                json_encode($attributes, JSON_UNESCAPED_UNICODE),
                json_encode((array) Arr::get($data, 'specs', array()), JSON_UNESCAPED_UNICODE),
                Arr::get($data, 'ai_confidence') !== NULL ? (float) $data['ai_confidence'] : NULL,
                Arr::get($data, 'ai_raw') ? json_encode($data['ai_raw'], JSON_UNESCAPED_UNICODE) : NULL,
                Arr::get($data, 'base_lat'),
                Arr::get($data, 'base_lon'),
                $actor ?: NULL,
                $actor ?: NULL,
            ))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        $this->record_history($id, 'create', $actor, $data);

        return $this->get($id);
    }

    /**
     * Обновить поля свободного объекта.
     *
     * @param   integer $id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     */
    public function update($id, array $data, $actor = NULL)
    {
        $object = $this->get($id);

        $allowed = array('name', 'brand', 'model', 'serial_number', 'description', 'moderator_comment');
        $update  = array();
        $before  = array();

        foreach ($allowed as $field)
        {
            if (array_key_exists($field, $data))
            {
                $before[$field] = $object[$field];
                $update[$field] = $data[$field] !== NULL ? (string) $data[$field] : NULL;
            }
        }

        if (array_key_exists('type_code', $data) OR array_key_exists('type', $data))
        {
            $before['type_id'] = (int) $object['type_id'];
            $update['type_id'] = $this->resolve_type(Arr::get($data, 'type_code', Arr::get($data, 'type')));
        }

        if (array_key_exists('attributes', $data) AND is_array($data['attributes']))
        {
            $before['attributes'] = Service_Erp199_Cast::json($object['attributes']);
            $update['attributes'] = json_encode($data['attributes'], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('specs', $data) AND is_array($data['specs']))
        {
            $before['specs'] = Service_Erp199_Cast::json($object['specs']);
            $update['specs'] = json_encode($data['specs'], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('base_lat', $data) OR array_key_exists('base_lon', $data))
        {
            $update['base_lat'] = Arr::get($data, 'base_lat', $object['base_lat']);
            $update['base_lon'] = Arr::get($data, 'base_lon', $object['base_lon']);
        }

        if ( ! $update)
        {
            return $object;
        }

        $update['updated_by'] = $actor ?: NULL;
        $update['updated_at'] = DB::expr('CURRENT_TIMESTAMP');

        DB::update('erp_199_objects')->set($update)->where('id', '=', (int) $object['id'])->execute();

        $this->record_history($object['id'], 'update', $actor, array('before' => $before, 'after' => $update));

        return $this->get($object['id']);
    }

    /**
     * Решение модератора по черновику: подтвердить, отклонить, в архив.
     *
     * @param   integer $id
     * @param   string  $status  active|rejected|archived|draft
     * @param   integer $actor
     * @param   string  $comment
     * @return  array
     */
    public function moderate($id, $status, $actor = NULL, $comment = NULL)
    {
        if ( ! in_array($status, array('draft', 'active', 'rejected', 'archived'), TRUE))
        {
            throw new Kohana_Exception('Недопустимый статус объекта «:status»', array(':status' => (string) $status));
        }

        $object = $this->get($id);

        DB::update('erp_199_objects')
            ->set(array(
                'status'            => $status,
                'moderator_comment' => $comment ?: NULL,
                'updated_by'        => $actor ?: NULL,
                'updated_at'        => DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->where('id', '=', (int) $object['id'])
            ->execute();

        $result = $this->get($object['id']);

        $this->record_history($object['id'], 'moderation', $actor, array(
            'from' => $object['status'], 'to' => $status, 'comment' => $comment,
        ));

        try
        {
            Event::fire('erp199.object.moderated', array(
                'object_id' => (int) $result['id'],
                'uuid'      => (string) $result['uuid'],
                'status'    => $status,
                'actor_id'  => $actor ?: NULL,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp199: событие модерации недоступно — :error', array(':error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Мягкое удаление (запись остаётся в истории).
     *
     * @param   integer $id
     * @param   integer $actor
     * @return  void
     */
    public function delete($id, $actor = NULL)
    {
        $object = $this->get($id);

        DB::update('erp_199_objects')
            ->set(array('is_deleted' => TRUE, 'updated_by' => $actor ?: NULL, 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
            ->where('id', '=', (int) $object['id'])
            ->execute();

        $this->record_history($object['id'], 'update', $actor, array('is_deleted' => TRUE));
    }

    /**
     * Объект по id или uuid.
     *
     * @param   mixed  $ref
     * @return  array
     */
    public function get($ref)
    {
        $query = DB::select('o.*', array('t.label', 'type_label'), array('t.code', 'type_code'))
            ->from(array('erp_199_objects', 'o'))
            ->join(array('erp_199_types', 't'), 'LEFT')->on('t.id', '=', 'o.type_id');

        if (Erp::is_uuid((string) $ref))
        {
            $query->where('o.uuid', '=', (string) $ref);
        }
        else
        {
            $query->where('o.id', '=', (int) $ref);
        }

        $row = $query->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Объект :ref не найден', array(':ref' => (string) $ref));
        }

        $row['attributes'] = Service_Erp199_Cast::json($row['attributes']);
        $row['specs']      = Service_Erp199_Cast::json($row['specs']);
        $row['is_deleted'] = Service_Erp199_Cast::boolean($row['is_deleted']);
        $row['photos']     = Service_Erp199_Media::instance()->search(array('object_id' => $row['id'], 'limit' => 12));

        return $row;
    }

    /**
     * Список свободных объектов.
     *
     * @param   array   $filters  q, status, type
     * @param   integer $limit
     * @param   integer $offset
     * @return  array   array('rows' => ..., 'total' => ...)
     */
    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit  = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        $query = DB::select('o.*', array('t.label', 'type_label'), array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->from(array('erp_199_objects', 'o'))
            ->join(array('erp_199_types', 't'), 'LEFT')->on('t.id', '=', 'o.type_id')
            ->where('o.is_deleted', '=', FALSE);

        $q = trim((string) Arr::get($filters, 'q', ''));

        if ($q !== '')
        {
            $query->and_where_open()
                ->where('o.name', 'ILIKE', '%'.$q.'%')
                ->or_where('o.serial_number', 'ILIKE', '%'.$q.'%')
                ->or_where('o.brand', 'ILIKE', '%'.$q.'%')
                ->and_where_close();
        }

        if (Arr::get($filters, 'status'))
        {
            $query->where('o.status', '=', (string) $filters['status']);
        }

        if (Arr::get($filters, 'type'))
        {
            $query->where('t.code', '=', (string) $filters['type']);
        }

        $rows = $query->order_by('o.id', 'DESC')->limit($limit)->offset($offset)->execute()->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            unset($row['total']);
            $row['attributes'] = Service_Erp199_Cast::json($row['attributes']);
            $row['is_deleted'] = Service_Erp199_Cast::boolean($row['is_deleted']);
            $row['tokens'] = $this->tokens_of($row['id']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Русские подписи статусов свободного объекта.
     *
     * @param   string  $status
     * @return  string
     */
    public function status_label($status)
    {
        $labels = array(
            'draft'    => 'Черновик (на модерации)',
            'active'   => 'Принят',
            'rejected' => 'Отклонён',
            'archived' => 'В архиве',
        );

        return isset($labels[$status]) ? $labels[$status] : (string) $status;
    }

    /**
     * Цвет бейджа для статуса объекта.
     *
     * @param   string  $status
     * @return  string  ok|warn|bad|info|neutral
     */
    public function state_of($status)
    {
        $map = array(
            'draft'    => 'warn',
            'active'   => 'ok',
            'rejected' => 'bad',
            'archived' => 'neutral',
        );

        return isset($map[$status]) ? $map[$status] : 'neutral';
    }

    /**
     * Статусы объекта для форм и фильтров.
     *
     * @return  array
     */
    public function statuses()
    {
        return array(
            'draft'    => $this->status_label('draft'),
            'active'   => $this->status_label('active'),
            'rejected' => $this->status_label('rejected'),
            'archived' => $this->status_label('archived'),
        );
    }

    /**
     * Наклейки, привязанные к объекту.
     *
     * @param   integer $object_id
     * @return  array
     */
    public function tokens_of($object_id)
    {
        return DB::select('id', 'code', 'status', 'label_note', 'batch')
            ->from('erp_199_tokens')
            ->where('object_id', '=', (int) $object_id)
            ->order_by('id', 'DESC')
            ->execute()
            ->as_array();
    }

    /**
     * История изменений объекта.
     *
     * @param   integer $object_id
     * @param   integer $limit
     * @return  array
     */
    public function history($object_id, $limit = 50)
    {
        $rows = DB::select()->from('erp_199_objects_history')
            ->where('object_id', '=', (int) $object_id)
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->execute()
            ->as_array();

        foreach ($rows as &$row)
        {
            $row['changed_data'] = Service_Erp199_Cast::json($row['changed_data']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Запись истории.
     *
     * @param   integer $object_id
     * @param   string  $type
     * @param   integer $actor
     * @param   array   $data
     * @return  void
     */
    protected function record_history($object_id, $type, $actor, array $data)
    {
        DB::insert('erp_199_objects_history', array('object_id', 'user_id', 'change_type', 'changed_data'))
            ->values(array(
                (int) $object_id,
                $actor ?: NULL,
                in_array($type, array('create', 'update', 'ai_refinement', 'moderation'), TRUE) ? $type : 'update',
                json_encode($data, JSON_UNESCAPED_UNICODE),
            ))
            ->execute();
    }

    /**
     * id типа по коду (или NULL, если код пуст).
     *
     * @param   string|null $code
     * @return  integer|null
     */
    protected function resolve_type($code)
    {
        $code = trim((string) $code);

        if ($code === '')
        {
            return NULL;
        }

        $row = DB::select('id')->from('erp_199_types')->where('code', '=', $code)->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Тип объекта «:code» не найден', array(':code' => $code));
        }

        return (int) $row['id'];
    }

    // =========================================================================
    //  Распознавание фото (Vision) — необязательный помощник
    // =========================================================================

    /**
     * Попробовать распознать объект по фотографии.
     *
     * @param   string  $image_path  абсолютный путь к файлу
     * @param   string  $type_code
     * @param   string  $comment     подсказка оператора
     * @return  array|null           разобранный ответ модели или NULL
     */
    public function recognize($image_path, $type_code = NULL, $comment = NULL)
    {
        if ( ! class_exists('Llm_Factory') OR ! is_file($image_path))
        {
            return NULL;
        }

        $config = (array) Kohana::$config->load('erp199_sqr.ai');
        $prompt = (string) Arr::get($config, 'prompt', '');

        if ($type_code)
        {
            $type = DB::select('ai_prompt_specs', 'expected_attributes')->from('erp_199_types')
                ->where('code', '=', (string) $type_code)->execute()->current();

            if ($type AND $type['ai_prompt_specs'])
            {
                $prompt .= "\nИнструкция по характеристикам: ".$type['ai_prompt_specs'];
            }

            if ($type AND $type['expected_attributes'])
            {
                $attributes = Service_Erp199_Cast::json($type['expected_attributes']);

                if (is_array($attributes) AND $attributes)
                {
                    $prompt .= "\nОбязательно проверь атрибуты: ".implode(', ', $attributes);
                }
            }
        }

        if ($comment)
        {
            $prompt .= "\nДополнительно от оператора: ".$comment;
        }

        try
        {
            $mime = function_exists('mime_content_type') ? mime_content_type($image_path) : 'image/jpeg';

            $messages = array(array(
                'role'    => 'user',
                'content' => array(
                    array('type' => 'text', 'text' => $prompt),
                    array('type' => 'image_url', 'image_url' => array(
                        'url' => 'data:'.$mime.';base64,'.base64_encode(file_get_contents($image_path)),
                    )),
                ),
            ));

            $llm = Llm_Factory::get()->provider((string) Arr::get($config, 'provider', 'local'));

            // Модель не задана в конфиге модуля — пусть ядро LLM само выберет
            // vision-модель провайдера (см. Llm_Service::chat).
            if (Arr::get($config, 'model'))
            {
                $llm->model((string) $config['model']);
            }

            $response = $llm->chat($messages, array('temperature' => 0.1, 'max_tokens' => 1000));

            $content = Arr::path((array) $response, 'choices.0.message.content');

            if ( ! $content)
            {
                return NULL;
            }

            $content = preg_replace('/^```(?:json)?\s*|```$/i', '', trim($content));
            $parsed  = json_decode($content, TRUE);

            return is_array($parsed) ? $parsed : NULL;
        }
        catch (Exception $e)
        {
            // Vision — помощник, а не обязательный шаг: объект создаётся и без него.
            Kohana::$log->add(Log::WARNING, 'erp199: распознавание фото недоступно — :error', array(':error' => $e->getMessage()));

            return NULL;
        }
    }
}
