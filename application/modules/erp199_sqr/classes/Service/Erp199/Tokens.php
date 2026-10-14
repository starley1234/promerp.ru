<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реестр QR-наклеек (erp199_sqr).
 *
 * Наклейка — физическая единица: код печатается заранее и ничего не значит до
 * первой привязки. Здесь вся работа с реестром: выпуск партии, привязка к цели,
 * смена состояния, поиск, статистика и данные для печати листа.
 *
 * Привязка не «доверяет» присланной подписи цели: подпись пересчитывается через
 * адаптеры `Service_Erp199_Targets`, поэтому в списке наклеек не появляется
 * устаревших названий («переименовали станок — наклейка показывает старое»).
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Tokens
{
    /** @var Service_Erp199_Tokens|null */
    protected static $_instance;

    /** Алфавит кода: без 0/O и 1/I — оператор читает код глазами с наклейки. */
    const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** Состояния наклейки, которые разрешено ставить вручную. */
    protected $_states = array('new', 'active', 'blocked', 'damaged', 'lost', 'archived');

    /**
     * @return  Service_Erp199_Tokens
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
     * Выпустить партию кодов для печати.
     *
     * @param   integer $count  сколько наклеек
     * @param   string  $batch  имя партии (если пусто — формируется по дате)
     * @param   integer $actor
     * @param   string  $note   где будет наклеена партия (подсказка)
     * @return  array           array('batch' => ..., 'codes' => array(...), 'ids' => array(...))
     */
    public function issue($count = 10, $batch = NULL, $actor = NULL, $note = NULL)
    {
        $count = max(1, min(500, (int) $count));

        $prefix = (string) Kohana::$config->load('erp199_sqr.code_prefix');
        $batch  = trim((string) $batch);

        if ($batch === '')
        {
            $batch = 'B'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        }

        $codes = array();
        $ids   = array();

        for ($i = 0; $i < $count; $i++)
        {
            $code = $this->generate_code($prefix);

            $result = DB::insert('erp_199_tokens', array(
                    'code', 'batch', 'status', 'target_kind', 'label_note', 'issued_by', 'issued_at',
                ))
                ->values(array($code, $batch, 'new', NULL, $note ?: NULL, $actor ?: NULL, DB::expr('CURRENT_TIMESTAMP')))
                ->execute();

            $ids[]   = is_array($result) ? (int) reset($result) : (int) $result;
            $codes[] = $code;
        }

        $this->log(NULL, 'issue', $actor, NULL, array(
            'batch' => $batch, 'count' => $count, 'codes' => $codes,
        ));

        // Строки наклеек нужны сразу: печать этикеток и клиент смотрят на код,
        // подпись и состояние, а не ходят в базу второй раз.
        $tokens = array();

        foreach ($ids as $id)
        {
            $tokens[] = $this->get($id);
        }

        return array('batch' => $batch, 'codes' => $codes, 'ids' => $ids, 'tokens' => $tokens);
    }

    /**
     * Сгенерировать уникальный код наклейки.
     *
     * @param   string  $prefix
     * @return  string
     */
    protected function generate_code($prefix)
    {
        for ($attempt = 0; $attempt < 20; $attempt++)
        {
            $raw = '';

            for ($i = 0; $i < 8; $i++)
            {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $code = $prefix.substr($raw, 0, 4).'-'.substr($raw, 4);

            $exists = DB::select('id')->from('erp_199_tokens')->where('code', '=', $code)->execute()->current();

            if ( ! $exists)
            {
                return $code;
            }
        }

        throw new Kohana_Exception('Не удалось сгенерировать уникальный код наклейки');
    }

    /**
     * Найти наклейку по коду или uuid (терминал присылает то, что в QR).
     *
     * @param   string  $ref
     * @return  array|null
     */
    public function find($ref)
    {
        $ref = trim((string) $ref);

        if ($ref === '')
        {
            return NULL;
        }

        $query = DB::select()->from('erp_199_tokens');

        if (Erp::is_uuid($ref))
        {
            $query->where('uuid', '=', $ref);
        }
        else
        {
            $query->where('code', '=', strtoupper($ref));
        }

        $row = $query->execute()->current();

        return $row ?: NULL;
    }

    /**
     * Наклейка по id или коду/uuid; исключение, если не найдена.
     *
     * @param   mixed  $ref
     * @return  array
     */
    public function get($ref)
    {
        $row = is_numeric($ref)
            ? DB::select()->from('erp_199_tokens')->where('id', '=', (int) $ref)->execute()->current()
            : $this->find($ref);

        if ( ! $row)
        {
            throw new Kohana_Exception('Наклейка :ref не найдена', array(':ref' => (string) $ref));
        }

        return $row;
    }

    /**
     * Привязать наклейку к цели.
     *
     * @param   mixed   $token     строка наклейки или id
     * @param   string  $kind      вид цели (см. Service_Erp199_Targets::kinds())
     * @param   mixed   $ref       id/uuid/код цели
     * @param   integer $actor
     * @param   string  $comment
     * @return  array              обновлённая наклейка
     */
    public function bind($token, $kind, $ref, $actor = NULL, $comment = NULL)
    {
        $row    = is_array($token) ? $token : $this->get($token);
        $targets = Service_Erp199_Targets::instance();

        if ( ! $targets->supports($kind))
        {
            throw new Kohana_Exception('Неизвестный вид цели «:kind»', array(':kind' => (string) $kind));
        }

        $card = $targets->card($kind, $ref);

        // Прямая ссылка (id/uuid) не сработала — оператор ввёл код или название.
        // Ищем по владельцу данных и берём единственное совпадение: угадывать
        // между двумя одинаковыми названиями сервер не должен.
        if ( ! $card AND ! is_numeric($ref))
        {
            $found = $targets->search($kind, (string) $ref, 2);

            if (count($found) === 1)
            {
                $card = $found[0];
            }
            elseif (count($found) > 1)
            {
                throw new Kohana_Exception('По запросу «:ref» найдено несколько целей — уточните код или id', array(
                    ':ref' => (string) $ref,
                ));
            }
        }

        if ( ! $card)
        {
            throw new Kohana_Exception('Цель :kind/:ref не найдена в системе', array(
                ':kind' => (string) $kind, ':ref' => (string) $ref,
            ));
        }

        if ($row['status'] === 'blocked' OR $row['status'] === 'archived')
        {
            throw new Kohana_Exception('Наклейка :code в состоянии «:status» — привязка запрещена', array(
                ':code' => $row['code'], ':status' => $row['status'],
            ));
        }

        $update = array(
            'target_kind'  => $kind,
            'target_id'    => (int) $card['id'],
            'target_uuid'  => $card['uuid'] ?: NULL,
            'target_label' => mb_substr((string) $card['label'], 0, 255),
            'object_id'    => ($kind === 'local') ? (int) $card['id'] : NULL,
            'status'       => 'active',
            'bound_by'     => $actor ?: NULL,
            'bound_at'     => DB::expr('CURRENT_TIMESTAMP'),
            'updated_at'   => DB::expr('CURRENT_TIMESTAMP'),
        );

        DB::update('erp_199_tokens')->set($update)->where('id', '=', (int) $row['id'])->execute();

        $result = $this->get($row['id']);

        $this->log($result, 'bind', $actor, 'ok', array(
            'target_kind' => $kind,
            'target_id'   => (int) $card['id'],
            'label'       => $card['label'],
        ), $comment);

        $this->fire('erp199.token.bound', $result, array(
            'target_kind' => $kind, 'target_id' => (int) $card['id'], 'actor_id' => $actor ?: NULL,
        ));

        return $result;
    }

    /**
     * Снять привязку (наклейка отклеена от объекта).
     *
     * @param   mixed   $token
     * @param   integer $actor
     * @param   string  $comment
     * @return  array
     */
    public function unbind($token, $actor = NULL, $comment = NULL)
    {
        $row = is_array($token) ? $token : $this->get($token);

        DB::update('erp_199_tokens')
            ->set(array(
                'target_kind' => NULL, 'target_id' => NULL, 'target_uuid' => NULL,
                'target_label' => NULL, 'object_id' => NULL, 'status' => 'new',
                'updated_at' => DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->where('id', '=', (int) $row['id'])
            ->execute();

        $result = $this->get($row['id']);

        $this->log($result, 'unbind', $actor, 'ok', array('previous_label' => $row['target_label']), $comment);

        return $result;
    }

    /**
     * Сменить состояние наклейки (потеряна, повреждена, заблокирована…).
     *
     * @param   mixed   $token
     * @param   string  $status
     * @param   integer $actor
     * @param   string  $comment
     * @return  array
     */
    public function set_status($token, $status, $actor = NULL, $comment = NULL)
    {
        $row = is_array($token) ? $token : $this->get($token);

        if ( ! in_array($status, $this->_states, TRUE))
        {
            throw new Kohana_Exception('Недопустимое состояние наклейки «:status»', array(':status' => (string) $status));
        }

        DB::update('erp_199_tokens')
            ->set(array('status' => $status, 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
            ->where('id', '=', (int) $row['id'])
            ->execute();

        $result = $this->get($row['id']);

        $this->log($result, 'status', $actor, $status, array('from' => $row['status'], 'to' => $status), $comment);

        return $result;
    }

    /**
     * Отметить печать партии (сколько раз печатали и когда).
     *
     * @param   array   $ids
     * @param   integer $actor
     * @return  integer количество отмеченных наклеек
     */
    public function mark_printed(array $ids, $actor = NULL)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ( ! $ids)
        {
            return 0;
        }

        DB::update('erp_199_tokens')
            ->set(array(
                'printed_at'  => DB::expr('CURRENT_TIMESTAMP'),
                'print_count' => DB::expr('print_count + 1'),
            ))
            ->where('id', 'IN', $ids)
            ->execute();

        $this->log(NULL, 'print', $actor, 'ok', array('count' => count($ids), 'ids' => $ids));

        return count($ids);
    }

    /**
     * Список наклеек с фильтрами (админка и терминал).
     *
     * @param   array   $filters  q, status, batch, kind, bound
     * @param   integer $limit
     * @param   integer $offset
     * @return  array   array('rows' => ..., 'total' => ...)
     */
    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit  = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        $query = DB::select('t.*', array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->from(array('erp_199_tokens', 't'));

        $q = trim((string) Arr::get($filters, 'q', ''));

        if ($q !== '')
        {
            $query->and_where_open()
                ->where('t.code', 'ILIKE', '%'.$q.'%')
                ->or_where('t.target_label', 'ILIKE', '%'.$q.'%')
                ->or_where('t.label_note', 'ILIKE', '%'.$q.'%')
                ->and_where_close();
        }

        if (Arr::get($filters, 'status'))
        {
            $query->where('t.status', '=', (string) $filters['status']);
        }

        if (Arr::get($filters, 'batch'))
        {
            $query->where('t.batch', '=', (string) $filters['batch']);
        }

        if (Arr::get($filters, 'kind'))
        {
            $query->where('t.target_kind', '=', (string) $filters['kind']);
        }

        $bound = Arr::get($filters, 'bound');

        if ($bound !== NULL AND $bound !== '')
        {
            // Сравнение с NULL через параметр всегда ложно, поэтому «пусто»
            // проверяем явным выражением, а не значением.
            $query->where('t.target_kind', $bound ? 'IS NOT' : 'IS', DB::expr('NULL'));
        }

        $rows = $query->order_by('t.id', 'DESC')->limit($limit)->offset($offset)->execute()->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            unset($row['total']);

            // Живая карточка цели: подпись в реестре — кэш для скорости, но
            // карточка идёт из модуля-владельца (см. README, «Владельцы данных»).
            $row['target'] = $row['target_kind']
                ? Service_Erp199_Targets::instance()->card($row['target_kind'], $row['target_id'])
                : NULL;
            $row['status_label'] = $this->status_label($row['status']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Русские подписи состояний наклейки.
     *
     * @param   string  $status
     * @return  string
     */
    public function status_label($status)
    {
        $labels = array(
            'new'      => 'Новая (не привязана)',
            'active'   => 'Активная',
            'blocked'  => 'Заблокирована',
            'damaged'  => 'Повреждена',
            'lost'     => 'Утеряна',
            'archived' => 'В архиве',
        );

        return isset($labels[$status]) ? $labels[$status] : (string) $status;
    }

    /**
     * Цвет бейджа для состояния наклейки (единый словарь Component::states()).
     *
     * @param   string  $status
     * @return  string  ok|warn|bad|info|neutral
     */
    public function state_of($status)
    {
        $map = array(
            'new'      => 'info',
            'active'   => 'ok',
            'blocked'  => 'warn',
            'damaged'  => 'warn',
            'lost'     => 'bad',
            'archived' => 'neutral',
        );

        return isset($map[$status]) ? $map[$status] : 'neutral';
    }

    /**
     * Состояния наклейки для форм.
     *
     * @return  array
     */
    public function states()
    {
        $result = array();

        foreach ($this->_states as $state)
        {
            $result[$state] = $this->status_label($state);
        }

        return $result;
    }

    /**
     * Сводка для дашборда.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'new')"), 'new'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'active')"), 'active'),
                array(DB::expr("COUNT(*) FILTER (WHERE status IN ('blocked','damaged','lost'))"), 'problem'),
                array(DB::expr('COALESCE(SUM(scans_count), 0)'), 'scans'),
                array(DB::expr('COUNT(DISTINCT batch)'), 'batches')
            )
            ->from('erp_199_tokens')
            ->execute()
            ->current();

        return array(
            'total'   => (int) $row['total'],
            'new'     => (int) $row['new'],
            'active'  => (int) $row['active'],
            'problem' => (int) $row['problem'],
            'scans'   => (int) $row['scans'],
            'batches' => (int) $row['batches'],
        );
    }

    /**
     * Журнал модуля: что происходило с наклейками и объектами.
     *
     * Это чтение erp_199_activity «снаружи» — для админки и разбора инцидентов.
     * По одной наклейке историю даёт Scan::activity(); здесь же нужен срез по
     * всем: кто сканировал, что искали, чем закончилось.
     *
     * @param   array   $filters  q (код/подпись/комментарий), action, result, code, user_id, from, to
     * @param   integer $limit
     * @param   integer $offset
     * @return  array   array('rows' => ..., 'total' => ...)
     */
    public function journal(array $filters = array(), $limit = 50, $offset = 0)
    {
        $limit  = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        $query = DB::select('a.*', array(DB::expr('COUNT(*) OVER ()'), 'total'))
            ->from(array('erp_199_activity', 'a'));

        $q = trim((string) Arr::get($filters, 'q', ''));

        if ($q !== '')
        {
            $query->and_where_open()
                ->where('a.token_code', 'ILIKE', '%'.$q.'%')
                ->or_where('a.comment', 'ILIKE', '%'.$q.'%')
                ->or_where(DB::expr("a.payload::text"), 'ILIKE', '%'.$q.'%')
                ->and_where_close();
        }

        if (Arr::get($filters, 'action'))
        {
            $query->where('a.action_type', '=', (string) $filters['action']);
        }

        if (Arr::get($filters, 'result'))
        {
            $query->where('a.result', '=', (string) $filters['result']);
        }

        if (Arr::get($filters, 'code'))
        {
            $query->where('a.token_code', '=', strtoupper(trim((string) $filters['code'])));
        }

        if (Arr::get($filters, 'user_id'))
        {
            $query->where('a.user_id', '=', (int) $filters['user_id']);
        }

        if (Arr::get($filters, 'from'))
        {
            $query->where('a.created_at', '>=', (string) $filters['from']);
        }

        if (Arr::get($filters, 'to'))
        {
            $query->where('a.created_at', '<=', (string) $filters['to']);
        }

        $rows = $query->order_by('a.id', 'DESC')->limit($limit)->offset($offset)->execute()->as_array();

        $total = $rows ? (int) $rows[0]['total'] : 0;

        foreach ($rows as &$row)
        {
            unset($row['total']);
            $row['payload'] = Service_Erp199_Cast::json($row['payload']);
        }
        unset($row);

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Виды действий журнала для фильтра и расшифровки в списке.
     *
     * @return  array
     */
    public function actions()
    {
        return array(
            'issue'     => 'Выпуск наклейки',
            'bind'      => 'Привязка к цели',
            'unbind'    => 'Отвязка',
            'scan'      => 'Сканирование',
            'scenario'  => 'Сценарий выполнен',
            'photo'     => 'Фотография',
            'media'     => 'Файл',
            'status'    => 'Смена состояния',
            'moderate'  => 'Модерация',
            'inventory' => 'Инвентаризация',
            'type'      => 'Тип объекта',
            'scenarios' => 'Настройка сценариев',
        );
    }

    /**
     * Отметить факт сканирования наклейки.
     *
     * @param   integer $token_id
     * @return  void
     */
    public function register_scan($token_id)
    {
        DB::update('erp_199_tokens')
            ->set(array(
                'scans_count'  => DB::expr('scans_count + 1'),
                'last_scan_at' => DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->where('id', '=', (int) $token_id)
            ->execute();
    }

    /**
     * Запись в журнал модуля (erp_199_activity).
     *
     * @param   array|null $token
     * @param   string     $action
     * @param   integer    $actor
     * @param   string     $result
     * @param   array      $payload
     * @param   string     $comment
     * @param   array      $meta     geo_lat, geo_lon, key_id, ip, device, scenario_code, object_id
     * @return  integer    id записи
     */
    public function log($token, $action, $actor = NULL, $result = NULL, array $payload = array(), $comment = NULL, array $meta = array())
    {
        $insert = DB::insert('erp_199_activity', array(
                'token_id', 'token_code', 'object_id', 'user_id', 'key_id', 'action_type',
                'scenario_code', 'result', 'payload', 'comment', 'geo_lat', 'geo_lon', 'ip', 'device',
            ))
            ->values(array(
                $token ? (int) $token['id'] : NULL,
                $token ? (string) $token['code'] : NULL,
                Arr::get($meta, 'object_id', $token ? $token['object_id'] : NULL),
                $actor ?: NULL,
                Arr::get($meta, 'key_id'),
                (string) $action,
                Arr::get($meta, 'scenario_code'),
                $result ?: NULL,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $comment ?: NULL,
                Arr::get($meta, 'geo_lat'),
                Arr::get($meta, 'geo_lon'),
                Arr::get($meta, 'ip'),
                Arr::get($meta, 'device'),
            ))
            ->execute();

        return is_array($insert) ? (int) reset($insert) : (int) $insert;
    }

    /**
     * Публикация события модуля: шина может быть недоступна — не ломаем скан.
     *
     * @param   string  $event
     * @param   array   $token
     * @param   array   $extra
     * @return  void
     */
    protected function fire($event, array $token, array $extra = array())
    {
        if ( ! class_exists('Service_Erp208_EventBus') AND ! class_exists('Event'))
        {
            return;
        }

        try
        {
            Event::fire($event, array(
                'token_id'   => (int) $token['id'],
                'token_uuid' => (string) $token['uuid'],
                'code'       => (string) $token['code'],
                'status'     => (string) $token['status'],
            ) + $extra);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp199: событие :event недоступно — :error', array(
                ':event' => $event, ':error' => $e->getMessage(),
            ));
        }
    }
}
