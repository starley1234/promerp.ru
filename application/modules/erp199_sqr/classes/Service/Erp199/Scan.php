<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сканирование и выполнение сценариев (erp199_sqr).
 *
 * Единственная точка входа для тонкого клиента: «вот код с наклейки» →
 * «вот что это, что с этим можно сделать и какую страницу показать».
 * Приложение не знает ни про модули, ни про их статусы — вся логика здесь.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Scan
{
    /** @var Service_Erp199_Scan|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Scan
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
     * Что показать терминалу по отсканированному коду.
     *
     * @param   string  $code
     * @param   integer $actor
     * @param   array   $meta   geo_lat, geo_lon, key_id, ip, device
     * @return  array
     */
    public function resolve($code, $actor = NULL, array $meta = array())
    {
        $token = Service_Erp199_Tokens::instance()->find($this->clean_code($code));

        // Неизвестный код — не ошибка: наклейку могли выпустить в другой
        // системе или она новая. Терминал предложит завести объект с телефона.
        if ( ! $token)
        {
            return array(
                'state'        => 'unknown',
                'token'        => array('code' => $this->clean_code($code)),
                'target'       => NULL,
                'object'       => NULL,
                'scenarios'    => array(),
                'photos'       => array(),
                'activity'     => array(),
                'can_register' => (bool) Kohana::$config->load('erp199_sqr.allow_register_from_field'),
                'message'      => 'Наклейка не зарегистрирована. Заведите объект с телефона или выпустите партию в админке.',
            );
        }

        Service_Erp199_Tokens::instance()->register_scan($token['id']);

        $targets = Service_Erp199_Targets::instance();
        $target  = $token['target_kind'] ? $targets->card($token['target_kind'], $token['target_id']) : NULL;
        $object  = ($token['target_kind'] === 'local' AND $token['object_id'])
            ? Service_Erp199_Objects::instance()->get($token['object_id'])
            : NULL;

        $scenarios = array();

        if ($token['status'] === 'active' AND $target)
        {
            $scenarios = Service_Erp199_Scenarios::instance()->for_kind($token['target_kind']);
        }

        $inventory = Service_Erp199_Inventories::instance()->open_session();

        // Сканирование активной наклейки — это факт, который нужно записать в
        // журнал: даже если оператор ничего не нажал, «кто-то был здесь» видно.
        if ($token['status'] === 'active')
        {
            Service_Erp199_Tokens::instance()->log($token, 'scan', $actor, 'ok', array(
                'target_kind' => $token['target_kind'],
                'label'       => $target ? $target['label'] : $token['target_label'],
            ), NULL, $meta);
        }

        return array(
            'state'        => $this->state($token, $target),
            'token'        => array(
                'id'           => (int) $token['id'],
                'code'         => (string) $token['code'],
                'uuid'         => (string) $token['uuid'],
                'status'       => (string) $token['status'],
                'status_label' => Service_Erp199_Tokens::instance()->status_label($token['status']),
                'batch'        => $token['batch'],
                'label_note'   => $token['label_note'],
                'target_kind'  => $token['target_kind'],
                'target_label' => $target ? $target['label'] : $token['target_label'],
                'scans_count'  => (int) $token['scans_count'],
                'last_scan_at' => $token['last_scan_at'],
            ),
            'target'       => $target,
            'object'       => $object,
            'scenarios'    => array_map(array($this, 'scenario_for_client'), $scenarios),
            'photos'       => Service_Erp199_Media::instance()->search(array('token_id' => $token['id'], 'limit' => 6)),
            'activity'     => $this->activity($token['id'], 5),
            'inventory'    => $inventory ? Service_Erp199_Inventories::instance()->progress($inventory['id']) : NULL,
            'can_register' => (bool) Kohana::$config->load('erp199_sqr.allow_register_from_field'),
            'message'      => $this->message($token, $target),
        );
    }

    /**
     * Выполнить сценарий над наклейкой.
     *
     * @param   string  $code
     * @param   string  $scenario_code
     * @param   array   $payload  значения полей формы + comment + image (base64)
     * @param   array   $files    $_FILES-совместимый файл (если клиент шлёт multipart)
     * @param   integer $actor
     * @param   array   $meta
     * @return  array
     */
    public function execute($code, $scenario_code, array $payload = array(), array $files = array(), $actor = NULL, array $meta = array())
    {
        $tokens   = Service_Erp199_Tokens::instance();
        $token    = $tokens->get($this->clean_code($code));
        $scenario = Service_Erp199_Scenarios::instance()->get($scenario_code);

        if ($token['status'] !== 'active' OR ! $token['target_kind'])
        {
            throw new Kohana_Exception('Наклейка :code не привязана к объекту — сначала привяжите её', array(':code' => $token['code']));
        }

        if ( ! $scenario['is_active'])
        {
            throw new Kohana_Exception('Сценарий «:label» отключён', array(':label' => $scenario['label']));
        }

        if ($scenario['target_kinds'] AND ! in_array($token['target_kind'], $scenario['target_kinds'], TRUE))
        {
            throw new Kohana_Exception('Сценарий «:label» не применим к этому виду объекта', array(':label' => $scenario['label']));
        }

        $this->validate($scenario, $payload, $files);

        $media = NULL;

        if ( ! empty($files['image']) OR ! empty($payload['image']))
        {
            $media = ! empty($files['image'])
                ? Service_Erp199_Media::instance()->save_upload($files['image'], array(
                    'token_id'    => $token['id'],
                    'object_id'   => $token['object_id'],
                    'purpose'     => Arr::get($payload, 'purpose', 'object'),
                    'uploaded_by' => $actor,
                    'geo_lat'     => Arr::get($meta, 'geo_lat'),
                    'geo_lon'     => Arr::get($meta, 'geo_lon'),
                ))
                : Service_Erp199_Media::instance()->save_base64($payload['image'], array(
                    'token_id'    => $token['id'],
                    'object_id'   => $token['object_id'],
                    'purpose'     => Arr::get($payload, 'purpose', 'object'),
                    'uploaded_by' => $actor,
                    'geo_lat'     => Arr::get($meta, 'geo_lat'),
                    'geo_lon'     => Arr::get($meta, 'geo_lon'),
                ));
        }

        $applied = $this->apply($scenario, $token, $payload, $actor, $meta, $media);

        $tokens->register_scan($token['id']);

        $activity_id = $tokens->log($token, 'scenario', $actor, $applied['result'], array(
            'scenario'  => $scenario['code'],
            'payload'   => array_diff_key($payload, array('image' => TRUE)),
            'applied'   => $applied['detail'],
            'media_id'  => $media ? (int) $media['id'] : NULL,
        ), Arr::get($payload, 'comment'), $meta + array('scenario_code' => $scenario['code']));

        return array(
            'ok'          => TRUE,
            'result'      => $applied['result'],
            'note'        => $applied['note'] ?: $scenario['result_note'],
            'detail'      => $applied['detail'],
            'media'       => $media,
            'activity_id' => (int) $activity_id,
            'target'      => Service_Erp199_Targets::instance()->card($token['target_kind'], $token['target_id']),
        );
    }

    /**
     * Привязать наклейку к существующей цели (терминал: «нашёл в системе»).
     *
     * @param   string  $code
     * @param   string  $kind
     * @param   mixed   $ref
     * @param   integer $actor
     * @param   string  $comment
     * @param   array   $meta
     * @return  array
     */
    public function bind($code, $kind, $ref, $actor = NULL, $comment = NULL, array $meta = array())
    {
        $tokens = Service_Erp199_Tokens::instance();
        $token  = $tokens->find($this->clean_code($code));

        if ( ! $token)
        {
            // Скан «чужого» кода: заводим наклейку, чтобы привязка не терялась.
            $token = $this->register_token($this->clean_code($code), $actor);
        }

        if ($token['target_kind'] AND $token['status'] === 'active')
        {
            throw new Kohana_Exception('Наклейка :code уже привязана к «:label»', array(
                ':code' => $token['code'], ':label' => $token['target_label'],
            ));
        }

        $bound = $tokens->bind($token, $kind, $ref, $actor, $comment);

        return array(
            'ok'     => TRUE,
            'token'  => $bound,
            'code'   => $bound['code'],
            'kind'   => $kind,
            'target' => Service_Erp199_Targets::instance()->card($kind, $ref),
        );
    }

    /**
     * Завести свободный объект с телефона и привязать к нему наклейку.
     *
     * Это тот самый сценарий «наклеил, сфотографировал, описал»: если у
     * системы нет владельца для объекта, он создаётся в erp199 (статус
     * `draft` — ждёт модерации), а наклейка становится активной.
     *
     * @param   string  $code
     * @param   array   $payload  name, brand, model, serial_number, description, type_code, image
     * @param   array   $files
     * @param   integer $actor
     * @param   array   $meta
     * @return  array
     */
    public function register_local($code, array $payload = array(), array $files = array(), $actor = NULL, array $meta = array())
    {
        if ( ! Kohana::$config->load('erp199_sqr.allow_register_from_field'))
        {
            throw new Kohana_Exception('Регистрация объектов с телефона отключена настройкой');
        }

        $tokens = Service_Erp199_Tokens::instance();
        $code   = $this->clean_code($code);
        $token  = $tokens->find($code);

        if ( ! $token)
        {
            $token = $this->register_token($code, $actor);
        }

        if ($token['status'] === 'active')
        {
            throw new Kohana_Exception('Наклейка :code уже привязана', array(':code' => $token['code']));
        }

        $media = NULL;

        if ( ! empty($files['image']) OR ! empty($payload['image']))
        {
            $media = ! empty($files['image'])
                ? Service_Erp199_Media::instance()->save_upload($files['image'], array(
                    'token_id' => $token['id'], 'purpose' => 'object', 'uploaded_by' => $actor,
                    'geo_lat' => Arr::get($meta, 'geo_lat'), 'geo_lon' => Arr::get($meta, 'geo_lon'),
                ))
                : Service_Erp199_Media::instance()->save_base64($payload['image'], array(
                    'token_id' => $token['id'], 'purpose' => 'object', 'uploaded_by' => $actor,
                    'geo_lat' => Arr::get($meta, 'geo_lat'), 'geo_lon' => Arr::get($meta, 'geo_lon'),
                ));
        }

        // Распознавание — помощник: подсказывает название и характеристики,
        // но решение остаётся за оператором (и за модератором потом).
        $recognized = $media
            ? Service_Erp199_Objects::instance()->recognize(
                DOCROOT.$media['file_path'],
                Arr::get($payload, 'type_code'),
                Arr::get($payload, 'comment')
            )
            : NULL;

        $data = array(
            'type_code'     => Arr::get($payload, 'type_code', Arr::get($recognized, 'type', 'tool')),
            'name'          => Arr::get($payload, 'name', Arr::get($recognized, 'name', 'Объект '.$code)),
            'brand'         => Arr::get($payload, 'brand', Arr::get($recognized, 'brand')),
            'model'         => Arr::get($payload, 'model', Arr::get($recognized, 'model')),
            'serial_number' => Arr::get($payload, 'serial_number', Arr::get($recognized, 'serial_number')),
            'description'   => Arr::get($payload, 'description', Arr::get($recognized, 'description')),
            'attributes'    => Arr::get($payload, 'attributes', array()),
            'specs'         => Arr::get($recognized, 'specs', array()),
            'ai_confidence' => Arr::get($recognized, 'confidence'),
            'ai_raw'        => $recognized,
            'base_lat'      => Arr::get($meta, 'geo_lat'),
            'base_lon'      => Arr::get($meta, 'geo_lon'),
        );

        $object = Service_Erp199_Objects::instance()->create($data, $actor, $recognized ? 'ai' : 'scan');

        if ($media)
        {
            DB::update('erp_199_media')->set(array('object_id' => (int) $object['id']))
                ->where('id', '=', (int) $media['id'])->execute();
        }

        $bound = $tokens->bind($token, 'local', $object['id'], $actor, Arr::get($payload, 'comment'));

        return array(
            'ok'         => TRUE,
            'token'      => $bound,
            'code'       => $bound['code'],
            'object'     => $object,
            'media'      => $media,
            'recognized' => $recognized ? TRUE : FALSE,
            'note'       => 'Объект создан и привязан; ждёт модерации.',
        );
    }

    /**
     * Последние записи журнала по наклейке.
     *
     * @param   integer|null  $token_id  NULL — последние действия по всем наклейкам
     * @param   integer       $limit
     * @return  array
     */
    public function activity($token_id, $limit = 10)
    {
        $query = DB::select('id', 'token_code', 'action_type', 'scenario_code', 'result', 'comment', 'user_id', 'payload', 'geo_lat', 'geo_lon', 'created_at')
            ->from('erp_199_activity');

        if ($token_id !== NULL)
        {
            $query->where('token_id', '=', (int) $token_id);
        }

        $rows = $query->order_by('id', 'DESC')
            ->limit(max(1, min(100, (int) $limit)))
            ->execute()
            ->as_array();

        foreach ($rows as &$row)
        {
            $row['payload'] = Service_Erp199_Cast::json($row['payload']);
        }
        unset($row);

        return $rows;
    }

    // =========================================================================
    //  Внутреннее
    // =========================================================================

    /**
     * Зарегистрировать наклейку по коду, пришедшему из QR (кода нет в реестре).
     *
     * @param   string  $code
     * @param   integer $actor
     * @return  array
     */
    protected function register_token($code, $actor = NULL)
    {
        $result = DB::insert('erp_199_tokens', array('code', 'batch', 'status', 'issued_by', 'label_note'))
            ->values(array($code, 'SCAN-'.date('ymd'), 'new', $actor ?: NULL, 'Заведена сканированием'))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        $token = Service_Erp199_Tokens::instance()->get($id);

        Service_Erp199_Tokens::instance()->log($token, 'issue', $actor, 'scan', array('code' => $code));

        return $token;
    }

    /**
     * Состояние для терминала.
     *
     * @param   array       $token
     * @param   array|null  $target
     * @return  string
     */
    protected function state(array $token, $target)
    {
        if (in_array($token['status'], array('blocked', 'archived', 'lost', 'damaged'), TRUE))
        {
            return $token['status'];
        }

        if ($token['status'] === 'new' OR ! $token['target_kind'])
        {
            return 'unbound';
        }

        // Наклейка активна, но цель исчезла из системы — это дефект данных,
        // и оператор должен увидеть его прямо на скане.
        if ( ! $target)
        {
            return 'broken';
        }

        return 'active';
    }

    /**
     * Сообщение оператору.
     *
     * @param   array       $token
     * @param   array|null  $target
     * @return  string
     */
    protected function message(array $token, $target)
    {
        switch ($this->state($token, $target))
        {
            case 'unbound':
                return 'Наклейка не привязана. Укажите, что это за объект.';
            case 'blocked':
                return 'Наклейка заблокирована администратором.';
            case 'archived':
                return 'Наклейка в архиве.';
            case 'lost':
                return 'Наклейка отмечена как утерянная.';
            case 'damaged':
                return 'Наклейка повреждена — замените её.';
            case 'broken':
                return 'Цель наклейки не найдена в системе: объект удалён или перенесён. Обратитесь к администратору.';
        }

        return $target ? $target['label'] : '';
    }

    /**
     * Сценарий в формате для приложения: только то, что нужно для отрисовки.
     *
     * @param   array  $scenario
     * @return  array
     */
    public function scenario_for_client(array $scenario)
    {
        return array(
            'code'             => $scenario['code'],
            'label'            => $scenario['label'],
            'description'      => $scenario['description'],
            'icon'             => $scenario['icon'],
            'requires_photo'   => $scenario['requires_photo'],
            'requires_comment' => $scenario['requires_comment'],
            'fields'           => $scenario['fields'],
            'effect'           => $scenario['effect'],
        );
    }

    /**
     * Проверка обязательных требований сценария.
     *
     * @param   array  $scenario
     * @param   array  $payload
     * @param   array  $files
     * @return  void
     */
    protected function validate(array $scenario, array $payload, array $files)
    {
        if ($scenario['requires_photo'] AND empty($files['image']) AND empty($payload['image']))
        {
            throw new Kohana_Exception('Сценарий «:label» требует фотографию', array(':label' => $scenario['label']));
        }

        if ($scenario['requires_comment'] AND trim((string) Arr::get($payload, 'comment', '')) === '')
        {
            throw new Kohana_Exception('Сценарий «:label» требует комментарий', array(':label' => $scenario['label']));
        }

        foreach ($scenario['fields'] as $field)
        {
            if (empty($field['required']))
            {
                continue;
            }

            $value = Arr::get($payload, $field['name']);

            if ($value === NULL OR (is_string($value) AND trim($value) === ''))
            {
                throw new Kohana_Exception('Заполните поле «:label»', array(':label' => Arr::get($field, 'label', $field['name'])));
            }
        }
    }

    /**
     * Применить эффект сценария.
     *
     * @param   array       $scenario
     * @param   array       $token
     * @param   array       $payload
     * @param   integer     $actor
     * @param   array       $meta
     * @param   array|null  $media
     * @return  array       result (applied|recorded|no_session), note, detail
     */
    protected function apply(array $scenario, array $token, array $payload, $actor, array $meta, $media)
    {
        $effect = $scenario['effect'];
        $kind   = (string) Arr::get($effect, 'kind', 'record');

        switch ($kind)
        {
            case 'set_status':
                $param  = (string) Arr::get($effect, 'status_param', 'status');
                $status = trim((string) Arr::get($payload, $param));

                $done = Service_Erp199_Targets::instance()->set_status(
                    $token['target_kind'], $token['target_id'], $status, $actor
                );

                return array(
                    'result' => $done ? 'applied' : 'recorded',
                    'note'   => $done
                        ? 'Статус изменён: '.$status
                        : 'Статус «'.$status.'» записан в журнал: владелец данных меняет его своим процессом.',
                    'detail' => array('status' => $status, 'owner_applied' => $done),
                );

            case 'set_attributes':
                $values = array_diff_key($payload, array('image' => TRUE, 'comment' => TRUE));
                $store  = (string) Arr::get($effect, 'store_as', '');

                if ($store !== '' AND isset($payload[$store]))
                {
                    $values[$store] = $payload[$store];
                }

                $done = Service_Erp199_Targets::instance()->set_attributes(
                    $token['target_kind'], $token['target_id'], $values, $actor
                );

                $note = $done
                    ? 'Записано: '.implode(', ', array_map(function ($k, $v) { return $k.' = '.(is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)); }, array_keys($values), $values))
                    : 'Отметка записана в журнал: атрибуты цели ведёт её модуль.';

                return array(
                    'result' => $done ? 'applied' : 'recorded',
                    'note'   => mb_substr($note, 0, 250),
                    'detail' => array('values' => $values, 'owner_applied' => $done),
                );

            case 'inventory_scan':
                $inventory = Service_Erp199_Inventories::instance()->open_session();

                if ( ! $inventory)
                {
                    return array(
                        'result' => 'no_session',
                        'note'   => 'Открытой инвентаризации нет: отметка записана в журнал.',
                        'detail' => array('inventory' => NULL),
                    );
                }

                $status = Arr::get($payload, 'condition', 'found');
                $status = in_array($status, array('found', 'missing', 'damaged', 'foreign'), TRUE)
                    ? ($status === 'ok' ? 'found' : $status)
                    : 'found';

                $progress = Service_Erp199_Inventories::instance()->mark($inventory['id'], $token, $status, $actor, array(
                    'media_id' => $media ? (int) $media['id'] : NULL,
                    'comment'  => Arr::get($payload, 'comment'),
                    'geo_lat'  => Arr::get($meta, 'geo_lat'),
                    'geo_lon'  => Arr::get($meta, 'geo_lon'),
                ));

                return array(
                    'result' => 'applied',
                    'note'   => 'Инвентаризация '.$progress['code'].': '.$progress['marked'].' из '.$progress['expected'],
                    'detail' => array('inventory' => $progress),
                );

            case 'record':
            default:
                return array(
                    'result' => 'recorded',
                    'note'   => NULL,
                    'detail' => array(),
                );
        }
    }

    /**
     * Очистить строку кода: приложение может прислать URL целиком.
     *
     * @param   string  $code
     * @return  string
     */
    public function clean_code($code)
    {
        $code = trim((string) $code);

        if ($code === '')
        {
            return '';
        }

        $parts = parse_url($code);
        $path  = isset($parts['path']) ? (string) $parts['path'] : $code;

        if (strpos($path, '/') !== FALSE)
        {
            // Ссылка целиком: код — последний сегмент пути (/qr/QR-XXXX-XXXX).
            $path = basename(rtrim($path, '/'));
        }

        $code = $path;

        if (isset($parts['query']))
        {
            parse_str($parts['query'], $params);

            // Некоторые сканеры передают код параметром: ?token=QR-…
            if ( ! empty($params['token']))
            {
                $code = (string) $params['token'];
            }
        }
        else
        {
            // «код?utm=1» parse_url не разберёт: строка без схемы и «/».
            $code = strtok($code, '?&#');
        }

        return strtoupper(trim(strip_tags((string) $code)));
    }
}
